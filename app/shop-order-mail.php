<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';


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
            updated_at = CURRENT_TIMESTAMP'
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

    /*
     * Both checkout.session.completed and
     * checkout.session.async_payment_succeeded can represent a
     * successful payment. Use a short database lock so two webhook
     * requests cannot send the same confirmation simultaneously.
     */
    $lockName = 'llamascout_shop_order_mail_' . $orderId;

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
                customer_email,
                shipping_name,
                paid_at
             FROM shop_orders
             WHERE id = ?
             LIMIT 1'
        );
        $orderStmt->execute([$orderId]);

        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException(
                'Shop order not found.'
            );
        }

        if (
            strtolower(
                trim((string) ($order['payment_status'] ?? ''))
            ) !== 'paid'
        ) {
            return false;
        }

        $email = trim(
            (string) ($order['customer_email'] ?? '')
        );

        if ($email === '') {
            throw new RuntimeException(
                'Paid Shop order does not have a customer email address.'
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

        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $orderNumber = trim(
            (string) ($order['order_number'] ?? '')
        );

        $currency = strtolower(
            trim((string) ($order['currency'] ?? 'usd'))
        ) ?: 'usd';

        $customerName = trim(
            (string) ($order['shipping_name'] ?? '')
        );

        $greeting = $customerName !== ''
            ? 'Hi ' . $customerName . ','
            : 'Hi,';

        $subject =
            'Order confirmed: '
            . ($orderNumber !== '' ? $orderNumber : 'Llama Scout order');

        $itemText = [];
        $itemHtml = '';

        foreach ($items as $item) {
            $productName = trim(
                (string) ($item['product_name'] ?? 'Shop item')
            );

            $variantName = trim(
                (string) ($item['variant_name'] ?? '')
            );

            $quantity = max(
                1,
                (int) ($item['quantity'] ?? 1)
            );

            $label = $productName;

            if (
                $variantName !== ''
                && strcasecmp($variantName, 'Standard') !== 0
            ) {
                $label .= ' - ' . $variantName;
            }

            $lineTotal = shop_order_mail_money(
                (int) ($item['line_total_cents'] ?? 0),
                (string) ($item['currency'] ?? $currency)
            );

            $itemText[] =
                $quantity
                . ' x '
                . $label
                . '  '
                . $lineTotal;

            $itemHtml .=
                '<tr>'
                . '<td style="padding:10px 0;border-bottom:1px solid #ecece8;">'
                . htmlspecialchars($quantity . ' x ' . $label, ENT_QUOTES, 'UTF-8')
                . '</td>'
                . '<td style="padding:10px 0;border-bottom:1px solid #ecece8;text-align:right;font-weight:bold;">'
                . htmlspecialchars($lineTotal, ENT_QUOTES, 'UTF-8')
                . '</td>'
                . '</tr>';
        }

        $subtotal = shop_order_mail_money(
            (int) ($order['subtotal_cents'] ?? 0),
            $currency
        );

        $shipping = shop_order_mail_money(
            (int) ($order['shipping_cents'] ?? 0),
            $currency
        );

        $tax = shop_order_mail_money(
            (int) ($order['tax_cents'] ?? 0),
            $currency
        );

        $discount = shop_order_mail_money(
            (int) ($order['discount_cents'] ?? 0),
            $currency
        );

        $total = shop_order_mail_money(
            (int) ($order['total_cents'] ?? 0),
            $currency
        );

        $orderUrl = '';

        if ((int) ($order['user_id'] ?? 0) > 0) {
            $orderUrl =
                'https://account.llamascout.com/order.php?id='
                . $orderId;
        }

        $text =
            $greeting
            . "\n\nThanks for your order. Your payment has been confirmed."
            . "\n\nOrder: "
            . $orderNumber
            . "\n\n"
            . implode("\n", $itemText)
            . "\n\nSubtotal: "
            . $subtotal
            . "\nShipping: "
            . $shipping
            . "\nTax: "
            . $tax;

        if ((int) ($order['discount_cents'] ?? 0) > 0) {
            $text .= "\nDiscount: -" . $discount;
        }

        $text .=
            "\nTotal: "
            . $total
            . "\n\nWe will send another update when shipping information is available.";

        if ($orderUrl !== '') {
            $text .=
                "\n\nView your order:\n"
                . $orderUrl;
        }

        $text .=
            "\n\nLlama Scout\n"
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

        $safeSubtotal = htmlspecialchars(
            $subtotal,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeShipping = htmlspecialchars(
            $shipping,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeTax = htmlspecialchars(
            $tax,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeDiscount = htmlspecialchars(
            $discount,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeTotal = htmlspecialchars(
            $total,
            ENT_QUOTES,
            'UTF-8'
        );

        $orderButton = '';

        if ($orderUrl !== '') {
            $safeOrderUrl = htmlspecialchars(
                $orderUrl,
                ENT_QUOTES,
                'UTF-8'
            );

            $orderButton =
                '<p style="margin:28px 0 0;">'
                . '<a href="' . $safeOrderUrl . '" '
                . 'style="display:inline-block;background:#172822;color:#ffffff;padding:13px 20px;border-radius:9px;text-decoration:none;font-weight:bold;">'
                . 'View your order'
                . '</a>'
                . '</p>';
        }

        $discountRow = '';

        if ((int) ($order['discount_cents'] ?? 0) > 0) {
            $discountRow =
                '<tr>'
                . '<td style="padding:5px 0;">Discount</td>'
                . '<td style="padding:5px 0;text-align:right;">-' . $safeDiscount . '</td>'
                . '</tr>';
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
<h1 style="margin:0 0 20px;font-size:28px;line-height:1.2;">Order confirmed</h1>

<p style="margin:0 0 16px;line-height:1.6;">{$safeGreeting}</p>
<p style="margin:0 0 24px;line-height:1.6;">
Thanks for your order. Your payment has been confirmed.
</p>

<div style="padding:14px 16px;background:#f7f7f3;border-radius:10px;margin-bottom:24px;">
<strong>Order {$safeOrderNumber}</strong>
</div>

<table role="presentation" style="width:100%;border-collapse:collapse;margin-bottom:24px;">
{$itemHtml}
</table>

<table role="presentation" style="width:100%;border-collapse:collapse;">
<tr>
<td style="padding:5px 0;">Subtotal</td>
<td style="padding:5px 0;text-align:right;">{$safeSubtotal}</td>
</tr>
<tr>
<td style="padding:5px 0;">Shipping</td>
<td style="padding:5px 0;text-align:right;">{$safeShipping}</td>
</tr>
<tr>
<td style="padding:5px 0;">Tax</td>
<td style="padding:5px 0;text-align:right;">{$safeTax}</td>
</tr>
{$discountRow}
<tr>
<td style="padding:12px 0 0;font-weight:bold;font-size:18px;">Total</td>
<td style="padding:12px 0 0;text-align:right;font-weight:bold;font-size:18px;">{$safeTotal}</td>
</tr>
</table>

<p style="margin:26px 0 0;line-height:1.6;color:#52605a;">
We will send another update when shipping information is available.
</p>

{$orderButton}

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
                    'Mail server rejected the order confirmation.'
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
        $releaseStmt->execute([$lockName]);
    }
}
