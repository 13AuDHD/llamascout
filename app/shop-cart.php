<?php

declare(strict_types=1);

function shop_cart_csrf_token(): string
{
    if (
        empty(
            $_SESSION['shop_cart_csrf']
        )
    ) {
        $_SESSION['shop_cart_csrf'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return
        (string)
        $_SESSION['shop_cart_csrf'];
}

function shop_cart_verify_csrf(
    string $token
): bool {
    $expected =
        $_SESSION['shop_cart_csrf']
        ?? '';

    return
        is_string($expected)
        && $expected !== ''
        && $token !== ''
        && hash_equals(
            $expected,
            $token
        );
}

function shop_cart_items(): array
{
    $items =
        $_SESSION['shop_cart']
        ?? [];

    return
        is_array($items)
            ? $items
            : [];
}

function shop_cart_count(): int
{
    $count = 0;

    foreach (
        shop_cart_items()
        as $item
    ) {
        $count +=
            max(
                0,
                (int) (
                    $item['quantity']
                    ?? 0
                )
            );
    }

    return $count;
}

function shop_cart_key(
    int $variantId
): string {
    return
        'variant-' .
        $variantId;
}

function shop_cart_load_variant(
    PDO $db,
    int $variantId
): ?array {
    $stmt =
        $db->prepare(
            'SELECT
                v.*,
                p.name AS product_name,
                p.slug AS product_slug,
                p.status AS product_status,
                p.requires_shipping,
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
                ) AS image_url
             FROM shop_product_variants v
             INNER JOIN shop_products p
                ON p.id = v.product_id
             WHERE v.id = ?
               AND v.is_active = 1
               AND p.status = "active"
             LIMIT 1'
        );

    $stmt->execute([
        $variantId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
        ?: null;
}

function shop_cart_available_quantity(
    array $variant
): ?int {
    if (
        (int) (
            $variant[
                'track_inventory'
            ]
            ?? 0
        ) !== 1
    ) {
        return null;
    }

    if (
        (int) (
            $variant[
                'allow_backorder'
            ]
            ?? 0
        ) === 1
    ) {
        return null;
    }

    return
        max(
            0,
            (int) (
                $variant[
                    'inventory_quantity'
                ]
                ?? 0
            )
        );
}

function shop_cart_add(
    PDO $db,
    int $variantId,
    int $quantity = 1
): void {
    $variant =
        shop_cart_load_variant(
            $db,
            $variantId
        );

    if (!$variant) {
        throw new RuntimeException(
            'That product option is not available.'
        );
    }

    $quantity =
        max(
            1,
            min(
                20,
                $quantity
            )
        );

    $key =
        shop_cart_key(
            $variantId
        );

    $cart =
        shop_cart_items();

    $current =
        isset($cart[$key])
            ? (int) (
                $cart[$key][
                    'quantity'
                ]
                ?? 0
            )
            : 0;

    $newQuantity =
        $current
        + $quantity;

    $available =
        shop_cart_available_quantity(
            $variant
        );

    if (
        $available !== null
        && $newQuantity > $available
    ) {
        throw new RuntimeException(
            $available > 0
                ? 'Only ' .
                    $available .
                    ' of that option ' .
                    (
                        $available === 1
                            ? 'is'
                            : 'are'
                    ) .
                    ' currently available.'
                : 'That option is currently out of stock.'
        );
    }

    $cart[$key] = [
        'variant_id' =>
            $variantId,
        'quantity' =>
            $newQuantity,
    ];

    $_SESSION['shop_cart'] =
        $cart;
}

function shop_cart_update(
    PDO $db,
    int $variantId,
    int $quantity
): void {
    $key =
        shop_cart_key(
            $variantId
        );

    $cart =
        shop_cart_items();

    if ($quantity <= 0) {
        unset(
            $cart[$key]
        );

        $_SESSION['shop_cart'] =
            $cart;

        return;
    }

    $variant =
        shop_cart_load_variant(
            $db,
            $variantId
        );

    if (!$variant) {
        unset(
            $cart[$key]
        );

        $_SESSION['shop_cart'] =
            $cart;

        return;
    }

    $quantity =
        min(
            20,
            $quantity
        );

    $available =
        shop_cart_available_quantity(
            $variant
        );

    if (
        $available !== null
        && $quantity > $available
    ) {
        throw new RuntimeException(
            $available > 0
                ? 'Only ' .
                    $available .
                    ' of that option ' .
                    (
                        $available === 1
                            ? 'is'
                            : 'are'
                    ) .
                    ' currently available.'
                : 'That option is currently out of stock.'
        );
    }

    $cart[$key] = [
        'variant_id' =>
            $variantId,
        'quantity' =>
            $quantity,
    ];

    $_SESSION['shop_cart'] =
        $cart;
}

function shop_cart_remove(
    int $variantId
): void {
    $cart =
        shop_cart_items();

    unset(
        $cart[
            shop_cart_key(
                $variantId
            )
        ]
    );

    $_SESSION['shop_cart'] =
        $cart;
}

function shop_cart_clear(): void
{
    $_SESSION['shop_cart'] = [];
}

function shop_cart_detailed_items(
    PDO $db
): array {
    $cart =
        shop_cart_items();

    $items = [];
    $cleanCart = [];

    foreach ($cart as $key => $entry) {
        $variantId =
            (int) (
                $entry['variant_id']
                ?? 0
            );

        $quantity =
            max(
                1,
                (int) (
                    $entry['quantity']
                    ?? 1
                )
            );

        if ($variantId < 1) {
            continue;
        }

        $variant =
            shop_cart_load_variant(
                $db,
                $variantId
            );

        if (!$variant) {
            continue;
        }

        $available =
            shop_cart_available_quantity(
                $variant
            );

        if (
            $available !== null
            && $available < 1
        ) {
            continue;
        }

        if (
            $available !== null
            && $quantity > $available
        ) {
            $quantity =
                $available;
        }

        $lineTotal =
            (int)
            $variant['price_cents']
            * $quantity;

        $variant['quantity'] =
            $quantity;

        $variant[
            'line_total_cents'
        ] =
            $lineTotal;

        $items[] =
            $variant;

        $cleanCart[$key] = [
            'variant_id' =>
                $variantId,
            'quantity' =>
                $quantity,
        ];
    }

    $_SESSION['shop_cart'] =
        $cleanCart;

    return $items;
}

function shop_cart_subtotal(
    array $items
): int {
    $subtotal = 0;

    foreach ($items as $item) {
        $subtotal +=
            (int) (
                $item[
                    'line_total_cents'
                ]
                ?? 0
            );
    }

    return $subtotal;
}
