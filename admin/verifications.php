<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-verifications.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$search = trim((string) ($_GET['q'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));

$items = admin_place_freshness_rows(
    $db,
    $search,
    $state
);

$freshnessStats = admin_place_freshness_stats($db);
$attentionQueue = admin_place_freshness_attention_queue($db, 100);
$recentChecks = admin_place_recent_field_checks($db, 100);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Place Freshness';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'verifications';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel admin-verification-intro-panel">
    <header class="admin-panel-header">
        <div>
            <p>Field freshness</p>
            <h2>How recently was each Place physically checked?</h2>
        </div>

        <span>
            Official-source checks do not reset field freshness. This page tracks real-world visits and geofenced check-ins.
        </span>
    </header>
</section>

<section class="admin-verification-stat-grid" aria-label="Place freshness summary">
    <div class="is-fresh">
        <span>Fresh</span>
        <strong><?= number_format((int) $freshnessStats['fresh']) ?></strong>
        <small>Checked within 6 months</small>
    </div>

    <div class="is-aging">
        <span>Aging</span>
        <strong><?= number_format((int) $freshnessStats['aging']) ?></strong>
        <small>6 to 12 months</small>
    </div>

    <div class="is-attention">
        <span>Needs attention</span>
        <strong><?= number_format((int) $freshnessStats['attention']) ?></strong>
        <small>More than 1 year</small>
    </div>

    <div class="is-never">
        <span>Never checked</span>
        <strong><?= number_format((int) $freshnessStats['never_checked']) ?></strong>
        <small>No field check recorded</small>
    </div>
</section>

<section class="admin-panel admin-verification-operations-panel">
    <header class="admin-panel-header">
        <div>
            <p>Operational queue</p>
            <h2>Places to revisit</h2>
        </div>

        <span>
            Aging Places are approaching one year. Red states need a new field check when practical.
        </span>
    </header>

    <?php if (!$attentionQueue): ?>
        <div class="admin-empty-state admin-verification-all-current">
            <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>
            <h3>Every published Place is fresh.</h3>
            <p>No active or featured Place is currently aging, overdue, or missing a field check.</p>
        </div>
    <?php else: ?>
        <div class="admin-verification-attention-list">
            <?php foreach ($attentionQueue as $place): ?>
                <?php
                $freshness = (string) ($place['freshness_state'] ?? 'never');
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
                                <?= moderation_e((string) ($place['freshness_label'] ?? 'Never checked')) ?>
                            </span>

                            <span>
                                Overall: <?= moderation_e(llama_place_freshness_relative_label($place['overall_last_checked_at'] ?? null)) ?>
                            </span>

                            <span>
                                Community: <?= moderation_e(llama_place_freshness_relative_label($place['community_last_checked_at'] ?? null)) ?>
                            </span>

                            <span>
                                Scout: <?= moderation_e(llama_place_freshness_relative_label($place['scout_last_checked_at'] ?? null)) ?>
                            </span>

                            <?php if ((int) ($place['open_report_count'] ?? 0) > 0): ?>
                                <span class="is-report">
                                    <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>
                                    <?= number_format((int) $place['open_report_count']) ?> open report<?= (int) $place['open_report_count'] === 1 ? '' : 's' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-verification-attention-actions">
                        <a
                            class="admin-button"
                            href="/place.php?id=<?= (int) $place['id'] ?>"
                        >
                            Manage Place
                        </a>

                        <a
                            class="admin-button is-muted"
                            href="https://llamascout.com/place.php?slug=<?= rawurlencode((string) $place['slug']) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            View
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-panel admin-user-filter-panel">
    <form class="admin-verification-filters" method="get">
        <label class="admin-user-search">
            <span>Search</span>
            <div>
                <i aria-hidden="true"><?= llama_icon('search') ?></i>
                <input
                    type="search"
                    name="q"
                    value="<?= moderation_e($search) ?>"
                    placeholder="Place, town, county, state, or Place ID"
                >
            </div>
        </label>

        <label>
            <span>Freshness</span>
            <select name="state">
                <option value="">All published Places</option>
                <option value="fresh" <?= $state === 'fresh' ? 'selected' : '' ?>>Fresh</option>
                <option value="aging" <?= $state === 'aging' ? 'selected' : '' ?>>Aging</option>
                <option value="attention" <?= $state === 'attention' ? 'selected' : '' ?>>Needs attention</option>
                <option value="never" <?= $state === 'never' ? 'selected' : '' ?>>Never checked</option>
            </select>
        </label>

        <div class="admin-user-filter-actions">
            <button class="admin-button" type="submit">Filter</button>
            <a class="admin-button is-muted" href="/verifications.php">Clear</a>
        </div>
    </form>
</section>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Published Places</p>
            <h2>
                <?= number_format(count($items)) ?> Place<?= count($items) === 1 ? '' : 's' ?> shown
            </h2>
        </div>

        <a class="admin-button" href="/places.php">Browse Places</a>
    </header>

    <?php if (!$items): ?>
        <div class="admin-empty-state">
            <i aria-hidden="true"><?= llama_icon('binoculars') ?></i>
            <h3>No Places match these filters.</h3>
            <p>Try changing the search or freshness filter.</p>
        </div>
    <?php else: ?>
        <div class="admin-freshness-place-list">
            <?php foreach ($items as $item): ?>
                <?php $freshness = (string) ($item['freshness_state'] ?? 'never'); ?>

                <article class="admin-freshness-place-row is-<?= moderation_e($freshness) ?>">
                    <span class="admin-verification-light" aria-hidden="true"></span>

                    <div class="admin-freshness-place-main">
                        <div>
                            <strong><?= moderation_e((string) $item['name']) ?></strong>
                            <span>
                                <?= moderation_e(
                                    implode(
                                        ' · ',
                                        array_filter([
                                            $item['city'] ?? null,
                                            $item['county'] ?? null,
                                            $item['state'] ?? null,
                                        ])
                                    ) ?: 'Location not recorded'
                                ) ?>
                            </span>
                        </div>

                        <div class="admin-freshness-place-dates">
                            <span>
                                <small>Overall</small>
                                <strong><?= moderation_e(llama_place_freshness_relative_label($item['overall_last_checked_at'] ?? null)) ?></strong>
                            </span>
                            <span>
                                <small>Community</small>
                                <strong><?= moderation_e(llama_place_freshness_relative_label($item['community_last_checked_at'] ?? null)) ?></strong>
                            </span>
                            <span>
                                <small>Scout</small>
                                <strong><?= moderation_e(llama_place_freshness_relative_label($item['scout_last_checked_at'] ?? null)) ?></strong>
                            </span>
                        </div>
                    </div>

                    <div class="admin-freshness-place-side">
                        <span class="admin-status-pill is-<?= moderation_e($freshness) ?>">
                            <?= moderation_e((string) ($item['freshness_label'] ?? 'Never checked')) ?>
                        </span>

                        <div>
                            <a class="admin-button" href="/place.php?id=<?= (int) $item['id'] ?>">Manage</a>
                            <a
                                class="admin-button is-muted"
                                href="https://llamascout.com/place.php?slug=<?= rawurlencode((string) $item['slug']) ?>"
                                target="_blank"
                                rel="noopener"
                            >View</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-panel admin-recent-field-checks-panel">
    <header class="admin-panel-header">
        <div>
            <p>Recent activity</p>
            <h2>Field Checks</h2>
        </div>

        <span>
            Check-ins are geofenced. Older Scout field visits are retained as legacy field checks.
        </span>
    </header>

    <?php if (!$recentChecks): ?>
        <div class="admin-empty-state">
            <i aria-hidden="true"><?= llama_icon('current-location') ?></i>
            <h3>No field checks recorded yet.</h3>
            <p>New check-ins will appear here after the Check In workflow is used.</p>
        </div>
    <?php else: ?>
        <div class="admin-verification-list">
            <?php foreach ($recentChecks as $item): ?>
                <?php
                $level = llama_contribution_level_normalize(
                    (string) ($item['contribution_level'] ?? 'community')
                );
                ?>

                <article class="admin-verification-row">
                    <span class="admin-verification-check-icon" aria-hidden="true">
                        <?= llama_icon(llama_contribution_level_icon($level)) ?>
                    </span>

                    <div class="admin-verification-main">
                        <div class="admin-verification-heading">
                            <div>
                                <span><?= moderation_e(llama_contribution_level_short_label($level)) ?> field check</span>
                                <h2><?= moderation_e((string) $item['place_name']) ?></h2>
                            </div>

                            <span class="admin-status-pill">
                                <?= moderation_e(llama_contribution_level_short_label($level)) ?>
                            </span>
                        </div>

                        <p>
                            <?= moderation_e((string) $item['checker_name']) ?>
                            · <?= moderation_e(llama_format_viewer_datetime((string) $item['checked_at'])) ?>
                            <?php if ((string) $item['record_kind'] === 'checkin'): ?>
                                · geofenced
                            <?php else: ?>
                                · legacy Scout field visit
                            <?php endif; ?>
                        </p>

                        <?php if ((string) $item['record_kind'] === 'checkin'): ?>
                            <p>
                                <?= number_format((float) $item['distance_meters'], 0) ?> m from pin
                                · GPS accuracy <?= number_format((float) $item['accuracy_meters'], 0) ?> m
                                <?php if ((int) $item['points_awarded'] > 0): ?>
                                    · +<?= number_format((int) $item['points_awarded']) ?> points
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-verification-actions">
                        <a class="admin-button" href="/place.php?id=<?= (int) $item['place_id'] ?>">Manage Place</a>
                        <?php if (in_array((string) $item['place_status'], ['active', 'featured'], true)): ?>
                            <a
                                class="admin-button is-muted"
                                href="https://llamascout.com/place.php?slug=<?= rawurlencode((string) $item['place_slug']) ?>"
                                target="_blank"
                                rel="noopener"
                            >View</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
