<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mail.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/resource-submissions.php';
require_once dirname(__DIR__) . '/app/contribution-presentation.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$tablesReady = admin_resources_tables_ready($db);
$submissionsReady = llama_resource_submissions_ready($db);
$reviewNotesReady = $submissionsReady && llama_resource_review_notes_ready($db);
$submissionId = max(0, (int) ($_GET['submission'] ?? $_POST['submission_id'] ?? 0));
$returnPlaceId = max(0, (int) ($_POST['return_place_id'] ?? 0));
$notice = '';
$error = '';
$resourceId =
    max(
        0,
        (int) (
            $_GET['id']
            ?? $_POST['resource_id']
            ?? 0
        )
    );

if (
    $tablesReady
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['resource_action']
                        ?? ''
                    )
                );

            $decision = match ($action) {
                'approve_submission' => 'approved',
                'reject_submission' => 'rejected',
                'request_changes' => 'needs-changes',
                default => null,
            };
            if ($decision !== null) {
                if (!$submissionsReady || $submissionId < 1) {
                    throw new InvalidArgumentException('Choose a pending Resource submission.');
                }
                $notes = mb_substr(trim((string) ($_POST['review_notes'] ?? '')), 0, 4000);
                if ($reviewNotesReady && $decision !== 'approved' && $notes === '') {
                    throw new InvalidArgumentException('Add review notes explaining your decision.');
                }
                $reviewedSubmission = $db->prepare(
                    'SELECT submitted_by, target_resource_id, name FROM resource_submissions WHERE id = ?'
                );
                $reviewedSubmission->execute([$submissionId]);
                $submission = $reviewedSubmission->fetch(PDO::FETCH_ASSOC);
                $resourceId = llama_resource_review(
                    $db,
                    $actorUserId,
                    $submissionId,
                    $decision === 'approved' ? $_POST : null,
                    $notes,
                    $decision === 'needs-changes',
                    (string) ($_POST['review_form'] ?? '')
                );

                try {
                    admin_users_audit(
                        $db,
                        $actorUserId,
                        (int) $submission['submitted_by'],
                        'resource.submission_' . str_replace('-', '_', $decision),
                        'Reviewed Resource submission #' . $submissionId,
                        ['submission_id' => $submissionId, 'decision' => $decision, 'review_notes' => $notes]
                    );
                } catch (Throwable $exception) {
                    llama_log_caught_exception($exception, 'admin.resource_review_audit', ['submission_id' => $submissionId]);
                }

                try {
                    $published = $resourceId > 0 ? admin_resource_get($db, $resourceId) : [];
                    send_contribution_review_email(
                        $db,
                        (int) $submission['submitted_by'],
                        $decision,
                        $submission['target_resource_id'] ? 'Resource update' : 'new Resource submission',
                        $submissionId,
                        (string) $submission['name'],
                        $notes,
                        0,
                        'https://account.llamascout.com/contributions.php',
                        !empty($published['slug']) ? 'https://llamascout.com/resource.php?slug=' . rawurlencode($published['slug']) : ''
                    );
                } catch (Throwable $exception) {
                    llama_log_caught_exception($exception, 'email.resource_review', ['submission_id' => $submissionId]);
                }

                $queue = $submission['target_resource_id'] ? '/updates.php' : '/submissions.php';
                header('Location: ' . $queue . '?resource_review=' . $decision, true, 303);
                exit;
            }

            if ($action === 'save') {
                if ($resourceId < 1) {
                    throw new InvalidArgumentException('Use Add a Place to submit a new Resource.');
                }
                $resourceId =
                    admin_resource_save(
                        $db,
                        $actorUserId,
                        $resourceId,
                        $_POST
                    );

                $location = $returnPlaceId > 0
                    ? '/place.php?id=' . $returnPlaceId . '&mode=resource&resource_id=' . $resourceId . '&resource_saved=1'
                    : '/resources.php?id=' . $resourceId . '&saved=1';
                header('Location: ' . $location, true, 303);

                exit;
            }

            if ($action === 'deactivate') {
                admin_resource_deactivate(
                    $db,
                    $actorUserId,
                    $resourceId
                );

                header(
                    'Location: /resources.php?deactivated=1'
                );

                exit;
            }

            if ($action === 'upload_photos') {
                $added =
                    admin_resource_add_photos(
                        $db,
                        $actorUserId,
                        $resourceId,
                        $_FILES['resource_photos']
                        ?? []
                    );

                header(
                    'Location: /resources.php?id='
                    . $resourceId
                    . '&photos_added='
                    . $added
                );

                exit;
            }

            if ($action === 'delete_photo') {
                admin_resource_delete_photo(
                    $db,
                    $resourceId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                header(
                    'Location: /resources.php?id='
                    . $resourceId
                    . '&photo_deleted=1'
                );

                exit;
            }

            if ($action === 'make_hero') {
                admin_resource_make_hero(
                    $db,
                    $resourceId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                header(
                    'Location: /resources.php?id='
                    . $resourceId
                    . '&hero_updated=1'
                );

                exit;
            }

            throw new InvalidArgumentException(
                'Choose a valid Resource action.'
            );

        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.resources',
                    [
                        'resource_id' =>
                            $resourceId,
                    ],
                    [
                        InvalidArgumentException::class,
                        RuntimeException::class,
                    ]
                );

            $error =
                $reference === null
                    ? $exception->getMessage()
                    : llama_error_message_with_reference(
                        'The Resource could not be saved.',
                        $reference
                    );
        }
    }
}

if (
    $tablesReady
    && (string) ($_GET['saved'] ?? '') === '1'
) {
    $notice = 'Resource saved.';
}

if (
    $tablesReady
    && (string) ($_GET['deactivated'] ?? '') === '1'
) {
    $notice = 'Resource deactivated.';
}

if (
    $tablesReady
    && (int) ($_GET['photos_added'] ?? 0) > 0
) {
    $count = (int) $_GET['photos_added'];

    $notice =
        $count
        . ' Resource photo'
        . ($count === 1 ? '' : 's')
        . ' added.';
}

if (
    $tablesReady
    && (string) ($_GET['photo_deleted'] ?? '') === '1'
) {
    $notice = 'Resource photo deleted.';
}

if (
    $tablesReady
    && (string) ($_GET['hero_updated'] ?? '') === '1'
) {
    $notice = 'Resource hero photo updated.';
}

$resource =
    $tablesReady
    && $resourceId > 0
        ? admin_resource_get(
            $db,
            $resourceId
        )
        : [];

$resourceSubmission = null;
if ($submissionsReady && $submissionId > 0) {
    $lookup = $db->prepare("SELECT * FROM resource_submissions WHERE id = ? AND status IN ('pending', 'needs-changes')");
    $lookup->execute([$submissionId]);
    $resourceSubmission = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($resourceSubmission === null && $error === '') {
        $error = 'That Resource submission is no longer in the moderation queue.';
    }
}
$waitingForChanges = ($resourceSubmission['status'] ?? '') === 'needs-changes';

$submittedResource = [];
if ($resourceSubmission && (int) $resourceSubmission['target_resource_id'] > 0) {
    $submittedResource = admin_resource_get($db, (int) $resourceSubmission['target_resource_id']);
}
$editing =
    !empty($resource);

$showInventory = $tablesReady && !$editing && !$resourceSubmission;
if ($showInventory) {
    $search = trim((string) ($_GET['q'] ?? ''));
    $filterValue = trim((string) ($_GET['type'] ?? ''));
    $state = trim((string) ($_GET['state'] ?? ''));
    $inventoryName = 'Resource';
    $inventoryUrl = '/resources.php';
    $submissionsUrl = '/submissions.php?kind=resource';
    $searchPlaceholder = 'Name, address, city, state, or ID';
    $filterName = 'type';
    $filterLabel = 'Type';
    $filterPlaceholder = 'All types';
    $filterOptions = llama_resource_types();
    $states = admin_resources_states($db);
    $resources = admin_resources_list($db, $search, $filterValue, $state);
    $stockImage = $resources ? llama_contribution_photo_url($db) : '';
    $statuses = llama_resource_statuses();

    $inventoryRows = array_map(static fn (array $row): array => [
        'id' => $row['id'],
        'name' => (string) $row['name'],
        'status' => !empty($row['is_active']) ? ($statuses[$row['status']] ?? 'Unknown') : 'Inactive',
        'image_url' => trim((string) ($row['featured_image'] ?? '')) === ''
            ? $stockImage
            : llama_photo_public_url((string) $row['featured_image']),
        'location' => [$row['street_address'], $row['city'], $row['state_region']],
        'facts' => [
            ['icon' => 'photo', 'text' => number_format((int) $row['image_count'])],
            ['text' => llama_resource_type_label($row['resource_type'])],
            ['text' => 'Last checked: ' . (!empty($row['verified_at'])
                ? llama_format_viewer_datetime((string) $row['verified_at'])
                : 'Never')],
        ],
        'manage_url' => '/resources.php?id=' . (int) $row['id'],
        'view_url' => !empty($row['is_active']) && !empty($row['slug'])
            ? 'https://llamascout.com/resource.php?slug=' . rawurlencode($row['slug'])
            : '',
    ], $resources);
}

$postedReviewMatches = !$resourceSubmission
    || hash_equals(hash('sha256', $resourceSubmission['form_data']), (string) ($_POST['review_form'] ?? ''));
$form = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $error !== '' && $postedReviewMatches
    && in_array($_POST['resource_action'] ?? '', ['save', 'approve_submission'], true)
    ? $_POST
    : ($resourceSubmission
        ? array_replace($submittedResource, json_decode($resourceSubmission['form_data'], true, 512, JSON_THROW_ON_ERROR))
        : $resource);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle =
    $editing
        ? 'Edit Resource'
        : 'Resources';

$adminPageEyebrow =
    'Places';

$adminActiveNav =
    'resources';

require __DIR__ . '/_header.php';

$e =
    static fn (mixed $value): string =>
        moderation_e(
            (string) $value
        );

?>

<?php if ($notice !== ''): ?>
<div class="admin-user-notice is-success">
    <?= $e($notice) ?>
</div>
<?php endif; ?>

<?php if ($error !== ''): ?>
<div class="admin-user-notice is-error">
    <?= $e($error) ?>
</div>
<?php endif; ?>

<?php if (!$tablesReady): ?>

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Setup</p>
            <h2>Resource database migration required</h2>
        </div>
    </header>

    <div class="admin-user-action-box">
        <p>
            Run
            <code>tools/migrations/20261101_v3_003_resources.sql</code>
            against the Llama Scout database, then reload this page.
        </p>
    </div>
</section>

<?php else: ?>

<?php if ($editing || $resourceSubmission): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p><?= $resourceSubmission
                ? ($resourceSubmission['target_resource_id'] ? 'Resource Update' : 'New Resource Submission')
                : 'Resource Management' ?></p>

            <h2>
                <?= $resourceSubmission
                    ? 'Review ' . $e($resourceSubmission['name'])
                    : 'Edit ' . $e($resource['name'] ?? 'Resource') ?>
            </h2>
        </div>

        <?php if ($editing && !$resourceSubmission): ?>
            <a class="admin-button is-muted" href="/resources.php">Back to Resources</a>
        <?php endif; ?>
    </header>

    <?php if ($returnPlaceId > 0): ?>
        <p><a href="/place.php?id=<?= $returnPlaceId ?>&amp;mode=resource">Back to the Place editor</a></p>
    <?php endif; ?>
    <?php if ($resourceSubmission): ?>
        <p>
            Resource submission #<?= (int) $resourceSubmission['id'] ?> from Member #<?= (int) $resourceSubmission['submitted_by'] ?>
            · <a href="<?= $resourceSubmission['target_resource_id'] ? '/updates.php' : '/submissions.php' ?>">Back to moderation queue</a>
        </p>
    <?php endif; ?>
    <?php if ($waitingForChanges): ?>
        <div class="admin-user-action-box">
            <h3>Waiting for contributor changes</h3>
            <p><?= nl2br($e($resourceSubmission['review_notes'] ?? '')) ?></p>
        </div>
    <?php else: ?>
    <form
        class="admin-user-action-box resource-editor"
        method="post" data-resource-editor
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(moderation_csrf_token()) ?>"
        >

        <input type="hidden" name="resource_action" value="<?= $resourceSubmission ? 'approve_submission' : 'save' ?>">
        <input type="hidden" name="submission_id" value="<?= (int) ($resourceSubmission['id'] ?? 0) ?>">
        <?php if ($resourceSubmission): ?>
            <input type="hidden" name="review_form" value="<?= hash('sha256', $resourceSubmission['form_data']) ?>">
        <?php endif; ?>
        <input type="hidden" name="return_place_id" value="<?= $returnPlaceId ?>">

        <input
            type="hidden"
            name="resource_id"
            value="<?= (int) $resourceId ?>"
        >

        <?php
        $resourceStaffFields = true;
        require dirname(__DIR__) . '/partials/resources/fields.php';
        ?>

        <?php if ($resourceSubmission && $reviewNotesReady): ?>
            <label>
                <span>Review notes (optional for approval)</span>
                <textarea name="review_notes" rows="3" maxlength="4000"><?= $e($_POST['review_notes'] ?? '') ?></textarea>
            </label>
        <?php endif; ?>

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                <?= $resourceSubmission ? 'Approve and Publish' : 'Save Resource' ?>
            </button>
        </div>
    </form>

    <?php if ($resourceSubmission): ?>
        <form method="post" class="admin-user-action-box">
            <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
            <input type="hidden" name="submission_id" value="<?= (int) $resourceSubmission['id'] ?>">
            <input type="hidden" name="review_form" value="<?= hash('sha256', $resourceSubmission['form_data']) ?>">
            <?php if ($reviewNotesReady): ?>
                <label>
                    <span>Review notes</span>
                    <textarea name="review_notes" rows="3" maxlength="4000" required><?= $e($_POST['review_notes'] ?? '') ?></textarea>
                </label>
                <button class="admin-button is-muted" type="submit" name="resource_action" value="request_changes">Request Changes</button>
            <?php endif; ?>
            <button class="admin-button is-muted" type="submit" name="resource_action" value="reject_submission">Reject Submission</button>
        </form>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($editing && !empty($resource['is_active'])): ?>
    <form
        class="admin-user-action-box"
        method="post"
        onsubmit="return confirm('Deactivate this Resource?');"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(moderation_csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="resource_action"
            value="deactivate"
        >

        <input
            type="hidden"
            name="resource_id"
            value="<?= (int) $resourceId ?>"
        >

        <button
            class="admin-button is-muted"
            type="submit"
        >
            Deactivate Resource
        </button>
    </form>
    <?php endif; ?>

</section>
<?php endif; ?>

<?php if ($editing): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Resource page</p>
            <h2>Photos</h2>
        </div>

        <a
            class="admin-button is-muted"
            href="https://llamascout.com/resource.php?slug=<?= rawurlencode(
                (string) (
                    $resource['slug']
                    ?? ''
                )
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            View Resource
        </a>
    </header>

    <?php
    $resourcePhotos =
        llama_resource_images(
            $db,
            $resourceId
        );

    $resourcePhotoRemaining =
        max(
            0,
            3 - count($resourcePhotos)
        );
    ?>

    <?php if ($resourcePhotos): ?>
    <div class="admin-resource-photo-grid">
        <?php foreach ($resourcePhotos as $photo): ?>
        <article class="admin-resource-photo<?= !empty($photo['is_featured']) ? ' is-hero' : '' ?>">
            <img
                src="<?= $e(
                    llama_resource_image_url(
                        $photo['src']
                        ?? ''
                    )
                ) ?>"
                alt="<?= $e(
                    $photo['alt_text']
                    ?? $resource['name']
                    ?? 'Resource'
                ) ?>"
            >

            <div>
                <?php if (!empty($photo['is_featured'])): ?>
                    <strong>Hero photo</strong>
                <?php else: ?>
                    <form method="post">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $e(moderation_csrf_token()) ?>"
                        >
                        <input
                            type="hidden"
                            name="resource_action"
                            value="make_hero"
                        >
                        <input
                            type="hidden"
                            name="resource_id"
                            value="<?= (int) $resourceId ?>"
                        >
                        <input
                            type="hidden"
                            name="image_id"
                            value="<?= (int) $photo['id'] ?>"
                        >
                        <button
                            class="admin-button is-muted"
                            type="submit"
                        >
                            Make hero
                        </button>
                    </form>
                <?php endif; ?>

                <form
                    method="post"
                    onsubmit="return confirm('Delete this Resource photo?');"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= $e(moderation_csrf_token()) ?>"
                    >
                    <input
                        type="hidden"
                        name="resource_action"
                        value="delete_photo"
                    >
                    <input
                        type="hidden"
                        name="resource_id"
                        value="<?= (int) $resourceId ?>"
                    >
                    <input
                        type="hidden"
                        name="image_id"
                        value="<?= (int) $photo['id'] ?>"
                    >
                    <button
                        class="admin-button is-muted"
                        type="submit"
                    >
                        Delete
                    </button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($resourcePhotoRemaining > 0): ?>
    <form
        class="admin-user-action-box"
        method="post"
        enctype="multipart/form-data"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(moderation_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="resource_action"
            value="upload_photos"
        >
        <input
            type="hidden"
            name="resource_id"
            value="<?= (int) $resourceId ?>"
        >

        <label class="admin-resource-photo-upload">
            <span>
                Add up to
                <?= (int) $resourcePhotoRemaining ?>
                more photo<?= $resourcePhotoRemaining === 1 ? '' : 's' ?>
            </span>

            <input
                type="file"
                name="resource_photos[]"
                accept="image/*"
                multiple
                required
            >
        </label>

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                Upload Resource photos
            </button>
        </div>
    </form>
    <?php endif; ?>

    <div class="admin-user-action-box">
        <p>
            Resource galleries are limited to 3 photos. The first photo is the
            hero image used on the Resource page and map card.
        </p>
    </div>
</section>
<?php endif; ?>

<?php if ($showInventory): ?>
    <?php require __DIR__ . '/partials/place-inventory.php'; ?>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
