<?php

declare(strict_types=1);

/*
 * Duplicate-safe manual fulfillment creation.
 *
 * Only unfulfilled physical items belonging to the selected
 * provider are attached. A repeated/double submission therefore
 * cannot create a second fulfillment for the same items.
 */

function admin_safe_create_fulfillment(
    PDO $db,
    int $actorUserId,
    int $orderId,
    array $data
): int {
    $order = admin_shop_order($db, $orderId);

    if (!$order) {
        throw new InvalidArgumentException('Order not found.');
    }

    $provider = admin_shop_normalize_provider(
        (string) ($data['fulfillment_provider'] ?? '')
    );

    if ($provider === '') {
        $provider = 'llama_scout';
    }

    if (!array_key_exists($provider, admin_shop_fulfillment_providers())) {
        throw new InvalidArgumentException(
            'Choose a valid fulfillment provider.'
        );
    }

    $type = in_array(
        $provider,
        ['printful', 'printify', 'other'],
        true
    )
        ? 'provider'
        : 'manual';

    $status = trim((string) ($data['status'] ?? 'pending'));

    if (
        !in_array(
            $status,
            [
                'pending',
                'processing',
                'submitted',
                'shipped',
                'delivered',
                'problem',
                'cancelled',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid fulfillment status.'
        );
    }

    $trackingNumber = trim(
        (string) ($data['tracking_number'] ?? '')
    );

    $trackingCarrier = admin_shop_normalize_tracking_carrier(
        (string) ($data['tracking_carrier'] ?? '')
    );

    if ($trackingNumber !== '' && $trackingCarrier === '') {
        throw new InvalidArgumentException(
            'Choose the tracking carrier for that tracking number.'
        );
    }

    $providerOrderId = trim(
        (string) ($data['provider_order_id'] ?? '')
    );

    $trackingUrl = admin_shop_tracking_url(
        $trackingCarrier,
        $trackingNumber
    );

    $db->beginTransaction();

    try {
        $itemStmt = $db->prepare(
            'SELECT oi.*
             FROM shop_order_items oi
             LEFT JOIN shop_order_fulfillment_items fi
                ON fi.order_item_id = oi.id
             WHERE oi.order_id = ?
               AND oi.requires_shipping = 1
               AND fi.order_item_id IS NULL
             ORDER BY oi.id ASC
             FOR UPDATE'
        );

        $itemStmt->execute([$orderId]);

        $eligibleItems = [];

        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
            $itemProvider = admin_shop_normalize_provider(
                (string) ($item['fulfillment_provider'] ?? '')
            );

            if ($itemProvider === '') {
                $itemProvider = 'llama_scout';
            }

            if ($itemProvider === $provider) {
                $eligibleItems[] = $item;
            }
        }

        if (!$eligibleItems) {
            throw new InvalidArgumentException(
                'There are no unfulfilled order items assigned to ' .
                admin_shop_fulfillment_provider_label($provider) .
                '.'
            );
        }

        $insert = $db->prepare(
            'INSERT INTO shop_order_fulfillments (
                order_id,
                fulfillment_type,
                fulfillment_provider,
                status,
                provider_order_id,
                tracking_number,
                tracking_carrier,
                tracking_url,
                submitted_at,
                shipped_at,
                delivered_at
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                CASE
                    WHEN ? IN ("submitted","processing","shipped","delivered")
                        THEN UTC_TIMESTAMP()
                    ELSE NULL
                END,
                CASE
                    WHEN ? IN ("shipped","delivered")
                        THEN UTC_TIMESTAMP()
                    ELSE NULL
                END,
                CASE
                    WHEN ? = "delivered"
                        THEN UTC_TIMESTAMP()
                    ELSE NULL
                END
             )'
        );

        $insert->execute([
            $orderId,
            $type,
            $provider,
            $status,
            $providerOrderId !== '' ? $providerOrderId : null,
            $trackingNumber !== '' ? $trackingNumber : null,
            $trackingCarrier !== '' ? $trackingCarrier : null,
            $trackingUrl !== '' ? $trackingUrl : null,
            $status,
            $status,
            $status,
        ]);

        $fulfillmentId = (int) $db->lastInsertId();

        $link = $db->prepare(
            'INSERT INTO shop_order_fulfillment_items (
                fulfillment_id,
                order_item_id,
                quantity
             ) VALUES (?, ?, ?)'
        );

        foreach ($eligibleItems as $item) {
            $link->execute([
                $fulfillmentId,
                (int) $item['id'],
                (int) $item['quantity'],
            ]);
        }

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                $order['user_id']
                    ? (int) $order['user_id']
                    : null,
                'shop.fulfillment_created',
                'Created ' .
                    admin_shop_fulfillment_provider_label($provider) .
                    ' fulfillment for order ' .
                    (string) $order['order_number'] .
                    '.',
                [
                    'order_id' => $orderId,
                    'fulfillment_id' => $fulfillmentId,
                    'provider' => $provider,
                    'item_count' => count($eligibleItems),
                ]
            );
        }

        $db->commit();

        return $fulfillmentId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
