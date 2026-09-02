<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/public-shop.php';
require_once __DIR__ . '/app/shop-cart.php';
require_once __DIR__ . '/app/shop-checkout.php';

$db = db();
$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');

$items = shop_cart_detailed_items($db);
if (!$items) {
    header('Location: /cart.php', true, 303);
    exit;
}

$subtotal = shop_cart_subtotal($items);
$account = shop_checkout_current_account($db);
$checkoutError = '';
$clientSecret = '';
$publishableKey = '';
$order = null;

try {
    shop_checkout_ensure_storage($db);
    $publishableKey = shop_checkout_publishable_key();

    $existingOrderId = (int) ($_SESSION['shop_checkout_order_id'] ?? 0);
    if ($existingOrderId > 0) {
        $existingOrder = shop_checkout_order($db, $existingOrderId);
        if (
            $existingOrder
            && (string) ($existingOrder['payment_status'] ?? '') === 'pending'
            && (string) ($existingOrder['order_status'] ?? '') === 'pending'
            && !empty($existingOrder['stripe_checkout_session_id'])
            && strtotime((string) ($existingOrder['checkout_expires_at'] ?? '')) > time()
        ) {
            try {
                $existingSession = llama_stripe_client()->checkout->sessions->retrieve(
                    (string) $existingOrder['stripe_checkout_session_id'],
                    []
                );
                if (
                    strtolower((string) ($existingSession->status ?? '')) === 'open'
                    && !empty($existingSession->client_secret)
                ) {
                    $order = $existingOrder;
                    $clientSecret = (string) $existingSession->client_secret;
                }
            } catch (Throwable) {
                // A stale Stripe session is replaced below.
            }
        }
    }

    if (!$order || $clientSecret === '') {
        if ($existingOrderId > 0) {
            $stale = shop_checkout_order($db, $existingOrderId);
            if ($stale && (string) ($stale['payment_status'] ?? '') !== 'paid') {
                shop_checkout_cancel_pending_order($db, $existingOrderId);
            }
        }

        $expiresAt = time() + 1800;
        $order = shop_checkout_create_pending_order($db, $items, $account, $expiresAt);

        try {
            $session = shop_checkout_create_stripe_session(
                $db,
                $order,
                $items,
                $account,
                $siteUrl . '/checkout-complete.php?session_id={CHECKOUT_SESSION_ID}',
                $expiresAt
            );
            $clientSecret = (string) $session->client_secret;
            $_SESSION['shop_checkout_order_id'] = (int) $order['id'];
        } catch (Throwable $exception) {
            shop_checkout_cancel_pending_order($db, (int) $order['id']);
            unset($_SESSION['shop_checkout_order_id']);
            throw $exception;
        }
    }
} catch (Throwable $exception) {
    http_response_code(500);
    if (function_exists('llama_log_caught_exception')) {
        $reference = llama_log_caught_exception(
            $exception,
            'shop.checkout_start',
            [
                'user_id' => $account ? (int) $account['id'] : null,
                'cart_count' => shop_cart_count(),
            ]
        );
        $checkoutError = function_exists('llama_error_message_with_reference')
            ? llama_error_message_with_reference('Secure Shop checkout could not start.', $reference)
            : 'Secure Shop checkout could not start. Error reference: ' . $reference;
    } else {
        error_log('Llama Scout Shop checkout: ' . $exception->getMessage());
        $checkoutError = 'Secure Shop checkout could not start.';
    }
}

$pageTitle = 'Checkout | Llama Scout Shop';
$pageDescription = 'Securely complete your Llama Scout Shop order.';
$canonicalUrl = $siteUrl . '/checkout.php';

require __DIR__ . '/partials/header.php';
?>

<section
    class="shop-page shop-checkout-page"
    id="shop-checkout"
    data-stripe-publishable-key="<?= htmlspecialchars($publishableKey, ENT_QUOTES, 'UTF-8') ?>"
    data-checkout-client-secret="<?= htmlspecialchars($clientSecret, ENT_QUOTES, 'UTF-8') ?>"
>

<header class="shop-checkout-heading">
    <a class="shop-back-link" href="/cart.php">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Return to cart
    </a>

    <p class="eyebrow">Secure checkout</p>
    <h1>Complete your order.</h1>
    <p>
        You stay on Llama Scout while Stripe securely handles payment details.
        Llama Scout never receives or stores your full card number.
    </p>
</header>

<?php if ($checkoutError !== ''): ?>
<section class="shop-checkout-error" role="alert">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    <div>
        <h2>Checkout could not start.</h2>
        <p><?= htmlspecialchars($checkoutError, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="shop-primary-button" href="/cart.php">Return to cart</a>
    </div>
</section>
<?php else: ?>

<div class="shop-checkout-layout">
    <aside class="shop-checkout-summary">
        <p class="eyebrow">Order summary</p>
        <h2><?= htmlspecialchars((string) ($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>

        <div class="shop-checkout-items">
            <?php foreach ($items as $item): ?>
            <article class="shop-checkout-item">
                <?php
                $image = public_shop_image_url((string) ($item['image_url'] ?? ''), $siteUrl);
                ?>
                <div class="shop-checkout-item-image">
                    <?php if ($image !== ''): ?>
                    <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php else: ?>
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="shop-checkout-item-copy">
                    <strong><?= htmlspecialchars((string) ($item['product_name'] ?? 'Shop item'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (trim((string) ($item['name'] ?? '')) !== ''): ?>
                    <span><?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <small>Qty <?= (int) ($item['quantity'] ?? 1) ?></small>
                </div>
                <strong class="shop-checkout-item-price">
                    <?= htmlspecialchars(
                        public_shop_money(
                            (int) ($item['line_total_cents'] ?? 0),
                            (string) ($item['currency'] ?? 'usd')
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="shop-checkout-total-row">
            <span>Items subtotal</span>
            <strong><?= htmlspecialchars(public_shop_money($subtotal), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <p class="shop-checkout-summary-note">
            Shipping details and applicable tax are finalized inside secure checkout.
        </p>

        <div class="shop-checkout-secure-note">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <span>Payment information is handled securely by Stripe.</span>
        </div>
    </aside>

    <section class="shop-checkout-payment">
        <header>
            <p class="eyebrow">Payment</p>
            <h2>Secure payment</h2>
        </header>

        <div id="stripe-shop-checkout" class="stripe-shop-checkout">
            <div id="shop-checkout-loading" class="shop-checkout-loading">
                <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                <span>Loading secure checkout...</span>
            </div>
        </div>

        <div id="shop-checkout-mount-error" class="shop-checkout-mount-error" hidden>
            Secure checkout could not be displayed. No payment was created.
            Return to your cart and try again.
        </div>
    </section>
</div>

<?php endif; ?>
</section>

<?php if ($checkoutError === ''): ?>
<script src="https://js.stripe.com/dahlia/stripe.js"></script>
<script src="/js/shop-checkout.js" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
