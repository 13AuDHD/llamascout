<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/pad-us-sync.php';
require_once dirname(__DIR__) . '/app/pad-us-review.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

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
        $db,
        $filters
    );

$states =
    llama_pad_us_states();

$propertyTypes =
    llama_pad_us_review_property_types(
        $db
    );

$organizations =
    llama_pad_us_review_organizations(
        $db,
        (string) $filters['state']
    );

$accessValues =
    llama_pad_us_review_access_values(
        $db,
        (string) $filters['state']
    );

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
    'PAD-US Named Units';

$adminPageEyebrow =
    'Integrations';

$adminActiveNav =
    'integrations';

require __DIR__ . '/_header.php';
?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Imported taxonomy</p>

        <h2>
            <?= number_format(
                (int) $result['total']
            ) ?>
            named unit<?= (int) $result['total'] === 1 ? '' : 's' ?>
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
            href="/pad-us-issues.php?state=<?= rawurlencode(
                (string) $filters['state']
            ) ?>"
        >
            Review import issues
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
        placeholder="Unit, property type, manager..."
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
    <span>Property type</span>

    <select name="property_type_id">
        <option value="0">
            All property types
        </option>

        <?php foreach (
            $propertyTypes
            as $type
        ): ?>

        <option
            value="<?= (int) $type['id'] ?>"
            <?= (int) $filters['property_type_id']
                === (int) $type['id']
                    ? 'selected'
                    : '' ?>
        >
            <?= moderation_e(
                (string) $type['name']
            ) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Manager / organization</span>

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
    <h3>No matching PAD-US named units.</h3>

    <p>
        Change the filters or synchronize another state first.
    </p>
</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>Named unit</th>
    <th>Property type</th>
    <th>Manager</th>
    <th>State</th>
    <th>Public access</th>
    <th>Source</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $result['rows']
    as $row
): ?>

<?php
$metadata =
    llama_pad_us_review_metadata(
        $row['metadata_json']
        ?? null
    );

$padUs =
    is_array(
        $metadata['pad_us']
        ?? null
    )
        ? $metadata['pad_us']
        : [];

$publicAccess =
    trim(
        (string) (
            $padUs['public_access']
            ?? ''
        )
    );

$localDesignation =
    trim(
        (string) (
            $padUs['local_designation']
            ?? ''
        )
    );

$gisAcres =
    $padUs['gis_acres']
    ?? null;

$sourceDate =
    trim(
        (string) (
            $padUs['source_date']
            ?? ''
        )
    );
?>

<tr>

<td data-label="Named unit">
    <strong>
        <?= moderation_e(
            (string) (
                $row['name']
                ?? ''
            )
        ) ?>
    </strong>

    <?php if ($localDesignation !== ''): ?>
    <small>
        PAD-US designation:
        <?= moderation_e(
            $localDesignation
        ) ?>
    </small>
    <?php endif; ?>

    <details>
        <summary>
            Stored PAD-US metadata
        </summary>

        <pre style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= moderation_e(
            json_encode(
                $padUs,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
            ?: '{}'
        ) ?></pre>
    </details>
</td>

<td data-label="Property type">
    <?= moderation_e(
        (string) (
            $row['property_type_name']
            ?? 'Unmapped'
        )
    ) ?>
</td>

<td data-label="Manager">
    <?= moderation_e(
        (string) (
            $row['organization_name']
            ?? 'Unknown'
        )
    ) ?>

    <?php if (
        trim(
            (string) (
                $row[
                    'organization_short_name'
                ]
                ?? ''
            )
        ) !== ''
    ): ?>
    <small>
        <?= moderation_e(
            (string) $row[
                'organization_short_name'
            ]
        ) ?>
    </small>
    <?php endif; ?>
</td>

<td data-label="State">
    <?= moderation_e(
        (string) (
            $row['state_code']
            ?? ''
        )
    ) ?>
</td>

<td data-label="Public access">
    <?= moderation_e(
        $publicAccess !== ''
            ? $publicAccess
            : 'Unknown'
    ) ?>
</td>

<td data-label="Source">
    PAD-US

    <?php if ($gisAcres !== null): ?>
    <small>
        <?= number_format(
            (float) $gisAcres,
            1
        ) ?>
        GIS acres
    </small>
    <?php endif; ?>

    <?php if ($sourceDate !== ''): ?>
    <small>
        Source date:
        <?= moderation_e(
            $sourceDate
        ) ?>
    </small>
    <?php endif; ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<nav
    class="admin-user-form-actions"
    aria-label="Named unit pages"
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
