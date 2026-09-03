<?php

declare(strict_types=1);

require_once __DIR__ . '/printful.php';

/*
 * Printful order fulfillment bridge.
 *
 * Creates or refreshes a Printful order for one Llama Scout
 * fulfillment. With auto_confirm=false, the Printful order stays
 * in Draft and does not charge the Printful billing method.
 */

function llama_printful_recipient_from_order(array $order): array
{
    $address = [];

    if (!empty($order['shipping_address_json'])) {
        $decoded = json_decode(
            (string) $order['shipping_address_json'],
            true
        );

        if (is_array($decoded)) {
            $address = $decoded;
        }
    }

    $recipient = [
        'name' => trim((string) ($order['shipping_name'] ?? '')),
        'address1' => trim((string) (
            $address['line1']
            ?? $address['street1']
            ?? ''
        )),
        'address2' => trim((string) (
            $address['line2']
            ?? $address['street2']
            ?? ''
        )),
        'city' => trim((string) ($address['city'] ?? '')),
        'state_code' => trim((string) ($address['state'] ?? '')),
        'country_code' => strtoupper(
            trim((string) ($address['country'] ?? 'US'))
        ),
        'zip' => trim((string) (
            $address['postal_code']
            ?? $address['zip']
            ?? ''
        )),
        'phone' => trim((string) ($order['shipping_phone'] ?? '')),
        'email' => trim((string) ($order['customer_email'] ?? '')),
    ];

    foreach (['name', 'address1', 'city', 'country_code', 'zip'] as $required) {
        if ($recipient[$required] === '') {
            throw new InvalidArgumentException(
                'The shipping address is incomplete for Printful. Missing: ' .
                $required . '.'
            );
        }
    }

    return array_filter(
        $recipient,
        static fn(string $value): bool => $value !== ''
    );
}

function llama_printful_fulfillment_context(
    PDO $db,
    int $fulfillmentId
): array {
    $stmt = $db->prepare(
        'SELECT
            f.*,
            o.order_number,
            o.user_id,
            o.payment_status,
            o.order_status,
            o.shipping_name,
            o.shipping_phone,
            o.customer_email,
            o.shipping_address_json
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE f.id = ?
         LIMIT 1'
    );

    $stmt->execute([$fulfillmentId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new InvalidArgumentException(
            'Fulfillment not found.'
        );
    }

    $provider = strtolower(
        trim((string) ($row['fulfillment_provider'] ?? ''))
    );

    if ($provider !== 'printful') {
        throw new InvalidArgumentException(
            'This fulfillment is not assigned to Printful.'
        );
    }

    if (
        strtolower(trim((string) ($row['payment_status'] ?? '')))
        !== 'paid'
    ) {
        throw new InvalidArgumentException(
            'Only paid orders can be sent to Printful.'
        );
    }

    return $row;
}

function llama_printful_fulfillment_items(
    PDO $db,
    int $fulfillmentId
): array {
    $stmt = $db->prepare(
        'SELECT
            oi.*,
            fi.quantity AS fulfillment_quantity
         FROM shop_order_fulfillment_items fi
         INNER JOIN shop_order_items oi
            ON oi.id = fi.order_item_id
         WHERE fi.fulfillment_id = ?
         ORDER BY fi.id ASC'
    );

    $stmt->execute([$fulfillmentId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    /*
     * Defensive filtering matters for older manually-created
     * fulfillments that may have attached every physical item.
     */
    return array_values(
        array_filter(
            $rows,
            static function (array $item): bool {
                return strtolower(
                    trim((string) ($item['fulfillment_provider'] ?? ''))
                ) === 'printful';
            }
        )
    );
}

function llama_printful_order_payload(
    PDO $db,
    int $fulfillmentId
): array {
    $fulfillment = llama_printful_fulfillment_context(
        $db,
        $fulfillmentId
    );

    $items = llama_printful_fulfillment_items(
        $db,
        $fulfillmentId
    );

    if (!$items) {
        throw new InvalidArgumentException(
            'This Printful fulfillment has no Printful order items attached.'
        );
    }

    $payloadItems = [];

    foreach ($items as $item) {
        $syncVariantId = (int) (
            $item['fulfillment_variant_id']
            ?? 0
        );

        if ($syncVariantId < 1) {
            throw new InvalidArgumentException(
                'Printful Sync Variant ID is missing for ' .
                (string) ($item['product_name'] ?? 'an order item') .
                '. Fix its variant mapping before creating the Printful order.'
            );
        }

        $quantity = max(
            1,
            (int) (
                $item['fulfillment_quantity']
                ?? $item['quantity']
                ?? 1
            )
        );

        $payloadItems[] = [
            'external_id' => 'LS-ITEM-' . (int) $item['id'],
            'sync_variant_id' => $syncVariantId,
            'quantity' => $quantity,
            'retail_price' => number_format(
                ((int) ($item['unit_price_cents'] ?? 0)) / 100,
                2,
                '.',
                ''
            ),
        ];
    }

    return [
        'external_id' => 'LS-FULFILLMENT-' . $fulfillmentId,
        'shipping' => 'STANDARD',
        'recipient' => llama_printful_recipient_from_order(
            $fulfillment
        ),
        'items' => $payloadItems,
    ];
}

function llama_printful_get_order(string $orderId): array
{
    $orderId = trim($orderId);

    if ($orderId === '') {
        return [];
    }

    $response = llama_printful_request(
        'GET',
        'orders/' . rawurlencode($orderId)
    );

    $result = $response['result'] ?? [];

    return is_array($result)
        ? $result
        : [];
}

function llama_printful_create_fulfillment_order(
    PDO $db,
    int $actorUserId,
    int $fulfillmentId
): array {
    $fulfillment = llama_printful_fulfillment_context(
        $db,
        $fulfillmentId
    );

    $existingId = trim(
        (string) ($fulfillment['provider_order_id'] ?? '')
    );

    if ($existingId !== '') {
        return llama_printful_get_order($existingId);
    }

    $payload = llama_printful_order_payload(
        $db,
        $fulfillmentId
    );

    $autoConfirm = llama_printful_auto_confirm();

    $response = llama_printful_request(
        'POST',
        'orders',
        $payload,
        [
            'confirm' => $autoConfirm ? '1' : '0',
            'update_existing' => '1',
        ]
    );

    $order = $response['result'] ?? [];

    if (!is_array($order)) {
        $order = [];
    }

    $providerOrderId = trim(
        (string) ($order['id'] ?? '')
    );

    if ($providerOrderId === '') {
        throw new RuntimeException(
            'Printful created the order but did not return an order ID.'
        );
    }

    $providerStatus = strtolower(
        trim((string) ($order['status'] ?? 'draft'))
    );

    $localStatus = $autoConfirm
        ? 'submitted'
        : 'processing';

    $update = $db->prepare(
        'UPDATE shop_order_fulfillments
         SET
            provider_order_id = ?,
            status = ?,
            submitted_at = CASE
                WHEN ? = "submitted"
                    THEN COALESCE(submitted_at, UTC_TIMESTAMP())
                ELSE submitted_at
            END
         WHERE id = ?'
    );

    $update->execute([
        $providerOrderId,
        $localStatus,
        $localStatus,
        $fulfillmentId,
    ]);

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            $fulfillment['user_id']
                ? (int) $fulfillment['user_id']
                : null,
            'shop.printful_order_created',
            'Created Printful ' .
                ($autoConfirm ? 'confirmed' : 'draft') .
                ' order for ' .
                (string) $fulfillment['order_number'] .
                '.',
            [
                'order_id' => (int) $fulfillment['order_id'],
                'fulfillment_id' => $fulfillmentId,
                'printful_order_id' => $providerOrderId,
                'printful_status' => $providerStatus,
                'auto_confirm' => $autoConfirm,
            ]
        );
    }

    if (function_exists('admin_fulfillment_sync_order_status')) {
        admin_fulfillment_sync_order_status(
            $db,
            (int) $fulfillment['order_id']
        );
    }

    return $order;
}
