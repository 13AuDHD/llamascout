<?php

declare(strict_types=1);

require_once __DIR__ . '/shop-refunds.php';
require_once __DIR__ . '/shop-returns.php';


function shop_return_full_order_received(
    PDO $db,
    int $orderId
): bool {
    if ($orderId < 1) {
        return false;
    }

    if (!shop_returns_table_exists($db)) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT
            oi.id,
            oi.quantity AS ordered_quantity,
            COALESCE(
                SUM(
                    CASE
                        WHEN r.status = "received"
                            THEN ri.quantity
                        ELSE 0
                    END
                ),
                0
            ) AS returned_quantity
         FROM shop_order_items oi
         LEFT JOIN shop_return_items ri
            ON ri.order_item_id = oi.id
         LEFT JOIN shop_returns r
            ON r.id = ri.return_id
         WHERE oi.order_id = ?
         GROUP BY
            oi.id,
            oi.quantity
         ORDER BY oi.id ASC'
    );

    $stmt->execute([$orderId]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$items) {
        return false;
    }

    foreach ($items as $item) {
        $ordered = max(
            0,
            (int) ($item['ordered_quantity'] ?? 0)
        );

        $returned = max(
            0,
            (int) ($item['returned_quantity'] ?? 0)
        );

        if ($ordered < 1 || $returned < $ordered) {
            return false;
        }
    }

    return true;
}


function shop_return_aware_refund_blocker(
    PDO $db,
    int $orderId
): ?string {
    if ($orderId < 1) {
        return 'A valid Shop order is required.';
    }

    $fullReturnReceived =
        shop_return_full_order_received(
            $db,
            $orderId
        );

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

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        as $fulfillment
    ) {
        $status = strtolower(
            trim(
                (string) (
                    $fulfillment['status']
                    ?? ''
                )
            )
        );

        if (
            in_array(
                $status,
                ['cancelled', 'canceled'],
                true
            )
        ) {
            continue;
        }

        /*
         * A fulfillment that reached the customer is allowed through
         * only after every order-item quantity has physically returned.
         * Provider IDs and tracking remain intact as historical truth.
         */
        if (
            $fullReturnReceived
            && in_array(
                $status,
                [
                    'shipped',
                    'delivered',
                    'fulfilled',
                ],
                true
            )
        ) {
            continue;
        }

        $providerOrderId = trim(
            (string) (
                $fulfillment['provider_order_id']
                ?? ''
            )
        );

        $trackingNumber = trim(
            (string) (
                $fulfillment['tracking_number']
                ?? ''
            )
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
            if (
                in_array(
                    $status,
                    [
                        'shipped',
                        'delivered',
                        'fulfilled',
                    ],
                    true
                )
            ) {
                return
                    'This order has already shipped. Record every item as physically returned before issuing the full Stripe refund.';
            }

            return
                'This order still has active fulfillment activity. Cancel or resolve fulfillment before issuing the Stripe refund.';
        }
    }

    return null;
}


function shop_issue_return_aware_full_refund(
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
            trim(
                (string) (
                    $order['payment_status']
                    ?? ''
                )
            )
        ) !== 'paid'
    ) {
        throw new InvalidArgumentException(
            'Only a paid order can be refunded.'
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
            'This order does not have a Stripe PaymentIntent to refund.'
        );
    }

    $fulfillmentBlocker =
        shop_return_aware_refund_blocker(
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
        $existingStatus =
            shop_refund_normalize_status(
                (string) (
                    $existing['status']
                    ?? ''
                )
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
                'llama_checkout_type' =>
                    'shop_refund',
                'llama_order_id' =>
                    (string) $orderId,
                'llama_order_number' =>
                    (string) (
                        $order['order_number']
                        ?? ''
                    ),
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

    $refundStatus =
        shop_refund_normalize_status(
            (string) (
                $refund->status
                ?? 'pending'
            )
        );

    $amount = max(
        0,
        (int) (
            $refund->amount
            ?? $order['total_cents']
            ?? 0
        )
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
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
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
                VALUES(status),
            failure_reason =
                VALUES(failure_reason),
            requested_by =
                VALUES(requested_by),
            requested_at =
                UTC_TIMESTAMP(),
            updated_at =
                UTC_TIMESTAMP()'
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

    /*
     * Reuse the existing integrity-checked status transition and
     * inventory-restock implementation. Returned quantities already
     * present in shop_inventory_restocks are counted, so this cannot
     * restore the same inventory twice.
     */
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
                'amount_cents' =>
                    $amount,
                'status' =>
                    $refundStatus,
                'reason' =>
                    $reason,
                'full_return_received' =>
                    shop_return_full_order_received(
                        $db,
                        $orderId
                    ),
            ]
        );
    }

    return [
        'refund_id' =>
            $refundId,
        'status' =>
            $refundStatus,
        'amount_cents' =>
            $amount,
        'currency' =>
            strtolower(
                (string) (
                    $refund->currency
                    ?? $order['currency']
                    ?? 'usd'
                )
            ),
    ];
}
