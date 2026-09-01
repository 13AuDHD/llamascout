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
        $error =
            $exception->getMessage();

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
        Required when resolving or dismissing the report.
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
