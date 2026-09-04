<?php

declare(strict_types=1);

function admin_shop_product_order_history_count(
    PDO $db,
    int $productId
): int {
    if ($productId < 1) {
        return 0;
    }

    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM shop_order_items oi
         WHERE oi.product_id = ?
            OR oi.variant_id IN (
                SELECT v.id
                FROM shop_product_variants v
                WHERE v.product_id = ?
            )'
    );

    $stmt->execute([
        $productId,
        $productId,
    ]);

    return max(0, (int) $stmt->fetchColumn());
}

function admin_shop_product_lifecycle_info(
    PDO $db,
    int $productId
): array {
    $product = admin_shop_product($db, $productId);

    if (!$product) {
        throw new InvalidArgumentException('Product not found.');
    }

    $historyCount =
        admin_shop_product_order_history_count(
            $db,
            $productId
        );

    return [
        'product' => $product,
        'status' => strtolower(
            trim((string) ($product['status'] ?? 'draft'))
        ),
        'order_history_count' => $historyCount,
        'can_delete' => $historyCount === 0,
    ];
}

function admin_shop_set_product_lifecycle_status(
    PDO $db,
    int $actorUserId,
    int $productId,
    string $status
): void {
    $status = strtolower(trim($status));

    if (
        !in_array(
            $status,
            ['draft', 'active', 'archived'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid product status.'
        );
    }

    $product = admin_shop_product($db, $productId);

    if (!$product) {
        throw new InvalidArgumentException(
            'Product not found.'
        );
    }

    $before = strtolower(
        trim((string) ($product['status'] ?? 'draft'))
    );

    if ($before === $status) {
        return;
    }

    $stmt = $db->prepare(
        'UPDATE shop_products
         SET status = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $status,
        $productId,
    ]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.product_status_changed',
            'Changed product "' .
                (string) $product['name'] .
                '" from ' .
                $before .
                ' to ' .
                $status .
                '.',
            [
                'product_id' => $productId,
                'status_before' => $before,
                'status_after' => $status,
            ]
        );
    }
}

function admin_shop_delete_product_permanently(
    PDO $db,
    int $actorUserId,
    int $productId
): void {
    $info =
        admin_shop_product_lifecycle_info(
            $db,
            $productId
        );

    $product = $info['product'];

    if (!$info['can_delete']) {
        throw new InvalidArgumentException(
            'This product has order history and cannot be permanently deleted. Archive it instead.'
        );
    }

    $db->beginTransaction();

    try {
        $variantStmt = $db->prepare(
            'SELECT id
             FROM shop_product_variants
             WHERE product_id = ?'
        );

        $variantStmt->execute([$productId]);

        $variantIds = array_map(
            'intval',
            $variantStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        );

        $deleteVariantValues = $db->prepare(
            'DELETE FROM shop_product_variant_values
             WHERE variant_id = ?'
        );

        foreach ($variantIds as $variantId) {
            $deleteVariantValues->execute([$variantId]);
        }

        $db->prepare(
            'DELETE FROM shop_product_variants
             WHERE product_id = ?'
        )->execute([$productId]);

        $optionStmt = $db->prepare(
            'SELECT id
             FROM shop_product_options
             WHERE product_id = ?'
        );

        $optionStmt->execute([$productId]);

        $optionIds = array_map(
            'intval',
            $optionStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        );

        $deleteOptionValues = $db->prepare(
            'DELETE FROM shop_product_option_values
             WHERE option_id = ?'
        );

        foreach ($optionIds as $optionId) {
            $deleteOptionValues->execute([$optionId]);
        }

        $db->prepare(
            'DELETE FROM shop_product_options
             WHERE product_id = ?'
        )->execute([$productId]);

        $db->prepare(
            'DELETE FROM shop_product_images
             WHERE product_id = ?'
        )->execute([$productId]);

        $deleteProduct = $db->prepare(
            'DELETE FROM shop_products
             WHERE id = ?
             LIMIT 1'
        );

        $deleteProduct->execute([$productId]);

        if ($deleteProduct->rowCount() !== 1) {
            throw new RuntimeException(
                'The product could not be deleted.'
            );
        }

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                null,
                'shop.product_deleted',
                'Permanently deleted unused product "' .
                    (string) $product['name'] .
                    '".',
                [
                    'product_id' => $productId,
                    'status' =>
                        (string) ($product['status'] ?? ''),
                ]
            );
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
