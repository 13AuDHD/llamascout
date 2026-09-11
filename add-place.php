<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/place-drafts.php';
require_once __DIR__ . '/app/place-report.php';

require_verified_email();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$error = null;

$draftId = max(
    0,
    (int) (
        $_GET['draft']
        ?? $_POST['draft_id']
        ?? 0
    )
);

$draft =
    $draftId > 0
        ? llama_place_draft_for_user(
            db(),
            $userId,
            $draftId
        )
        : null;

if ($draftId > 0 && !$draft) {
    http_response_code(404);
    $error = 'That saved Place could not be found.';
}

$draftSaveToken =
    trim(
        (string) (
            $_POST['draft_save_token']
            ?? $draft['form_data']['draft_save_token']
            ?? ''
        )
    );

if (
    !preg_match(
        '/^[a-f0-9]{64}$/',
        $draftSaveToken
    )
) {
    $draftSaveToken =
        bin2hex(
            random_bytes(32)
        );
}

$editSubmissionId = max(
    0,
    (int) (
        $_GET['submission']
        ?? $_POST['submission_id']
        ?? 0
    )
);

$editSubmission =
    $editSubmissionId > 0
        ? community_new_place_submission_for_user(
            $userId,
            $editSubmissionId
        )
        : null;

$isNeedsChanges =
    $editSubmission
    && (string) ($editSubmission['status'] ?? '') === 'needs-changes';

if ($editSubmissionId > 0 && !$editSubmission) {
    http_response_code(404);
    $error = 'That Place submission could not be found.';
}

if ($editSubmission && !$isNeedsChanges) {
    header(
        'Location: https://account.llamascout.com/contributions.php',
        true,
        303
    );
    exit;
}

if (
    $isNeedsChanges
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    $_POST = array_merge(
        llama_place_report_form_input_from_data(
            (array) ($editSubmission['data'] ?? [])
        ),
        $_POST
    );
}

$existingSubmissionPhotos =
    $isNeedsChanges
    && is_array($editSubmission['data']['photos'] ?? null)
        ? $editSubmission['data']['photos']
        : [];

if (
    $draft
    && !$isNeedsChanges
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    $_POST = array_merge(
        (array) ($draft['form_data'] ?? []),
        $_POST
    );

    $restoredDraftPhotos =
        llama_place_draft_restore_photos(
            $userId,
            $draftId,
            (array) ($draft['photos'] ?? [])
        );

    $_POST['photo_stage_token'] =
        (string) (
            $restoredDraftPhotos['token']
            ?? ''
        );

    $_POST['photos_json'] =
        json_encode(
            (array) (
                $restoredDraftPhotos['photos']
                ?? []
            ),
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !community_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error =
            'Your session expired. Refresh the page and try again.';
    } else {
        if (
            isset($_POST['save_for_later'])
            && !$isNeedsChanges
        ) {
            try {
                $savedDraftId =
                    llama_place_draft_save(
                        db(),
                        $userId,
                        $draftId,
                        $_POST
                    );

                header(
                    'Location: https://account.llamascout.com/saved-later.php?saved='
                    . $savedDraftId,
                    true,
                    303
                );

                exit;

            } catch (Throwable $exception) {
                $reference =
                    llama_log_caught_exception(
                        $exception,
                        'place.draft.save',
                        [
                            'user_id' => $userId,
                            'draft_id' => $draftId,
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
                            'The Place could not be saved for later.',
                            $reference
                        );
            }
        }

        if ($error === null) {
            try {
                if ($isNeedsChanges) {
                    llama_place_report_resubmit_new_place(
                        $userId,
                        $editSubmissionId,
                        $_POST
                    );

                    header(
                        'Location: https://account.llamascout.com/contributions.php?submitted=new-resubmitted',
                        true,
                        303
                    );
                } else {
                    llama_place_report_submit_new_place(
                        $userId,
                        $_POST
                    );

                    if ($draftId > 0) {
                        llama_place_draft_delete(
                            db(),
                            $userId,
                            $draftId
                        );
                    }

                    header(
                        'Location: https://account.llamascout.com/contributions.php?submitted=new',
                        true,
                        303
                    );
                }

                exit;

            } catch (Throwable $exception) {
                $reference =
                    llama_log_caught_exception(
                        $exception,
                        'place.submit',
                        [
                            'user_id' => $userId,
                            'submission_id' => $editSubmissionId,
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
                            'The Place could not be submitted. Please try again.',
                            $reference
                        );
            }
        }
    }
}

$pageTitle =
    $isNeedsChanges
        ? 'Revise Place Submission | Llama Scout'
        : 'Add a Place | Llama Scout';

$pageStyles = [
    'site/features/place-report-form.css',
];

require __DIR__ . '/partials/header.php';

$placeReportValues = $_POST;
$placeReportMode = 'contributor';
$placeReportExistingPhotos = $existingSubmissionPhotos;
$placeReportShowLocate = true;
$placeReportShowNameSuggestion = true;
$placeReportPhotoCsrf = llama_photo_csrf_token();
$placeReportPhotoTitle = 'Photos of this Place';
$placeReportPhotoHelp =
    'Add up to 10 current photos. Signs, gates, washouts, road conditions, parking areas, and obstructions are especially useful. Location metadata is removed before permanent storage.';
?>

<section class="contribution-page add-place-page">

    <header class="contribution-header">
        <p class="eyebrow">
            <?= $isNeedsChanges
                ? 'Changes requested'
                : 'Community contribution' ?>
        </p>

        <h1>
            <?= $isNeedsChanges
                ? 'Revise Place Submission'
                : 'Add a Place' ?>
        </h1>

        <p>
            Share what you actually observed. Leave a question untouched when
            you did not assess it. Choose ? only when you deliberately checked
            but could not determine the answer.
        </p>

        <div class="add-place-form-note">
            <i
                class="fa-solid fa-circle-info"
                aria-hidden="true"
            ></i>

            <span>
                Nothing publishes automatically. A moderator reviews the full
                submission, photos, and location data before it becomes a Place.
            </span>
        </div>
    </header>

    <?php if ($isNeedsChanges): ?>
        <div
            class="contribution-message is-attention add-place-review-request"
        >
            <i
                class="fa-solid fa-triangle-exclamation"
                aria-hidden="true"
            ></i>

            <div>
                <strong>
                    Moderation requested changes before this Place can be published.
                </strong>

                <?php if (!empty($editSubmission['review_notes'])): ?>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                (string) $editSubmission['review_notes'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
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
            name="csrf_token"
            value="<?= htmlspecialchars(
                community_csrf_token(),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <?php if ($draftId > 0): ?>
            <input
                type="hidden"
                name="draft_id"
                value="<?= $draftId ?>"
            >
        <?php endif; ?>

        <input
            type="hidden"
            name="draft_save_token"
            value="<?= htmlspecialchars(
                $draftSaveToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <?php if ($isNeedsChanges): ?>
            <input
                type="hidden"
                name="submission_id"
                value="<?= $editSubmissionId ?>"
            >
        <?php endif; ?>

        <input
            type="hidden"
            name="photo_stage_token"
            value="<?= htmlspecialchars(
                (string) ($_POST['photo_stage_token'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="photos_json"
            value="<?= htmlspecialchars(
                (string) ($_POST['photos_json'] ?? '[]'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <?php
        require __DIR__
            . '/partials/place-report/form.php';
        ?>

        <div class="contribution-actions add-place-submit-bar">
            <button
                class="contribution-submit"
                type="submit"
                name="submit_for_review"
                value="1"
            >
                <i
                    class="fa-solid fa-paper-plane"
                    aria-hidden="true"
                ></i>

                <?= $isNeedsChanges
                    ? 'Resubmit for Review'
                    : 'Submit for Review' ?>
            </button>

            <?php if (!$isNeedsChanges): ?>
                <button
                    class="contribution-submit"
                    type="submit"
                    name="save_for_later"
                    value="1"
                    formnovalidate
                >
                    <i
                        class="fa-solid fa-floppy-disk"
                        aria-hidden="true"
                    ></i>

                    Save for Later
                </button>
            <?php endif; ?>

            <a href="/map.php">Cancel</a>
        </div>
    </form>
</section>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/add-place-location.js',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/add-place-name.js',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/add-place-draft.js',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/place-report-form.js',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
