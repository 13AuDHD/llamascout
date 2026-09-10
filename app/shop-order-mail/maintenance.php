<?php

declare(strict_types=1);


function shop_notification_maintenance_storage_available(
    PDO $db
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'app_maintenance',
    ]);

    return (bool) $stmt->fetchColumn();
}


function shop_notification_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 300
): bool {
    $intervalSeconds =
        max(60, $intervalSeconds);

    if (
        !shop_notification_maintenance_storage_available(
            $db
        )
    ) {
        throw new RuntimeException(
            'Shop notification maintenance storage is not initialized. Missing table: app_maintenance'
        );
    }

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'shop_notification_email',
    ]);

    $lastRun =
        $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $timestamp =
        shop_notification_utc_timestamp(
            (string) $lastRun
        );

    return
        $timestamp === null
        || (
            time() - $timestamp
        ) >= $intervalSeconds;
}


function shop_mark_notification_maintenance_run(
    PDO $db
): void {
    $stmt = $db->prepare(
        'INSERT INTO app_maintenance
         (
            maintenance_key,
            last_run_at
         )
         VALUES (?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            last_run_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        'shop_notification_email',
    ]);
}


function shop_run_shipment_email_maintenance(
    PDO $db,
    int $limit = 5
): array {
    $summary = [
        'ran' => false,
        'sent' => 0,
        'failed' => 0,
    ];

    if (
        !shop_order_notification_table_exists(
            $db
        )
        || !shop_notification_maintenance_is_due(
            $db
        )
    ) {
        return $summary;
    }

    $lockStmt = $db->query(
        "SELECT GET_LOCK('llamascout_shop_notification_email', 0)"
    );

    if (
        !$lockStmt
        || (int) $lockStmt->fetchColumn() !== 1
    ) {
        return $summary;
    }

    try {
        if (
            !shop_notification_maintenance_is_due(
                $db
            )
        ) {
            return $summary;
        }

        $summary['ran'] = true;

        $limit =
            max(
                1,
                min(
                    25,
                    $limit
                )
            );

        /*
         * LIMIT applies only to fulfillments that still have
         * unsent or retryable notification work.
         */
        $fulfillmentStmt = $db->query(
            'SELECT
                f.id,
                f.status
             FROM shop_order_fulfillments f
             LEFT JOIN shop_order_notifications shipped_notice
                ON shipped_notice.order_id = f.order_id
               AND shipped_notice.notification_type =
                    CONCAT("fulfillment_shipped_", f.id)
             LEFT JOIN shop_order_notifications delivered_notice
                ON delivered_notice.order_id = f.order_id
               AND delivered_notice.notification_type =
                    CONCAT("fulfillment_delivered_", f.id)
             WHERE f.status IN ("shipped","delivered")
               AND (
                    (
                        shipped_notice.id IS NULL
                        OR (
                            shipped_notice.status <> "sent"
                            AND (
                                shipped_notice.failed_at IS NULL
                                OR shipped_notice.failed_at <=
                                    DATE_SUB(
                                        UTC_TIMESTAMP(),
                                        INTERVAL 1 HOUR
                                    )
                            )
                        )
                    )
                    OR (
                        f.status = "delivered"
                        AND (
                            delivered_notice.id IS NULL
                            OR (
                                delivered_notice.status <> "sent"
                                AND (
                                    delivered_notice.failed_at IS NULL
                                    OR delivered_notice.failed_at <=
                                        DATE_SUB(
                                            UTC_TIMESTAMP(),
                                            INTERVAL 1 HOUR
                                        )
                                )
                            )
                        )
                    )
               )
             ORDER BY f.updated_at ASC, f.id ASC
             LIMIT ' . $limit
        );

        $fulfillments =
            $fulfillmentStmt
                ? (
                    $fulfillmentStmt->fetchAll(
                        PDO::FETCH_ASSOC
                    )
                    ?: []
                )
                : [];

        foreach ($fulfillments as $row) {
            $fulfillmentId =
                (int) $row['id'];

            $events =
                strtolower(
                    (string) (
                        $row['status']
                        ?? ''
                    )
                ) === 'delivered'
                    ? [
                        'shipped',
                        'delivered',
                    ]
                    : [
                        'shipped',
                    ];

            foreach ($events as $event) {
                try {
                    if (
                        shop_send_fulfillment_status_email(
                            $db,
                            $fulfillmentId,
                            $event
                        )
                    ) {
                        $summary['sent']++;
                    }
                } catch (Throwable $exception) {
                    $summary['failed']++;

                    if (
                        function_exists(
                            'llama_log_caught_exception'
                        )
                    ) {
                        llama_log_caught_exception(
                            $exception,
                            'shop.fulfillment_notification',
                            [
                                'fulfillment_id' =>
                                    $fulfillmentId,
                                'event' =>
                                    $event,
                            ]
                        );
                    }
                }
            }
        }

        /*
         * LIMIT applies only to refunded orders whose message
         * has never been sent or is old enough to retry.
         */
        $refundStmt = $db->query(
            'SELECT o.id
             FROM shop_orders o
             LEFT JOIN shop_order_notifications refund_notice
                ON refund_notice.order_id = o.id
               AND refund_notice.notification_type =
                    "refund_confirmation"
             WHERE o.payment_status = "refunded"
               AND o.order_status = "refunded"
               AND (
                    refund_notice.id IS NULL
                    OR (
                        refund_notice.status <> "sent"
                        AND (
                            refund_notice.failed_at IS NULL
                            OR refund_notice.failed_at <=
                                DATE_SUB(
                                    UTC_TIMESTAMP(),
                                    INTERVAL 1 HOUR
                                )
                        )
                    )
               )
             ORDER BY o.updated_at ASC, o.id ASC
             LIMIT ' . $limit
        );

        $refundOrderIds =
            $refundStmt
                ? (
                    $refundStmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                    ?: []
                )
                : [];

        foreach ($refundOrderIds as $orderId) {
            try {
                if (
                    shop_send_refund_confirmation(
                        $db,
                        (int) $orderId
                    )
                ) {
                    $summary['sent']++;
                }
            } catch (Throwable $exception) {
                $summary['failed']++;

                if (
                    function_exists(
                        'llama_log_caught_exception'
                    )
                ) {
                    llama_log_caught_exception(
                        $exception,
                        'shop.refund_notification_maintenance',
                        [
                            'order_id' =>
                                (int) $orderId,
                        ]
                    );
                }
            }
        }

        shop_mark_notification_maintenance_run(
            $db
        );

        return $summary;

    } finally {
        try {
            $db->query(
                "SELECT RELEASE_LOCK('llamascout_shop_notification_email')"
            );
        } catch (Throwable) {
            // Connection cleanup releases the lock.
        }
    }
}
