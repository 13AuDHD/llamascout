<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once __DIR__ . '/_dashboard.php';
require_once dirname(__DIR__) . '/app/resource-submissions.php';

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

$adminPageTitle = 'Place and Resource Updates';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'updates';

require __DIR__ . '/_header.php';

$items = llama_resource_merge_queue(moderation_update_queue($db), llama_resource_submission_queue($db, true));
?>

<?php
$resourceReviewNotice = match ($_GET['resource_review'] ?? '') {
    'approved' => 'Resource contribution approved.',
    'rejected' => 'Resource contribution rejected.',
    'needs-changes' => 'Changes requested from the contributor.',
    default => '',
};
?>
<?php if ($resourceReviewNotice !== ''): ?>
    <div class="admin-user-notice is-success" role="status"><?= moderation_e($resourceReviewNotice) ?></div>
<?php endif; ?>

<section class="admin-panel">

    <?php if (!$items): ?>

        <div class="admin-empty-state">
            <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>
            <h2>Queue clear.</h2>
            <p>There are no Place or Resource updates waiting for review.</p>
        </div>

    <?php else: ?>

        <div class="admin-queue-list">
            <?php foreach ($items as $item): ?>
                <?php $isResource = !empty($item['is_resource']); ?>
                <?php $changes = moderation_decode_json($item['proposed_changes'] ?? ''); ?>

                <article class="admin-queue-card">
                    <div>
                        <span class="admin-queue-type"><?= $isResource ? 'Resource Update' : 'Place Update' ?></span>
                        <h2><?= moderation_e($item['place_name']) ?></h2>
                        <p>
                            <?= moderation_e($item['display_name'] ?: ($item['username'] ?: 'Former Llama Scout Member')) ?>
                            <?php if (!$isResource): ?>· <?= count($changes) ?> changed field<?= count($changes) === 1 ? '' : 's' ?><?php endif; ?>
                            · submitted <?= moderation_e(
                                llama_format_viewer_datetime(
                                    (string) $item['submitted_at']
                                )
                            ) ?>
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
                            href="<?= $isResource ? '/resources.php?submission=' : '/moderate-update.php?id=' ?><?= (int) $item['id'] ?>"
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
