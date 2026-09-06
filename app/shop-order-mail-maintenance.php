<?php

declare(strict_types=1);

require_once __DIR__ . '/shop-order-mail.php';


function shop_notification_row(
    PDO $db,
    int $orderId,
    string $type
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM shop_order_notifications
         WHERE order_id = ?
           AND notification_type = ?
         LIMIT 1'
    );

    $stmt->execute([
        $orderId,
        $type,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function shop_notification_can_attempt(
    PDO $db,
    int $orderId,
    string $type,
    int $retrySeconds = 3600
): bool {
    $row = shop_notification_row(
        $db,
        $orderId,
        $type
    );

    if (!$row) {
        return true;
    }

    if (
        strtolower(
            trim(
                (string) (
                    $row['status']
                    ?? ''
                )
            )
        ) === 'sent'
    ) {
        return false;
    }

    $failedAt = trim(
        (string) (
            $row['failed_at']
            ?? ''
        )
    );

    if ($failedAt === '') {
        return true;
    }

    $failedTimestamp =
        strtotime($failedAt);

    if ($failedTimestamp === false) {
        return true;
    }

    return
        (time() - $failedTimestamp)
        >= max(300, $retrySeconds);
}


function shop_order_mail_customer(
    PDO $db,
    int $orderId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            id,
            order_number,
            user_id,
            customer_email,
            shipping_name,
            currency,
            total_cents,
            payment_status,
            order_status
         FROM shop_orders
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$orderId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function shop_fulfillment_notification_type(
    int $fulfillmentId,
    string $event
): string {
    $event = strtolower(trim($event));

    if (!in_array(
        $event,
        ['shipped', 'delivered'],
        true
    )) {
        throw new InvalidArgumentException(
            'Invalid fulfillment email event.'
        );
    }

    return
        'fulfillment_'
        . $event
        . '_'
        . $fulfillmentId;
}


function shop_send_fulfillment_status_email(
    PDO $db,
    int $fulfillmentId,
    string $event
): bool {
    if (!shop_order_notification_table_exists($db)) {
        return false;
    }

    $event = strtolower(trim($event));

    if (!in_array(
        $event,
        ['shipped', 'delivered'],
        true
    )) {
        throw new InvalidArgumentException(
            'Invalid fulfillment email event.'
        );
    }

    $stmt = $db->prepare(
        'SELECT
            f.id,
            f.order_id,
            f.status,
            f.fulfillment_provider,
            f.provider_order_id,
            f.tracking_number,
            f.tracking_carrier,
            f.tracking_url,
            f.shipped_at,
            f.delivered_at,
            o.order_number,
            o.user_id,
            o.customer_email,
            o.shipping_name
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE f.id = ?
         LIMIT 1'
    );

    $stmt->execute([$fulfillmentId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return false;
    }

    $status = strtolower(
        trim(
            (string) (
                $row['status']
                ?? ''
            )
        )
    );

    if (
        $event === 'shipped'
        && !in_array(
            $status,
            ['shipped', 'delivered'],
            true
        )
    ) {
        return false;
    }

    if (
        $event === 'delivered'
        && $status !== 'delivered'
    ) {
        return false;
    }

    $orderId =
        (int) $row['order_id'];

    $notificationType =
        shop_fulfillment_notification_type(
            $fulfillmentId,
            $event
        );

    if (
        !shop_notification_can_attempt(
            $db,
            $orderId,
            $notificationType
        )
    ) {
        return false;
    }

    $email = trim(
        (string) (
            $row['customer_email']
            ?? ''
        )
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $orderNumber = trim(
        (string) (
            $row['order_number']
            ?? ''
        )
    );

    $name = trim(
        (string) (
            $row['shipping_name']
            ?? ''
        )
    );

    $greeting =
        $name !== ''
            ? 'Hi ' . $name . ','
            : 'Hi,';

    $trackingNumber = trim(
        (string) (
            $row['tracking_number']
            ?? ''
        )
    );

    $trackingCarrier = trim(
        (string) (
            $row['tracking_carrier']
            ?? ''
        )
    );

    $trackingUrl = trim(
        (string) (
            $row['tracking_url']
            ?? ''
        )
    );

    if ($event === 'delivered') {
        $subject =
            'Delivered: '
            . (
                $orderNumber !== ''
                    ? $orderNumber
                    : 'Llama Scout order'
            );

        $headline =
            'Your Llama Scout order was delivered.';

        $bodyLine =
            'The fulfillment provider has marked this shipment as delivered.';
    } else {
        $subject =
            'Your order shipped: '
            . (
                $orderNumber !== ''
                    ? $orderNumber
                    : 'Llama Scout order'
            );

        $headline =
            'Your Llama Scout order is on the way.';

        $bodyLine =
            'Your shipment has been marked shipped.';
    }

    $text =
        $greeting
        . "\n\n"
        . $headline
        . "\n\nOrder: "
        . $orderNumber
        . "\n"
        . $bodyLine;

    if ($trackingCarrier !== '') {
        $text .=
            "\nCarrier: "
            . strtoupper($trackingCarrier);
    }

    if ($trackingNumber !== '') {
        $text .=
            "\nTracking: "
            . $trackingNumber;
    }

    if ($trackingUrl !== '') {
        $text .=
            "\nTrack shipment:\n"
            . $trackingUrl;
    }

    $text .=
        "\n\nLlama Scout\n"
        . "Know the place before you go.\n";

    $safeGreeting = htmlspecialchars(
        $greeting,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeHeadline = htmlspecialchars(
        $headline,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeOrderNumber = htmlspecialchars(
        $orderNumber,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeBodyLine = htmlspecialchars(
        $bodyLine,
        ENT_QUOTES,
        'UTF-8'
    );

    $trackingHtml = '';

    if (
        $trackingCarrier !== ''
        || $trackingNumber !== ''
    ) {
        $trackingHtml .=
            '<div style="margin:22px 0;padding:14px 16px;background:#f7f7f3;border-radius:10px;">';

        if ($trackingCarrier !== '') {
            $trackingHtml .=
                '<div><strong>Carrier:</strong> '
                . htmlspecialchars(
                    strtoupper($trackingCarrier),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</div>';
        }

        if ($trackingNumber !== '') {
            $trackingHtml .=
                '<div style="margin-top:5px;"><strong>Tracking:</strong> '
                . htmlspecialchars(
                    $trackingNumber,
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</div>';
        }

        $trackingHtml .= '</div>';
    }

    $trackingButton = '';

    if ($trackingUrl !== '') {
        $trackingButton =
            '<p style="margin:24px 0 0;">'
            . '<a href="'
            . htmlspecialchars(
                $trackingUrl,
                ENT_QUOTES,
                'UTF-8'
            )
            . '" style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:bold;">'
            . 'Track shipment'
            . '</a>'
            . '</p>';
    }

    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#172822;">
<div style="max-width:640px;margin:0 auto;padding:32px 18px;">
<div style="background:#ffffff;border-radius:16px;padding:32px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#667069;">Llama Scout Shop</p>
<h1 style="margin:0 0 20px;font-size:28px;line-height:1.2;">{$safeHeadline}</h1>
<p style="margin:0 0 16px;line-height:1.6;">{$safeGreeting}</p>
<p style="margin:0 0 12px;line-height:1.6;">{$safeBodyLine}</p>
<p style="margin:0;line-height:1.6;"><strong>Order:</strong> {$safeOrderNumber}</p>
{$trackingHtml}
{$trackingButton}
<hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 20px;">
<p style="margin:0;color:#667069;font-size:12px;line-height:1.6;">
Llama Scout<br>
Know the place before you go.
</p>
</div>
</div>
</body>
</html>
HTML;

    try {
        $sent = send_llama_mail(
            $email,
            $subject,
            $text,
            $html
        );

        if (!$sent) {
            throw new RuntimeException(
                'Mail server rejected the fulfillment notification.'
            );
        }

        shop_order_record_notification(
            $db,
            $orderId,
            $notificationType,
            $email,
            'sent'
        );

        return true;
    } catch (Throwable $exception) {
        shop_order_record_notification(
            $db,
            $orderId,
            $notificationType,
            $email,
            'failed',
            $exception->getMessage()
        );

        throw $exception;
    }
}


function shop_send_refund_confirmation(
    PDO $db,
    int $orderId
): bool {
    if (!shop_order_notification_table_exists($db)) {
        return false;
    }

    $notificationType =
        'refund_confirmation';

    if (
        !shop_notification_can_attempt(
            $db,
            $orderId,
            $notificationType
        )
    ) {
        return false;
    }

    $order = shop_order_mail_customer(
        $db,
        $orderId
    );

    if (!$order) {
        return false;
    }

    if (
        strtolower(
            trim(
                (string) (
                    $order['payment_status']
                    ?? ''
                )
            )
        ) !== 'refunded'
    ) {
        return false;
    }

    $email = trim(
        (string) (
            $order['customer_email']
            ?? ''
        )
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $orderNumber = trim(
        (string) (
            $order['order_number']
            ?? ''
        )
    );

    $name = trim(
        (string) (
            $order['shipping_name']
            ?? ''
        )
    );

    $greeting =
        $name !== ''
            ? 'Hi ' . $name . ','
            : 'Hi,';

    $subject =
        'Refund confirmed: '
        . (
            $orderNumber !== ''
                ? $orderNumber
                : 'Llama Scout order'
        );

    $text =
        $greeting
        . "\n\nYour Llama Scout Shop refund has been confirmed."
        . "\n\nOrder: "
        . $orderNumber
        . "\nRefund: "
        . shop_order_mail_money(
            (int) (
                $order['total_cents']
                ?? 0
            ),
            (string) (
                $order['currency']
                ?? 'usd'
            )
        )
        . "\n\nYour bank or card provider may take additional time to post the credit."
        . "\n\nLlama Scout\n"
        . "Know the place before you go.\n";

    $safeGreeting = htmlspecialchars(
        $greeting,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeOrderNumber = htmlspecialchars(
        $orderNumber,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeAmount = htmlspecialchars(
        shop_order_mail_money(
            (int) (
                $order['total_cents']
                ?? 0
            ),
            (string) (
                $order['currency']
                ?? 'usd'
            )
        ),
        ENT_QUOTES,
        'UTF-8'
    );

    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#172822;">
<div style="max-width:640px;margin:0 auto;padding:32px 18px;">
<div style="background:#ffffff;border-radius:16px;padding:32px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#667069;">Llama Scout Shop</p>
<h1 style="margin:0 0 20px;font-size:28px;line-height:1.2;">Refund confirmed</h1>
<p style="margin:0 0 16px;line-height:1.6;">{$safeGreeting}</p>
<p style="margin:0 0 16px;line-height:1.6;">
Your Llama Scout Shop refund has been confirmed.
</p>
<div style="padding:14px 16px;background:#f7f7f3;border-radius:10px;">
<div><strong>Order:</strong> {$safeOrderNumber}</div>
<div style="margin-top:5px;"><strong>Refund:</strong> {$safeAmount}</div>
</div>
<p style="margin:22px 0 0;line-height:1.6;color:#52605a;">
Your bank or card provider may take additional time to post the credit.
</p>
<hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 20px;">
<p style="margin:0;color:#667069;font-size:12px;line-height:1.6;">
Llama Scout<br>
Know the place before you go.
</p>
</div>
</div>
</body>
</html>
HTML;

    try {
        $sent = send_llama_mail(
            $email,
            $subject,
            $text,
            $html
        );

        if (!$sent) {
            throw new RuntimeException(
                'Mail server rejected the refund confirmation.'
            );
        }

        shop_order_record_notification(
            $db,
            $orderId,
            $notificationType,
            $email,
            'sent'
        );

        return true;
    } catch (Throwable $exception) {
        shop_order_record_notification(
            $db,
            $orderId,
            $notificationType,
            $email,
            'failed',
            $exception->getMessage()
        );

        throw $exception;
    }
}


function shop_notification_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 300
): bool {
    $intervalSeconds =
        max(60, $intervalSeconds);

    $db->exec(
        'CREATE TABLE IF NOT EXISTS app_maintenance
         (
            maintenance_key VARCHAR(100) NOT NULL,
            last_run_at DATETIME NULL,
            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (maintenance_key)
         )
         ENGINE=InnoDB
         DEFAULT CHARSET=utf8mb4
         COLLATE=utf8mb4_unicode_ci'
    );

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'shop_notification_email',
    ]);

    $lastRun = $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $timestamp =
        strtotime((string) $lastRun);

    return
        $timestamp === false
        || (time() - $timestamp)
            >= $intervalSeconds;
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
        || (int) $lockStmt->fetchColumn()
            !== 1
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

        $limit = max(
            1,
            min(25, $limit)
        );

        /*
         * Select only fulfillments that still have notification work.
         *
         * The previous query limited the oldest shipped/delivered
         * fulfillments first and then checked notification state in PHP.
         * Once enough old rows had already been notified, newer rows could
         * sit behind the LIMIT forever.
         *
         * Keep the send functions as the final race-safe eligibility check,
         * but make the SQL LIMIT apply to actual unsent or retryable work.
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
                    ? ['shipped', 'delivered']
                    : ['shipped'];

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
                                'event' => $event,
                            ]
                        );
                    }
                }
            }
        }

        /*
         * Apply the refund LIMIT only to orders whose confirmation has
         * never been sent or whose previous failure is old enough to retry.
         */
        $refundStmt = $db->query(
            'SELECT o.id
             FROM shop_orders o
             LEFT JOIN shop_order_notifications refund_notice
                ON refund_notice.order_id = o.id
               AND refund_notice.notification_type =
                    "refund_confirmation"
             WHERE o.payment_status = "refunded"
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
