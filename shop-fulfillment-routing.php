<?php

declare(strict_types=1);

/*
 * Automatic fulfillment routing for paid Shop orders.
 *
 * Physical order items are grouped by their configured fulfillment
 * provider. Each provider gets its own fulfillment record, and each
 * order item is attached only once.
 *
 * This file performs no schema creation or alteration.
 */

function shop_fulfillment_normalize_provider(
    ?string $provider
): string {
    $provider =
        strtolower(
            trim(
                (string) $provider
            )
        );

    return match ($provider) {
        'printful' => 'printful',
        'printify' => 'printify',
        'other',
        'external' => 'other',
        'llama_scout',
        'llama scout',
        'llama scout fulfillment',
        'manual',
        'in-house',
        'in house',
        '' => 'llama_scout',
        default => 'llama_scout',
    };
}

function shop_fulfillment_type_for_provider(
    string $provider
): string {
    return in_array(
        $provider,
        [
            'printful',
            'printify',
            'other',
        ],
        true
    )
        ? 'provider'
        : 'manual';
}

function shop_fulfillment_validate_provider_item(
    array $item,
    string $provider
): void {
    if (
        !in_array(
            $provider,
            [
                'printful',
                'printify',
            ],
            true
        )
    ) {
        return;
    }

    $productId =
        trim(
            (string) (
                $item['fulfillment_product_id']
                ?? ''
            )
        );

    $variantId =
        trim(
            (string) (
                $item['fulfillment_variant_id']
                ?? ''
            )
        );

    if (
        $productId === ''
        || $variantId === ''
    ) {
        throw new RuntimeException(
            ucfirst($provider) .
            ' fulfillment is configured for "' .
            (string) (
                $item['product_name']
                ?? 'Shop item'
            ) .
            '", but its provider product ID or provider variant ID is missing.'
        );
    }
}

function shop_fulfillment_route_paid_order(
    PDO $db,
    int $orderId
): array {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid order ID is required for fulfillment routing.'
        );
    }

    foreach (
        [
            'shop_orders',
            'shop_order_items',
            'shop_order_fulfillments',
            'shop_order_fulfillment_items',
        ]
        as $table
    ) {
        if (
            function_exists(
                'shop_checkout_table_exists'
            )
            && !shop_checkout_table_exists(
                $db,
                $table
            )
        ) {
            throw new RuntimeException(
                'Shop fulfillment database migration is incomplete. Missing table: ' .
                $table .
                '.'
            );
        }
    }

    $orderStmt =
        $db->prepare(
            'SELECT
                id,
                order_number,
                payment_status,
                order_status
             FROM shop_orders
             WHERE id = ?
             LIMIT 1'
        );

    $orderStmt->execute([
        $orderId,
    ]);

    $order =
        $orderStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$order) {
        throw new RuntimeException(
            'The paid Shop order could not be found for fulfillment routing.'
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
        return [
            'created' => 0,
            'linked_items' => 0,
            'providers' => [],
        ];
    }

    /*
     * Pull only physical items that have not already been linked
     * to a fulfillment. This makes webhook retries idempotent.
     */
    $itemsStmt =
        $db->prepare(
            'SELECT oi.*
             FROM shop_order_items oi
             LEFT JOIN shop_order_fulfillment_items fi
                ON fi.order_item_id = oi.id
             WHERE oi.order_id = ?
               AND oi.requires_shipping = 1
               AND fi.id IS NULL
             ORDER BY oi.id ASC'
        );

    $itemsStmt->execute([
        $orderId,
    ]);

    $items =
        $itemsStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) ?: [];

    if (!$items) {
        return [
            'created' => 0,
            'linked_items' => 0,
            'providers' => [],
        ];
    }

    $groups = [];

    foreach ($items as $item) {
        $provider =
            shop_fulfillment_normalize_provider(
                (string) (
                    $item['fulfillment_provider']
                    ?? ''
                )
            );

        shop_fulfillment_validate_provider_item(
            $item,
            $provider
        );

        $groups[$provider][] =
            $item;
    }

    $db->beginTransaction();

    try {
        $insertFulfillment =
            $db->prepare(
                'INSERT INTO shop_order_fulfillments (
                    order_id,
                    fulfillment_type,
                    fulfillment_provider,
                    status
                 ) VALUES (
                    ?, ?, ?, "pending"
                 )'
            );

        $insertItem =
            $db->prepare(
                'INSERT INTO shop_order_fulfillment_items (
                    fulfillment_id,
                    order_item_id,
                    quantity
                 ) VALUES (?, ?, ?)'
            );

        $created = 0;
        $linkedItems = 0;
        $providers = [];

        foreach (
            $groups
            as $provider => $providerItems
        ) {
            $insertFulfillment->execute([
                $orderId,
                shop_fulfillment_type_for_provider(
                    $provider
                ),
                $provider,
            ]);

            $fulfillmentId =
                (int) $db->lastInsertId();

            if ($fulfillmentId < 1) {
                throw new RuntimeException(
                    'The fulfillment record could not be created.'
                );
            }

            foreach (
                $providerItems
                as $item
            ) {
                $quantity =
                    max(
                        1,
                        (int) (
                            $item['quantity']
                            ?? 1
                        )
                    );

                $insertItem->execute([
                    $fulfillmentId,
                    (int) $item['id'],
                    $quantity,
                ]);

                $linkedItems++;
            }

            $created++;
            $providers[] = $provider;
        }

        $db->commit();

        return [
            'created' => $created,
            'linked_items' => $linkedItems,
            'providers' => $providers,
        ];

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
