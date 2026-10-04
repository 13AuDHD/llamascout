<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/pad-us-sync.php';
require_once dirname(__DIR__) . '/app/pad-us-review.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$mainDb = db();
$referenceDb = reference_db();

$filters =
    llama_pad_us_review_unit_filters(
        $_GET
    );

if (
    $filters['state'] === ''
    && !array_key_exists(
        'state',
        $_GET
    )
) {
    $filters['state'] = 'CO';
}

$result =
    llama_pad_us_review_units(
        $referenceDb,
        $filters
    );

$states =
    llama_pad_us_states();

$propertyTypes =
    llama_pad_us_review_distinct(
        $referenceDb,
        'property_type_slug',
        (string) $filters['state']
    );

$designations =
    llama_pad_us_review_distinct(
        $referenceDb,
        'source_designation',
        (string) $filters['state']
    );

$accessValues =
    llama_pad_us_review_distinct(
        $referenceDb,
        'public_access',
        (string) $filters['state']
    );

$organizations =
    llama_pad_us_review_organizations(
        $referenceDb,
        (string) $filters['state']
    );

$stats =
    admin_dashboard_stats(
        $mainDb
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
    'PAD-US Reference Units';

$adminPageEyebrow =
    'Integrations';

$adminActiveNav =
    'reference-data';

require __DIR__ . '/_header.php';
?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Reference database</p>

        <h2>
            <?= number_format(
                (int) $result['total']
            ) ?>
            unit<?= (int) $result['total'] === 1 ? '' : 's' ?>
        </h2>
    </div>

    <div class="admin-user-form-actions">

        <a
            class="admin-button is-secondary"
            href="/pad-us-sync.php"
        >
            Back to PAD-US Sync
        </a>

        <a
            class="admin-button"
            href="/pad-us-classifications.php?state=<?= rawurlencode(
                (string) $filters['state']
            ) ?>"
        >
            Review classifications
        </a>

    </div>
</header>


<form
    class="admin-user-action-box"
    method="get"
>

<div class="admin-integration-summary">

<label>
    <span>Search</span>

    <input
        type="search"
        name="q"
        value="<?= moderation_e(
            (string) $filters['q']
        ) ?>"
        placeholder="Unit, manager, designation..."
    >
</label>

<label>
    <span>State</span>

    <select name="state">
        <option value="">
            All states
        </option>

        <?php foreach (
            $states
            as $code => $name
        ): ?>

        <option
            value="<?= moderation_e($code) ?>"
            <?= $filters['state'] === $code
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($name) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Llama Scout property type</span>

    <select name="property_type_slug">
        <option value="">
            All mappings
        </option>

        <?php foreach (
            $propertyTypes
            as $slug
        ): ?>

        <option
            value="<?= moderation_e($slug) ?>"
            <?= $filters['property_type_slug'] === $slug
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($slug) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>PAD-US designation</span>

    <select name="designation">
        <option value="">
            All designations
        </option>

        <?php foreach (
            $designations
            as $designation
        ): ?>

        <option
            value="<?= moderation_e($designation) ?>"
            <?= $filters['designation'] === $designation
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($designation) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Manager</span>

    <select name="organization_id">
        <option value="0">
            All managers
        </option>

        <?php foreach (
            $organizations
            as $organization
        ): ?>

        <option
            value="<?= (int) $organization['id'] ?>"
            <?= (int) $filters['organization_id']
                === (int) $organization['id']
                    ? 'selected'
                    : '' ?>
        >
            <?= moderation_e(
                (string) $organization['name']
            ) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<?php if ($accessValues): ?>

<label>
    <span>Public access</span>

    <select name="access">
        <option value="">
            All access values
        </option>

        <?php foreach (
            $accessValues
            as $access
        ): ?>

        <option
            value="<?= moderation_e($access) ?>"
            <?= $filters['access'] === $access
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($access) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<?php endif; ?>

</div>


<div class="admin-user-form-actions">

<button
    class="admin-button"
    type="submit"
>
    Apply filters
</button>

<a
    class="admin-button is-secondary"
    href="/pad-us-units.php?state=CO"
>
    Reset to Colorado
</a>

</div>

</form>

</section>


<section class="admin-panel">

<?php if (!$result['rows']): ?>

<div class="admin-empty-state">
    <h3>No matching PAD-US reference units.</h3>
</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>Reference unit</th>
    <th>PAD-US designation</th>
    <th>Llama Scout mapping</th>
    <th>Manager</th>
    <th>Access</th>
    <th>Size</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $result['rows']
    as $row
): ?>

<tr>

<td data-label="Reference unit">
    <strong>
        <?= moderation_e(
            (string) (
                $row['name']
                ?? ''
            )
        ) ?>
    </strong>

    <small>
        <?= moderation_e(
            (string) (
                $row['state_code']
                ?? ''
            )
        ) ?>
    </small>

    <?php if (
        trim(
            (string) (
                $row['source_local_name']
                ?? ''
            )
        ) !== ''
        && trim(
            (string) (
                $row['source_local_name']
                ?? ''
            )
        ) !== trim(
            (string) (
                $row['name']
                ?? ''
            )
        )
    ): ?>

    <small>
        Local name:
        <?= moderation_e(
            (string) $row['source_local_name']
        ) ?>
    </small>

    <?php endif; ?>

    <details>
        <summary>
            Source details
        </summary>

        <div>
            <strong>External ID:</strong>
            <?= moderation_e(
                (string) (
                    $row['external_id']
                    ?? ''
                )
            ) ?>
        </div>

        <div>
            <strong>Local designation:</strong>
            <?= moderation_e(
                (string) (
                    $row['source_local_designation']
                    ?? ''
                )
            ) ?>
        </div>

        <div>
            <strong>Category:</strong>
            <?= moderation_e(
                (string) (
                    $row['source_category']
                    ?? ''
                )
            ) ?>
        </div>

        <div>
            <strong>Source manager:</strong>
            <?= moderation_e(
                (string) (
                    $row['source_manager_name']
                    ?? ''
                )
            ) ?>
        </div>
    </details>
</td>

<td data-label="PAD-US designation">
    <?= moderation_e(
        (string) (
            $row['source_designation']
            ?? 'Unknown'
        )
    ) ?>

    <?php if (
        trim(
            (string) (
                $row['source_designation_code']
                ?? ''
            )
        ) !== ''
    ): ?>

    <small>
        Code:
        <?= moderation_e(
            (string) $row[
                'source_designation_code'
            ]
        ) ?>
    </small>

    <?php endif; ?>
</td>

<td data-label="Llama Scout mapping">
    <?= moderation_e(
        trim(
            (string) (
                $row['property_type_slug']
                ?? ''
            )
        ) !== ''
            ? (string) $row['property_type_slug']
            : 'Unmapped'
    ) ?>
</td>

<td data-label="Manager">
    <?= moderation_e(
        (string) (
            $row['organization_name']
            ?? $row['source_local_manager']
            ?? $row['source_manager_name']
            ?? 'Unknown'
        )
    ) ?>
</td>

<td data-label="Access">
    <?= moderation_e(
        trim(
            (string) (
                $row['public_access']
                ?? ''
            )
        ) !== ''
            ? (string) $row['public_access']
            : 'Unknown'
    ) ?>
</td>

<td data-label="Size">

<?php if (
    $row['acreage']
    !== null
): ?>

    <?= number_format(
        (float) $row['acreage'],
        1
    ) ?>
    acres

<?php else: ?>

    Unknown

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<nav
    class="admin-user-form-actions"
    aria-label="Reference unit pages"
>

<?php if (
    (int) $result['page'] > 1
): ?>

<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_pad_us_review_url(
            '/pad-us-units.php',
            array_merge(
                $filters,
                [
                    'page' =>
                        (int) $result['page']
                        - 1,
                ]
            )
        )
    ) ?>"
>
    Previous
</a>

<?php endif; ?>

<span>
    Page
    <?= number_format(
        (int) $result['page']
    ) ?>
    of
    <?= number_format(
        (int) $result['pages']
    ) ?>
</span>

<?php if (
    (int) $result['page']
    < (int) $result['pages']
): ?>

<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_pad_us_review_url(
            '/pad-us-units.php',
            array_merge(
                $filters,
                [
                    'page' =>
                        (int) $result['page']
                        + 1,
                ]
            )
        )
    ) ?>"
>
    Next
</a>

<?php endif; ?>

</nav>

<?php endif; ?>

</section>


<?php require __DIR__ . '/_footer.php'; ?>
