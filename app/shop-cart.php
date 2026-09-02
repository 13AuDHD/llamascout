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

function shop_cart_variant_storefront_meta(
    array $variant
): array {
    $data = [];

    $raw =
        $variant['fulfillment_data']
        ?? null;

    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $data = $decoded;
        }
    } elseif (is_array($raw)) {
        $data = $raw;
    }

    $mode =
        strtolower(
            trim(
                (string) (
                    $data['storefront_availability']
                    ?? 'standard'
                )
            )
        );

    if (!in_array($mode, ['standard', 'preorder'], true)) {
        $mode = 'standard';
    }

    $maxPerOrder =
        max(
            0,
            (int) (
                $data['max_per_order']
                ?? 0
            )
        );

    return [
        'availability_mode' => $mode,
        'max_per_order' =>
            $maxPerOrder > 0
                ? min(20, $maxPerOrder)
                : 20,
    ];
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
                p.requires_shipping
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

function shop_cart_variant_options(
    array $variant
): array {
    $options = [];

    for ($slot = 1; $slot <= 3; $slot++) {
        $name = trim((string) (
            $variant['option_' . [1 => 'one', 2 => 'two', 3 => 'three'][$slot] . '_name']
            ?? ''
        ));
        $value = trim((string) (
            $variant['option_' . [1 => 'one', 2 => 'two', 3 => 'three'][$slot] . '_value']
            ?? ''
        ));

        if ($name !== '' && $value !== '') {
            $options[$name] = $value;
        }
    }

    return $options;
}

function shop_cart_image_criteria(array $image): array
{
    $optionName = trim((string) ($image['option_name'] ?? ''));
    $optionValue = trim((string) ($image['option_value'] ?? ''));

    if ($optionName === '' || $optionValue === '') {
        return [];
    }

    if ($optionName === '__criteria__') {
        $decoded = json_decode($optionValue, true);
        if (!is_array($decoded)) {
            return [];
        }

        $criteria = [];
        foreach ($decoded as $name => $values) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            if (!is_array($values)) {
                $values = [$values];
            }
            $clean = [];
            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $clean[$value] = $value;
                }
            }
            if ($clean) {
                $criteria[$name] = array_values($clean);
            }
        }
        return $criteria;
    }

    if ($optionName === '__variant__') {
        return ['__variant__' => [$optionValue]];
    }

    return [$optionName => [$optionValue]];
}

function shop_cart_image_matches_variant(array $image, array $variant): bool
{
    $criteria = shop_cart_image_criteria($image);
    if (!$criteria) {
        return true;
    }

    if (!empty($criteria['__variant__'])) {
        return in_array((string) ($variant['id'] ?? ''), $criteria['__variant__'], true);
    }

    $options = shop_cart_variant_options($variant);
    foreach ($criteria as $name => $values) {
        if ($name === '__variant__') {
            continue;
        }
        if (!isset($options[$name]) || !in_array((string) $options[$name], $values, true)) {
            return false;
        }
    }

    return true;
}

function shop_cart_image_specificity(array $image): int
{
    $criteria = shop_cart_image_criteria($image);
    if (!$criteria) {
        return 0;
    }
    if (!empty($criteria['__variant__'])) {
        return 10000;
    }

    $groups = count($criteria);
    $breadth = 0;
    foreach ($criteria as $values) {
        $breadth += count((array) $values);
    }
    return ($groups * 100) - $breadth;
}

function shop_cart_variant_image(
    PDO $db,
    array $variant
): string {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_product_images
         WHERE product_id = ?
         ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $stmt->execute([(int) ($variant['product_id'] ?? 0)]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $best = null;
    $bestScore = -1;

    foreach ($images as $image) {
        if (!shop_cart_image_matches_variant($image, $variant)) {
            continue;
        }
        $score = shop_cart_image_specificity($image);
        if ($score > $bestScore) {
            $best = $image;
            $bestScore = $score;
        }
    }

    return trim((string) ($best['image_url'] ?? ''));
}

function shop_cart_available_quantity(
    array $variant
): ?int {
    $meta =
        shop_cart_variant_storefront_meta(
            $variant
        );

    if (
        $meta['availability_mode']
        === 'preorder'
    ) {
        return null;
    }

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

function shop_cart_max_quantity_for_variant(array $variant): int
{
    $meta = shop_cart_variant_storefront_meta($variant);
    $max = max(1, (int) ($meta['max_per_order'] ?? 20));
    $available = shop_cart_available_quantity($variant);
    if ($available !== null) {
        $max = min($max, max(0, $available));
    }
    return max(0, $max);
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

    if (
        (int) (
            $variant['price_cents']
            ?? 0
        ) <= 0
    ) {
        throw new RuntimeException(
            'That product option is unavailable.'
        );
    }

    $meta =
        shop_cart_variant_storefront_meta(
            $variant
        );

    $maxPerOrder =
        max(
            1,
            (int) (
                $meta['max_per_order']
                ?? 20
            )
        );

    $quantity =
        max(
            1,
            min(
                $maxPerOrder,
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

    if ($newQuantity > $maxPerOrder) {
        throw new RuntimeException(
            'You can add up to '
            . $maxPerOrder
            . ' of this item per order.'
        );
    }

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

    $meta =
        shop_cart_variant_storefront_meta(
            $variant
        );

    $maxPerOrder =
        max(
            1,
            (int) (
                $meta['max_per_order']
                ?? 20
            )
        );

    $quantity =
        min(
            $maxPerOrder,
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
): ?array {
    $cart = shop_cart_items();
    $key = shop_cart_key($variantId);
    $removed = isset($cart[$key]) && is_array($cart[$key])
        ? $cart[$key]
        : null;

    if ($removed) {
        $_SESSION['shop_cart_undo'] = [
            'variant_id' => $variantId,
            'quantity' => max(1, (int) ($removed['quantity'] ?? 1)),
        ];
    }

    unset($cart[$key]);
    $_SESSION['shop_cart'] = $cart;

    return $removed;
}

function shop_cart_undo_available(): ?array
{
    $undo = $_SESSION['shop_cart_undo'] ?? null;
    return is_array($undo) ? $undo : null;
}

function shop_cart_clear_undo(): void
{
    unset($_SESSION['shop_cart_undo']);
}

function shop_cart_restore_removed(PDO $db): void
{
    $undo = shop_cart_undo_available();
    if (!$undo) {
        throw new RuntimeException('There is no recently removed item to restore.');
    }

    $variantId = (int) ($undo['variant_id'] ?? 0);
    $quantity = max(1, (int) ($undo['quantity'] ?? 1));
    shop_cart_add($db, $variantId, $quantity);
    shop_cart_clear_undo();
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

        $variant['image_url'] =
            shop_cart_variant_image(
                $db,
                $variant
            );

        $variant['max_quantity'] =
            shop_cart_max_quantity_for_variant(
                $variant
            );

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
