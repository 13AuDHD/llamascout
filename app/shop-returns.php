<?php

declare(strict_types=1);

function shop_returns_table_exists(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN (
                'shop_returns',
                'shop_return_items'
           )"
    );

    return $stmt
        && (int) $stmt->fetchColumn() === 2;
}


function shop_return_inventory_table_exists(PDO $db): bool
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


function shop_return_item_tracks_inventory(array $item): bool
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

    return
        (int) ($item['variant_id'] ?? 0) > 0
        && strtolower(
            trim((string) ($snapshot['availability'] ?? ''))
        ) !== 'preorder'
        && (int) ($snapshot['track_inventory'] ?? 0) === 1
        && (int) ($snapshot['allow_backorder'] ?? 0) !== 1;
}


function shop_returns_for_order(
    PDO $db,
    int $orderId
): array {
    if (!shop_returns_table_exists($db)) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM shop_returns
         WHERE order_id = ?
         ORDER BY received_at DESC, id DESC'
    );
    $stmt->execute([$orderId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function shop_return_received_quantity(
    PDO $db,
    int $orderItemId
): int {
    if (!shop_returns_table_exists($db)) {
        return 0;
    }

    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(ri.quantity), 0)
         FROM shop_return_items ri
         INNER JOIN shop_returns r
            ON r.id = ri.return_id
         WHERE ri.order_item_id = ?
           AND r.status = "received"'
    );
    $stmt->execute([$orderItemId]);

    return max(0, (int) $stmt->fetchColumn());
}


function shop_return_create_received(
    PDO $db,
    int $actorUserId,
    int $orderId,
    array $quantities,
    string $reason = '',
    string $notes = ''
): array {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid Shop order is required.'
        );
    }

    if (!shop_returns_table_exists($db)) {
        throw new RuntimeException(
            'Shop return database migration is missing.'
        );
    }

    if (!shop_return_inventory_table_exists($db)) {
        throw new RuntimeException(
            'Shop inventory restock database migration is missing.'
        );
    }

    $reason = trim($reason);
    $notes = trim($notes);

    if (mb_strlen($reason) > 255) {
        throw new InvalidArgumentException(
            'Return reason must be 255 characters or fewer.'
        );
    }

    if (mb_strlen($notes) > 5000) {
        throw new InvalidArgumentException(
            'Return notes must be 5,000 characters or fewer.'
        );
    }

    $db->beginTransaction();

    try {
        $orderStmt = $db->prepare(
            'SELECT
                id,
                order_number,
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
            throw new InvalidArgumentException(
                'Shop order not found.'
            );
        }

        if (empty($order['inventory_committed_at'])) {
            throw new InvalidArgumentException(
                'This order never committed inventory, so there is no deducted stock to return.'
            );
        }

        $itemStmt = $db->prepare(
            'SELECT
                id,
                variant_id,
                product_name,
                variant_name,
                quantity,
                variant_snapshot_json
             FROM shop_order_items
             WHERE order_id = ?
             ORDER BY id ASC
             FOR UPDATE'
        );
        $itemStmt->execute([$orderId]);

        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $requested = [];
        $itemMap = [];

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);

            if ($itemId < 1) {
                continue;
            }

            $itemMap[$itemId] = $item;

            $qty = max(
                0,
                (int) ($quantities[$itemId] ?? 0)
            );

            if ($qty > 0) {
                $requested[$itemId] = $qty;
            }
        }

        if (!$requested) {
            throw new InvalidArgumentException(
                'Choose at least one returned item quantity.'
            );
        }

        $alreadyReturnedStmt = $db->prepare(
            'SELECT COALESCE(SUM(ri.quantity), 0)
             FROM shop_return_items ri
             INNER JOIN shop_returns r
                ON r.id = ri.return_id
             WHERE ri.order_item_id = ?
               AND r.status = "received"'
        );

        $alreadyRestockedStmt = $db->prepare(
            'SELECT COALESCE(SUM(quantity), 0)
             FROM shop_inventory_restocks
             WHERE order_item_id = ?'
        );

        foreach ($requested as $itemId => $qty) {
            $item = $itemMap[$itemId];
            $ordered = max(0, (int) ($item['quantity'] ?? 0));

            $alreadyReturnedStmt->execute([$itemId]);
            $alreadyReturned = max(
                0,
                (int) $alreadyReturnedStmt->fetchColumn()
            );

            if ($qty > ($ordered - $alreadyReturned)) {
                throw new InvalidArgumentException(
                    'Returned quantity exceeds the remaining returnable quantity for '
                    . (string) ($item['product_name'] ?? 'an order item')
                    . '.'
                );
            }

            if (shop_return_item_tracks_inventory($item)) {
                $alreadyRestockedStmt->execute([$itemId]);

                $alreadyRestocked = max(
                    0,
                    (int) $alreadyRestockedStmt->fetchColumn()
                );

                if ($qty > ($ordered - $alreadyRestocked)) {
                    throw new RuntimeException(
                        'Returned quantity would over-restock an order item.'
                    );
                }
            }
        }

        $returnStmt = $db->prepare(
            'INSERT INTO shop_returns (
                order_id,
                status,
                reason,
                notes,
                received_at,
                created_by_user_id,
                created_at,
                updated_at
             ) VALUES (
                ?,
                "received",
                ?,
                ?,
                UTC_TIMESTAMP(),
                ?,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
             )'
        );

        $returnStmt->execute([
            $orderId,
            $reason !== '' ? $reason : null,
            $notes !== '' ? $notes : null,
            $actorUserId > 0 ? $actorUserId : null,
        ]);

        $returnId = (int) $db->lastInsertId();

        if ($returnId < 1) {
            throw new RuntimeException(
                'Return record could not be created.'
            );
        }

        $insertItem = $db->prepare(
            'INSERT INTO shop_return_items (
                return_id,
                order_id,
                order_item_id,
                variant_id,
                quantity,
                restocked_quantity,
                created_at
             ) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
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
             ) VALUES (
                ?, ?, ?, ?, "return", ?, UTC_TIMESTAMP()
             )'
        );

        $variantLock = $db->prepare(
            'SELECT inventory_quantity
             FROM shop_product_variants
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $increaseInventory = $db->prepare(
            'UPDATE shop_product_variants
             SET inventory_quantity = inventory_quantity + ?
             WHERE id = ?'
        );

        $restockedTotal = 0;

        foreach ($requested as $itemId => $qty) {
            $item = $itemMap[$itemId];
            $variantId = (int) ($item['variant_id'] ?? 0);
            $restocked = 0;

            if (shop_return_item_tracks_inventory($item)) {
                $variantLock->execute([$variantId]);

                if ($variantLock->fetchColumn() === false) {
                    throw new RuntimeException(
                        'Returned Shop item references an inventory variant that no longer exists.'
                    );
                }

                $insertRestock->execute([
                    $orderId,
                    $itemId,
                    $variantId,
                    $qty,
                    (string) $returnId,
                ]);

                $increaseInventory->execute([
                    $qty,
                    $variantId,
                ]);

                if ($increaseInventory->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Returned inventory could not be restored safely.'
                    );
                }

                $restocked = $qty;
                $restockedTotal += $qty;
            }

            $insertItem->execute([
                $returnId,
                $orderId,
                $itemId,
                $variantId > 0 ? $variantId : null,
                $qty,
                $restocked,
            ]);
        }

        $db->commit();

        return [
            'return_id' => $returnId,
            'order_id' => $orderId,
            'restocked_quantity' => $restockedTotal,
            'item_count' => count($requested),
        ];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
