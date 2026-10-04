<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/pad-us-sync.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$mainDb = db();
$referenceDb = reference_db();

$states =
    llama_pad_us_states();

$runs =
    llama_pad_us_recent_runs(
        $referenceDb,
        40
    );

$issueCount =
    llama_pad_us_issue_count(
        $referenceDb
    );

$source =
    llama_pad_us_source(
        $referenceDb
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
    'PAD-US Sync';

$adminPageEyebrow =
    'Integrations';

$adminActiveNav =
    'integrations';

require __DIR__ . '/_header.php';
?>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Reference data</p>
        <h2>PAD-US Synchronization</h2>
    </div>

    <span class="admin-status-pill">
        <?= moderation_e(
            (string) (
                $source['version']
                ?? 'PAD-US'
            )
        ) ?>
    </span>
</header>


<div class="admin-user-action-box">

<p>
    PAD-US is imported into the dedicated reference database.
    These records are external reference units, not Llama Scout Places.
</p>

<p>
    Original PAD-US designations are preserved even when Llama Scout
    does not yet have a matching property type. Duplicate polygons are
    normalized into logical named units.
</p>

</div>


<form
    class="admin-user-action-box"
    data-pad-us-sync-form
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        moderation_csrf_token()
    ) ?>"
>

<label>
    <span>State</span>

    <select
        name="state_code"
        required
        data-pad-us-state
    >
        <option value="">
            Choose a state...
        </option>

        <option value="ALL">
            All 50 states
        </option>

        <?php foreach (
            $states
            as $code => $name
        ): ?>

        <option
            value="<?= moderation_e($code) ?>"
        >
            <?= moderation_e($name) ?>
        </option>

        <?php endforeach; ?>
    </select>
</label>


<div class="admin-user-form-actions">

<button
    class="admin-button"
    type="submit"
    data-pad-us-start
>
    Start synchronization
</button>

<button
    class="admin-button is-secondary"
    type="button"
    data-pad-us-stop
    hidden
>
    Stop after this batch
</button>

</div>


<div
    data-pad-us-progress
    hidden
>

<p>
    <strong data-pad-us-progress-title>
        Preparing synchronization...
    </strong>
</p>

<progress
    value="0"
    max="100"
    data-pad-us-progress-bar
></progress>

<p data-pad-us-progress-text>
    Waiting...
</p>

</div>


<div
    class="admin-user-notice is-error"
    role="alert"
    data-pad-us-error
    hidden
></div>

<div
    class="admin-user-notice is-success"
    role="status"
    data-pad-us-success
    hidden
></div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>History</p>
        <h2>Recent PAD-US Runs</h2>
    </div>
</header>

<?php if (!$runs): ?>

<div class="admin-empty-state">
    <h3>No PAD-US synchronization has run in the reference database yet.</h3>
</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>State</th>
    <th>Status</th>
    <th>Progress</th>
    <th>Reference units</th>
    <th>Skipped</th>
    <th>Last message</th>
    <th></th>
</tr>
</thead>

<tbody>

<?php foreach (
    $runs
    as $run
): ?>

<?php
$runState =
    trim(
        (string) (
            $run['state_code']
            ?? ''
        )
    );

$runStatus =
    trim(
        (string) (
            $run['status']
            ?? ''
        )
    );

$canResume =
    in_array(
        $runStatus,
        [
            'running',
            'partial',
            'failed',
        ],
        true
    )
    && isset(
        $states[$runState]
    );
?>

<tr>

<td data-label="State">
    <strong>
        <?= moderation_e(
            $states[$runState]
            ?? $runState
        ) ?>
    </strong>
</td>

<td data-label="Status">
    <?= moderation_e(
        ucwords(
            str_replace(
                '_',
                ' ',
                $runStatus
            )
        )
    ) ?>
</td>

<td data-label="Progress">
    <?= number_format(
        (int) (
            $run['rows_processed']
            ?? 0
        )
    ) ?>
    /
    <?= number_format(
        (int) (
            $run['source_rows']
            ?? 0
        )
    ) ?>
</td>

<td data-label="Reference units">
    +
    <?= number_format(
        (int) (
            $run['units_created']
            ?? 0
        )
    ) ?>
    new,
    <?= number_format(
        (int) (
            $run['units_updated']
            ?? 0
        )
    ) ?>
    updated
</td>

<td data-label="Skipped">
    <?= number_format(
        (int) (
            $run['rows_skipped']
            ?? 0
        )
    ) ?>
</td>

<td data-label="Last message">
    <?= moderation_e(
        (string) (
            $run['last_message']
            ?? ''
        )
    ) ?>
</td>

<td data-label="Action">

<?php if ($canResume): ?>

<button
    class="admin-button is-secondary"
    type="button"
    data-pad-us-resume
    data-run-id="<?= (int) $run['id'] ?>"
    data-state-code="<?= moderation_e(
        $runState
    ) ?>"
>
    Resume
</button>

<?php else: ?>

<span>
    None
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


<script
    src="https://llamascout.com/js/admin/pad-us-sync.js"
></script>

<?php require __DIR__ . '/_footer.php'; ?>
