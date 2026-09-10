<?php

declare(strict_types=1);


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

    $order =
        shop_order_mail_customer(
            $db,
            $orderId
        );

    if (!$order) {
        return false;
    }

    $paymentStatus =
        strtolower(
            trim(
                (string) (
                    $order['payment_status']
                    ?? ''
                )
            )
        );

    $orderStatus =
        strtolower(
            trim(
                (string) (
                    $order['order_status']
                    ?? ''
                )
            )
        );

    if (
        $paymentStatus !== 'refunded'
        || $orderStatus !== 'refunded'
    ) {
        return false;
    }

    $email =
        trim(
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

    $orderNumber =
        trim(
            (string) (
                $order['order_number']
                ?? ''
            )
        );

    if ($orderNumber === '') {
        $orderNumber =
            'Llama Scout order';
    }

    $context = [
        'customer_name' =>
            shop_order_mail_customer_name(
                $order['shipping_name']
                ?? null
            ),

        'order_number' =>
            $orderNumber,

        'refund_amount' =>
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
    ];

    try {
        $sent =
            llama_email_send_template(
                $db,
                'refund_confirmation',
                $email,
                $context,
                false,
                (int) (
                    $order['user_id']
                    ?? 0
                ) ?: null
            );

        if (!$sent) {
            throw new RuntimeException(
                'Refund confirmation email is disabled or the mail server rejected it.'
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
