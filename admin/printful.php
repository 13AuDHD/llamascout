<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printful.php';
require_once dirname(__DIR__) . '/app/printful-mapping.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

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
            $catalog =
                llama_printful_catalog();

            $action = trim(
                (string) (
                    $_POST['printful_action']
                    ?? ''
                )
            );

            if ($action === 'map') {
                llama_printful_save_manual_mapping(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['local_variant_id']
                        ?? 0
                    ),
                    (int) (
                        $_POST['sync_variant_id']
                        ?? 0
                    ),
                    $catalog
                );

                $notice =
                    'Printful variant mapping saved.';
            } elseif ($action === 'unmap') {
                llama_printful_unmap_local_variant(
                    $db,
                    $actorUserId,
                    (int) (
                        $_POST['local_variant_id']
                        ?? 0
                    )
                );

                $notice =
                    'Printful variant mapping removed.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.printful_mapping',
                    [],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The Printful mapping could not be saved.',
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

$adminPageTitle = 'Printful Catalog';
$adminPageEyebrow = 'Integrations';
$adminActiveNav = 'integrations';

$catalog = [
    'products' => [],
    'variants' => [],
    'variants_by_sku' => [],
];

$catalogError = '';

try {
    $catalog = llama_printful_catalog();
} catch (Throwable $exception) {
    $catalogError =
        $exception->getMessage();

    llama_log_caught_exception(
        $exception,
        'admin.printful_catalog'
    );
}

$localVariants =
    llama_printful_local_physical_variants(
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
        <p>Printful</p>
        <h2>Catalog &amp; Variant Mapping</h2>
    </div>

    <a
        class="admin-button"
        href="/integrations.php"
    >
        Back to Integrations
    </a>
</header>

<?php if ($catalogError !== ''): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-triangle-exclamation"
        aria-hidden="true"
    ></i>

    <h3>Printful catalog unavailable.</h3>

    <p>
        <?= moderation_e($catalogError) ?>
    </p>
</div>

<?php elseif (!$catalog['products']): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-shirt"
        aria-hidden="true"
    ></i>

    <h3>No Printful products found.</h3>

    <p>
        Create or publish a product to the connected
        Llama Scout store in Printful first.
    </p>
</div>

<?php else: ?>

<div class="admin-printful-explainer">
    <i
        class="fa-solid fa-link"
        aria-hidden="true"
    ></i>

    <div>
        <strong>
            Match what Printful makes to what Llama Scout sells.
        </strong>

        <span>
            Names do not have to match. Each Llama Scout Shop
            variant simply needs to point to the correct Printful
            Sync Variant.
        </span>
    </div>
</div>


<div class="admin-printful-products">

<?php foreach (
    $catalog['products']
    as $product
): ?>

<article class="admin-printful-product">

<header>

<div class="admin-printful-product-image">

<?php if (
    trim(
        (string) (
            $product['thumbnail_url']
            ?? ''
        )
    ) !== ''
): ?>

<img
    src="<?= moderation_e(
        (string) $product['thumbnail_url']
    ) ?>"
    alt=""
>

<?php else: ?>

<i
    class="fa-solid fa-shirt"
    aria-hidden="true"
></i>

<?php endif; ?>

</div>

<div>
    <p>Printful Product</p>

    <h3>
        <?= moderation_e(
            (string) (
                $product['name']
                ?: 'Unnamed Printful product'
            )
        ) ?>
    </h3>

    <span>
        Sync Product ID
        <?= (int) $product['id'] ?>
        |
        <?= number_format(
            (int) $product['variant_count']
        ) ?>
        variant<?= (int) $product['variant_count'] === 1 ? '' : 's' ?>
    </span>
</div>

</header>


<div class="admin-printful-variants">

<?php foreach (
    $catalog['variants']
    as $remote
): ?>

<?php if (
    (int) $remote['sync_product_id']
    !==
    (int) $product['id']
) {
    continue;
} ?>

<?php
$currentLocal = null;

foreach (
    $localVariants
    as $candidate
) {
    if (
        strtolower(
            trim(
                (string) (
                    $candidate['fulfillment_provider']
                    ?? ''
                )
            )
        ) === 'printful'
        &&
        (int) (
            $candidate['fulfillment_variant_id']
            ?? 0
        ) ===
        (int) $remote['sync_variant_id']
    ) {
        $currentLocal = $candidate;
        break;
    }
}
?>

<section class="admin-printful-variant">

<div class="admin-printful-variant-info">

<div>
    <strong>
        <?= moderation_e(
            (string) (
                $remote['name']
                ?: 'Printful variant'
            )
        ) ?>
    </strong>

    <span>
        Sync Variant ID
        <?= (int) $remote['sync_variant_id'] ?>
    </span>
</div>

<?php if (
    trim(
        (string) ($remote['sku'] ?? '')
    ) !== ''
): ?>

<code>
    <?= moderation_e(
        (string) $remote['sku']
    ) ?>
</code>

<?php endif; ?>

</div>


<?php if ($currentLocal): ?>

<div class="admin-printful-mapped">

<div>
    <span>Currently mapped to</span>

    <strong>
        <?= moderation_e(
            (string) $currentLocal['product_name']
        ) ?>
        /
        <?= moderation_e(
            (string) $currentLocal['variant_name']
        ) ?>
    </strong>

    <small>
        <?= moderation_e(
            (string) (
                $currentLocal['sku']
                ?: 'No Llama Scout SKU'
            )
        ) ?>
    </small>
</div>

<form method="post">

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>

<input
    type="hidden"
    name="printful_action"
    value="unmap"
>

<input
    type="hidden"
    name="local_variant_id"
    value="<?= (int) $currentLocal['id'] ?>"
>

<button
    class="admin-button"
    type="submit"
>
    Remove mapping
</button>

</form>

</div>

<?php else: ?>

<form
    class="admin-printful-map"
    method="post"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(moderation_csrf_token()) ?>"
>

<input
    type="hidden"
    name="printful_action"
    value="map"
>

<input
    type="hidden"
    name="sync_variant_id"
    value="<?= (int) $remote['sync_variant_id'] ?>"
>

<label>

<span>Map to Llama Scout variant</span>

<select
    name="local_variant_id"
    required
>

<option value="">
    Choose a Shop variant
</option>

<?php foreach (
    $localVariants
    as $local
): ?>

<?php
$alreadyMapped =
    strtolower(
        trim(
            (string) (
                $local['fulfillment_provider']
                ?? ''
            )
        )
    ) === 'printful'
    &&
    trim(
        (string) (
            $local['fulfillment_variant_id']
            ?? ''
        )
    ) !== '';
?>

<option
    value="<?= (int) $local['id'] ?>"
    <?= $alreadyMapped
        ? 'disabled'
        : '' ?>
>
    <?= moderation_e(
        (string) $local['product_name']
    ) ?>
    /
    <?= moderation_e(
        (string) $local['variant_name']
    ) ?>

    <?php if (
        trim(
            (string) (
                $local['sku']
                ?? ''
            )
        ) !== ''
    ): ?>
        [
        <?= moderation_e(
            (string) $local['sku']
        ) ?>
        ]
    <?php endif; ?>
</option>

<?php endforeach; ?>

</select>

</label>

<button
    class="admin-button"
    type="submit"
>
    Save mapping
</button>

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
