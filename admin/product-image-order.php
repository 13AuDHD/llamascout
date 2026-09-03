<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/admin-shop-image-order.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);
$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);

if ($productId < 1) {
    header('Location: /products.php');
    exit;
}

$product = admin_shop_product($db, $productId);
if (!$product) {
    header('Location: /products.php');
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $updated = admin_shop_save_product_image_order(
                $db,
                $actorUserId,
                $productId,
                is_array($_POST['position'] ?? null) ? $_POST['position'] : []
            );

            $notice = 'Saved image order for ' . $updated . ' photo' . ($updated === 1 ? '' : 's') . '.';
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.product_image_order',
                ['product_id' => $productId],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference('The image order could not be saved.', $reference);
        }
    }
}

$images = admin_shop_product_images($db, $productId);
$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Image Order';
$adminPageEyebrow = (string) $product['name'];
$adminActiveNav = 'products';

require __DIR__ . '/_header.php';
?>
<link rel="stylesheet" href="/css/admin-product-images.css">

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success"><?= moderation_e($notice) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error"><?= moderation_e($error) ?></div>
<?php endif; ?>

<section class="admin-panel">
<header class="admin-panel-header">
    <div>
        <p>Media</p>
        <h2>Product Image Order</h2>
    </div>
    <a class="admin-button" href="/product.php?id=<?= (int) $productId ?>">Back to Product</a>
</header>

<?php if (!$images): ?>
<div class="admin-empty-state">
    <i class="fa-regular fa-image" aria-hidden="true"></i>
    <h3>No product images yet.</h3>
    <p>Add photos from the product editor first.</p>
</div>
<?php else: ?>

<div class="admin-image-order-intro">
    <i class="fa-solid fa-arrow-down-1-9" aria-hidden="true"></i>
    <div>
        <strong>Choose the order customers should see.</strong>
        <span>The primary image is always position 1. Duplicate or skipped numbers are normalized automatically.</span>
    </div>
</div>

<form class="admin-image-order-form" method="post">
<input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
<input type="hidden" name="product_id" value="<?= (int) $productId ?>">

<div class="admin-image-order-grid">
<?php foreach ($images as $index => $image): ?>
<article class="admin-image-order-card">
    <div class="admin-image-order-preview">
        <img
            src="<?= moderation_e(admin_shop_image_url((string) $image['image_url'], $siteUrl)) ?>"
            alt="<?= moderation_e((string) ($image['alt_text'] ?: $product['name'])) ?>"
            loading="lazy"
        >
        <?php if ((int) $image['is_primary'] === 1): ?>
        <span class="admin-image-order-primary">Primary</span>
        <?php endif; ?>
    </div>

    <div class="admin-image-order-controls">
        <label>
            <span>Position</span>
            <input
                type="number"
                name="position[<?= (int) $image['id'] ?>]"
                min="1"
                max="<?= count($images) ?>"
                step="1"
                value="<?= $index + 1 ?>"
                <?= (int) $image['is_primary'] === 1 ? 'readonly' : '' ?>
            >
        </label>

        <div class="admin-image-order-meta">
            <span>Image #<?= (int) $image['id'] ?></span>
            <?php $criteria = admin_shop_photo_criteria($image); ?>
            <?php if ($criteria): ?>
                <?php
                $criterionParts = [];
                foreach ($criteria as $criterionName => $criterionValues) {
                    $criterionParts[] = $criterionName . ': ' . implode(', ', $criterionValues);
                }
                ?>
                <span><?= moderation_e(implode(' | ', $criterionParts)) ?></span>
            <?php else: ?>
                <span>General product photo</span>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php endforeach; ?>
</div>

<div class="admin-image-order-actions">
    <a class="admin-button" href="/product.php?id=<?= (int) $productId ?>">Cancel</a>
    <button class="admin-button" type="submit">Save image order</button>
</div>
</form>
<?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
