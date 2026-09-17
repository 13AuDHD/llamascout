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

$productTypes = [
    'T-Shirt',
    'Hat',
    'Socks',
    'Outerwear',
    'Camping Gear',
    'Trail Gear',
    'Drinkware',
    'Accessories',
    'Stickers',
    'Other',
];

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
            $reference = llama_log_caught_exception(
                $exception,
                'admin.product_create',
                [],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The product could not be created.',
                    $reference
                );
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

$selectedProductType =
    trim(
        (string) (
            $_POST['product_type']
            ?? ''
        )
    );
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
    <span>Product type</span>
    <select name="product_type">
        <option value="">
            Select type
        </option>

        <?php foreach ($productTypes as $productType): ?>
            <option
                value="<?= moderation_e($productType) ?>"
                <?= $selectedProductType === $productType
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e($productType) ?>
            </option>
        <?php endforeach; ?>
    </select>
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
