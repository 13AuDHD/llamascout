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

$states =
    llama_pad_us_states();

$stateCode =
    strtoupper(
        trim(
            (string) (
                $_GET['state']
                ?? 'CO'
            )
        )
    );

if (
    $stateCode !== ''
    && !isset(
        $states[$stateCode]
    )
) {
    $stateCode = 'CO';
}

$rows =
    llama_pad_us_review_classifications(
        $referenceDb,
        $stateCode
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
    'PAD-US Classifications';

$adminPageEyebrow =
    'Integrations';

$adminActiveNav =
    'integrations';

require __DIR__ . '/_header.php';
?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Reference taxonomy</p>
        <h2>PAD-US classifications</h2>
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
                $stateCode
            ) ?>"
        >
            Browse reference units
        </a>

    </div>
</header>


<form
    class="admin-user-action-box"
    method="get"
>

<label>
    <span>State</span>

    <select
        name="state"
        onchange="this.form.submit()"
    >
        <option value="">
            All states
        </option>

        <?php foreach (
            $states
            as $code => $name
        ): ?>

        <option
            value="<?= moderation_e($code) ?>"
            <?= $stateCode === $code
                ? 'selected'
                : '' ?>
        >
            <?= moderation_e($name) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>

</form>

</section>


<section class="admin-panel">

<?php if (!$rows): ?>

<div class="admin-empty-state">
    <h3>No PAD-US classifications found.</h3>
</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>PAD-US designation</th>
    <th>Units</th>
    <th>Unmapped</th>
    <th>Llama Scout mapping</th>
    <th>Review status</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $rows
    as $row
): ?>

<tr>

<td data-label="PAD-US designation">
    <strong>
        <?= moderation_e(
            (string) (
                $row['source_designation']
                ?? ''
            )
        ) ?>
    </strong>

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

<td data-label="Units">
    <?= number_format(
        (int) (
            $row['unit_count']
            ?? 0
        )
    ) ?>
</td>

<td data-label="Unmapped">
    <?= number_format(
        (int) (
            $row['unmapped_count']
            ?? 0
        )
    ) ?>
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
            : 'Reference only / not mapped'
    ) ?>
</td>

<td data-label="Review status">

<?php if (
    $row['reviewed']
    === null
): ?>

    Not reviewed

<?php elseif (
    (int) $row['reviewed'] === 1
): ?>

    Reviewed

    <?php if (
        (int) (
            $row['surface_in_place_form']
            ?? 0
        ) === 1
    ): ?>

    <small>
        Available for Llama Scout taxonomy
    </small>

    <?php else: ?>

    <small>
        Reference only
    </small>

    <?php endif; ?>

<?php else: ?>

    Pending review

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
