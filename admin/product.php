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
            } elseif ($action === 'apply-variant-defaults') {
                $updated = admin_shop_apply_variant_defaults(
                    $db,
                    $actorUserId,
                    $productId,
                    $_POST
                );

                $notice = $updated . ' variant' . ($updated === 1 ? '' : 's') . ' updated from defaults.';
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
            } elseif ($action === 'assign-photo') {
                admin_shop_assign_product_photo(
                    $db,
                    $actorUserId,
                    $productId,
                    (int) ($_POST['image_id'] ?? 0),
                    is_array($_POST['photo_criteria'] ?? null)
                        ? $_POST['photo_criteria']
                        : []
                );

                $notice = 'Product photo assignment updated.';
            } elseif ($action === 'set-primary-photo') {
                admin_shop_set_primary_photo(
                    $db,
                    $actorUserId,
                    $productId,
                    (int) ($_POST['image_id'] ?? 0)
                );

                $notice = 'Primary product photo updated.';
            } elseif ($action === 'save-photo-position') {
                admin_shop_swap_product_photo_position(
                    $db,
                    $actorUserId,
                    $productId,
                    (int) ($_POST['image_id'] ?? 0),
                    (int) ($_POST['photo_position'] ?? 0)
                );

                $notice = 'Product photo position updated.';
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
            $reference = llama_log_caught_exception(
                $exception,
                'admin.product_action',
                ['product_id' => $productId, 'action' => $action],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference('The product action could not be completed.', $reference);
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

admin_shop_ensure_variant_sort_sequence($db, $productId);

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
$publicProductUrl = $siteUrl . '/product.php?slug=' . rawurlencode((string) $product['slug']);
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
<style>
.admin-commerce-field-help {
    display: block;
    margin-top: 6px;
    color: var(--admin-muted, #a7a7a7);
    font-size: .72rem;
    line-height: 1.45;
}
.admin-commerce-inventory-explainer {
    display: grid;
    gap: 3px;
    padding: 10px 12px;
    border: 1px solid var(--admin-border, rgba(255,255,255,.14));
    border-radius: 9px;
}
.admin-commerce-inventory-explainer strong { font-size: .72rem; }
.admin-commerce-inventory-explainer span {
    color: var(--admin-muted, #a7a7a7);
    font-size: .68rem;
    line-height: 1.45;
}
</style>

<style>
.admin-commerce-product-single {
    display: grid;
    gap: 22px;
}

.admin-commerce-collapsible {
    padding: 0;
    overflow: hidden;
}

.admin-commerce-collapse-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 20px 22px;
    cursor: pointer;
    list-style: none;
}

.admin-commerce-collapse-summary::-webkit-details-marker {
    display: none;
}

.admin-commerce-collapse-summary::after {
    content: "\f078";
    flex: 0 0 auto;
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    transition: transform .16s ease;
}

.admin-commerce-collapsible[open] > .admin-commerce-collapse-summary::after {
    transform: rotate(180deg);
}

.admin-commerce-collapse-summary > span:first-child {
    display: grid;
    gap: 4px;
}

.admin-commerce-collapse-summary small {
    opacity: .68;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.admin-commerce-collapse-summary strong {
    font-size: 1.05rem;
}

.admin-commerce-collapse-meta {
    margin-left: auto;
    opacity: .68;
    font-size: .78rem;
}

.admin-commerce-collapse-body {
    padding: 0 22px 22px;
    border-top: 1px solid var(--admin-border, rgba(255,255,255,.12));
}

.admin-commerce-image-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 20px;
}

.admin-commerce-photo-criteria {
    display: grid;
    gap: 14px;
    margin: 0;
    padding: 0;
    border: 0;
}

.admin-commerce-photo-criteria legend {
    margin-bottom: 8px;
    font-size: .8rem;
    font-weight: 800;
}

.admin-commerce-photo-help {
    margin: 0;
    opacity: .7;
    font-size: .76rem;
    line-height: 1.5;
}

.admin-commerce-photo-criteria-group {
    display: grid;
    gap: 8px;
}

.admin-commerce-photo-criteria-group > strong {
    font-size: .74rem;
}

.admin-commerce-photo-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.admin-commerce-photo-checkboxes label {
    position: relative;
    cursor: pointer;
}

.admin-commerce-photo-checkboxes input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.admin-commerce-photo-checkboxes span {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 6px 10px;
    border: 1px solid var(--admin-border, rgba(255,255,255,.16));
    border-radius: 9px;
    font-size: .72rem;
    opacity: .76;
}

.admin-commerce-photo-checkboxes input:checked + span {
    background: var(--admin-text, #f4f4f4);
    color: var(--admin-bg, #171717);
    border-color: var(--admin-text, #f4f4f4);
    opacity: 1;
}

.admin-commerce-photo-assignment {
    display: grid;
    gap: 14px;
}

.admin-commerce-defaults {
    display: grid;
    gap: 16px;
    margin: 20px 0 24px;
    padding: 18px;
    border: 1px solid var(--admin-border, rgba(255,255,255,.12));
    border-radius: 12px;
    background: rgba(255,255,255,.025);
}
.admin-commerce-defaults-header h3 { margin: 2px 0 4px; }
.admin-commerce-defaults-header p { margin: 0; opacity: .7; font-size: .76rem; }
.admin-commerce-defaults-header small { opacity: .65; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.admin-commerce-default-grid,
.admin-commerce-variant-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
}
.admin-commerce-default-field { display: grid; gap: 6px; min-width: 0; }
.admin-commerce-default-field > span:first-child { font-size: .72rem; font-weight: 800; opacity: .75; }
.admin-commerce-default-field input,
.admin-commerce-default-field select { width: 100%; min-width: 0; }
.admin-commerce-apply-toggle { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; opacity: .8; }
.admin-commerce-resequence { display: flex; align-items: flex-start; gap: 9px; padding: 12px; border: 1px solid var(--admin-border, rgba(255,255,255,.12)); border-radius: 9px; }
.admin-commerce-resequence span { display: grid; gap: 3px; }
.admin-commerce-resequence small { opacity: .65; }
.admin-commerce-variant-grid .is-wide { grid-column: span 2; }

@media (max-width: 1100px) {
    .admin-commerce-default-grid,
    .admin-commerce-variant-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (max-width: 650px) {
    .admin-commerce-default-grid,
    .admin-commerce-variant-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .admin-commerce-variant-grid .is-wide { grid-column: 1 / -1; }
}

@media (max-width: 760px) {
    .admin-commerce-image-grid {
        grid-template-columns: 1fr;
    }

    .admin-commerce-collapse-summary {
        padding: 17px;
    }

    .admin-commerce-collapse-body {
        padding: 0 17px 17px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const productUrl = <?= json_encode(
        $publicProductUrl,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    [
        document.querySelector('.admin-topbar-title strong'),
        document.querySelector('.admin-page-header h1'),
    ].forEach((title) => {
        if (!title || title.querySelector('a')) {
            return;
        }

        const link = document.createElement('a');
        link.href = productUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = title.textContent;
        link.style.color = 'inherit';
        link.style.textDecoration = 'none';
        link.title = 'Open public product page';

        title.replaceChildren(link);
    });
});
</script>

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


<div class="admin-commerce-product-single">

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

<details class="admin-panel admin-commerce-media-panel admin-commerce-collapsible" open>
<summary class="admin-commerce-collapse-summary">
    <span>
        <small>Media</small>
        <strong>Product Images</strong>
    </span>
    <span class="admin-commerce-collapse-meta">
        <?= number_format(count($images)) ?> of 20
    </span>
</summary>
<div class="admin-commerce-collapse-body">



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

<form class="admin-form admin-commerce-photo-assignment" method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
    <input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="assign-photo">

    <?php $photoCriteria = admin_shop_photo_criteria($image); ?>

    <fieldset class="admin-commerce-photo-criteria">
        <legend>Photo represents</legend>

        <p class="admin-commerce-photo-help">
            Check every option value this photo can represent.
            Leave everything unchecked to use it as a general product photo.
        </p>

        <?php foreach ($options as $photoOption): ?>
        <?php
        $photoOptionName = (string) $photoOption['option_name'];
        $selectedPhotoValues = $photoCriteria[$photoOptionName] ?? [];
        ?>
        <div class="admin-commerce-photo-criteria-group">
            <strong><?= moderation_e($photoOptionName) ?></strong>

            <div class="admin-commerce-photo-checkboxes">
                <?php foreach (($photoOption['values'] ?? []) as $photoValue): ?>
                <?php $photoValueText = (string) $photoValue['option_value']; ?>
                <label>
                    <input
                        type="checkbox"
                        name="photo_criteria[<?= moderation_e($photoOptionName) ?>][]"
                        value="<?= moderation_e($photoValueText) ?>"
                        <?= in_array($photoValueText, $selectedPhotoValues, true) ? 'checked' : '' ?>
                    >
                    <span><?= moderation_e($photoValueText) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </fieldset>

    <button class="admin-button is-muted" type="submit">
        Save photo criteria
    </button>
</form>

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

<form
    class="admin-commerce-photo-position"
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
        name="image_id"
        value="<?= (int) $image['id'] ?>"
    >

    <input
        type="hidden"
        name="shop_admin_action"
        value="save-photo-position"
    >

    <label>
        <span>#</span>

        <input
            type="number"
            name="photo_position"
            min="1"
            max="<?= count($images) ?>"
            step="1"
            value="<?= max(1, (int) $image['sort_order']) ?>"
            aria-label="Photo position"
            required
        >
    </label>

    <button
        class="admin-button is-muted"
        type="submit"
    >
        Save
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

</div>
</details>

<details class="admin-panel admin-commerce-collapsible">
<summary class="admin-commerce-collapse-summary">
    <span>
        <small>Variant Builder</small>
        <strong>Generate Missing Variants</strong>
    </span>
    
</summary>
<div class="admin-commerce-collapse-body">



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

<label>
    <span>Fulfillment provider</span>
    <select name="default_fulfillment_provider">
        <?php foreach (admin_shop_fulfillment_providers() as $providerValue => $providerLabel): ?>
            <option value="<?= moderation_e($providerValue) ?>">
                <?= moderation_e($providerLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label class="is-wide">
    <span>Inventory &amp; selling mode</span>
    <select name="default_inventory_mode">
        <option value="untracked">Always available</option>
        <option value="tracked">Track inventory</option>
        <option value="backorder">Backorder when sold out</option>
        <option value="preorder">Preorder</option>
    </select>
    <small class="admin-commerce-field-help">Always available ignores the stock count. Track inventory automatically shows Low stock and Out of stock. Backorder stays orderable after stock reaches zero. Preorder stays orderable before normal fulfillment begins.</small>
</label>

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

</div>
</details>

<details class="admin-panel admin-commerce-collapsible">
<summary class="admin-commerce-collapse-summary">
    <span>
        <small>Pricing + Inventory</small>
        <strong>Variants</strong>
    </span>
    <span class="admin-commerce-collapse-meta"><?= number_format(count($variants)) ?> variants</span>
</summary>
<div class="admin-commerce-collapse-body">



<?php if (!$variants): ?>
<div class="admin-empty-state">
    <p>No variants configured.</p>
</div>
<?php else: ?>

<form class="admin-commerce-defaults" method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
    <input type="hidden" name="shop_admin_action" value="apply-variant-defaults">

    <header class="admin-commerce-defaults-header">
        <div>
            <small>Bulk Editing</small>
            <h3>Variant Defaults</h3>
            <p>Check Apply beside only the fields you want written to every variant.</p>
        </div>
    </header>

    <div class="admin-commerce-default-grid">
        <?php
        $defaultFields = [
            ['price','Price','number','default_price','0.01',''],
            ['compare_at_price','Compare at','number','default_compare_at_price','0.01',''],
            ['inventory_quantity','Inventory','number','default_inventory_quantity','1','0'],
            ['low_stock_threshold','Low stock warning at','number','default_low_stock_threshold','1','5'],
            ['max_per_order','Max per order','number','default_max_per_order','1','0'],
            ['fulfillment_product_id','Provider product ID','text','default_fulfillment_product_id','',''],
            ['fulfillment_variant_id','Provider variant ID','text','default_fulfillment_variant_id','',''],
        ];
        foreach ($defaultFields as [$key,$label,$type,$name,$step,$value]):
        ?>
        <label class="admin-commerce-default-field">
            <span><?= moderation_e($label) ?></span>
            <input type="<?= $type ?>" name="<?= $name ?>" <?= $step !== '' ? 'step="'.moderation_e($step).'"' : '' ?> value="<?= moderation_e($value) ?>">
            <span class="admin-commerce-apply-toggle"><input type="checkbox" name="apply[<?= moderation_e($key) ?>]" value="1"> Apply</span>
        </label>
        <?php endforeach; ?>

        <label class="admin-commerce-default-field">
            <span>Inventory &amp; selling mode</span>
            <select name="default_inventory_mode">
                <option value="untracked">Always available</option>
                <option value="tracked">Track inventory</option>
                <option value="backorder">Backorder when sold out</option>
                <option value="preorder">Preorder</option>
            </select>
            <span class="admin-commerce-apply-toggle"><input type="checkbox" name="apply[inventory_mode]" value="1"> Apply</span>
        </label>

        <label class="admin-commerce-default-field">
            <span>Fulfillment</span>
            <select name="default_fulfillment_type"><option value="manual">Manual</option><option value="provider">Provider</option><option value="digital">Digital</option></select>
            <span class="admin-commerce-apply-toggle"><input type="checkbox" name="apply[fulfillment_type]" value="1"> Apply</span>
        </label>

        <label class="admin-commerce-default-field">
            <span>Provider</span>
            <select name="default_fulfillment_provider">
                <?php foreach (admin_shop_fulfillment_providers() as $providerValue => $providerLabel): ?>
                    <option value="<?= moderation_e($providerValue) ?>"><?= moderation_e($providerLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="admin-commerce-apply-toggle"><input type="checkbox" name="apply[fulfillment_provider]" value="1"> Apply</span>
        </label>

        <?php foreach ([
            ['is_active','Active','default_is_active'],
        ] as [$key,$label,$name]): ?>
        <label class="admin-commerce-default-field">
            <span><?= moderation_e($label) ?></span>
            <select name="<?= moderation_e($name) ?>"><option value="1">Yes</option><option value="0">No</option></select>
            <span class="admin-commerce-apply-toggle"><input type="checkbox" name="apply[<?= moderation_e($key) ?>]" value="1"> Apply</span>
        </label>
        <?php endforeach; ?>
    </div>

    <label class="admin-commerce-resequence">
        <input type="checkbox" name="resequence_sort" value="1">
        <span><strong>Resequence sort order 1 â <?= count($variants) ?></strong><small>Preserves the current order, then rewrites clean sequential numbers.</small></span>
    </label>

    <div class="admin-user-form-actions"><button class="admin-button" type="submit">Apply selected defaults</button></div>
</form>

<div class="admin-commerce-variants">

<?php foreach ($variants as $variant): ?>
<?php $storefrontMeta = admin_shop_variant_storefront_meta($variant); ?>

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
    <span>On-hand inventory</span>
    <input
        type="number"
        name="inventory_quantity"
        step="1"
        value="<?= (int) $variant['inventory_quantity'] ?>"
    >
</label>

<label>
    <span>Inventory &amp; selling mode</span>
    <select name="inventory_mode">
        <option value="untracked" <?= $storefrontMeta['inventory_mode'] === 'untracked' ? 'selected' : '' ?>>Always available</option>
        <option value="tracked" <?= $storefrontMeta['inventory_mode'] === 'tracked' ? 'selected' : '' ?>>Track inventory</option>
        <option value="backorder" <?= $storefrontMeta['inventory_mode'] === 'backorder' ? 'selected' : '' ?>>Backorder when sold out</option>
        <option value="preorder" <?= $storefrontMeta['inventory_mode'] === 'preorder' ? 'selected' : '' ?>>Preorder</option>
    </select>
</label>

<label>
    <span>Low stock warning at</span>
    <input
        type="number"
        name="low_stock_threshold"
        min="0"
        step="1"
        value="<?= (int) $storefrontMeta['low_stock_threshold'] ?>"
    >
</label>

<label>
    <span>Max per order</span>
    <input
        type="number"
        name="max_per_order"
        min="0"
        max="20"
        step="1"
        value="<?= (int) $storefrontMeta['max_per_order'] ?>"
        placeholder="0 = automatic"
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
    <?php $selectedProvider = admin_shop_normalize_provider((string) ($variant['fulfillment_provider'] ?? '')); ?>
    <select name="fulfillment_provider">
        <?php foreach (admin_shop_fulfillment_providers() as $providerValue => $providerLabel): ?>
            <option
                value="<?= moderation_e($providerValue) ?>"
                <?= $selectedProvider === $providerValue ? 'selected' : '' ?>
            >
                <?= moderation_e($providerLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
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

<div class="admin-commerce-inventory-explainer is-wide">
    <strong><?= moderation_e(match ($storefrontMeta['inventory_mode']) {
        'tracked' => 'Tracked inventory',
        'backorder' => 'Backorder when sold out',
        'preorder' => 'Preorder enabled',
        default => 'Inventory not tracked',
    }) ?></strong>
    <span><?= moderation_e(match ($storefrontMeta['inventory_mode']) {
        'tracked' => 'Inventory controls In stock, Low stock, and Out of stock automatically.',
        'backorder' => 'Inventory is tracked. At zero, customers can still order and the storefront shows Backorder.',
        'preorder' => 'Customers can order before normal fulfillment begins. Inventory count does not control availability.',
        default => 'This variant remains available regardless of the inventory number.',
    }) ?></span>
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

</div>
</details>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
