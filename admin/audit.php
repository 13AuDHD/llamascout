<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-system.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$rows = admin_system_audit_rows($db, 250);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Audit Log';
$adminPageEyebrow = 'System';
$adminActiveNav = 'audit';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">

    <header class="admin-panel-header">
        <div>
            <p>Administrative History</p>
            <h2>Latest <?= number_format(count($rows)) ?> actions</h2>
        </div>

        <span>Newest first</span>
    </header>

    <?php if (!$rows): ?>

        <div class="admin-empty-state">
            <p>No administrative actions have been logged yet.</p>
        </div>

    <?php else: ?>

        <div class="admin-audit-list">

            <?php foreach ($rows as $row): ?>

                <article class="admin-audit-row">

                    <span class="admin-audit-icon">
                        <i
                            class="fa-solid <?= moderation_e(
                                str_starts_with(
                                    (string) $row['action'],
                                    'user.'
                                )
                                    ? 'fa-user-gear'
                                    : (
                                        str_starts_with(
                                            (string) $row['action'],
                                            'scout.'
                                        )
                                            ? 'fa-binoculars'
                                            : (
                                                str_starts_with(
                                                    (string) $row['action'],
                                                    'points.'
                                                )
                                                    ? 'fa-star'
                                                    : 'fa-gears'
                                            )
                                    )
                            ) ?>"
                            aria-hidden="true"
                        ></i>
                    </span>

                    <div>
                        <strong>
                            <?= moderation_e(
                                (string) $row['summary']
                            ) ?>
                        </strong>

                        <span>
                            <?= moderation_e(
                                (string) $row['actor_name']
                            ) ?>

                            <?php if (!empty($row['target_name'])): ?>
                                ·
                                <?= moderation_e(
                                    (string) $row['target_name']
                                ) ?>
                            <?php endif; ?>

                            ·
                            <?= moderation_e(
                                (string) $row['created_at']
                            ) ?>
                        </span>

                        <small>
                            <?= moderation_e(
                                (string) $row['action']
                            ) ?>

                            <?php if (!empty($row['ip_address'])): ?>
                                · IP
                                <?= moderation_e(
                                    (string) $row['ip_address']
                                ) ?>
                            <?php endif; ?>
                        </small>
                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
