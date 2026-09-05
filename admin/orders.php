<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$status = trim(
    (string) ($_GET['status'] ?? '')
);

$payment = trim(
    (string) ($_GET['payment'] ?? '')
);

$orders = admin_shop_orders(
    $db,
    $status,
    $payment
);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Orders';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'orders';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel admin-user-filter-panel">

<form class="admin-commerce-order-filters" method="get">

<label>
    <span>Order status</span>
    <select name="status">
        <option value="">All statuses</option>
        <?php foreach (
            [
                'pending',
                'paid',
                'processing',
                'submitted',
                'shipped',
                'delivered',
                'cancelled',
                'refunded',
                'problem',
            ] as $option
        ): ?>
            <option
                value="<?= moderation_e($option) ?>"
                <?= $status === $option ? 'selected' : '' ?>
            >
                <?= moderation_e(ucfirst($option)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Payment</span>
    <select name="payment">
        <option value="">All payments</option>
        <?php foreach (
            ['pending','paid','failed','refunded'] as $option
        ): ?>
            <option
                value="<?= moderation_e($option) ?>"
                <?= $payment === $option ? 'selected' : '' ?>
            >
                <?= moderation_e(ucfirst($option)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<div>
    <button class="admin-button" type="submit">
        Filter
    </button>

    <a class="admin-button" href="/orders.php">
        Clear
    </a>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Sales</p>
        <h2><?= number_format(count($orders)) ?> orders</h2>
    </div>
</header>

<?php if (!$orders): ?>
<div class="admin-empty-state">
    <i class="fa-solid fa-box" aria-hidden="true"></i>
    <h3>No matching orders.</h3>
</div>
<?php else: ?>

<div class="admin-commerce-orders-table-wrap">
<table class="admin-commerce-orders-table">

<thead>
<tr>
    <th>Order</th>
    <th>Customer</th>
    <th>Items</th>
    <th>Total</th>
    <th>Payment</th>
    <th>Status</th>
    <th>Fulfillment</th>
    <th>Date</th>
    <th><span class="sr-only">Actions</span></th>
</tr>
</thead>

<tbody>

<?php foreach ($orders as $order): ?>
<tr>
    <td data-label="Order">
        <a
            class="admin-commerce-order-number"
            href="/order.php?id=<?= (int) $order['id'] ?>"
        >
            <?= moderation_e((string) $order['order_number']) ?>
        </a>
    </td>

    <td data-label="Customer">
        <?= moderation_e(
            (string) (
                $order['customer_name']
                ?: $order['customer_email']
                ?: 'Guest'
            )
        ) ?>
    </td>

    <td data-label="Items">
        <?= number_format((int) $order['item_count']) ?>
    </td>

    <td data-label="Total">
        <strong>
            <?= moderation_e(
                admin_shop_money(
                    (int) $order['total_cents'],
                    (string) $order['currency']
                )
            ) ?>
        </strong>
    </td>

    <td data-label="Payment">
        <span class="admin-status-pill">
            <?= moderation_e(
                ucfirst(
                    (string) $order['payment_status']
                )
            ) ?>
        </span>
    </td>

    <td data-label="Status">
        <span class="admin-status-pill">
            <?= moderation_e(
                ucfirst(
                    (string) $order['order_status']
                )
            ) ?>
        </span>
    </td>

    <td data-label="Fulfillment">
        <span class="admin-table-muted">
            <?= moderation_e(
                ucfirst(
                    (string) (
                        $order['fulfillment_status']
                        ?: 'Not started'
                    )
                )
            ) ?>
        </span>
    </td>

    <td data-label="Date">
        <span class="admin-table-muted">
            <?= moderation_e((string) $order['created_at']) ?>
        </span>
    </td>

    <td class="admin-commerce-order-action">
        <a
            class="admin-button"
            href="/order.php?id=<?= (int) $order['id'] ?>"
        >
            Manage
        </a>

        <?php if (
            (string) $order['payment_status'] === 'paid'
            && !empty($order['stripe_payment_intent_id'])
        ): ?>
        <a
            class="admin-button"
            href="/refund-order.php?id=<?= (int) $order['id'] ?>"
        >
            Refund
        </a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
