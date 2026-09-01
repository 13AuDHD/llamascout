<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-reports.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$status =
    trim(
        (string) (
            $_GET['status']
            ?? ''
        )
    );

$problemType =
    trim(
        (string) (
            $_GET['type']
            ?? ''
        )
    );

$search =
    trim(
        (string) (
            $_GET['q']
            ?? ''
        )
    );

$items =
    admin_reports_queue(
        $db,
        $status,
        $problemType,
        $search
    );

$problemTypes =
    admin_report_problem_types($db);

$reportStats =
    admin_report_stats($db);

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Problem Reports';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'reports';

require __DIR__ . '/_header.php';
?>

<section class="admin-report-stat-grid">

<div>
    <span>Open</span>
    <strong>
        <?= number_format(
            $reportStats['open']
        ) ?>
    </strong>
</div>

<div>
    <span>Investigating</span>
    <strong>
        <?= number_format(
            $reportStats['investigating']
        ) ?>
    </strong>
</div>

<div class="<?= $reportStats['urgent'] > 0
    ? 'is-urgent'
    : '' ?>">
    <span>Urgent</span>
    <strong>
        <?= number_format(
            $reportStats['urgent']
        ) ?>
    </strong>
</div>

<div>
    <span>Oldest unresolved</span>
    <strong class="admin-report-age-stat">
        <?= moderation_e(
            admin_report_age_label(
                $reportStats['oldest']
            )
        ) ?>
    </strong>
</div>

</section>


<section class="admin-panel admin-user-filter-panel">

<form
    class="admin-report-filters"
    method="get"
>

<label class="admin-user-search">
    <span>Search reports</span>

    <div>
        <i
            class="fa-solid fa-magnifying-glass"
            aria-hidden="true"
        ></i>

        <input
            type="search"
            name="q"
            value="<?= moderation_e($search) ?>"
            placeholder="Place, reporter, location, details, or report ID"
        >
    </div>
</label>


<label>
    <span>Status</span>

    <select name="status">
        <option value="">
            Open + investigating
        </option>

        <?php foreach (
            [
                'open' => 'Open',
                'investigating' =>
                    'Investigating',
                'resolved' =>
                    'Resolved',
                'dismissed' =>
                    'Dismissed',
            ] as $value => $label
        ): ?>
            <option
                value="<?= moderation_e($value) ?>"
                <?= $status === $value
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>


<label>
    <span>Problem type</span>

    <select name="type">
        <option value="">
            All problem types
        </option>

        <?php foreach ($problemTypes as $type): ?>
            <?php $meta =
                admin_report_problem_meta(
                    $type
                ); ?>

            <option
                value="<?= moderation_e($type) ?>"
                <?= $problemType === $type
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    $meta['label']
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>


<div class="admin-user-filter-actions">
    <button
        class="admin-button"
        type="submit"
    >
        Filter
    </button>

    <a
        class="admin-button is-muted"
        href="/reports.php"
    >
        Clear
    </a>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Operations Queue</p>

        <h2>
            <?= number_format(
                count($items)
            ) ?>
            report<?= count($items) === 1
                ? ''
                : 's' ?>
            shown
        </h2>
    </div>
</header>


<?php if (!$items): ?>

<div class="admin-empty-state">

<i
    class="fa-solid fa-circle-check"
    aria-hidden="true"
></i>

<h2>Queue clear.</h2>

<p>
    No reports match the current filters.
</p>

</div>

<?php else: ?>

<div class="admin-report-queue">

<?php foreach ($items as $item): ?>

<?php
$meta =
    admin_report_problem_meta(
        (string) $item['problem_type']
    );

$location =
    implode(
        ' · ',
        array_filter(
            [
                $item['city'] ?? null,
                $item['county'] ?? null,
                $item['state'] ?? null,
            ]
        )
    );
?>

<article
    class="admin-report-queue-card"
    data-priority="<?= (int) $meta['priority'] ?>"
>

<div class="admin-report-priority-icon">
    <i
        class="fa-solid <?= moderation_e(
            $meta['icon']
        ) ?>"
        aria-hidden="true"
    ></i>
</div>


<div class="admin-report-queue-main">

<div class="admin-report-queue-heading">

<div>
    <span>
        Report #<?= (int) $item['id'] ?>
        ·
        <?= moderation_e(
            $meta['priority_label']
        ) ?>
    </span>

    <h2>
        <?= moderation_e(
            (string) $item['place_name']
        ) ?>
    </h2>
</div>

<span class="admin-status-pill">
    <?= moderation_e(
        moderation_status_label(
            (string) $item['status']
        )
    ) ?>
</span>

</div>


<p>
    <strong>
        <?= moderation_e(
            $meta['label']
        ) ?>
    </strong>

    ·
    <?= moderation_e(
        $item['display_name']
        ?: $item['username']
    ) ?>

    · waiting
    <?= moderation_e(
        admin_report_age_label(
            (string) $item['created_at']
        )
    ) ?>

    ·
    <?= number_format(
        (int) $item['image_count']
    ) ?>
    photo<?= (int) $item['image_count'] === 1
        ? ''
        : 's' ?>
</p>


<?php if ($location !== ''): ?>
<p>
    <?= moderation_e($location) ?>
</p>
<?php endif; ?>


<?php if ((int) $item['matching_report_count'] > 1): ?>

<div class="admin-report-related-pill">
    <i
        class="fa-solid fa-layer-group"
        aria-hidden="true"
    ></i>

    <?= number_format(
        (int) $item['matching_report_count']
    ) ?>
    reports of this type for this Place

    <?php if ((int) $item['matching_open_count'] > 1): ?>
        ·
        <?= number_format(
            (int) $item['matching_open_count']
        ) ?>
        still unresolved
    <?php endif; ?>
</div>

<?php endif; ?>

</div>


<div class="admin-report-queue-actions">

<a
    class="admin-button"
    href="/moderate-report.php?id=<?= (int) $item['id'] ?>"
>
    Review
</a>

<a
    class="admin-button is-muted"
    href="/place.php?id=<?= (int) $item['place_id'] ?>#<?= moderation_e(
        $meta['place_anchor']
    ) ?>"
>
    Place data
</a>

</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
