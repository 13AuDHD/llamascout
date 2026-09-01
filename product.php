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

$slug =
    trim(
        (string) (
            $_GET['slug']
            ?? ''
        )
    );

$product =
    $slug !== ''
        ? public_shop_product_by_slug(
            $db,
            $slug
        )
        : null;

if (!$product) {
    http_response_code(404);

    $pageTitle =
        'Product not found | Llama Scout';

    $pageRobots =
        'noindex,follow';

    $shopPageCss = true;

    require __DIR__ . '/partials/header.php';
    ?>

    <section class="shop-page">

        <div class="shop-empty">

            <i
                class="fa-solid fa-box-open"
                aria-hidden="true"
            ></i>

            <h1>Product not found.</h1>

            <p>
                This product may be unavailable or no longer active.
            </p>

            <a
                class="shop-primary-button"
                href="/shop.php"
            >
                Back to the Shop
            </a>

        </div>

    </section>

    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$productId =
    (int) $product['id'];

$images =
    public_shop_product_images(
        $db,
        $productId
    );

$options =
    public_shop_product_options(
        $db,
        $productId
    );

$variants =
    public_shop_product_variants(
        $db,
        $productId
    );

$prices =
    array_values(
        array_map(
            static fn(array $variant): int =>
                (int) $variant['price_cents'],
            $variants
        )
    );

$minPrice =
    $prices
        ? min($prices)
        : null;

$maxPrice =
    $prices
        ? max($prices)
        : null;

$pageTitle =
    (string) $product['name']
    . ' | Llama Scout Shop';

$pageDescription =
    trim(
        (string) (
            $product['short_description']
            ?? ''
        )
    );

$canonicalUrl =
    $siteUrl
    . '/product.php?slug='
    . rawurlencode($slug);

$shopPageCss = true;

require __DIR__ . '/partials/header.php';
?>

<section class="shop-page product-page">

<a
    class="shop-back-link"
    href="/shop.php"
>
    <i
        class="fa-solid fa-arrow-left"
        aria-hidden="true"
    ></i>

    Shop
</a>


<div class="product-layout">

<section class="product-media">

<?php if ($images): ?>

<div class="product-primary-image">

<img
    src="<?= htmlspecialchars(
        public_shop_image_url(
            (string) $images[0]['image_url'],
            $siteUrl
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    alt="<?= htmlspecialchars(
        (string) (
            $images[0]['alt_text']
            ?: $product['name']
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

</div>


<?php if (count($images) > 1): ?>

<div class="product-image-grid">

<?php foreach (
    array_slice(
        $images,
        1
    ) as $image
): ?>

<img
    src="<?= htmlspecialchars(
        public_shop_image_url(
            (string) $image['image_url'],
            $siteUrl
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    alt="<?= htmlspecialchars(
        (string) (
            $image['alt_text']
            ?: $product['name']
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    loading="lazy"
>

<?php endforeach; ?>

</div>

<?php endif; ?>


<?php else: ?>

<div class="product-primary-image product-image-empty">
    <i
        class="fa-solid fa-image"
        aria-hidden="true"
    ></i>
</div>

<?php endif; ?>

</section>


<section class="product-information">

<?php if (!empty($product['product_type'])): ?>

<p class="eyebrow">
    <?= htmlspecialchars(
        (string) $product['product_type'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php endif; ?>


<h1>
    <?= htmlspecialchars(
        (string) $product['name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h1>


<div class="product-price">

<?php if ($minPrice === null): ?>

Pricing coming soon

<?php elseif ($minPrice === $maxPrice): ?>

<?= htmlspecialchars(
    public_shop_money($minPrice),
    ENT_QUOTES,
    'UTF-8'
) ?>

<?php else: ?>

<?= htmlspecialchars(
    public_shop_money($minPrice),
    ENT_QUOTES,
    'UTF-8'
) ?>
–
<?= htmlspecialchars(
    public_shop_money($maxPrice),
    ENT_QUOTES,
    'UTF-8'
) ?>

<?php endif; ?>

</div>


<?php if (!empty($product['short_description'])): ?>

<p class="product-short-description">
    <?= htmlspecialchars(
        (string) $product['short_description'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php endif; ?>


<?php if ($options): ?>

<div class="product-options-display">

<h2>Available options</h2>

<?php foreach ($options as $option): ?>

<div>

<strong>
    <?= htmlspecialchars(
        (string) $option['option_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</strong>

<div class="product-option-values">

<?php foreach ($option['values'] as $value): ?>

<span>
    <?= htmlspecialchars(
        (string) $value['option_value'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</span>

<?php endforeach; ?>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>


<?php if ($variants): ?>

<div class="product-variant-list">

<h2>Available versions</h2>

<?php foreach ($variants as $variant): ?>

<div class="product-variant-row">

<div>
    <strong>
        <?= htmlspecialchars(
            (string) (
                $variant['name']
                ?: 'Standard'
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>

    <span>
        <?= htmlspecialchars(
            public_shop_variant_availability(
                $variant
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </span>
</div>

<strong>
    <?= htmlspecialchars(
        public_shop_money(
            (int) $variant['price_cents'],
            (string) (
                $variant['currency']
                ?? 'usd'
            )
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</strong>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>


<div class="product-purchase-note">

<i
    class="fa-solid fa-cart-shopping"
    aria-hidden="true"
></i>

<div>
    <strong>Online checkout is the next shop step.</strong>

    <span>
        The catalog and product pages are live now. Checkout will be
        connected to the existing order and Stripe infrastructure next.
    </span>
</div>

</div>


<?php if (!empty($product['description'])): ?>

<div class="product-description">

<h2>About this product</h2>

<?= nl2br(
    htmlspecialchars(
        (string) $product['description'],
        ENT_QUOTES,
        'UTF-8'
    )
) ?>

</div>

<?php endif; ?>

</section>

</div>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
