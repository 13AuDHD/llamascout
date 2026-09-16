<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/admin-fulfillment.php';
require_once dirname(__DIR__) . '/app/printify-cancellation.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim((string) ($_POST['printify_order_action'] ?? ''));
            $fulfillmentId = (int) ($_POST['fulfillment_id'] ?? 0);

            if ($action === 'cancel') {
                $result = llama_printify_cancel_fulfillment(
                    $db,
                    $fulfillmentId,
                    $actorUserId
                );

                $notice =
                    'Printify order #' .
                    $result['provider_order_id'] .
                    ' cancelled.';

                if (!empty($result['refund_required'])) {
                    $notice .=
                        ' The customer is still paid and must now be refunded through Stripe.';
                }
            } elseif ($action === 'send-production') {
                $remote = llama_printify_send_to_production(
                    $db,
                    $actorUserId,
                    $fulfillmentId
                );

                $notice =
                    'Printify order sent to production. Current status: ' .
                    ucfirst(
                        str_replace(
                            '-',
                            ' ',
                            llama_printify_remote_status($remote)
                        )
                    ) .
                    '.';
            } elseif ($action === 'refresh') {
                $sync = llama_printify_sync_fulfillment(
                    $db,
                    $fulfillmentId,
                    $actorUserId
                );

                $notice =
                    'Printify fulfillment refreshed. Current status: ' .
                    ucfirst((string) $sync['local_status']) .
                    '.';
            }
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.printify_orders',
                [],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The Printify order could not be updated.',
                    $reference
                );
        }
    }
}

$fulfillments = llama_printify_active_fulfillments($db, 200);
$rows = [];

foreach ($fulfillments as $fulfillment) {
    $providerOrderId = trim((string) ($fulfillment['provider_order_id'] ?? ''));
    $remoteOrder = [];
    $remoteError = '';

    if ($providerOrderId !== '') {
        try {
            $remoteOrder = llama_printify_get_order($providerOrderId);
        } catch (Throwable $exception) {
            $remoteError = $exception->getMessage();
        }
    }

    $remoteStatus = llama_printify_remote_status($remoteOrder);

    $fulfillment['_remote_order'] = $remoteOrder;
    $fulfillment['_remote_status'] = $remoteStatus;
    $fulfillment['_remote_error'] = $remoteError;
    $fulfillment['_can_cancel'] =
        $remoteError === ''
        && llama_printify_cancellable_status($remoteStatus);
    $fulfillment['_can_send'] =
        $remoteError === ''
        && llama_printify_sendable_status($remoteStatus);

    $rows[] = $fulfillment;
}

$stats = admin_dashboard_stats($db);
$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Printify Orders';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'printify-orders';

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
        <p>Provider Fulfillment</p>
        <h2>Printify Orders</h2>
    </div>

    <div class="admin-user-form-actions">
        <a class="admin-button" href="/printify.php">Variant Mapping</a>
        <a class="admin-button" href="/printify-webhook.php">Webhook</a>
    </div>
</header>

<div class="admin-user-notice admin-printify-order-warning">
    <strong>Provider cancellation and customer refund are separate.</strong>
    <p>
        Cancelling an unpaid Printify order stops the provider order. It does not
        refund a customer payment collected by Llama Scout. Paid customer orders
        are moved to Problem until the Stripe refund workflow is completed.
    </p>
</div>

<div class="admin-user-notice is-warning admin-printify-order-warning">
    <strong>Keep Printify Order approval set to Manual while testing.</strong>
    <p>
        Llama Scout can wait for an explicit Send to production action, but Printify
        can also auto-approve new orders from the store settings on its own.
    </p>
</div>

<?php if (!$rows): ?>
<div class="admin-empty-state">
    <i aria-hidden="true"><?= llama_icon('packages') ?></i>
    <h3>No Printify orders yet.</h3>
    <p>Printify fulfillments will appear here after a paid Shop order is created in Printify.</p>
</div>
<?php else: ?>

<div class="admin-commerce-orders-table-wrap">
<table class="admin-commerce-orders-table">
<thead>
<tr>
    <th>Llama Scout Order</th>
    <th>Printify Order</th>
    <th>Remote Status</th>
    <th>Local Status</th>
    <th>Payment</th>
    <th><span class="sr-only">Actions</span></th>
</tr>
</thead>
<tbody>

<?php foreach ($rows as $row): ?>
<?php
$remoteStatus = (string) ($row['_remote_status'] ?? '');
$remoteError = (string) ($row['_remote_error'] ?? '');
$remoteOrder = is_array($row['_remote_order'] ?? null) ? $row['_remote_order'] : [];
$connectUrl = trim((string) ($remoteOrder['printify_connect']['url'] ?? ''));
?>
<tr>
<td data-label="Llama Scout Order">
    <a class="admin-commerce-order-number" href="/order.php?id=<?= (int) $row['order_id'] ?>">
        <?= moderation_e((string) $row['order_number']) ?>
    </a>
</td>
<td data-label="Printify Order">#<?= moderation_e((string) $row['provider_order_id']) ?></td>
<td data-label="Remote Status">
    <?php if ($remoteError !== ''): ?>
        <span class="admin-status-pill">Unavailable</span>
        <small class="admin-printify-remote-error"><?= moderation_e($remoteError) ?></small>
    <?php else: ?>
        <span class="admin-status-pill">
            <?= moderation_e(ucwords(str_replace(['-', '_'], ' ', $remoteStatus !== '' ? $remoteStatus : 'Unknown'))) ?>
        </span>
    <?php endif; ?>
</td>
<td data-label="Local Status"><span class="admin-status-pill"><?= moderation_e(ucfirst((string) ($row['status'] ?? ''))) ?></span></td>
<td data-label="Payment"><span class="admin-status-pill"><?= moderation_e(ucfirst((string) ($row['payment_status'] ?? ''))) ?></span></td>
<td>
<div class="admin-user-form-actions admin-printify-order-actions">
    <a class="admin-button" href="/order.php?id=<?= (int) $row['order_id'] ?>">Manage Order</a>

    <?php if ($connectUrl !== ''): ?>
        <a class="admin-button" href="<?= moderation_e($connectUrl) ?>" target="_blank" rel="noopener">Open in Printify</a>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
        <input type="hidden" name="fulfillment_id" value="<?= (int) $row['id'] ?>">
        <input type="hidden" name="printify_order_action" value="refresh">
        <button class="admin-button" type="submit"><i aria-hidden="true"><?= llama_icon('refresh') ?></i> Refresh</button>
    </form>

    <?php if (!empty($row['_can_send'])): ?>
        <form method="post" onsubmit="return confirm('Send this Printify order to production now? This can authorize production charges.');">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="fulfillment_id" value="<?= (int) $row['id'] ?>">
            <input type="hidden" name="printify_order_action" value="send-production">
            <button class="admin-button" type="submit">Send to production</button>
        </form>
    <?php endif; ?>

    <?php if ((string) ($row['payment_status'] ?? '') === 'paid'): ?>
        <a class="admin-button" href="/refund-order.php?id=<?= (int) $row['order_id'] ?>">Refund Customer</a>
    <?php endif; ?>

    <?php if (!empty($row['_can_cancel'])): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
            <input type="hidden" name="fulfillment_id" value="<?= (int) $row['id'] ?>">
            <input type="hidden" name="printify_order_action" value="cancel">
            <button
                class="admin-button"
                type="submit"
                onclick="return confirm('Cancel this order at Printify? The customer will NOT be refunded by this action.');"
            >Cancel at Printify</button>
        </form>
    <?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>
<?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
