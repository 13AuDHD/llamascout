<?php

declare(strict_types=1);

/*
 * Llama Scout Shop fulfillment routing.
 *
 * Called by the Stripe Shop webhook after a payment has been
 * committed locally.
 *
 * Responsibilities:
 * - split physical order items by fulfillment provider
 * - create one fulfillment per provider
 * - attach each physical order item exactly once
 * - remain safe when Stripe retries the same webhook
 *
 * No schema is created or altered here.
 */

function shop_fulfillment_route_paid_order(
    PDO $db,
    int $orderId
): void {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid Shop order is required.'
        );
    }

    $db->beginTransaction();

    try {
        $orderStmt = $db->prepare(
            'SELECT
                id,
                order_status,
                payment_status
             FROM shop_orders
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $orderStmt->execute([
            $orderId,
        ]);

        $order =
            $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException(
                'Shop order not found while routing fulfillment.'
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
            throw new RuntimeException(
                'Fulfillment routing requires a paid Shop order.'
            );
        }

        $itemStmt = $db->prepare(
            'SELECT
                oi.id,
                oi.quantity,
                oi.fulfillment_type,
                oi.fulfillment_provider
             FROM shop_order_items oi
             LEFT JOIN shop_order_fulfillment_items fi
                ON fi.order_item_id = oi.id
             WHERE oi.order_id = ?
               AND oi.requires_shipping = 1
               AND fi.order_item_id IS NULL
             ORDER BY oi.id ASC
             FOR UPDATE'
        );

        $itemStmt->execute([
            $orderId,
        ]);

        $items =
            $itemStmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        if (!$items) {
            $db->commit();
            return;
        }

        $groups = [];

        foreach ($items as $item) {
            $provider = strtolower(
                trim(
                    (string) (
                        $item['fulfillment_provider']
                        ?? ''
                    )
                )
            );

            if ($provider === '') {
                $provider = 'llama_scout';
            }

            $type = in_array(
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

            if (!isset($groups[$provider])) {
                $groups[$provider] = [
                    'type' => $type,
                    'items' => [],
                ];
            }

            $groups[$provider]['items'][] =
                $item;
        }

        $findFulfillment = $db->prepare(
            'SELECT id
             FROM shop_order_fulfillments
             WHERE order_id = ?
               AND fulfillment_provider = ?
               AND status NOT IN (
                   "cancelled",
                   "delivered"
               )
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE'
        );

        $insertFulfillment = $db->prepare(
            'INSERT INTO shop_order_fulfillments (
                order_id,
                fulfillment_type,
                fulfillment_provider,
                status
             ) VALUES (?, ?, ?, "pending")'
        );

        $linkItem = $db->prepare(
            'INSERT INTO shop_order_fulfillment_items (
                fulfillment_id,
                order_item_id,
                quantity
             )
             SELECT ?, ?, ?
             WHERE NOT EXISTS (
                SELECT 1
                FROM shop_order_fulfillment_items
                WHERE order_item_id = ?
             )'
        );

        foreach (
            $groups
            as $provider => $group
        ) {
            $findFulfillment->execute([
                $orderId,
                $provider,
            ]);

            $fulfillmentId =
                (int) (
                    $findFulfillment->fetchColumn()
                    ?: 0
                );

            if ($fulfillmentId < 1) {
                $insertFulfillment->execute([
                    $orderId,
                    (string) $group['type'],
                    $provider,
                ]);

                $fulfillmentId =
                    (int) $db->lastInsertId();
            }

            foreach (
                $group['items']
                as $item
            ) {
                $orderItemId =
                    (int) $item['id'];

                $quantity =
                    max(
                        1,
                        (int) $item['quantity']
                    );

                $linkItem->execute([
                    $fulfillmentId,
                    $orderItemId,
                    $quantity,
                    $orderItemId,
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
