<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/printful-sync.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $action = trim(
                (string) (
                    $_POST['printful_webhook_action']
                    ?? ''
                )
            );

            if ($action === 'configure') {
                llama_printful_configure_webhook();

                $notice =
                    'Printful webhook synchronization configured.';
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
                        'Printful webhook configuration failed.',
                        $reference
                    );
        }
    }
}

$configuration = [];
$configError = '';

if (llama_printful_configured()) {
    try {
        $configuration =
            llama_printful_webhook_configuration();
    } catch (Throwable $exception) {
        $configError =
            $exception->getMessage();
    }
}

$configuredUrl = trim(
    (string) (
        $configuration['url']
        ?? ''
    )
);

$configuredTypes = is_array(
    $configuration['types']
    ?? null
)
    ? $configuration['types']
    : [];

$isCorrect =
    $configuredUrl
    === llama_printful_webhook_url();

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    'Printful Sync';

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
        <h2>Fulfillment Synchronization</h2>
    </div>

    <span class="admin-status-pill">
        <?= $isCorrect
            ? 'Connected'
            : 'Setup required' ?>
    </span>
</header>


<div class="admin-user-action-box">

<p>
    Llama Scout can receive Printful order and shipment events,
    then verify the current state directly with the Printful API.
    Tracking and fulfillment status can update automatically.
</p>

</div>


<dl class="admin-user-definition-list">

<div>
    <dt>Webhook endpoint</dt>
    <dd>
        <?= moderation_e(
            llama_printful_webhook_url()
        ) ?>
    </dd>
</div>

<div>
    <dt>Printful configuration</dt>
    <dd>
        <?= $isCorrect
            ? 'Correct endpoint configured'
            : (
                $configuredUrl !== ''
                    ? moderation_e($configuredUrl)
                    : 'Not configured'
            ) ?>
    </dd>
</div>

<div>
    <dt>Event types</dt>
    <dd>
        <?= $configuredTypes
            ? moderation_e(
                implode(
                    ', ',
                    $configuredTypes
                )
            )
            : 'None configured' ?>
    </dd>
</div>

</dl>


<?php if ($configError !== ''): ?>

<div class="admin-integration-error">
    <strong>
        Printful webhook status could not be read.
    </strong>

    <p>
        <?= moderation_e($configError) ?>
    </p>
</div>

<?php endif; ?>


<form
    class="admin-user-action-box"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="printful_webhook_action"
    value="configure"
>

<p>
    Configure Printful to send fulfillment events to Llama Scout.
    Printful allows one v1 webhook URL per store, so this replaces
    the current v1 webhook configuration for this store.
</p>

<button
    class="admin-button"
    type="submit"
>
    <?= $isCorrect
        ? 'Refresh Printful webhook configuration'
        : 'Configure Printful synchronization' ?>
</button>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Behavior</p>
        <h2>What Syncs</h2>
    </div>
</header>

<dl class="admin-user-definition-list">

<div>
    <dt>Printful status</dt>
    <dd>
        Draft, submitted, failed, cancelled, and hold states
        update the local fulfillment state.
    </dd>
</div>

<div>
    <dt>Shipment</dt>
    <dd>
        Carrier, tracking number, tracking URL, and shipped time.
    </dd>
</div>

<div>
    <dt>Delivery</dt>
    <dd>
        Printful shipment delivery data can advance the order
        to Delivered when available.
    </dd>
</div>

<div>
    <dt>Safety</dt>
    <dd>
        Webhook data is used only as a notification. Llama Scout
        re-fetches the authoritative order from Printful before
        changing local fulfillment data.
    </dd>
</div>

</dl>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
