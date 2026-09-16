<?php

declare(strict_types=1);

require_once __DIR__ . '/printify-orders.php';

function llama_printify_mysql_datetime(
    mixed $value
): ?string {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return null;
    }

    return gmdate(
        'Y-m-d H:i:s',
        $timestamp
    );
}

function llama_printify_shipments(
    array $order
): array {
    $rows = is_array(
        $order['shipments'] ?? null
    )
        ? $order['shipments']
        : [];

    return array_values(
        array_filter(
            $rows,
            'is_array'
        )
    );
}

function llama_printify_tracking_carrier(
    string $carrier
): string {
    $normalized =
        preg_replace(
            '/[^a-z0-9]+/',
            '',
            strtolower($carrier)
        ) ?? '';

    return match (true) {
        str_contains($normalized, 'usps') =>
            'usps',
        str_contains($normalized, 'fedex') =>
            'fedex',
        str_contains($normalized, 'dhlglobalmail'),
        str_contains($normalized, 'dhlecommerce') =>
            'dhl_ecommerce',
        str_contains($normalized, 'dhl') =>
            'dhl',
        str_contains($normalized, 'ontrac') =>
            'ontrac',
        str_contains($normalized, 'ups') =>
            'ups',
        $normalized !== '' =>
            'other',
        default =>
            '',
    };
}

function llama_printify_best_shipment(
    array $shipments
): array {
    $best = [];

    foreach ($shipments as $shipment) {
        if (!is_array($shipment)) {
            continue;
        }

        $best = $shipment;

        if (
            trim(
                (string) (
                    $shipment['delivered_at']
                    ?? ''
                )
            ) !== ''
        ) {
            return $shipment;
        }
    }

    return $best;
}

function llama_printify_local_status(
    string $remoteStatus,
    array $shipments = []
): string {
    $remoteStatus = strtolower(
        trim($remoteStatus)
    );

    if ($remoteStatus === 'canceled') {
        return 'cancelled';
    }

    if (
        in_array(
            $remoteStatus,
            [
                'payment-not-received',
                'has-issues',
                'unfulfillable',
                'source-check-failed',
            ],
            true
        )
    ) {
        return 'problem';
    }

    $hasShipment = false;
    $hasDelivered = false;

    foreach ($shipments as $shipment) {
        if (!is_array($shipment)) {
            continue;
        }

        if (
            trim((string) ($shipment['number'] ?? '')) !== ''
            || trim((string) ($shipment['url'] ?? '')) !== ''
        ) {
            $hasShipment = true;
        }

        if (
            trim(
                (string) (
                    $shipment['delivered_at']
                    ?? ''
                )
            ) !== ''
        ) {
            $hasDelivered = true;
        }
    }

    if (
        $remoteStatus === 'fulfilled'
        && $hasDelivered
    ) {
        return 'delivered';
    }

    if (
        in_array(
            $remoteStatus,
            [
                'fulfilled',
                'partially-fulfilled',
            ],
            true
        )
        || $hasShipment
    ) {
        return 'shipped';
    }

    if (
        in_array(
            $remoteStatus,
            [
                'sending-to-production',
                'in-production',
                'sending_to_production_delegate',
                'sending_to_production_delegate_sync',
            ],
            true
        )
    ) {
        return 'submitted';
    }

    return 'processing';
}

function llama_printify_sync_fulfillment(
    PDO $db,
    int $fulfillmentId,
    int $actorUserId = 0
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
            'This Printify fulfillment does not have a provider order ID yet.'
        );
    }

    $remoteOrder =
        llama_printify_get_order(
            $providerOrderId
        );

    $remoteStatus =
        llama_printify_remote_status(
            $remoteOrder
        );

    $shipments =
        llama_printify_shipments(
            $remoteOrder
        );

    $localStatus =
        llama_printify_local_status(
            $remoteStatus,
            $shipments
        );

    $shipment =
        llama_printify_best_shipment(
            $shipments
        );

    $trackingNumber = trim(
        (string) (
            $shipment['number']
            ?? ''
        )
    );

    $trackingCarrier =
        llama_printify_tracking_carrier(
            (string) (
                $shipment['carrier']
                ?? ''
            )
        );

    $trackingUrl = trim(
        (string) (
            $shipment['url']
            ?? ''
        )
    );

    $submittedAt =
        $fulfillment['submitted_at']
        ?? null;

    $shippedAt =
        $fulfillment['shipped_at']
        ?? null;

    $deliveredAt =
        $fulfillment['delivered_at']
        ?? null;

    if (
        in_array(
            $localStatus,
            [
                'processing',
                'submitted',
                'shipped',
                'delivered',
            ],
            true
        )
        && empty($submittedAt)
    ) {
        $submittedAt =
            llama_printify_mysql_datetime(
                $remoteOrder['sent_to_production_at']
                ?? $remoteOrder['created_at']
                ?? ''
            )
            ?? gmdate('Y-m-d H:i:s');
    }

    if (
        in_array(
            $localStatus,
            ['shipped', 'delivered'],
            true
        )
    ) {
        $shippedAt =
            llama_printify_mysql_datetime(
                $shipment['shipped_at']
                ?? $remoteOrder['fulfilled_at']
                ?? ''
            )
            ?? $shippedAt
            ?? gmdate('Y-m-d H:i:s');
    }

    if ($localStatus === 'delivered') {
        $deliveredAt =
            llama_printify_mysql_datetime(
                $shipment['delivered_at']
                ?? ''
            )
            ?? $deliveredAt
            ?? gmdate('Y-m-d H:i:s');
    }

    $update = $db->prepare(
        'UPDATE shop_order_fulfillments
         SET
            status = ?,
            tracking_number = ?,
            tracking_carrier = ?,
            tracking_url = ?,
            submitted_at = ?,
            shipped_at = ?,
            delivered_at = ?,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $update->execute([
        $localStatus,
        $trackingNumber !== ''
            ? $trackingNumber
            : null,
        $trackingCarrier !== ''
            ? $trackingCarrier
            : null,
        $trackingUrl !== ''
            ? $trackingUrl
            : null,
        $submittedAt,
        $shippedAt,
        $deliveredAt,
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

    if (
        $actorUserId > 0
        && function_exists(
            'admin_users_audit'
        )
    ) {
        admin_users_audit(
            $db,
            $actorUserId,
            $fulfillment['user_id']
                ? (int) $fulfillment['user_id']
                : null,
            'shop.printify_fulfillment_refreshed',
            'Refreshed Printify fulfillment #' .
                $fulfillmentId .
                ' for order ' .
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
                    $remoteStatus,
                'local_status' =>
                    $localStatus,
            ]
        );
    }

    return [
        'fulfillment_id' =>
            $fulfillmentId,
        'provider_order_id' =>
            $providerOrderId,
        'remote_status' =>
            $remoteStatus,
        'local_status' =>
            $localStatus,
        'tracking_number' =>
            $trackingNumber,
        'tracking_url' =>
            $trackingUrl,
        'remote_order' =>
            $remoteOrder,
        'shipments' =>
            $shipments,
    ];
}

function llama_printify_find_fulfillment_for_event(
    PDO $db,
    array $event
): int {
    $resource = is_array(
        $event['resource'] ?? null
    )
        ? $event['resource']
        : [];

    $resourceType = strtolower(
        trim(
            (string) (
                $resource['type']
                ?? ''
            )
        )
    );

    if ($resourceType !== 'order') {
        return 0;
    }

    $providerOrderId = trim(
        (string) (
            $resource['id']
            ?? ''
        )
    );

    if ($providerOrderId === '') {
        return 0;
    }

    $stmt = $db->prepare(
        'SELECT id
         FROM shop_order_fulfillments
         WHERE LOWER(
            COALESCE(
                fulfillment_provider,
                ""
            )
         ) = "printify"
           AND provider_order_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );

    $stmt->execute([
        $providerOrderId,
    ]);

    return (int) (
        $stmt->fetchColumn()
        ?: 0
    );
}

function llama_printify_process_webhook(
    PDO $db,
    array $event
): array {
    $type = strtolower(
        trim(
            (string) (
                $event['type']
                ?? ''
            )
        )
    );

    if ($type === '') {
        throw new InvalidArgumentException(
            'Printify webhook event type is missing.'
        );
    }

    $allowedTypes = [
        'order:created',
        'order:updated',
        'order:sent-to-production',
        'order:shipment:created',
        'order:shipment:delivered',
    ];

    if (!in_array($type, $allowedTypes, true)) {
        return [
            'handled' => false,
            'type' => $type,
            'reason' =>
                'Webhook topic is not used by Llama Scout fulfillment.',
        ];
    }

    $fulfillmentId =
        llama_printify_find_fulfillment_for_event(
            $db,
            $event
        );

    if ($fulfillmentId < 1) {
        return [
            'handled' => false,
            'type' => $type,
            'reason' =>
                'No matching Llama Scout Printify fulfillment.',
        ];
    }

    $result =
        llama_printify_sync_fulfillment(
            $db,
            $fulfillmentId
        );

    return [
        'handled' => true,
        'type' => $type,
        'fulfillment_id' =>
            $fulfillmentId,
        'sync' => $result,
    ];
}
