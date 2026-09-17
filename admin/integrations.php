<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/printful.php';
require_once dirname(__DIR__) . '/app/printful-mapping.php';
require_once dirname(__DIR__) . '/app/printful-webhook-security.php';
require_once dirname(__DIR__) . '/app/printify.php';
require_once dirname(__DIR__) . '/app/printify-mapping.php';
require_once dirname(__DIR__) . '/app/printify-webhook-security.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$notice = '';
$error = '';

if (
    ($_SERVER['REQUEST_METHOD'] ?? '')
    === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['integration_action']
                        ?? ''
                    )
                );

            if (
                $action
                === 'apply-printful-exact-mappings'
            ) {
                $catalogForMapping =
                    llama_printful_catalog();

                $applied =
                    llama_printful_apply_exact_mappings(
                        $db,
                        $actorUserId,
                        $catalogForMapping
                    );

                $notice =
                    $applied > 0
                        ? number_format($applied)
                            . ' exact Printful mapping'
                            . ($applied === 1 ? '' : 's')
                            . ' applied.'
                        : 'No new exact Printful mappings were available.';
            } elseif (
                $action
                === 'apply-printify-exact-mappings'
            ) {
                $catalogForMapping =
                    llama_printify_catalog();

                $applied =
                    llama_printify_apply_exact_mappings(
                        $db,
                        $actorUserId,
                        $catalogForMapping
                    );

                $notice =
                    $applied > 0
                        ? number_format($applied)
                            . ' exact Printify mapping'
                            . ($applied === 1 ? '' : 's')
                            . ' applied.'
                        : 'No new exact Printify mappings were available.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.integration_action',
                    [
                        'action' =>
                            $action ?? '',
                    ],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The integration update could not be completed.',
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
    'new_places' =>
        $stats['new_places'],
    'updates' =>
        $stats['updates'],
    'reports' =>
        $stats['reports'],
    'orders' =>
        $stats['orders'],
    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'Integrations';

$adminPageEyebrow =
    'Configuration';

$adminActiveNav =
    'integrations';


/* =========================================================
   PROVIDER DATA
   ========================================================= */

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
$printfulWebhookActive = false;
$printfulWebhookError = null;

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

    if ($printfulError === null) {
        try {
            $printfulWebhookActive =
                llama_printful_secure_webhook_active();
        } catch (Throwable $exception) {
            $printfulWebhookError =
                $exception->getMessage();

            if (
                function_exists(
                    'llama_log_caught_exception'
                )
            ) {
                llama_log_caught_exception(
                    $exception,
                    'admin.printful_webhook_status'
                );
            }
        }
    }
}

$printifyConfigured =
    llama_printify_configured();

$printifyError = null;
$printifyShop = [];
$printifyCatalog = [
    'products' => [],
    'variants' => [],
    'variants_by_sku' => [],
];
$printifyDiagnostics = [];
$printifyWebhookActive = false;
$printifyWebhookError = null;

if ($printifyConfigured) {
    try {
        $printifyShop =
            llama_printify_shop();

        $printifyCatalog =
            llama_printify_catalog();

        $printifyDiagnostics =
            llama_printify_mapping_diagnostics(
                $db,
                $printifyCatalog
            );
    } catch (Throwable $exception) {
        $printifyError =
            $exception->getMessage();

        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {
            llama_log_caught_exception(
                $exception,
                'admin.printify_integration'
            );
        }
    }

    if ($printifyError === null) {
        try {
            $printifyWebhookActive =
                llama_printify_secure_webhooks_active();
        } catch (Throwable $exception) {
            $printifyWebhookError =
                $exception->getMessage();

            if (
                function_exists(
                    'llama_log_caught_exception'
                )
            ) {
                llama_log_caught_exception(
                    $exception,
                    'admin.printify_webhook_status'
                );
            }
        }
    }
}


/* =========================================================
   MAPPING COUNTS
   ========================================================= */

function admin_integration_mapping_counts(
    array $diagnostics
): array {
    $mapped = 0;
    $suggested = 0;
    $problem = 0;

    foreach ($diagnostics as $row) {
        $status =
            (string) (
                $row['status']
                ?? ''
            );

        if ($status === 'mapped') {
            $mapped++;
        } elseif ($status === 'suggested') {
            $suggested++;
        } elseif (
            in_array(
                $status,
                [
                    'invalid',
                    'ambiguous',
                    'missing_sku',
                    'unmapped',
                ],
                true
            )
        ) {
            $problem++;
        }
    }

    return [
        'mapped' => $mapped,
        'suggested' => $suggested,
        'problem' => $problem,
    ];
}

$printfulCounts =
    admin_integration_mapping_counts(
        $printfulDiagnostics
    );

$printifyCounts =
    admin_integration_mapping_counts(
        $printifyDiagnostics
    );

$mappedCount =
    $printfulCounts['mapped'];

$suggestedCount =
    $printfulCounts['suggested'];

$problemCount =
    $printfulCounts['problem'];

$printifyMappedCount =
    $printifyCounts['mapped'];

$printifySuggestedCount =
    $printifyCounts['suggested'];

$printifyProblemCount =
    $printifyCounts['problem'];


/* =========================================================
   MAPPING DASHBOARD
   ========================================================= */

$mappingProvider =
    strtolower(
        trim(
            (string) (
                $_GET['mapping_provider']
                ?? 'printful'
            )
        )
    );

if (
    !in_array(
        $mappingProvider,
        [
            'printful',
            'printify',
        ],
        true
    )
) {
    $mappingProvider =
        'printful';
}

$sort =
    trim(
        (string) (
            $_GET['sort']
            ?? 'attention'
        )
    );

if (
    !in_array(
        $sort,
        [
            'attention',
            'sku',
            'product',
            'status',
        ],
        true
    )
) {
    $sort =
        'attention';
}

$statusPriority = [
    'unmapped' => 0,
    'invalid' => 1,
    'ambiguous' => 2,
    'missing_sku' => 3,
    'suggested' => 4,
    'mapped' => 5,
];

function admin_integration_sort_diagnostics(
    array &$diagnostics,
    string $sort,
    array $statusPriority
): void {
    usort(
        $diagnostics,
        static function (
            array $a,
            array $b
        ) use (
            $sort,
            $statusPriority
        ): int {
            $aLocal =
                $a['local']
                ?? [];

            $bLocal =
                $b['local']
                ?? [];

            $aStatus =
                (string) (
                    $a['status']
                    ?? ''
                );

            $bStatus =
                (string) (
                    $b['status']
                    ?? ''
                );

            $aSku =
                strtolower(
                    trim(
                        (string) (
                            $aLocal['sku']
                            ?? ''
                        )
                    )
                );

            $bSku =
                strtolower(
                    trim(
                        (string) (
                            $bLocal['sku']
                            ?? ''
                        )
                    )
                );

            $aProduct =
                strtolower(
                    trim(
                        (string) (
                            $aLocal['product_name']
                            ?? ''
                        )
                    )
                );

            $bProduct =
                strtolower(
                    trim(
                        (string) (
                            $bLocal['product_name']
                            ?? ''
                        )
                    )
                );

            $aVariant =
                strtolower(
                    trim(
                        (string) (
                            $aLocal['variant_name']
                            ?? ''
                        )
                    )
                );

            $bVariant =
                strtolower(
                    trim(
                        (string) (
                            $bLocal['variant_name']
                            ?? ''
                        )
                    )
                );

            if ($sort === 'sku') {
                $compare =
                    $aSku <=> $bSku;

                if ($compare !== 0) {
                    return $compare;
                }
            } elseif ($sort === 'product') {
                $compare =
                    $aProduct <=> $bProduct;

                if ($compare !== 0) {
                    return $compare;
                }

                $compare =
                    $aVariant <=> $bVariant;

                if ($compare !== 0) {
                    return $compare;
                }
            } elseif ($sort === 'status') {
                $compare =
                    $aStatus <=> $bStatus;

                if ($compare !== 0) {
                    return $compare;
                }
            } else {
                $aPriority =
                    $statusPriority[$aStatus]
                    ?? 99;

                $bPriority =
                    $statusPriority[$bStatus]
                    ?? 99;

                $compare =
                    $aPriority <=> $bPriority;

                if ($compare !== 0) {
                    return $compare;
                }
            }

            $compare =
                $aProduct <=> $bProduct;

            if ($compare !== 0) {
                return $compare;
            }

            return
                $aVariant <=> $bVariant;
        }
    );
}

$mappingDiagnostics =
    $mappingProvider === 'printify'
        ? $printifyDiagnostics
        : $printfulDiagnostics;

admin_integration_sort_diagnostics(
    $mappingDiagnostics,
    $sort,
    $statusPriority
);

$mappingLabel =
    $mappingProvider === 'printify'
        ? 'Printify'
        : 'Printful';

$mappingError =
    $mappingProvider === 'printify'
        ? $printifyError
        : $printfulError;

$mappingCounts =
    $mappingProvider === 'printify'
        ? $printifyCounts
        : $printfulCounts;

$mappingExactAction =
    $mappingProvider === 'printify'
        ? 'apply-printify-exact-mappings'
        : 'apply-printful-exact-mappings';

$mappingCatalogUrl =
    $mappingProvider === 'printify'
        ? '/printify.php'
        : '/printful.php';

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

<section class="admin-panel admin-integration-card admin-integration-card--provider">

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
    <i aria-hidden="true">
        <?= llama_icon('plug') ?>
    </i>

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
        <?= moderation_e($printfulError) ?>
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

<?php if (
    $printfulConfigured
    && !$printfulError
): ?>

<div class="admin-integration-health">
    <div>
        <span>Mapped</span>
        <strong>
            <?= number_format($mappedCount) ?>
        </strong>
    </div>

    <div>
        <span>Needs attention</span>
        <strong>
            <?= number_format($problemCount) ?>
        </strong>
    </div>
</div>

<div class="admin-integration-catalog-action">
    <a
        class="admin-button"
        href="/printful.php"
    >
        <i aria-hidden="true">
            <?= llama_icon('package') ?>
        </i>
        Open Printful Catalog
    </a>

    <a
        class="admin-button"
        href="/printful-webhook.php"
    >
        <i aria-hidden="true">
            <?= llama_icon('shield') ?>
        </i>
        <?= $printfulWebhookActive
            ? 'Webhook installed'
            : (
                $printfulWebhookError
                    ? 'Webhook needs access'
                    : 'Configure webhook'
            ) ?>
    </a>

    <a
        class="admin-button"
        href="/printful-orders.php"
    >
        <i aria-hidden="true">
            <?= llama_icon('truck-delivery') ?>
        </i>
        Printful Orders
    </a>
</div>

<?php if ($printfulWebhookError): ?>
<div class="admin-user-notice is-warning admin-integration-inline-notice">
    <strong>Webhook access is not ready.</strong>
    <p>
        <?= moderation_e(
            $printfulWebhookError
        ) ?>
    </p>
</div>
<?php endif; ?>

<?php if ($suggestedCount > 0): ?>
<form
    class="admin-integration-mapping-action"
    method="post"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(
            moderation_csrf_token()
        ) ?>"
    >

    <input
        type="hidden"
        name="integration_action"
        value="apply-printful-exact-mappings"
    >

    <div>
        <strong>
            Safe exact Printful matches available
        </strong>

        <span>
            One and only one Printful variant has the same SKU for
            <?= number_format($suggestedCount) ?>
            assigned local variant<?= $suggestedCount === 1 ? '' : 's' ?>.
        </span>
    </div>

    <button
        class="admin-button"
        type="submit"
    >
        Apply exact Printful mappings
    </button>
</form>
<?php endif; ?>

<div class="admin-user-notice is-warning admin-integration-inline-notice">
    <strong>Testing safeguard</strong>

    <p>
        <?= llama_printful_auto_confirm()
            ? 'Auto confirm is enabled. New Printful orders can be submitted to production automatically.'
            : 'Auto confirm is disabled. New Printful orders stay in Draft until you explicitly confirm them.' ?>
    </p>
</div>

<?php endif; ?>

</section>


<section class="admin-panel admin-integration-card admin-integration-card--provider">

<header class="admin-panel-header">
    <div>
        <p>Fulfillment</p>
        <h2>Printify</h2>
    </div>

    <?php if (!$printifyConfigured): ?>
        <span class="admin-status-pill">
            Not configured
        </span>
    <?php elseif ($printifyError): ?>
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
        <?= $printifyConfigured
            ? 'Configured'
            : 'Missing' ?>
    </strong>
</div>

<div>
    <span>Shop ID</span>
    <strong>
        <?= $printifyShop
            ? moderation_e(
                (string) (
                    $printifyShop['id']
                    ?? 'Unknown'
                )
            )
            : 'Auto-detect' ?>
    </strong>
</div>

<div>
    <span>Auto submit</span>
    <strong>
        <?= llama_printify_auto_submit()
            ? 'Enabled'
            : 'Disabled' ?>
    </strong>
</div>

<div>
    <span>API products</span>
    <strong>
        <?= number_format(
            count(
                $printifyCatalog['products']
            )
        ) ?>
    </strong>
</div>

</div>

<?php if (!$printifyConfigured): ?>

<div class="admin-empty-state">
    <i aria-hidden="true">
        <?= llama_icon('plug') ?>
    </i>

    <h3>Printify token missing.</h3>

    <p>
        Add the private API token to
        /private/printify.php.
    </p>
</div>

<?php elseif ($printifyError): ?>

<div class="admin-integration-error">
    <strong>
        Printify could not be reached.
    </strong>

    <p>
        <?= moderation_e(
            $printifyError
        ) ?>
    </p>
</div>

<?php else: ?>

<div class="admin-integration-store">
    <span>Authorized store</span>

    <div>
        <strong>
            <?= moderation_e(
                (string) (
                    $printifyShop['title']
                    ?? $printifyShop['name']
                    ?? 'Printify API Store'
                )
            ) ?>
        </strong>

        <span>
            Store ID
            <?= moderation_e(
                (string) (
                    $printifyShop['id']
                    ?? 'Unknown'
                )
            ) ?>
        </span>
    </div>
</div>

<div class="admin-integration-health">
    <div>
        <span>Mapped</span>
        <strong>
            <?= number_format(
                $printifyMappedCount
            ) ?>
        </strong>
    </div>

    <div>
        <span>Needs attention</span>
        <strong>
            <?= number_format(
                $printifyProblemCount
            ) ?>
        </strong>
    </div>
</div>

<div class="admin-integration-catalog-action">
    <a
        class="admin-button"
        href="/printify.php"
    >
        <i aria-hidden="true">
            <?= llama_icon('package') ?>
        </i>
        Open Printify Catalog
    </a>

    <a
        class="admin-button"
        href="/printify-webhook.php"
    >
        <i aria-hidden="true">
            <?= llama_icon('shield') ?>
        </i>
        <?= $printifyWebhookActive
            ? 'Webhook installed'
            : (
                $printifyWebhookError
                    ? 'Webhook needs access'
                    : 'Configure webhook'
            ) ?>
    </a>

    <a
        class="admin-button"
        href="/printify-orders.php"
    >
        <i aria-hidden="true">
            <?= llama_icon('truck-delivery') ?>
        </i>
        Printify Orders
    </a>
</div>

<?php if ($printifyWebhookError): ?>
<div class="admin-user-notice is-warning admin-integration-inline-notice">
    <strong>Webhook access is not ready.</strong>

    <p>
        <?= moderation_e(
            $printifyWebhookError
        ) ?>
    </p>
</div>
<?php endif; ?>

<?php if ($printifySuggestedCount > 0): ?>
<form
    class="admin-integration-mapping-action"
    method="post"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(
            moderation_csrf_token()
        ) ?>"
    >

    <input
        type="hidden"
        name="integration_action"
        value="apply-printify-exact-mappings"
    >

    <div>
        <strong>
            Safe exact Printify matches available
        </strong>

        <span>
            One and only one Printify variant has the same SKU for
            <?= number_format(
                $printifySuggestedCount
            ) ?>
            assigned local variant<?= $printifySuggestedCount === 1 ? '' : 's' ?>.
        </span>
    </div>

    <button
        class="admin-button"
        type="submit"
    >
        Apply exact Printify mappings
    </button>
</form>
<?php endif; ?>

<div class="admin-user-notice is-warning admin-integration-inline-notice">
    <strong>Testing safeguard</strong>

    <p>
        Keep Printify Order approval set to Manual until live fulfillment is ready.
        Printify can auto-approve created orders independently of Llama Scout.
    </p>
</div>

<?php endif; ?>

</section>


<section class="admin-panel admin-integration-card admin-integration-card--shipping">

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
    <i aria-hidden="true">
        <?= llama_icon('truck-delivery') ?>
    </i>

    <h3>Approval pending.</h3>

    <p>
        Llama Scout Fulfillment is ready for
        rate shopping and label purchasing once
        EasyPost access is available.
    </p>
</div>

</section>

</section>


<section class="admin-panel admin-integration-mapping-panel">

<header class="admin-panel-header admin-integration-mapping-header">
    <div>
        <p>
            <?= moderation_e(
                $mappingLabel
            ) ?>
        </p>

        <h2>Variant Mapping Health</h2>
    </div>

    <div class="admin-integration-mapping-header-tools">
        <nav
            class="admin-integration-provider-switch"
            aria-label="Mapping provider"
        >
            <a
                class="<?= $mappingProvider === 'printful'
                    ? 'is-active'
                    : '' ?>"
                href="/integrations.php?mapping_provider=printful&amp;sort=<?= rawurlencode($sort) ?>"
            >
                Printful
            </a>

            <a
                class="<?= $mappingProvider === 'printify'
                    ? 'is-active'
                    : '' ?>"
                href="/integrations.php?mapping_provider=printify&amp;sort=<?= rawurlencode($sort) ?>"
            >
                Printify
            </a>
        </nav>

        <span>
            <?= number_format(
                count(
                    $mappingDiagnostics
                )
            ) ?>
            local <?= moderation_e($mappingLabel) ?>
            variant<?= count($mappingDiagnostics) === 1 ? '' : 's' ?>
        </span>
    </div>
</header>

<?php if ($mappingError): ?>

<div class="admin-empty-state">
    <i aria-hidden="true">
        <?= llama_icon('alert-triangle') ?>
    </i>

    <h3>
        Mappings cannot be checked.
    </h3>

    <p>
        Restore the
        <?= moderation_e($mappingLabel) ?>
        API connection first.
    </p>
</div>

<?php elseif (!$mappingDiagnostics): ?>

<div class="admin-empty-state">
    <i aria-hidden="true">
        <?= llama_icon('shirt') ?>
    </i>

    <h3>
        No local
        <?= moderation_e($mappingLabel) ?>
        variants yet.
    </h3>

    <p>
        Set a Shop variant's fulfillment provider to
        <?= moderation_e($mappingLabel) ?>
        and it will appear here.
    </p>
</div>

<?php else: ?>

<div class="admin-integration-health admin-integration-health--mapping">

<div>
    <span>Mapped</span>

    <strong>
        <?= number_format(
            $mappingCounts['mapped']
        ) ?>
    </strong>
</div>

<div>
    <span>Exact SKU matches</span>

    <strong>
        <?= number_format(
            $mappingCounts['suggested']
        ) ?>
    </strong>
</div>

<div>
    <span>Needs attention</span>

    <strong>
        <?= number_format(
            $mappingCounts['problem']
        ) ?>
    </strong>
</div>

</div>

<form
    class="admin-integration-sort"
    method="get"
>
    <input
        type="hidden"
        name="mapping_provider"
        value="<?= moderation_e(
            $mappingProvider
        ) ?>"
    >

    <label>
        <span>Sort variants</span>

        <select name="sort">
            <option
                value="attention"
                <?= $sort === 'attention'
                    ? 'selected'
                    : '' ?>
            >
                Needs attention first
            </option>

            <option
                value="sku"
                <?= $sort === 'sku'
                    ? 'selected'
                    : '' ?>
            >
                SKU
            </option>

            <option
                value="product"
                <?= $sort === 'product'
                    ? 'selected'
                    : '' ?>
            >
                Product
            </option>

            <option
                value="status"
                <?= $sort === 'status'
                    ? 'selected'
                    : '' ?>
            >
                Status
            </option>
        </select>
    </label>

    <button
        class="admin-button"
        type="submit"
    >
        Sort
    </button>

    <a
        class="admin-button is-secondary"
        href="<?= moderation_e(
            $mappingCatalogUrl
        ) ?>"
    >
        Open <?= moderation_e($mappingLabel) ?> Catalog
    </a>
</form>

<?php if ($mappingCounts['suggested'] > 0): ?>

<form
    class="admin-integration-mapping-action"
    method="post"
>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e(
            moderation_csrf_token()
        ) ?>"
    >

    <input
        type="hidden"
        name="integration_action"
        value="<?= moderation_e(
            $mappingExactAction
        ) ?>"
    >

    <div>
        <strong>
            Safe exact matches available
        </strong>

        <span>
            Llama Scout found one and only one
            <?= moderation_e($mappingLabel) ?>
            variant with the same SKU for
            <?= number_format(
                $mappingCounts['suggested']
            ) ?>
            local variant<?= $mappingCounts['suggested'] === 1 ? '' : 's' ?>.
        </span>
    </div>

    <button
        class="admin-button"
        type="submit"
    >
        Apply exact
        <?= moderation_e($mappingLabel) ?>
        mappings
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
    <th>IDs</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $mappingDiagnostics
    as $diagnostic
): ?>

<?php
$local =
    is_array(
        $diagnostic['local']
        ?? null
    )
        ? $diagnostic['local']
        : [];

$matches =
    is_array(
        $diagnostic['matches']
        ?? null
    )
        ? $diagnostic['matches']
        : [];

$firstMatch =
    count($matches) === 1
        ? $matches[0]
        : null;

$configuredProductId =
    (string) (
        $diagnostic['configured_product_id']
        ?? ''
    );

$configuredVariantId =
    (string) (
        $diagnostic['configured_variant_id']
        ?? ''
    );

$suggestedProductId = '';
$suggestedVariantId = '';

if ($firstMatch) {
    if ($mappingProvider === 'printify') {
        $suggestedProductId =
            (string) (
                $firstMatch['product_id']
                ?? ''
            );

        $suggestedVariantId =
            (string) (
                $firstMatch['variant_id']
                ?? ''
            );
    } else {
        $suggestedProductId =
            (string) (
                $firstMatch['sync_product_id']
                ?? ''
            );

        $suggestedVariantId =
            (string) (
                $firstMatch['sync_variant_id']
                ?? ''
            );
    }
}
?>

<tr>

<td data-label="Llama Scout variant">
    <strong>
        <?= moderation_e(
            (string) (
                $local['product_name']
                ?? ''
            )
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) (
                $local['variant_name']
                ?? ''
            )
        ) ?>
    </span>
</td>

<td data-label="SKU">
    <?= moderation_e(
        (string) (
            $local['sku']
            ?? ''
        ) !== ''
            ? (string) $local['sku']
            : 'Missing'
    ) ?>
</td>

<td data-label="Status">
    <span
        class="admin-status-pill admin-mapping-status is-<?= moderation_e(
            (string) (
                $diagnostic['status']
                ?? ''
            )
        ) ?>"
    >
        <?= moderation_e(
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) (
                        $diagnostic['status']
                        ?? ''
                    )
                )
            )
        ) ?>
    </span>

    <?php if (
        !in_array(
            (string) (
                $diagnostic['status']
                ?? ''
            ),
            [
                'mapped',
                'unmapped',
            ],
            true
        )
    ): ?>
        <small>
            <?= moderation_e(
                (string) (
                    $diagnostic['message']
                    ?? ''
                )
            ) ?>
        </small>
    <?php endif; ?>
</td>

<td data-label="IDs">

<?php if ($configuredVariantId !== ''): ?>

<span class="admin-integration-id-line">
    Product:
    <?= moderation_e(
        $configuredProductId !== ''
            ? $configuredProductId
            : 'Unknown'
    ) ?>
</span>

<span class="admin-integration-id-line">
    Variant:
    <?= moderation_e(
        $configuredVariantId
    ) ?>
</span>

<?php elseif (
    $suggestedVariantId !== ''
): ?>

<span class="admin-integration-id-line">
    Product:
    <?= moderation_e(
        $suggestedProductId !== ''
            ? $suggestedProductId
            : 'Unknown'
    ) ?>
</span>

<span class="admin-integration-id-line">
    Variant:
    <?= moderation_e(
        $suggestedVariantId
    ) ?>
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
