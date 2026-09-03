<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/shop-customer-orders.php';

require_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$email = trim((string) ($user['email'] ?? ''));
$db = db();

$orders = shop_customer_orders(
    $db,
    $userId,
    $email
);

$config = llama_config();

$siteUrl = rtrim(
    (string) ($config['app']['url'] ?? 'https://llamascout.com'),
    '/'
);

$pageTitle = 'Your Orders | Llama Scout';

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
        <p class="account-eyebrow">Shop</p>
        <h1>Your Orders</h1>
        <p>
            Purchases made while signed in, plus guest purchases
            made with this account's email address.
        </p>
    </div>

    <a class="account-orders-back" href="/">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Account
    </a>
</header>

<?php if (!$orders): ?>

<section class="account-orders-empty">
    <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
    <h2>No orders yet</h2>
    <p>
        When you buy something from the Llama Scout Shop,
        your order history will appear here.
    </p>
    <a href="<?= htmlspecialchars(
        $siteUrl . '/shop.php',
        ENT_QUOTES,
        'UTF-8'
    ) ?>">
        Visit the Shop
    </a>
</section>

<?php else: ?>

<div class="account-orders-list">

<?php foreach ($orders as $order): ?>

<a
    class="account-order-card"
    href="/order.php?id=<?= (int) $order['id'] ?>"
>

<div class="account-order-card-main">
    <div class="account-order-card-icon">
        <i class="fa-solid fa-box" aria-hidden="true"></i>
    </div>

    <div>
        <span>
            <?= htmlspecialchars(
                (string) $order['order_number'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </span>

        <strong>
            <?= htmlspecialchars(
                shop_customer_status_label(
                    (string) $order['order_status']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

        <small>
            <?= htmlspecialchars(
                (string) (
                    $order['paid_at']
                    ?: $order['created_at']
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
            |
            <?= number_format(
                (int) $order['unit_count']
            ) ?>
            item<?= (int) $order['unit_count'] === 1 ? '' : 's' ?>
        </small>
    </div>
</div>

<div class="account-order-card-total">
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

    <i
        class="fa-solid fa-chevron-right"
        aria-hidden="true"
    ></i>
</div>

</a>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
