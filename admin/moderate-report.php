<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-reports.php';

$adminUser =
    moderation_require_admin();

$db = db();

$csrfToken =
    moderation_csrf_token();

require_once __DIR__ . '/_dashboard.php';

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    'Review Problem Report';

$adminPageEyebrow =
    'Moderation';

$adminActiveNav = 'reports';

$reportId =
    (int) (
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );

$item =
    moderation_report(
        $db,
        $reportId
    );

$error = '';
$notice = '';

if (!$item) {
    http_response_code(404);

    require __DIR__ . '/_header.php';

    echo '<div class="admin-moderation-notice">Report not found.</div>';

    require __DIR__ . '/_footer.php';

    exit;
}

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {
    try {
        if (
            !moderation_verify_csrf(
                (string) (
                    $_POST['csrf_token']
                    ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $status =
            trim(
                (string) (
                    $_POST['status']
                    ?? ''
                )
            );

        $notes =
            trim(
                (string) (
                    $_POST['resolution_notes']
                    ?? ''
                )
            );

        admin_report_set_status(
            $db,
            $reportId,
            (int) $adminUser['id'],
            $status,
            $notes
        );

        $notice =
            'Report updated.';

        $item =
            moderation_report(
                $db,
                $reportId
            );
    } catch (Throwable $exception) {
        $reference = llama_log_caught_exception(
            $exception,
            'admin.moderate_report',
            ['report_id' => $reportId],
            [InvalidArgumentException::class]
        );

        $error = $reference === null
            ? $exception->getMessage()
            : llama_error_message_with_reference('The report could not be updated.', $reference);

        $item =
            moderation_report(
                $db,
                $reportId
            );
    }
}

$meta =
    admin_report_problem_meta(
        (string) $item['problem_type']
    );

$related =
    admin_report_related(
        $db,
        $reportId,
        (int) $item['place_id'],
        (string) $item['problem_type']
    );

$history =
    admin_report_history(
        $db,
        $reportId
    );

$snapshot =
    admin_report_place_snapshot(
        $db,
        (int) $item['place_id'],
        (string) $item['problem_type']
    );

$placeContext =
    admin_report_place_context(
        $db,
        (int) $item['place_id']
    );

$recentUpdates =
    admin_report_recent_updates(
        $db,
        (int) $item['place_id'],
        8
    );

$placeUnresolvedReports =
    admin_report_place_unresolved(
        $db,
        (int) $item['place_id'],
        $reportId
    );

require __DIR__ . '/_header.php';
?>

<?php if ($error !== ''): ?>

<div class="admin-user-notice is-error">
    <?= moderation_e($error) ?>
</div>

<?php endif; ?>


<?php if ($notice !== ''): ?>

<div class="admin-user-notice is-success">
    <?= moderation_e($notice) ?>
</div>

<?php endif; ?>


<section class="admin-report-review-summary">

<div class="admin-report-review-icon">
    <i
        class="fa-solid <?= moderation_e(
            $meta['icon']
        ) ?>"
        aria-hidden="true"
    ></i>
</div>

<div>

<p>
    Report #<?= (int) $item['id'] ?>
    ·
    <?= moderation_e(
        $meta['priority_label']
    ) ?>
    priority
</p>

<h2>
    <?= moderation_e(
        (string) $item['place_name']
    ) ?>
</h2>

<span>
    <?= moderation_e(
        $meta['label']
    ) ?>
    · reported by
    <?= moderation_e(
        $item['display_name']
        ?: $item['username']
    ) ?>
    ·
    <?= moderation_e(
        (string) $item['created_at']
    ) ?>
</span>

</div>


<div class="admin-report-review-summary-actions">

<span class="admin-status-pill">
    <?= moderation_e(
        moderation_status_label(
            (string) $item['status']
        )
    ) ?>
</span>

<a
    class="admin-button"
    href="/place.php?id=<?= (int) $item['place_id'] ?>#<?= moderation_e(
        $meta['place_anchor']
    ) ?>"
>
    <?= moderation_e(
        $meta['place_action']
    ) ?>
</a>

<a
    class="admin-button is-muted"
    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
        (string) $item['place_slug']
    ) ?>"
    target="_blank"
    rel="noopener"
>
    Public Place
</a>

</div>

</section>


<?php if ($placeContext): ?>

<section class="admin-panel admin-report-place-context">

<header class="admin-panel-header">
    <div>
        <p>Place Operations</p>
        <h2>Current Place Context</h2>
    </div>

    <?php if (!empty($placeContext['is_published'])): ?>
        <span class="admin-report-published-warning">
            <i
                class="fa-solid fa-eye"
                aria-hidden="true"
            ></i>
            This Place is published
        </span>
    <?php endif; ?>
</header>


<div class="admin-report-context-grid">

<div>
    <span>Publication</span>
    <strong>
        <?= moderation_e(
            ucfirst(
                (string) (
                    $placeContext['status']
                    ?? 'unknown'
                )
            )
        ) ?>
    </strong>
</div>


<div>
    <span>Verification</span>

    <strong
        class="is-<?= moderation_e(
            (string) (
                $placeContext['verification_freshness']
                ?? 'attention'
            )
        ) ?>"
    >
        <i
            class="admin-report-context-light"
            aria-hidden="true"
        ></i>

        <?= moderation_e(
            admin_report_freshness_label(
                (string) (
                    $placeContext['verification_freshness']
                    ?? 'attention'
                ),
                isset(
                    $placeContext['verification_age_days']
                )
                    ? (int) $placeContext['verification_age_days']
                    : null
            )
        ) ?>
    </strong>
</div>


<div>
    <span>Llama Scout history</span>

    <strong class="<?= !empty($placeContext['ever_llama_scouted'])
        ? 'is-scouted'
        : '' ?>">
        <i
            class="fa-solid <?= !empty($placeContext['ever_llama_scouted'])
                ? 'fa-binoculars'
                : 'fa-circle-minus' ?>"
            aria-hidden="true"
        ></i>

        <?= !empty($placeContext['ever_llama_scouted'])
            ? 'Llama Scouted'
            : 'No field Scout visit recorded' ?>
    </strong>
</div>


<div class="<?= (int) ($placeContext['unresolved_report_count'] ?? 0) > 1
    ? 'has-attention'
    : '' ?>">
    <span>Unresolved reports</span>
    <strong>
        <?= number_format(
            (int) (
                $placeContext['unresolved_report_count']
                ?? 0
            )
        ) ?>
    </strong>
</div>


<div class="<?= (int) ($placeContext['pending_update_count'] ?? 0) > 0
    ? 'has-attention'
    : '' ?>">
    <span>Pending updates</span>
    <strong>
        <?= number_format(
            (int) (
                $placeContext['pending_update_count']
                ?? 0
            )
        ) ?>
    </strong>
</div>


<div>
    <span>Total verifications</span>
    <strong>
        <?= number_format(
            (int) (
                $placeContext['verification_count']
                ?? 0
            )
        ) ?>
    </strong>
</div>

</div>


<?php if (!empty($placeContext['is_published'])): ?>

<div class="admin-report-public-impact-note">
    <i
        class="fa-solid fa-triangle-exclamation"
        aria-hidden="true"
    ></i>

    <div>
        <strong>
            Public information may currently be wrong.
        </strong>

        <span>
            This Place is visible to visitors now. Review the relevant
            section before closing the report when the report identifies
            incorrect or unsafe public information.
        </span>
    </div>
</div>

<?php endif; ?>

</section>

<?php endif; ?>


<div class="admin-report-review-grid">

<div class="admin-report-review-main">


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Reporter</p>
        <h2>What was reported</h2>
    </div>
</header>

<div class="admin-report-details-copy">
    <?= nl2br(
        moderation_e(
            (string) (
                $item['details']
                ?? ''
            )
        )
    ) ?>
</div>

</section>


<?php if ($snapshot): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Current Record</p>
        <h2>Relevant Place Data</h2>
    </div>

    <a
        class="admin-button is-muted"
        href="/place.php?id=<?= (int) $item['place_id'] ?>#<?= moderation_e(
            $meta['place_anchor']
        ) ?>"
    >
        Edit this section
    </a>
</header>


<div class="admin-report-snapshot-grid">

<?php foreach ($snapshot as $label => $value): ?>

<div>
    <span>
        <?= moderation_e(
            (string) $label
        ) ?>
    </span>

    <strong>
        <?= nl2br(
            moderation_e(
                (string) $value
            )
        ) ?>
    </strong>
</div>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>


<?php if (!empty($item['images'])): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Evidence</p>
        <h2>Report Photos</h2>
    </div>

    <span>
        <?= number_format(
            count($item['images'])
        ) ?>
    </span>
</header>


<div class="admin-report-photo-grid">

<?php foreach ($item['images'] as $image): ?>

<?php
$src =
    '/' .
    ltrim(
        (string) $image['file_path'],
        '/'
    );
?>

<a
    href="https://llamascout.com<?= moderation_e($src) ?>"
    target="_blank"
    rel="noopener"
>

<img
    src="https://llamascout.com<?= moderation_e($src) ?>"
    alt="Report evidence"
    loading="lazy"
>

<span>
    Open full size
</span>

</a>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>


<?php if ($recentUpdates): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Recent Activity</p>
        <h2>Recent Place Updates</h2>
    </div>

    <span>
        <?= number_format(
            count($recentUpdates)
        ) ?>
    </span>
</header>


<div class="admin-report-update-list">

<?php foreach ($recentUpdates as $update): ?>

<?php
$changes =
    json_decode(
        (string) (
            $update['proposed_changes']
            ?? ''
        ),
        true
    );

$changeCount =
    is_array($changes)
        ? count($changes)
        : 0;

$updateOpen =
    in_array(
        (string) $update['status'],
        [
            'pending',
            'needs-changes',
        ],
        true
    );
?>

<article class="<?= $updateOpen
    ? 'has-attention'
    : '' ?>">

<div>
    <strong>
        Update #<?= (int) $update['id'] ?>
        ·
        <?= moderation_e(
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    (string) $update['status']
                )
            )
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $update['contributor_name']
        ) ?>
        ·
        <?= moderation_e(
            (string) $update['submitted_at']
        ) ?>
    </span>

    <p>
        <?= moderation_e(
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    (string) $update['update_type']
                )
            )
        ) ?>
        ·
        <?= number_format($changeCount) ?>
        change group<?= $changeCount === 1 ? '' : 's' ?>
    </p>

    <?php if (!empty($update['contributor_notes'])): ?>
        <p>
            <?= moderation_e(
                mb_strimwidth(
                    (string) $update['contributor_notes'],
                    0,
                    220,
                    '…'
                )
            ) ?>
        </p>
    <?php endif; ?>
</div>


<?php if ($updateOpen): ?>
    <a
        class="admin-button is-muted"
        href="/moderate-update.php?id=<?= (int) $update['id'] ?>"
    >
        Review update
    </a>
<?php endif; ?>

</article>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>


<?php if ($placeUnresolvedReports): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Place Pattern</p>
        <h2>Other unresolved reports for this Place</h2>
    </div>

    <span>
        <?= number_format(
            count($placeUnresolvedReports)
        ) ?>
    </span>
</header>


<div class="admin-report-other-open-list">

<?php foreach ($placeUnresolvedReports as $openReport): ?>

<?php
$openMeta =
    admin_report_problem_meta(
        (string) $openReport['problem_type']
    );
?>

<article data-priority="<?= (int) $openMeta['priority'] ?>">

<div>
    <strong>
        Report #<?= (int) $openReport['id'] ?>
        ·
        <?= moderation_e(
            $openMeta['label']
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $openReport['reporter_name']
        ) ?>
        ·
        <?= moderation_e(
            (string) $openReport['created_at']
        ) ?>
        ·
        <?= moderation_e(
            moderation_status_label(
                (string) $openReport['status']
            )
        ) ?>
    </span>

    <?php if (!empty($openReport['details'])): ?>
        <p>
            <?= moderation_e(
                mb_strimwidth(
                    (string) $openReport['details'],
                    0,
                    220,
                    '…'
                )
            ) ?>
        </p>
    <?php endif; ?>
</div>

<a
    class="admin-button is-muted"
    href="/moderate-report.php?id=<?= (int) $openReport['id'] ?>"
>
    Open
</a>

</article>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>


<?php if ($related): ?>

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Pattern Detection</p>
        <h2>
            Other reports of the same problem type
        </h2>
    </div>

    <span>
        <?= number_format(
            count($related)
        ) ?>
    </span>
</header>


<div class="admin-report-related-list">

<?php foreach ($related as $entry): ?>

<article>

<div>
    <strong>
        Report #<?= (int) $entry['id'] ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $entry['reporter_name']
        ) ?>
        ·
        <?= moderation_e(
            (string) $entry['created_at']
        ) ?>
    </span>

    <p>
        <?= moderation_e(
            mb_strimwidth(
                (string) (
                    $entry['details']
                    ?? ''
                ),
                0,
                220,
                '…'
            )
        ) ?>
    </p>
</div>

<div>
    <span class="admin-status-pill">
        <?= moderation_e(
            moderation_status_label(
                (string) $entry['status']
            )
        ) ?>
    </span>

    <a
        class="admin-button is-muted"
        href="/moderate-report.php?id=<?= (int) $entry['id'] ?>"
    >
        Open
    </a>
</div>

</article>

<?php endforeach; ?>

</div>

</section>

<?php endif; ?>

</div>


<aside class="admin-report-review-side">

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Shortcuts</p>
        <h2>Place Operations</h2>
    </div>
</header>

<div class="admin-report-operation-links">

<a
    href="/place.php?id=<?= (int) $item['place_id'] ?>#<?= moderation_e(
        $meta['place_anchor']
    ) ?>"
>
    <i
        class="fa-solid fa-pen-to-square"
        aria-hidden="true"
    ></i>
    <span>
        <strong>
            <?= moderation_e(
                $meta['place_action']
            ) ?>
        </strong>
        <small>Jump to the affected Place section.</small>
    </span>
</a>

<a
    href="/place.php?id=<?= (int) $item['place_id'] ?>#verification"
>
    <i
        class="fa-solid fa-shield-halved"
        aria-hidden="true"
    ></i>
    <span>
        <strong>Verification</strong>
        <small>Review freshness or record a new verification.</small>
    </span>
</a>

<a
    href="/place.php?id=<?= (int) $item['place_id'] ?>#history"
>
    <i
        class="fa-solid fa-clock-rotate-left"
        aria-hidden="true"
    ></i>
    <span>
        <strong>Place history</strong>
        <small>Contributions, reports, updates, and provenance.</small>
    </span>
</a>

</div>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Workflow</p>
        <h2>Report Status</h2>
    </div>
</header>


<form
    method="post"
    class="admin-report-status-form"
>

<input
    type="hidden"
    name="id"
    value="<?= $reportId ?>"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= moderation_e(
        $csrfToken
    ) ?>"
>


<label>
    <span>Status</span>

    <select name="status">

        <?php foreach (
            [
                'open' => 'Open',
                'investigating' =>
                    'Investigating',
                'resolved' =>
                    'Resolved',
                'dismissed' =>
                    'Dismissed',
            ] as $status => $label
        ): ?>

            <option
                value="<?= moderation_e($status) ?>"
                <?= $status ===
                    (string) $item['status']
                        ? 'selected'
                        : '' ?>
            >
                <?= moderation_e($label) ?>
            </option>

        <?php endforeach; ?>

    </select>
</label>


<label>
    <span>
        Resolution / moderator notes
    </span>

    <textarea
        name="resolution_notes"
        rows="7"
        placeholder="Document what was checked, changed, or why the report was dismissed."
    ><?= moderation_e(
        (string) (
            $item['resolution_notes']
            ?? ''
        )
    ) ?></textarea>

    <small>
        Required when resolving or dismissing the report. Record what
        was checked, what changed, and why this report can be closed.
    </small>
</label>


<button
    class="admin-button"
    type="submit"
>
    Save report status
</button>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>History</p>
        <h2>Report Timeline</h2>
    </div>
</header>


<?php if (!$history): ?>

<div class="admin-empty-state">
    <p>No report history yet.</p>
</div>

<?php else: ?>

<div class="admin-report-history">

<?php foreach ($history as $entry): ?>

<div>

<strong>
    <?= moderation_e(
        moderation_status_label(
            (string) $entry['new_status']
        )
    ) ?>
</strong>

<span>
    <?= moderation_e(
        (string) $entry['actor_name']
    ) ?>
    ·
    <?= moderation_e(
        (string) $entry['created_at']
    ) ?>
</span>


<?php if (!empty($entry['notes'])): ?>

<p>
    <?= nl2br(
        moderation_e(
            (string) $entry['notes']
        )
    ) ?>
</p>

<?php endif; ?>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</aside>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
