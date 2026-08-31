<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$maintenanceState = llama_maintenance_state($db);

$stats = admin_dashboard_stats($db);
$queue = admin_dashboard_queue($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Dashboard';
$adminPageEyebrow = 'Operations Center';
$adminActiveNav = 'dashboard';

require __DIR__ . '/_header.php';
?>

<section class="admin-status-strip">
    <div>
        <span
            class="admin-status-dot <?= $maintenanceState['enabled']
                ? 'is-maintenance'
                : 'is-live' ?>"
            aria-hidden="true"
        ></span>

        <strong>
            <?= $maintenanceState['enabled']
                ? 'Maintenance mode'
                : 'Site live' ?>
        </strong>

        <span>
            <?= $maintenanceState['enabled']
                ? 'Public access is currently restricted.'
                : 'Public site is operating normally.' ?>
        </span>
    </div>

    <a
        class="admin-status-strip-note"
        href="/system.php"
    >
        System controls
    </a>
</section>


<section class="admin-stat-grid" aria-label="Admin work queues">

    <a class="admin-stat-card is-action" href="/submissions.php">
        <span class="admin-stat-icon">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        </span>
        <div>
            <span>Pending Places</span>
            <strong><?= (int) $stats['new_places'] ?></strong>
            <small>New Place submissions</small>
        </div>
    </a>

    <a class="admin-stat-card is-action" href="/updates.php">
        <span class="admin-stat-icon">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
        </span>
        <div>
            <span>Pending Updates</span>
            <strong><?= (int) $stats['updates'] ?></strong>
            <small>Existing Place changes</small>
        </div>
    </a>

    <a class="admin-stat-card is-action" href="/reports.php">
        <span class="admin-stat-icon">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        </span>
        <div>
            <span>Open Reports</span>
            <strong><?= (int) $stats['reports'] ?></strong>
            <small>Problems needing attention</small>
        </div>
    </a>

    <article class="admin-stat-card">
        <span class="admin-stat-icon">
            <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
        </span>
        <div>
            <span>Scout Reviews</span>
            <strong><?= (int) $stats['scout_reviews'] ?></strong>
            <small>Applications or approvals</small>
        </div>
    </article>

    <article class="admin-stat-card">
        <span class="admin-stat-icon">
            <i class="fa-solid fa-box" aria-hidden="true"></i>
        </span>
        <div>
            <span>Orders to Fulfill</span>
            <strong><?= (int) $stats['orders'] ?></strong>
            <small>Paid, not completed</small>
        </div>
    </article>

</section>


<div class="admin-dashboard-layout">

    <section class="admin-panel admin-inbox">

        <header class="admin-panel-header">
            <div>
                <p>Work Queue</p>
                <h2>Admin Inbox</h2>
            </div>

            <span>
                <?= count($queue) ?> recent item<?= count($queue) === 1 ? '' : 's' ?>
            </span>
        </header>

        <?php if (!$queue): ?>

            <div class="admin-empty-state">
                <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                <h3>Nothing is yelling for attention.</h3>
                <p>
                    The moderation queues, Scout reviews, and paid-order queue
                    are currently clear.
                </p>
            </div>

        <?php else: ?>

            <div class="admin-inbox-list">

                <?php foreach ($queue as $item): ?>

                    <article class="admin-inbox-item">

                        <span class="admin-inbox-icon">
                            <i
                                class="fa-solid <?= moderation_e($item['icon']) ?>"
                                aria-hidden="true"
                            ></i>
                        </span>

                        <div class="admin-inbox-content">

                            <span class="admin-inbox-type">
                                <?= moderation_e($item['type']) ?>
                            </span>

                            <strong>
                                <?= moderation_e($item['title']) ?>
                            </strong>

                            <p><?= moderation_e($item['meta']) ?></p>

                            <time>
                                <?= moderation_e(
                                    admin_format_datetime(
                                        (string) $item['time']
                                    )
                                ) ?>
                            </time>

                        </div>

                        <?php if ($item['href'] !== ''): ?>
                            <a
                                class="admin-button"
                                href="<?= moderation_e($item['href']) ?>"
                            >
                                <?= moderation_e($item['action']) ?>
                            </a>
                        <?php else: ?>
                            <span class="admin-button is-muted">
                                <?= moderation_e($item['action']) ?>
                            </span>
                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <div class="admin-dashboard-side">

        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Platform</p>
                    <h2>At a Glance</h2>
                </div>
            </header>

            <dl class="admin-metric-list">

                <div>
                    <dt>Published Places</dt>
                    <dd><?= number_format((int) $stats['places']) ?></dd>
                </div>

                <div>
                    <dt>Active Accounts</dt>
                    <dd><?= number_format((int) $stats['members']) ?></dd>
                </div>

                <div>
                    <dt>Paid Members</dt>
                    <dd><?= number_format((int) $stats['paid_members']) ?></dd>
                </div>

            </dl>

        </section>


        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Build Status</p>
                    <h2>Admin Modules</h2>
                </div>
            </header>

            <div class="admin-module-list">

                <div class="is-ready">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Moderation</strong>
                        New Places, updates, reports
                    </span>
                </div>

                <div>
                    <i class="fa-regular fa-circle" aria-hidden="true"></i>
                    <span>
                        <strong>Users + Scouts</strong>
                        Accounts, anonymization, ranks
                    </span>
                </div>

                <div>
                    <i class="fa-regular fa-circle" aria-hidden="true"></i>
                    <span>
                        <strong>Policies + Points</strong>
                        Versioning and point ledger
                    </span>
                </div>

                <div>
                    <i class="fa-regular fa-circle" aria-hidden="true"></i>
                    <span>
                        <strong>Shop + Orders</strong>
                        Products and fulfillment
                    </span>
                </div>

                <div>
                    <i class="fa-regular fa-circle" aria-hidden="true"></i>
                    <span>
                        <strong>System</strong>
                        Maintenance and audit log
                    </span>
                </div>

            </div>

        </section>

    </div>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
