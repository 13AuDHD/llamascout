<?php

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/shop-order-mail.php';


function shop_refund_table_exists(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'shop_refunds'"
    );

    return $stmt
        && (int) $stmt->fetchColumn() > 0;
}


function shop_refund_restock_table_exists(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'shop_inventory_restocks'"
    );

    return $stmt
        && (int) $stmt->fetchColumn() > 0;
}


function shop_refund_for_order(
    PDO $db,
    int $orderId
): ?array {
    if (!shop_refund_table_exists($db)) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM shop_refunds
         WHERE order_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$orderId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function shop_refund_normalize_status(string $status): string
{
    $status = strtolower(trim($status));

    return in_array(
        $status,
        [
            'pending',
            'requires_action',
            'succeeded',
            'failed',
            'canceled',
            'cancelled',
        ],
        true
    )
        ? $status
        : 'pending';
}


function shop_reconcile_full_refund_from_stripe(
    PDO $db,
    int $orderId,
    int $actorUserId
): array {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid Shop order is required.'
        );
    }

    if (!shop_refund_table_exists($db)) {
        throw new RuntimeException(
            'Shop refund database migration is missing.'
        );
    }

    if (!shop_refund_restock_table_exists($db)) {
        throw new RuntimeException(
            'Shop inventory restock database migration is missing.'
        );
    }

    $orderStmt = $db->prepare(
        'SELECT *
         FROM shop_orders
         WHERE id = ?
         LIMIT 1'
    );

    $orderStmt->execute([
        $orderId,
    ]);

    $order =
        $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new InvalidArgumentException(
            'Shop order not found.'
        );
    }

    $paymentIntentId = trim(
        (string) (
            $order['stripe_payment_intent_id']
            ?? ''
        )
    );

    if ($paymentIntentId === '') {
        throw new RuntimeException(
            'This order does not have a Stripe PaymentIntent.'
        );
    }

    $orderTotal = max(
        0,
        (int) (
            $order['total_cents']
            ?? 0
        )
    );

    if ($orderTotal < 1) {
        throw new RuntimeException(
            'This order does not have a valid total.'
        );
    }

    /*
     * Ask Stripe for refunds belonging to this exact PaymentIntent.
     *
     * We do not trust an admin-entered refund ID for reconciliation.
     */
    $refundList = llama_stripe_client()
        ->refunds
        ->all([
            'payment_intent' =>
                $paymentIntentId,
            'limit' =>
                100,
        ]);

    $stripeRefunds = [];

    foreach (
        $refundList->data ?? []
        as $stripeRefund
    ) {
        $refundId = trim(
            (string) (
                $stripeRefund->id
                ?? ''
            )
        );

        if ($refundId === '') {
            continue;
        }

        $stripeRefunds[] =
            $stripeRefund;
    }

    if (!$stripeRefunds) {
        throw new InvalidArgumentException(
            'Stripe does not show any refund for this order PaymentIntent.'
        );
    }

    $successfulRefunds =
        array_values(
            array_filter(
                $stripeRefunds,
                static function ($refund): bool {
                    return strtolower(
                        trim(
                            (string) (
                                $refund->status
                                ?? ''
                            )
                        )
                    ) === 'succeeded';
                }
            )
        );

    if (!$successfulRefunds) {
        throw new InvalidArgumentException(
            'Stripe does not show a succeeded refund for this order.'
        );
    }

    /*
     * Current Llama Scout refund accounting supports one full refund.
     *
     * Multiple succeeded Stripe refunds indicate partial/multi-refund
     * history and must not be collapsed into a fake single full refund.
     */
    if (count($successfulRefunds) !== 1) {
        throw new InvalidArgumentException(
            'Stripe shows multiple succeeded refunds for this order. Automatic reconciliation is not safe.'
        );
    }

    $stripeRefund =
        $successfulRefunds[0];

    $refundId = trim(
        (string) (
            $stripeRefund->id
            ?? ''
        )
    );

    $refundAmount = max(
        0,
        (int) (
            $stripeRefund->amount
            ?? 0
        )
    );

    if ($refundAmount !== $orderTotal) {
        throw new InvalidArgumentException(
            'Stripe shows a succeeded refund, but it is not a full-order refund. Automatic reconciliation is not safe.'
        );
    }

    $refundPaymentIntent = trim(
        (string) (
            $stripeRefund->payment_intent
            ?? ''
        )
    );

    if (
        $refundPaymentIntent !== ''
        && $refundPaymentIntent
            !== $paymentIntentId
    ) {
        throw new RuntimeException(
            'Stripe refund does not belong to this order PaymentIntent.'
        );
    }

    $existing = shop_refund_for_order(
        $db,
        $orderId
    );

    if ($existing) {
        $existingRefundId = trim(
            (string) (
                $existing['stripe_refund_id']
                ?? ''
            )
        );

        if (
            $existingRefundId !== ''
            && $existingRefundId
                !== $refundId
        ) {
            throw new InvalidArgumentException(
                'This order already contains a different local refund record. Automatic reconciliation is not safe.'
            );
        }
    }

    $currency = strtolower(
        trim(
            (string) (
                $stripeRefund->currency
                ?? $order['currency']
                ?? 'usd'
            )
        )
    );

    $reason = trim(
        (string) (
            $stripeRefund->reason
            ?? ''
        )
    );

    if (
        !in_array(
            $reason,
            [
                'requested_by_customer',
                'duplicate',
                'fraudulent',
            ],
            true
        )
    ) {
        $reason =
            'requested_by_customer';
    }

    /*
     * Persist Stripe truth locally.
     *
     * requested_by identifies the admin who performed the Llama Scout
     * reconciliation, not necessarily whoever initiated it in Stripe.
     */
    $stmt = $db->prepare(
        'INSERT INTO shop_refunds
         (
            order_id,
            stripe_refund_id,
            stripe_payment_intent_id,
            amount_cents,
            currency,
            reason,
            status,
            failure_reason,
            requested_by,
            requested_at,
            updated_at
         )
         VALUES
         (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            "succeeded",
            NULL,
            ?,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            stripe_refund_id =
                VALUES(stripe_refund_id),
            stripe_payment_intent_id =
                VALUES(stripe_payment_intent_id),
            amount_cents =
                VALUES(amount_cents),
            currency =
                VALUES(currency),
            reason =
                VALUES(reason),
            status =
                "succeeded",
            failure_reason =
                NULL,
            requested_by =
                VALUES(requested_by),
            updated_at =
                UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $orderId,
        $refundId,
        $paymentIntentId,
        $refundAmount,
        $currency,
        $reason,
        $actorUserId > 0
            ? $actorUserId
            : null,
    ]);

    /*
     * Reuse the same full-refund finalization path as normal refunds.
     *
     * This restores only inventory not already restored by a recorded
     * physical return, then finalizes payment/order state.
     */
    shop_refund_apply_order_status(
        $db,
        $orderId,
        'succeeded',
        $refundId
    );

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            !empty($order['user_id'])
                ? (int) $order['user_id']
                : null,
            'shop.order_refund_reconciled',
            'Reconciled existing Stripe full refund for order '
                . (string) (
                    $order['order_number']
                    ?? $orderId
                )
                . '.',
            [
                'order_id' =>
                    $orderId,
                'stripe_refund_id' =>
                    $refundId,
                'stripe_payment_intent_id' =>
                    $paymentIntentId,
                'amount_cents' =>
                    $refundAmount,
            ]
        );
    }

    return [
        'refund_id' =>
            $refundId,
        'status' =>
            'succeeded',
        'amount_cents' =>
            $refundAmount,
        'currency' =>
            $currency,
    ];
}


function shop_refund_fulfillment_blocker(
    PDO $db,
    int $orderId
): ?string {
    if ($orderId < 1) {
        return 'A valid Shop order is required.';
    }

    $stmt = $db->prepare(
        'SELECT
            status,
            fulfillment_provider,
            provider_order_id,
            tracking_number
         FROM shop_order_fulfillments
         WHERE order_id = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$orderId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fulfillment) {
        $status = strtolower(
            trim((string) ($fulfillment['status'] ?? ''))
        );

        if (in_array($status, ['cancelled', 'canceled'], true)) {
            continue;
        }

        $providerOrderId = trim(
            (string) ($fulfillment['provider_order_id'] ?? '')
        );
        $trackingNumber = trim(
            (string) ($fulfillment['tracking_number'] ?? '')
        );

        if (
            in_array(
                $status,
                [
                    'processing',
                    'submitted',
                    'shipped',
                    'delivered',
                    'fulfilled',
                ],
                true
            )
            || $providerOrderId !== ''
            || $trackingNumber !== ''
        ) {
            return 'This order already has active fulfillment activity. Cancel or resolve fulfillment before issuing the Stripe refund.';
        }
    }

    return null;
}


function shop_refund_item_tracks_inventory(array $item): bool
{
    $snapshot = [];

    if (!empty($item['variant_snapshot_json'])) {
        $decoded = json_decode(
            (string) $item['variant_snapshot_json'],
            true
        );

        if (is_array($decoded)) {
            $snapshot = $decoded;
        }
    }

    $availability = strtolower(
        trim((string) ($snapshot['availability'] ?? ''))
    );

    $trackInventory =
        (int) ($snapshot['track_inventory'] ?? 0);

    $allowBackorder =
        (int) ($snapshot['allow_backorder'] ?? 0);

    return
        (int) ($item['variant_id'] ?? 0) > 0
        && $availability !== 'preorder'
        && $trackInventory === 1
        && $allowBackorder !== 1;
}


function shop_refund_restore_inventory(
    PDO $db,
    int $orderId,
    string $stripeRefundId
): int {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid Shop order is required.'
        );
    }

    $stripeRefundId = trim($stripeRefundId);

    if ($stripeRefundId === '') {
        throw new InvalidArgumentException(
            'A Stripe refund ID is required to restore inventory.'
        );
    }

    if (!shop_refund_restock_table_exists($db)) {
        throw new RuntimeException(
            'Shop inventory restock database migration is missing.'
        );
    }

    $db->beginTransaction();

    try {
        $orderStmt = $db->prepare(
            'SELECT
                id,
                payment_status,
                order_status,
                inventory_committed_at
             FROM shop_orders
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $orderStmt->execute([$orderId]);

        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException(
                'Shop order not found while restoring inventory.'
            );
        }

        /*
         * No inventory was deducted from a checkout that never reached
         * inventory_committed_at. Pending checkout cancellation therefore
         * releases reservations only and must not increase on-hand stock.
         */
        if (empty($order['inventory_committed_at'])) {
            $db->commit();
            return 0;
        }

        $itemStmt = $db->prepare(
            'SELECT
                id,
                variant_id,
                quantity,
                variant_snapshot_json
             FROM shop_order_items
             WHERE order_id = ?
             ORDER BY id ASC
             FOR UPDATE'
        );
        $itemStmt->execute([$orderId]);

        $items =
            $itemStmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        $restockedTotal = 0;

        $alreadyStmt = $db->prepare(
            'SELECT COALESCE(SUM(quantity), 0)
             FROM shop_inventory_restocks
             WHERE order_item_id = ?'
        );

        $variantLock = $db->prepare(
            'SELECT inventory_quantity
             FROM shop_product_variants
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $insertRestock = $db->prepare(
            'INSERT INTO shop_inventory_restocks (
                order_id,
                order_item_id,
                variant_id,
                quantity,
                source_type,
                source_id,
                created_at
             ) VALUES (?, ?, ?, ?, "refund", ?, UTC_TIMESTAMP())'
        );

        $increaseInventory = $db->prepare(
            'UPDATE shop_product_variants
             SET inventory_quantity = inventory_quantity + ?
             WHERE id = ?'
        );

        foreach ($items as $item) {
            if (!shop_refund_item_tracks_inventory($item)) {
                continue;
            }

            $orderItemId =
                (int) ($item['id'] ?? 0);

            $variantId =
                (int) ($item['variant_id'] ?? 0);

            $orderedQuantity =
                max(0, (int) ($item['quantity'] ?? 0));

            if (
                $orderItemId < 1
                || $variantId < 1
                || $orderedQuantity < 1
            ) {
                continue;
            }

            /*
             * Sum every previous restock for this order item, regardless
             * of source. This makes the upper bound the immutable ordered
             * quantity, so later support for partial returns cannot ever
             * over-restock the item.
             */
            $alreadyStmt->execute([$orderItemId]);

            $alreadyRestocked =
                max(0, (int) $alreadyStmt->fetchColumn());

            $remaining =
                max(
                    0,
                    $orderedQuantity - $alreadyRestocked
                );

            if ($remaining < 1) {
                continue;
            }

            $variantLock->execute([$variantId]);

            if ($variantLock->fetchColumn() === false) {
                throw new RuntimeException(
                    'A refunded Shop item references an inventory variant that no longer exists.'
                );
            }

            $insertRestock->execute([
                $orderId,
                $orderItemId,
                $variantId,
                $remaining,
                $stripeRefundId,
            ]);

            $increaseInventory->execute([
                $remaining,
                $variantId,
            ]);

            if ($increaseInventory->rowCount() !== 1) {
                throw new RuntimeException(
                    'Refund inventory could not be restored safely.'
                );
            }

            $restockedTotal += $remaining;
        }

        $db->commit();

        return $restockedTotal;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function shop_refund_apply_order_status(
    PDO $db,
    int $orderId,
    string $refundStatus,
    ?string $stripeRefundId = null
): void {
    $refundStatus = shop_refund_normalize_status(
        $refundStatus
    );

    if ($refundStatus !== 'succeeded') {
        return;
    }

    $stripeRefundId =
        trim((string) $stripeRefundId);

    if ($stripeRefundId === '') {
        throw new RuntimeException(
            'A successful Shop refund is missing its Stripe refund ID.'
        );
    }

    /*
     * This Shop endpoint supports FULL refunds only. Never turn a partial
     * Stripe refund into a fully refunded order or a full inventory
     * restock. The persisted refund row is also our ownership check: a
     * webhook must refer to the exact refund Llama Scout created.
     */
    $integrityStmt = $db->prepare(
        'SELECT
            r.amount_cents AS refund_amount_cents,
            r.status AS stored_refund_status,
            o.total_cents AS order_total_cents
         FROM shop_refunds r
         INNER JOIN shop_orders o
            ON o.id = r.order_id
         WHERE r.order_id = ?
           AND r.stripe_refund_id = ?
         LIMIT 1'
    );

    $integrityStmt->execute([
        $orderId,
        $stripeRefundId,
    ]);

    $integrity = $integrityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$integrity) {
        throw new RuntimeException(
            'The successful Stripe refund does not match a Llama Scout refund record.'
        );
    }

    if (
        shop_refund_normalize_status(
            (string) ($integrity['stored_refund_status'] ?? '')
        ) !== 'succeeded'
    ) {
        throw new RuntimeException(
            'The local refund record is not in a succeeded state.'
        );
    }

    $refundAmount =
        max(0, (int) ($integrity['refund_amount_cents'] ?? 0));

    $orderTotal =
        max(0, (int) ($integrity['order_total_cents'] ?? 0));

    if (
        $orderTotal < 1
        || $refundAmount !== $orderTotal
    ) {
        throw new RuntimeException(
            'A partial or mismatched Stripe refund cannot fully restock this Shop order.'
        );
    }

    /*
     * Restore committed inventory before marking the order refunded.
     * Both operations are independently retry-safe: the restock helper
     * caps each order item at its original ordered quantity, and the
     * order status update is idempotent.
     */
    shop_refund_restore_inventory(
        $db,
        $orderId,
        $stripeRefundId
    );

    $stmt = $db->prepare(
        'UPDATE shop_orders
         SET
            payment_status = "refunded",
            order_status = "refunded",
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $stmt->execute([$orderId]);

    /*
     * Financial state is authoritative. Email is best-effort and
     * retryable through the existing no-cron Shop mail maintenance.
     */
    try {
        shop_send_refund_confirmation(
            $db,
            $orderId
        );
    } catch (Throwable $exception) {
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception(
                $exception,
                'shop.refund_confirmation',
                ['order_id' => $orderId]
            );
        }
    }
}


function shop_issue_full_refund(
    PDO $db,
    int $orderId,
    int $actorUserId,
    string $reason = 'requested_by_customer'
): array {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid Shop order is required.'
        );
    }

    if (!shop_refund_table_exists($db)) {
        throw new RuntimeException(
            'Shop refund database migration is missing.'
        );
    }

    if (!shop_refund_restock_table_exists($db)) {
        throw new RuntimeException(
            'Shop inventory restock database migration is missing.'
        );
    }

    $allowedReasons = [
        'requested_by_customer',
        'duplicate',
        'fraudulent',
    ];

    if (!in_array($reason, $allowedReasons, true)) {
        $reason = 'requested_by_customer';
    }

    $orderStmt = $db->prepare(
        'SELECT *
         FROM shop_orders
         WHERE id = ?
         LIMIT 1'
    );
    $orderStmt->execute([$orderId]);

    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException(
            'Shop order not found.'
        );
    }

    if (
        strtolower(
            trim((string) ($order['payment_status'] ?? ''))
        ) !== 'paid'
    ) {
        throw new InvalidArgumentException(
            'Only a paid order can be refunded.'
        );
    }

    $paymentIntentId = trim(
        (string) ($order['stripe_payment_intent_id'] ?? '')
    );

    if ($paymentIntentId === '') {
        throw new RuntimeException(
            'This order does not have a Stripe PaymentIntent to refund.'
        );
    }

    $fulfillmentBlocker = shop_refund_fulfillment_blocker(
        $db,
        $orderId
    );

    if ($fulfillmentBlocker !== null) {
        throw new InvalidArgumentException(
            $fulfillmentBlocker
        );
    }

    $existing = shop_refund_for_order(
        $db,
        $orderId
    );

    if ($existing) {
        $existingStatus = shop_refund_normalize_status(
            (string) ($existing['status'] ?? '')
        );

        if (
            in_array(
                $existingStatus,
                [
                    'pending',
                    'requires_action',
                    'succeeded',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                $existingStatus === 'succeeded'
                    ? 'This order has already been refunded.'
                    : 'A refund is already in progress for this order.'
            );
        }
    }

    $refund = llama_stripe_client()
        ->refunds
        ->create([
            'payment_intent' => $paymentIntentId,
            'reason' => $reason,
            'metadata' => [
                'llama_checkout_type' => 'shop_refund',
                'llama_order_id' => (string) $orderId,
                'llama_order_number' =>
                    (string) ($order['order_number'] ?? ''),
                'llama_admin_user_id' =>
                    (string) $actorUserId,
            ],
        ]);

    $refundId = trim(
        (string) ($refund->id ?? '')
    );

    if ($refundId === '') {
        throw new RuntimeException(
            'Stripe did not return a refund ID.'
        );
    }

    $refundStatus = shop_refund_normalize_status(
        (string) ($refund->status ?? 'pending')
    );

    $amount = max(
        0,
        (int) ($refund->amount ?? $order['total_cents'] ?? 0)
    );

    $failureReason = trim(
        (string) (
            $refund->failure_reason
            ?? ''
        )
    );

    $stmt = $db->prepare(
        'INSERT INTO shop_refunds
         (
            order_id,
            stripe_refund_id,
            stripe_payment_intent_id,
            amount_cents,
            currency,
            reason,
            status,
            failure_reason,
            requested_by,
            requested_at,
            updated_at
         )
         VALUES
         (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            stripe_refund_id = VALUES(stripe_refund_id),
            stripe_payment_intent_id = VALUES(stripe_payment_intent_id),
            amount_cents = VALUES(amount_cents),
            currency = VALUES(currency),
            reason = VALUES(reason),
            status = VALUES(status),
            failure_reason = VALUES(failure_reason),
            requested_by = VALUES(requested_by),
            requested_at = UTC_TIMESTAMP(),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $orderId,
        $refundId,
        $paymentIntentId,
        $amount,
        strtolower(
            trim(
                (string) (
                    $refund->currency
                    ?? $order['currency']
                    ?? 'usd'
                )
            )
        ),
        $reason,
        $refundStatus,
        $failureReason !== ''
            ? $failureReason
            : null,
        $actorUserId > 0
            ? $actorUserId
            : null,
    ]);

    shop_refund_apply_order_status(
        $db,
        $orderId,
        $refundStatus,
        $refundId
    );

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            !empty($order['user_id'])
                ? (int) $order['user_id']
                : null,
            'shop.order_refund_requested',
            'Requested full Stripe refund for order '
                . (string) ($order['order_number'] ?? $orderId)
                . '.',
            [
                'order_id' => $orderId,
                'stripe_refund_id' => $refundId,
                'amount_cents' => $amount,
                'status' => $refundStatus,
                'reason' => $reason,
            ]
        );
    }

    return [
        'refund_id' => $refundId,
        'status' => $refundStatus,
        'amount_cents' => $amount,
        'currency' => strtolower(
            (string) (
                $refund->currency
                ?? $order['currency']
                ?? 'usd'
            )
        ),
    ];
}


function shop_sync_refund_from_stripe(
    PDO $db,
    object $refund
): bool {
    if (!shop_refund_table_exists($db)) {
        throw new RuntimeException(
            'Shop refund database migration is missing.'
        );
    }

    if (!shop_refund_restock_table_exists($db)) {
        throw new RuntimeException(
            'Shop inventory restock database migration is missing.'
        );
    }

    $refundId = trim(
        (string) ($refund->id ?? '')
    );

    $orderId = (int) (
        $refund->metadata->llama_order_id
        ?? 0
    );

    if ($refundId === '' || $orderId < 1) {
        return false;
    }

    $checkoutType = trim(
        (string) (
            $refund->metadata->llama_checkout_type
            ?? ''
        )
    );

    if ($checkoutType !== 'shop_refund') {
        return false;
    }

    $status = shop_refund_normalize_status(
        (string) ($refund->status ?? 'pending')
    );

    $failureReason = trim(
        (string) (
            $refund->failure_reason
            ?? ''
        )
    );

    $stmt = $db->prepare(
        'UPDATE shop_refunds
         SET
            status = ?,
            failure_reason = ?,
            updated_at = UTC_TIMESTAMP()
         WHERE order_id = ?
           AND stripe_refund_id = ?'
    );

    $stmt->execute([
        $status,
        $failureReason !== ''
            ? $failureReason
            : null,
        $orderId,
        $refundId,
    ]);

    if ($stmt->rowCount() < 1) {
        $knownStmt = $db->prepare(
            'SELECT 1
             FROM shop_refunds
             WHERE order_id = ?
               AND stripe_refund_id = ?
             LIMIT 1'
        );
        $knownStmt->execute([
            $orderId,
            $refundId,
        ]);

        if (!$knownStmt->fetchColumn()) {
            throw new RuntimeException(
                'Stripe refund webhook does not match a known Llama Scout refund.'
            );
        }
    }

    shop_refund_apply_order_status(
        $db,
        $orderId,
        $status,
        $refundId
    );

    return true;
}
