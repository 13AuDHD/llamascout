<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-shop.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$products = admin_shop_products($db);
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

<?php if (!$products): ?>

<div class="admin-empty-state">
    <i class="fa-solid fa-shirt" aria-hidden="true"></i>
    <h3>No products yet.</h3>
    <p>
        Products can be added after the catalog editor
        is connected to the existing staged photo uploader.
    </p>
</div>

<?php else: ?>

<div class="admin-commerce-product-grid">

<?php foreach ($products as $product): ?>
    <article class="admin-commerce-product">

        <div class="admin-commerce-product-image">
            <?php if (!empty($product['primary_image_url'])): ?>
                <img
                    src="https://llamascout.com<?= moderation_e(
                        (string) $product['primary_image_url']
                    ) ?>"
                    alt=""
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
                        ucfirst(
                            (string) $product['status']
                        )
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

            <a
                class="admin-button"
                href="/product.php?id=<?= (int) $product['id'] ?>"
            >
                Manage product
            </a>

        </div>

    </article>
<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
