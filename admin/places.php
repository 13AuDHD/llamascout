<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$search =
    trim((string) ($_GET['q'] ?? ''));

$status =
    trim((string) ($_GET['status'] ?? ''));

$state =
    trim((string) ($_GET['state'] ?? ''));

$places = admin_places_list(
    $db,
    $search,
    $status,
    $state
);

$states =
    admin_places_states($db);

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Places';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'places';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel admin-user-filter-panel">

<form
    class="admin-place-filters"
    method="get"
>

<label class="admin-user-search">
    <span>Search Places</span>
    <div>
        <i
            class="fa-solid fa-magnifying-glass"
            aria-hidden="true"
        ></i>

        <input
            type="search"
            name="q"
            value="<?= moderation_e($search) ?>"
            placeholder="Name, road, town, land manager, or ID"
        >
    </div>
</label>

<label>
    <span>Status</span>
    <select name="status">
        <option value="">All statuses</option>

        <?php foreach (
            [
                'featured',
                'active',
                'draft',
                'unlisted',
                'archived',
                'removed',
            ] as $option
        ): ?>
            <option
                value="<?= moderation_e($option) ?>"
                <?= $status === $option
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e(
                    ucfirst($option)
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
</label>

<label>
    <span>State</span>
    <select name="state">
        <option value="">All states</option>

        <?php foreach ($states as $option): ?>
            <option
                value="<?= moderation_e($option) ?>"
                <?= $state === $option
                    ? 'selected'
                    : '' ?>
            >
                <?= moderation_e($option) ?>
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
        href="/places.php"
    >
        Clear
    </a>
</div>

</form>

</section>


<section class="admin-panel">

<header class="admin-panel-header">
    <div>
        <p>Canonical Place Database</p>
        <h2>
            <?= number_format(count($places)) ?>
            Places shown
        </h2>
    </div>

    <a
        class="admin-button"
        href="/submissions.php"
    >
        Review new submissions
    </a>
</header>

<?php if (!$places): ?>

<div class="admin-empty-state">
    <i
        class="fa-solid fa-map-location-dot"
        aria-hidden="true"
    ></i>

    <h3>No Places found.</h3>

    <p>
        Try changing the current filters.
    </p>
</div>

<?php else: ?>

<div class="admin-place-list">

<?php foreach ($places as $place): ?>

<article class="admin-place-list-card">

<div class="admin-place-list-image">

<?php if (!empty($place['featured_image'])): ?>
    <img
        src="<?= moderation_e(
            llama_photo_public_url(
                (string) $place['featured_image']
            )
        ) ?>"
        alt=""
        loading="lazy"
    >
<?php else: ?>
    <i
        class="fa-solid fa-mountain-sun"
        aria-hidden="true"
    ></i>
<?php endif; ?>

</div>


<div class="admin-place-list-main">

<div class="admin-place-list-heading">
    <div>
        <span>
            Place #<?= (int) $place['id'] ?>
        </span>

        <h2>
            <?= moderation_e(
                (string) $place['name']
            ) ?>
        </h2>
    </div>

    <span class="admin-status-pill">
        <?= moderation_e(
            ucfirst(
                (string) $place['status']
            )
        ) ?>
    </span>
</div>

<p>
    <?= moderation_e(
        implode(
            ' · ',
            array_filter(
                [
                    $place['city'] ?? null,
                    $place['county'] ?? null,
                    $place['state'] ?? null,
                    $place['land_manager'] ?? null,
                ]
            )
        )
    ) ?>
</p>

<div class="admin-place-list-facts">
    <span>
        <i class="fa-regular fa-images" aria-hidden="true"></i>
        <?= number_format(
            (int) $place['image_count']
        ) ?>
    </span>

    <span>
        <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
        <?= number_format(
            (int) $place['verification_count']
        ) ?>
    </span>

    <span class="<?= (int) $place['open_report_count'] > 0
        ? 'has-alert'
        : '' ?>">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <?= number_format(
            (int) $place['open_report_count']
        ) ?>
    </span>

    <span>
        Last checked:
        <?= moderation_e(
            (string) (
                $place['last_verified_at']
                ?: 'Never'
            )
        ) ?>
    </span>
</div>

</div>


<div class="admin-place-list-actions">

<a
    class="admin-button"
    href="/place.php?id=<?= (int) $place['id'] ?>"
>
    Manage
</a>

<?php if (
    in_array(
        (string) $place['status'],
        ['active','featured'],
        true
    )
): ?>
    <a
        class="admin-button is-muted"
        href="https://llamascout.com/place.php?slug=<?= rawurlencode(
            (string) $place['slug']
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
