<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/shop-customer-orders.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$email = trim((string) ($user['email'] ?? ''));
$db = db();

$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId < 1) {
    header('Location: /orders.php');
    exit;
}

$order = shop_customer_order(
    $db,
    $orderId,
    $userId,
    $email
);

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order Not Found | Llama Scout';

    require dirname(__DIR__) . '/partials/header.php';
    ?>
    <section class="account-page account-orders-page">
        <section class="account-orders-empty">
            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
            <h1>Order not found</h1>
            <p>
                This order is not associated with your account.
            </p>
            <a href="/orders.php">Back to your orders</a>
        </section>
    </section>
    <?php
    require dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$items = shop_customer_order_items(
    $db,
    $orderId
);

$fulfillments = shop_customer_fulfillments(
    $db,
    $orderId
);

$shippingLines = shop_customer_address_lines(
    (string) ($order['shipping_address_json'] ?? '')
);

$config = llama_config();

$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$pageTitle =
    (string) $order['order_number'] .
    ' | Llama Scout';

require dirname(__DIR__) . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="<?= htmlspecialchars(
        $siteUrl . '/css/account-orders.css',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<section class="account-page account-orders-page">

<header class="account-orders-header">
    <div>
        <p class="account-eyebrow">Order</p>
        <h1>
            <?= htmlspecialchars(
                (string) $order['order_number'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>
        <p>
            <?= htmlspecialchars(
                shop_customer_status_label(
                    (string) $order['order_status']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    </div>

    <a class="account-orders-back" href="/orders.php">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Orders
    </a>
</header>


<section class="account-order-status-card">
    <div>
        <span>Order status</span>
        <strong>
            <?= htmlspecialchars(
                shop_customer_status_label(
                    (string) $order['order_status']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </div>

    <div>
        <span>Payment</span>
        <strong>
            <?= htmlspecialchars(
                ucfirst(
                    (string) $order['payment_status']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </div>

    <div>
        <span>Total</span>
        <strong>
            <?= htmlspecialchars(
                shop_customer_money(
                    (int) $order['total_cents'],
                    (string) $order['currency']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </div>
</section>


<div class="account-order-detail-grid">

<div class="account-order-detail-main">

<section class="account-order-panel">

<header>
    <p class="account-eyebrow">Order</p>
    <h2>Items</h2>
</header>

<div class="account-order-items">

<?php foreach ($items as $item): ?>

<article>
    <div class="account-order-item-image">
        <?php if (!empty($item['image_url'])): ?>
            <img
                src="<?= htmlspecialchars(
                    str_starts_with(
                        (string) $item['image_url'],
                        'http'
                    )
                        ? (string) $item['image_url']
                        : $siteUrl . '/' . ltrim(
                            (string) $item['image_url'],
                            '/'
                        ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                alt=""
            >
        <?php else: ?>
            <i class="fa-solid fa-box" aria-hidden="true"></i>
        <?php endif; ?>
    </div>

    <div class="account-order-item-copy">
        <strong>
            <?= htmlspecialchars(
                (string) $item['product_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <?php if (!empty($item['variant_name'])): ?>
        <span>
            <?= htmlspecialchars(
                (string) $item['variant_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>
        <?php endif; ?>

        <small>
            Qty <?= (int) $item['quantity'] ?>
            <?php if (!empty($item['sku'])): ?>
                | <?= htmlspecialchars(
                    (string) $item['sku'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            <?php endif; ?>
        </small>
    </div>

    <strong class="account-order-item-price">
        <?= htmlspecialchars(
            shop_customer_money(
                (int) $item['line_total_cents'],
                (string) $item['currency']
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>
</article>

<?php endforeach; ?>

</div>

</section>


<?php if ($fulfillments): ?>

<section class="account-order-panel">

<header>
    <p class="account-eyebrow">Shipping</p>
    <h2>Delivery</h2>
</header>

<div class="account-order-fulfillments">

<?php foreach ($fulfillments as $fulfillment): ?>

<article>
    <div class="account-order-fulfillment-heading">
        <div>
            <span>Fulfillment</span>
            <strong>
                <?= htmlspecialchars(
                    shop_customer_status_label(
                        (string) $fulfillment['status']
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>
        </div>

        <?php if (!empty($fulfillment['tracking_number'])): ?>
        <span class="account-order-tracking-carrier">
            <?= htmlspecialchars(
                shop_customer_tracking_label(
                    (string) ($fulfillment['tracking_carrier'] ?? '')
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (!empty($fulfillment['tracking_number'])): ?>

    <div class="account-order-tracking">
        <span>Tracking number</span>
        <strong>
            <?= htmlspecialchars(
                (string) $fulfillment['tracking_number'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <?php if (!empty($fulfillment['tracking_url'])): ?>
        <a
            href="<?= htmlspecialchars(
                (string) $fulfillment['tracking_url'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Track package
            <i
                class="fa-solid fa-arrow-up-right-from-square"
                aria-hidden="true"
            ></i>
        </a>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <dl class="account-order-timeline">
        <div>
            <dt>Processing</dt>
            <dd>
                <?= htmlspecialchars(
                    (string) (
                        $fulfillment['submitted_at']
                        ?: 'Not yet'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Shipped</dt>
            <dd>
                <?= htmlspecialchars(
                    (string) (
                        $fulfillment['shipped_at']
                        ?: 'Not yet'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </dd>
        </div>

        <div>
            <dt>Delivered</dt>
            <dd>
                <?= htmlspecialchars(
                    (string) (
                        $fulfillment['delivered_at']
                        ?: 'Not yet'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </dd>
        </div>
    </dl>
</article>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>

</div>


<aside class="account-order-detail-side">

<section class="account-order-panel">

<header>
    <p class="account-eyebrow">Receipt</p>
    <h2>Order total</h2>
</header>

<dl class="account-order-totals">
    <div>
        <dt>Subtotal</dt>
        <dd>
            <?= htmlspecialchars(
                shop_customer_money(
                    (int) $order['subtotal_cents'],
                    (string) $order['currency']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </dd>
    </div>

    <div>
        <dt>Shipping</dt>
        <dd>
            <?= htmlspecialchars(
                shop_customer_money(
                    (int) $order['shipping_cents'],
                    (string) $order['currency']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </dd>
    </div>

    <div>
        <dt>Tax</dt>
        <dd>
            <?= htmlspecialchars(
                shop_customer_money(
                    (int) $order['tax_cents'],
                    (string) $order['currency']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </dd>
    </div>

    <?php if ((int) $order['discount_cents'] > 0): ?>
    <div>
        <dt>Discount</dt>
        <dd>
            -<?= htmlspecialchars(
                shop_customer_money(
                    (int) $order['discount_cents'],
                    (string) $order['currency']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </dd>
    </div>
    <?php endif; ?>

    <div class="is-total">
        <dt>Total</dt>
        <dd>
            <?= htmlspecialchars(
                shop_customer_money(
                    (int) $order['total_cents'],
                    (string) $order['currency']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </dd>
    </div>
</dl>

</section>


<section class="account-order-panel">

<header>
    <p class="account-eyebrow">Shipping</p>
    <h2>Ship to</h2>
</header>

<div class="account-order-address">
    <?php if (!empty($order['shipping_name'])): ?>
    <strong>
        <?= htmlspecialchars(
            (string) $order['shipping_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>
    <?php endif; ?>

    <?php foreach ($shippingLines as $line): ?>
    <span>
        <?= htmlspecialchars(
            $line,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </span>
    <?php endforeach; ?>

    <?php if (!$shippingLines): ?>
    <span>Shipping address unavailable.</span>
    <?php endif; ?>
</div>

</section>

</aside>

</div>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
