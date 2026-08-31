<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-moderation.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$queueStats =
    admin_moderation_stats($db);

$newPlaces =
    admin_moderation_new_places($db);

$updates =
    admin_moderation_updates($db);

$reports =
    admin_moderation_reports($db);

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Moderation';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'moderation';

require __DIR__ . '/_header.php';
?>

<section class="admin-moderation-overview-grid">

<a href="/submissions.php">
    <span>New Places</span>
    <strong>
        <?= number_format(
            $queueStats['new_places']
        ) ?>
    </strong>
    <small>
        Oldest:
        <?= moderation_e(
            admin_moderation_age_label(
                $queueStats['oldest_submission']
            )
        ) ?>
    </small>
</a>

<a href="/updates.php">
    <span>Place Updates</span>
    <strong>
        <?= number_format(
            $queueStats['updates']
        ) ?>
    </strong>
    <small>
        Oldest:
        <?= moderation_e(
            admin_moderation_age_label(
                $queueStats['oldest_update']
            )
        ) ?>
    </small>
</a>

<a href="/reports.php">
    <span>Problem Reports</span>
    <strong>
        <?= number_format(
            $queueStats['reports']
        ) ?>
    </strong>
    <small>
        Oldest:
        <?= moderation_e(
            admin_moderation_age_label(
                $queueStats['oldest_report']
            )
        ) ?>
    </small>
</a>

</section>


<div class="admin-moderation-console-grid">

<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Queue</p>
        <h2>New Places</h2>
    </div>

    <a class="admin-button is-muted" href="/submissions.php">
        Full queue
    </a>
</header>

<?php if (!$newPlaces): ?>

<div class="admin-empty-state">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <p>No new Place submissions waiting.</p>
</div>

<?php else: ?>

<div class="admin-moderation-console-list">

<?php foreach ($newPlaces as $item): ?>

<article>

<span class="admin-user-table-avatar">
    <img
        src="<?= moderation_e(
            admin_user_avatar_src(
                (string) (
                    $item['profile_image_src']
                    ?? ''
                ),
                $siteUrl
            )
        ) ?>"
        alt=""
        loading="lazy"
    >
</span>

<div>
    <strong>
        <?= moderation_e(
            (string) $item['place_name']
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $item['contributor_name']
        ) ?>
        ·
        <?= moderation_e(
            ucwords(
                str_replace(
                    ['-', '_'],
                    ' ',
                    (string) $item['status']
                )
            )
        ) ?>
    </span>

    <small>
        Waiting
        <?= moderation_e(
            admin_moderation_age_label(
                (string) $item['submitted_at']
            )
        ) ?>
        ·
        <?= moderation_e(
            (string) (
                $item['role_at_submission']
                ?: 'member'
            )
        ) ?>
    </small>
</div>

<a
    class="admin-button"
    href="/moderate-submission.php?id=<?= (int) $item['id'] ?>"
>
    Review
</a>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Queue</p>
        <h2>Place Updates</h2>
    </div>

    <a class="admin-button is-muted" href="/updates.php">
        Full queue
    </a>
</header>

<?php if (!$updates): ?>

<div class="admin-empty-state">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <p>No Place updates waiting.</p>
</div>

<?php else: ?>

<div class="admin-moderation-console-list">

<?php foreach ($updates as $item): ?>
<?php
$changes =
    moderation_decode_json(
        $item['proposed_changes']
    );
?>

<article>

<span class="admin-user-table-avatar">
    <img
        src="<?= moderation_e(
            admin_user_avatar_src(
                (string) (
                    $item['profile_image_src']
                    ?? ''
                ),
                $siteUrl
            )
        ) ?>"
        alt=""
        loading="lazy"
    >
</span>

<div>
    <strong>
        <?= moderation_e(
            (string) $item['place_name']
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            (string) $item['contributor_name']
        ) ?>
        ·
        <?= number_format(count($changes)) ?>
        changed field<?= count($changes) === 1 ? '' : 's' ?>
    </span>

    <small>
        Waiting
        <?= moderation_e(
            admin_moderation_age_label(
                (string) $item['submitted_at']
            )
        ) ?>
        ·
        <?= moderation_e(
            ucwords(
                str_replace(
                    ['-', '_'],
                    ' ',
                    (string) $item['update_type']
                )
            )
        ) ?>
    </small>
</div>

<a
    class="admin-button"
    href="/moderate-update.php?id=<?= (int) $item['id'] ?>"
>
    Review
</a>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>


<section class="admin-panel admin-moderation-reports-panel">

<header class="admin-panel-header">
    <div>
        <p>Queue</p>
        <h2>Problem Reports</h2>
    </div>

    <a class="admin-button is-muted" href="/reports.php">
        Full queue
    </a>
</header>

<?php if (!$reports): ?>

<div class="admin-empty-state">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <p>No open problem reports.</p>
</div>

<?php else: ?>

<div class="admin-moderation-console-list">

<?php foreach ($reports as $item): ?>

<article>

<span class="admin-user-table-avatar">
    <img
        src="<?= moderation_e(
            admin_user_avatar_src(
                (string) (
                    $item['profile_image_src']
                    ?? ''
                ),
                $siteUrl
            )
        ) ?>"
        alt=""
        loading="lazy"
    >
</span>

<div>
    <strong>
        <?= moderation_e(
            (string) $item['place_name']
        ) ?>
    </strong>

    <span>
        <?= moderation_e(
            ucwords(
                str_replace(
                    ['-', '_'],
                    ' ',
                    (string) $item['problem_type']
                )
            )
        ) ?>
        ·
        <?= moderation_e(
            (string) $item['contributor_name']
        ) ?>
    </span>

    <small>
        Waiting
        <?= moderation_e(
            admin_moderation_age_label(
                (string) $item['created_at']
            )
        ) ?>
        ·
        <?= number_format(
            (int) $item['image_count']
        ) ?>
        photo<?= (int) $item['image_count'] === 1 ? '' : 's' ?>
    </small>
</div>

<a
    class="admin-button"
    href="/moderate-report.php?id=<?= (int) $item['id'] ?>"
>
    Review
</a>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
