<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/public-shop.php';
require_once __DIR__ . '/app/shop-cart.php';

$db = db();
$config = llama_config();
$siteUrl = rtrim((string) ($config['app']['url'] ?? 'https://llamascout.com'), '/');
$slug = trim((string) ($_GET['slug'] ?? ''));

$product = $slug !== ''
    ? public_shop_product_by_slug($db, $slug)
    : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found | Llama Scout';
    $pageRobots = 'noindex,follow';
    $shopPageCss = true;
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="shop-page">
        <div class="shop-empty">
            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
            <h1>Product not found.</h1>
            <p>This product may be unavailable or no longer active.</p>
            <a class="shop-primary-button" href="/shop.php">Back to the Shop</a>
        </div>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$productId = (int) $product['id'];
$images = public_shop_product_images($db, $productId);
$options = public_shop_product_options($db, $productId);
$variants = public_shop_product_variants($db, $productId);

$cartError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!shop_cart_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Your session could not be verified. Reload the page and try again.');
        }

        $variantId = (int) ($_POST['variant_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);

        if ($variantId < 1) {
            throw new RuntimeException('Choose the product options you want first.');
        }

        shop_cart_add($db, $variantId, $quantity);

        header('Location: /cart.php?added=1', true, 303);
        exit;
    } catch (RuntimeException $exception) {
        $cartError = $exception->getMessage();
    } catch (Throwable $exception) {
        $reference = llama_log_caught_exception(
            $exception,
            'product_add_to_cart',
            ['product_id' => $productId]
        );

        $cartError = llama_error_message_with_reference(
            'The item could not be added to your cart.',
            $reference
        );
    }
}

$initialVariant = null;

foreach ($variants as $variant) {
    $state = public_shop_variant_state($variant);

    if ((int) ($variant['is_active'] ?? 0) === 1 && !empty($state['purchasable'])) {
        $initialVariant = $variant;
        break;
    }
}

if (!$initialVariant) {
    foreach ($variants as $variant) {
        if ((int) ($variant['is_active'] ?? 0) === 1) {
            $initialVariant = $variant;
            break;
        }
    }
}

if (!$initialVariant && $variants) {
    $initialVariant = $variants[0];
}

$initialState = $initialVariant
    ? public_shop_variant_state($initialVariant)
    : ['key' => 'unavailable', 'label' => 'Unavailable', 'purchasable' => false];

$initialPrice = $initialVariant ? (int) ($initialVariant['price_cents'] ?? 0) : 0;
$initialCompare = $initialVariant && $initialVariant['compare_at_price_cents'] !== null
    ? (int) $initialVariant['compare_at_price_cents']
    : 0;
$initialSale = $initialCompare > $initialPrice && $initialPrice > 0;
$initialMaxQuantity = $initialVariant
    ? public_shop_variant_max_quantity($initialVariant)
    : 0;

$variantPayload = [];

foreach ($variants as $variant) {
    $state = public_shop_variant_state($variant);

    $variantPayload[] = [
        'id' => (int) $variant['id'],
        'name' => (string) ($variant['name'] ?? ''),
        'priceCents' => (int) ($variant['price_cents'] ?? 0),
        'compareAtPriceCents' => $variant['compare_at_price_cents'] !== null
            ? (int) $variant['compare_at_price_cents']
            : null,
        'currency' => (string) ($variant['currency'] ?? 'usd'),
        'active' => (int) ($variant['is_active'] ?? 0) === 1,
        'options' => is_array($variant['_options'] ?? null) ? $variant['_options'] : [],
        'state' => $state,
        'maxQuantity' => public_shop_variant_max_quantity($variant),
    ];
}

function product_image_criteria_payload(
    array $image
): array {
    $optionName = trim((string) ($image['option_name'] ?? ''));
    $optionValue = trim((string) ($image['option_value'] ?? ''));

    if ($optionName === '' || $optionValue === '') {
        return [];
    }

    if ($optionName === '__criteria__') {
        $decoded = json_decode($optionValue, true);

        if (!is_array($decoded)) {
            return [];
        }

        $criteria = [];

        foreach ($decoded as $name => $values) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            if (!is_array($values)) {
                $values = [$values];
            }

            $clean = [];

            foreach ($values as $value) {
                $value = trim((string) $value);

                if ($value !== '') {
                    $clean[$value] = $value;
                }
            }

            if ($clean) {
                $criteria[$name] = array_values($clean);
            }
        }

        return $criteria;
    }

    if ($optionName === '__variant__') {
        return [
            '__variant__' => [$optionValue],
        ];
    }

    return [
        $optionName => [$optionValue],
    ];
}

$imagePayload = [];

foreach ($images as $index => $image) {
    $imagePayload[] = [
        'id' => (int) $image['id'],
        'src' => public_shop_image_url((string) ($image['image_url'] ?? ''), $siteUrl),
        'alt' => trim((string) ($image['alt_text'] ?? '')) ?: (string) $product['name'],
        'criteria' => product_image_criteria_payload($image),
        'primary' => (int) ($image['is_primary'] ?? 0) === 1,
        'index' => $index,
    ];
}

$optionPayload = [];

foreach ($options as $option) {
    $values = [];

    foreach (($option['values'] ?? []) as $value) {
        $optionValue = trim((string) ($value['option_value'] ?? ''));
        if ($optionValue !== '') {
            $values[] = $optionValue;
        }
    }

    $optionPayload[] = [
        'name' => (string) ($option['option_name'] ?? ''),
        'values' => $values,
    ];
}

$pageTitle = (string) $product['name'] . ' | Llama Scout Shop';
$pageDescription = trim((string) ($product['short_description'] ?? ''));
$canonicalUrl = $siteUrl . '/product.php?slug=' . rawurlencode($slug);
$pageSocialType = 'product';
$pageSocialImage = $images
    ? public_shop_image_url((string) ($images[0]['image_url'] ?? ''), $siteUrl)
    : public_shop_image_url((string) ($product['primary_image_url'] ?? ''), $siteUrl);
$shopPageCss = true;

require __DIR__ . '/partials/header.php';
?>

<section class="shop-page product-page" data-product-page>

<a class="shop-back-link" href="/shop.php">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    Shop
</a>

<div class="product-layout">

<section class="product-media" aria-label="Product photos">

<?php if ($images): ?>

<div class="product-primary-image product-gallery-stage" data-gallery-stage>
    <img
        data-main-product-image
        src="<?= htmlspecialchars(public_shop_image_url((string) $images[0]['image_url'], $siteUrl), ENT_QUOTES, 'UTF-8') ?>"
        alt="<?= htmlspecialchars((string) ($images[0]['alt_text'] ?: $product['name']), ENT_QUOTES, 'UTF-8') ?>"
    >

    <?php if (count($images) > 1): ?>
    <button class="product-gallery-arrow is-previous" type="button" data-gallery-previous aria-label="Previous product photo">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
    </button>

    <button class="product-gallery-arrow is-next" type="button" data-gallery-next aria-label="Next product photo">
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    </button>
    <?php endif; ?>
</div>

<?php if (count($images) > 1): ?>
<div class="product-thumbnail-carousel">
    <button class="product-thumbnail-arrow" type="button" data-thumbnails-previous aria-label="Scroll product photos left">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
    </button>

    <div class="product-thumbnail-track" data-thumbnail-track>
        <?php foreach ($images as $index => $image): ?>
        <button
            class="product-thumbnail<?= $index === 0 ? ' is-active' : '' ?>"
            type="button"
            data-thumbnail-index="<?= (int) $index ?>"
            aria-label="View product photo <?= (int) $index + 1 ?>"
            aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
        >
            <img
                src="<?= htmlspecialchars(public_shop_image_url((string) $image['image_url'], $siteUrl), ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars((string) ($image['alt_text'] ?: $product['name']), ENT_QUOTES, 'UTF-8') ?>"
                loading="lazy"
            >
        </button>
        <?php endforeach; ?>
    </div>

    <button class="product-thumbnail-arrow" type="button" data-thumbnails-next aria-label="Scroll product photos right">
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    </button>
</div>
<?php endif; ?>

<?php else: ?>
<div class="product-primary-image product-image-empty">
    <i class="fa-solid fa-image" aria-hidden="true"></i>
</div>
<?php endif; ?>

</section>

<section class="product-information">

<?php if (!empty($product['product_type'])): ?>
<p class="eyebrow"><?= htmlspecialchars((string) $product['product_type'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<div class="product-title-row">
    <h1><?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?></h1>

    <button
        class="shop-share-button llama-share-button"
        type="button"
        data-share
        data-share-title="<?= htmlspecialchars((string) $product['name'] . ' | Llama Scout Shop', ENT_QUOTES, 'UTF-8') ?>"
        data-share-text="<?= htmlspecialchars((string) $product['name'] . ' from the Llama Scout Shop.', ENT_QUOTES, 'UTF-8') ?>"
        data-share-url="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>"
        aria-label="Share <?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?>"
    >
        <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
        <span data-share-label>Share</span>
    </button>
</div>

<div class="product-price-block" aria-live="polite">
    <div class="product-price-line">
        <span class="product-sale-badge" data-sale-badge <?= !$initialSale ? 'hidden' : '' ?>>Sale</span>
        <span class="product-price" data-product-price>
            <?= $initialPrice > 0
                ? htmlspecialchars(public_shop_money($initialPrice, (string) ($initialVariant['currency'] ?? 'usd')), ENT_QUOTES, 'UTF-8')
                : 'Unavailable' ?>
        </span>
        <span class="product-compare-price" data-compare-price <?= !$initialSale ? 'hidden' : '' ?>>
            <?= $initialSale ? htmlspecialchars(public_shop_money($initialCompare, (string) ($initialVariant['currency'] ?? 'usd')), ENT_QUOTES, 'UTF-8') : '' ?>
        </span>
    </div>

    <div class="product-stock-status is-<?= htmlspecialchars((string) $initialState['key'], ENT_QUOTES, 'UTF-8') ?>" data-stock-status>
        <?= htmlspecialchars((string) $initialState['label'], ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>

<?php if (!empty($product['short_description'])): ?>
<p class="product-short-description"><?= htmlspecialchars((string) $product['short_description'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($options): ?>
<div class="product-options-display" data-product-options>
    <h2>Available options</h2>

    <?php foreach ($options as $option): ?>
    <?php $optionName = (string) $option['option_name']; ?>
    <div class="product-option-group" data-option-group="<?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?>">
        <strong><?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?></strong>

        <div class="product-option-values" role="group" aria-label="<?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($option['values'] as $value): ?>
            <?php
            $optionValue = (string) $value['option_value'];
            $isSelected = $initialVariant
                && (string) (($initialVariant['_options'][$optionName] ?? '')) === $optionValue;
            ?>
            <button
                type="button"
                class="product-option-pill<?= $isSelected ? ' is-selected' : '' ?>"
                data-option-name="<?= htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8') ?>"
                data-option-value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>"
                aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
            >
                <?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($cartError !== ''): ?>
<div class="product-cart-message is-error">
    <?= htmlspecialchars($cartError, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<form class="product-add-cart" method="post" data-product-cart-form>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(shop_cart_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="variant_id" value="<?= $initialVariant ? (int) $initialVariant['id'] : 0 ?>" data-selected-variant>

    <label class="product-quantity-field">
        <span>Quantity</span>
        <select name="quantity" data-product-quantity <?= $initialMaxQuantity < 1 ? 'disabled' : '' ?>>
            <?php for ($quantity = 1; $quantity <= max(1, $initialMaxQuantity); $quantity++): ?>
            <option value="<?= $quantity ?>"><?= $quantity ?></option>
            <?php endfor; ?>
        </select>
    </label>

    <button
        class="shop-primary-button product-add-button"
        type="submit"
        data-add-to-cart
        <?= empty($initialState['purchasable']) ? 'disabled' : '' ?>
    >
        <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
        <span data-add-to-cart-label>
            <?= match ((string) $initialState['key']) {
                'preorder' => 'Preorder',
                'backorder' => 'Backorder',
                'out_of_stock' => 'Out of stock',
                'unavailable' => 'Unavailable',
                default => 'Add to cart',
            } ?>
        </span>
    </button>
</form>

<?php if (!empty($product['description'])): ?>
<div class="product-description">
    <h2>About this product</h2>
    <?= nl2br(htmlspecialchars((string) $product['description'], ENT_QUOTES, 'UTF-8')) ?>
</div>
<?php endif; ?>

</section>
</div>

<script type="application/json" id="product-variant-data"><?= htmlspecialchars(
    json_encode(
        [
            'options' => $optionPayload,
            'variants' => $variantPayload,
            'images' => $imagePayload,
            'initialVariantId' => $initialVariant ? (int) $initialVariant['id'] : null,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    ) ?: '{}',
    ENT_NOQUOTES,
    'UTF-8'
) ?></script>
<script src="/js/shop-product.js" defer></script>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
