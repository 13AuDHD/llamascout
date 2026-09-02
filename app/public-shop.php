<?php

declare(strict_types=1);

function public_shop_image_url(
    ?string $value,
    string $siteUrl
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (
        str_starts_with($value, 'http://')
        || str_starts_with($value, 'https://')
    ) {
        return $value;
    }

    return rtrim($siteUrl, '/')
        . '/'
        . ltrim($value, '/');
}

function public_shop_money(
    int $cents,
    string $currency = 'usd'
): string {
    $symbol =
        strtolower($currency) === 'usd'
            ? '$'
            : '';

    return $symbol
        . number_format(
            $cents / 100,
            2
        );
}

function public_shop_products(
    PDO $db
): array {
    $sql = '
        SELECT
            p.*,
            COALESCE(
                NULLIF(p.primary_image_url, ""),
                (
                    SELECT spi.image_url
                    FROM shop_product_images spi
                    WHERE spi.product_id = p.id
                    ORDER BY
                        spi.is_primary DESC,
                        spi.sort_order ASC,
                        spi.id ASC
                    LIMIT 1
                )
            ) AS display_image,
            (
                SELECT MIN(v.price_cents)
                FROM shop_product_variants v
                WHERE v.product_id = p.id
                  AND v.is_active = 1
            ) AS min_price_cents,
            (
                SELECT MAX(v.price_cents)
                FROM shop_product_variants v
                WHERE v.product_id = p.id
                  AND v.is_active = 1
            ) AS max_price_cents,
            (
                SELECT COUNT(*)
                FROM shop_product_variants v
                WHERE v.product_id = p.id
                  AND v.is_active = 1
            ) AS active_variant_count
        FROM shop_products p
        WHERE p.status = "active"
        ORDER BY
            p.is_featured DESC,
            p.sort_order ASC,
            p.name ASC
    ';

    return $db->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}

function public_shop_product_by_slug(
    PDO $db,
    string $slug
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_products
         WHERE slug = ?
           AND status = "active"
         LIMIT 1'
    );

    $stmt->execute([$slug]);

    $product =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $product ?: null;
}

function public_shop_product_images(
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

    return $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}

function public_shop_product_options(
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
            $valueStmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];
    }

    unset($option);

    return $options;
}

function public_shop_product_variants(
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

    $variants =
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];

    if (!$variants) {
        return [];
    }

    $valueStmt = $db->prepare(
        'SELECT
            o.option_name,
            ov.option_value
         FROM shop_product_variant_values vv
         INNER JOIN shop_product_options o
            ON o.id = vv.option_id
         INNER JOIN shop_product_option_values ov
            ON ov.id = vv.option_value_id
         WHERE vv.variant_id = ?
         ORDER BY
            o.option_position ASC,
            o.id ASC'
    );

    foreach ($variants as &$variant) {
        $valueStmt->execute([
            (int) $variant['id'],
        ]);

        $pairs =
            $valueStmt->fetchAll(PDO::FETCH_ASSOC)
            ?: [];

        $variant['_options'] = [];

        foreach ($pairs as $pair) {
            $name =
                trim(
                    (string) (
                        $pair['option_name']
                        ?? ''
                    )
                );

            $value =
                trim(
                    (string) (
                        $pair['option_value']
                        ?? ''
                    )
                );

            if ($name !== '' && $value !== '') {
                $variant['_options'][$name] = $value;
            }
        }

        $variant['_storefront'] =
            public_shop_variant_storefront_meta(
                $variant
            );
    }

    unset($variant);

    return $variants;
}

function public_shop_variant_storefront_meta(
    array $variant
): array {
    $data = [];

    $raw =
        $variant['fulfillment_data']
        ?? null;

    if (is_string($raw) && trim($raw) !== '') {
        $decoded =
            json_decode(
                $raw,
                true
            );

        if (is_array($decoded)) {
            $data = $decoded;
        }
    } elseif (is_array($raw)) {
        $data = $raw;
    }

    $availabilityMode =
        strtolower(
            trim(
                (string) (
                    $data['storefront_availability']
                    ?? 'standard'
                )
            )
        );

    if (
        !in_array(
            $availabilityMode,
            ['standard', 'preorder'],
            true
        )
    ) {
        $availabilityMode = 'standard';
    }

    $lowStockThreshold =
        max(
            0,
            (int) (
                $data['low_stock_threshold']
                ?? 5
            )
        );

    $maxPerOrder =
        max(
            0,
            (int) (
                $data['max_per_order']
                ?? 0
            )
        );

    if ($maxPerOrder > 20) {
        $maxPerOrder = 20;
    }

    return [
        'availability_mode' =>
            $availabilityMode,
        'low_stock_threshold' =>
            $lowStockThreshold,
        'max_per_order' =>
            $maxPerOrder,
    ];
}

function public_shop_variant_state(
    array $variant
): array {
    $meta =
        is_array(
            $variant['_storefront']
            ?? null
        )
            ? $variant['_storefront']
            : public_shop_variant_storefront_meta(
                $variant
            );

    $active =
        (int) (
            $variant['is_active']
            ?? 0
        ) === 1;

    $priceCents =
        (int) (
            $variant['price_cents']
            ?? 0
        );

    $trackInventory =
        (int) (
            $variant['track_inventory']
            ?? 0
        ) === 1;

    $quantity =
        (int) (
            $variant['inventory_quantity']
            ?? 0
        );

    $allowBackorder =
        (int) (
            $variant['allow_backorder']
            ?? 0
        ) === 1;

    $mode =
        (string) (
            $meta['availability_mode']
            ?? 'standard'
        );

    $threshold =
        max(
            0,
            (int) (
                $meta['low_stock_threshold']
                ?? 5
            )
        );

    if (!$active || $priceCents <= 0) {
        return [
            'key' => 'unavailable',
            'label' => 'Unavailable',
            'purchasable' => false,
        ];
    }

    if ($mode === 'preorder') {
        return [
            'key' => 'preorder',
            'label' => 'Preorder',
            'purchasable' => true,
        ];
    }

    if (!$trackInventory) {
        return [
            'key' => 'in_stock',
            'label' => 'In stock',
            'purchasable' => true,
        ];
    }

    if ($quantity > 0) {
        if (
            $threshold > 0
            && $quantity <= $threshold
        ) {
            return [
                'key' => 'low_stock',
                'label' =>
                    'Low stock'
                    . ($quantity > 0
                        ? ' Â· '
                            . $quantity
                            . ' left'
                        : ''),
                'purchasable' => true,
            ];
        }

        return [
            'key' => 'in_stock',
            'label' => 'In stock',
            'purchasable' => true,
        ];
    }

    if ($allowBackorder) {
        return [
            'key' => 'backorder',
            'label' => 'Backorder',
            'purchasable' => true,
        ];
    }

    return [
        'key' => 'out_of_stock',
        'label' => 'Out of stock',
        'purchasable' => false,
    ];
}

function public_shop_variant_max_quantity(
    array $variant
): int {
    $meta =
        is_array(
            $variant['_storefront']
            ?? null
        )
            ? $variant['_storefront']
            : public_shop_variant_storefront_meta(
                $variant
            );

    $configured =
        max(
            0,
            (int) (
                $meta['max_per_order']
                ?? 0
            )
        );

    $max =
        $configured > 0
            ? min(20, $configured)
            : 20;

    $state =
        public_shop_variant_state(
            $variant
        );

    if (
        !$state['purchasable']
    ) {
        return 0;
    }

    if (
        (string) $state['key']
        === 'preorder'
        || (string) $state['key']
        === 'backorder'
    ) {
        return $max;
    }

    if (
        (int) (
            $variant['track_inventory']
            ?? 0
        ) === 1
    ) {
        $available =
            max(
                0,
                (int) (
                    $variant['inventory_quantity']
                    ?? 0
                )
            );

        $max =
            min(
                $max,
                $available
            );
    }

    return max(0, $max);
}

function public_shop_price_label(
    array $product
): string {
    $min =
        $product['min_price_cents']
        ?? null;

    $max =
        $product['max_price_cents']
        ?? null;

    if ($min === null) {
        return 'Pricing coming soon';
    }

    $min = (int) $min;
    $max = $max !== null
        ? (int) $max
        : $min;

    if ($min === $max) {
        return public_shop_money($min);
    }

    return public_shop_money($min)
        . ' â '
        . public_shop_money($max);
}

function public_shop_variant_availability(
    array $variant
): string {
    $state =
        public_shop_variant_state(
            $variant
        );

    return (string) $state['label'];
}
