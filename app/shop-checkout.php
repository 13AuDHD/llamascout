<?php

declare(strict_types=1);

require_once __DIR__ . '/shop-cart.php';
require_once __DIR__ . '/stripe.php';

function shop_checkout_table_exists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function shop_checkout_column_exists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function shop_checkout_add_column(PDO $db, string $table, string $column, string $definition): void
{
    if (shop_checkout_column_exists($db, $table, $column)) {
        return;
    }

    if (!preg_match('/^[a-z0-9_]+$/i', $table) || !preg_match('/^[a-z0-9_]+$/i', $column)) {
        throw new RuntimeException('Invalid Shop storage identifier.');
    }

    $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
}

function shop_checkout_ensure_storage(PDO $db): void
{
    if ($db->inTransaction()) {
        throw new RuntimeException('Shop checkout storage cannot be initialized inside an active transaction.');
    }

    if (!shop_checkout_table_exists($db, 'shop_orders')) {
        $db->exec(
            'CREATE TABLE shop_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_number VARCHAR(40) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                currency CHAR(3) NOT NULL DEFAULT "usd",
                subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
                shipping_cents INT UNSIGNED NOT NULL DEFAULT 0,
                tax_cents INT UNSIGNED NOT NULL DEFAULT 0,
                discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                total_cents INT UNSIGNED NOT NULL DEFAULT 0,
                order_status VARCHAR(30) NOT NULL DEFAULT "pending",
                payment_status VARCHAR(30) NOT NULL DEFAULT "pending",
                customer_email VARCHAR(255) NULL,
                shipping_name VARCHAR(255) NULL,
                shipping_phone VARCHAR(80) NULL,
                shipping_address_json LONGTEXT NULL,
                billing_address_json LONGTEXT NULL,
                stripe_checkout_session_id VARCHAR(255) NULL,
                stripe_payment_intent_id VARCHAR(255) NULL,
                stripe_customer_id VARCHAR(255) NULL,
                inventory_committed_at DATETIME NULL,
                checkout_expires_at DATETIME NULL,
                paid_at DATETIME NULL,
                canceled_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_shop_orders_number (order_number),
                UNIQUE KEY uq_shop_orders_checkout (stripe_checkout_session_id),
                KEY idx_shop_orders_user (user_id),
                KEY idx_shop_orders_status (order_status, payment_status),
                KEY idx_shop_orders_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    $orderColumns = [
        'order_number' => 'VARCHAR(40) NULL',
        'user_id' => 'BIGINT UNSIGNED NULL',
        'currency' => 'CHAR(3) NOT NULL DEFAULT "usd"',
        'subtotal_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'shipping_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'tax_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'discount_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'total_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'order_status' => 'VARCHAR(30) NOT NULL DEFAULT "pending"',
        'payment_status' => 'VARCHAR(30) NOT NULL DEFAULT "pending"',
        'customer_email' => 'VARCHAR(255) NULL',
        'shipping_name' => 'VARCHAR(255) NULL',
        'shipping_phone' => 'VARCHAR(80) NULL',
        'shipping_address_json' => 'LONGTEXT NULL',
        'billing_address_json' => 'LONGTEXT NULL',
        'stripe_checkout_session_id' => 'VARCHAR(255) NULL',
        'stripe_payment_intent_id' => 'VARCHAR(255) NULL',
        'stripe_customer_id' => 'VARCHAR(255) NULL',
        'inventory_committed_at' => 'DATETIME NULL',
        'checkout_expires_at' => 'DATETIME NULL',
        'paid_at' => 'DATETIME NULL',
        'canceled_at' => 'DATETIME NULL',
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];

    foreach ($orderColumns as $column => $definition) {
        shop_checkout_add_column($db, 'shop_orders', $column, $definition);
    }

    if (!shop_checkout_table_exists($db, 'shop_order_items')) {
        $db->exec(
            'CREATE TABLE shop_order_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                variant_id BIGINT UNSIGNED NULL,
                product_name VARCHAR(255) NOT NULL,
                variant_name VARCHAR(255) NULL,
                sku VARCHAR(190) NULL,
                quantity INT UNSIGNED NOT NULL,
                unit_price_cents INT UNSIGNED NOT NULL,
                line_total_cents INT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT "usd",
                requires_shipping TINYINT(1) NOT NULL DEFAULT 1,
                fulfillment_type VARCHAR(40) NULL,
                fulfillment_provider VARCHAR(100) NULL,
                fulfillment_product_id VARCHAR(255) NULL,
                fulfillment_variant_id VARCHAR(255) NULL,
                variant_snapshot_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_shop_order_items_order (order_id),
                KEY idx_shop_order_items_variant (variant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    $itemColumns = [
        'order_id' => 'BIGINT UNSIGNED NOT NULL',
        'product_id' => 'BIGINT UNSIGNED NULL',
        'variant_id' => 'BIGINT UNSIGNED NULL',
        'product_name' => 'VARCHAR(255) NULL',
        'variant_name' => 'VARCHAR(255) NULL',
        'sku' => 'VARCHAR(190) NULL',
        'quantity' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'unit_price_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'line_total_cents' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'currency' => 'CHAR(3) NOT NULL DEFAULT "usd"',
        'requires_shipping' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'fulfillment_type' => 'VARCHAR(40) NULL',
        'fulfillment_provider' => 'VARCHAR(100) NULL',
        'fulfillment_product_id' => 'VARCHAR(255) NULL',
        'fulfillment_variant_id' => 'VARCHAR(255) NULL',
        'variant_snapshot_json' => 'LONGTEXT NULL',
        'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($itemColumns as $column => $definition) {
        shop_checkout_add_column($db, 'shop_order_items', $column, $definition);
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS shop_inventory_reservations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "active",
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            released_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_shop_reservation_order_variant (order_id, variant_id),
            KEY idx_shop_reservation_variant (variant_id, status, expires_at),
            KEY idx_shop_reservation_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS shop_stripe_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            stripe_event_id VARCHAR(255) NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT "processing",
            error_message TEXT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_shop_stripe_event (stripe_event_id),
            KEY idx_shop_stripe_order (order_id),
            KEY idx_shop_stripe_status (status, received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function shop_checkout_settings(): array
{
    $config = llama_stripe_config();
    $shop = is_array($config['shop'] ?? null) ? $config['shop'] : [];

    $countries = [];
    foreach ((array) ($shop['shipping_countries'] ?? []) as $country) {
        $country = strtoupper(trim((string) $country));
        if (preg_match('/^[A-Z]{2}$/', $country)) {
            $countries[$country] = $country;
        }
    }

    $shippingRateIds = [];
    foreach ((array) ($shop['shipping_rate_ids'] ?? []) as $rateId) {
        $rateId = trim((string) $rateId);
        if ($rateId !== '') {
            $shippingRateIds[$rateId] = $rateId;
        }
    }

    return [
        'shipping_countries' => array_values($countries),
        'shipping_rate_ids' => array_values($shippingRateIds),
        'automatic_tax' => !empty($shop['automatic_tax']),
    ];
}

function shop_checkout_publishable_key(): string
{
    $config = llama_stripe_config();
    $key = trim((string) ($config['publishable_key'] ?? $config['public_key'] ?? ''));
    if ($key === '') {
        throw new RuntimeException('Stripe publishable key is missing.');
    }
    return $key;
}

function shop_checkout_webhook_secret(): string
{
    $config = llama_stripe_config();
    $secret = trim((string) ($config['shop_webhook_secret'] ?? ''));
    if ($secret === '') {
        throw new RuntimeException('Stripe Shop webhook secret is missing.');
    }
    return $secret;
}

function shop_checkout_order_number(): string
{
    return 'LS-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function shop_checkout_current_account(PDO $db): ?array
{
    $user = current_user();
    if (!$user || empty($user['id'])) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT id, email, username, display_name, stripe_customer_id FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function shop_checkout_requires_shipping(array $items): bool
{
    foreach ($items as $item) {
        if ((int) ($item['requires_shipping'] ?? 0) === 1) {
            return true;
        }
    }
    return false;
}

function shop_checkout_currency(array $items): string
{
    $currency = '';
    foreach ($items as $item) {
        $itemCurrency = strtolower(trim((string) ($item['currency'] ?? 'usd')));
        if (!preg_match('/^[a-z]{3}$/', $itemCurrency)) {
            throw new RuntimeException('A Shop item has an invalid currency.');
        }
        if ($currency === '') {
            $currency = $itemCurrency;
        } elseif ($currency !== $itemCurrency) {
            throw new RuntimeException('A single order cannot contain multiple currencies.');
        }
    }
    return $currency !== '' ? $currency : 'usd';
}

function shop_checkout_release_expired_reservations(PDO $db): void
{
    $db->exec(
        'UPDATE shop_inventory_reservations SET status = "released", released_at = COALESCE(released_at, UTC_TIMESTAMP()) WHERE status = "active" AND expires_at <= UTC_TIMESTAMP()'
    );
}

function shop_checkout_active_reserved_quantity(PDO $db, int $variantId, int $excludeOrderId = 0): int
{
    $sql = 'SELECT COALESCE(SUM(quantity), 0) FROM shop_inventory_reservations WHERE variant_id = ? AND status = "active" AND expires_at > UTC_TIMESTAMP()';
    $params = [$variantId];
    if ($excludeOrderId > 0) {
        $sql .= ' AND order_id <> ?';
        $params[] = $excludeOrderId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return max(0, (int) $stmt->fetchColumn());
}

function shop_checkout_create_pending_order(PDO $db, array $items, ?array $account, int $expiresAt): array
{
    if (!$items) {
        throw new RuntimeException('Your cart is empty.');
    }

    $currency = shop_checkout_currency($items);
    $subtotal = shop_cart_subtotal($items);
    if ($subtotal <= 0) {
        throw new RuntimeException('Your cart does not contain anything available for checkout.');
    }

    shop_checkout_release_expired_reservations($db);

    $db->beginTransaction();
    try {
        $orderNumber = shop_checkout_order_number();
        $insert = $db->prepare(
            'INSERT INTO shop_orders (order_number, user_id, currency, subtotal_cents, total_cents, order_status, payment_status, customer_email, stripe_customer_id, checkout_expires_at) VALUES (?, ?, ?, ?, ?, "pending", "pending", ?, ?, FROM_UNIXTIME(?))'
        );
        $insert->execute([
            $orderNumber,
            $account ? (int) $account['id'] : null,
            $currency,
            $subtotal,
            $subtotal,
            $account ? (string) ($account['email'] ?? '') : null,
            $account && !empty($account['stripe_customer_id']) ? (string) $account['stripe_customer_id'] : null,
            $expiresAt,
        ]);
        $orderId = (int) $db->lastInsertId();

        $itemInsert = $db->prepare(
            'INSERT INTO shop_order_items (order_id, product_id, variant_id, product_name, variant_name, sku, quantity, unit_price_cents, line_total_cents, currency, requires_shipping, fulfillment_type, fulfillment_provider, fulfillment_product_id, fulfillment_variant_id, variant_snapshot_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $reserveInsert = $db->prepare(
            'INSERT INTO shop_inventory_reservations (order_id, variant_id, quantity, status, expires_at) VALUES (?, ?, ?, "active", FROM_UNIXTIME(?))'
        );

        foreach ($items as $item) {
            $variantId = (int) ($item['id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $variant = shop_cart_load_variant($db, $variantId);
            if (!$variant) {
                throw new RuntimeException('One of the items in your cart is no longer available.');
            }

            $currentPrice = (int) ($variant['price_cents'] ?? 0);
            if ($currentPrice <= 0 || $currentPrice !== (int) ($item['price_cents'] ?? 0)) {
                throw new RuntimeException('One of the prices in your cart changed. Return to your cart and review the updated total.');
            }

            $meta = shop_cart_variant_storefront_meta($variant);
            $needsReservation =
                $meta['availability_mode'] !== 'preorder'
                && (int) ($variant['track_inventory'] ?? 0) === 1
                && (int) ($variant['allow_backorder'] ?? 0) !== 1;

            if ($needsReservation) {
                $lock = $db->prepare('SELECT inventory_quantity FROM shop_product_variants WHERE id = ? FOR UPDATE');
                $lock->execute([$variantId]);
                $onHand = max(0, (int) $lock->fetchColumn());
                $reserved = shop_checkout_active_reserved_quantity($db, $variantId, $orderId);
                if ($quantity > max(0, $onHand - $reserved)) {
                    throw new RuntimeException('An item in your cart no longer has enough stock for that quantity.');
                }
                $reserveInsert->execute([$orderId, $variantId, $quantity, $expiresAt]);
            }

            $snapshot = [
                'options' => shop_cart_variant_options($variant),
                'availability' => $meta['availability_mode'],
                'allow_backorder' => (int) ($variant['allow_backorder'] ?? 0),
                'track_inventory' => (int) ($variant['track_inventory'] ?? 0),
                'image_url' => (string) ($item['image_url'] ?? ''),
            ];

            $itemInsert->execute([
                $orderId,
                (int) ($variant['product_id'] ?? 0) ?: null,
                $variantId,
                (string) ($variant['product_name'] ?? 'Shop item'),
                trim((string) ($variant['name'] ?? '')) ?: null,
                trim((string) ($variant['sku'] ?? '')) ?: null,
                $quantity,
                $currentPrice,
                $currentPrice * $quantity,
                strtolower((string) ($variant['currency'] ?? 'usd')),
                (int) ($variant['requires_shipping'] ?? 0),
                trim((string) ($variant['fulfillment_type'] ?? '')) ?: null,
                trim((string) ($variant['fulfillment_provider'] ?? '')) ?: null,
                trim((string) ($variant['fulfillment_product_id'] ?? '')) ?: null,
                trim((string) ($variant['fulfillment_variant_id'] ?? '')) ?: null,
                json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }

        $db->commit();
        return ['id' => $orderId, 'order_number' => $orderNumber, 'currency' => $currency, 'subtotal_cents' => $subtotal];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function shop_checkout_stripe_line_items(array $items): array
{
    $lineItems = [];
    foreach ($items as $item) {
        $name = trim((string) ($item['product_name'] ?? 'Shop item'));
        $variantName = trim((string) ($item['name'] ?? ''));
        if ($variantName !== '' && strcasecmp($variantName, 'Standard') !== 0) {
            $name .= ' - ' . $variantName;
        }
        $lineItems[] = [
            'price_data' => [
                'currency' => strtolower((string) ($item['currency'] ?? 'usd')),
                'unit_amount' => (int) ($item['price_cents'] ?? 0),
                'product_data' => ['name' => $name],
            ],
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
        ];
    }
    return $lineItems;
}

function shop_checkout_create_stripe_session(PDO $db, array $order, array $items, ?array $account, string $returnUrl, int $expiresAt): object
{
    $settings = shop_checkout_settings();
    $sessionData = [
        'mode' => 'payment',
        'ui_mode' => 'embedded_page',
        'line_items' => shop_checkout_stripe_line_items($items),
        'client_reference_id' => (string) $order['order_number'],
        'metadata' => [
            'llama_checkout_type' => 'shop',
            'llama_order_id' => (string) $order['id'],
            'llama_order_number' => (string) $order['order_number'],
            'llama_user_id' => $account ? (string) $account['id'] : '',
        ],
        'return_url' => $returnUrl,
        'expires_at' => $expiresAt,
        'billing_address_collection' => 'auto',
    ];

    if ($settings['automatic_tax']) {
        $sessionData['automatic_tax'] = ['enabled' => true];
    }

    if (shop_checkout_requires_shipping($items)) {
        if (!$settings['shipping_countries']) {
            throw new RuntimeException('Shop shipping countries are not configured.');
        }
        if (!$settings['shipping_rate_ids']) {
            throw new RuntimeException('Shop shipping rates are not configured.');
        }

        $sessionData['shipping_address_collection'] = [
            'allowed_countries' => $settings['shipping_countries'],
        ];
        $sessionData['shipping_options'] = array_map(
            static fn(string $rateId): array => ['shipping_rate' => $rateId],
            $settings['shipping_rate_ids']
        );
    }

    if ($account && !empty($account['stripe_customer_id'])) {
        $sessionData['customer'] = (string) $account['stripe_customer_id'];
    } elseif ($account && !empty($account['email'])) {
        $sessionData['customer_email'] = (string) $account['email'];
    }

    $session = llama_stripe_client()->checkout->sessions->create($sessionData);
    $sessionId = trim((string) ($session->id ?? ''));
    $clientSecret = trim((string) ($session->client_secret ?? ''));
    if ($sessionId === '' || $clientSecret === '') {
        throw new RuntimeException('Stripe did not return a complete Embedded Checkout session.');
    }

    $stmt = $db->prepare('UPDATE shop_orders SET stripe_checkout_session_id = ? WHERE id = ?');
    $stmt->execute([$sessionId, (int) $order['id']]);
    return $session;
}

function shop_checkout_cancel_pending_order(PDO $db, int $orderId): void
{
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE shop_orders SET order_status = "cancelled", payment_status = CASE WHEN payment_status = "paid" THEN payment_status ELSE "cancelled" END, canceled_at = COALESCE(canceled_at, UTC_TIMESTAMP()) WHERE id = ? AND payment_status <> "paid"');
        $stmt->execute([$orderId]);
        $stmt = $db->prepare('UPDATE shop_inventory_reservations SET status = "released", released_at = COALESCE(released_at, UTC_TIMESTAMP()) WHERE order_id = ? AND status = "active"');
        $stmt->execute([$orderId]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function shop_checkout_order(PDO $db, int $orderId): ?array
{
    $stmt = $db->prepare('SELECT * FROM shop_orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function shop_checkout_order_by_session(PDO $db, string $sessionId): ?array
{
    $stmt = $db->prepare('SELECT * FROM shop_orders WHERE stripe_checkout_session_id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function shop_checkout_order_items(PDO $db, int $orderId): array
{
    $stmt = $db->prepare('SELECT * FROM shop_order_items WHERE order_id = ? ORDER BY id ASC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function shop_checkout_stripe_address(?object $address): ?string
{
    if (!$address) {
        return null;
    }
    $data = [];
    foreach (['line1','line2','city','state','postal_code','country'] as $field) {
        $value = trim((string) ($address->{$field} ?? ''));
        if ($value !== '') {
            $data[$field] = $value;
        }
    }
    return $data ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}

function shop_checkout_record_event(PDO $db, string $eventId, string $eventType, ?int $orderId): bool
{
    try {
        $stmt = $db->prepare('INSERT INTO shop_stripe_events (stripe_event_id, event_type, order_id, status) VALUES (?, ?, ?, "processing")');
        $stmt->execute([$eventId, $eventType, $orderId]);
        return true;
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '23000') {
            throw $exception;
        }

        $existing = $db->prepare('SELECT status FROM shop_stripe_events WHERE stripe_event_id = ? LIMIT 1');
        $existing->execute([$eventId]);
        $status = (string) ($existing->fetchColumn() ?: '');

        if ($status === 'failed') {
            $retry = $db->prepare('UPDATE shop_stripe_events SET status = "processing", error_message = NULL, processed_at = NULL WHERE stripe_event_id = ?');
            $retry->execute([$eventId]);
            return true;
        }

        return false;
    }
}

function shop_checkout_finish_event(PDO $db, string $eventId, string $status, ?string $error = null): void
{
    $stmt = $db->prepare('UPDATE shop_stripe_events SET status = ?, error_message = ?, processed_at = UTC_TIMESTAMP() WHERE stripe_event_id = ?');
    $stmt->execute([$status, $error, $eventId]);
}

function shop_checkout_commit_paid_session(PDO $db, object $session): void
{
    $orderId = (int) ($session->metadata->llama_order_id ?? 0);
    if ($orderId < 1) {
        throw new RuntimeException('Stripe Shop session is missing its Llama Scout order ID.');
    }

    $db->beginTransaction();
    try {
        $orderStmt = $db->prepare('SELECT * FROM shop_orders WHERE id = ? FOR UPDATE');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('The Shop order linked to this Stripe session does not exist.');
        }

        $paymentStatus = strtolower(trim((string) ($session->payment_status ?? '')));
        if ($paymentStatus !== 'paid') {
            $stmt = $db->prepare('UPDATE shop_orders SET payment_status = ? WHERE id = ?');
            $stmt->execute([$paymentStatus !== '' ? $paymentStatus : 'pending', $orderId]);
            $db->commit();
            return;
        }

        if (empty($order['inventory_committed_at'])) {
            $reservations = $db->prepare('SELECT * FROM shop_inventory_reservations WHERE order_id = ? AND status = "active" FOR UPDATE');
            $reservations->execute([$orderId]);
            foreach ($reservations->fetchAll(PDO::FETCH_ASSOC) ?: [] as $reservation) {
                $variantId = (int) $reservation['variant_id'];
                $quantity = (int) $reservation['quantity'];
                $variantStmt = $db->prepare('SELECT inventory_quantity, track_inventory, allow_backorder FROM shop_product_variants WHERE id = ? FOR UPDATE');
                $variantStmt->execute([$variantId]);
                $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);
                if (!$variant || (int) $variant['track_inventory'] !== 1 || (int) $variant['allow_backorder'] === 1) {
                    continue;
                }
                $onHand = max(0, (int) $variant['inventory_quantity']);
                if ($quantity > $onHand) {
                    throw new RuntimeException('Reserved inventory is no longer available for paid Shop order ' . (string) $order['order_number'] . '.');
                }
                $updateInventory = $db->prepare('UPDATE shop_product_variants SET inventory_quantity = inventory_quantity - ? WHERE id = ?');
                $updateInventory->execute([$quantity, $variantId]);
            }

            $consume = $db->prepare('UPDATE shop_inventory_reservations SET status = "consumed", consumed_at = UTC_TIMESTAMP() WHERE order_id = ? AND status = "active"');
            $consume->execute([$orderId]);
        }

        $customerDetails = $session->customer_details ?? null;
        $shippingDetails = $session->shipping_details ?? null;
        $shippingAddress = $shippingDetails->address ?? ($customerDetails->address ?? null);
        $billingAddress = $customerDetails->address ?? null;
        $customerEmail = trim((string) ($customerDetails->email ?? $session->customer_email ?? ''));
        $shippingName = trim((string) ($shippingDetails->name ?? $customerDetails->name ?? ''));
        $shippingPhone = trim((string) ($shippingDetails->phone ?? $customerDetails->phone ?? ''));
        $paymentIntent = is_string($session->payment_intent ?? null) ? $session->payment_intent : (string) ($session->payment_intent->id ?? '');
        $customerId = is_string($session->customer ?? null) ? $session->customer : (string) ($session->customer->id ?? '');
        $amountSubtotal = max(0, (int) ($session->amount_subtotal ?? $order['subtotal_cents']));
        $amountTotal = max(0, (int) ($session->amount_total ?? $order['total_cents']));
        $taxTotal = max(0, (int) ($session->total_details->amount_tax ?? 0));
        $discountTotal = max(0, (int) ($session->total_details->amount_discount ?? 0));
        $shippingTotal = max(0, $amountTotal - $amountSubtotal - $taxTotal + $discountTotal);

        $update = $db->prepare(
            'UPDATE shop_orders SET order_status = "paid", payment_status = "paid", subtotal_cents = ?, shipping_cents = ?, tax_cents = ?, discount_cents = ?, total_cents = ?, customer_email = ?, shipping_name = ?, shipping_phone = ?, shipping_address_json = ?, billing_address_json = ?, stripe_payment_intent_id = ?, stripe_customer_id = COALESCE(NULLIF(?, ""), stripe_customer_id), inventory_committed_at = COALESCE(inventory_committed_at, UTC_TIMESTAMP()), paid_at = COALESCE(paid_at, UTC_TIMESTAMP()), canceled_at = NULL WHERE id = ?'
        );
        $update->execute([
            $amountSubtotal,
            $shippingTotal,
            $taxTotal,
            $discountTotal,
            $amountTotal,
            $customerEmail !== '' ? $customerEmail : null,
            $shippingName !== '' ? $shippingName : null,
            $shippingPhone !== '' ? $shippingPhone : null,
            shop_checkout_stripe_address($shippingAddress),
            shop_checkout_stripe_address($billingAddress),
            $paymentIntent !== '' ? $paymentIntent : null,
            $customerId,
            $orderId,
        ]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function shop_checkout_mark_failed(PDO $db, int $orderId, string $status = 'failed'): void
{
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE shop_orders SET payment_status = ?, order_status = CASE WHEN order_status = "paid" THEN order_status ELSE "problem" END WHERE id = ?');
        $stmt->execute([$status, $orderId]);
        $stmt = $db->prepare('UPDATE shop_inventory_reservations SET status = "released", released_at = COALESCE(released_at, UTC_TIMESTAMP()) WHERE order_id = ? AND status = "active"');
        $stmt->execute([$orderId]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}
