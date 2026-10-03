<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/pad-us-sync.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$states =
    llama_pad_us_states();

$runs =
    llama_pad_us_recent_runs(
        $db,
        40
    );

$issueCount =
    llama_pad_us_issue_count(
        $db
    );

$source =
    llama_pad_us_source(
        $db
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
        <p>National taxonomy</p>
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
    Import public-land unit names, managers, designation types,
    source information, and public-access metadata from PAD-US.
    Polygon geometry is not downloaded.
</p>

<p>
    Existing imported rows are updated instead of duplicated.
    Unknown designation types are sent to the taxonomy review queue
    rather than guessed.
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
            <?= moderation_e(
                $name
            ) ?>
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
        <p>Review</p>
        <h2>Import Issues</h2>
    </div>

    <span class="admin-status-pill">
        <?= number_format(
            $issueCount
        ) ?>
        unresolved
    </span>
</header>

<div class="admin-user-action-box">

<p>
    These are records Llama Scout refused to guess about.
    They remain available for later taxonomy mapping instead of
    silently creating inaccurate property types.
</p>

</div>

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
    <h3>No PAD-US synchronization has run yet.</h3>
</div>

<?php else: ?>

<div class="admin-integration-table-wrap">

<table class="admin-integration-table">

<thead>
<tr>
    <th>State</th>
    <th>Status</th>
    <th>Progress</th>
    <th>Locations</th>
    <th>Warnings</th>
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

<td data-label="Locations">
    +
    <?= number_format(
        (int) (
            $run['locations_created']
            ?? 0
        )
    ) ?>
    new,
    <?= number_format(
        (int) (
            $run['locations_updated']
            ?? 0
        )
    ) ?>
    updated
</td>

<td data-label="Warnings">
    <?= number_format(
        (int) (
            $run['warning_count']
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
    â
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
