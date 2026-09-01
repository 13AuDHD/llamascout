<?php

declare(strict_types=1);

function admin_shop_money(
    int $cents,
    string $currency = 'usd'
): string {
    $symbol = strtolower($currency) === 'usd'
        ? '$'
        : strtoupper($currency) . ' ';

    return $symbol .
        number_format(
            $cents / 100,
            2
        );
}

function admin_shop_image_url(
    ?string $src,
    string $siteUrl = 'https://llamascout.com'
): string {
    $src = trim((string) $src);

    if ($src === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $src
        )
    ) {
        return $src;
    }

    if (str_starts_with($src, '//')) {
        return 'https:' . $src;
    }

    return rtrim($siteUrl, '/') .
        '/' .
        ltrim($src, '/');
}

function admin_shop_products(PDO $db): array
{
    $stmt = $db->query(
        'SELECT
            p.*,
            COUNT(DISTINCT v.id) AS variant_count,
            SUM(
                CASE
                    WHEN v.is_active = 1
                        THEN 1
                    ELSE 0
                END
            ) AS active_variant_count,
            MIN(
                CASE
                    WHEN v.is_active = 1
                        THEN v.price_cents
                    ELSE NULL
                END
            ) AS min_price_cents,
            MAX(
                CASE
                    WHEN v.is_active = 1
                        THEN v.price_cents
                    ELSE NULL
                END
            ) AS max_price_cents
         FROM shop_products p
         LEFT JOIN shop_product_variants v
            ON v.product_id = p.id
         GROUP BY p.id
         ORDER BY
            p.sort_order ASC,
            p.name ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_shop_product(
    PDO $db,
    int $productId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_products
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$productId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_shop_product_images(
    PDO $db,
    int $productId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_product_images
         WHERE product_id = ?
         ORDER BY
            is_primary DESC,
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([$productId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_shop_product_options(
    PDO $db,
    int $productId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_product_options
         WHERE product_id = ?
         ORDER BY
            option_position ASC,
            id ASC'
    );

    $stmt->execute([$productId]);

    $options =
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];

    if (!$options) {
        return [];
    }

    $valueStmt = $db->prepare(
        'SELECT *
         FROM shop_product_option_values
         WHERE option_id = ?
         ORDER BY
            sort_order ASC,
            id ASC'
    );

    foreach ($options as &$option) {
        $valueStmt->execute([
            (int) $option['id'],
        ]);

        $option['values'] =
            $valueStmt->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    unset($option);

    return $options;
}

function admin_shop_normalize_option_values(
    string $raw
): array {
    $parts =
        preg_split(
            '/[\r\n,]+/',
            $raw
        ) ?: [];

    $values = [];
    $seen = [];

    foreach ($parts as $part) {
        $value =
            trim((string) $part);

        if ($value === '') {
            continue;
        }

        if (mb_strlen($value) > 150) {
            throw new RuntimeException(
                'Option values must be 150 characters or fewer.'
            );
        }

        $key =
            mb_strtolower($value);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $values[] = $value;
    }

    if (count($values) > 50) {
        throw new RuntimeException(
            'Each option can contain at most 50 values.'
        );
    }

    return $values;
}

function admin_shop_save_options(
    PDO $db,
    int $actorUserId,
    int $productId,
    array $data
): void {
    $product =
        admin_shop_product(
            $db,
            $productId
        );

    if (!$product) {
        throw new RuntimeException(
            'Product not found.'
        );
    }

    $submitted = [];

    for ($position = 1; $position <= 3; $position++) {
        $name =
            trim(
                (string) (
                    $data['option_name'][$position]
                    ?? ''
                )
            );

        $rawValues =
            (string) (
                $data['option_values'][$position]
                ?? ''
            );

        $values =
            admin_shop_normalize_option_values(
                $rawValues
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
                'Option "' .
                $name .
                '" needs at least one value.'
            );
        }

        $submitted[$position] = [
            'name' => $name,
            'values' => $values,
        ];
    }

    if ($submitted) {
        $positions =
            array_keys($submitted);

        $expected =
            range(
                1,
                count($positions)
            );

        if ($positions !== $expected) {
            throw new RuntimeException(
                'Product options must use positions 1, 2, and 3 in order without gaps.'
            );
        }
    }

    $variants =
        admin_shop_product_variants(
            $db,
            $productId
        );

    $existing =
        admin_shop_product_options(
            $db,
            $productId
        );

    $existingByPosition = [];

    foreach ($existing as $option) {
        $existingByPosition[
            (int) $option['option_position']
        ] = $option;
    }

    $db->beginTransaction();

    try {
        if (!$variants) {
            $delete =
                $db->prepare(
                    'DELETE FROM shop_product_options
                     WHERE product_id = ?'
                );

            $delete->execute([$productId]);

            $insertOption =
                $db->prepare(
                    'INSERT INTO shop_product_options (
                        product_id,
                        option_position,
                        option_name
                     ) VALUES (?, ?, ?)'
                );

            $insertValue =
                $db->prepare(
                    'INSERT INTO shop_product_option_values (
                        option_id,
                        option_value,
                        sort_order
                     ) VALUES (?, ?, ?)'
                );

            foreach (
                $submitted
                as $position => $option
            ) {
                $insertOption->execute([
                    $productId,
                    $position,
                    $option['name'],
                ]);

                $optionId =
                    (int) $db->lastInsertId();

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
        } else {
            if (
                count($submitted)
                !== count($existing)
            ) {
                throw new RuntimeException(
                    'Once variants exist, option groups cannot be added or removed. You can still add new values to the existing options.'
                );
            }

            foreach (
                $submitted
                as $position => $option
            ) {
                $existingOption =
                    $existingByPosition[$position]
                    ?? null;

                if (!$existingOption) {
                    throw new RuntimeException(
                        'The existing option structure no longer matches this product.'
                    );
                }

                if (
                    mb_strtolower(
                        trim(
                            (string) $existingOption['option_name']
                        )
                    )
                    !==
                    mb_strtolower(
                        $option['name']
                    )
                ) {
                    throw new RuntimeException(
                        'Option names cannot be renamed after variants exist. Existing orders and variant combinations depend on them.'
                    );
                }

                $known = [];

                foreach (
                    $existingOption['values']
                    as $valueRow
                ) {
                    $known[
                        mb_strtolower(
                            trim(
                                (string) $valueRow['option_value']
                            )
                        )
                    ] = true;
                }

                $insertValue =
                    $db->prepare(
                        'INSERT INTO shop_product_option_values (
                            option_id,
                            option_value,
                            sort_order
                         ) VALUES (?, ?, ?)'
                    );

                $nextSort =
                    count(
                        $existingOption['values']
                    );

                foreach (
                    $option['values']
                    as $value
                ) {
                    $key =
                        mb_strtolower($value);

                    if (isset($known[$key])) {
                        continue;
                    }

                    $insertValue->execute([
                        (int) $existingOption['id'],
                        $value,
                        $nextSort++,
                    ]);

                    $known[$key] = true;
                }
            }
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.product_options_updated',
            'Updated product options for "' .
                (string) $product['name'] .
                '".',
            [
                'product_id' => $productId,
                'option_count' =>
                    count($submitted),
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_shop_sku_piece(
    string $value
): string {
    $value =
        strtoupper($value);

    $value =
        preg_replace(
            '/[^A-Z0-9]+/',
            '-',
            $value
        ) ?? '';

    return trim($value, '-');
}

function admin_shop_unique_sku(
    PDO $db,
    string $base
): string {
    $base =
        admin_shop_sku_piece($base);

    if ($base === '') {
        $base = 'LS-PRODUCT';
    }

    $base =
        substr(
            $base,
            0,
            105
        );

    $candidate = $base;
    $suffix = 2;

    $stmt =
        $db->prepare(
            'SELECT id
             FROM shop_product_variants
             WHERE sku = ?
             LIMIT 1'
        );

    while (true) {
        $stmt->execute([$candidate]);

        if (!$stmt->fetchColumn()) {
            return $candidate;
        }

        $candidate =
            substr(
                $base,
                0,
                110
            )
            . '-'
            . $suffix;

        $suffix++;
    }
}

function admin_shop_cartesian_product(
    array $sets
): array {
    if (!$sets) {
        return [[]];
    }

    $results = [[]];

    foreach ($sets as $set) {
        $next = [];

        foreach ($results as $result) {
            foreach ($set as $value) {
                $next[] =
                    array_merge(
                        $result,
                        [$value]
                    );
            }
        }

        $results = $next;

        if (count($results) > 250) {
            throw new RuntimeException(
                'This option setup would create more than 250 variants. Reduce the number of option values.'
            );
        }
    }

    return $results;
}

function admin_shop_existing_variant_keys(
    PDO $db,
    int $productId
): array {
    $stmt = $db->prepare(
        'SELECT
            vv.variant_id,
            o.option_position,
            vv.option_value_id
         FROM shop_product_variant_values vv
         INNER JOIN shop_product_options o
            ON o.id = vv.option_id
         INNER JOIN shop_product_variants v
            ON v.id = vv.variant_id
         WHERE v.product_id = ?
         ORDER BY
            vv.variant_id ASC,
            o.option_position ASC'
    );

    $stmt->execute([$productId]);

    $byVariant = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: []
        as $row
    ) {
        $byVariant[
            (int) $row['variant_id']
        ][
            (int) $row['option_position']
        ] =
            (int) $row['option_value_id'];
    }

    $keys = [];

    foreach ($byVariant as $values) {
        ksort($values);

        $keys[
            implode(
                ':',
                array_values($values)
            )
        ] = true;
    }

    return $keys;
}

function admin_shop_generate_variants(
    PDO $db,
    int $actorUserId,
    int $productId,
    array $data
): int {
    $product =
        admin_shop_product(
            $db,
            $productId
        );

    if (!$product) {
        throw new RuntimeException(
            'Product not found.'
        );
    }

    $priceRaw =
        trim(
            (string) (
                $data['default_price']
                ?? ''
            )
        );

    if (
        $priceRaw === ''
        || !is_numeric($priceRaw)
        || (float) $priceRaw < 0
    ) {
        throw new RuntimeException(
            'Enter a valid default price before generating variants.'
        );
    }

    $priceCents =
        (int) round(
            ((float) $priceRaw)
            * 100
        );

    $inventoryQuantity =
        (int) (
            $data['default_inventory']
            ?? 0
        );

    $trackInventory =
        isset(
            $data['default_track_inventory']
        )
            ? 1
            : 0;

    $allowBackorder =
        isset(
            $data['default_allow_backorder']
        )
            ? 1
            : 0;

    $fulfillmentType =
        trim(
            (string) (
                $data['default_fulfillment_type']
                ?? 'manual'
            )
        );

    if (
        !in_array(
            $fulfillmentType,
            [
                'manual',
                'provider',
                'digital',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Invalid fulfillment type.'
        );
    }

    $fulfillmentProvider =
        trim(
            (string) (
                $data['default_fulfillment_provider']
                ?? ''
            )
        );

    $skuPrefix =
        trim(
            (string) (
                $data['sku_prefix']
                ?? ''
            )
        );

    if ($skuPrefix === '') {
        $skuPrefix =
            'LS-' .
            admin_shop_sku_piece(
                (string) $product['slug']
            );
    }

    $options =
        admin_shop_product_options(
            $db,
            $productId
        );

    $sets = [];

    foreach ($options as $option) {
        if (empty($option['values'])) {
            throw new RuntimeException(
                'Every option needs at least one value before variants can be generated.'
            );
        }

        $sets[] =
            $option['values'];
    }

    $combinations =
        admin_shop_cartesian_product(
            $sets
        );

    $existingKeys =
        admin_shop_existing_variant_keys(
            $db,
            $productId
        );

    $existingVariants =
        admin_shop_product_variants(
            $db,
            $productId
        );

    $hasDefault =
        !$options
        && !empty($existingVariants);

    if ($hasDefault) {
        return 0;
    }

    $insertVariant =
        $db->prepare(
            'INSERT INTO shop_product_variants (
                product_id,
                sku,
                name,
                option_one_name,
                option_one_value,
                option_two_name,
                option_two_value,
                option_three_name,
                option_three_value,
                price_cents,
                currency,
                track_inventory,
                inventory_quantity,
                allow_backorder,
                fulfillment_type,
                fulfillment_provider,
                is_active,
                sort_order
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, "usd", ?, ?, ?, ?, ?, 1, ?
             )'
        );

    $insertLink =
        $db->prepare(
            'INSERT INTO shop_product_variant_values (
                variant_id,
                option_id,
                option_value_id,
                sort_order
             ) VALUES (?, ?, ?, ?)'
        );

    $count = 0;

    $sortStmt =
        $db->prepare(
            'SELECT COALESCE(
                MAX(sort_order),
                -1
             )
             FROM shop_product_variants
             WHERE product_id = ?'
        );

    $sortStmt->execute([$productId]);

    $nextSort =
        (int) $sortStmt->fetchColumn()
        + 1;

    $db->beginTransaction();

    try {
        foreach ($combinations as $combo) {
            if ($options) {
                $ids =
                    array_map(
                        static fn(array $value): int =>
                            (int) $value['id'],
                        $combo
                    );

                $key =
                    implode(':', $ids);

                if (isset($existingKeys[$key])) {
                    continue;
                }
            }

            $valueNames =
                array_map(
                    static fn(array $value): string =>
                        (string) $value['option_value'],
                    $combo
                );

            $variantName =
                $valueNames
                    ? implode(' / ', $valueNames)
                    : 'Default';

            $skuParts = [$skuPrefix];

            foreach ($valueNames as $valueName) {
                $piece =
                    admin_shop_sku_piece(
                        $valueName
                    );

                if ($piece !== '') {
                    $skuParts[] = $piece;
                }
            }

            $sku =
                admin_shop_unique_sku(
                    $db,
                    implode('-', $skuParts)
                );

            $slot = [];

            for ($i = 0; $i < 3; $i++) {
                $slot[$i] = [
                    'name' =>
                        isset($options[$i])
                            ? (string) $options[$i]['option_name']
                            : null,
                    'value' =>
                        isset($combo[$i])
                            ? (string) $combo[$i]['option_value']
                            : null,
                ];
            }

            $insertVariant->execute([
                $productId,
                $sku,
                $variantName,
                $slot[0]['name'],
                $slot[0]['value'],
                $slot[1]['name'],
                $slot[1]['value'],
                $slot[2]['name'],
                $slot[2]['value'],
                $priceCents,
                $trackInventory,
                $inventoryQuantity,
                $allowBackorder,
                $fulfillmentType,
                $fulfillmentProvider !== ''
                    ? $fulfillmentProvider
                    : null,
                $nextSort++,
            ]);

            $variantId =
                (int) $db->lastInsertId();

            foreach ($combo as $index => $value) {
                $insertLink->execute([
                    $variantId,
                    (int) $options[$index]['id'],
                    (int) $value['id'],
                    $index,
                ]);
            }

            $count++;
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'shop.variants_generated',
            'Generated ' .
                $count .
                ' product variant' .
                ($count === 1 ? '' : 's') .
                ' for "' .
                (string) $product['name'] .
                '".',
            [
                'product_id' => $productId,
                'created_count' => $count,
            ]
        );

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return $count;
}

function admin_shop_product_variants(
    PDO $db,
    int $productId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_product_variants
         WHERE product_id = ?
         ORDER BY
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([$productId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_shop_slugify(
    string $name
): string {
    $value = trim($name);

    if (
        function_exists('iconv')
        && $value !== ''
    ) {
        $converted = @iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        if (is_string($converted)) {
            $value = $converted;
        }
    }

    $value = strtolower($value);

    $value = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $value
    ) ?? '';

    $value = trim($value, '-');

    return $value !== ''
        ? $value
        : 'product';
}

function admin_shop_unique_slug(
    PDO $db,
    string $name
): string {
    $base = admin_shop_slugify($name);
    $slug = $base;
    $suffix = 2;

    while (true) {
        $stmt = $db->prepare(
            'SELECT id
             FROM shop_products
             WHERE slug = ?
             LIMIT 1'
        );

        $stmt->execute([$slug]);

        if (!$stmt->fetchColumn()) {
            return $slug;
        }

        $slug =
            $base .
            '-' .
            $suffix;

        $suffix++;
    }
}

function admin_shop_create_product(
    PDO $db,
    int $actorUserId,
    array $data
): int {
    $name = trim(
        (string) ($data['name'] ?? '')
    );

    $productType = trim(
        (string) ($data['product_type'] ?? '')
    );

    $requiresShipping =
        isset($data['requires_shipping'])
            ? 1
            : 0;

    if ($name === '') {
        throw new RuntimeException(
            'Product name is required.'
        );
    }

    $slug =
        admin_shop_unique_slug(
            $db,
            $name
        );

    $stmt = $db->prepare(
        'INSERT INTO shop_products (
            slug,
            name,
            status,
            product_type,
            requires_shipping,
            sort_order
         ) VALUES (
            ?, ?, "draft", ?, ?, 0
         )'
    );

    $stmt->execute([
        $slug,
        $name,
        $productType !== ''
            ? $productType
            : null,
        $requiresShipping,
    ]);

    $productId =
        (int) $db->lastInsertId();

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'shop.product_created',
        'Created draft shop product "' .
            $name .
            '".',
        [
            'product_id' => $productId,
            'slug' => $slug,
        ]
    );

    return $productId;
}

function admin_shop_save_product(
    PDO $db,
    int $actorUserId,
    int $productId,
    array $data
): void {
    $product = admin_shop_product(
        $db,
        $productId
    );

    if (!$product) {
        throw new RuntimeException(
            'Product not found.'
        );
    }

    $name = trim(
        (string) ($data['name'] ?? '')
    );

    $shortDescription = trim(
        (string) ($data['short_description'] ?? '')
    );

    $description = trim(
        (string) ($data['description'] ?? '')
    );

    $productType = trim(
        (string) ($data['product_type'] ?? '')
    );

    $status = trim(
        (string) ($data['status'] ?? 'draft')
    );

    $isFeatured =
        isset($data['is_featured'])
            ? 1
            : 0;

    $requiresShipping =
        isset($data['requires_shipping'])
            ? 1
            : 0;

    $sortOrder = (int) (
        $data['sort_order'] ?? 0
    );

    if ($name === '') {
        throw new RuntimeException(
            'Product name is required.'
        );
    }

    if (
        !in_array(
            $status,
            ['draft','active','archived'],
            true
        )
    ) {
        throw new RuntimeException(
            'Invalid product status.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE shop_products
         SET
            name = ?,
            short_description = ?,
            description = ?,
            status = ?,
            product_type = ?,
            is_featured = ?,
            requires_shipping = ?,
            sort_order = ?
         WHERE id = ?'
    );

    $stmt->execute([
        $name,
        $shortDescription !== ''
            ? $shortDescription
            : null,
        $description !== ''
            ? $description
            : null,
        $status,
        $productType !== ''
            ? $productType
            : null,
        $isFeatured,
        $requiresShipping,
        $sortOrder,
        $productId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'shop.product_updated',
        'Updated shop product "' .
            $name .
            '".',
        [
            'product_id' => $productId,
            'before' => [
                'name' => $product['name'],
                'slug' => $product['slug'],
                'status' => $product['status'],
            ],
            'after' => [
                'name' => $name,
                'slug' => $product['slug'],
                'status' => $status,
            ],
        ]
    );
}

function admin_shop_save_variant(
    PDO $db,
    int $actorUserId,
    int $variantId,
    array $data
): void {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_product_variants
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$variantId]);

    $variant =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new RuntimeException(
            'Variant not found.'
        );
    }

    $sku =
        trim(
            (string) (
                $data['sku']
                ?? $variant['sku']
            )
        );

    if ($sku === '') {
        throw new RuntimeException(
            'Variant SKU is required.'
        );
    }

    if (mb_strlen($sku) > 120) {
        throw new RuntimeException(
            'Variant SKU must be 120 characters or fewer.'
        );
    }

    $dupe =
        $db->prepare(
            'SELECT id
             FROM shop_product_variants
             WHERE sku = ?
               AND id <> ?
             LIMIT 1'
        );

    $dupe->execute([
        $sku,
        $variantId,
    ]);

    if ($dupe->fetchColumn()) {
        throw new RuntimeException(
            'Another variant already uses that SKU.'
        );
    }

    $priceRaw =
        trim(
            (string) (
                $data['price']
                ?? ''
            )
        );

    if (
        $priceRaw === ''
        || !is_numeric($priceRaw)
        || (float) $priceRaw < 0
    ) {
        throw new RuntimeException(
            'Enter a valid variant price.'
        );
    }

    $priceCents =
        (int) round(
            ((float) $priceRaw)
            * 100
        );

    $compareRaw =
        trim(
            (string) (
                $data['compare_at_price']
                ?? ''
            )
        );

    $compareCents =
        $compareRaw !== ''
            ? max(
                0,
                (int) round(
                    ((float) $compareRaw)
                    * 100
                )
            )
            : null;

    $trackInventory =
        isset($data['track_inventory'])
            ? 1
            : 0;

    $inventoryQuantity =
        (int) (
            $data['inventory_quantity']
            ?? 0
        );

    $allowBackorder =
        isset($data['allow_backorder'])
            ? 1
            : 0;

    $isActive =
        isset($data['is_active'])
            ? 1
            : 0;

    $sortOrder =
        (int) (
            $data['sort_order']
            ?? $variant['sort_order']
        );

    $fulfillmentType =
        trim(
            (string) (
                $data['fulfillment_type']
                ?? 'manual'
            )
        );

    $fulfillmentProvider =
        trim(
            (string) (
                $data['fulfillment_provider']
                ?? ''
            )
        );

    $fulfillmentProductId =
        trim(
            (string) (
                $data['fulfillment_product_id']
                ?? ''
            )
        );

    $fulfillmentVariantId =
        trim(
            (string) (
                $data['fulfillment_variant_id']
                ?? ''
            )
        );

    if (
        !in_array(
            $fulfillmentType,
            [
                'manual',
                'provider',
                'digital',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Invalid fulfillment type.'
        );
    }

    $update =
        $db->prepare(
            'UPDATE shop_product_variants
             SET
                sku = ?,
                price_cents = ?,
                compare_at_price_cents = ?,
                track_inventory = ?,
                inventory_quantity = ?,
                allow_backorder = ?,
                fulfillment_type = ?,
                fulfillment_provider = ?,
                fulfillment_product_id = ?,
                fulfillment_variant_id = ?,
                is_active = ?,
                sort_order = ?
             WHERE id = ?'
        );

    $update->execute([
        $sku,
        $priceCents,
        $compareCents,
        $trackInventory,
        $inventoryQuantity,
        $allowBackorder,
        $fulfillmentType,
        $fulfillmentProvider !== ''
            ? $fulfillmentProvider
            : null,
        $fulfillmentProductId !== ''
            ? $fulfillmentProductId
            : null,
        $fulfillmentVariantId !== ''
            ? $fulfillmentVariantId
            : null,
        $isActive,
        $sortOrder,
        $variantId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'shop.variant_updated',
        'Updated shop variant "' .
            (string) $variant['name'] .
            '".',
        [
            'product_id' =>
                (int) $variant['product_id'],
            'variant_id' => $variantId,
            'sku_before' =>
                $variant['sku'],
            'sku_after' => $sku,
        ]
    );
}

function admin_shop_orders(
    PDO $db,
    string $status = '',
    string $payment = ''
): array {
    $where = ['1 = 1'];
    $params = [];

    $status = trim($status);
    $payment = trim($payment);

    if ($status !== '') {
        $where[] = 'o.order_status = ?';
        $params[] = $status;
    }

    if ($payment !== '') {
        $where[] = 'o.payment_status = ?';
        $params[] = $payment;
    }

    $sql =
        'SELECT
            o.*,
            COUNT(DISTINCT oi.id) AS line_count,
            COALESCE(
                SUM(oi.quantity),
                0
            ) AS item_count,
            COUNT(DISTINCT f.id) AS fulfillment_count,
            MAX(f.status) AS fulfillment_status
         FROM shop_orders o
         LEFT JOIN shop_order_items oi
            ON oi.order_id = o.id
         LEFT JOIN shop_order_fulfillments f
            ON f.order_id = o.id
         WHERE ' .
            implode(' AND ', $where) .
        ' GROUP BY o.id
          ORDER BY o.created_at DESC
          LIMIT 300';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_shop_order(
    PDO $db,
    int $orderId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            o.*,
            u.username,
            u.display_name,
            ' . admin_user_profile_image_sql('u') . ' AS profile_image_src
         FROM shop_orders o
         LEFT JOIN users u
            ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1'
    );

    $stmt->execute([$orderId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_shop_order_items(
    PDO $db,
    int $orderId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_order_items
         WHERE order_id = ?
         ORDER BY id ASC'
    );

    $stmt->execute([$orderId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_shop_fulfillments(
    PDO $db,
    int $orderId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_order_fulfillments
         WHERE order_id = ?
         ORDER BY id ASC'
    );

    $stmt->execute([$orderId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_shop_save_order_status(
    PDO $db,
    int $actorUserId,
    int $orderId,
    string $status
): void {
    $allowed = [
        'pending',
        'paid',
        'processing',
        'submitted',
        'shipped',
        'delivered',
        'cancelled',
        'refunded',
        'problem',
    ];

    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException(
            'Invalid order status.'
        );
    }

    $order = admin_shop_order(
        $db,
        $orderId
    );

    if (!$order) {
        throw new RuntimeException(
            'Order not found.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE shop_orders
         SET
            order_status = ?,
            canceled_at = CASE
                WHEN ? = "cancelled"
                    THEN COALESCE(canceled_at, NOW())
                ELSE canceled_at
            END
         WHERE id = ?'
    );

    $stmt->execute([
        $status,
        $status,
        $orderId,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        $order['user_id']
            ? (int) $order['user_id']
            : null,
        'shop.order_status_updated',
        'Changed order ' .
            (string) $order['order_number'] .
            ' from ' .
            (string) $order['order_status'] .
            ' to ' .
            $status .
            '.',
        [
            'order_id' => $orderId,
            'before' => $order['order_status'],
            'after' => $status,
        ]
    );
}

function admin_shop_create_fulfillment(
    PDO $db,
    int $actorUserId,
    int $orderId,
    array $data
): int {
    $order = admin_shop_order(
        $db,
        $orderId
    );

    if (!$order) {
        throw new RuntimeException(
            'Order not found.'
        );
    }

    $type = trim(
        (string) ($data['fulfillment_type'] ?? 'manual')
    );

    $provider = trim(
        (string) ($data['fulfillment_provider'] ?? '')
    );

    $status = trim(
        (string) ($data['status'] ?? 'pending')
    );

    $trackingNumber = trim(
        (string) ($data['tracking_number'] ?? '')
    );

    $trackingUrl = trim(
        (string) ($data['tracking_url'] ?? '')
    );

    $providerOrderId = trim(
        (string) ($data['provider_order_id'] ?? '')
    );

    $allowedStatus = [
        'pending',
        'processing',
        'submitted',
        'shipped',
        'delivered',
        'problem',
        'cancelled',
    ];

    if (!in_array($status, $allowedStatus, true)) {
        throw new RuntimeException(
            'Invalid fulfillment status.'
        );
    }

    $stmt = $db->prepare(
        'INSERT INTO shop_order_fulfillments (
            order_id,
            fulfillment_type,
            fulfillment_provider,
            status,
            provider_order_id,
            tracking_number,
            tracking_url,
            submitted_at,
            shipped_at,
            delivered_at
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            CASE
                WHEN ? IN ("submitted","processing","shipped","delivered")
                    THEN NOW()
                ELSE NULL
            END,
            CASE
                WHEN ? IN ("shipped","delivered")
                    THEN NOW()
                ELSE NULL
            END,
            CASE
                WHEN ? = "delivered"
                    THEN NOW()
                ELSE NULL
            END
         )'
    );

    $stmt->execute([
        $orderId,
        $type,
        $provider !== '' ? $provider : null,
        $status,
        $providerOrderId !== ''
            ? $providerOrderId
            : null,
        $trackingNumber !== ''
            ? $trackingNumber
            : null,
        $trackingUrl !== ''
            ? $trackingUrl
            : null,
        $status,
        $status,
        $status,
    ]);

    $fulfillmentId =
        (int) $db->lastInsertId();

    $items = admin_shop_order_items(
        $db,
        $orderId
    );

    $itemStmt = $db->prepare(
        'INSERT INTO shop_order_fulfillment_items (
            fulfillment_id,
            order_item_id,
            quantity
         ) VALUES (?, ?, ?)'
    );

    foreach ($items as $item) {
        if ((int) $item['requires_shipping'] !== 1) {
            continue;
        }

        $itemStmt->execute([
            $fulfillmentId,
            (int) $item['id'],
            (int) $item['quantity'],
        ]);
    }

    if (
        in_array(
            $status,
            ['processing','submitted'],
            true
        )
    ) {
        admin_shop_save_order_status(
            $db,
            $actorUserId,
            $orderId,
            'processing'
        );
    } elseif ($status === 'shipped') {
        admin_shop_save_order_status(
            $db,
            $actorUserId,
            $orderId,
            'shipped'
        );
    } elseif ($status === 'delivered') {
        admin_shop_save_order_status(
            $db,
            $actorUserId,
            $orderId,
            'delivered'
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        $order['user_id']
            ? (int) $order['user_id']
            : null,
        'shop.fulfillment_created',
        'Created fulfillment for order ' .
            (string) $order['order_number'] .
            '.',
        [
            'order_id' => $orderId,
            'fulfillment_id' => $fulfillmentId,
            'status' => $status,
            'provider' => $provider,
        ]
    );

    return $fulfillmentId;
}

function admin_shop_update_fulfillment(
    PDO $db,
    int $actorUserId,
    int $fulfillmentId,
    array $data
): void {
    $stmt = $db->prepare(
        'SELECT
            f.*,
            o.order_number,
            o.user_id
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE f.id = ?
         LIMIT 1'
    );

    $stmt->execute([$fulfillmentId]);
    $fulfillment =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fulfillment) {
        throw new RuntimeException(
            'Fulfillment not found.'
        );
    }

    $status = trim(
        (string) ($data['status'] ?? 'pending')
    );

    $trackingNumber = trim(
        (string) ($data['tracking_number'] ?? '')
    );

    $trackingUrl = trim(
        (string) ($data['tracking_url'] ?? '')
    );

    $providerOrderId = trim(
        (string) ($data['provider_order_id'] ?? '')
    );

    $allowedStatus = [
        'pending',
        'processing',
        'submitted',
        'shipped',
        'delivered',
        'problem',
        'cancelled',
    ];

    if (!in_array($status, $allowedStatus, true)) {
        throw new RuntimeException(
            'Invalid fulfillment status.'
        );
    }

    $update = $db->prepare(
        'UPDATE shop_order_fulfillments
         SET
            status = ?,
            provider_order_id = ?,
            tracking_number = ?,
            tracking_url = ?,
            submitted_at = CASE
                WHEN ? IN ("submitted","processing","shipped","delivered")
                    THEN COALESCE(submitted_at, NOW())
                ELSE submitted_at
            END,
            shipped_at = CASE
                WHEN ? IN ("shipped","delivered")
                    THEN COALESCE(shipped_at, NOW())
                ELSE shipped_at
            END,
            delivered_at = CASE
                WHEN ? = "delivered"
                    THEN COALESCE(delivered_at, NOW())
                ELSE delivered_at
            END
         WHERE id = ?'
    );

    $update->execute([
        $status,
        $providerOrderId !== ''
            ? $providerOrderId
            : null,
        $trackingNumber !== ''
            ? $trackingNumber
            : null,
        $trackingUrl !== ''
            ? $trackingUrl
            : null,
        $status,
        $status,
        $status,
        $fulfillmentId,
    ]);

    if ($status === 'shipped') {
        admin_shop_save_order_status(
            $db,
            $actorUserId,
            (int) $fulfillment['order_id'],
            'shipped'
        );
    } elseif ($status === 'delivered') {
        admin_shop_save_order_status(
            $db,
            $actorUserId,
            (int) $fulfillment['order_id'],
            'delivered'
        );
    }

    admin_users_audit(
        $db,
        $actorUserId,
        $fulfillment['user_id']
            ? (int) $fulfillment['user_id']
            : null,
        'shop.fulfillment_updated',
        'Updated fulfillment #' .
            $fulfillmentId .
            ' for order ' .
            (string) $fulfillment['order_number'] .
            '.',
        [
            'fulfillment_id' => $fulfillmentId,
            'order_id' => (int) $fulfillment['order_id'],
            'status' => $status,
        ]
    );
}


function admin_shop_add_product_photos(
    PDO $db,
    int $actorUserId,
    int $productId,
    string $photoToken,
    array $photos
): int {
    $added = shop_images_add_staged(
        $actorUserId,
        $productId,
        $photoToken,
        $photos
    );

    $count = count($added);

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'shop.product_photos_added',
        'Added ' .
            $count .
            ' product photo' .
            ($count === 1 ? '' : 's') .
            '.',
        [
            'product_id' => $productId,
            'count' => $count,
        ]
    );

    return $count;
}

function admin_shop_set_primary_photo(
    PDO $db,
    int $actorUserId,
    int $productId,
    int $imageId
): void {
    shop_images_set_primary(
        $productId,
        $imageId
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'shop.product_primary_photo_changed',
        'Changed a product primary photo.',
        [
            'product_id' => $productId,
            'image_id' => $imageId,
        ]
    );
}

function admin_shop_delete_product_photo(
    PDO $db,
    int $actorUserId,
    int $productId,
    int $imageId
): void {
    shop_images_delete(
        $productId,
        $imageId
    );

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'shop.product_photo_deleted',
        'Deleted a product photo.',
        [
            'product_id' => $productId,
            'image_id' => $imageId,
        ]
    );
}
