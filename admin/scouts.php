<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-scouts.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$scouts = admin_scouts_list($db);
$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Scouts';
$adminPageEyebrow = 'People';
$adminActiveNav = 'scouts';

require __DIR__ . '/_header.php';
?>

<section class="admin-panel">

    <header class="admin-panel-header">
        <div>
            <p>Scout Program</p>
            <h2><?= number_format(count($scouts)) ?> Scout profiles</h2>
        </div>

        <a class="admin-button" href="/policies.php">
            Scout policies
        </a>
    </header>

    <?php if (!$scouts): ?>
        <div class="admin-empty-state">
            <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
            <h3>No Scout profiles yet.</h3>
        </div>
    <?php else: ?>

        <div class="admin-user-table-wrap">
            <table class="admin-user-table">
                <thead>
                    <tr>
                        <th>Scout</th>
                        <th>Rank</th>
                        <th>Status</th>
                        <th>Scout Points</th>
                        <th>Activity</th>
                        <th>Active Through</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scouts as $scout): ?>
                    <?php
                    $roles = explode(
                        ',',
                        (string) ($scout['role_slugs'] ?? '')
                    );
                    $isMaster = in_array(
                        'master_scout',
                        $roles,
                        true
                    );
                    ?>
                    <tr>
                        <td>
                            <div class="admin-user-identity">
                                <span class="admin-user-table-avatar" aria-hidden="true">
                                    <?= moderation_e(
                                        strtoupper(
                                            substr(
                                                (string) (
                                                    $scout['display_name']
                                                    ?: $scout['username']
                                                    ?: 'S'
                                                ),
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>
                                </span>
                                <div>
                                    <strong>
                                        <?= moderation_e(
                                            $scout['display_name']
                                            ?: $scout['username']
                                            ?: 'Scout'
                                        ) ?>
                                    </strong>
                                    <span>
                                        @<?= moderation_e(
                                            (string) $scout['username']
                                        ) ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="admin-status-pill">
                                <?= $isMaster ? 'Master Scout' : 'Llama Scout' ?>
                            </span>
                        </td>
                        <td>
                            <span class="admin-status-pill">
                                <?= moderation_e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string) $scout['status']
                                        )
                                    )
                                ) ?>
                            </span>
                        </td>
                        <td><?= number_format((int) $scout['scout_points']) ?></td>
                        <td><?= number_format((int) $scout['activity_count']) ?></td>
                        <td>
                            <span class="admin-table-muted">
                                <?= moderation_e(
                                    (string) (
                                        $scout['active_through']
                                        ?: 'Not active'
                                    )
                                ) ?>
                            </span>
                        </td>
                        <td>
                            <a
                                class="admin-button"
                                href="/scout.php?id=<?= (int) $scout['id'] ?>"
                            >
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
