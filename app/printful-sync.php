<?php

declare(strict_types=1);

require_once __DIR__ . '/printful-orders.php';

/*
 * Printful -> Llama Scout synchronization.
 *
 * Webhook payloads are treated only as a signal to refresh.
 * Llama Scout re-fetches the authoritative order/shipment data
 * directly from Printful before changing local fulfillment data.
 *
 * This keeps the public webhook endpoint safe even though Printful
 * v1 webhooks do not provide a signing secret.
 */

function llama_printful_webhook_url(): string
{
    return 'https://llamascout.com/printful-webhook.php';
}

function llama_printful_webhook_types(): array
{
    return [
        'package_shipped',
        'package_returned',
        'order_created',
        'order_failed',
        'order_canceled',
        'order_put_hold',
        'order_remove_hold',
    ];
}

function llama_printful_webhook_configuration(): array
{
    $response = llama_printful_request(
        'GET',
        'webhooks'
    );

    $result = $response['result'] ?? [];

    return is_array($result)
        ? $result
        : [];
}

function llama_printful_configure_webhook(): array
{
    $response = llama_printful_request(
        'POST',
        'webhooks',
        [
            'url' => llama_printful_webhook_url(),
            'types' => llama_printful_webhook_types(),
        ]
    );

    $result = $response['result'] ?? [];

    return is_array($result)
        ? $result
        : [];
}

function llama_printful_shipments(
    string $providerOrderId
): array {
    $providerOrderId = trim($providerOrderId);

    if ($providerOrderId === '') {
        return [];
    }

    /*
     * Printful v2 has the richer shipment status model, including
     * tracking and delivered timestamps.
     */
    $response = llama_printful_request(
        'GET',
        'v2/orders/' .
            rawurlencode($providerOrderId) .
            '/shipments'
    );

    $rows = $response['data'] ?? [];

    return is_array($rows)
        ? array_values(
            array_filter(
                $rows,
                'is_array'
            )
        )
        : [];
}

function llama_printful_local_status(
    string $remoteStatus,
    array $shipments = []
): string {
    foreach ($shipments as $shipment) {
        $deliveredAt = trim(
            (string) (
                $shipment['delivered_at']
                ?? ''
            )
        );

        $deliveryStatus = strtolower(
            trim(
                (string) (
                    $shipment['delivery_status']
                    ?? ''
                )
            )
        );

        if (
            $deliveredAt !== ''
            || $deliveryStatus === 'delivered'
        ) {
            return 'delivered';
        }
    }

    foreach ($shipments as $shipment) {
        $shippedAt = trim(
            (string) (
                $shipment['shipped_at']
                ?? ''
            )
        );

        $shipmentStatus = strtolower(
            trim(
                (string) (
                    $shipment['shipment_status']
                    ?? ''
                )
            )
        );

        if (
            $shippedAt !== ''
            || in_array(
                $shipmentStatus,
                [
                    'shipped',
                    'in_transit',
                    'in-transit',
                ],
                true
            )
        ) {
            return 'shipped';
        }
    }

    $remoteStatus = strtolower(
        trim($remoteStatus)
    );

    return match ($remoteStatus) {
        'draft' => 'processing',

        'pending',
        'inreview',
        'in_review',
        'inprocess',
        'in_process',
        'partial' => 'submitted',

        'fulfilled' => 'shipped',

        'failed',
        'onhold',
        'on_hold' => 'problem',

        'canceled',
        'cancelled' => 'cancelled',

        default => 'processing',
    };
}

function llama_printful_tracking_carrier(
    string $carrier
): string {
    $normalized = strtolower(
        preg_replace(
            '/[^a-z0-9]+/',
            '',
            $carrier
        ) ?? ''
    );

    return match (true) {
        str_contains($normalized, 'usps') =>
            'usps',

        str_contains($normalized, 'ups') =>
            'ups',

        str_contains($normalized, 'fedex') =>
            'fedex',

        str_contains($normalized, 'dhl') =>
            'dhl',

        default =>
            '',
    };
}

function llama_printful_best_shipment(
    array $shipments
): array {
    if (!$shipments) {
        return [];
    }

    usort(
        $shipments,
        static function (
            array $a,
            array $b
        ): int {
            $aTime = strtotime(
                (string) (
                    $a['delivered_at']
                    ?? $a['shipped_at']
                    ?? $a['created_at']
                    ?? ''
                )
            ) ?: 0;

            $bTime = strtotime(
                (string) (
                    $b['delivered_at']
                    ?? $b['shipped_at']
                    ?? $b['created_at']
                    ?? ''
                )
            ) ?: 0;

            return $bTime <=> $aTime;
        }
    );

    return $shipments[0];
}

function llama_printful_mysql_datetime(
    mixed $value
): ?string {
    $raw = trim((string) $value);

    if ($raw === '') {
        return null;
    }

    if (ctype_digit($raw)) {
        $timestamp = (int) $raw;
    } else {
        $timestamp = strtotime($raw) ?: 0;
    }

    if ($timestamp < 1) {
        return null;
    }

    return gmdate(
        'Y-m-d H:i:s',
        $timestamp
    );
}


function llama_printful_order_missing_exception(
    Throwable $exception
): bool {
    $message = strtolower(
        trim(
            $exception->getMessage()
        )
    );

    if ($message === '') {
        return false;
    }

    foreach (
        [
            'not found',
            'order not found',
            'does not exist',
            'could not be found',
            'unknown order',
        ]
        as $fragment
    ) {
        if (
            str_contains(
                $message,
                $fragment
            )
        ) {
            return true;
        }
    }

    return false;
}

function llama_printful_mark_removed_fulfillment(
    PDO $db,
    array $fulfillment,
    int $actorUserId = 0
): array {
    $fulfillmentId =
        (int) $fulfillment['id'];

    $providerOrderId = trim(
        (string) (
            $fulfillment['provider_order_id']
            ?? ''
        )
    );

    $update = $db->prepare(
        'UPDATE shop_order_fulfillments
         SET
            status = "cancelled",
            tracking_number = NULL,
            tracking_carrier = NULL,
            tracking_url = NULL,
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
            'shop.printful_order_removed',
            'Marked Printful fulfillment #' .
                $fulfillmentId .
                ' cancelled because Printful order #' .
                $providerOrderId .
                ' no longer exists.',
            [
                'order_id' =>
                    (int) $fulfillment['order_id'],
                'fulfillment_id' =>
                    $fulfillmentId,
                'printful_order_id' =>
                    $providerOrderId,
            ]
        );
    }

    return [
        'fulfillment_id' =>
            $fulfillmentId,
        'provider_order_id' =>
            $providerOrderId,
        'remote_status' =>
            'removed',
        'local_status' =>
            'cancelled',
        'tracking_number' =>
            '',
        'tracking_url' =>
            '',
        'remote_order' =>
            [],
        'shipments' =>
            [],
        'removed_at_provider' =>
            true,
    ];
}

function llama_printful_sync_fulfillment(
    PDO $db,
    int $fulfillmentId,
    int $actorUserId = 0
): array {
    $stmt = $db->prepare(
        'SELECT
            f.*,
            o.user_id,
            o.order_number
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE f.id = ?
           AND LOWER(
                COALESCE(
                    f.fulfillment_provider,
                    ""
                )
           ) = "printful"
         LIMIT 1'
    );

    $stmt->execute([
        $fulfillmentId,
    ]);

    $fulfillment =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fulfillment) {
        throw new InvalidArgumentException(
            'Printful fulfillment not found.'
        );
    }

    $providerOrderId = trim(
        (string) (
            $fulfillment['provider_order_id']
            ?? ''
        )
    );

    if ($providerOrderId === '') {
        throw new InvalidArgumentException(
            'This fulfillment does not have a Printful order ID yet.'
        );
    }

    try {
        $remoteOrder =
            llama_printful_get_order(
                $providerOrderId
            );
    } catch (Throwable $exception) {
        /*
         * A draft can be deleted directly in Printful. In that
         * case there is no longer a remote status to retrieve.
         * Treat a definite "not found" response as provider-side
         * cancellation instead of leaving the local fulfillment
         * stuck in Processing.
         *
         * Network failures and all other API errors still bubble
         * up normally and do NOT cancel the local fulfillment.
         */
        if (
            llama_printful_order_missing_exception(
                $exception
            )
        ) {
            return
                llama_printful_mark_removed_fulfillment(
                    $db,
                    $fulfillment,
                    $actorUserId
                );
        }

        throw $exception;
    }

    $shipments = [];

    try {
        $shipments =
            llama_printful_shipments(
                $providerOrderId
            );
    } catch (Throwable $exception) {
        /*
         * Order status synchronization should still work if the
         * newer shipment endpoint is temporarily unavailable.
         */
        $shipments = is_array(
            $remoteOrder['shipments']
            ?? null
        )
            ? $remoteOrder['shipments']
            : [];
    }

    $remoteStatus = strtolower(
        trim(
            (string) (
                $remoteOrder['status']
                ?? ''
            )
        )
    );

    $localStatus =
        llama_printful_local_status(
            $remoteStatus,
            $shipments
        );

    $shipment =
        llama_printful_best_shipment(
            $shipments
        );

    $trackingNumber = trim(
        (string) (
            $shipment['tracking_number']
            ?? ''
        )
    );

    $trackingUrl = trim(
        (string) (
            $shipment['tracking_url']
            ?? ''
        )
    );

    $trackingCarrier =
        llama_printful_tracking_carrier(
            (string) (
                $shipment['carrier']
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
            llama_printful_mysql_datetime(
                $remoteOrder['updated']
                ?? $remoteOrder['created']
                ?? ''
            )
            ?? gmdate('Y-m-d H:i:s');
    }

    if (
        in_array(
            $localStatus,
            [
                'shipped',
                'delivered',
            ],
            true
        )
    ) {
        $shippedAt =
            llama_printful_mysql_datetime(
                $shipment['shipped_at']
                ?? $shipment['created']
                ?? ''
            )
            ?? $shippedAt
            ?? gmdate('Y-m-d H:i:s');
    }

    if ($localStatus === 'delivered') {
        $deliveredAt =
            llama_printful_mysql_datetime(
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
            'shop.printful_fulfillment_refreshed',
            'Refreshed Printful fulfillment #' .
                $fulfillmentId .
                ' for order ' .
                (string) $fulfillment['order_number'] .
                '.',
            [
                'order_id' =>
                    (int) $fulfillment['order_id'],
                'fulfillment_id' =>
                    $fulfillmentId,
                'printful_order_id' =>
                    $providerOrderId,
                'printful_status' =>
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

function llama_printful_find_fulfillment_for_event(
    PDO $db,
    array $event
): int {
    $data = is_array(
        $event['data']
        ?? null
    )
        ? $event['data']
        : [];

    $order = is_array(
        $data['order']
        ?? null
    )
        ? $data['order']
        : [];

    $providerOrderId = trim(
        (string) (
            $order['id']
            ?? ''
        )
    );

    if ($providerOrderId !== '') {
        $stmt = $db->prepare(
            'SELECT id
             FROM shop_order_fulfillments
             WHERE LOWER(
                COALESCE(
                    fulfillment_provider,
                    ""
                )
             ) = "printful"
               AND provider_order_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );

        $stmt->execute([
            $providerOrderId,
        ]);

        $id = (int) (
            $stmt->fetchColumn()
            ?: 0
        );

        if ($id > 0) {
            return $id;
        }
    }

    $externalId = trim(
        (string) (
            $order['external_id']
            ?? ''
        )
    );

    if (
        preg_match(
            '/^LS-FULFILLMENT-(\d+)$/',
            $externalId,
            $matches
        )
    ) {
        return (int) $matches[1];
    }

    return 0;
}

function llama_printful_process_webhook(
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
            'Printful webhook event type is missing.'
        );
    }

    /*
     * Product/catalog events are deliberately ignored here.
     * This endpoint is for order fulfillment state only.
     */
    $fulfillmentId =
        llama_printful_find_fulfillment_for_event(
            $db,
            $event
        );

    if ($fulfillmentId < 1) {
        return [
            'handled' => false,
            'type' => $type,
            'reason' =>
                'No matching Llama Scout Printful fulfillment.',
        ];
    }

    $result =
        llama_printful_sync_fulfillment(
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
