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

function admin_fulfillment_format_timestamp(
    mixed $value
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return 'Not yet';
    }

    return $value;
}
