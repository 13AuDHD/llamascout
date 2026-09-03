<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printful.php';
require_once dirname(__DIR__) . '/app/printful-mapping.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

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
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim(
                (string) ($_POST['integration_action'] ?? '')
            );

            if ($action === 'apply-printful-exact-mappings') {
                $catalogForMapping = llama_printful_catalog();

                $applied = llama_printful_apply_exact_mappings(
                    $db,
                    $actorUserId,
                    $catalogForMapping
                );

                $notice = $applied > 0
                    ? number_format($applied) .
                        ' exact Printful mapping' .
                        ($applied === 1 ? '' : 's') .
                        ' applied.'
                    : 'No new exact Printful mappings were available.';
            }
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.printful_mapping',
                [],
                [InvalidArgumentException::class]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'Printful mappings could not be updated.',
                    $reference
                );
        }
    }
}

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Integrations';
$adminPageEyebrow = 'Configuration';
$adminActiveNav = 'integrations';

$printfulConfigured =
    llama_printful_configured();

$printfulError = null;
$printfulStores = [];
$printfulCatalog = [
    'products' => [],
    'variants' => [],
    'variants_by_sku' => [],
];
$printfulDiagnostics = [];

if ($printfulConfigured) {
    try {
        $printfulStores =
            llama_printful_stores();

        $printfulCatalog =
            llama_printful_catalog();

        $printfulDiagnostics =
            llama_printful_mapping_diagnostics(
                $db,
                $printfulCatalog
            );
    } catch (Throwable $exception) {
        $printfulError =
            $exception->getMessage();

        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {
            llama_log_caught_exception(
                $exception,
                'admin.printful_integration'
            );
        }
    }
}

$mappedCount =
    count(
        array_filter(
            $printfulDiagnostics,
            static fn(array $row): bool =>
                ($row['status'] ?? '')
                === 'mapped'
        )
    );

$suggestedCount =
    count(
        array_filter(
            $printfulDiagnostics,
            static fn(array $row): bool =>
                ($row['status'] ?? '')
                === 'suggested'
        )
    );

$problemCount =
    count(
        array_filter(
            $printfulDiagnostics,
            static fn(array $row): bool =>
                in_array(
                    $row['status'] ?? '',
                    [
                        'invalid',
                        'ambiguous',
                        'missing_sku',
                        'unmapped',
                    ],
                    true
                )
        )
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

<section class="admin-integration-grid">

<section class="admin-panel admin-integration-card">

<header class="admin-panel-header">
    <div>
        <p>Fulfillment</p>
        <h2>Printful</h2>
    </div>

    <?php if (!$printfulConfigured): ?>
        <span class="admin-status-pill">
            Not configured
        </span>
    <?php elseif ($printfulError): ?>
        <span class="admin-status-pill">
            Connection problem
        </span>
    <?php else: ?>
        <span class="admin-status-pill">
            Connected
        </span>
    <?php endif; ?>
</header>

<div class="admin-integration-summary">

<div>
    <span>Private token</span>
    <strong>
        <?= $printfulConfigured
            ? 'Configured'
            : 'Missing' ?>
    </strong>
</div>

<div>
    <span>Store header</span>
    <strong>
        <?= llama_printful_store_id() !== ''
            ? moderation_e(
                llama_printful_store_id()
            )
            : 'Not required' ?>
    </strong>
</div>

<div>
    <span>Auto confirm</span>
    <strong>
        <?= llama_printful_auto_confirm()
            ? 'Enabled'
            : 'Disabled' ?>
    </strong>
</div>

<div>
    <span>API products</span>
    <strong>
        <?= number_format(
            count(
                $printfulCatalog['products']
            )
        ) ?>
    </strong>
</div>

</div>

<?php if (!$printfulConfigured): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-plug"
        aria-hidden="true"
    ></i>

    <h3>Printful token missing.</h3>

    <p>
        Add the store-level private token to
        /private/printful.php.
    </p>
</div>

<?php elseif ($printfulError): ?>

<div class="admin-integration-error">
    <strong>
        Printful could not be reached.
    </strong>

    <p>
        <?= moderation_e(
            $printfulError
        ) ?>
    </p>
</div>

<?php else: ?>

<div class="admin-integration-store">

<span>Authorized store</span>

<?php if ($printfulStores): ?>
<?php foreach ($printfulStores as $store): ?>

<div>
    <strong>
        <?= moderation_e(
            (string) (
                $store['name']
                ?? 'Printful store'
            )
        ) ?>
    </strong>

    <span>
        Store ID
        <?= moderation_e(
            (string) (
                $store['id']
                ?? 'Unknown'
            )
        ) ?>
    </span>
</div>

<?php endforeach; ?>
<?php else: ?>

<div>
    <strong>
        Store token accepted
    </strong>
    <span>
        Store metadata was not returned.
    </span>
</div>

<?php endif; ?>

</div>

<?php endif; ?>

</section>


<section class="admin-panel admin-integration-card">

<header class="admin-panel-header">
    <div>
        <p>Shipping</p>
        <h2>EasyPost</h2>
    </div>

    <span class="admin-status-pill">
        Pending
    </span>
</header>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-truck-fast"
        aria-hidden="true"
    ></i>

    <h3>Approval pending.</h3>

    <p>
        Llama Scout Fulfillment is ready for
        rate shopping and label purchasing once
        EasyPost access is available.
    </p>
</div>

</section>


<section class="admin-panel admin-integration-card">

<header class="admin-panel-header">
    <div>
        <p>Fulfillment</p>
        <h2>Printify</h2>
    </div>

    <span class="admin-status-pill">
        Next
    </span>
</header>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-boxes-packing"
        aria-hidden="true"
    ></i>

    <h3>Integration queued.</h3>

    <p>
        Printify will use the same provider-routing
        architecture after Printful is verified.
    </p>
</div>

</section>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Printful</p>
        <h2>Variant Mapping Health</h2>
    </div>

    <span>
        <?= number_format(
            count(
                $printfulDiagnostics
            )
        ) ?>
        local Printful variant<?= count($printfulDiagnostics) === 1 ? '' : 's' ?>
    </span>
</header>

<?php if ($printfulError): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-triangle-exclamation"
        aria-hidden="true"
    ></i>

    <h3>Mappings cannot be checked.</h3>

    <p>
        Restore the Printful API connection first.
    </p>
</div>

<?php elseif (!$printfulDiagnostics): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-shirt"
        aria-hidden="true"
    ></i>

    <h3>No local Printful variants yet.</h3>

    <p>
        Set a Shop variant's fulfillment provider to
        Printful and it will appear here.
    </p>
</div>

<?php else: ?>

<div class="admin-integration-health">

<div>
    <span>Mapped</span>
    <strong>
        <?= number_format(
            $mappedCount
        ) ?>
    </strong>
</div>

<div>
    <span>Exact SKU matches</span>
    <strong>
        <?= number_format(
            $suggestedCount
        ) ?>
    </strong>
</div>

<div>
    <span>Needs attention</span>
    <strong>
        <?= number_format(
            $problemCount
        ) ?>
    </strong>
</div>

</div>

<?php if ($suggestedCount > 0): ?>
<form
    class="admin-integration-mapping-action"
    method="post"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(moderation_csrf_token()) ?>"
    >
    <input
        type="hidden"
        name="integration_action"
        value="apply-printful-exact-mappings"
    >

    <div>
        <strong>Safe exact matches available</strong>
        <span>
            Llama Scout found one and only one Printful variant with the same SKU for
            <?= number_format($suggestedCount) ?> local variant<?= $suggestedCount === 1 ? '' : 's' ?>.
        </span>
    </div>

    <button class="admin-button" type="submit">
        Apply exact Printful mappings
    </button>
</form>
<?php endif; ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>Llama Scout variant</th>
    <th>SKU</th>
    <th>Status</th>
    <th>Printful match</th>
    <th>IDs</th>
</tr>
</thead>

<tbody>

<?php foreach ($printfulDiagnostics as $diagnostic): ?>
<?php
$local =
    $diagnostic['local'];

$matches =
    $diagnostic['matches'];

$firstMatch =
    count($matches) === 1
        ? $matches[0]
        : null;
?>

<tr>

<td data-label="Llama Scout variant">
    <strong>
        <?= moderation_e(
            (string) $local['product_name']
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $local['variant_name']
        ) ?>
    </span>
</td>

<td data-label="SKU">
    <?= moderation_e(
        (string) (
            $local['sku']
            ?: 'Missing'
        )
    ) ?>
</td>

<td data-label="Status">
    <span
        class="admin-status-pill admin-mapping-status is-<?= moderation_e(
            (string) $diagnostic['status']
        ) ?>"
    >
        <?= moderation_e(
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) $diagnostic['status']
                )
            )
        ) ?>
    </span>

    <small>
        <?= moderation_e(
            (string) $diagnostic['message']
        ) ?>
    </small>
</td>

<td data-label="Printful match">

<?php if ($firstMatch): ?>

<strong>
    <?= moderation_e(
        (string) $firstMatch['sync_product_name']
    ) ?>
</strong>

<span>
    <?= moderation_e(
        (string) $firstMatch['name']
    ) ?>
</span>

<?php elseif (count($matches) > 1): ?>

<strong>
    <?= number_format(
        count($matches)
    ) ?>
    exact SKU matches
</strong>

<span>
    Resolve the duplicate SKU in Printful.
</span>

<?php else: ?>

<span>
    No exact SKU match.
</span>

<?php endif; ?>

</td>

<td data-label="IDs">

<?php if (
    $diagnostic['configured_variant_id'] !== ''
): ?>

<span>
    Product:
    <?= moderation_e(
        $diagnostic['configured_product_id']
        !== ''
            ? $diagnostic['configured_product_id']
            : 'Unknown'
    ) ?>
</span>

<span>
    Variant:
    <?= moderation_e(
        $diagnostic['configured_variant_id']
    ) ?>
</span>

<?php elseif ($firstMatch): ?>

<span>
    Product:
    <?= (int) $firstMatch['sync_product_id'] ?>
</span>

<span>
    Variant:
    <?= (int) $firstMatch['sync_variant_id'] ?>
</span>

<?php else: ?>

<span>
    Not mapped
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
