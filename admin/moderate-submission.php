<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/app/bootstrap.php';

require_once dirname(__DIR__)
    . '/app/admin-users.php';

require_once dirname(__DIR__)
    . '/app/place-report.php';

require_once dirname(__DIR__)
    . '/app/points.php';

$adminUser =
    moderation_require_admin();

$db = db();

$csrfToken =
    moderation_csrf_token();

require_once __DIR__
    . '/_dashboard.php';

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

$submissionId =
    (int) (
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );

$adminPageTitle =
    'Review New Place';

$adminPageEyebrow =
    'Moderation';

$adminActiveNav =
    'submissions';

$adminPageActions =
    '<a class="admin-button" href="/edit-submission.php?id='
    . $submissionId
    . '">'
    . '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> '
    . 'Edit Submission'
    . '</a>';

$item =
    moderation_submission(
        $db,
        $submissionId
    );

$error = '';

if (!$item) {
    http_response_code(404);

    require __DIR__
        . '/_header.php';

    echo '<div class="admin-moderation-notice">Submission not found.</div>';

    require __DIR__
        . '/_footer.php';

    exit;
}

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

        $action =
            (string) (
                $_POST['action']
                ?? ''
            );

        $notes =
            trim(
                (string) (
                    $_POST['review_notes']
                    ?? ''
                )
            );

        $currentData =
            is_array(
                $item['data']
                ?? null
            )
                ? $item['data']
                : [];

        $pointInput =
            llama_place_report_scoring_input_from_data(
                $currentData
            );

        $pointEstimate =
            llama_points_estimate_new_place(
                $db,
                $pointInput,
                count(
                    is_array(
                        $currentData['photos']
                        ?? null
                    )
                        ? $currentData['photos']
                        : []
                )
            );

        $points =
            (int) (
                $pointEstimate[
                    'estimated_points'
                ]
                ?? 0
            );

        $db->beginTransaction();

        if ($action === 'approve') {
            $status =
                (string) (
                    $_POST['publish_status']
                    ?? 'active'
                );

            $placeId =
                moderation_approve_new_place(
                    $db,
                    $submissionId,
                    (int) $adminUser['id'],
                    $status,
                    $notes,
                    $points
                );

            /*
             * The published Place needs the explicit Unknown list
             * because normalized SQL columns use NULL for both an
             * untouched question and an explicit Unknown answer.
             */
            llama_place_report_publish_answer_state(
                $db,
                $placeId,
                $currentData
            );

            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                'place.submission_approved',
                'Approved new Place submission #'
                . $submissionId
                . '.',
                [
                    'submission_id' =>
                        $submissionId,
                    'place_id' =>
                        $placeId,
                    'publish_status' =>
                        $status,
                    'points_awarded' =>
                        $points,
                ]
            );

            $db->commit();

            header(
                'Location: /submissions.php?approved='
                . $placeId
            );

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
                        : 'Add review notes explaining why the submission was not approved.'
                );
            }

            moderation_set_submission_status(
                $db,
                $submissionId,
                (int) $adminUser['id'],
                $action,
                $notes
            );

            $auditAction =
                $action === 'needs-changes'
                    ? 'place.submission_changes_requested'
                    : 'place.submission_rejected';

            $auditSummary =
                $action === 'needs-changes'
                    ? 'Requested changes to new Place submission #'
                        . $submissionId
                        . '.'
                    : 'Rejected new Place submission #'
                        . $submissionId
                        . '.';

            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                $auditAction,
                $auditSummary,
                [
                    'submission_id' =>
                        $submissionId,
                    'review_notes' =>
                        $notes,
                ]
            );

            $db->commit();

            header(
                'Location: /submissions.php?updated=1'
            );

            exit;
        }

        throw new InvalidArgumentException(
            'Choose a moderation action.'
        );

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.moderate_submission',
                [
                    'submission_id' =>
                        $submissionId,
                    'action' =>
                        $action
                        ?? '',
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
                    'The moderation action could not be completed.',
                    $reference
                );
    }

    $item =
        moderation_submission(
            $db,
            $submissionId
        );
}

require __DIR__
    . '/_header.php';

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

$pointInput =
    llama_place_report_scoring_input_from_data(
        $data
    );

$newPlacePointEstimate =
    llama_points_estimate_new_place(
        $db,
        $pointInput,
        count($photos)
    );

$fields =
    llama_place_report_fields();

$answered = 0;

foreach (
    array_keys($fields)
    as $fieldKey
) {
    if (
        llama_place_report_answer_state(
            $data,
            $fieldKey
        )
        !== 'unanswered'
    ) {
        $answered++;
    }
}

$totalFields =
    count($fields);

$unknownCount =
    count(
        llama_place_report_unknown_fields(
            $data
        )
    );

$placeReportData =
    $data;

$placeReportReadMode =
    'moderation';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/scout-report-cards.css"
>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/features/place-report-form.css"
>

<?php if ($error !== ''): ?>
    <div class="admin-moderation-notice">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-moderation-detail">
    <h2>
        <?= moderation_e(
            $item['place_name']
        ) ?>
    </h2>

    <p>
        Submitted by

        <strong>
            <?= moderation_e(
                $item['display_name']
                ?: $item['username']
            ) ?>
        </strong>

        on

        <?= moderation_e(
            llama_format_viewer_datetime(
                (string) $item['submitted_at']
            )
        ) ?>.
    </p>
</div>

<section
    class="admin-moderation-detail admin-moderation-review-readiness"
>
    <header class="admin-moderation-section-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i
                    class="fa-solid fa-clipboard-check"
                    aria-hidden="true"
                ></i>

                Review Readiness
            </p>

            <h2>
                Place Report Completeness
            </h2>

            <p>
                Not provided means the question was untouched.
                Unknown means the contributor deliberately selected ?.
            </p>
        </div>
    </header>

    <div class="admin-moderation-readiness-grid">
        <div>
            <span>Questions answered</span>

            <strong>
                <?= number_format($answered) ?>
                /
                <?= number_format($totalFields) ?>
            </strong>
        </div>

        <div>
            <span>Explicit Unknown</span>

            <strong>
                <?= number_format(
                    $unknownCount
                ) ?>
            </strong>
        </div>

        <div>
            <span>Submitted photos</span>

            <strong>
                <?= number_format(
                    count($photos)
                ) ?>
            </strong>
        </div>
    </div>
</section>

<section class="scout-report">
    <header class="scout-report-header">
        <p class="admin-moderation-eyebrow">
            Shared Place Report
        </p>

        <h2>
            Questions and answers
        </h2>
    </header>

    <?php
    require dirname(__DIR__)
        . '/partials/place-report/read-only.php';
    ?>
</section>

<?php if ($photos): ?>
    <div class="admin-moderation-detail">
        <h2>Submitted Photos</h2>

        <div class="admin-moderation-photo-grid">
            <?php foreach ($photos as $photo): ?>
                <?php
                $src =
                    llama_place_report_photo_path(
                        $photo
                    );
                
                $photoUrl =
                    llama_place_report_photo_url(
                        $photo
                    );
                ?>

                <?php if ($src !== ''): ?>
                    <img
                        src="<?= moderation_e($photoUrl) ?>"
                        alt="<?= moderation_e(
                            is_array($photo)
                                ? ($photo['alt'] ?? '')
                                : ''
                        ) ?>"
                    >
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
require __DIR__
    . '/_moderation-new-place-points.php';
?>

<div class="admin-moderation-detail">
    <h2>Decision</h2>

    <form
        method="post"
        class="admin-moderation-form"
    >
        <input
            type="hidden"
            name="id"
            value="<?= $submissionId ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(
                $csrfToken
            ) ?>"
        >

        <label>
            Publish status

            <select name="publish_status">
                <option value="active">
                    Active
                </option>

                <option value="featured">
                    Featured
                </option>
            </select>
        </label>

        <label>
            Review notes

            <textarea
                name="review_notes"
                rows="5"
                placeholder="Required when not approving. Also useful for documenting anything you corrected or verified."
            ></textarea>
        </label>

        <div class="admin-moderation-actions">
            <button
                class="admin-moderation-button is-primary"
                type="submit"
                name="action"
                value="approve"
            >
                Approve and Publish
            </button>

            <button
                class="admin-moderation-button is-warning"
                type="submit"
                name="action"
                value="needs-changes"
            >
                Request Changes
            </button>

            <button
                class="admin-moderation-button is-danger"
                type="submit"
                name="action"
                value="rejected"
            >
                Not Approved
            </button>
        </div>
    </form>
</div>

<?php
require __DIR__
    . '/_footer.php';
?>
