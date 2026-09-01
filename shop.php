<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/public-shop.php';

$db = db();

$config = llama_config();

$siteUrl = rtrim(
    (string) (
        $config['app']['url']
        ?? 'https://llamascout.com'
    ),
    '/'
);

$products =
    public_shop_products($db);

$pageTitle =
    'Shop | Llama Scout';

$pageDescription =
    'Llama Scout gear, apparel, accessories, and trail-ready goods.';

$canonicalUrl =
    $siteUrl . '/shop.php';

$shopPageCss = true;

require __DIR__ . '/partials/header.php';
?>

<section class="shop-page">

<header class="shop-hero">

<p class="eyebrow">Llama Scout Shop</p>

<h1>
    Gear for knowing the place before you go.
</h1>

<p>
    Llama Scout apparel, accessories, and useful trail gear.
    The shop supports continued development of Llama Scout.
</p>

</header>


<?php if (!$products): ?>

<section class="shop-empty">

<i
    class="fa-solid fa-box-open"
    aria-hidden="true"
></i>

<h2>The shelves are being stocked.</h2>

<p>
    There are no active products in the public shop yet.
</p>

</section>

<?php else: ?>

<div class="shop-product-grid">

<?php foreach ($products as $product): ?>

<?php
$image =
    public_shop_image_url(
        $product['display_image']
        ?? '',
        $siteUrl
    );
?>

<article class="shop-product-card">

<a
    class="shop-product-image"
    href="/product.php?slug=<?= rawurlencode(
        (string) $product['slug']
    ) ?>"
>

<?php if ($image !== ''): ?>

<img
    src="<?= htmlspecialchars(
        $image,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    alt="<?= htmlspecialchars(
        (string) $product['name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    loading="lazy"
>

<?php else: ?>

<div class="shop-product-image-empty">
    <i
        class="fa-solid fa-image"
        aria-hidden="true"
    ></i>
</div>

<?php endif; ?>

<?php if ((int) $product['is_featured'] === 1): ?>
    <span class="shop-featured-badge">
        Featured
    </span>
<?php endif; ?>

</a>


<div class="shop-product-card-body">

<?php if (!empty($product['product_type'])): ?>

<span class="shop-product-type">
    <?= htmlspecialchars(
        (string) $product['product_type'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</span>

<?php endif; ?>


<h2>
    <a
        href="/product.php?slug=<?= rawurlencode(
            (string) $product['slug']
        ) ?>"
    >
        <?= htmlspecialchars(
            (string) $product['name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </a>
</h2>


<?php if (!empty($product['short_description'])): ?>

<p>
    <?= htmlspecialchars(
        (string) $product['short_description'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php endif; ?>


<div class="shop-product-card-footer">

<strong>
    <?= htmlspecialchars(
        public_shop_price_label($product),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</strong>

<a
    class="shop-card-button"
    href="/product.php?slug=<?= rawurlencode(
        (string) $product['slug']
    ) ?>"
>
    View product
</a>

</div>

</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
