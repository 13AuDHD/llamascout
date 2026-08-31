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

$adminPageTitle = 'Place Updates';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'updates';

require __DIR__ . '/_header.php';

$items = moderation_update_queue($db);
?>

<section class="admin-panel">

    <?php if (!$items): ?>

        <div class="admin-empty-state">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <h2>Queue clear.</h2>
            <p>There are no Place updates waiting for review.</p>
        </div>

    <?php else: ?>

        <div class="admin-queue-list">
            <?php foreach ($items as $item): ?>
                <?php $changes = moderation_decode_json($item['proposed_changes']); ?>

                <article class="admin-queue-card">
                    <div>
                        <span class="admin-queue-type">Place Update</span>
                        <h2><?= moderation_e($item['place_name']) ?></h2>
                        <p>
                            <?= moderation_e($item['display_name'] ?: $item['username']) ?>
                            · <?= count($changes) ?> changed field<?= count($changes) === 1 ? '' : 's' ?>
                            · submitted <?= moderation_e($item['submitted_at']) ?>
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
                            href="/moderate-update.php?id=<?= (int) $item['id'] ?>"
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
