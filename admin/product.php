<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);

$productId = (int) (
    $_GET['id']
    ?? $_POST['product_id']
    ?? 0
);

if ($productId < 1) {
    header('Location: /products.php');
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !moderation_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = (string) (
                $_POST['shop_admin_action'] ?? ''
            );

            if ($action === 'save-product') {
                admin_shop_save_product(
                    $db,
                    $actorUserId,
                    $productId,
                    $_POST
                );

                $notice = 'Product updated.';
            } elseif ($action === 'save-options') {
                admin_shop_save_options(
                    $db,
                    $actorUserId,
                    $productId,
                    $_POST
                );

                $notice = 'Product options updated.';
            } elseif ($action === 'generate-variants') {
                $created = admin_shop_generate_variants(
                    $db,
                    $actorUserId,
                    $productId,
                    $_POST
                );

                $notice =
                    $created > 0
                        ? $created . ' missing variant' .
                            ($created === 1 ? '' : 's') .
                            ' generated.'
                        : 'No missing variants needed to be generated.';
            } elseif ($action === 'save-variant') {
                admin_shop_save_variant(
                    $db,
                    $actorUserId,
                    (int) ($_POST['variant_id'] ?? 0),
                    $_POST
                );

                $notice = 'Variant updated.';
            } elseif ($action === 'add-photos') {
                $photoToken = trim(
                    (string) ($_POST['photo_stage_token'] ?? '')
                );

                $photos = llama_photo_decode_form_photos(
                    $_POST['photos_json'] ?? '[]'
                );

                if ($photoToken === '' || !$photos) {
                    throw new RuntimeException(
                        'Choose at least one product photo.'
                    );
                }

                $added = admin_shop_add_product_photos(
                    $db,
                    $actorUserId,
                    $productId,
                    $photoToken,
                    $photos
                );

                $notice =
                    $added === 1
                        ? 'Product photo added.'
                        : $added . ' product photos added.';
            } elseif ($action === 'set-primary-photo') {
                admin_shop_set_primary_photo(
                    $db,
                    $actorUserId,
                    $productId,
                    (int) ($_POST['image_id'] ?? 0)
                );

                $notice = 'Primary product photo updated.';
            } elseif ($action === 'delete-photo') {
                admin_shop_delete_product_photo(
                    $db,
                    $actorUserId,
                    $productId,
                    (int) ($_POST['image_id'] ?? 0)
                );

                $notice = 'Product photo deleted.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$product = admin_shop_product(
    $db,
    $productId
);

if (!$product) {
    header('Location: /products.php');
    exit;
}

$images = admin_shop_product_images(
    $db,
    $productId
);

$options = admin_shop_product_options(
    $db,
    $productId
);

$variants = admin_shop_product_variants(
    $db,
    $productId
);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = (string) $product['name'];
$adminPageEyebrow = 'Product Administration';
$adminActiveNav = 'products';
$adminNeedsPhotoUploader = true;

$remainingProductPhotos =
    max(
        0,
        20 - count($images)
    );

require __DIR__ . '/_header.php';
?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>
<?php endif; ?>


<div class="admin-commerce-product-detail">

<div class="admin-user-detail-main">

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Catalog</p>
        <h2>Product Details</h2>
    </div>
</header>

<form class="admin-user-form" method="post">

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int) $productId ?>"
>

<input
    type="hidden"
    name="shop_admin_action"
    value="save-product"
>

<div class="admin-user-form-grid">

<label>
    <span>Name</span>
    <input
        type="text"
        name="name"
        maxlength="200"
        value="<?= moderation_e((string) $product['name']) ?>"
        required
    >
</label>

<label>
    <span>Product type</span>
    <input
        type="text"
        name="product_type"
        value="<?= moderation_e(
            (string) ($product['product_type'] ?? '')
        ) ?>"
    >
</label>

<label>
    <span>Status</span>
    <select name="status">
        <?php foreach (['draft','active','archived'] as $status): ?>
            <option
                value="<?= moderation_e($status) ?>"
                <?= $product['status'] === $status ? 'selected' : '' ?>
            >
                <?= moderation_e(ucfirst($status)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="is-wide">
    <span>Short description</span>
    <input
        type="text"
        name="short_description"
        maxlength="500"
        value="<?= moderation_e(
            (string) ($product['short_description'] ?? '')
        ) ?>"
    >
</label>

<label class="is-wide">
    <span>Description</span>
    <textarea
        name="description"
        rows="8"
    ><?= moderation_e(
        (string) ($product['description'] ?? '')
    ) ?></textarea>
</label>

<label>
    <span>Sort order</span>
    <input
        type="number"
        name="sort_order"
        step="1"
        value="<?= (int) $product['sort_order'] ?>"
    >
</label>

<div class="admin-commerce-checks">
    <label>
        <input
            type="checkbox"
            name="is_featured"
            value="1"
            <?= (int) $product['is_featured'] === 1 ? 'checked' : '' ?>
        >
        <span>Featured product</span>
    </label>

    <label>
        <input
            type="checkbox"
            name="requires_shipping"
            value="1"
            <?= (int) $product['requires_shipping'] === 1 ? 'checked' : '' ?>
        >
        <span>Requires shipping</span>
    </label>
</div>

</div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save product
    </button>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Catalog Structure</p>
        <h2>Product Options</h2>
    </div>

    <span>
        <?= number_format(count($options)) ?>
        of 3 option groups
    </span>
</header>

<form
    class="admin-commerce-options-form"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int) $productId ?>"
>

<input
    type="hidden"
    name="shop_admin_action"
    value="save-options"
>

<?php
$optionsByPosition = [];

foreach ($options as $option) {
    $optionsByPosition[
        (int) $option['option_position']
    ] = $option;
}
?>

<div class="admin-commerce-option-grid">

<?php for ($position = 1; $position <= 3; $position++): ?>

<?php
$option =
    $optionsByPosition[$position]
    ?? null;

$valueText =
    $option
        ? implode(
            "\n",
            array_map(
                static fn(array $row): string =>
                    (string) $row['option_value'],
                $option['values']
            )
        )
        : '';
?>

<div class="admin-commerce-option-card">

<div class="admin-commerce-option-number">
    Option <?= $position ?>
</div>

<label>
    <span>Option name</span>

    <input
        type="text"
        name="option_name[<?= $position ?>]"
        maxlength="100"
        value="<?= moderation_e(
            (string) (
                $option['option_name']
                ?? ''
            )
        ) ?>"
        placeholder="<?= $position === 1
            ? 'Color'
            : (
                $position === 2
                    ? 'Size'
                    : 'Style'
            ) ?>"
    >
</label>

<label>
    <span>Values</span>

    <textarea
        name="option_values[<?= $position ?>]"
        rows="5"
        placeholder="One value per line, or separate values with commas."
    ><?= moderation_e($valueText) ?></textarea>
</label>

</div>

<?php endfor; ?>

</div>

<div class="admin-commerce-options-note">

<?php if ($variants): ?>
    Existing option groups and names are locked because this product
    already has variants. You can safely add new values, then use
    Generate Missing Variants below.
<?php else: ?>
    Leave unused option groups blank. A product can have up to three
    options, such as Color, Size, and Style.
<?php endif; ?>

</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Save options
    </button>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Variant Builder</p>
        <h2>Generate Missing Variants</h2>
    </div>
</header>

<form
    class="admin-commerce-generator"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int) $productId ?>"
>

<input
    type="hidden"
    name="shop_admin_action"
    value="generate-variants"
>

<div class="admin-commerce-generator-grid">

<label>
    <span>Default price</span>

    <input
        type="number"
        name="default_price"
        min="0"
        step=".01"
        placeholder="24.00"
        required
    >
</label>

<label>
    <span>SKU prefix</span>

    <input
        type="text"
        name="sku_prefix"
        maxlength="80"
        value="LS-<?= moderation_e(
            admin_shop_sku_piece(
                (string) $product['slug']
            )
        ) ?>"
    >
</label>

<label>
    <span>Initial inventory</span>

    <input
        type="number"
        name="default_inventory"
        step="1"
        value="0"
    >
</label>

<label>
    <span>Fulfillment</span>

    <select name="default_fulfillment_type">
        <option value="manual">Manual</option>
        <option value="provider">Provider</option>
        <option value="digital">Digital</option>
    </select>
</label>

<label class="is-wide">
    <span>Fulfillment provider</span>

    <input
        type="text"
        name="default_fulfillment_provider"
        placeholder="Printful, Printify, local inventory, etc."
    >
</label>

<div class="admin-commerce-checks is-wide">
    <label>
        <input
            type="checkbox"
            name="default_track_inventory"
            value="1"
        >
        <span>Track inventory</span>
    </label>

    <label>
        <input
            type="checkbox"
            name="default_allow_backorder"
            value="1"
        >
        <span>Allow backorder</span>
    </label>
</div>

</div>

<div class="admin-commerce-options-note">
    This creates only combinations that do not already exist.
    Existing variants, prices, inventory, fulfillment IDs, and order
    history are never deleted or replaced.
</div>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Generate missing variants
    </button>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Pricing + Inventory</p>
        <h2>Variants</h2>
    </div>
    <span><?= number_format(count($variants)) ?> variants</span>
</header>

<?php if (!$variants): ?>
<div class="admin-empty-state">
    <p>No variants configured.</p>
</div>
<?php else: ?>

<div class="admin-commerce-variants">

<?php foreach ($variants as $variant): ?>

<form
    class="admin-commerce-variant"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>

<input
    type="hidden"
    name="product_id"
    value="<?= (int) $productId ?>"
>

<input
    type="hidden"
    name="variant_id"
    value="<?= (int) $variant['id'] ?>"
>

<input
    type="hidden"
    name="shop_admin_action"
    value="save-variant"
>

<div class="admin-commerce-variant-title">
    <div>
        <strong>
            <?= moderation_e((string) $variant['name']) ?>
        </strong>

        <span>
            <?= moderation_e((string) $variant['sku']) ?>
        </span>
    </div>

    <label>
        <input
            type="checkbox"
            name="is_active"
            value="1"
            <?= (int) $variant['is_active'] === 1 ? 'checked' : '' ?>
        >
        Active
    </label>
</div>

<div class="admin-commerce-variant-grid">

<label class="is-wide">
    <span>SKU</span>
    <input
        type="text"
        name="sku"
        maxlength="120"
        value="<?= moderation_e(
            (string) $variant['sku']
        ) ?>"
        required
    >
</label>

<label>
    <span>Price</span>
    <input
        type="number"
        name="price"
        min="0"
        step=".01"
        value="<?= moderation_e(
            number_format(
                ((int) $variant['price_cents']) / 100,
                2,
                '.',
                ''
            )
        ) ?>"
    >
</label>

<label>
    <span>Compare at</span>
    <input
        type="number"
        name="compare_at_price"
        min="0"
        step=".01"
        value="<?= $variant['compare_at_price_cents'] !== null
            ? moderation_e(
                number_format(
                    ((int) $variant['compare_at_price_cents']) / 100,
                    2,
                    '.',
                    ''
                )
            )
            : '' ?>"
    >
</label>

<label>
    <span>Inventory</span>
    <input
        type="number"
        name="inventory_quantity"
        step="1"
        value="<?= (int) $variant['inventory_quantity'] ?>"
    >
</label>

<label>
    <span>Fulfillment</span>
    <select name="fulfillment_type">
        <?php foreach (['manual','provider','digital'] as $type): ?>
            <option
                value="<?= moderation_e($type) ?>"
                <?= $variant['fulfillment_type'] === $type ? 'selected' : '' ?>
            >
                <?= moderation_e(ucfirst($type)) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Provider</span>
    <input
        type="text"
        name="fulfillment_provider"
        value="<?= moderation_e(
            (string) ($variant['fulfillment_provider'] ?? '')
        ) ?>"
        placeholder="Printful, Printify, manual, etc."
    >
</label>

<label>
    <span>Provider product ID</span>
    <input
        type="text"
        name="fulfillment_product_id"
        value="<?= moderation_e(
            (string) (
                $variant['fulfillment_product_id']
                ?? ''
            )
        ) ?>"
    >
</label>

<label>
    <span>Provider variant ID</span>
    <input
        type="text"
        name="fulfillment_variant_id"
        value="<?= moderation_e(
            (string) (
                $variant['fulfillment_variant_id']
                ?? ''
            )
        ) ?>"
    >
</label>

<label>
    <span>Sort order</span>
    <input
        type="number"
        name="sort_order"
        step="1"
        value="<?= (int) $variant['sort_order'] ?>"
    >
</label>

<div class="admin-commerce-checks is-wide">
    <label>
        <input
            type="checkbox"
            name="track_inventory"
            value="1"
            <?= (int) $variant['track_inventory'] === 1 ? 'checked' : '' ?>
        >
        <span>Track inventory</span>
    </label>

    <label>
        <input
            type="checkbox"
            name="allow_backorder"
            value="1"
            <?= (int) $variant['allow_backorder'] === 1 ? 'checked' : '' ?>
        >
        <span>Allow backorder</span>
    </label>
</div>

</div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Save variant
    </button>
</div>

</form>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</div>


<aside class="admin-user-detail-side">

<section class="admin-panel admin-commerce-media-panel">

<header class="admin-panel-header">
    <div>
        <p>Media</p>
        <h2>Product Images</h2>
    </div>

    <span>
        <?= number_format(count($images)) ?> of 20
    </span>
</header>

<?php if ($images): ?>

<div class="admin-commerce-image-grid">

<?php foreach ($images as $image): ?>

<article class="admin-commerce-image-card">

<div class="admin-commerce-image-preview">

<img
    src="<?= moderation_e(
        admin_shop_image_url(
            (string) $image['image_url'],
            $siteUrl
        )
    ) ?>"
    alt="<?= moderation_e(
        (string) (
            $image['alt_text']
            ?: $product['name']
        )
    ) ?>"
    loading="lazy"
>

<?php if ((int) $image['is_primary'] === 1): ?>
    <span class="admin-commerce-primary-badge">
        Primary
    </span>
<?php endif; ?>

</div>

<div class="admin-commerce-image-actions">

<?php if ((int) $image['is_primary'] !== 1): ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="set-primary-photo">

    <button class="admin-button is-muted" type="submit">
        Make primary
    </button>
</form>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="delete-photo">

    <button class="admin-button admin-commerce-delete-photo" type="submit">
        Delete
    </button>
</form>

</div>

</article>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="admin-empty-state admin-commerce-photo-empty">
    <i class="fa-regular fa-images" aria-hidden="true"></i>
    <h3>No product images.</h3>
    <p>Add fresh product photos below.</p>
</div>

<?php endif; ?>


<?php if ($remainingProductPhotos > 0): ?>

<form
    class="admin-commerce-photo-upload-form"
    method="post"
>

<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="product_id" value="<?= (int) $productId ?>">
<input type="hidden" name="shop_admin_action" value="add-photos">
<input type="hidden" name="photo_stage_token" value="">
<input type="hidden" name="photos_json" value="[]">

<div
    data-photo-uploader
    data-photo-context="shop-products"
    data-photo-max="<?= (int) $remainingProductPhotos ?>"
    data-photo-csrf="<?= moderation_e(llama_photo_csrf_token()) ?>"
    data-photo-endpoint="/photo-upload.php"
    data-photo-title="Add product photos"
    data-photo-help="Add up to <?= (int) $remainingProductPhotos ?> more product photo<?= $remainingProductPhotos === 1 ? '' : 's' ?>. Photos are resized and location metadata is removed before storage."
></div>

<div class="admin-user-form-actions">
    <button class="admin-button" type="submit">
        Add photos to product
    </button>
</div>

</form>

<?php else: ?>

<div class="admin-commerce-photo-limit">
    This product already has the maximum of 20 photos.
</div>

<?php endif; ?>

</section>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Public Store</p>
        <h2>Product Link</h2>
    </div>
</header>

<div class="admin-user-action-box">
    <p>
        Product slug:
        <strong><?= moderation_e((string) $product['slug']) ?></strong>
    </p>

    <a
        class="admin-button"
        href="<?= moderation_e(
            $siteUrl .
            '/product.php?slug=' .
            rawurlencode(
                (string) $product['slug']
            )
        ) ?>"
        target="_blank"
        rel="noopener"
    >
        View product
    </a>
</div>

</section>

</aside>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
