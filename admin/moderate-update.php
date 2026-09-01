<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';

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
            $contributionId =
                moderation_approve_update(
                    $db,
                    $updateId,
                    (int) $adminUser['id'],
                    $notes,
                    $points
                );

            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                'place.update_approved',
                'Approved Place update #' . $updateId . '.',
                [
                    'update_id' => $updateId,
                    'place_id' => (int) $item['place_id'],
                    'contribution_id' => $contributionId,
                    'changed_fields' => array_keys($item['proposed']),
                    'points_awarded' => $points,
                ]
            );

            $db->commit();
            header('Location: /updates.php?approved=1');
            exit;
        }

        if (
            in_array(
                $action,
                [
                    'needs-changes',
                    'rejected',
                ],
                true
            )
        ) {
            if ($notes === '') {
                throw new InvalidArgumentException(
                    $action === 'needs-changes'
                        ? 'Add clear review notes explaining what the contributor needs to change.'
                        : 'Add review notes explaining why the update was not approved.'
                );
            }

            moderation_set_update_status(
                $db,
                $updateId,
                (int) $adminUser['id'],
                $action,
                $notes
            );

            $auditAction =
                $action === 'needs-changes'
                    ? 'place.update_changes_requested'
                    : 'place.update_rejected';

            $auditSummary =
                $action === 'needs-changes'
                    ? 'Requested changes to Place update #' . $updateId . '.'
                    : 'Rejected Place update #' . $updateId . '.';

            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                $auditAction,
                $auditSummary,
                [
                    'update_id' => $updateId,
                    'place_id' => (int) $item['place_id'],
                    'review_notes' => $notes,
                ]
            );

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

$definitions =
    community_place_update_field_definitions();

$groupedChanges = [];

foreach ($proposed as $path => $newValue) {
    $definition =
        $definitions[$path]
        ?? [
            'label' =>
                ucwords(
                    str_replace(
                        ['.', '_'],
                        ' ',
                        $path
                    )
                ),
            'group' => 'Other',
            'type' => 'text',
        ];

    $groupedChanges[
        (string) $definition['group']
    ][$path] = [
        'definition' => $definition,
        'old' => $original[$path] ?? null,
        'new' => $newValue,
    ];
}

function moderate_update_value(
    mixed $value,
    string $type
): string {
    if ($value === null || $value === '') {
        return 'Unknown';
    }

    if ($type === 'bool') {
        return (int) $value === 1
            ? 'Yes'
            : 'No';
    }

    if ($type === 'rating') {
        return (string) $value . '/5';
    }

    return (string) $value;
}
?>

<?php if ($error !== ''): ?>
    <div class="admin-moderation-notice">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-moderation-detail">
    <h2><?= moderation_e($item['place_name']) ?></h2>

    <p>
        Submitted by
        <strong>
            <?= moderation_e(
                $item['display_name']
                ?: $item['username']
            ) ?>
        </strong>

        on
        <?= moderation_e($item['submitted_at']) ?>.

        <?php if (!empty($item['visited_at'])): ?>
            Visited
            <?= moderation_e($item['visited_at']) ?>.
        <?php endif; ?>

        <a
            href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                (string) $item['place_slug']
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Open live Place
        </a>
    </p>

    <?php if (!empty($item['contributor_notes'])): ?>
        <p>
            <strong>Contributor notes:</strong><br>
            <?= nl2br(
                moderation_e(
                    $item['contributor_notes']
                )
            ) ?>
        </p>
    <?php endif; ?>
</div>


<?php foreach ($groupedChanges as $group => $changes): ?>

<section class="admin-moderation-detail">

<h2><?= moderation_e($group) ?></h2>

<?php foreach ($changes as $path => $change): ?>

<?php
$definition =
    $change['definition'];

$type =
    (string) (
        $definition['type']
        ?? 'text'
    );
?>

<div class="admin-moderation-field admin-update-comparison">

<span>
    <?= moderation_e(
        (string) (
            $definition['label']
            ?? $path
        )
    ) ?>
</span>

<div class="admin-moderation-change">

<div class="admin-moderation-old">
    <strong>Current when submitted</strong><br>

    <?= nl2br(
        moderation_e(
            moderate_update_value(
                $change['old'],
                $type
            )
        )
    ) ?>
</div>

<div class="admin-moderation-new">
    <strong>Proposed</strong><br>

    <?= nl2br(
        moderation_e(
            moderate_update_value(
                $change['new'],
                $type
            )
        )
    ) ?>
</div>

</div>

</div>

<?php endforeach; ?>

</section>

<?php endforeach; ?>


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
            <button class="admin-moderation-button is-warning" type="submit" name="action" value="needs-changes">Request Changes</button>
            <button class="admin-moderation-button is-danger" type="submit" name="action" value="rejected">Not Approved</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
