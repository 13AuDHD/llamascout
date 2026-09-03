<?php

declare(strict_types=1);

/*
 * Llama Scout Fulfillment package metadata.
 *
 * This file intentionally contains no CREATE TABLE or ALTER TABLE
 * statements. Schema changes are one-time phpMyAdmin migrations.
 */

function admin_fulfillment_package_types(): array
{
    return [
        'poly_mailer' => 'Poly mailer',
        'padded_mailer' => 'Padded mailer',
        'box' => 'Box',
        'envelope' => 'Envelope',
        'tube' => 'Tube',
        'other' => 'Other package',
    ];
}

function admin_fulfillment_package(
    PDO $db,
    int $fulfillmentId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_fulfillment_packages
         WHERE fulfillment_id = ?
         LIMIT 1'
    );

    $stmt->execute([$fulfillmentId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_fulfillment_decimal(
    mixed $value,
    string $label,
    bool $required = false
): ?float {
    $raw = trim((string) $value);

    if ($raw === '') {
        if ($required) {
            throw new InvalidArgumentException(
                $label . ' is required.'
            );
        }

        return null;
    }

    if (!is_numeric($raw)) {
        throw new InvalidArgumentException(
            $label . ' must be a number.'
        );
    }

    $number = (float) $raw;

    if ($number <= 0) {
        throw new InvalidArgumentException(
            $label . ' must be greater than zero.'
        );
    }

    if ($number > 9999) {
        throw new InvalidArgumentException(
            $label . ' is too large.'
        );
    }

    return round($number, 2);
}

function admin_fulfillment_save_package(
    PDO $db,
    int $actorUserId,
    int $orderId,
    int $fulfillmentId,
    array $data
): void {
    $fulfillmentStmt = $db->prepare(
        'SELECT
            f.*,
            o.order_number,
            o.user_id
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE f.id = ?
           AND f.order_id = ?
         LIMIT 1'
    );

    $fulfillmentStmt->execute([
        $fulfillmentId,
        $orderId,
    ]);

    $fulfillment =
        $fulfillmentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$fulfillment) {
        throw new InvalidArgumentException(
            'Fulfillment not found.'
        );
    }

    $provider = admin_shop_normalize_provider(
        (string) ($fulfillment['fulfillment_provider'] ?? '')
    );

    if ($provider === '') {
        $provider = 'llama_scout';
    }

    if ($provider !== 'llama_scout') {
        throw new InvalidArgumentException(
            'Package details are managed here only for Llama Scout Fulfillment orders.'
        );
    }

    $packageType = trim(
        (string) ($data['package_type'] ?? '')
    );

    $packageTypes =
        admin_fulfillment_package_types();

    if (!array_key_exists($packageType, $packageTypes)) {
        throw new InvalidArgumentException(
            'Choose a valid package type.'
        );
    }

    $weightOz = admin_fulfillment_decimal(
        $data['weight_oz'] ?? '',
        'Package weight',
        true
    );

    $lengthIn = admin_fulfillment_decimal(
        $data['length_in'] ?? '',
        'Package length'
    );

    $widthIn = admin_fulfillment_decimal(
        $data['width_in'] ?? '',
        'Package width'
    );

    $heightIn = admin_fulfillment_decimal(
        $data['height_in'] ?? '',
        'Package height'
    );

    $dimensionValues = [
        $lengthIn,
        $widthIn,
        $heightIn,
    ];

    $dimensionCount = count(
        array_filter(
            $dimensionValues,
            static fn(?float $value): bool =>
                $value !== null
        )
    );

    if ($dimensionCount !== 0 && $dimensionCount !== 3) {
        throw new InvalidArgumentException(
            'Enter all three package dimensions or leave all three blank.'
        );
    }

    $notes = trim(
        (string) ($data['package_notes'] ?? '')
    );

    if (mb_strlen($notes) > 2000) {
        throw new InvalidArgumentException(
            'Package notes must be 2,000 characters or fewer.'
        );
    }

    $stmt = $db->prepare(
        'INSERT INTO shop_fulfillment_packages (
            fulfillment_id,
            package_type,
            weight_oz,
            length_in,
            width_in,
            height_in,
            internal_notes,
            created_at,
            updated_at
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            package_type = VALUES(package_type),
            weight_oz = VALUES(weight_oz),
            length_in = VALUES(length_in),
            width_in = VALUES(width_in),
            height_in = VALUES(height_in),
            internal_notes = VALUES(internal_notes),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $fulfillmentId,
        $packageType,
        $weightOz,
        $lengthIn,
        $widthIn,
        $heightIn,
        $notes !== '' ? $notes : null,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        $fulfillment['user_id']
            ? (int) $fulfillment['user_id']
            : null,
        'shop.fulfillment_package_saved',
        'Saved package details for fulfillment #' .
            $fulfillmentId .
            ' on order ' .
            (string) $fulfillment['order_number'] .
            '.',
        [
            'order_id' => $orderId,
            'fulfillment_id' => $fulfillmentId,
            'package_type' => $packageType,
            'weight_oz' => $weightOz,
        ]
    );
}


function admin_fulfillment_sync_order_status(
    PDO $db,
    int $orderId
): string {
    $orderStmt = $db->prepare(
        'SELECT
            order_status,
            payment_status
         FROM shop_orders
         WHERE id = ?
         LIMIT 1'
    );

    $orderStmt->execute([$orderId]);

    $order =
        $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new InvalidArgumentException(
            'Order not found.'
        );
    }

    $current =
        strtolower(
            trim(
                (string) (
                    $order['order_status']
                    ?? ''
                )
            )
        );

    /*
     * Manual terminal states win. A fulfillment update must never
     * accidentally reopen a cancelled or refunded order.
     */
    if (
        in_array(
            $current,
            [
                'cancelled',
                'canceled',
                'refunded',
            ],
            true
        )
    ) {
        return $current;
    }

    $payment =
        strtolower(
            trim(
                (string) (
                    $order['payment_status']
                    ?? ''
                )
            )
        );

    if ($payment !== 'paid') {
        return $current;
    }

    $stmt = $db->prepare(
        'SELECT status
         FROM shop_order_fulfillments
         WHERE order_id = ?
         ORDER BY id ASC'
    );

    $stmt->execute([$orderId]);

    $statuses =
        array_values(
            array_filter(
                array_map(
                    static fn(mixed $value): string =>
                        strtolower(
                            trim(
                                (string) $value
                            )
                        ),
                    $stmt->fetchAll(
                        PDO::FETCH_COLUMN
                    ) ?: []
                ),
                static fn(string $value): bool =>
                    $value !== ''
            )
        );

    if (!$statuses) {
        $target = 'paid';
    } elseif (
        count(
            array_filter(
                $statuses,
                static fn(string $status): bool =>
                    $status === 'delivered'
            )
        ) === count($statuses)
    ) {
        $target = 'delivered';
    } elseif (
        count(
            array_filter(
                $statuses,
                static fn(string $status): bool =>
                    in_array(
                        $status,
                        ['shipped', 'delivered'],
                        true
                    )
            )
        ) === count($statuses)
    ) {
        $target = 'shipped';
    } elseif (
        in_array(
            'problem',
            $statuses,
            true
        )
    ) {
        $target = 'problem';
    } elseif (
        count(
            array_filter(
                $statuses,
                static fn(string $status): bool =>
                    $status === 'cancelled'
            )
        ) === count($statuses)
    ) {
        $target = 'cancelled';
    } elseif (
        in_array(
            'submitted',
            $statuses,
            true
        )
    ) {
        $target = 'submitted';
    } elseif (
        in_array(
            'processing',
            $statuses,
            true
        )
    ) {
        $target = 'processing';
    } else {
        $target = 'paid';
    }

    if ($target === $current) {
        return $target;
    }

    $update = $db->prepare(
        'UPDATE shop_orders
         SET
            order_status = ?,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $update->execute([
        $target,
        $orderId,
    ]);

    return $target;
}

function admin_fulfillment_validate_status_update(
    PDO $db,
    int $orderId,
    int $fulfillmentId,
    array $data
): void {
    $status =
        strtolower(
            trim(
                (string) (
                    $data['status']
                    ?? ''
                )
            )
        );

    if (
        !in_array(
            $status,
            [
                'pending',
                'processing',
                'submitted',
                'shipped',
                'delivered',
                'problem',
                'cancelled',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid fulfillment status.'
        );
    }

    $stmt = $db->prepare(
        'SELECT
            id,
            tracking_number,
            shipped_at
         FROM shop_order_fulfillments
         WHERE id = ?
           AND order_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $fulfillmentId,
        $orderId,
    ]);

    $fulfillment =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fulfillment) {
        throw new InvalidArgumentException(
            'Fulfillment not found.'
        );
    }

    $submittedTracking =
        trim(
            (string) (
                $data['tracking_number']
                ?? ''
            )
        );

    $existingTracking =
        trim(
            (string) (
                $fulfillment['tracking_number']
                ?? ''
            )
        );

    if (
        $status === 'shipped'
        && $submittedTracking === ''
        && $existingTracking === ''
    ) {
        throw new InvalidArgumentException(
            'Add a tracking number before marking a shipped order as shipped.'
        );
    }

    if (
        $status === 'delivered'
        && trim(
            (string) (
                $fulfillment['shipped_at']
                ?? ''
            )
        ) === ''
    ) {
        throw new InvalidArgumentException(
            'Mark the fulfillment shipped before marking it delivered.'
        );
    }
}

function admin_fulfillment_format_timestamp(
    mixed $value
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return 'Not yet';
    }

    return $value;
}


function admin_fulfillment_shipping_destination(
    array $order
): array {
    $address = [];

    if (
        !empty(
            $order['shipping_address_json']
        )
    ) {
        $decoded =
            json_decode(
                (string) $order['shipping_address_json'],
                true
            );

        if (is_array($decoded)) {
            $address = $decoded;
        }
    }

    $address['name'] =
        trim(
            (string) (
                $order['shipping_name']
                ?? ''
            )
        );

    $address['phone'] =
        trim(
            (string) (
                $order['shipping_phone']
                ?? ''
            )
        );

    $address['email'] =
        trim(
            (string) (
                $order['customer_email']
                ?? ''
            )
        );

    return $address;
}

function admin_fulfillment_rate_rows(
    PDO $db,
    int $fulfillmentId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_fulfillment_rates
         WHERE fulfillment_id = ?
         ORDER BY
            rate_cents ASC,
            carrier ASC,
            service ASC'
    );

    $stmt->execute([
        $fulfillmentId,
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

function admin_fulfillment_label(
    PDO $db,
    int $fulfillmentId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_fulfillment_labels
         WHERE fulfillment_id = ?
           AND voided_at IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );

    $stmt->execute([
        $fulfillmentId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}

function admin_fulfillment_quote_rates(
    PDO $db,
    int $actorUserId,
    int $orderId,
    int $fulfillmentId
): int {
    if (!llama_shipping_easypost_configured()) {
        throw new InvalidArgumentException(
            'EasyPost is not configured yet.'
        );
    }

    $fulfillmentStmt =
        $db->prepare(
            'SELECT
                f.*,
                o.order_number,
                o.user_id,
                o.shipping_name,
                o.shipping_phone,
                o.customer_email,
                o.shipping_address_json
             FROM shop_order_fulfillments f
             INNER JOIN shop_orders o
                ON o.id = f.order_id
             WHERE f.id = ?
               AND f.order_id = ?
             LIMIT 1'
        );

    $fulfillmentStmt->execute([
        $fulfillmentId,
        $orderId,
    ]);

    $fulfillment =
        $fulfillmentStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$fulfillment) {
        throw new InvalidArgumentException(
            'Fulfillment not found.'
        );
    }

    $provider =
        admin_shop_normalize_provider(
            (string) (
                $fulfillment['fulfillment_provider']
                ?? ''
            )
        );

    if ($provider === '') {
        $provider = 'llama_scout';
    }

    if ($provider !== 'llama_scout') {
        throw new InvalidArgumentException(
            'EasyPost rates apply only to Llama Scout Fulfillment orders.'
        );
    }

    if (
        admin_fulfillment_label(
            $db,
            $fulfillmentId
        )
    ) {
        throw new InvalidArgumentException(
            'A shipping label has already been purchased for this fulfillment.'
        );
    }

    $package =
        admin_fulfillment_package(
            $db,
            $fulfillmentId
        );

    if (!$package) {
        throw new InvalidArgumentException(
            'Save the package details before requesting shipping rates.'
        );
    }

    $weight =
        (float) (
            $package['weight_oz']
            ?? 0
        );

    if ($weight <= 0) {
        throw new InvalidArgumentException(
            'Package weight is required before requesting shipping rates.'
        );
    }

    $parcel = [
        'weight' => number_format(
            $weight,
            2,
            '.',
            ''
        ),
    ];

    $length =
        (float) (
            $package['length_in']
            ?? 0
        );

    $width =
        (float) (
            $package['width_in']
            ?? 0
        );

    $height =
        (float) (
            $package['height_in']
            ?? 0
        );

    if (
        $length > 0
        && $width > 0
        && $height > 0
    ) {
        $parcel['length'] =
            number_format(
                $length,
                2,
                '.',
                ''
            );

        $parcel['width'] =
            number_format(
                $width,
                2,
                '.',
                ''
            );

        $parcel['height'] =
            number_format(
                $height,
                2,
                '.',
                ''
            );
    }

    $to =
        llama_shipping_address_payload(
            admin_fulfillment_shipping_destination(
                $fulfillment
            ),
            (string) (
                $fulfillment['shipping_name']
                ?? ''
            )
        );

    $from =
        llama_shipping_address_payload(
            llama_shipping_from_address(),
            'Llama Scout Fulfillment'
        );

    $shipment =
        llama_shipping_easypost_request(
            'POST',
            'shipments',
            [
                'shipment' => [
                    'to_address' => $to,
                    'from_address' => $from,
                    'parcel' => $parcel,
                    'reference' =>
                        (string) $fulfillment['order_number'],
                ],
            ]
        );

    $shipmentId =
        trim(
            (string) (
                $shipment['id']
                ?? ''
            )
        );

    if ($shipmentId === '') {
        throw new RuntimeException(
            'EasyPost did not return a shipment ID.'
        );
    }

    $rates =
        is_array(
            $shipment['rates']
            ?? null
        )
            ? $shipment['rates']
            : [];

    if (!$rates) {
        throw new RuntimeException(
            'No shipping rates were returned for this package.'
        );
    }

    $db->beginTransaction();

    try {
        $delete =
            $db->prepare(
                'DELETE FROM shop_fulfillment_rates
                 WHERE fulfillment_id = ?'
            );

        $delete->execute([
            $fulfillmentId,
        ]);

        $insert =
            $db->prepare(
                'INSERT INTO shop_fulfillment_rates (
                    fulfillment_id,
                    provider,
                    external_shipment_id,
                    external_rate_id,
                    carrier,
                    service,
                    rate_cents,
                    currency,
                    delivery_days,
                    delivery_date,
                    created_at
                 ) VALUES (
                    ?, "easypost", ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP()
                 )'
            );

        $count = 0;

        foreach ($rates as $rate) {
            if (!is_array($rate)) {
                continue;
            }

            $rateId =
                trim(
                    (string) (
                        $rate['id']
                        ?? ''
                    )
                );

            $carrier =
                trim(
                    (string) (
                        $rate['carrier']
                        ?? ''
                    )
                );

            $service =
                trim(
                    (string) (
                        $rate['service']
                        ?? ''
                    )
                );

            $rateAmount =
                trim(
                    (string) (
                        $rate['rate']
                        ?? ''
                    )
                );

            if (
                $rateId === ''
                || $carrier === ''
                || $service === ''
                || $rateAmount === ''
                || !is_numeric($rateAmount)
            ) {
                continue;
            }

            $rateCents =
                (int) round(
                    ((float) $rateAmount)
                    * 100
                );

            if ($rateCents <= 0) {
                continue;
            }

            $insert->execute([
                $fulfillmentId,
                $shipmentId,
                $rateId,
                $carrier,
                $service,
                $rateCents,
                strtoupper(
                    trim(
                        (string) (
                            $rate['currency']
                            ?? 'USD'
                        )
                    )
                ),
                isset($rate['delivery_days'])
                    && is_numeric(
                        $rate['delivery_days']
                    )
                        ? (int) $rate['delivery_days']
                        : null,
                trim(
                    (string) (
                        $rate['delivery_date']
                        ?? ''
                    )
                ) ?: null,
            ]);

            $count++;
        }

        if ($count < 1) {
            throw new RuntimeException(
                'No usable shipping rates were returned.'
            );
        }

        admin_users_audit(
            $db,
            $actorUserId,
            $fulfillment['user_id']
                ? (int) $fulfillment['user_id']
                : null,
            'shop.fulfillment_rates_quoted',
            'Requested shipping rates for fulfillment #' .
                $fulfillmentId .
                ' on order ' .
                (string) $fulfillment['order_number'] .
                '.',
            [
                'order_id' => $orderId,
                'fulfillment_id' => $fulfillmentId,
                'rate_count' => $count,
                'shipment_id' => $shipmentId,
            ]
        );

        $db->commit();

        return $count;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function admin_fulfillment_buy_label(
    PDO $db,
    int $actorUserId,
    int $orderId,
    int $fulfillmentId,
    int $rateRowId
): array {
    if (!llama_shipping_easypost_configured()) {
        throw new InvalidArgumentException(
            'EasyPost is not configured yet.'
        );
    }

    if (
        admin_fulfillment_label(
            $db,
            $fulfillmentId
        )
    ) {
        throw new InvalidArgumentException(
            'A shipping label has already been purchased for this fulfillment.'
        );
    }

    $stmt =
        $db->prepare(
            'SELECT
                r.*,
                f.fulfillment_provider,
                o.order_number,
                o.user_id
             FROM shop_fulfillment_rates r
             INNER JOIN shop_order_fulfillments f
                ON f.id = r.fulfillment_id
             INNER JOIN shop_orders o
                ON o.id = f.order_id
             WHERE r.id = ?
               AND r.fulfillment_id = ?
               AND f.order_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $rateRowId,
        $fulfillmentId,
        $orderId,
    ]);

    $rate =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$rate) {
        throw new InvalidArgumentException(
            'The selected shipping rate is no longer available.'
        );
    }

    if (
        (string) $rate['provider']
        !== 'easypost'
    ) {
        throw new InvalidArgumentException(
            'The selected rate is not an EasyPost rate.'
        );
    }

    $shipmentId =
        trim(
            (string) (
                $rate['external_shipment_id']
                ?? ''
            )
        );

    $externalRateId =
        trim(
            (string) (
                $rate['external_rate_id']
                ?? ''
            )
        );

    if (
        $shipmentId === ''
        || $externalRateId === ''
    ) {
        throw new RuntimeException(
            'The shipping rate is missing its EasyPost identifiers.'
        );
    }

    $shipment =
        llama_shipping_easypost_request(
            'POST',
            'shipments/' .
                rawurlencode(
                    $shipmentId
                ) .
                '/buy',
            [
                'rate' => [
                    'id' =>
                        $externalRateId,
                ],
            ]
        );

    $trackingCode =
        trim(
            (string) (
                $shipment['tracking_code']
                ?? $shipment['tracker']['tracking_code']
                ?? ''
            )
        );

    $carrier =
        trim(
            (string) (
                $shipment['selected_rate']['carrier']
                ?? $rate['carrier']
                ?? ''
            )
        );

    $service =
        trim(
            (string) (
                $shipment['selected_rate']['service']
                ?? $rate['service']
                ?? ''
            )
        );

    $labelUrl =
        trim(
            (string) (
                $shipment['postage_label']['label_pdf_url']
                ?? $shipment['postage_label']['label_url']
                ?? $shipment['postage_label']['label_png_url']
                ?? ''
            )
        );

    $trackerId =
        trim(
            (string) (
                $shipment['tracker']['id']
                ?? ''
            )
        );

    if (
        $trackingCode === ''
        || $labelUrl === ''
    ) {
        throw new RuntimeException(
            'EasyPost purchased the shipment but did not return complete label data.'
        );
    }

    $postageCents =
        (int) (
            $rate['rate_cents']
            ?? 0
        );

    $trackingUrl =
        llama_shipping_tracking_url(
            $carrier,
            $trackingCode
        );

    $db->beginTransaction();

    try {
        $insert =
            $db->prepare(
                'INSERT INTO shop_fulfillment_labels (
                    fulfillment_id,
                    provider,
                    external_shipment_id,
                    external_rate_id,
                    external_tracker_id,
                    carrier,
                    service,
                    tracking_code,
                    tracking_url,
                    label_url,
                    postage_cents,
                    currency,
                    purchased_at
                 ) VALUES (
                    ?, "easypost", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP()
                 )'
            );

        $insert->execute([
            $fulfillmentId,
            $shipmentId,
            $externalRateId,
            $trackerId !== ''
                ? $trackerId
                : null,
            $carrier,
            $service,
            $trackingCode,
            $trackingUrl !== ''
                ? $trackingUrl
                : null,
            $labelUrl,
            $postageCents,
            strtoupper(
                (string) (
                    $rate['currency']
                    ?? 'USD'
                )
            ),
        ]);

        $carrierKey =
            admin_shop_normalize_tracking_carrier(
                $carrier
            );

        $update =
            $db->prepare(
                'UPDATE shop_order_fulfillments
                 SET
                    status = CASE
                        WHEN status = "pending"
                            THEN "submitted"
                        ELSE status
                    END,
                    tracking_carrier = ?,
                    tracking_number = ?,
                    tracking_url = ?,
                    submitted_at = COALESCE(
                        submitted_at,
                        UTC_TIMESTAMP()
                    )
                 WHERE id = ?
                   AND order_id = ?'
            );

        $update->execute([
            $carrierKey !== ''
                ? $carrierKey
                : 'other',
            $trackingCode,
            $trackingUrl !== ''
                ? $trackingUrl
                : null,
            $fulfillmentId,
            $orderId,
        ]);

        admin_users_audit(
            $db,
            $actorUserId,
            $rate['user_id']
                ? (int) $rate['user_id']
                : null,
            'shop.fulfillment_label_purchased',
            'Purchased a shipping label for fulfillment #' .
                $fulfillmentId .
                ' on order ' .
                (string) $rate['order_number'] .
                '.',
            [
                'order_id' => $orderId,
                'fulfillment_id' => $fulfillmentId,
                'carrier' => $carrier,
                'service' => $service,
                'tracking_code' => $trackingCode,
                'postage_cents' => $postageCents,
            ]
        );

        $db->commit();

        admin_fulfillment_sync_order_status(
            $db,
            $orderId
        );

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return [
        'carrier' => $carrier,
        'service' => $service,
        'tracking_code' => $trackingCode,
        'tracking_url' => $trackingUrl,
        'label_url' => $labelUrl,
        'postage_cents' => $postageCents,
    ];
}
