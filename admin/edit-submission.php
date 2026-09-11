<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/moderation-submission-editor.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

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

    require __DIR__
        . '/_header.php';

    echo '<div class="admin-user-notice is-error">Submission not found.</div>';

    require __DIR__
        . '/_footer.php';

    exit;
}

if (
    !in_array(
        (string) (
            $item['status']
            ?? ''
        ),
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

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {
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
                $_POST,
                $removePhotos,
                $photoToken,
                $submittedPhotos
            );

        admin_users_audit(
            $db,
            (int) $adminUser['id'],
            (int) (
                $item['user_id']
                ?? 0
            ),
            'place.submission_edited',
            'Edited new Place submission #'
            . $submissionId
            . ' before moderation decision.',
            [
                'submission_id' =>
                    $submissionId,
                'status' =>
                    (string) (
                        $item['status']
                        ?? ''
                    ),
                'field_changes' =>
                    $result['field_changes'],
                'removed_photos' =>
                    $result['removed_photos'],
                'added_photo_count' =>
                    $result['added_photo_count'],
            ]
        );

        $db->commit();

        /*
         * Only delete permanent files after the database commit
         * succeeds. If DB work fails, the original submission
         * remains intact.
         */
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
    . '">'
    . '<i class="fa-solid fa-arrow-left" aria-hidden="true"></i> '
    . 'Back to Review'
    . '</a>';

$csrfToken =
    moderation_csrf_token();

require __DIR__
    . '/_header.php';

$placeReportValues =
    llama_place_report_form_input_from_data(
        $data
    );

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {
    $placeReportValues =
        array_merge(
            $placeReportValues,
            $_POST
        );
}

$placeReportMode =
    'moderator';

$placeReportExistingPhotos =
    $photos;

$placeReportShowLocate =
    false;

$placeReportShowNameSuggestion =
    false;

$placeReportPhotoCsrf =
    llama_photo_csrf_token();

$placeReportPhotoEndpoint =
    '/photo-upload.php';

$placeReportPhotoTitle =
    'Add moderator photos';

$placeReportPhotoHelp =
    'Add replacement or supporting photos before the moderation decision. Location metadata is removed before storage.';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/pages/add-place.css"
>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/features/place-report-form.css"
>

<section class="contribution-page add-place-page">

    <header class="contribution-header">
        <p class="eyebrow">
            Moderator edit
        </p>

        <h1>
            <?= htmlspecialchars(
                (string) (
                    $data['name']
                    ?? $item['place_name']
                    ?? 'New Place'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </h1>

        <p>
            This is the same Place Report used by contributors.
            Saving changes here does not approve, reject, or
            return the submission.
        </p>

        <div class="add-place-form-note">
            <i
                class="fa-solid fa-shield-halved"
                aria-hidden="true"
            ></i>

            <span>
                Status remains
                <strong>
                    <?= htmlspecialchars(
                        moderation_status_label(
                            (string) $item['status']
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>.
                All moderator edits are recorded in the audit log.
            </span>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div
            class="contribution-message is-error"
            role="alert"
        >
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        class="contribution-form add-place-form place-report-form"
    >
        <input
            type="hidden"
            name="id"
            value="<?= $submissionId ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="photo_stage_token"
            value="<?= htmlspecialchars(
                (string) (
                    $_POST['photo_stage_token']
                    ?? ''
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="photos_json"
            value="<?= htmlspecialchars(
                (string) (
                    $_POST['photos_json']
                    ?? '[]'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <?php
        require dirname(__DIR__)
            . '/partials/place-report/form.php';
        ?>

        <div
            class="contribution-actions add-place-submit-bar"
        >
            <button
                class="contribution-submit"
                type="submit"
            >
                <i
                    class="fa-solid fa-floppy-disk"
                    aria-hidden="true"
                ></i>

                Save Submission Changes
            </button>

            <a
                href="/moderate-submission.php?id=<?= $submissionId ?>"
            >
                Cancel
            </a>
        </div>
    </form>
</section>

<script
    src="https://llamascout.com/js/photo-uploader.js"
></script>

<script
    src="https://llamascout.com/js/place-report-form.js"
></script>

<?php
require __DIR__
    . '/_footer.php';
?>
