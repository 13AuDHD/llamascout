<?php

declare(strict_types=1);


function shop_send_order_confirmation(
    PDO $db,
    int $orderId
): bool {
    if ($orderId < 1) {
        throw new InvalidArgumentException(
            'A valid Shop order is required.'
        );
    }

    if (!shop_order_notification_table_exists($db)) {
        throw new RuntimeException(
            'Shop order notification database migration is missing.'
        );
    }

    $lockName =
        'llamascout_shop_order_mail_'
        . $orderId;

    $lockStmt = $db->prepare(
        'SELECT GET_LOCK(?, 5)'
    );

    $lockStmt->execute([$lockName]);

    if ((int) $lockStmt->fetchColumn() !== 1) {
        throw new RuntimeException(
            'Could not acquire the order-email lock.'
        );
    }

    try {
        if (shop_order_confirmation_sent($db, $orderId)) {
            return false;
        }

        $orderStmt = $db->prepare(
            'SELECT
                id,
                order_number,
                user_id,
                currency,
                subtotal_cents,
                shipping_cents,
                tax_cents,
                discount_cents,
                total_cents,
                payment_status,
                order_status,
                customer_email,
                shipping_name,
                paid_at
             FROM shop_orders
             WHERE id = ?
             LIMIT 1'
        );

        $orderStmt->execute([$orderId]);

        $order =
            $orderStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$order) {
            throw new RuntimeException(
                'Shop order not found.'
            );
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
            throw new RuntimeException(
                'Paid Shop order does not have a valid customer email address.'
            );
        }

        $itemsStmt = $db->prepare(
            'SELECT
                product_name,
                variant_name,
                sku,
                quantity,
                unit_price_cents,
                line_total_cents,
                currency
             FROM shop_order_items
             WHERE order_id = ?
             ORDER BY id ASC'
        );

        $itemsStmt->execute([$orderId]);

        $items =
            $itemsStmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $orderNumber =
            trim(
                (string) (
                    $order['order_number']
                    ?? ''
                )
            );

        if ($orderNumber === '') {
            $orderNumber = 'Llama Scout order';
        }

        $currency =
            strtolower(
                trim(
                    (string) (
                        $order['currency']
                        ?? 'usd'
                    )
                )
            )
            ?: 'usd';

        $itemLines = [];

        foreach ($items as $item) {
            $productName =
                trim(
                    (string) (
                        $item['product_name']
                        ?? 'Shop item'
                    )
                );

            $variantName =
                trim(
                    (string) (
                        $item['variant_name']
                        ?? ''
                    )
                );

            $quantity =
                max(
                    1,
                    (int) (
                        $item['quantity']
                        ?? 1
                    )
                );

            $label = $productName;

            if (
                $variantName !== ''
                && strcasecmp(
                    $variantName,
                    'Standard'
                ) !== 0
            ) {
                $label .=
                    ' - '
                    . $variantName;
            }

            $itemLines[] =
                $quantity
                . ' x '
                . $label
                . '  '
                . shop_order_mail_money(
                    (int) (
                        $item['line_total_cents']
                        ?? 0
                    ),
                    (string) (
                        $item['currency']
                        ?? $currency
                    )
                );
        }

        $discountCents =
            (int) (
                $order['discount_cents']
                ?? 0
            );

        $orderActionUrl =
            (int) (
                $order['user_id']
                ?? 0
            ) > 0
                ? (
                    'https://account.llamascout.com/order.php?id='
                    . $orderId
                )
                : 'https://llamascout.com/shop.php';

        $orderActionLabel =
            (int) (
                $order['user_id']
                ?? 0
            ) > 0
                ? 'View your order'
                : 'Visit Llama Scout Shop';

        $context = [
            'customer_name' =>
                shop_order_mail_customer_name(
                    $order['shipping_name']
                    ?? null
                ),

            'order_number' =>
                $orderNumber,

            'order_items' =>
                $itemLines
                    ? implode("\n", $itemLines)
                    : 'Your Shop items',

            'subtotal' =>
                shop_order_mail_money(
                    (int) (
                        $order['subtotal_cents']
                        ?? 0
                    ),
                    $currency
                ),

            'shipping' =>
                shop_order_mail_money(
                    (int) (
                        $order['shipping_cents']
                        ?? 0
                    ),
                    $currency
                ),

            'tax' =>
                shop_order_mail_money(
                    (int) (
                        $order['tax_cents']
                        ?? 0
                    ),
                    $currency
                ),

            'discount_line' =>
                $discountCents > 0
                    ? (
                        'Discount: -'
                        . shop_order_mail_money(
                            $discountCents,
                            $currency
                        )
                    )
                    : '',

            'total' =>
                shop_order_mail_money(
                    (int) (
                        $order['total_cents']
                        ?? 0
                    ),
                    $currency
                ),

            'order_action_url' =>
                $orderActionUrl,

            'order_action_label' =>
                $orderActionLabel,
        ];

        try {
            $sent =
                llama_email_send_template(
                    $db,
                    'order_confirmation',
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
                    'Order confirmation email is disabled or the mail server rejected it.'
                );
            }

            shop_order_record_notification(
                $db,
                $orderId,
                'order_confirmation',
                $email,
                'sent'
            );

            return true;

        } catch (Throwable $exception) {
            shop_order_record_notification(
                $db,
                $orderId,
                'order_confirmation',
                $email,
                'failed',
                $exception->getMessage()
            );

            throw $exception;
        }

    } finally {
        $releaseStmt = $db->prepare(
            'SELECT RELEASE_LOCK(?)'
        );

        $releaseStmt->execute([
            $lockName,
        ]);
    }
}
