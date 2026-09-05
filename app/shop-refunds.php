<?php

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';


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


function shop_refund_apply_order_status(
    PDO $db,
    int $orderId,
    string $refundStatus
): void {
    $refundStatus = shop_refund_normalize_status(
        $refundStatus
    );

    if ($refundStatus !== 'succeeded') {
        return;
    }

    $stmt = $db->prepare(
        'UPDATE shop_orders
         SET
            payment_status = "refunded",
            order_status = "refunded",
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $stmt->execute([$orderId]);
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
        $refundStatus
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

    shop_refund_apply_order_status(
        $db,
        $orderId,
        $status
    );

    return true;
}
