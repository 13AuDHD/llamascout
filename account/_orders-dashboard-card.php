<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/shop-customer-orders.php';

$accountOrderEmail =
    trim(
        (string) (
            $user['email']
            ?? ''
        )
    );

$accountOrders =
    shop_customer_orders(
        $db,
        $userId,
        $accountOrderEmail
    );

$accountOrderCount =
    count($accountOrders);

$accountOpenOrderCount =
    count(
        array_filter(
            $accountOrders,
            static function (array $order): bool {
                $status =
                    strtolower(
                        trim(
                            (string) (
                                $order['order_status']
                                ?? ''
                            )
                        )
                    );

                return !in_array(
                    $status,
                    [
                        'delivered',
                        'cancelled',
                        'canceled',
                        'refunded',
                    ],
                    true
                );
            }
        )
    );

$accountLatestOrder =
    $accountOrders[0]
    ?? null;

$accountOrderSummary =
    'No orders yet';

if ($accountOrderCount > 0) {
    if ($accountOpenOrderCount > 0) {
        $accountOrderSummary =
            number_format(
                $accountOpenOrderCount
            ) .
            ' active order' .
            (
                $accountOpenOrderCount === 1
                    ? ''
                    : 's'
            );
    } else {
        $accountOrderSummary =
            number_format(
                $accountOrderCount
            ) .
            ' order' .
            (
                $accountOrderCount === 1
                    ? ''
                    : 's'
            );
    }
}
?>

<a
    class="account-glance-card account-glance-link account-glance-orders"
    href="/orders.php"
>
    <span class="account-glance-icon">
        <i
            class="fa-solid fa-bag-shopping"
            aria-hidden="true"
        ></i>
    </span>

    <div>
        <strong>
            <?= htmlspecialchars(
                $accountOrderSummary,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <span>
            <?php if ($accountLatestOrder): ?>
                Latest:
                <?= htmlspecialchars(
                    (string) $accountLatestOrder['order_number'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            <?php else: ?>
                Shop orders &amp; tracking
            <?php endif; ?>
        </span>
    </div>

    <i
        class="fa-solid fa-chevron-right"
        aria-hidden="true"
    ></i>
</a>

<?php
$accountSupportCard =
    __DIR__ . '/_support-dashboard-card.php';

if (is_file($accountSupportCard)) {
    require $accountSupportCard;
}
?>
