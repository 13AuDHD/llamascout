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

function admin_shop_create_product(
    PDO $db,
    int $actorUserId,
    array $data
): int {
    $name = trim(
        (string) ($data['name'] ?? '')
    );

    $slug = strtolower(
        trim(
            (string) ($data['slug'] ?? '')
        )
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

    if (
        $slug === ''
        || !preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $slug
        )
    ) {
        throw new RuntimeException(
            'Product slug must contain lowercase letters, numbers, and hyphens.'
        );
    }

    $dupe = $db->prepare(
        'SELECT id
         FROM shop_products
         WHERE slug = ?
         LIMIT 1'
    );

    $dupe->execute([$slug]);

    if ($dupe->fetchColumn()) {
        throw new RuntimeException(
            'Another product already uses that slug.'
        );
    }

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

    $slug = strtolower(
        trim(
            (string) ($data['slug'] ?? '')
        )
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
        $slug === ''
        || !preg_match(
            '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            $slug
        )
    ) {
        throw new RuntimeException(
            'Product slug must contain lowercase letters, numbers, and hyphens.'
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

    $dupe = $db->prepare(
        'SELECT id
         FROM shop_products
         WHERE slug = ?
           AND id <> ?
         LIMIT 1'
    );

    $dupe->execute([
        $slug,
        $productId,
    ]);

    if ($dupe->fetchColumn()) {
        throw new RuntimeException(
            'Another product already uses that slug.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE shop_products
         SET
            slug = ?,
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
        $slug,
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
                'slug' => $slug,
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
    $variant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant) {
        throw new RuntimeException(
            'Variant not found.'
        );
    }

    $priceCents = max(
        0,
        (int) round(
            ((float) ($data['price'] ?? 0))
            * 100
        )
    );

    $compareRaw = trim(
        (string) ($data['compare_at_price'] ?? '')
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
        (int) ($data['inventory_quantity'] ?? 0);

    $allowBackorder =
        isset($data['allow_backorder'])
            ? 1
            : 0;

    $isActive =
        isset($data['is_active'])
            ? 1
            : 0;

    $fulfillmentType = trim(
        (string) ($data['fulfillment_type'] ?? 'manual')
    );

    $fulfillmentProvider = trim(
        (string) ($data['fulfillment_provider'] ?? '')
    );

    if (
        !in_array(
            $fulfillmentType,
            ['manual','provider','digital'],
            true
        )
    ) {
        throw new RuntimeException(
            'Invalid fulfillment type.'
        );
    }

    $update = $db->prepare(
        'UPDATE shop_product_variants
         SET
            price_cents = ?,
            compare_at_price_cents = ?,
            track_inventory = ?,
            inventory_quantity = ?,
            allow_backorder = ?,
            fulfillment_type = ?,
            fulfillment_provider = ?,
            is_active = ?
         WHERE id = ?'
    );

    $update->execute([
        $priceCents,
        $compareCents,
        $trackInventory,
        $inventoryQuantity,
        $allowBackorder,
        $fulfillmentType,
        $fulfillmentProvider !== ''
            ? $fulfillmentProvider
            : null,
        $isActive,
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
            'sku' => $variant['sku'],
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
