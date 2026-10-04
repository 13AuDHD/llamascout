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

$propertyTypes =
    llama_pad_us_review_property_types(
        $mainDb
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
    'reference-data';

require __DIR__ . '/_header.php';
?>


<?php if (
    isset(
        $_GET['saved']
    )
): ?>

<div
    class="admin-user-notice is-success"
    role="status"
>
    Classification saved.
</div>

<?php endif; ?>


<?php if (
    trim(
        (string) (
            $_GET['error']
            ?? ''
        )
    ) !== ''
): ?>

<div
    class="admin-user-notice is-error"
    role="alert"
>
    <?= moderation_e(
        (string) $_GET['error']
    ) ?>
</div>

<?php endif; ?>


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
    <th>Classification</th>
</tr>
</thead>

<tbody>

<?php foreach (
    $rows
    as $row
): ?>

<?php
$currentSlug =
    trim(
        (string) (
            $row['property_type_slug']
            ?? ''
        )
    );

$currentReviewed =
    $row['reviewed']
    !== null
    && (int) $row['reviewed'] === 1;

$currentSurface =
    (int) (
        $row['surface_in_place_form']
        ?? 0
    ) === 1;

$currentMode =
    !$currentReviewed
        ? 'pending'
        : (
            $currentSlug !== ''
                ? 'mapped'
                : 'reference_only'
        );
?>

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

<td data-label="Classification">

<form
    method="post"
    action="/pad-us-classification-save.php"
    class="admin-user-action-box"
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
    name="state"
    value="<?= moderation_e(
        $stateCode
    ) ?>"
>

<input
    type="hidden"
    name="designation"
    value="<?= moderation_e(
        (string) (
            $row['source_designation']
            ?? ''
        )
    ) ?>"
>

<input
    type="hidden"
    name="designation_code"
    value="<?= moderation_e(
        (string) (
            $row['source_designation_code']
            ?? ''
        )
    ) ?>"
>

<label>
    <span>Action</span>

    <select name="mode">
        <option
            value="pending"
            <?= $currentMode === 'pending'
                ? 'selected'
                : '' ?>
        >
            Not reviewed
        </option>

        <option
            value="reference_only"
            <?= $currentMode === 'reference_only'
                ? 'selected'
                : '' ?>
        >
            Keep reference-only
        </option>

        <option
            value="mapped"
            <?= $currentMode === 'mapped'
                ? 'selected'
                : '' ?>
        >
            Map to Llama Scout property type
        </option>
    </select>
</label>

<label>
    <span>Property type</span>

    <select name="property_type_slug">
        <option value="">
            Choose a property type...
        </option>

        <?php foreach (
            $propertyTypes
            as $type
        ): ?>

        <option
            value="<?= moderation_e(
                (string) $type['slug']
            ) ?>"
            <?= $currentSlug === (string) $type['slug']
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
    <input
        type="checkbox"
        name="surface_in_place_form"
        value="1"
        <?= $currentSurface
            ? 'checked'
            : '' ?>
    >

    <span>
        Allow this classification to surface in the Llama Scout place form
    </span>
</label>

<label>
    <span>Notes</span>

    <input
        type="text"
        name="notes"
        value="<?= moderation_e(
            (string) (
                $row['notes']
                ?? ''
            )
        ) ?>"
        placeholder="Optional internal note"
    >
</label>

<div class="admin-user-form-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Save classification
    </button>
</div>

</form>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>


<?php require __DIR__ . '/_footer.php'; ?>
