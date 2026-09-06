<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printful-webhook-security.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$notice = '';
$error = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['webhook_action']
                        ?? ''
                    )
                );

            if ($action === 'configure') {
                llama_printful_configure_secure_webhook();

                $notice =
                    'Protected Printful webhook configured.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.printful_webhook'
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The Printful webhook could not be configured.',
                        $reference
                    );
        }
    }
}

$secureActive = false;
$statusError = '';

try {
    if (llama_printful_configured()) {
        $secureActive =
            llama_printful_secure_webhook_active();
    }
} catch (Throwable $exception) {
    $statusError =
        $exception->getMessage();
}

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    'Printful Webhook';

$adminPageEyebrow =
    'Integrations';

$adminActiveNav =
    'integrations';

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>
<?php endif; ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Printful</p>
            <h2>Webhook Security</h2>
        </div>

        <a
            class="admin-button"
            href="/integrations.php"
        >
            Back to Integrations
        </a>
    </header>

    <?php if (!llama_printful_configured()): ?>

        <div class="admin-empty-state">
            <i
                class="fa-solid fa-triangle-exclamation"
                aria-hidden="true"
            ></i>

            <h3>Printful is not configured.</h3>

            <p>
                Add the Printful private API token before configuring
                webhook delivery.
            </p>
        </div>

    <?php elseif ($statusError !== ''): ?>

        <div class="admin-user-notice is-error">
            <?= moderation_e($statusError) ?>
        </div>

    <?php else: ?>

        <div
            class="admin-user-notice <?= $secureActive
                ? 'is-success'
                : 'is-warning' ?>"
        >
            <strong>
                <?= $secureActive
                    ? 'Protected webhook is active.'
                    : 'Webhook needs to be protected.' ?>
            </strong>

            <p>
                Llama Scout uses an opaque callback key for the
                existing Printful v1 webhook integration. The private
                Printful API token is never placed in the webhook URL.
            </p>
        </div>

        <?php if (!$secureActive): ?>
            <form method="post">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= moderation_e(
                        moderation_csrf_token()
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="webhook_action"
                    value="configure"
                >

                <button
                    class="admin-button"
                    type="submit"
                >
                    <i
                        class="fa-solid fa-shield-halved"
                        aria-hidden="true"
                    ></i>
                    Protect Printful Webhook
                </button>
            </form>
        <?php endif; ?>

    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
