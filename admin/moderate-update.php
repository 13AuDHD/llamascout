<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$adminUser = moderation_require_admin();
$db = db();
$csrfToken = moderation_csrf_token();
require_once __DIR__ . '/_dashboard.php';

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Review Place Update';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'updates';

require __DIR__ . '/_header.php';

$updateId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = moderation_update($db, $updateId);
$error = '';

if (!$item) {
    http_response_code(404);
    echo '<div class="admin-moderation-notice">Update not found.</div>';
    require __DIR__ . '/_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Your session could not be verified. Reload the page and try again.');
        }

        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['review_notes'] ?? ''));
        $points = max(0, (int) ($_POST['points'] ?? 0));

        $db->beginTransaction();

        if ($action === 'approve') {
            moderation_approve_update($db, $updateId, (int) $adminUser['id'], $notes, $points);
            $db->commit();
            header('Location: /updates.php?approved=1');
            exit;
        }

        if ($action === 'rejected') {
            if ($notes === '') {
                throw new InvalidArgumentException('Add review notes explaining why the update was not approved.');
            }
            moderation_set_update_status($db, $updateId, (int) $adminUser['id'], $action, $notes);
            $db->commit();
            header('Location: /updates.php?updated=1');
            exit;
        }

        throw new InvalidArgumentException('Choose a moderation action.');
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $exception->getMessage();
    }

    $item = moderation_update($db, $updateId);
}

$proposed = $item['proposed'];
$original = $item['original'];
$photos = $item['photo_list'];
?>

<?php if ($error !== ''): ?>
    <div class="admin-moderation-notice"><?= moderation_e($error) ?></div>
<?php endif; ?>

<div class="admin-moderation-detail">
    <h2><?= moderation_e($item['place_name']) ?></h2>
    <p>
        Submitted by <strong><?= moderation_e($item['display_name'] ?: $item['username']) ?></strong>
        on <?= moderation_e($item['submitted_at']) ?>.
        <a href="https://llamascout.com/place.php?slug=<?= rawurlencode((string) $item['place_slug']) ?>" target="_blank" rel="noopener">Open live Place</a>
    </p>

    <?php if (!empty($item['contributor_notes'])): ?>
        <p><strong>Contributor notes:</strong><br><?= nl2br(moderation_e($item['contributor_notes'])) ?></p>
    <?php endif; ?>

    <?php foreach ($proposed as $field => $newValue): ?>
        <div class="admin-moderation-field" style="margin-top:12px;">
            <span><?= moderation_e(ucwords(str_replace('_', ' ', $field))) ?></span>
            <div class="admin-moderation-change">
                <div class="admin-moderation-old">
                    <strong>Original</strong><br>
                    <?= nl2br(moderation_e($original[$field] ?? '')) ?>
                </div>
                <div class="admin-moderation-new">
                    <strong>Proposed</strong><br>
                    <?= nl2br(moderation_e($newValue ?? '')) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($photos): ?>
    <div class="admin-moderation-detail">
        <h2>Submitted Photos</h2>
        <div class="admin-moderation-photo-grid">
            <?php foreach ($photos as $photo): ?>
                <?php $src = moderation_photo_path($photo); ?>
                <?php if ($src !== ''): ?>
                    <img src="https://llamascout.com<?= moderation_e($src) ?>" alt="<?= moderation_e($photo['alt'] ?? '') ?>">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="admin-moderation-detail">
    <h2>Decision</h2>
    <form method="post" class="admin-moderation-form">
        <input type="hidden" name="id" value="<?= $updateId ?>">
        <input type="hidden" name="csrf_token" value="<?= moderation_e($csrfToken) ?>">

        <label>
            Contribution points
            <input type="number" name="points" min="0" step="1" value="0">
        </label>

        <label>
            Review notes
            <textarea name="review_notes" rows="5" placeholder="Required when requesting changes or not approving."></textarea>
        </label>

        <div class="admin-moderation-actions">
            <button class="admin-moderation-button is-primary" type="submit" name="action" value="approve">Approve Update</button>
            <button class="admin-moderation-button is-danger" type="submit" name="action" value="rejected">Not Approved</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
