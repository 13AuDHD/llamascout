<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printify.php';
require_once dirname(__DIR__) . '/app/printify-mapping.php';
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
            $catalog = llama_printify_catalog();

            $action = trim(
                (string) (
                    $_POST['printify_action']
                    ?? ''
                )
            );

            if ($action === 'map') {
                llama_printify_save_manual_mapping(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['local_variant_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['printify_product_id']
                        ?? ''
                    ),
                    (int) (
                        $_POST['printify_variant_id']
                        ?? 0
                    ),
                    $catalog
                );

                $notice =
                    'Printify variant mapping saved.';
            } elseif ($action === 'unmap') {
                llama_printify_unmap_local_variant(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['local_variant_id']
                        ?? 0
                    )
                );

                $notice =
                    'Printify variant mapping removed.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.printify_mapping',
                    [],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The Printify mapping could not be saved.',
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

$adminPageTitle = 'Printify Catalog';
$adminPageEyebrow = 'Integrations';
$adminActiveNav = 'integrations';

$catalog = [
    'products' => [],
    'variants' => [],
    'variants_by_sku' => [],
];

$catalogError = '';
$shop = [];

try {
    if (llama_printify_configured()) {
        $shop = llama_printify_shop();
        $catalog = llama_printify_catalog();
    }
} catch (Throwable $exception) {
    $catalogError = $exception->getMessage();

    llama_log_caught_exception(
        $exception,
        'admin.printify_catalog'
    );
}

$localVariants =
    llama_printify_local_physical_variants(
        $db
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

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Printify</p>
        <h2>Catalog &amp; Variant Mapping</h2>
    </div>

    <a class="admin-button" href="/integrations.php">
        Back to Integrations
    </a>
</header>

<?php if (!llama_printify_configured()): ?>

<div class="admin-empty-state">
    <i aria-hidden="true"><?= llama_icon('plug') ?></i>
    <h3>Printify is not configured.</h3>
    <p>Add the private API token to /private/printify.php.</p>
</div>

<?php elseif ($catalogError !== ''): ?>

<div class="admin-empty-state">
    <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>
    <h3>Printify catalog unavailable.</h3>
    <p><?= moderation_e($catalogError) ?></p>
</div>

<?php elseif (!$catalog['products']): ?>

<div class="admin-empty-state">
    <i aria-hidden="true"><?= llama_icon('shirt') ?></i>
    <h3>No Printify products found.</h3>
    <p>Create a product in the connected Printify API store first.</p>
</div>

<?php else: ?>

<div class="admin-printify-explainer">
    <i aria-hidden="true"><?= llama_icon('link') ?></i>
    <div>
        <strong>Match what Printify makes to what Llama Scout sells.</strong>
        <span>
            Each Llama Scout Shop variant points to one Printify product and variant ID.
            Exact SKU matches are useful, but manual mapping is always available.
        </span>
    </div>
</div>

<?php if ($shop): ?>
<div class="admin-printify-store-summary">
    <span>Connected store</span>
    <strong><?= moderation_e((string) ($shop['title'] ?? $shop['name'] ?? 'Printify API Store')) ?></strong>
    <small>Store ID <?= moderation_e((string) ($shop['id'] ?? 'Unknown')) ?></small>
</div>
<?php endif; ?>

<div class="admin-printify-products">

<?php foreach ($catalog['products'] as $product): ?>

<article class="admin-printify-product">
<header>
    <div class="admin-printify-product-image">
        <?php if (trim((string) ($product['thumbnail_url'] ?? '')) !== ''): ?>
            <img src="<?= moderation_e((string) $product['thumbnail_url']) ?>" alt="">
        <?php else: ?>
            <i aria-hidden="true"><?= llama_icon('shirt') ?></i>
        <?php endif; ?>
    </div>

    <div>
        <p>Printify Product</p>
        <h3><?= moderation_e((string) ($product['name'] ?: 'Unnamed Printify product')) ?></h3>
        <span>
            Product ID <?= moderation_e((string) $product['id']) ?>
            |
            <?= number_format((int) $product['variant_count']) ?>
            variant<?= (int) $product['variant_count'] === 1 ? '' : 's' ?>
        </span>
    </div>
</header>

<div class="admin-printify-variants">

<?php foreach ($catalog['variants'] as $remote): ?>
<?php if ((string) $remote['product_id'] !== (string) $product['id']) { continue; } ?>

<?php
$currentLocal = null;

foreach ($localVariants as $candidate) {
    if (
        strtolower(trim((string) ($candidate['fulfillment_provider'] ?? ''))) === 'printify'
        && (string) ($candidate['fulfillment_product_id'] ?? '') === (string) $remote['product_id']
        && (int) ($candidate['fulfillment_variant_id'] ?? 0) === (int) $remote['variant_id']
    ) {
        $currentLocal = $candidate;
        break;
    }
}
?>

<section class="admin-printify-variant">
<div class="admin-printify-variant-info">
    <div>
        <strong><?= moderation_e((string) ($remote['name'] ?: 'Printify variant')) ?></strong>
        <span>
            Variant ID <?= (int) $remote['variant_id'] ?>
            <?php if (!empty($remote['enabled'])): ?> | Enabled<?php endif; ?>
            <?php if (empty($remote['available'])): ?> | Unavailable<?php endif; ?>
        </span>
    </div>

    <?php if (trim((string) ($remote['sku'] ?? '')) !== ''): ?>
        <code><?= moderation_e((string) $remote['sku']) ?></code>
    <?php endif; ?>
</div>

<?php if ($currentLocal): ?>

<div class="admin-printify-mapped">
    <div>
        <span>Currently mapped to</span>
        <strong>
            <?= moderation_e((string) $currentLocal['product_name']) ?>
            /
            <?= moderation_e((string) $currentLocal['variant_name']) ?>
        </strong>
        <small><?= moderation_e((string) ($currentLocal['sku'] ?: 'No Llama Scout SKU')) ?></small>
    </div>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
        <input type="hidden" name="printify_action" value="unmap">
        <input type="hidden" name="local_variant_id" value="<?= (int) $currentLocal['id'] ?>">
        <button class="admin-button" type="submit">Remove mapping</button>
    </form>
</div>

<?php else: ?>

<form class="admin-printify-map" method="post">
    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
    <input type="hidden" name="printify_action" value="map">
    <input type="hidden" name="printify_product_id" value="<?= moderation_e((string) $remote['product_id']) ?>">
    <input type="hidden" name="printify_variant_id" value="<?= (int) $remote['variant_id'] ?>">

    <label>
        <span>Map to Llama Scout variant</span>
        <select name="local_variant_id" required>
            <option value="">Choose a Shop variant</option>

            <?php foreach ($localVariants as $local): ?>
            <?php
            $provider = strtolower(trim((string) ($local['fulfillment_provider'] ?? '')));
            $hasRemoteMapping =
                trim((string) ($local['fulfillment_product_id'] ?? '')) !== ''
                || trim((string) ($local['fulfillment_variant_id'] ?? '')) !== '';
            $alreadyMapped = $hasRemoteMapping && $provider !== 'printify';
            $mappedToPrintify = $provider === 'printify' && $hasRemoteMapping;
            ?>

            <option
                value="<?= (int) $local['id'] ?>"
                <?= ($alreadyMapped || $mappedToPrintify) ? 'disabled' : '' ?>
            >
                <?= moderation_e((string) $local['product_name']) ?>
                /
                <?= moderation_e((string) $local['variant_name']) ?>
                <?php if (trim((string) ($local['sku'] ?? '')) !== ''): ?>
                    [<?= moderation_e((string) $local['sku']) ?>]
                <?php endif; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button class="admin-button" type="submit">Save mapping</button>
</form>

<?php endif; ?>
</section>

<?php endforeach; ?>
</div>
</article>

<?php endforeach; ?>
</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
