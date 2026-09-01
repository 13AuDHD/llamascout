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
           AND is_active = 1
         ORDER BY
            sort_order ASC,
            id ASC'
    );

    $stmt->execute([$productId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
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
        . ' – '
        . public_shop_money($max);
}

function public_shop_variant_availability(
    array $variant
): string {
    if (!(int) ($variant['track_inventory'] ?? 0)) {
        return 'Available';
    }

    $quantity =
        (int) (
            $variant['inventory_quantity']
            ?? 0
        );

    if ($quantity > 0) {
        return $quantity
            . ' in stock';
    }

    if (
        (int) (
            $variant['allow_backorder']
            ?? 0
        ) === 1
    ) {
        return 'Available to order';
    }

    return 'Out of stock';
}
