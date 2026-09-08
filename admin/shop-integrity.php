<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

function shop_integrity_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function shop_integrity_issue(
    array &$issues,
    string $severity,
    string $code,
    int $orderId,
    string $orderNumber,
    string $message,
    array $details = []
): void {
    $issues[] = [
        'severity' => $severity,
        'code' => $code,
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'message' => $message,
        'details' => $details,
    ];
}

function shop_integrity_snapshot_tracks_inventory(array $item): bool
{
    $snapshot = json_decode(
        (string) ($item['variant_snapshot_json'] ?? ''),
        true
    );

    if (!is_array($snapshot)) {
        return false;
    }

    return
        strtolower(
            trim((string) ($snapshot['availability'] ?? ''))
        ) !== 'preorder'
        && (int) ($snapshot['track_inventory'] ?? 0) === 1
        && (int) ($snapshot['allow_backorder'] ?? 0) !== 1
        && (int) ($item['variant_id'] ?? 0) > 0;
}

$issues = [];
$schemaWarnings = [];

$requiredTables = [
    'shop_orders',
    'shop_order_items',
    'shop_order_fulfillments',
    'shop_inventory_reservations',
];

foreach ($requiredTables as $table) {
    if (!shop_integrity_table_exists($db, $table)) {
        $schemaWarnings[] = 'Missing table: ' . $table;
    }
}

$hasRestocks = shop_integrity_table_exists(
    $db,
    'shop_inventory_restocks'
);

if (!$hasRestocks) {
    $schemaWarnings[] =
        'Missing table: shop_inventory_restocks. Run the refund/restock migration before relying on refund inventory checks.';
}

if (!$schemaWarnings || count($schemaWarnings) === 1 && !$hasRestocks) {
    /*
     * 1. Payment succeeded but inventory never committed.
     * These are the late-payment inventory conflicts intentionally held
     * in Problem, plus any unexpected paid orders missing inventory.
     */
    $stmt = $db->query(
        'SELECT
            id,
            order_number,
            order_status,
            payment_status,
            inventory_committed_at,
            total_cents,
            currency
         FROM shop_orders
         WHERE payment_status = "paid"
           AND inventory_committed_at IS NULL
         ORDER BY id DESC
         LIMIT 500'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $order) {
        $orderStatus = strtolower(
            trim((string) ($order['order_status'] ?? ''))
        );

        shop_integrity_issue(
            $issues,
            $orderStatus === 'problem' ? 'warning' : 'critical',
            'paid_without_inventory_commit',
            (int) $order['id'],
            (string) $order['order_number'],
            $orderStatus === 'problem'
                ? 'Payment is paid, inventory was not committed, and the order is correctly being held for manual review.'
                : 'Payment is paid but inventory was never committed and the order is not marked Problem.',
            [
                'order_status' => $orderStatus,
                'payment_status' => 'paid',
            ]
        );
    }

    /*
     * 2. Paid order still holding active reservations.
     * Once inventory is committed, reservations should be consumed.
     */
    $stmt = $db->query(
        'SELECT DISTINCT
            o.id,
            o.order_number,
            o.order_status,
            o.inventory_committed_at
         FROM shop_orders o
         INNER JOIN shop_inventory_reservations r
            ON r.order_id = o.id
         WHERE o.payment_status = "paid"
           AND r.status = "active"
         ORDER BY o.id DESC
         LIMIT 500'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $order) {
        shop_integrity_issue(
            $issues,
            'critical',
            'paid_with_active_reservation',
            (int) $order['id'],
            (string) $order['order_number'],
            'A paid order still has an active inventory reservation. Paid inventory should be consumed or the order should be in the explicit Problem recovery state.',
            [
                'inventory_committed_at' =>
                    (string) ($order['inventory_committed_at'] ?? ''),
            ]
        );
    }

    /*
     * 3. Inventory reservation says consumed but the order does not say
     * inventory was committed. This represents contradictory stock truth.
     */
    $stmt = $db->query(
        'SELECT DISTINCT
            o.id,
            o.order_number,
            o.order_status,
            o.payment_status
         FROM shop_orders o
         INNER JOIN shop_inventory_reservations r
            ON r.order_id = o.id
         WHERE r.status = "consumed"
           AND o.inventory_committed_at IS NULL
         ORDER BY o.id DESC
         LIMIT 500'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $order) {
        shop_integrity_issue(
            $issues,
            'critical',
            'consumed_reservation_without_commit',
            (int) $order['id'],
            (string) $order['order_number'],
            'Inventory reservation is consumed but the order has no inventory_committed_at timestamp.',
            [
                'order_status' =>
                    (string) ($order['order_status'] ?? ''),
                'payment_status' =>
                    (string) ($order['payment_status'] ?? ''),
            ]
        );
    }

    /*
     * 4. Active fulfillment must not exist for financially non-paid or
     * refunded orders.
     */
    $stmt = $db->query(
        'SELECT
            o.id,
            o.order_number,
            o.order_status,
            o.payment_status,
            f.id AS fulfillment_id,
            f.status AS fulfillment_status,
            f.fulfillment_provider,
            f.provider_order_id
         FROM shop_orders o
         INNER JOIN shop_order_fulfillments f
            ON f.order_id = o.id
         WHERE o.payment_status <> "paid"
           AND LOWER(COALESCE(f.status, "")) NOT IN (
                "cancelled",
                "canceled",
                "delivered"
           )
         ORDER BY o.id DESC, f.id ASC
         LIMIT 500'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        shop_integrity_issue(
            $issues,
            'critical',
            'active_fulfillment_without_paid_payment',
            (int) $row['id'],
            (string) $row['order_number'],
            'An active fulfillment exists for an order whose payment is not currently paid.',
            [
                'payment_status' =>
                    (string) ($row['payment_status'] ?? ''),
                'order_status' =>
                    (string) ($row['order_status'] ?? ''),
                'fulfillment_id' =>
                    (int) ($row['fulfillment_id'] ?? 0),
                'fulfillment_status' =>
                    (string) ($row['fulfillment_status'] ?? ''),
                'provider' =>
                    (string) ($row['fulfillment_provider'] ?? ''),
                'provider_order_id' =>
                    (string) ($row['provider_order_id'] ?? ''),
            ]
        );
    }

    /*
     * 5. Impossible payment/order state combinations. The database
     * trigger blocks new contradictions, but this catches historical rows.
     */
    $stmt = $db->query(
        'SELECT
            id,
            order_number,
            order_status,
            payment_status
         FROM shop_orders
         WHERE
            (order_status = "refunded" AND payment_status <> "refunded")
            OR
            (payment_status = "refunded" AND order_status <> "refunded")
            OR
            (
                order_status IN (
                    "paid",
                    "processing",
                    "submitted",
                    "shipped",
                    "delivered"
                )
                AND payment_status <> "paid"
            )
            OR
            (order_status = "cancelled" AND payment_status = "paid")
         ORDER BY id DESC
         LIMIT 500'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $order) {
        shop_integrity_issue(
            $issues,
            'critical',
            'payment_order_state_contradiction',
            (int) $order['id'],
            (string) $order['order_number'],
            'Order status contradicts payment status.',
            [
                'order_status' =>
                    (string) ($order['order_status'] ?? ''),
                'payment_status' =>
                    (string) ($order['payment_status'] ?? ''),
            ]
        );
    }

    if ($hasRestocks) {
        /*
         * Build immutable tracked quantities from order item snapshots,
         * then compare them with all recorded restocks.
         */
        $orderStmt = $db->query(
            'SELECT
                id,
                order_number,
                order_status,
                payment_status,
                inventory_committed_at
             FROM shop_orders
             WHERE payment_status = "refunded"
                OR order_status = "refunded"
             ORDER BY id DESC
             LIMIT 500'
        );

        $itemStmt = $db->prepare(
            'SELECT
                id,
                variant_id,
                quantity,
                variant_snapshot_json
             FROM shop_order_items
             WHERE order_id = ?
             ORDER BY id ASC'
        );

        $restockStmt = $db->prepare(
            'SELECT COALESCE(SUM(quantity), 0)
             FROM shop_inventory_restocks
             WHERE order_item_id = ?'
        );

        foreach ($orderStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $order) {
            $itemStmt->execute([(int) $order['id']]);

            foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
                if (!shop_integrity_snapshot_tracks_inventory($item)) {
                    continue;
                }

                $ordered = max(
                    0,
                    (int) ($item['quantity'] ?? 0)
                );

                $restockStmt->execute([
                    (int) $item['id'],
                ]);

                $restocked = max(
                    0,
                    (int) $restockStmt->fetchColumn()
                );

                if (
                    !empty($order['inventory_committed_at'])
                    && $restocked < $ordered
                ) {
                    shop_integrity_issue(
                        $issues,
                        'critical',
                        'refunded_item_not_fully_restocked',
                        (int) $order['id'],
                        (string) $order['order_number'],
                        'A refunded tracked item has not been fully returned to sellable inventory.',
                        [
                            'order_item_id' => (int) $item['id'],
                            'variant_id' => (int) $item['variant_id'],
                            'ordered_quantity' => $ordered,
                            'restocked_quantity' => $restocked,
                        ]
                    );
                }

                if ($restocked > $ordered) {
                    shop_integrity_issue(
                        $issues,
                        'critical',
                        'order_item_over_restocked',
                        (int) $order['id'],
                        (string) $order['order_number'],
                        'An order item has been restocked beyond its original ordered quantity.',
                        [
                            'order_item_id' => (int) $item['id'],
                            'variant_id' => (int) $item['variant_id'],
                            'ordered_quantity' => $ordered,
                            'restocked_quantity' => $restocked,
                        ]
                    );
                }
            }
        }

        /*
         * 7. Any restock attached to an item that exceeds the immutable
         * ordered quantity, regardless of current order state.
         */
        $stmt = $db->query(
            'SELECT
                o.id,
                o.order_number,
                oi.id AS order_item_id,
                oi.variant_id,
                oi.quantity AS ordered_quantity,
                COALESCE(SUM(r.quantity), 0) AS restocked_quantity
             FROM shop_inventory_restocks r
             INNER JOIN shop_order_items oi
                ON oi.id = r.order_item_id
             INNER JOIN shop_orders o
                ON o.id = oi.order_id
             GROUP BY
                o.id,
                o.order_number,
                oi.id,
                oi.variant_id,
                oi.quantity
             HAVING restocked_quantity > ordered_quantity
             ORDER BY o.id DESC
             LIMIT 500'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            shop_integrity_issue(
                $issues,
                'critical',
                'restock_ledger_exceeds_order_quantity',
                (int) $row['id'],
                (string) $row['order_number'],
                'The restock ledger exceeds the order item quantity.',
                [
                    'order_item_id' =>
                        (int) $row['order_item_id'],
                    'variant_id' =>
                        (int) $row['variant_id'],
                    'ordered_quantity' =>
                        (int) $row['ordered_quantity'],
                    'restocked_quantity' =>
                        (int) $row['restocked_quantity'],
                ]
            );
        }
    }
}

usort(
    $issues,
    static function (array $a, array $b): int {
        $weight = [
            'critical' => 0,
            'warning' => 1,
            'info' => 2,
        ];

        $severity =
            ($weight[$a['severity']] ?? 9)
            <=>
            ($weight[$b['severity']] ?? 9);

        if ($severity !== 0) {
            return $severity;
        }

        return
            ($b['order_id'] ?? 0)
            <=>
            ($a['order_id'] ?? 0);
    }
);

$criticalCount = count(
    array_filter(
        $issues,
        static fn(array $issue): bool =>
            $issue['severity'] === 'critical'
    )
);

$warningCount = count(
    array_filter(
        $issues,
        static fn(array $issue): bool =>
            $issue['severity'] === 'warning'
    )
);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Shop Integrity';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'orders';

$adminPageActions =
    '<a class="admin-button" href="/orders.php">Orders</a>';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Commerce Safety</p>
        <h2>Shop Integrity Audit</h2>
    </div>

    <span>
        <?= number_format(count($issues)) ?>
        issue<?= count($issues) === 1 ? '' : 's' ?>
    </span>
</header>

<div class="admin-user-action-box">
    <p>
        This page is read-only. It compares payment, order,
        fulfillment, reservation, refund, and inventory-restock
        state. It does not automatically repair anything.
    </p>
</div>

<?php if ($schemaWarnings): ?>
<div class="admin-user-notice is-error">
    <strong>Schema check needs attention.</strong>
    <ul>
        <?php foreach ($schemaWarnings as $warning): ?>
        <li><?= moderation_e($warning) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="admin-user-detail-grid">
    <div class="admin-user-action-box">
        <strong><?= number_format($criticalCount) ?></strong>
        <span>Critical</span>
    </div>

    <div class="admin-user-action-box">
        <strong><?= number_format($warningCount) ?></strong>
        <span>Manual review</span>
    </div>
</div>

<?php if (!$issues): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-circle-check"
        aria-hidden="true"
    ></i>
    <h3>No integrity problems found.</h3>
    <p>
        The Shop order, payment, inventory, refund, and
        fulfillment checks currently agree.
    </p>
</div>

<?php else: ?>

<div class="admin-commerce-orders-table-wrap">
<table class="admin-commerce-orders-table">

<thead>
<tr>
    <th>Severity</th>
    <th>Order</th>
    <th>Check</th>
    <th>Finding</th>
    <th>Details</th>
</tr>
</thead>

<tbody>

<?php foreach ($issues as $issue): ?>
<tr>

<td data-label="Severity">
    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst((string) $issue['severity'])
        ) ?>
    </span>
</td>

<td data-label="Order">
    <a
        class="admin-commerce-order-number"
        href="/order.php?id=<?= (int) $issue['order_id'] ?>"
    >
        <?= moderation_e(
            (string) $issue['order_number']
        ) ?>
    </a>
</td>

<td data-label="Check">
    <code><?= moderation_e(
        (string) $issue['code']
    ) ?></code>
</td>

<td data-label="Finding">
    <?= moderation_e(
        (string) $issue['message']
    ) ?>
</td>

<td data-label="Details">
    <?php if (!empty($issue['details'])): ?>
        <?php foreach ($issue['details'] as $key => $value): ?>
            <div>
                <strong><?= moderation_e((string) $key) ?>:</strong>
                <?= moderation_e(
                    is_scalar($value)
                        ? (string) $value
                        : json_encode(
                            $value,
                            JSON_UNESCAPED_SLASHES
                            | JSON_UNESCAPED_UNICODE
                        )
                ) ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        None
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
