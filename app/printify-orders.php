<?php

declare(strict_types=1);

require_once __DIR__ . '/printify.php';

/*
 * Printify order fulfillment bridge.
 *
 * Llama Scout creates an order in Printify first. With auto_submit=false,
 * Llama Scout does not explicitly call Send to Production. Printify store
 * Order approval must also be set to Manual during testing because Printify
 * can auto-approve orders independently of this integration.
 */

function llama_printify_recipient_name_parts(
    string $name
): array {
    $name = trim(
        preg_replace('/\s+/', ' ', $name)
        ?? $name
    );

    if ($name === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/', $name) ?: [];

    if (count($parts) < 2) {
        return [$name, ''];
    }

    $first = (string) array_shift($parts);
    $last = trim(implode(' ', $parts));

    return [$first, $last];
}

function llama_printify_recipient_from_order(
    array $order
): array {
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

    [$firstName, $lastName] =
        llama_printify_recipient_name_parts(
            (string) ($order['shipping_name'] ?? '')
        );

    $recipient = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => trim(
            (string) ($order['customer_email'] ?? '')
        ),
        'phone' => trim(
            (string) ($order['shipping_phone'] ?? '')
        ),
        'country' => strtoupper(
            trim(
                (string) (
                    $address['country']
                    ?? 'US'
                )
            )
        ),
        'region' => trim(
            (string) (
                $address['state']
                ?? $address['region']
                ?? ''
            )
        ),
        'address1' => trim(
            (string) (
                $address['line1']
                ?? $address['street1']
                ?? ''
            )
        ),
        'address2' => trim(
            (string) (
                $address['line2']
                ?? $address['street2']
                ?? ''
            )
        ),
        'city' => trim(
            (string) ($address['city'] ?? '')
        ),
        'zip' => trim(
            (string) (
                $address['postal_code']
                ?? $address['zip']
                ?? ''
            )
        ),
    ];

    foreach (
        [
            'first_name',
            'address1',
            'city',
            'country',
            'zip',
        ]
        as $required
    ) {
        if ($recipient[$required] === '') {
            throw new InvalidArgumentException(
                'The shipping address is incomplete for Printify. Missing: ' .
                $required . '.'
            );
        }
    }

    return $recipient;
}

function llama_printify_fulfillment_context(
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
        trim(
            (string) ($row['fulfillment_provider'] ?? '')
        )
    );

    if ($provider !== 'printify') {
        throw new InvalidArgumentException(
            'This fulfillment is not assigned to Printify.'
        );
    }

    $paymentStatus = strtolower(
        trim((string) ($row['payment_status'] ?? ''))
    );

    $orderStatus = strtolower(
        trim((string) ($row['order_status'] ?? ''))
    );

    if (
        $paymentStatus !== 'paid'
        || !in_array(
            $orderStatus,
            [
                'paid',
                'processing',
                'submitted',
                'shipped',
                'delivered',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Only paid and fulfillable orders can be managed through Printify.'
        );
    }

    return $row;
}

function llama_printify_fulfillment_items(
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
         ORDER BY fi.order_item_id ASC'
    );

    $stmt->execute([$fulfillmentId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_values(
        array_filter(
            $rows,
            static function (array $item): bool {
                return strtolower(
                    trim(
                        (string) (
                            $item['fulfillment_provider']
                            ?? ''
                        )
                    )
                ) === 'printify';
            }
        )
    );
}

function llama_printify_order_payload(
    PDO $db,
    int $fulfillmentId
): array {
    $fulfillment =
        llama_printify_fulfillment_context(
            $db,
            $fulfillmentId
        );

    $items = llama_printify_fulfillment_items(
        $db,
        $fulfillmentId
    );

    if (!$items) {
        throw new InvalidArgumentException(
            'This Printify fulfillment has no Printify order items attached.'
        );
    }

    $lineItems = [];

    foreach ($items as $item) {
        $productId = trim(
            (string) (
                $item['fulfillment_product_id']
                ?? ''
            )
        );

        $variantId = (int) (
            $item['fulfillment_variant_id']
            ?? 0
        );

        if ($productId === '' || $variantId < 1) {
            throw new InvalidArgumentException(
                'Printify product or variant ID is missing for ' .
                (string) ($item['product_name'] ?? 'an order item') .
                '. Fix its variant mapping before creating the Printify order.'
            );
        }

        $lineItems[] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => max(
                1,
                (int) (
                    $item['fulfillment_quantity']
                    ?? $item['quantity']
                    ?? 1
                )
            ),
            'external_id' =>
                'LS-ITEM-' . (int) $item['id'],
        ];
    }

    return [
        'external_id' =>
            'LS-FULFILLMENT-' . $fulfillmentId,
        'label' =>
            (string) $fulfillment['order_number'],
        'line_items' => $lineItems,
        'shipping_method' =>
            llama_printify_shipping_method(),
        'send_shipping_notification' => false,
        'address_to' =>
            llama_printify_recipient_from_order(
                $fulfillment
            ),
    ];
}

function llama_printify_get_order(
    string $orderId
): array {
    $orderId = trim($orderId);

    if ($orderId === '') {
        return [];
    }

    return llama_printify_request(
        'GET',
        'shops/' .
            rawurlencode(llama_printify_shop_id()) .
            '/orders/' .
            rawurlencode($orderId) .
            '.json'
    );
}

function llama_printify_find_order_by_external_id(
    string $externalId,
    int $maxPages = 20
): array {
    $externalId = trim($externalId);

    if ($externalId === '') {
        return [];
    }

    $shopId = llama_printify_shop_id();
    $maxPages = max(1, min(50, $maxPages));

    for ($page = 1; $page <= $maxPages; $page++) {
        $response = llama_printify_request(
            'GET',
            'shops/' .
                rawurlencode($shopId) .
                '/orders.json',
            null,
            [
                'page' => $page,
                'limit' => 10,
            ]
        );

        $rows = is_array($response['data'] ?? null)
            ? $response['data']
            : [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $metadata = is_array($row['metadata'] ?? null)
                ? $row['metadata']
                : [];

            $candidates = [
                trim((string) ($row['external_id'] ?? '')),
                trim((string) ($metadata['shop_order_id'] ?? '')),
                trim((string) ($metadata['shop_order_label'] ?? '')),
            ];

            foreach ($candidates as $candidate) {
                if (
                    $candidate !== ''
                    && hash_equals($externalId, $candidate)
                ) {
                    return $row;
                }
            }
        }

        if (!$rows) {
            break;
        }

        $lastPage = (int) (
            $response['last_page']
            ?? 0
        );

        if ($lastPage > 0 && $page >= $lastPage) {
            break;
        }
    }

    return [];
}

function llama_printify_remote_status(
    array $order
): string {
    return strtolower(
        trim(
            (string) (
                $order['status']
                ?? ''
            )
        )
    );
}

function llama_printify_create_fulfillment_order(
    PDO $db,
    int $actorUserId,
    int $fulfillmentId
): array {
    if ($fulfillmentId < 1) {
        throw new InvalidArgumentException(
            'A valid fulfillment is required.'
        );
    }

    $lockName =
        'llamascout_printify_fulfillment_' .
        $fulfillmentId;

    $lockStmt = $db->prepare(
        'SELECT GET_LOCK(?, 10)'
    );
    $lockStmt->execute([$lockName]);

    if ((int) $lockStmt->fetchColumn() !== 1) {
        throw new RuntimeException(
            'Could not acquire the Printify fulfillment lock.'
        );
    }

    try {
        $fulfillment =
            llama_printify_fulfillment_context(
                $db,
                $fulfillmentId
            );

        $existingId = trim(
            (string) (
                $fulfillment['provider_order_id']
                ?? ''
            )
        );

        if ($existingId !== '') {
            return llama_printify_get_order(
                $existingId
            );
        }

        $payload = llama_printify_order_payload(
            $db,
            $fulfillmentId
        );

        $shopId = llama_printify_shop_id();
        $externalId = (string) $payload['external_id'];

        /*
         * Recover safely if a previous provider request succeeded but the
         * local database write failed before provider_order_id was stored.
         */
        $order = llama_printify_find_order_by_external_id(
            $externalId
        );

        if (!$order) {
            try {
                $order = llama_printify_request(
                    'POST',
                    'shops/' .
                        rawurlencode($shopId) .
                        '/orders.json',
                    $payload
                );
            } catch (Throwable $exception) {
                $recovered =
                    llama_printify_find_order_by_external_id(
                        $externalId
                    );

                if (!$recovered) {
                    throw $exception;
                }

                $order = $recovered;
            }
        }

        $providerOrderId = trim(
            (string) ($order['id'] ?? '')
        );

        if ($providerOrderId === '') {
            throw new RuntimeException(
                'Printify created the order but did not return an order ID.'
            );
        }

        $submittedAt = gmdate('Y-m-d H:i:s');

        $update = $db->prepare(
            'UPDATE shop_order_fulfillments
             SET
                provider_order_id = ?,
                status = "processing",
                submitted_at = COALESCE(
                    submitted_at,
                    ?
                )
             WHERE id = ?
               AND (
                   provider_order_id IS NULL
                   OR provider_order_id = ""
               )'
        );

        $update->execute([
            $providerOrderId,
            $submittedAt,
            $fulfillmentId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException(
                'Printify order was created but the local fulfillment could not be claimed safely.'
            );
        }

        if (llama_printify_auto_submit()) {
            $order = llama_printify_send_to_production(
                $db,
                $actorUserId,
                $fulfillmentId
            );
        } elseif (
            function_exists(
                'admin_fulfillment_sync_order_status'
            )
        ) {
            admin_fulfillment_sync_order_status(
                $db,
                (int) $fulfillment['order_id']
            );
        }

        if (function_exists('admin_users_audit')) {
            admin_users_audit(
                $db,
                $actorUserId,
                $fulfillment['user_id']
                    ? (int) $fulfillment['user_id']
                    : null,
                'shop.printify_order_created',
                'Created Printify order for ' .
                    (string) $fulfillment['order_number'] .
                    '.',
                [
                    'order_id' =>
                        (int) $fulfillment['order_id'],
                    'fulfillment_id' =>
                        $fulfillmentId,
                    'printify_order_id' =>
                        $providerOrderId,
                    'printify_status' =>
                        llama_printify_remote_status($order),
                    'auto_submit' =>
                        llama_printify_auto_submit(),
                ]
            );
        }

        return $order;
    } finally {
        $releaseStmt = $db->prepare(
            'SELECT RELEASE_LOCK(?)'
        );
        $releaseStmt->execute([$lockName]);
    }
}

function llama_printify_send_to_production(
    PDO $db,
    int $actorUserId,
    int $fulfillmentId
): array {
    $fulfillment =
        llama_printify_fulfillment_context(
            $db,
            $fulfillmentId
        );

    $providerOrderId = trim(
        (string) (
            $fulfillment['provider_order_id']
            ?? ''
        )
    );

    if ($providerOrderId === '') {
        throw new InvalidArgumentException(
            'Create the Printify order before sending it to production.'
        );
    }

    $current = llama_printify_get_order(
        $providerOrderId
    );

    $status = llama_printify_remote_status(
        $current
    );

    if (
        in_array(
            $status,
            [
                'sending-to-production',
                'in-production',
                'fulfilled',
                'partially-fulfilled',
                'sending_to_production_delegate',
                'sending_to_production_delegate_sync',
            ],
            true
        )
    ) {
        return $current;
    }

    if ($status === 'canceled') {
        throw new InvalidArgumentException(
            'This Printify order is cancelled and cannot be sent to production.'
        );
    }

    llama_printify_request(
        'POST',
        'shops/' .
            rawurlencode(llama_printify_shop_id()) .
            '/orders/' .
            rawurlencode($providerOrderId) .
            '/send_to_production.json',
        []
    );

    $remote = llama_printify_get_order(
        $providerOrderId
    );

    $update = $db->prepare(
        'UPDATE shop_order_fulfillments
         SET
            status = "submitted",
            submitted_at = COALESCE(
                submitted_at,
                UTC_TIMESTAMP()
            ),
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $update->execute([
        $fulfillmentId,
    ]);

    if (
        function_exists(
            'admin_fulfillment_sync_order_status'
        )
    ) {
        admin_fulfillment_sync_order_status(
            $db,
            (int) $fulfillment['order_id']
        );
    }

    if (function_exists('admin_users_audit')) {
        admin_users_audit(
            $db,
            $actorUserId,
            $fulfillment['user_id']
                ? (int) $fulfillment['user_id']
                : null,
            'shop.printify_order_sent_to_production',
            'Sent Printify order #' .
                $providerOrderId .
                ' to production for ' .
                (string) $fulfillment['order_number'] .
                '.',
            [
                'order_id' =>
                    (int) $fulfillment['order_id'],
                'fulfillment_id' =>
                    $fulfillmentId,
                'printify_order_id' =>
                    $providerOrderId,
                'printify_status' =>
                    llama_printify_remote_status($remote),
            ]
        );
    }

    return $remote;
}
