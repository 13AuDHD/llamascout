<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$maintenanceState = llama_maintenance_state($db);

$stats = admin_dashboard_stats($db);
$queue = admin_dashboard_queue($db);

/*
 * Newsletters are optional until their migration is installed.
 * Keep the main Admin dashboard safe if that table does not
 * exist yet.
 */
$newsletterQueueCount = 0;
$newsletterNextSendAt = null;

try {
    $newsletterQueueCount = (int) $db
        ->query(
            "SELECT COUNT(*)
             FROM newsletter_issues
             WHERE status IN ('scheduled','sending')
               AND sent_at IS NULL"
        )
        ->fetchColumn();

    $newsletterNextSendAt = $db
        ->query(
            "SELECT send_at
             FROM newsletter_issues
             WHERE status IN ('scheduled','sending')
               AND sent_at IS NULL
               AND send_at IS NOT NULL
             ORDER BY send_at ASC, id ASC
             LIMIT 1"
        )
        ->fetchColumn();

    if (!$newsletterNextSendAt) {
        $newsletterNextSendAt = null;
    }
} catch (Throwable $exception) {
    $newsletterQueueCount = 0;
    $newsletterNextSendAt = null;
}

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

$adminUsesSplitCss = true;

$adminPageStyles = [
    'dashboard.css',
];

$adminFeatureStyles = [];

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

    <a class="admin-stat-card is-action" href="/support.php">
        <span class="admin-stat-icon">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
        </span>
        <div>
            <span>Support Tickets</span>
            <strong><?= (int) $stats['support'] ?></strong>
            <small>Open or waiting</small>
        </div>
    </a>

    <a
        class="admin-stat-card is-action"
        href="/scouts.php?filter=attention"
    >
        <span class="admin-stat-icon">
            <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
        </span>
        <div>
            <span>Scout Reviews</span>
            <strong><?= (int) $stats['scout_reviews'] ?></strong>
            <small>Applications or approvals</small>
        </div>
    </a>

    <a
        class="admin-stat-card is-action"
        href="/orders.php"
    >
        <span class="admin-stat-icon">
            <i class="fa-solid fa-box" aria-hidden="true"></i>
        </span>
        <div>
            <span>Orders to Fulfill</span>
            <strong><?= (int) $stats['orders'] ?></strong>
            <small>Paid, not completed</small>
        </div>
    </a>

    <a
        class="admin-stat-card is-action"
        href="/newsletters.php"
    >
        <span class="admin-stat-icon">
            <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
        </span>
        <div>
            <span>Newsletter Queue</span>
            <strong><?= $newsletterQueueCount ?></strong>
            <small>
                <?php if ($newsletterNextSendAt): ?>
                    Next: <?= moderation_e(
                        admin_format_datetime(
                            (string) $newsletterNextSendAt
                        )
                    ) ?>
                <?php else: ?>
                    No scheduled issues
                <?php endif; ?>
            </small>
        </div>
    </a>

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
                    Moderation, support, Scout reviews, and paid-order
                    queues are currently clear.
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

            <?php require __DIR__ . '/_acquisition.php'; ?>

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

                <div>
                    <dt>Online Now</dt>
                    <dd><?= number_format((int) $stats['online_now']) ?></dd>
                </div>

                <div>
                    <dt>Active Last Hour</dt>
                    <dd><?= number_format((int) $stats['active_hour']) ?></dd>
                </div>

                <div>
                    <dt>Active Today</dt>
                    <dd><?= number_format((int) $stats['active_today']) ?></dd>
                </div>

            </dl>

            <p class="admin-dashboard-activity-note">
                Online Now = activity within 5 minutes.
                Active Today begins at midnight Mountain Time.
            </p>

        </section>


        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Operations</p>
                    <h2>Admin Systems</h2>
                </div>
            </header>

            <div class="admin-module-list">

                <a class="is-ready" href="/support.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Support</strong>
                        Customer tickets and linked error reports
                    </span>
                </a>

                <a class="is-ready" href="/newsletters.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Newsletters</strong>
                        Monthly and member-only email publications
                    </span>
                </a>

                <a class="is-ready" href="/submissions.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Place Moderation</strong>
                        New Places and suggested updates
                    </span>
                </a>

                <a class="is-ready" href="/reports.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Place Operations</strong>
                        Reports, verification, canonical records
                    </span>
                </a>

                <a class="is-ready" href="/users.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Users + Scouts</strong>
                        Accounts, Scout periods, Master Scout
                    </span>
                </a>

                <a class="is-ready" href="/badges.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Badges + Achievements</strong>
                        Definitions, images, awards
                    </span>
                </a>

                <a class="is-ready" href="/points.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Policies + Points</strong>
                        Scout policy and contribution scoring
                    </span>
                </a>

                <a class="is-ready" href="/orders.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>Shop + Orders</strong>
                        Products, orders, fulfillment
                    </span>
                </a>

                <a class="is-ready" href="/system.php">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>
                        <strong>System + Audit</strong>
                        Health, maintenance, staging, audit log
                    </span>
                </a>

            </div>

        </section>

    </div>

</div>

<?php require __DIR__ . '/_footer.php'; ?>
