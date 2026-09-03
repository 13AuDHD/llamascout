<?php

declare(strict_types=1);

function shop_customer_money(
    int $cents,
    string $currency = 'usd'
): string {
    $currency = strtolower(trim($currency));

    if ($currency === 'usd') {
        return '$' . number_format($cents / 100, 2);
    }

    return strtoupper($currency) . ' ' .
        number_format($cents / 100, 2);
}

function shop_customer_orders(
    PDO $db,
    int $userId,
    string $email
): array {
    $email = strtolower(trim($email));

    $stmt = $db->prepare(
        'SELECT
            o.*,
            COUNT(DISTINCT i.id) AS item_count,
            COALESCE(SUM(i.quantity), 0) AS unit_count,
            COUNT(DISTINCT f.id) AS fulfillment_count,
            MAX(
                CASE
                    WHEN f.status = "problem" THEN 1
                    ELSE 0
                END
            ) AS has_fulfillment_problem,
            MAX(f.shipped_at) AS latest_shipped_at,
            MAX(f.delivered_at) AS latest_delivered_at
         FROM shop_orders o
         LEFT JOIN shop_order_items i
            ON i.order_id = o.id
         LEFT JOIN shop_order_fulfillments f
            ON f.order_id = o.id
         WHERE (
                o.user_id = ?
                OR (
                    o.user_id IS NULL
                    AND ? <> ""
                    AND LOWER(COALESCE(o.customer_email, "")) = ?
                )
            )
           AND o.payment_status IN (
                "paid",
                "refunded"
           )
         GROUP BY o.id
         ORDER BY
            COALESCE(o.paid_at, o.created_at) DESC,
            o.id DESC'
    );

    $stmt->execute([
        $userId,
        $email,
        $email,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function shop_customer_order(
    PDO $db,
    int $orderId,
    int $userId,
    string $email
): ?array {
    $email = strtolower(trim($email));

    $stmt = $db->prepare(
        'SELECT *
         FROM shop_orders
         WHERE id = ?
           AND (
                user_id = ?
                OR (
                    user_id IS NULL
                    AND ? <> ""
                    AND LOWER(COALESCE(customer_email, "")) = ?
                )
           )
         LIMIT 1'
    );

    $stmt->execute([
        $orderId,
        $userId,
        $email,
        $email,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function shop_customer_order_items(
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

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $snapshot = [];

        if (!empty($row['variant_snapshot_json'])) {
            $decoded = json_decode(
                (string) $row['variant_snapshot_json'],
                true
            );

            if (is_array($decoded)) {
                $snapshot = $decoded;
            }
        }

        $row['image_url'] =
            trim((string) ($snapshot['image_url'] ?? ''));

        $row['snapshot_options'] =
            is_array($snapshot['options'] ?? null)
                ? $snapshot['options']
                : [];
    }

    unset($row);

    return $rows;
}

function shop_customer_fulfillments(
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

function shop_customer_status_label(
    string $status
): string {
    $status = strtolower(trim($status));

    return match ($status) {
        'paid' => 'Order received',
        'processing' => 'Preparing your order',
        'submitted' => 'Sent to fulfillment',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled',
        'canceled' => 'Cancelled',
        'refunded' => 'Refunded',
        'problem' => 'Needs attention',
        default => ucwords(
            str_replace(
                ['_', '-'],
                ' ',
                $status !== '' ? $status : 'Pending'
            )
        ),
    };
}

function shop_customer_tracking_label(
    string $carrier
): string {
    $carrier = strtolower(trim($carrier));

    return match ($carrier) {
        'usps' => 'USPS',
        'ups' => 'UPS',
        'fedex' => 'FedEx',
        'dhl' => 'DHL Express',
        'dhl_ecommerce' => 'DHL eCommerce',
        'ontrac' => 'OnTrac',
        'other' => 'Carrier',
        default => 'Carrier',
    };
}

function shop_customer_address_lines(
    ?string $json
): array {
    $json = trim((string) $json);

    if ($json === '') {
        return [];
    }

    $address = json_decode($json, true);

    if (!is_array($address)) {
        return [];
    }

    $lines = [];

    foreach (['line1', 'street1'] as $key) {
        if (!empty($address[$key])) {
            $lines[] = trim((string) $address[$key]);
            break;
        }
    }

    foreach (['line2', 'street2'] as $key) {
        if (!empty($address[$key])) {
            $lines[] = trim((string) $address[$key]);
            break;
        }
    }

    $cityStateZip = trim(
        implode(
            ' ',
            array_filter([
                trim((string) ($address['city'] ?? '')),
                trim((string) ($address['state'] ?? '')),
                trim((string) ($address['postal_code'] ?? $address['zip'] ?? '')),
            ])
        )
    );

    if ($cityStateZip !== '') {
        $lines[] = $cityStateZip;
    }

    $country = trim(
        (string) ($address['country'] ?? '')
    );

    if ($country !== '') {
        $lines[] = $country;
    }

    return $lines;
}
