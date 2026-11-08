<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/resource-submissions.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$tablesReady = admin_resources_tables_ready($db);
$submissionsReady = llama_resource_submissions_ready($db);
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

            if (in_array($action, ['approve_submission', 'reject_submission'], true)) {
                if (!$submissionsReady || $submissionId < 1) {
                    throw new InvalidArgumentException('Choose a pending Resource submission.');
                }
                $resourceId = llama_resource_review(
                    $db,
                    $actorUserId,
                    $submissionId,
                    $action === 'approve_submission' ? $_POST : null
                );
                header('Location: /resources.php?' . ($resourceId > 0 ? 'id=' . $resourceId . '&saved=1' : 'rejected=1'), true, 303);
                exit;
            }

            if ($action === 'save') {
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

$resources =
    $tablesReady
        ? admin_resources_list($db)
        : [];

$resource =
    $tablesReady
    && $resourceId > 0
        ? admin_resource_get(
            $db,
            $resourceId
        )
        : [];

$resourceSubmission = null;
$pendingResourceSubmissions = [];
if ($submissionsReady) {
    $pendingResourceSubmissions = $db->query(
        "SELECT id, name, created_at FROM resource_submissions WHERE status = 'pending' ORDER BY id LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($submissionId > 0) {
        $lookup = $db->prepare("SELECT * FROM resource_submissions WHERE id = ? AND status = 'pending'");
        $lookup->execute([$submissionId]);
        $resourceSubmission = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($resourceSubmission === null && $error === '') {
            $error = 'That Resource submission is no longer pending.';
        }
    }
}
if (($_GET['rejected'] ?? '') === '1') {
    $notice = 'Resource submission rejected.';
}

$submittedResource = [];
if ($resourceSubmission && (int) $resourceSubmission['target_resource_id'] > 0) {
    $submittedResource = admin_resource_get($db, (int) $resourceSubmission['target_resource_id']);
}
$editing =
    !empty($resource);

$form = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $error !== ''
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
    'Map Data';

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

<?php if ($pendingResourceSubmissions): ?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div><p>Moderation</p><h2>Pending Resource Submissions</h2></div>
    </header>
    <ul>
        <?php foreach ($pendingResourceSubmissions as $pending): ?>
            <li><a href="/resources.php?submission=<?= (int) $pending['id'] ?>"><?= $e($pending['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
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

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p><?= $resourceSubmission ? 'Member contribution' : ($editing ? 'Standalone map entity' : 'New map entity') ?></p>

            <h2>
                <?= $resourceSubmission
                    ? 'Review ' . $e($resourceSubmission['name'])
                    : ($editing ? 'Edit ' . $e($resource['name'] ?? 'Resource') : 'Create Resource') ?>
            </h2>
        </div>

        <?php if ($editing): ?>
        <a
            class="admin-button is-muted"
            href="/resources.php"
        >
            New Resource
        </a>
        <?php endif; ?>
    </header>

    <?php if ($returnPlaceId > 0): ?>
        <p><a href="/place.php?id=<?= $returnPlaceId ?>&amp;mode=resource">Back to the Place editor</a></p>
    <?php endif; ?>
    <?php if ($resourceSubmission): ?>
        <p>Resource submission #<?= (int) $resourceSubmission['id'] ?> from Member #<?= (int) $resourceSubmission['submitted_by'] ?></p>
    <?php endif; ?>
    <form
        class="admin-user-action-box"
        method="post" data-resource-editor
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(moderation_csrf_token()) ?>"
        >

        <input type="hidden" name="resource_action" value="<?= $resourceSubmission ? 'approve_submission' : 'save' ?>">
        <input type="hidden" name="submission_id" value="<?= (int) ($resourceSubmission['id'] ?? 0) ?>">
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

        <div class="admin-user-form-actions">
            <button
                class="admin-button"
                type="submit"
            >
                <?= $resourceSubmission ? 'Approve and Publish' : ($editing ? 'Save Resource' : 'Create Resource') ?>
            </button>
        </div>
    </form>

    <?php if ($resourceSubmission): ?>
        <form method="post" class="admin-user-action-box">
            <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
            <input type="hidden" name="resource_action" value="reject_submission">
            <input type="hidden" name="submission_id" value="<?= (int) $resourceSubmission['id'] ?>">
            <button class="admin-button is-muted" type="submit">Reject Submission</button>
        </form>
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

<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>Standalone map data</p>
            <h2>Resources</h2>
        </div>

        <span><?= number_format(count($resources)) ?></span>
    </header>

    <?php if (!$resources): ?>
    <div class="admin-empty-state">
        <h3>No Resources yet.</h3>
        <p>
            Create the first standalone Resource above.
        </p>
    </div>
    <?php else: ?>

    <div class="admin-integration-table-wrap">
        <table class="admin-integration-table">
            <thead>
            <tr>
                <th>Resource</th>
                <th>Type</th>
                <th>Services</th>
                <th>Status</th>
                <th>GPS</th>
                <th>Manage</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($resources as $row): ?>
            <?php
            $serviceNames =
                array_values(
                    array_filter(
                        array_map(
                            static fn (array $service): string =>
                                trim(
                                    (string) (
                                        $service['service_type_label']
                                        ?? ''
                                    )
                                ),
                            (array) (
                                $row['services']
                                ?? []
                            )
                        )
                    )
                );
            ?>
            <tr class="<?= empty($row['is_active']) ? 'admin-resource-inactive' : '' ?>">
                <td data-label="Resource">
                    <strong><?= $e($row['name'] ?? 'Resource') ?></strong>
                </td>

                <td data-label="Type">
                    <?= $e(
                        $row['resource_type_label']
                        ?? $row['resource_type']
                        ?? ''
                    ) ?>
                </td>

                <td data-label="Services">
                    <?= $e(
                        $serviceNames
                            ? implode(', ', $serviceNames)
                            : 'None'
                    ) ?>
                </td>

                <td data-label="Status">
                    <span class="admin-status-pill">
                        <?= $e(
                            !empty($row['is_active'])
                                ? (
                                    llama_resource_statuses()[
                                        (string) (
                                            $row['status']
                                            ?? 'unknown'
                                        )
                                    ]
                                    ?? ucfirst(
                                        (string) (
                                            $row['status']
                                            ?? 'unknown'
                                        )
                                    )
                                )
                                : 'Inactive'
                        ) ?>
                    </span>
                </td>

                <td
                    data-label="GPS"
                    class="admin-resource-gps"
                >
                    <?php if (!empty($row['has_coordinates'])): ?>
                        <?= $e(
                            number_format(
                                (float) $row['latitude'],
                                6,
                                '.',
                                ''
                            )
                        ) ?>,
                        <?= $e(
                            number_format(
                                (float) $row['longitude'],
                                6,
                                '.',
                                ''
                            )
                        ) ?>
                    <?php else: ?>
                        Missing
                    <?php endif; ?>
                </td>

                <td data-label="Manage">
                    <a
                        class="admin-button"
                        href="/resources.php?id=<?= (int) $row['id'] ?>"
                    >
                        Edit
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</section>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
