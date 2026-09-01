<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-verifications.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$search =
    trim((string) ($_GET['q'] ?? ''));

$type =
    trim((string) ($_GET['type'] ?? ''));

$age =
    trim((string) ($_GET['age'] ?? ''));

$items =
    admin_verifications_list(
        $db,
        $search,
        $type,
        $age
    );

$types =
    admin_verification_types($db);

$verificationStats =
    admin_verification_stats($db);

$attentionQueue =
    admin_verification_attention_queue(
        $db,
        100
    );

$attentionStats =
    admin_verification_attention_stats(
        $db
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

$adminPageTitle = 'Verifications';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'verifications';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel admin-verification-operations-panel">

<header class="admin-panel-header">
    <div>
        <p>Operational Queue</p>
        <h2>Place Verification Freshness</h2>
    </div>

    <span>
        Published Places are considered current when checked within the last year.
    </span>
</header>

<div class="admin-verification-health-summary">

    <div class="is-good">
        <span class="admin-verification-light" aria-hidden="true"></span>
        <div>
            <strong><?= number_format((int) $attentionStats['published_current']) ?></strong>
            <span>Current</span>
        </div>
    </div>

    <div class="is-attention">
        <span class="admin-verification-light" aria-hidden="true"></span>
        <div>
            <strong><?= number_format((int) $attentionStats['published_stale']) ?></strong>
            <span>Need attention</span>
        </div>
    </div>

    <div class="is-down">
        <span class="admin-verification-light" aria-hidden="true"></span>
        <div>
            <strong><?= number_format((int) $attentionStats['published_never_verified']) ?></strong>
            <span>Never verified</span>
        </div>
    </div>

</div>


<?php
$needsAttention = array_values(
    array_filter(
        $attentionQueue,
        static fn (array $place): bool =>
            in_array(
                (string) $place['freshness_state'],
                ['never','overdue','attention'],
                true
            )
    )
);
?>

<?php if (!$needsAttention): ?>

<div class="admin-empty-state admin-verification-all-current">
    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
    <h3>Published Place verification is current.</h3>
    <p>No active or featured Places are currently more than one year out of date or missing verification.</p>
</div>

<?php else: ?>

<div class="admin-verification-attention-list">

<?php foreach ($needsAttention as $place): ?>

<?php
$freshness = (string) $place['freshness_state'];
$published = in_array(
    (string) $place['status'],
    ['active','featured'],
    true
);
?>

<article class="admin-verification-attention-row is-<?= moderation_e($freshness) ?>">

<span class="admin-verification-light" aria-hidden="true"></span>

<div class="admin-verification-attention-main">
    <div>
        <strong><?= moderation_e((string) $place['name']) ?></strong>
        <span>
            <?= moderation_e(
                implode(
                    ' · ',
                    array_filter([
                        $place['city'] ?? null,
                        $place['county'] ?? null,
                        $place['state'] ?? null,
                    ])
                ) ?: 'Location not recorded'
            ) ?>
        </span>
    </div>

    <div class="admin-verification-attention-meta">
        <span class="admin-status-pill">
            <?= moderation_e(ucfirst((string) $place['status'])) ?>
        </span>

        <span>
            <?= moderation_e(admin_verification_freshness_label($freshness)) ?>
        </span>

        <?php if ((int) $place['open_report_count'] > 0): ?>
            <span class="is-report">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <?= number_format((int) $place['open_report_count']) ?> open report<?= (int) $place['open_report_count'] === 1 ? '' : 's' ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="admin-verification-attention-actions">
    <a
        class="admin-button"
        href="/place.php?id=<?= (int) $place['id'] ?>#verification"
    >
        Verify Place
    </a>

    <?php if ($published): ?>
        <a
            class="admin-button is-muted"
            href="https://llamascout.com/place.php?slug=<?= rawurlencode((string) $place['slug']) ?>"
            target="_blank"
            rel="noopener"
        >
            View
        </a>
    <?php endif; ?>
</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>


<section class="admin-verification-stat-grid">

<div>
    <span>Total verifications</span>
    <strong>
        <?= number_format(
            $verificationStats['total']
        ) ?>
    </strong>
</div>

<div>
    <span>Last 30 days</span>
    <strong>
        <?= number_format(
            $verificationStats['last_30']
        ) ?>
    </strong>
</div>

<div>
    <span>Llama Scouted</span>
    <strong>
        <?= number_format(
            $verificationStats['llama_scouted']
        ) ?>
    </strong>
</div>

<div>
    <span>Public data checked</span>
    <strong>
        <?= number_format(
            $verificationStats['public_checked']
        ) ?>
    </strong>
</div>

</section>


<section class="admin-panel admin-user-filter-panel">

<form
    class="admin-verification-filters"
    method="get"
>

<label class="admin-user-search">
    <span>Search</span>

    <div>
        <i
            class="fa-solid fa-magnifying-glass"
            aria-hidden="true"
        ></i>

        <input
            type="search"
            name="q"
            value="<?= moderation_e($search) ?>"
            placeholder="Place, town, source, notes, or Place ID"
        >
    </div>
</label>


<label>
    <span>Verification type</span>

    <select name="type">
        <option value="">
            All verification types
        </option>

        <?php foreach ($types as $option): ?>
            <option
                value="<?= moderation_e($option) ?>"
                <?= $type === $option
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    ucwords(
                        str_replace(
                            ['-', '_'],
                            ' ',
                            $option
                        )
                    )
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>


<label>
    <span>Age</span>

    <select name="age">
        <option value="">
            Any age
        </option>

        <option
            value="30"
            <?= $age === '30'
                ? 'selected'
                : '' ?>
        >
            Last 30 days
        </option>

        <option
            value="90"
            <?= $age === '90'
                ? 'selected'
                : '' ?>
        >
            Last 90 days
        </option>

        <option
            value="365"
            <?= $age === '365'
                ? 'selected'
                : '' ?>
        >
            Last year
        </option>

        <option
            value="older-365"
            <?= $age === 'older-365'
                ? 'selected'
                : '' ?>
        >
            Older than one year
        </option>
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
        href="/verifications.php"
    >
        Clear
    </a>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">

<div>
    <p>Verification History</p>

    <h2>
        <?= number_format(count($items)) ?>
        verification<?= count($items) === 1 ? '' : 's' ?>
        shown
    </h2>
</div>

<a
    class="admin-button"
    href="/places.php"
>
    Browse Places
</a>

</header>


<?php if (!$items): ?>

<div class="admin-empty-state">

<i
    class="fa-solid fa-binoculars"
    aria-hidden="true"
></i>

<h3>No verifications found.</h3>

<p>
    Try changing the current filters.
</p>

</div>

<?php else: ?>

<div class="admin-verification-list">

<?php foreach ($items as $item): ?>

<article class="admin-verification-row">

<span class="admin-user-table-avatar">

<img
    src="<?= moderation_e(
        admin_user_avatar_src(
            (string) (
                $item['verifier_profile_image']
                ?? ''
            ),
            $siteUrl
        )
    ) ?>"
    alt=""
    loading="lazy"
>

</span>


<div class="admin-verification-main">

<div class="admin-verification-heading">

<div>

<span>
    <?= moderation_e(
        ucwords(
            str_replace(
                ['-', '_'],
                ' ',
                (string) $item['verification_type']
            )
        )
    ) ?>
</span>

<h2>
    <?= moderation_e(
        (string) $item['place_name']
    ) ?>
</h2>

</div>


<?php if (
    (int) $item['public_data_verified'] === 1
): ?>

<span class="admin-status-pill">
    Public data checked
</span>

<?php endif; ?>

</div>


<p>
    <?= moderation_e(
        (string) $item['verifier_name']
    ) ?>

    · verified
    <?= moderation_e(
        (string) $item['verified_at']
    ) ?>

    <?php if (!empty($item['visited_at'])): ?>
        · visited
        <?= moderation_e(
            (string) $item['visited_at']
        ) ?>
    <?php endif; ?>
</p>


<p>
    <?= moderation_e(
        implode(
            ' · ',
            array_filter(
                [
                    $item['city'] ?? null,
                    $item['county'] ?? null,
                    $item['state'] ?? null,
                    $item['source'] ?? null,
                ]
            )
        )
    ) ?>
</p>


<?php if (!empty($item['notes'])): ?>

<div class="admin-verification-note">
    <?= nl2br(
        moderation_e(
            (string) $item['notes']
        )
    ) ?>
</div>

<?php endif; ?>

</div>


<div class="admin-verification-actions">

<a
    class="admin-button"
    href="/place.php?id=<?= (int) $item['place_id'] ?>#verification"
>
    Manage Place
</a>

<?php if (
    in_array(
        (string) $item['place_status'],
        ['active', 'featured'],
        true
    )
): ?>

<a
    class="admin-button is-muted"
    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
        (string) $item['place_slug']
    ) ?>"
    target="_blank"
    rel="noopener"
>
    View
</a>

<?php endif; ?>

</div>

</article>

<?php endforeach; ?>

</div>

<?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
