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

$resourcesOnly = ($_GET['kind'] ?? '') === 'resource';
$adminPageTitle = $resourcesOnly ? 'New Resource Submissions' : 'New Place and Resource Submissions';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'submissions';

require __DIR__ . '/_header.php';

$resources = llama_resource_submission_queue($db, false);
$items = $resourcesOnly ? $resources : llama_resource_merge_queue(moderation_new_place_queue($db), $resources);
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
            <p><?= $resourcesOnly ? 'There are no new Resource submissions waiting for review.' : 'There are no new Place or Resource submissions waiting for review.' ?></p>
        </div>

    <?php else: ?>

        <div class="admin-queue-list">
            <?php foreach ($items as $item): ?>
                <?php $isResource = !empty($item['is_resource']); ?>
                <article class="admin-queue-card">
                    <div>
                        <span class="admin-queue-type"><?= $isResource ? 'New Resource' : 'New Place' ?></span>
                        <h2><?= moderation_e($item['place_name']) ?></h2>
                        <p>
                            <?= moderation_e($item['display_name'] ?: ($item['username'] ?: 'Former Llama Scout Member')) ?>
                            · submitted <?= moderation_e(
                                llama_format_viewer_datetime(
                                    (string) $item['submitted_at']
                                )
                            ) ?>
                            <?php if (!$isResource): ?>· <?= moderation_e($item['role_at_submission'] ?: 'user') ?><?php endif; ?>
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
                            href="<?= $isResource ? '/resources.php?submission=' : '/moderate-submission.php?id=' ?><?= (int) $item['id'] ?>"
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
