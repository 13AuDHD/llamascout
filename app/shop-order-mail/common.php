<?php

declare(strict_types=1);


function shop_order_mail_money(
    int $cents,
    string $currency = 'usd'
): string {
    $currency = strtolower(trim($currency));

    if ($currency === 'usd') {
        return '$' . number_format($cents / 100, 2);
    }

    return number_format($cents / 100, 2)
        . ' '
        . strtoupper($currency);
}


function shop_order_notification_table_exists(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'shop_order_notifications'"
    );

    return $stmt
        && (int) $stmt->fetchColumn() > 0;
}


function shop_order_confirmation_sent(
    PDO $db,
    int $orderId
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM shop_order_notifications
         WHERE order_id = ?
           AND notification_type = "order_confirmation"
           AND status = "sent"
         LIMIT 1'
    );

    $stmt->execute([$orderId]);

    return (bool) $stmt->fetchColumn();
}


function shop_order_record_notification(
    PDO $db,
    int $orderId,
    string $type,
    string $email,
    string $status,
    ?string $failure = null
): void {
    $stmt = $db->prepare(
        'INSERT INTO shop_order_notifications
         (
            order_id,
            notification_type,
            email,
            status,
            sent_at,
            failed_at,
            failure_message
         )
         VALUES
         (
            ?, ?, ?, ?,
            CASE WHEN ? = "sent" THEN UTC_TIMESTAMP() ELSE NULL END,
            CASE WHEN ? = "failed" THEN UTC_TIMESTAMP() ELSE NULL END,
            ?
         )
         ON DUPLICATE KEY UPDATE
            email = VALUES(email),
            status = VALUES(status),
            sent_at = CASE
                WHEN VALUES(status) = "sent"
                THEN UTC_TIMESTAMP()
                ELSE sent_at
            END,
            failed_at = CASE
                WHEN VALUES(status) = "failed"
                THEN UTC_TIMESTAMP()
                ELSE NULL
            END,
            failure_message = VALUES(failure_message),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $orderId,
        $type,
        $email,
        $status,
        $status,
        $status,
        $failure !== null
            ? mb_substr($failure, 0, 500)
            : null,
    ]);
}


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


function shop_notification_utc_timestamp(
    ?string $value
): ?int {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )->getTimestamp();
    } catch (Throwable) {
        return null;
    }
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
        shop_notification_utc_timestamp(
            $failedAt
        );

    if ($failedTimestamp === null) {
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


function shop_order_mail_customer_name(
    ?string $value
): string {
    $value = trim((string) $value);

    return $value !== ''
        ? $value
        : 'there';
}
