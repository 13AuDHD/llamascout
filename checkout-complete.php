<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/public-shop.php';
require_once __DIR__ . '/app/shop-cart.php';
require_once __DIR__ . '/app/shop-checkout.php';

$db = db();
$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$order = null;
$items = [];
$pageError = '';

try {
    shop_checkout_ensure_storage($db);
    if ($sessionId === '') {
        throw new RuntimeException('That checkout session is missing.');
    }

    $order = shop_checkout_order_by_session($db, $sessionId);
    if (!$order) {
        throw new RuntimeException('That Shop order could not be found.');
    }

    $items = shop_checkout_order_items($db, (int) $order['id']);

    if ((string) ($order['payment_status'] ?? '') === 'paid') {
        shop_cart_clear();
        shop_cart_clear_undo();
        unset($_SESSION['shop_checkout_order_id']);
    }
} catch (Throwable $exception) {
    http_response_code(400);
    $pageError = $exception->getMessage();
}

$pageTitle = 'Order Confirmation | Llama Scout Shop';
$pageDescription = 'Llama Scout Shop order confirmation.';
$canonicalUrl = $siteUrl . '/checkout-complete.php';
require __DIR__ . '/partials/header.php';
?>

<section class="shop-page shop-order-confirmation">
<?php if ($pageError !== ''): ?>
    <section class="shop-checkout-error" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
            <h1>We could not load that order.</h1>
            <p><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></p>
            <a class="shop-primary-button" href="/cart.php">Return to cart</a>
        </div>
    </section>
<?php else: ?>
    <?php
    $paid = (string) ($order['payment_status'] ?? '') === 'paid';
    $failed = in_array((string) ($order['payment_status'] ?? ''), ['failed','canceled','cancelled'], true);
    ?>
    <header class="shop-order-confirmation-heading">
        <div class="shop-order-confirmation-icon<?= $paid ? ' is-paid' : ($failed ? ' is-failed' : ' is-pending') ?>">
            <i class="fa-solid <?= $paid ? 'fa-check' : ($failed ? 'fa-xmark' : 'fa-clock') ?>" aria-hidden="true"></i>
        </div>
        <p class="eyebrow">Order <?= htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= $paid ? 'Your order is confirmed.' : ($failed ? 'Payment was not completed.' : 'We are confirming your payment.') ?></h1>
        <p>
            <?php if ($paid): ?>
                Stripe confirmed your payment. The Llama thanks you for your order.
            <?php elseif ($failed): ?>
                No paid order was created. You can return to your cart and try checkout again.
            <?php else: ?>
                Stripe has returned you to Llama Scout, but the payment webhook has not finished confirming the order yet. Refresh this page in a moment.
            <?php endif; ?>
        </p>
    </header>

    <section class="shop-order-confirmation-card">
        <div class="shop-order-confirmation-row">
            <span>Order</span>
            <strong><?= htmlspecialchars((string) $order['order_number'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div class="shop-order-confirmation-row">
            <span>Payment</span>
            <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $order['payment_status'])), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div class="shop-order-confirmation-row">
            <span>Total</span>
            <strong><?= htmlspecialchars(public_shop_money((int) ($order['total_cents'] ?? $order['subtotal_cents'] ?? 0), (string) ($order['currency'] ?? 'usd')), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    </section>

    <div class="shop-order-confirmation-actions">
        <?php if (!$paid): ?>
        <a class="shop-primary-button" href="/cart.php">Return to cart</a>
        <?php else: ?>
        <a class="shop-primary-button" href="/shop.php">Continue shopping</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
