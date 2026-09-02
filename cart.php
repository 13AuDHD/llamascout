<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/public-shop.php';
require_once __DIR__ . '/app/shop-cart.php';

$db = db();

$config = llama_config();

$siteUrl =
    rtrim(
        (string) (
            $config['app']['url']
            ?? 'https://llamascout.com'
        ),
        '/'
    );

$error = '';
$removedNotice = false;
$restoredNotice = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $refererPath = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
    $comingFromCart = $refererPath === '/cart.php';

    if (!isset($_GET['removed']) && !isset($_GET['restored']) && !$comingFromCart) {
        shop_cart_clear_undo();
    }
}

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {
    try {
        if (
            !shop_cart_verify_csrf(
                (string) (
                    $_POST['csrf_token']
                    ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $action =
            trim(
                (string) (
                    $_POST['cart_action']
                    ?? ''
                )
            );

        $variantId =
            (int) (
                $_POST['variant_id']
                ?? 0
            );

        $redirect = '/cart.php';

        if ($action === 'update') {
            shop_cart_update(
                $db,
                $variantId,
                (int) (
                    $_POST['quantity']
                    ?? 1
                )
            );
        } elseif ($action === 'remove') {
            $removed = shop_cart_remove($variantId);
            if ($removed) {
                $redirect = '/cart.php?removed=1';
            }
        } elseif ($action === 'undo-remove') {
            shop_cart_restore_removed($db);
            $redirect = '/cart.php?restored=1';
        } elseif ($action === 'clear') {
            shop_cart_clear();
            shop_cart_clear_undo();
        }

        header(
            'Location: ' . $redirect,
            true,
            303
        );

        exit;
    } catch (Throwable $exception) {
        $error =
            $exception->getMessage();
    }
}

$items =
    shop_cart_detailed_items(
        $db
    );

$subtotal =
    shop_cart_subtotal(
        $items
    );

$pageTitle =
    'Cart | Llama Scout Shop';

$pageDescription =
    'Review the items in your Llama Scout shopping cart.';

$canonicalUrl =
    $siteUrl . '/cart.php';

require __DIR__ . '/partials/header.php';
?>

<section class="shop-page cart-page">

<header class="cart-header">

<div>
    <p class="eyebrow">
        Llama Scout Shop
    </p>

    <h1>Your cart</h1>
</div>

<a
    class="shop-back-link"
    href="/shop.php"
>
    Continue shopping
</a>

</header>


<?php if (
    isset($_GET['added'])
): ?>

<div class="product-cart-message is-success">
    Added to your cart.
</div>

<?php endif; ?>

<?php if (isset($_GET['removed']) && shop_cart_undo_available()): ?>

<div class="product-cart-message is-error cart-removal-message">
    <span>Item removed from your cart.</span>

    <form method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(shop_cart_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
        >
        <button
            type="submit"
            name="cart_action"
            value="undo-remove"
            class="cart-undo-button"
        >
            Undo
        </button>
    </form>
</div>

<?php endif; ?>

<?php if (isset($_GET['restored'])): ?>

<div class="product-cart-message is-success">
    Item restored to your cart.
</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="product-cart-message is-error">
    <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

<?php endif; ?>


<?php if (!$items): ?>

<section class="shop-empty">

<i
    class="fa-solid fa-cart-shopping"
    aria-hidden="true"
></i>

<h2>Your cart is empty.</h2>

<p>
    Browse the Shop and add something llama-approved.
</p>

<a
    class="shop-primary-button"
    href="/shop.php"
>
    Browse the Shop
</a>

</section>

<?php else: ?>

<div class="cart-layout">

<section class="cart-items">

<?php foreach ($items as $item): ?>

<?php
$image =
    public_shop_image_url(
        $item['image_url']
        ?? '',
        $siteUrl
    );
?>

<article class="cart-item">

<a
    class="cart-item-image"
    href="/product.php?slug=<?= rawurlencode(
        (string) $item[
            'product_slug'
        ]
    ) ?>"
>

<?php if ($image !== ''): ?>

<img
    src="<?= htmlspecialchars(
        $image,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    alt=""
>

<?php else: ?>

<i
    class="fa-solid fa-image"
    aria-hidden="true"
></i>

<?php endif; ?>

</a>


<div class="cart-item-main">

<h2>
    <a
        href="/product.php?slug=<?= rawurlencode(
            (string) $item[
                'product_slug'
            ]
        ) ?>"
    >
        <?= htmlspecialchars(
            (string) $item[
                'product_name'
            ],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </a>
</h2>

<p>
    <?= htmlspecialchars(
        (string) (
            $item['name']
            ?: 'Standard'
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<span>
    <?= htmlspecialchars(
        public_shop_money(
            (int) $item[
                'price_cents'
            ],
            (string) (
                $item['currency']
                ?? 'usd'
            )
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
    each
</span>


<form
    class="cart-item-controls"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(
        shop_cart_csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<input
    type="hidden"
    name="variant_id"
    value="<?= (int) $item['id'] ?>"
>


<label>
    <span>Qty</span>

    <select name="quantity">
        <?php for ($quantity = 1; $quantity <= max(1, (int) ($item['max_quantity'] ?? 20)); $quantity++): ?>
            <option
                value="<?= $quantity ?>"
                <?= $quantity ===
                    (int) $item['quantity']
                        ? 'selected'
                        : '' ?>
            >
                <?= $quantity ?>
            </option>
        <?php endfor; ?>
    </select>
</label>


<button
    type="submit"
    name="cart_action"
    value="update"
>
    Update
</button>

<button
    type="submit"
    name="cart_action"
    value="remove"
    class="is-remove"
>
    Remove
</button>

</form>

</div>


<strong class="cart-item-total">
    <?= htmlspecialchars(
        public_shop_money(
            (int) $item[
                'line_total_cents'
            ],
            (string) (
                $item['currency']
                ?? 'usd'
            )
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</strong>

</article>

<?php endforeach; ?>

</section>


<aside class="cart-summary">

<p class="eyebrow">
    Order summary
</p>

<div class="cart-summary-row">
    <span>Subtotal</span>

    <strong>
        <?= htmlspecialchars(
            public_shop_money(
                $subtotal
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>
</div>

<p class="cart-summary-note">
    Shipping details and applicable tax are finalized during secure checkout.
</p>

<a
    class="shop-primary-button cart-checkout-button"
    href="/checkout.php"
>
    Secure checkout
</a>

<p class="cart-summary-secure">
    <i
        class="fa-solid fa-lock"
        aria-hidden="true"
    ></i>

    Payments will be securely processed by Stripe.
</p>


<form
    class="cart-clear-form"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars(
        shop_cart_csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<button
    type="submit"
    name="cart_action"
    value="clear"
>
    Empty cart
</button>

</form>

</aside>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
