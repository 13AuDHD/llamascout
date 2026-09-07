<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT SHOP SERVICE
   ========================================================= */


/* =========================================================
   PRODUCT STATUS
   ========================================================= */

const LLAMA_SHOP_PRODUCT_DRAFT = 'draft';
const LLAMA_SHOP_PRODUCT_ACTIVE = 'active';
const LLAMA_SHOP_PRODUCT_ARCHIVED = 'archived';


/* =========================================================
   FULFILLMENT TYPES
   ========================================================= */

const LLAMA_SHOP_FULFILLMENT_MANUAL = 'manual';
const LLAMA_SHOP_FULFILLMENT_PRINTFUL = 'printful';
const LLAMA_SHOP_FULFILLMENT_PRINTIFY = 'printify';
const LLAMA_SHOP_FULFILLMENT_EXTERNAL = 'external';


/* =========================================================
   ORDER STATUS
   ========================================================= */

const LLAMA_SHOP_ORDER_PENDING = 'pending';
const LLAMA_SHOP_ORDER_PAID = 'paid';
const LLAMA_SHOP_ORDER_PROCESSING = 'processing';
const LLAMA_SHOP_ORDER_PARTIAL = 'partially_fulfilled';
const LLAMA_SHOP_ORDER_FULFILLED = 'fulfilled';
const LLAMA_SHOP_ORDER_CANCELED = 'canceled';
const LLAMA_SHOP_ORDER_REFUNDED = 'refunded';


/* =========================================================
   PAYMENT STATUS
   ========================================================= */

const LLAMA_SHOP_PAYMENT_PENDING = 'pending';
const LLAMA_SHOP_PAYMENT_PAID = 'paid';
const LLAMA_SHOP_PAYMENT_FAILED = 'failed';
const LLAMA_SHOP_PAYMENT_CANCELED = 'canceled';
const LLAMA_SHOP_PAYMENT_PARTIAL_REFUND = 'partially_refunded';
const LLAMA_SHOP_PAYMENT_REFUNDED = 'refunded';


/* =========================================================
   FULFILLMENT STATUS
   ========================================================= */

const LLAMA_SHOP_FULFILLMENT_PENDING = 'pending';
const LLAMA_SHOP_FULFILLMENT_SUBMITTED = 'submitted';
const LLAMA_SHOP_FULFILLMENT_PROCESSING = 'processing';
const LLAMA_SHOP_FULFILLMENT_SHIPPED = 'shipped';
const LLAMA_SHOP_FULFILLMENT_DELIVERED = 'delivered';
const LLAMA_SHOP_FULFILLMENT_CANCELED = 'canceled';
const LLAMA_SHOP_FULFILLMENT_ERROR = 'error';


/* =========================================================
   SCHEMA HELPERS
   ========================================================= */

function llama_shop_table_exists(
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


function llama_shop_column_exists(
    PDO $db,
    string $table,
    string $column
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        $table,
        $column,
    ]);

    return (bool) $stmt->fetchColumn();
}


/* =========================================================
   STORAGE VALIDATION

   Schema creation and migrations must be run explicitly.
   Normal web requests only validate that required Shop
   storage already exists.
   ========================================================= */

function llama_ensure_shop_storage(
    PDO $db
): void {
    $requiredTables = [
        'shop_products',
        'shop_product_variants',
        'shop_orders',
        'shop_order_items',
        'shop_order_fulfillments',
        'shop_order_fulfillment_items',
        'shop_stripe_events',
    ];

    foreach ($requiredTables as $table) {
        if (!llama_shop_table_exists($db, $table)) {
            throw new RuntimeException(
                'Shop storage is not initialized. Missing table: ' . $table
            );
        }
    }

    $requiredOrderColumns = [
        'shipping_rate_key',
        'shipping_source',
        'shipping_carrier',
        'shipping_service',
        'shipping_quote_zip',
        'shipping_quote_data',
        'shipping_needs_review',
        'shipping_review_reason',
    ];

    foreach ($requiredOrderColumns as $column) {
        if (!llama_shop_column_exists($db, 'shop_orders', $column)) {
            throw new RuntimeException(
                'Shop storage is not initialized. Missing shop_orders column: '
                . $column
            );
        }
    }
}


/* =========================================================
   ORDER NUMBER
   ========================================================= */

function llama_shop_order_number(
    PDO $db
): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $number =
            'LS-'
            . gmdate('Ymd')
            . '-'
            . strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

        $check = $db->prepare(
            'SELECT id
             FROM shop_orders
             WHERE order_number = ?
             LIMIT 1'
        );

        $check->execute([$number]);

        if (!$check->fetchColumn()) {
            return $number;
        }
    }

    throw new RuntimeException(
        'Could not generate a unique order number.'
    );
}


/* =========================================================
   OPTION SNAPSHOT
   ========================================================= */

function llama_shop_variant_options(
    array $variant
): array {
    $options = [];

    foreach (
        [
            ['option_one_name', 'option_one_value'],
            ['option_two_name', 'option_two_value'],
            ['option_three_name', 'option_three_value'],
        ]
        as [$nameKey, $valueKey]
    ) {
        $name = trim(
            (string) (
                $variant[$nameKey]
                ?? ''
            )
        );

        $value = trim(
            (string) (
                $variant[$valueKey]
                ?? ''
            )
        );

        if ($name !== '' && $value !== '') {
            $options[] = [
                'name' => $name,
                'value' => $value,
            ];
        }
    }

    return $options;
}


/* =========================================================
   CREATE PENDING ORDER FROM CART

   Inventory is reserved before Stripe payment. Successful
   payment commits the reservation. Expiration or cancellation
   releases it.
   ========================================================= */

function llama_shop_create_pending_order(
    PDO $db,
    array $cart,
    ?int $userId = null
): array {
    $normalizedCart = [];

    foreach ($cart as $variantId => $quantity) {
        $variantId = (int) $variantId;
        $quantity = (int) $quantity;

        if ($variantId > 0 && $quantity > 0) {
            $normalizedCart[$variantId] = min(99, $quantity);
        }
    }

    if (!$normalizedCart) {
        throw new RuntimeException(
            'Your cart is empty.'
        );
    }

    $variantIds = array_keys($normalizedCart);

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($variantIds),
            '?'
        )
    );

    $db->beginTransaction();

    try {
        $variantStmt = $db->prepare(
            'SELECT
                v.*,
                p.slug AS product_slug,
                p.name AS product_name,
                p.primary_image_url,
                p.requires_shipping,
                p.status AS product_status
             FROM shop_product_variants v
             INNER JOIN shop_products p
               ON p.id = v.product_id
             WHERE v.id IN (' . $placeholders . ')
             FOR UPDATE'
        );

        $variantStmt->execute($variantIds);

        $rows = $variantStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $rowsById = [];

        foreach ($rows as $row) {
            $rowsById[(int) $row['id']] = $row;
        }

        if (
            count($rowsById)
            !== count($variantIds)
        ) {
            throw new RuntimeException(
                'One or more cart items no longer exist.'
            );
        }

        $subtotal = 0;
        $currency = '';

        foreach ($variantIds as $variantId) {
            $row = $rowsById[$variantId];
            $quantity = $normalizedCart[$variantId];

            if (
                (string) $row['product_status']
                    !== LLAMA_SHOP_PRODUCT_ACTIVE
                || !(bool) $row['is_active']
            ) {
                throw new RuntimeException(
                    'One or more cart items are no longer available.'
                );
            }

            $rowCurrency = strtolower(
                trim(
                    (string) $row['currency']
                )
            );

            if ($currency === '') {
                $currency = $rowCurrency;
            } elseif ($currency !== $rowCurrency) {
                throw new RuntimeException(
                    'Items using different currencies cannot be checked out together.'
                );
            }

            if (
                (bool) $row['track_inventory']
                && !(bool) $row['allow_backorder']
                && (int) $row['inventory_quantity'] < $quantity
            ) {
                throw new RuntimeException(
                    $row['product_name']
                    . ' does not have enough inventory for that quantity.'
                );
            }

            $subtotal +=
                (int) $row['price_cents']
                * $quantity;
        }

        $orderNumber = llama_shop_order_number($db);

        $insertOrder = $db->prepare(
            'INSERT INTO shop_orders
                (
                    order_number,
                    user_id,
                    order_status,
                    payment_status,
                    currency,
                    subtotal_cents,
                    total_cents
                )
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $insertOrder->execute([
            $orderNumber,
            $userId,
            LLAMA_SHOP_ORDER_PENDING,
            LLAMA_SHOP_PAYMENT_PENDING,
            $currency !== '' ? $currency : 'usd',
            $subtotal,
            $subtotal,
        ]);

        $orderId = (int) $db->lastInsertId();

        $insertItem = $db->prepare(
            'INSERT INTO shop_order_items
                (
                    order_id,
                    product_id,
                    variant_id,
                    product_name,
                    product_slug,
                    variant_name,
                    sku,
                    option_data,
                    image_url,
                    unit_price_cents,
                    quantity,
                    line_total_cents,
                    currency,
                    requires_shipping,
                    fulfillment_type,
                    fulfillment_provider,
                    fulfillment_product_id,
                    fulfillment_variant_id,
                    fulfillment_data,
                    inventory_reserved_quantity
                )
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $reserveInventory = $db->prepare(
            'UPDATE shop_product_variants
             SET inventory_quantity =
                 inventory_quantity - ?
             WHERE id = ?
             LIMIT 1'
        );

        foreach ($variantIds as $variantId) {
            $row = $rowsById[$variantId];
            $quantity = $normalizedCart[$variantId];

            $options = llama_shop_variant_options($row);

            $reservedQuantity =
                (bool) $row['track_inventory']
                    ? $quantity
                    : 0;

            $fulfillmentData = null;

            if ($row['fulfillment_data'] !== null) {
                $fulfillmentData =
                    is_string($row['fulfillment_data'])
                        ? $row['fulfillment_data']
                        : json_encode(
                            $row['fulfillment_data'],
                            JSON_UNESCAPED_SLASHES
                        );
            }

            $insertItem->execute([
                $orderId,
                (int) $row['product_id'],
                $variantId,
                $row['product_name'],
                $row['product_slug'],
                $row['name'],
                $row['sku'],
                $options
                    ? json_encode(
                        $options,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    : null,
                $row['primary_image_url'] ?: null,
                (int) $row['price_cents'],
                $quantity,
                (int) $row['price_cents'] * $quantity,
                $row['currency'],
                (bool) $row['requires_shipping'] ? 1 : 0,
                $row['fulfillment_type'],
                $row['fulfillment_provider'] ?: null,
                $row['fulfillment_product_id'] ?: null,
                $row['fulfillment_variant_id'] ?: null,
                $fulfillmentData,
                $reservedQuantity,
            ]);

            if ($reservedQuantity > 0) {
                $reserveInventory->execute([
                    $reservedQuantity,
                    $variantId,
                ]);
            }
        }

        $db->commit();

        return
            llama_shop_order_by_id(
                $db,
                $orderId
            )
            ?? throw new RuntimeException(
                'Order was created but could not be reloaded.'
            );
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


/* =========================================================
   ORDER LOOKUP
   ========================================================= */

function llama_shop_order_by_id(
    PDO $db,
    int $orderId
): ?array {
    if ($orderId < 1) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM shop_orders
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$orderId]);

    $order = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $order ?: null;
}


function llama_shop_order_by_checkout_session(
    PDO $db,
    string $sessionId
): ?array {
    $sessionId = trim($sessionId);

    if ($sessionId === '') {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT *
         FROM shop_orders
         WHERE stripe_checkout_session_id = ?
         LIMIT 1'
    );

    $stmt->execute([$sessionId]);

    $order = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $order ?: null;
}


/* =========================================================
   ORDER ITEMS
   ========================================================= */

function llama_shop_order_items(
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

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/* =========================================================
   ATTACH STRIPE CHECKOUT SESSION
   ========================================================= */

function llama_shop_attach_checkout_session(
    PDO $db,
    int $orderId,
    string $sessionId,
    ?int $expiresTimestamp = null
): void {
    $expiresAt =
        $expiresTimestamp !== null
            ? gmdate(
                'Y-m-d H:i:s',
                $expiresTimestamp
            )
            : null;

    $stmt = $db->prepare(
        'UPDATE shop_orders
         SET
            stripe_checkout_session_id = ?,
            checkout_expires_at = ?
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $sessionId,
        $expiresAt,
        $orderId,
    ]);
}


/* =========================================================
   COMMIT RESERVED INVENTORY
   ========================================================= */

function llama_shop_commit_order_inventory(
    PDO $db,
    int $orderId
): void {
    $stmt = $db->prepare(
        'UPDATE shop_order_items
         SET inventory_committed = 1
         WHERE order_id = ?
           AND inventory_reserved_quantity > 0
           AND inventory_released = 0'
    );

    $stmt->execute([$orderId]);
}


/* =========================================================
   RELEASE RESERVED INVENTORY
   ========================================================= */

function llama_shop_release_order_inventory(
    PDO $db,
    int $orderId
): void {
    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT
                id,
                variant_id,
                inventory_reserved_quantity
             FROM shop_order_items
             WHERE order_id = ?
               AND inventory_reserved_quantity > 0
               AND inventory_committed = 0
               AND inventory_released = 0
             FOR UPDATE'
        );

        $stmt->execute([$orderId]);

        $items = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $restore = $db->prepare(
            'UPDATE shop_product_variants
             SET inventory_quantity =
                 inventory_quantity + ?
             WHERE id = ?
             LIMIT 1'
        );

        $markReleased = $db->prepare(
            'UPDATE shop_order_items
             SET inventory_released = 1
             WHERE id = ?
             LIMIT 1'
        );

        foreach ($items as $item) {
            $variantId = (int) (
                $item['variant_id']
                ?? 0
            );

            $quantity =
                (int) $item['inventory_reserved_quantity'];

            if ($variantId > 0 && $quantity > 0) {
                $restore->execute([
                    $quantity,
                    $variantId,
                ]);
            }

            $markReleased->execute([
                (int) $item['id'],
            ]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


/* =========================================================
   CANCEL PENDING ORDER
   ========================================================= */

function llama_shop_cancel_pending_order(
    PDO $db,
    int $orderId,
    string $paymentStatus =
        LLAMA_SHOP_PAYMENT_CANCELED
): void {
    $order = llama_shop_order_by_id(
        $db,
        $orderId
    );

    if (!$order) {
        return;
    }

    if (
        (string) $order['payment_status']
        === LLAMA_SHOP_PAYMENT_PAID
    ) {
        return;
    }

    llama_shop_release_order_inventory(
        $db,
        $orderId
    );

    $stmt = $db->prepare(
        'UPDATE shop_orders
         SET
            order_status = ?,
            payment_status = ?,
            canceled_at = CURRENT_TIMESTAMP
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        LLAMA_SHOP_ORDER_CANCELED,
        $paymentStatus,
        $orderId,
    ]);
}
