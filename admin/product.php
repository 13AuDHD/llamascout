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
            } elseif ($action === 'save-variant') {
                admin_shop_save_variant(
                    $db,
                    $actorUserId,
                    (int) ($_POST['variant_id'] ?? 0),
                    $_POST
                );

                $notice = 'Variant updated.';
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
    <span>Slug</span>
    <input
        type="text"
        name="slug"
        maxlength="160"
        value="<?= moderation_e((string) $product['slug']) ?>"
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

<div class="admin-commerce-checks">
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

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Media</p>
        <h2>Product Images</h2>
    </div>

    <span>
        Image manager next
    </span>
</header>

<?php if (!$images): ?>
<div class="admin-empty-state">
    <p>No product images.</p>
</div>
<?php else: ?>

<div class="admin-commerce-image-grid">

<?php foreach ($images as $image): ?>
<figure>
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
        <figcaption>Primary</figcaption>
    <?php endif; ?>
</figure>
<?php endforeach; ?>

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
