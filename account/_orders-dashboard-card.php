<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/app/shop-customer-orders.php';

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
            static function (
                array $order
            ): bool {
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

$accountOrderDetail =
    $accountOrderCount === 0
        ? 'No orders yet. View shop orders and tracking here.'
        : (
            $accountOpenOrderCount > 0
                ? number_format(
                    $accountOpenOrderCount
                )
                . ' active order'
                . (
                    $accountOpenOrderCount === 1
                        ? ''
                        : 's'
                )
                . '. View order status and tracking.'
                : number_format(
                    $accountOrderCount
                )
                . ' order'
                . (
                    $accountOrderCount === 1
                        ? ''
                        : 's'
                )
                . ' in your order history.'
        );
?>

<a
    class="account-action-card account-settings-orders"
    href="/orders.php"
>
    <i
        class="fa-solid fa-bag-shopping"
        aria-hidden="true"
    ></i>

    <span>
        <strong>
            Orders & tracking
        </strong>

        <small>
            <?= htmlspecialchars(
                $accountOrderDetail,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </small>
    </span>
</a>

<?php
require __DIR__
    . '/_support-dashboard-card.php';
?>
