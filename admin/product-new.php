<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

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
            $productId =
                admin_shop_create_product(
                    $db,
                    $actorUserId,
                    $_POST
                );

            header(
                'Location: /product.php?id=' .
                $productId
            );

            exit;
        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'New Product';
$adminPageEyebrow = 'Commerce';
$adminActiveNav = 'products';

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>
<?php endif; ?>


<section class="admin-panel admin-commerce-new-product">

<header class="admin-panel-header">
    <div>
        <p>Catalog</p>
        <h2>Create Product</h2>
    </div>
</header>

<form
    class="admin-user-form"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<div class="admin-user-form-grid">

<label>
    <span>Product name</span>
    <input
        type="text"
        name="name"
        maxlength="200"
        value="<?= moderation_e(
            (string) ($_POST['name'] ?? '')
        ) ?>"
        required
    >
</label>

<label>
    <span>Slug</span>
    <input
        type="text"
        name="slug"
        maxlength="160"
        value="<?= moderation_e(
            (string) ($_POST['slug'] ?? '')
        ) ?>"
        placeholder="llama-scout-t-shirt"
        required
    >
</label>

<label>
    <span>Product type</span>
    <input
        type="text"
        name="product_type"
        value="<?= moderation_e(
            (string) ($_POST['product_type'] ?? '')
        ) ?>"
        placeholder="T-shirt, hat, sticker, etc."
    >
</label>

<div class="admin-commerce-checks">
    <label>
        <input
            type="checkbox"
            name="requires_shipping"
            value="1"
            <?= isset($_POST['requires_shipping'])
                || $_SERVER['REQUEST_METHOD'] !== 'POST'
                    ? 'checked'
                    : '' ?>
        >
        <span>Requires shipping</span>
    </label>
</div>

</div>

<p class="admin-commerce-new-product-note">
    The product starts as a draft. After creating it,
    you can add descriptions, pricing, variants,
    inventory, fulfillment details, and product images.
</p>

<div class="admin-user-form-actions">
    <a
        class="admin-button is-muted"
        href="/products.php"
    >
        Cancel
    </a>

    <button
        class="admin-button"
        type="submit"
    >
        Create draft product
    </button>
</div>

</form>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
