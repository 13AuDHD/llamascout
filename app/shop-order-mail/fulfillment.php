<?php

declare(strict_types=1);


function shop_fulfillment_notification_type(
    int $fulfillmentId,
    string $event
): string {
    $event = strtolower(trim($event));

    if (
        !in_array(
            $event,
            [
                'shipped',
                'delivered',
            ],
            true
        )
    ) {
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

    if (
        !in_array(
            $event,
            [
                'shipped',
                'delivered',
            ],
            true
        )
    ) {
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
            o.shipping_name,
            o.payment_status,
            o.order_status
         FROM shop_order_fulfillments f
         INNER JOIN shop_orders o
            ON o.id = f.order_id
         WHERE f.id = ?
         LIMIT 1'
    );

    $stmt->execute([$fulfillmentId]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        return false;
    }

    $paymentStatus =
        strtolower(
            trim(
                (string) (
                    $row['payment_status']
                    ?? ''
                )
            )
        );

    $orderStatus =
        strtolower(
            trim(
                (string) (
                    $row['order_status']
                    ?? ''
                )
            )
        );

    if ($paymentStatus !== 'paid') {
        return false;
    }

    if (
        in_array(
            $orderStatus,
            [
                'problem',
                'cancelled',
                'canceled',
                'refunded',
            ],
            true
        )
    ) {
        return false;
    }

    $status =
        strtolower(
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
            [
                'shipped',
                'delivered',
            ],
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

    $email =
        trim(
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

    $orderNumber =
        trim(
            (string) (
                $row['order_number']
                ?? ''
            )
        );

    if ($orderNumber === '') {
        $orderNumber =
            'Llama Scout order';
    }

    $trackingUrl =
        trim(
            (string) (
                $row['tracking_url']
                ?? ''
            )
        );

    if ($trackingUrl === '') {
        $trackingUrl =
            (int) (
                $row['user_id']
                ?? 0
            ) > 0
                ? (
                    'https://account.llamascout.com/order.php?id='
                    . $orderId
                )
                : 'https://llamascout.com/shop.php';
    }

    $templateKey =
        $event === 'delivered'
            ? 'order_delivered'
            : 'order_shipped';

    $context = [
        'customer_name' =>
            shop_order_mail_customer_name(
                $row['shipping_name']
                ?? null
            ),

        'order_number' =>
            $orderNumber,

        'tracking_carrier' =>
            strtoupper(
                trim(
                    (string) (
                        $row['tracking_carrier']
                        ?? ''
                    )
                )
            ),

        'tracking_number' =>
            trim(
                (string) (
                    $row['tracking_number']
                    ?? ''
                )
            ),

        'tracking_url' =>
            $trackingUrl,
    ];

    try {
        $sent =
            llama_email_send_template(
                $db,
                $templateKey,
                $email,
                $context,
                false,
                (int) (
                    $row['user_id']
                    ?? 0
                ) ?: null
            );

        if (!$sent) {
            throw new RuntimeException(
                'Fulfillment email is disabled or the mail server rejected it.'
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
