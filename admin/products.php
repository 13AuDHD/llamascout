<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once dirname(__DIR__) . '/app/admin-shop-variant-workflow.php';
require_once dirname(__DIR__) . '/app/admin-shop-product-lifecycle.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId = (int) ($adminUser['id'] ?? 0);

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
            $action = trim(
                (string) ($_POST['shop_admin_action'] ?? '')
            );

            $productId =
                (int) ($_POST['product_id'] ?? 0);

            if ($action === 'archive-product') {
                admin_shop_set_product_lifecycle_status(
                    $db,
                    $actorUserId,
                    $productId,
                    'archived'
                );

                $notice =
                    'Product archived and removed from the public Shop.';
            } elseif ($action === 'restore-product') {
                admin_shop_set_product_lifecycle_status(
                    $db,
                    $actorUserId,
                    $productId,
                    'draft'
                );

                $notice =
                    'Product restored as a draft.';
            } elseif ($action === 'publish-product') {
                admin_shop_set_product_lifecycle_status(
                    $db,
                    $actorUserId,
                    $productId,
                    'active'
                );

                $notice =
                    'Product published to the Shop.';
            } elseif ($action === 'delete-product') {
                admin_shop_delete_product_permanently(
                    $db,
                    $actorUserId,
                    $productId
                );

                $notice =
                    'Unused product permanently deleted.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.products_lifecycle',
                    [
                        'product_id' => $productId ?? 0,
                        'action' => $action ?? '',
                    ],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The product could not be updated.',
                        $reference
                    );
        }
    }
}

$products = admin_shop_products($db);

$grouped = [
    'active' => [],
    'draft' => [],
    'archived' => [],
];

foreach ($products as $product) {
    $status = strtolower(
        trim((string) ($product['status'] ?? 'draft'))
    );

    if (!isset($grouped[$status])) {
        $status = 'draft';
    }

    $product['_lifecycle'] =
        admin_shop_product_lifecycle_info(
            $db,
            (int) $product['id']
        );

    $grouped[$status][] = $product;
}

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Products';
$adminPageEyebrow = 'Commerce';
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


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Catalog</p>
        <h2><?= number_format(count($products)) ?> products</h2>
    </div>

    <a class="admin-button" href="/product-new.php">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        New product
    </a>
</header>

<dl class="admin-user-definition-list">
    <div>
        <dt>Active</dt>
        <dd><?= number_format(count($grouped['active'])) ?></dd>
    </div>

    <div>
        <dt>Draft</dt>
        <dd><?= number_format(count($grouped['draft'])) ?></dd>
    </div>

    <div>
        <dt>Archived</dt>
        <dd><?= number_format(count($grouped['archived'])) ?></dd>
    </div>
</dl>

</section>


<?php
$statusSections = [
    'active' => [
        'eyebrow' => 'Public Shop',
        'title' => 'Active Products',
        'empty' => 'No active products.',
    ],
    'draft' => [
        'eyebrow' => 'Work in Progress',
        'title' => 'Draft Products',
        'empty' => 'No draft products.',
    ],
    'archived' => [
        'eyebrow' => 'Retired Catalog',
        'title' => 'Archived Products',
        'empty' => 'No archived products.',
    ],
];
?>

<?php foreach ($statusSections as $statusKey => $section): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p><?= moderation_e($section['eyebrow']) ?></p>
        <h2><?= moderation_e($section['title']) ?></h2>
    </div>

    <span>
        <?= number_format(count($grouped[$statusKey])) ?>
    </span>
</header>

<?php if (!$grouped[$statusKey]): ?>

<div class="admin-empty-state">
    <i class="fa-solid fa-box-open" aria-hidden="true"></i>
    <h3><?= moderation_e($section['empty']) ?></h3>
</div>

<?php else: ?>

<div class="admin-commerce-product-grid">

<?php foreach ($grouped[$statusKey] as $product): ?>
<?php
$lifecycle = $product['_lifecycle'];
$orderHistoryCount =
    (int) $lifecycle['order_history_count'];
?>

<article class="admin-commerce-product">

<div class="admin-commerce-product-image">
    <?php if (!empty($product['primary_image_url'])): ?>
        <img
            src="<?= moderation_e(
                admin_shop_image_url(
                    (string) $product['primary_image_url'],
                    $siteUrl
                )
            ) ?>"
            alt="<?= moderation_e(
                (string) $product['name']
            ) ?>"
            loading="lazy"
        >
    <?php else: ?>
        <i class="fa-solid fa-box-open" aria-hidden="true"></i>
    <?php endif; ?>
</div>

<div class="admin-commerce-product-content">

<div class="admin-commerce-product-heading">
    <div>
        <span>
            <?= moderation_e(
                (string) (
                    $product['product_type']
                    ?: 'Product'
                )
            ) ?>
        </span>

        <h2>
            <?= moderation_e(
                (string) $product['name']
            ) ?>
        </h2>
    </div>

    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst((string) $product['status'])
        ) ?>
    </span>
</div>

<p>
    <?= moderation_e(
        (string) (
            $product['short_description']
            ?: 'No short description.'
        )
    ) ?>
</p>

<dl>
    <div>
        <dt>Variants</dt>
        <dd>
            <?= number_format(
                (int) $product['active_variant_count']
            ) ?>
            active /
            <?= number_format(
                (int) $product['variant_count']
            ) ?>
        </dd>
    </div>

    <div>
        <dt>Order history</dt>
        <dd>
            <?= number_format($orderHistoryCount) ?>
            item<?= $orderHistoryCount === 1 ? '' : 's' ?>
        </dd>
    </div>

    <div>
        <dt>Price</dt>
        <dd>
            <?php if ($product['min_price_cents'] === null): ?>
                No active price
            <?php elseif (
                (int) $product['min_price_cents']
                ===
                (int) $product['max_price_cents']
            ): ?>
                <?= moderation_e(
                    admin_shop_money(
                        (int) $product['min_price_cents']
                    )
                ) ?>
            <?php else: ?>
                <?= moderation_e(
                    admin_shop_money(
                        (int) $product['min_price_cents']
                    )
                ) ?>
                -
                <?= moderation_e(
                    admin_shop_money(
                        (int) $product['max_price_cents']
                    )
                ) ?>
            <?php endif; ?>
        </dd>
    </div>
</dl>

<div class="admin-user-form-actions">

<a
    class="admin-button"
    href="/product.php?id=<?= (int) $product['id'] ?>"
>
    Manage product
</a>

<?php if ($statusKey === 'active'): ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="archive-product">

    <button class="admin-button" type="submit">
        Archive
    </button>
</form>

<?php elseif ($statusKey === 'draft'): ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="publish-product">

    <button class="admin-button" type="submit">
        Publish
    </button>
</form>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="archive-product">

    <button class="admin-button" type="submit">
        Archive
    </button>
</form>

<?php elseif ($statusKey === 'archived'): ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="restore-product">

    <button class="admin-button" type="submit">
        Restore to draft
    </button>
</form>

<?php endif; ?>

<?php if (!empty($lifecycle['can_delete'])): ?>

<form
    method="post"
    onsubmit="return confirm('Permanently delete this unused product? This cannot be undone.');"
>
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="shop_admin_action" value="delete-product">

    <button class="admin-button" type="submit">
        Delete permanently
    </button>
</form>

<?php else: ?>

<span class="admin-status-pill">
    Protected by order history
</span>

<?php endif; ?>

</div>

</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php endforeach; ?>

<?php require __DIR__ . '/_footer.php'; ?>
