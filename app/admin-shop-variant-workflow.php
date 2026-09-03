<?php

declare(strict_types=1);

/*
 * Flexible product option + variant workflow for Llama Scout Admin.
 *
 * This file intentionally builds on the existing admin-shop.php helpers.
 * It does not replace them.
 */

function admin_shop_table_exists(
    PDO $db,
    string $table
): bool {
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

function admin_shop_variant_has_order_history(
    PDO $db,
    int $variantId
): bool {
    if (
        $variantId < 1
        || !admin_shop_table_exists($db, 'shop_order_items')
    ) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT 1
         FROM shop_order_items
         WHERE variant_id = ?
         LIMIT 1'
    );

    $stmt->execute([$variantId]);

    return (bool) $stmt->fetchColumn();
}

function admin_shop_product_has_ordered_variants(
    PDO $db,
    int $productId
): bool {
    if (
        $productId < 1
        || !admin_shop_table_exists($db, 'shop_order_items')
    ) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT 1
         FROM shop_order_items oi
         INNER JOIN shop_product_variants v
            ON v.id = oi.variant_id
         WHERE v.product_id = ?
         LIMIT 1'
    );

    $stmt->execute([$productId]);

    return (bool) $stmt->fetchColumn();
}

function admin_shop_normalize_submitted_options(
    array $data
): array {
    $submitted = [];

    for ($position = 1; $position <= 3; $position++) {
        $name = trim(
            (string) (
                $data['option_name'][$position]
                ?? ''
            )
        );

        $values = admin_shop_normalize_option_values(
            (string) (
                $data['option_values'][$position]
                ?? ''
            )
        );

        if ($name === '' && !$values) {
            continue;
        }

        if ($name === '') {
            throw new RuntimeException(
                'Every option with values needs an option name.'
            );
        }

        if (mb_strlen($name) > 100) {
            throw new RuntimeException(
                'Option names must be 100 characters or fewer.'
            );
        }

        if (!$values) {
            throw new RuntimeException(
                'Option "' . $name . '" needs at least one value.'
            );
        }

        $submitted[$position] = [
            'name' => $name,
            'values' => $values,
        ];
    }

    if ($submitted) {
        $positions = array_keys($submitted);
        $expected = range(1, count($positions));

        if ($positions !== $expected) {
            throw new RuntimeException(
                'Product options must use positions 1, 2, and 3 in order without gaps.'
            );
        }
    }

    return $submitted;
}

function admin_shop_option_structure_changed(
    array $existing,
    array $submitted
): bool {
    if (count($existing) !== count($submitted)) {
        return true;
    }

    $existingByPosition = [];

    foreach ($existing as $option) {
        $existingByPosition[
            (int) $option['option_position']
        ] = $option;
    }

    foreach ($submitted as $position => $option) {
        $current = $existingByPosition[$position] ?? null;

        if (!$current) {
            return true;
        }

        if (
            mb_strtolower(
                trim((string) $current['option_name'])
            )
            !== mb_strtolower($option['name'])
        ) {
            return true;
        }

        $submittedValues = [];

        foreach ($option['values'] as $value) {
            $submittedValues[
                mb_strtolower(trim($value))
            ] = true;
        }

        /*
         * Adding values is not destructive and can use the existing
         * admin_shop_save_options() behavior.
         *
         * Removing an existing value changes the structure and requires
         * a safe rebuild.
         */
        foreach (($current['values'] ?? []) as $valueRow) {
            $key = mb_strtolower(
                trim((string) $valueRow['option_value'])
            );

            if (!isset($submittedValues[$key])) {
                return true;
            }
        }
    }

    return false;
}

function admin_shop_delete_unordered_product_variants(
    PDO $db,
    int $productId
): int {
    $variants = admin_shop_product_variants(
        $db,
        $productId
    );

    if (!$variants) {
        return 0;
    }

    $deleteValues = $db->prepare(
        'DELETE FROM shop_product_variant_values
         WHERE variant_id = ?'
    );

    $deleteVariant = $db->prepare(
        'DELETE FROM shop_product_variants
         WHERE id = ?
           AND product_id = ?'
    );

    $deleted = 0;

    foreach ($variants as $variant) {
        $variantId = (int) $variant['id'];

        if (
            admin_shop_variant_has_order_history(
                $db,
                $variantId
            )
        ) {
            continue;
        }

        $deleteValues->execute([$variantId]);
        $deleteVariant->execute([
            $variantId,
            $productId,
        ]);

        $deleted += $deleteVariant->rowCount();
    }

    return $deleted;
}

function admin_shop_recreate_product_options(
    PDO $db,
    int $productId,
    array $submitted
): void {
    $delete = $db->prepare(
        'DELETE FROM shop_product_options
         WHERE product_id = ?'
    );

    $delete->execute([$productId]);

    $insertOption = $db->prepare(
        'INSERT INTO shop_product_options (
            product_id,
            option_position,
            option_name
         ) VALUES (?, ?, ?)'
    );

    $insertValue = $db->prepare(
        'INSERT INTO shop_product_option_values (
            option_id,
            option_value,
            sort_order
         ) VALUES (?, ?, ?)'
    );

    foreach ($submitted as $position => $option) {
        $insertOption->execute([
            $productId,
            $position,
            $option['name'],
        ]);

        $optionId = (int) $db->lastInsertId();

        foreach (
            $option['values']
            as $sortOrder => $value
        ) {
            $insertValue->execute([
                $optionId,
                $value,
                $sortOrder,
            ]);
        }
    }
}

function admin_shop_save_options_flexible(
    PDO $db,
    int $actorUserId,
    int $productId,
    array $data
): array {
    $product = admin_shop_product(
        $db,
        $productId
    );

    if (!$product) {
        throw new RuntimeException(
            'Product not found.'
        );
    }

    $submitted =
        admin_shop_normalize_submitted_options($data);

    $existing = admin_shop_product_options(
        $db,
        $productId
    );

    $variants = admin_shop_product_variants(
        $db,
        $productId
    );

    $structureChanged =
        admin_shop_option_structure_changed(
            $existing,
            $submitted
        );

    /*
     * Nothing structural changed. Preserve all existing variants and
     * use the existing helper, which safely adds newly entered values.
     */
    if (!$structureChanged) {
        admin_shop_save_options(
            $db,
            $actorUserId,
            $productId,
            $data
        );

        return [
            'options_rebuilt' => false,
            'variants_removed' => 0,
        ];
    }

    /*
     * If there are no variants yet, the existing helper already allows
     * a complete rewrite of the option structure.
     */
    if (!$variants) {
        admin_shop_save_options(
            $db,
            $actorUserId,
            $productId,
            $data
        );

        return [
            'options_rebuilt' => true,
            'variants_removed' => 0,
        ];
    }

    /*
     * Ordered variants are protected because historical orders depend
     * on them. Products without order history remain fully editable.
     */
    if (
        admin_shop_product_has_ordered_variants(
            $db,
            $productId
        )
    ) {
        throw new RuntimeException(
            'This product already has order history. Existing ordered variants are protected, so its option structure cannot be destructively rebuilt. You can still add new option values and generate new variants.'
        );
    }

    $db->beginTransaction();

    try {
        $removed =
            admin_shop_delete_unordered_product_variants(
                $db,
                $productId
            );

        admin_shop_recreate_product_options(
            $db,
            $productId,
            $submitted
        );

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                null,
                'shop.product_options_rebuilt',
                'Rebuilt product options for "' .
                    (string) $product['name'] .
                    '" and removed ' .
                    $removed .
                    ' disposable variant' .
                    ($removed === 1 ? '' : 's') .
                    '.',
                [
                    'product_id' => $productId,
                    'option_count' => count($submitted),
                    'variants_removed' => $removed,
                ]
            );
        }

        $db->commit();

        return [
            'options_rebuilt' => true,
            'variants_removed' => $removed,
        ];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_shop_delete_variant_safe(
    PDO $db,
    int $actorUserId,
    int $productId,
    int $variantId
): void {
    if ($productId < 1 || $variantId < 1) {
        throw new InvalidArgumentException(
            'A valid variant is required.'
        );
    }

    $stmt = $db->prepare(
        'SELECT id, name, sku
         FROM shop_product_variants
         WHERE id = ?
           AND product_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $variantId,
        $productId,
    ]);

    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new InvalidArgumentException(
            'Variant not found.'
        );
    }

    if (
        admin_shop_variant_has_order_history(
            $db,
            $variantId
        )
    ) {
        throw new RuntimeException(
            'This variant has been used in an order and is protected. Disable it instead of deleting it.'
        );
    }

    $db->beginTransaction();

    try {
        $db->prepare(
            'DELETE FROM shop_product_variant_values
             WHERE variant_id = ?'
        )->execute([$variantId]);

        $db->prepare(
            'DELETE FROM shop_product_variants
             WHERE id = ?
               AND product_id = ?'
        )->execute([
            $variantId,
            $productId,
        ]);

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                null,
                'shop.product_variant_deleted',
                'Deleted unused product variant "' .
                    (
                        trim((string) ($variant['name'] ?? ''))
                        ?: trim((string) ($variant['sku'] ?? ''))
                        ?: ('#' . $variantId)
                    ) .
                    '".',
                [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
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

function admin_shop_rebuild_variants(
    PDO $db,
    int $actorUserId,
    int $productId,
    array $data
): array {
    $product = admin_shop_product(
        $db,
        $productId
    );

    if (!$product) {
        throw new RuntimeException(
            'Product not found.'
        );
    }

    $removed = 0;

    $db->beginTransaction();

    try {
        $removed =
            admin_shop_delete_unordered_product_variants(
                $db,
                $productId
            );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    /*
     * Generate after the deletion transaction is complete because the
     * existing generator manages its own transaction.
     */
    $created = admin_shop_generate_variants(
        $db,
        $actorUserId,
        $productId,
        $data
    );

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.product_variants_rebuilt',
            'Rebuilt variants for "' .
                (string) $product['name'] .
                '".',
            [
                'product_id' => $productId,
                'variants_removed' => $removed,
                'variants_created' => $created,
            ]
        );
    }

    return [
        'removed' => $removed,
        'created' => $created,
    ];
}

function admin_shop_save_photo_editor(
    PDO $db,
    int $actorUserId,
    int $productId,
    int $imageId,
    array $criteria,
    int $position
): void {
    if ($imageId < 1) {
        throw new InvalidArgumentException(
            'A valid product photo is required.'
        );
    }

    admin_shop_assign_product_photo(
        $db,
        $actorUserId,
        $productId,
        $imageId,
        $criteria
    );

    admin_shop_swap_product_photo_position(
        $db,
        $actorUserId,
        $productId,
        $imageId,
        $position
    );
}
