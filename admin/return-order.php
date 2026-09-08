<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/shop-returns.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);

$orderId = (int) (
    $_GET['id']
    ?? $_POST['order_id']
    ?? 0
);

if ($orderId < 1) {
    header('Location: /orders.php');
    exit;
}

$order = admin_shop_order($db, $orderId);

if (!$order) {
    header('Location: /orders.php');
    exit;
}

$items = admin_shop_order_items($db, $orderId);
$returns = shop_returns_for_order($db, $orderId);

$shippingBoundaryStmt = $db->prepare(
    'SELECT
        COUNT(*)
     FROM shop_order_fulfillments
     WHERE order_id = ?
       AND (
            shipped_at IS NOT NULL
            OR delivered_at IS NOT NULL
            OR LOWER(COALESCE(status, "")) IN (
                "shipped",
                "delivered",
                "fulfilled"
            )
       )'
);

$shippingBoundaryStmt->execute([
    $orderId,
]);

$hasCrossedShippingBoundary =
    (int) $shippingBoundaryStmt->fetchColumn() > 0;

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !moderation_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            /*
             * A physical return can exist only after merchandise has
             * actually crossed the shipping boundary.
             *
             * Pre-shipment cancellations belong in the cancellation /
             * refund workflow and must not create a fake return record or
             * increase inventory that was never physically sent out.
             */
            if (!$hasCrossedShippingBoundary) {
                throw new InvalidArgumentException(
                    'This order has not shipped yet, so there is no physical merchandise return to receive. Cancel or refund the unshipped order instead.'
                );
            }

            $quantities = [];

            foreach (
                (array) ($_POST['return_quantity'] ?? [])
                as $itemId => $quantity
            ) {
                $quantities[(int) $itemId] =
                    max(0, (int) $quantity);
            }

            $result = shop_return_create_received(
                $db,
                $actorUserId,
                $orderId,
                $quantities,
                (string) ($_POST['reason'] ?? ''),
                (string) ($_POST['notes'] ?? '')
            );

            $notice =
                'Return received. '
                . number_format(
                    (int) $result['restocked_quantity']
                )
                . ' tracked item'
                . (
                    (int) $result['restocked_quantity'] === 1
                        ? ''
                        : 's'
                )
                . ' returned to sellable inventory.';

            $items = admin_shop_order_items($db, $orderId);
            $returns = shop_returns_for_order($db, $orderId);
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.return_order',
                ['order_id' => $orderId],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The return could not be recorded.',
                    $reference
                );
        }
    }
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    'Return ' . (string) $order['order_number'];

$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'orders';

$adminPageActions =
    '<a class="admin-button" href="/order.php?id='
    . $orderId
    . '">Back to order</a>';

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
        <p>Returned merchandise</p>
        <h2>Receive Return</h2>
    </div>
</header>

<?php if (!$hasCrossedShippingBoundary): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-box"
        aria-hidden="true"
    ></i>

    <h3>Nothing has shipped yet</h3>

    <p>
        This order has not crossed the shipping boundary, so there is
        no physical merchandise return to receive.
    </p>

    <p>
        If the order needs to be stopped before shipment, use the
        cancellation or refund workflow instead. Inventory must not be
        increased through a return when merchandise was never sent out.
    </p>

    <p>
        <a
            class="admin-button"
            href="/order.php?id=<?= (int) $orderId ?>"
        >
            Back to order
        </a>
    </p>
</div>

<?php else: ?>

<div class="admin-user-action-box">
    <p>
        Record only merchandise that has physically returned to
        Llama Scout and is sellable again. Tracked inventory is
        restored immediately. A later Stripe refund will not
        double-restock the same quantity.
    </p>
</div>

<form method="post" class="admin-commerce-fulfillment-form">

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>
<input
    type="hidden"
    name="order_id"
    value="<?= (int) $orderId ?>"
>

<?php foreach ($items as $item): ?>
<?php
$itemId = (int) $item['id'];
$ordered = max(0, (int) $item['quantity']);
$alreadyReturned =
    shop_return_received_quantity($db, $itemId);
$returnable = max(0, $ordered - $alreadyReturned);
?>

<div class="admin-user-action-box">
    <strong>
        <?= moderation_e((string) $item['product_name']) ?>
        <?php if (!empty($item['variant_name'])): ?>
            &middot; <?= moderation_e((string) $item['variant_name']) ?>
        <?php endif; ?>
    </strong>

    <span>
        Ordered <?= number_format($ordered) ?>
        &middot; Already returned <?= number_format($alreadyReturned) ?>
        &middot; Remaining <?= number_format($returnable) ?>
    </span>

    <label>
        <span>Quantity received now</span>
        <input
            type="number"
            name="return_quantity[<?= $itemId ?>]"
            min="0"
            max="<?= $returnable ?>"
            step="1"
            value="0"
            <?= $returnable < 1 ? 'disabled' : '' ?>
        >
    </label>
</div>

<?php endforeach; ?>

<label>
    <span>Reason</span>
    <input
        type="text"
        name="reason"
        maxlength="255"
        placeholder="Example: Customer return"
    >
</label>

<label>
    <span>Internal notes</span>
    <textarea
        name="notes"
        rows="4"
        maxlength="5000"
        placeholder="Condition, packaging, return details, etc."
    ></textarea>
</label>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Record received return
    </button>
</div>

</form>

<?php endif; ?>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>History</p>
        <h2>Returns</h2>
    </div>
</header>

<?php if (!$returns): ?>

<div class="admin-empty-state">
    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
    <h3>No returns recorded.</h3>
</div>

<?php else: ?>

<div class="admin-commerce-orders-table-wrap">
<table class="admin-commerce-orders-table">

<thead>
<tr>
    <th>Return</th>
    <th>Status</th>
    <th>Reason</th>
    <th>Received</th>
</tr>
</thead>

<tbody>

<?php foreach ($returns as $return): ?>
<tr>
    <td data-label="Return">
        #<?= (int) $return['id'] ?>
    </td>
    <td data-label="Status">
        <span class="admin-status-pill">
            <?= moderation_e(
                ucfirst((string) $return['status'])
            ) ?>
        </span>
    </td>
    <td data-label="Reason">
        <?= moderation_e(
            (string) (
                $return['reason']
                ?: 'Not specified'
            )
        ) ?>
    </td>
    <td data-label="Received">
        <?= moderation_e(
            llama_format_viewer_datetime(
                (string) $return['received_at']
            )
        ) ?>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
