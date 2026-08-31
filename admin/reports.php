<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
];

$adminPageTitle = 'Problem Reports';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'reports';

require __DIR__ . '/_header.php';

$items = moderation_report_queue($db);
?>

<section class="admin-panel">

    <?php if (!$items): ?>

        <div class="admin-empty-state">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <h2>Queue clear.</h2>
            <p>There are no open Place reports.</p>
        </div>

    <?php else: ?>

        <div class="admin-queue-list">
            <?php foreach ($items as $item): ?>
                <article class="admin-queue-card">
                    <div>
                        <span class="admin-queue-type">Problem Report</span>
                        <h2><?= moderation_e($item['place_name']) ?></h2>
                        <p>
                            <?= moderation_e(
                                ucwords(
                                    str_replace(
                                        ['-','_'],
                                        ' ',
                                        (string) $item['problem_type']
                                    )
                                )
                            ) ?>
                            · <?= moderation_e($item['display_name'] ?: $item['username']) ?>
                            · <?= (int) $item['image_count'] ?> photo<?= (int) $item['image_count'] === 1 ? '' : 's' ?>
                        </p>
                    </div>

                    <div class="admin-queue-actions">
                        <span class="admin-status-pill">
                            <?= moderation_e(
                                moderation_status_label(
                                    (string) $item['status']
                                )
                            ) ?>
                        </span>

                        <a
                            class="admin-button"
                            href="/moderate-report.php?id=<?= (int) $item['id'] ?>"
                        >
                            Review
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</section>

<?php require __DIR__ . '/_footer.php'; ?>
