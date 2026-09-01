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

$placeGroups = [];

foreach ($items as $reportItem) {
    $placeId =
        (int) $reportItem['place_id'];

    if (
        !isset(
            $placeGroups[$placeId]
        )
    ) {
        $placeGroups[$placeId] = [
            'place_id' =>
                $placeId,
            'place_name' =>
                (string) $reportItem['place_name'],
            'place_slug' =>
                (string) $reportItem['place_slug'],
            'place_status' =>
                (string) $reportItem['place_status'],
            'city' =>
                $reportItem['city'] ?? null,
            'county' =>
                $reportItem['county'] ?? null,
            'state' =>
                $reportItem['state'] ?? null,
            'latest_verification_at' =>
                $reportItem['latest_verification_at']
                ?? null,
            'llama_scouted_count' =>
                (int) (
                    $reportItem['llama_scouted_count']
                    ?? 0
                ),
            'pending_update_count' =>
                (int) (
                    $reportItem['pending_update_count']
                    ?? 0
                ),
            'place_open_report_count' =>
                (int) (
                    $reportItem['place_open_report_count']
                    ?? 0
                ),
            'reports' => [],
            'priority' => 99,
        ];
    }

    $meta =
        admin_report_problem_meta(
            (string)
            $reportItem['problem_type']
        );

    $placeGroups[$placeId]['priority'] =
        min(
            (int) $placeGroups[$placeId]['priority'],
            (int) $meta['priority']
        );

    $placeGroups[$placeId]['reports'][] =
        $reportItem;
}

uasort(
    $placeGroups,
    static function (
        array $a,
        array $b
    ): int {
        $priorityCompare =
            (int) $a['priority']
            <=>
            (int) $b['priority'];

        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }

        $openCompare =
            (int) $b['place_open_report_count']
            <=>
            (int) $a['place_open_report_count'];

        if ($openCompare !== 0) {
            return $openCompare;
        }

        return
            strcasecmp(
                (string) $a['place_name'],
                (string) $b['place_name']
            );
    }
);

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
            report<?= count($items) === 1 ? '' : 's' ?>
            across
            <?= number_format(
                count($placeGroups)
            ) ?>
            Place<?= count($placeGroups) === 1 ? '' : 's' ?>
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

<div class="admin-report-place-groups">

<?php foreach ($placeGroups as $group): ?>

<?php
$location =
    implode(
        ' · ',
        array_filter([
            $group['city'] ?? null,
            $group['county'] ?? null,
            $group['state'] ?? null,
        ])
    );

$published =
    in_array(
        (string) $group['place_status'],
        ['active','featured'],
        true
    );

$verifiedAt =
    trim(
        (string) (
            $group['latest_verification_at']
            ?? ''
        )
    );

$verificationState =
    'never';

$verificationLabel =
    'Never verified';

if ($verifiedAt !== '') {
    try {
        $verifiedDate =
            new DateTimeImmutable(
                $verifiedAt
            );

        $verificationDays =
            max(
                0,
                (int) floor(
                    (
                        time()
                        - $verifiedDate->getTimestamp()
                    ) / 86400
                )
            );

        $verificationState =
            $verificationDays > 730
                ? 'overdue'
                : (
                    $verificationDays > 365
                        ? 'attention'
                        : 'current'
                );

        $verificationLabel =
            $verificationState === 'current'
                ? 'Verified ' .
                    number_format($verificationDays) .
                    ' days ago'
                : (
                    $verificationState === 'overdue'
                        ? 'Verification over 2 years old'
                        : 'Verification over 1 year old'
                );
    } catch (Throwable) {
        $verificationState =
            'attention';

        $verificationLabel =
            'Verification date needs review';
    }
}
?>

<section
    class="admin-report-place-group"
    data-priority="<?= (int) $group['priority'] ?>"
>

<header class="admin-report-place-group-header">

<div class="admin-report-place-group-title">
    <div>
        <span class="admin-report-place-group-eyebrow">
            Place #<?= (int) $group['place_id'] ?>
            ·
            <?= moderation_e(
                ucfirst(
                    (string) $group['place_status']
                )
            ) ?>
        </span>

        <h3>
            <?= moderation_e(
                (string) $group['place_name']
            ) ?>
        </h3>

        <?php if ($location !== ''): ?>
            <span>
                <?= moderation_e($location) ?>
            </span>
        <?php endif; ?>
    </div>
</div>


<div class="admin-report-place-flags">

    <?php if ($published): ?>
        <span class="admin-report-place-flag is-published">
            <i
                class="fa-solid fa-eye"
                aria-hidden="true"
            ></i>
            Published
        </span>
    <?php endif; ?>

    <?php if ((int) $group['llama_scouted_count'] > 0): ?>
        <span class="admin-report-place-flag is-scouted">
            <i
                class="fa-solid fa-binoculars"
                aria-hidden="true"
            ></i>
            Llama Scouted
        </span>
    <?php endif; ?>

    <span
        class="admin-report-place-flag is-verification-<?= moderation_e(
            $verificationState
        ) ?>"
    >
        <i
            class="fa-solid fa-circle"
            aria-hidden="true"
        ></i>
        <?= moderation_e(
            $verificationLabel
        ) ?>
    </span>

    <?php if ((int) $group['pending_update_count'] > 0): ?>
        <span class="admin-report-place-flag is-update">
            <i
                class="fa-solid fa-pen"
                aria-hidden="true"
            ></i>
            <?= number_format(
                (int) $group['pending_update_count']
            ) ?>
            pending update<?= (int) $group['pending_update_count'] === 1 ? '' : 's' ?>
        </span>
    <?php endif; ?>

</div>


<div class="admin-report-place-group-actions">
    <a
        class="admin-button is-muted"
        href="/place.php?id=<?= (int) $group['place_id'] ?>#history"
    >
        Place record
    </a>

    <?php if ($published): ?>
        <a
            class="admin-button is-muted"
            href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                (string) $group['place_slug']
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Public
        </a>
    <?php endif; ?>
</div>

</header>


<div class="admin-report-place-group-summary">
    <strong>
        <?= number_format(
            (int) $group['place_open_report_count']
        ) ?>
        unresolved report<?= (int) $group['place_open_report_count'] === 1 ? '' : 's' ?>
        for this Place
    </strong>

    <?php if ($published): ?>
        <span>
            Changes to this record affect a currently published Place.
        </span>
    <?php endif; ?>
</div>


<div class="admin-report-place-group-reports">

<?php foreach ($group['reports'] as $item): ?>

<?php
$meta =
    admin_report_problem_meta(
        (string) $item['problem_type']
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
            $meta['label']
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
    photo<?= (int) $item['image_count'] === 1 ? '' : 's' ?>
</p>


<?php if ((int) $item['matching_report_count'] > 1): ?>

<div class="admin-report-related-pill">
    <i
        class="fa-solid fa-layer-group"
        aria-hidden="true"
    ></i>

    <?= number_format(
        (int) $item['matching_report_count']
    ) ?>
    reports of this type

    <?php if ((int) $item['matching_open_count'] > 1): ?>
        ·
        <?= number_format(
            (int) $item['matching_open_count']
        ) ?>
        unresolved
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
    Fix section
</a>

</div>

</article>

<?php endforeach; ?>

</div>

</section>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
