<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/public-shop.php';
require_once __DIR__ . '/app/shop-cart.php';

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

$cartError = '';
$cartNotice = '';

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

        $variantId =
            (int) (
                $_POST['variant_id']
                ?? 0
            );

        $quantity =
            (int) (
                $_POST['quantity']
                ?? 1
            );

        $validVariantIds =
            array_map(
                static fn(array $variant): int =>
                    (int) $variant['id'],
                $variants
            );

        if (
            $variantId < 1
            || !in_array(
                $variantId,
                $validVariantIds,
                true
            )
        ) {
            throw new RuntimeException(
                'Choose an available product option.'
            );
        }

        shop_cart_add(
            $db,
            $variantId,
            $quantity
        );

        header(
            'Location: /cart.php?added=1',
            true,
            303
        );

        exit;
    } catch (Throwable $exception) {
        $cartError =
            $exception->getMessage();
    }
}

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


<?php if ($cartError !== ''): ?>

<div class="product-cart-message is-error">
    <?= htmlspecialchars(
        $cartError,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

<?php endif; ?>


<?php if ($variants): ?>

<form
    class="product-add-cart"
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


<label>
    <span>Choose option</span>

    <select
        name="variant_id"
        required
    >

        <option value="">
            Select an option
        </option>

        <?php foreach ($variants as $variant): ?>

            <?php
            $availability =
                public_shop_variant_availability(
                    $variant
                );

            $soldOut =
                (
                    (int) (
                        $variant[
                            'track_inventory'
                        ]
                        ?? 0
                    ) === 1
                    && (int) (
                        $variant[
                            'inventory_quantity'
                        ]
                        ?? 0
                    ) <= 0
                    && (int) (
                        $variant[
                            'allow_backorder'
                        ]
                        ?? 0
                    ) !== 1
                );
            ?>

            <option
                value="<?= (int) $variant['id'] ?>"
                <?= $soldOut
                    ? 'disabled'
                    : '' ?>
            >
                <?= htmlspecialchars(
                    (string) (
                        $variant['name']
                        ?: 'Standard'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                ·
                <?= htmlspecialchars(
                    public_shop_money(
                        (int) $variant[
                            'price_cents'
                        ],
                        (string) (
                            $variant[
                                'currency'
                            ]
                            ?? 'usd'
                        )
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
                ·
                <?= htmlspecialchars(
                    $availability,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>
</label>


<label class="product-quantity-field">
    <span>Quantity</span>

    <select name="quantity">
        <?php for ($quantity = 1; $quantity <= 10; $quantity++): ?>
            <option value="<?= $quantity ?>">
                <?= $quantity ?>
            </option>
        <?php endfor; ?>
    </select>
</label>


<button
    class="shop-primary-button product-add-button"
    type="submit"
>
    <i
        class="fa-solid fa-cart-plus"
        aria-hidden="true"
    ></i>

    Add to cart
</button>

</form>

<?php else: ?>

<div class="product-purchase-note">

<i
    class="fa-solid fa-circle-info"
    aria-hidden="true"
></i>

<div>
    <strong>This product is not ready for purchase yet.</strong>

    <span>
        An active product variant with a price is required before it
        can be added to the cart.
    </span>
</div>

</div>

<?php endif; ?>


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
