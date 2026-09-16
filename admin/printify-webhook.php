<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printify-webhook-security.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim((string) ($_POST['webhook_action'] ?? ''));

            if ($action === 'configure') {
                $configured = llama_printify_configure_secure_webhooks();
                $notice =
                    number_format(count($configured)) .
                    ' signed Printify order webhook' .
                    (count($configured) === 1 ? '' : 's') .
                    ' configured.';
            }
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.printify_webhook'
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The Printify webhooks could not be configured.',
                    $reference
                );
        }
    }
}

$secureActive = false;
$statusError = '';

try {
    if (llama_printify_configured()) {
        $secureActive = llama_printify_secure_webhooks_active();
    }
} catch (Throwable $exception) {
    $statusError = $exception->getMessage();
}

$stats = admin_dashboard_stats($db);
$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Printify Webhook';
$adminPageEyebrow = 'Integrations';
$adminActiveNav = 'integrations';

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success"><?= moderation_e($notice) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error"><?= moderation_e($error) ?></div>
<?php endif; ?>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Printify</p>
        <h2>Signed Order Webhooks</h2>
    </div>
    <a class="admin-button" href="/integrations.php">Back to Integrations</a>
</header>

<?php if (!llama_printify_configured()): ?>
<div class="admin-empty-state">
    <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>
    <h3>Printify is not configured.</h3>
    <p>Add the Printify private API token before configuring webhook delivery.</p>
</div>

<?php elseif ($statusError !== ''): ?>
<div class="admin-user-notice is-error"><?= moderation_e($statusError) ?></div>

<?php else: ?>
<div class="admin-user-notice <?= $secureActive ? 'is-success' : 'is-warning' ?>">
    <strong><?= $secureActive ? 'Required Printify webhooks are installed.' : 'Printify order webhooks need setup.' ?></strong>
    <p>
        Llama Scout validates Printify's X-Pfy-Signature HMAC before processing
        any event, then re-fetches the provider order before changing local state.
    </p>
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="webhook_action" value="configure">
    <button class="admin-button" type="submit">
        <i aria-hidden="true"><?= llama_icon('shield') ?></i>
        <?= $secureActive ? 'Reconfigure signed webhooks' : 'Configure signed webhooks' ?>
    </button>
</form>

<div class="admin-printify-webhook-topics">
    <strong>Order topics</strong>
    <ul>
        <?php foreach (llama_printify_webhook_topics() as $topic): ?>
            <li><code><?= moderation_e($topic) ?></code></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
