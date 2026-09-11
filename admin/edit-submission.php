<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/moderation-submission-editor.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

$submissionId =
    (int) (
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );

$item =
    moderation_submission(
        $db,
        $submissionId
    );

if (!$item) {
    http_response_code(404);

    $adminPageTitle =
        'Submission Not Found';

    $adminPageEyebrow =
        'Moderation';

    $adminActiveNav =
        'submissions';

    require __DIR__ . '/_header.php';

    echo '<div class="admin-user-notice is-error">Submission not found.</div>';

    require __DIR__ . '/_footer.php';
    exit;
}

if (
    !in_array(
        (string) ($item['status'] ?? ''),
        [
            'pending',
            'needs-changes',
        ],
        true
    )
) {
    header(
        'Location: /moderate-submission.php?id='
        . $submissionId,
        true,
        303
    );
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !moderation_verify_csrf(
                (string) (
                    $_POST['csrf_token']
                    ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $postedFields =
            is_array(
                $_POST['fields']
                ?? null
            )
                ? $_POST['fields']
                : [];

        $removePhotos =
            is_array(
                $_POST['remove_existing_photos']
                ?? null
            )
                ? $_POST['remove_existing_photos']
                : [];

        $photoToken =
            trim(
                (string) (
                    $_POST['photo_stage_token']
                    ?? ''
                )
            );

        $submittedPhotos =
            llama_photo_decode_form_photos(
                $_POST['photos_json']
                ?? '[]'
            );

        $db->beginTransaction();

        $result =
            moderation_save_submission_edits(
                $db,
                $submissionId,
                (int) $adminUser['id'],
                $postedFields,
                $removePhotos,
                $photoToken,
                $submittedPhotos
            );

        admin_users_audit(
            $db,
            (int) $adminUser['id'],
            (int) ($item['user_id'] ?? 0),
            'place.submission_edited',
            'Edited new Place submission #'
            . $submissionId
            . ' before moderation decision.',
            [
                'submission_id' =>
                    $submissionId,

                'status' =>
                    (string) ($item['status'] ?? ''),

                'field_changes' =>
                    $result['field_changes'],

                'removed_photos' =>
                    $result['removed_photos'],

                'added_photo_count' =>
                    $result['added_photo_count'],
            ]
        );

        $db->commit();

        foreach (
            $result['removed_photos']
            as $path
        ) {
            $absolute =
                dirname(__DIR__)
                . $path;

            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        header(
            'Location: /moderate-submission.php?id='
            . $submissionId
            . '&edited=1',
            true,
            303
        );
        exit;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.edit_submission',
                [
                    'submission_id' =>
                        $submissionId,
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
                    'The submission edits could not be saved.',
                    $reference
                );
    }

    $item =
        moderation_submission(
            $db,
            $submissionId
        );
}

$data =
    is_array(
        $item['data']
        ?? null
    )
        ? $item['data']
        : [];

$flat =
    moderation_submission_editor_flatten(
        $data
    );

$groups = [];

foreach ($flat as $path => $value) {
    $groups[
        moderation_submission_editor_group(
            $path
        )
    ][$path] = $value;
}

$photos =
    is_array(
        $data['photos']
        ?? null
    )
        ? $data['photos']
        : [];

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],

    'updates' =>
        $stats['updates'],

    'reports' =>
        $stats['reports'],

    'orders' =>
        $stats['orders'],

    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    'Edit New Place Submission';

$adminPageEyebrow =
    'Moderation';

$adminActiveNav =
    'submissions';

$adminNeedsPhotoUploader =
    true;

$adminPageActions =
    '<a class="admin-button" href="/moderate-submission.php?id='
    . $submissionId
    . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Review</a>';

$csrfToken =
    moderation_csrf_token();

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/edit-submission.css"
>

<?php
function moderator_editor_value(
    mixed $value
): string {
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value
            ? '1'
            : '0';
    }

    return (string) $value;
}

function moderator_editor_control(
    string $path,
    mixed $value
): void {
    $name =
        'fields['
        . $path
        . ']';

    $escapedName =
        moderation_e(
            $name
        );

    $escapedValue =
        moderation_e(
            moderator_editor_value(
                $value
            )
        );

    if (
        in_array(
            $path,
            moderation_submission_editor_bool_paths(),
            true
        )
    ) {
        ?>
        <select name="<?= $escapedName ?>">
            <option value="" <?= $value === null ? 'selected' : '' ?>>Unknown</option>
            <option value="1" <?= $value === true ? 'selected' : '' ?>>Yes</option>
            <option value="0" <?= $value === false ? 'selected' : '' ?>>No</option>
        </select>
        <?php
        return;
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_rating_paths(),
            true
        )
    ) {
        ?>
        <select name="<?= $escapedName ?>">
            <option value="" <?= $value === null ? 'selected' : '' ?>>Unknown</option>

            <?php for ($i = 1; $i <= 5; $i++): ?>
                <option
                    value="<?= $i ?>"
                    <?= (int) $value === $i ? 'selected' : '' ?>
                >
                    <?= $i ?>/5
                </option>
            <?php endfor; ?>
        </select>
        <?php
        return;
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_long_text_paths(),
            true
        )
    ) {
        ?>
        <textarea
            name="<?= $escapedName ?>"
            rows="4"
        ><?= $escapedValue ?></textarea>
        <?php
        return;
    }

    $inputType =
        in_array(
            $path,
            array_merge(
                moderation_submission_editor_integer_paths(),
                moderation_submission_editor_float_paths()
            ),
            true
        )
            ? 'number'
            : (
                $path === 'visited_at'
                    ? 'date'
                    : 'text'
            );

    $step =
        in_array(
            $path,
            moderation_submission_editor_float_paths(),
            true
        )
            ? 'any'
            : '1';
    ?>

    <input
        type="<?= moderation_e($inputType) ?>"
        name="<?= $escapedName ?>"
        value="<?= $escapedValue ?>"
        <?= $inputType === 'number'
            ? 'step="' . moderation_e($step) . '"'
            : '' ?>
    >

    <?php
}
?>

<?php if ($error !== ''): ?>
    <div
        class="admin-user-notice is-error"
        role="alert"
    >
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<section class="admin-panel moderator-submission-editor-intro">
    <div>
        <span class="admin-status-pill">
            <?= moderation_e(
                moderation_status_label(
                    (string) $item['status']
                )
            ) ?>
        </span>

        <h2>
            <?= moderation_e(
                (string) (
                    $data['name']
                    ?? $item['place_name']
                    ?? 'New Place'
                )
            ) ?>
        </h2>

        <p>
            Edit the submitted information before making a moderation decision.
            Saving here does not approve, reject, or send the submission back.
        </p>
    </div>
</section>

<form
    method="post"
    class="moderator-submission-editor-form"
>
    <input
        type="hidden"
        name="id"
        value="<?= $submissionId ?>"
    >

    <input
        type="hidden"
        name="csrf_token"
        value="<?= moderation_e($csrfToken) ?>"
    >

    <input
        type="hidden"
        name="photo_stage_token"
        value=""
    >

    <input
        type="hidden"
        name="photos_json"
        value="[]"
    >

    <?php foreach ($groups as $groupName => $fields): ?>
        <section class="admin-panel moderator-submission-editor-section">
            <header>
                <h2>
                    <?= moderation_e($groupName) ?>
                </h2>
            </header>

            <div class="moderator-submission-editor-grid">
                <?php foreach ($fields as $path => $value): ?>
                    <label
                        class="<?= in_array(
                            $path,
                            moderation_submission_editor_long_text_paths(),
                            true
                        )
                            ? 'is-wide'
                            : '' ?>"
                    >
                        <span>
                            <?= moderation_e(
                                moderation_submission_editor_label(
                                    $path
                                )
                            ) ?>
                        </span>

                        <small>
                            <?= moderation_e($path) ?>
                        </small>

                        <?php
                        moderator_editor_control(
                            $path,
                            $value
                        );
                        ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="admin-panel moderator-submission-editor-section">
        <header>
            <h2>Submitted Photos</h2>
            <p>
                Removing a submitted photo does not automatically request changes.
                Save the edit, then use Request Changes on the review page if a replacement is needed.
            </p>
        </header>

        <?php if ($photos): ?>
            <div class="moderator-submission-photo-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php
                    $src =
                        moderation_photo_path(
                            $photo
                        );
                    ?>

                    <?php if ($src !== ''): ?>
                        <label class="moderator-submission-photo">
                            <img
                                src="https://llamascout.com<?= moderation_e($src) ?>"
                                alt="<?= moderation_e(
                                    (string) (
                                        $photo['alt']
                                        ?? ''
                                    )
                                ) ?>"
                            >

                            <span>
                                <input
                                    type="checkbox"
                                    name="remove_existing_photos[]"
                                    value="<?= moderation_e($src) ?>"
                                >
                                Remove this photo
                            </span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-empty-state">
                <i class="fa-regular fa-image" aria-hidden="true"></i>
                <h3>No submitted photos</h3>
            </div>
        <?php endif; ?>

        <div
            data-photo-uploader
            data-photo-context="add-place"
            data-photo-max="10"
            data-photo-csrf="<?= moderation_e(
                llama_photo_csrf_token()
            ) ?>"
            data-photo-endpoint="/photo-upload.php"
            data-photo-title="Add moderator photos"
            data-photo-help="Add replacement or supporting photos before the moderation decision."
        ></div>
    </section>

    <div class="moderator-submission-editor-actions">
        <button
            class="admin-button"
            type="submit"
        >
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            Save Submission Changes
        </button>

        <a
            class="admin-button is-muted"
            href="/moderate-submission.php?id=<?= $submissionId ?>"
        >
            Cancel
        </a>
    </div>
</form>

<script src="https://llamascout.com/js/photo-uploader.js"></script>

<?php require __DIR__ . '/_footer.php'; ?>
