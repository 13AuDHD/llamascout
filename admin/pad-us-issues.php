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
    llama_pad_us_review_issue_filters(
        $_GET
    );

$result =
    llama_pad_us_review_issues(
        $db,
        $filters
    );

$states =
    llama_pad_us_states();

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
    'PAD-US Import Issues';

$adminPageEyebrow =
    'Integrations';

$adminActiveNav =
    'integrations';

require __DIR__ . '/_header.php';
?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Review queue</p>
        <h2>
            <?= number_format(
                (int) $result['total']
            ) ?>
            issue<?= (int) $result['total'] === 1 ? '' : 's' ?>
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
            href="/pad-us-units.php?state=<?= rawurlencode(
                (string) (
                    $filters['state']
                    ?: 'CO'
                )
            ) ?>"
        >
            Browse named units
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
        placeholder="Designation, unit, manager, source ID..."
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
    <span>Severity</span>

    <select name="severity">
        <option value="">
            All severities
        </option>

        <?php foreach (
            [
                'error' =>
                    'Error',
                'warning' =>
                    'Warning',
                'info' =>
                    'Info',
            ]
            as $value => $label
        ): ?>

        <option
            value="<?= moderation_e($value) ?>"
            <?= $filters['severity'] === $value
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($label) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>Status</span>

    <select name="resolved">
        <option
            value="0"
            <?= $filters['resolved'] === '0'
                ? 'selected'
                : '' ?>
        >
            Unresolved
        </option>

        <option
            value="1"
            <?= $filters['resolved'] === '1'
                ? 'selected'
                : '' ?>
        >
            Resolved
        </option>

        <option
            value=""
            <?= $filters['resolved'] === ''
                ? 'selected'
                : '' ?>
        >
            All
        </option>
    </select>
</label>

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
    href="/pad-us-issues.php"
>
    Reset
</a>

</div>

</form>

</section>


<section class="admin-panel">

<?php if (!$result['rows']): ?>

<div class="admin-empty-state">
    <h3>No matching PAD-US issues.</h3>

    <p>
        Change the filters or return to the synchronization page.
    </p>
</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>Issue</th>
    <th>State</th>
    <th>Source details</th>
    <th>Run</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $result['rows']
    as $row
): ?>

<?php
$sourceData =
    llama_pad_us_review_metadata(
        $row['source_data_json']
        ?? null
    );

$unitName =
    trim(
        (string) (
            $sourceData['Unit_Nm']
            ?? $sourceData['Loc_Nm']
            ?? ''
        )
    );

$localManager =
    trim(
        (string) (
            $sourceData['Loc_Mang']
            ?? ''
        )
    );

$manager =
    trim(
        (string) (
            $sourceData['Mang_Name']
            ?? ''
        )
    );

$designation =
    trim(
        (string) (
            $sourceData['Loc_Ds']
            ?? $sourceData['Des_Tp']
            ?? ''
        )
    );
?>

<tr>

<td data-label="Issue">
    <strong>
        <?= moderation_e(
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) (
                        $row['issue_type']
                        ?? ''
                    )
                )
            )
        ) ?>
    </strong>

    <div>
        <?= moderation_e(
            (string) (
                $row['message']
                ?? ''
            )
        ) ?>
    </div>

    <small>
        Severity:
        <?= moderation_e(
            ucfirst(
                (string) (
                    $row['severity']
                    ?? ''
                )
            )
        ) ?>
        |
        <?= !empty($row['resolved'])
            ? 'Resolved'
            : 'Unresolved' ?>
    </small>
</td>

<td data-label="State">
    <?= moderation_e(
        (string) (
            $row['state_code']
            ?? ''
        )
    ) ?>
</td>

<td data-label="Source details">

<?php if ($unitName !== ''): ?>
<div>
    <strong>Unit:</strong>
    <?= moderation_e($unitName) ?>
</div>
<?php endif; ?>

<?php if (
    $localManager !== ''
    || $manager !== ''
): ?>
<div>
    <strong>Manager:</strong>
    <?= moderation_e(
        $localManager !== ''
            ? $localManager
            : $manager
    ) ?>
</div>
<?php endif; ?>

<?php if ($designation !== ''): ?>
<div>
    <strong>Designation:</strong>
    <?= moderation_e($designation) ?>
</div>
<?php endif; ?>

<?php if (
    trim(
        (string) (
            $row['source_record_id']
            ?? ''
        )
    ) !== ''
): ?>
<small>
    Source record:
    <?= moderation_e(
        (string) $row['source_record_id']
    ) ?>
</small>
<?php endif; ?>


<details>
    <summary>
        Raw PAD-US fields
    </summary>

    <pre style="white-space: pre-wrap; overflow-wrap: anywhere;"><?= moderation_e(
        json_encode(
            $sourceData,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        )
        ?: '{}'
    ) ?></pre>
</details>

</td>

<td data-label="Run">
    #<?= (int) (
        $row['import_run_id']
        ?? 0
    ) ?>

    <small>
        PAD-US
        <?= moderation_e(
            (string) (
                $row['source_version']
                ?? ''
            )
        ) ?>
    </small>
</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<nav
    class="admin-user-form-actions"
    aria-label="Issue pages"
>

<?php if (
    (int) $result['page'] > 1
): ?>

<a
    class="admin-button is-secondary"
    href="<?= moderation_e(
        llama_pad_us_review_url(
            '/pad-us-issues.php',
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
            '/pad-us-issues.php',
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
