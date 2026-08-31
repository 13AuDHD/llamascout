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
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'New Place Submissions';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'submissions';

require __DIR__ . '/_header.php';

$items = moderation_new_place_queue($db);
?>

<section class="admin-panel">

    <?php if (!$items): ?>

        <div class="admin-empty-state">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <h2>Queue clear.</h2>
            <p>There are no new Place submissions waiting for review.</p>
        </div>

    <?php else: ?>

        <div class="admin-queue-list">
            <?php foreach ($items as $item): ?>
                <article class="admin-queue-card">
                    <div>
                        <span class="admin-queue-type">New Place</span>
                        <h2><?= moderation_e($item['place_name']) ?></h2>
                        <p>
                            <?= moderation_e($item['display_name'] ?: $item['username']) ?>
                            · submitted <?= moderation_e($item['submitted_at']) ?>
                            · <?= moderation_e($item['role_at_submission'] ?: 'user') ?>
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
                            href="/moderate-submission.php?id=<?= (int) $item['id'] ?>"
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
