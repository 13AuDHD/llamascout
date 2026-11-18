<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/place-drafts.php';
require_once __DIR__ . '/app/place-report.php';
require_once __DIR__ . '/app/area-submissions.php';

require_verified_email();

// A Site is submitted independently under an existing Place. Never send its
// answers through the new Place approval bundle.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && (string) ($_GET['tab'] ?? '') === 'site'
    && !isset($_GET['submission'])) {
    $parentId = max(0, (int) ($_GET['place_id'] ?? 0));
    header('Location: /add-site.php'
        . ($parentId > 0 ? '?site_place_id=' . $parentId : ''), true, 303);
    exit;
}

// Facility creation is now a standalone, one-at-a-time Resource-style form.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && (string) ($_GET['tab'] ?? '') === 'area') {
    $query = ['kind' => 'facility'];
    $parentId = (int) ($_GET['area_place_id'] ?? $_GET['place_id'] ?? 0);
    if ($parentId > 0) $query['parent_place_id'] = $parentId;
    if ((int) ($_GET['draft'] ?? 0) > 0) $query['return_draft'] = (int) $_GET['draft'];
    header('Location: /resource-contribution.php?' . http_build_query($query), true, 303);
    exit;
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$currentContributionLevel =
    llama_user_contribution_level(
        db(),
        $userId
    );

$currentContributionShortLabel =
    llama_contribution_level_short_label(
        $currentContributionLevel
    );

if (
    !llama_contributor_can(
        db(),
        $userId,
        'submit_place'
    )
) {
    http_response_code(403);
    exit('Your account is not eligible to submit new Places.');
}

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

// Keep older bundled Place + first Site submissions editable. New Place
// contributions use only Place-owned canonical fields and one report target.
$legacyPlaceBundle = $isNeedsChanges
    && empty($editSubmission['data']['_submission_target_kind']);

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
    $firstSiteCoordinates = $editSubmission['data']['_first_site_coordinates'] ?? null;
    if (is_array($firstSiteCoordinates)
        && isset($firstSiteCoordinates['latitude'], $firstSiteCoordinates['longitude'])) {
        $_POST['site_coordinates'] = $firstSiteCoordinates['latitude']
            . ', ' . $firstSiteCoordinates['longitude'];
    }
}

$placeReportExistingSitePhotos = $isNeedsChanges
    && is_array($editSubmission['data']['site_photos'] ?? null)
        ? $editSubmission['data']['site_photos']
        : [];

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

    $restoredSitePhotos = llama_place_draft_restore_photos(
        $userId,
        $draftId,
        (array) ($draft['form_data']['_draft_site_photos'] ?? []),
        'add-site',
        'site'
    );
    $_POST['site_photo_stage_token'] = (string) ($restoredSitePhotos['token'] ?? '');
    $_POST['site_photos_json'] = json_encode(
        (array) ($restoredSitePhotos['photos'] ?? []),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['recover_staged_photos'])
    && !$isNeedsChanges
) {
    if (
        !community_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $recoveredCount = llama_place_draft_recover_staged_photos(
                db(),
                $userId,
                $draftId,
                (string) ($_POST['recover_stage_token'] ?? '')
            );

            header(
                'Location: /add-place.php?draft='
                . $draftId
                . '&photos_recovered='
                . $recoveredCount,
                true,
                303
            );
            exit;
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'place.draft.photo_recovery',
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
                        'The staged photos could not be recovered.',
                        $reference
                    );
        }
    }
}

// Area submissions have their own form and review queue. Never feed one
// into the Place submission handler or its required Place/photo fields.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_area'])) {
    if (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload the Area form and try again.';
    } else {
        try {
            llama_area_submission_submit(db(), $userId, $_POST);
            header('Location: /add-place.php?tab=area&area_submitted=1'
                . '&area_place_id=' . (int) ($_POST['area_place_id'] ?? 0), true, 303);
            exit;
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception, 'area.submit',
                ['user_id' => $userId, 'place_id' => (int) ($_POST['area_place_id'] ?? 0)],
                [InvalidArgumentException::class, RuntimeException::class]
            );
            $error = $reference === null ? $exception->getMessage()
                : llama_error_message_with_reference('The Area could not be submitted.', $reference);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['submit_area'])) {
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

$recoverablePhotoStages = [];
if (
    $draft
    && !$isNeedsChanges
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
    && empty($draft['photos'])
) {
    $recoverablePhotoStages = llama_place_draft_recoverable_stages(
        $userId
    );
}

$areaPlaceChoices = llama_area_place_choices(
    db(),
    (string) ($_GET['area_q'] ?? ''),
    (int) ($_POST['area_place_id'] ?? $_GET['area_place_id'] ?? $_GET['place_id'] ?? 0)
);
// Direct links from the Resource page must open the requested panel.
$requestedContributionTab = (string) ($_GET['tab'] ?? 'place');
$areaInitialTab = isset($_POST['submit_area'])
    ? 'area'
    : (in_array($requestedContributionTab, ['place', 'site', 'area'], true)
        ? $requestedContributionTab : 'place');

$pageTitle =
    $isNeedsChanges
        ? 'Revise Place Submission | Llama Scout'
        : 'Add a Place | Llama Scout';

$pageStyles = [
    'site/features/place-report-form.css',
    'site/features/contribution-tabs.css',
    'site/features/resource-form.css',
];

require __DIR__ . '/partials/header.php';

$placeReportValues = $_POST;
$placeReportMode = 'contributor';
$placeReportOnlyScope = $legacyPlaceBundle ? '' : 'place';
$placeReportExistingPhotos = $existingSubmissionPhotos;
$placeReportShowLocate = true;
$placeReportShowNameSuggestion = true;
$placeReportShowFieldHelp = true;
$placeReportPhotoCsrf = llama_photo_csrf_token();
$placeReportPhotoTitle = 'Photos of this Place';
$placeReportPhotoHelp =
    'Add up to 10 current photos. Signs, gates, washouts, road conditions, parking areas, and obstructions are especially useful. Location metadata is removed before storage.';


?>

<section class="contribution-page add-place-page">

    <header class="contribution-header">
        <p class="eyebrow">
            <?= $isNeedsChanges
                ? 'Changes requested'
                : htmlspecialchars(
                    $currentContributionShortLabel . ' contribution',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
        </p>

        <h1>
            <?= $isNeedsChanges
                ? 'Revise Place Submission'
                : 'Add a Place' ?>
        </h1>

    </header>

    <?php if ($isNeedsChanges): ?>
        <div
            class="contribution-message is-attention add-place-review-request"
        >
            <i aria-hidden="true"><?= llama_icon('alert-triangle') ?></i>

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

    <?php if (isset($_GET['photos_recovered'])): ?>
        <div
            class="contribution-message is-success"
            role="status"
        >
            <?= max(0, (int) $_GET['photos_recovered']) ?> staged photos were recovered and attached to this saved Place.
        </div>
    <?php endif; ?>

    <?php if ($recoverablePhotoStages): ?>
        <div class="contribution-message" role="status">
            <strong>Staged photos found</strong>
            <p>
                This saved Place has no attached photos, but Llama Scout found
                a recent staged upload that was never connected to the draft.
            </p>

            <?php foreach ($recoverablePhotoStages as $batch): ?>
                <form method="post" class="add-place-photo-recovery-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            community_csrf_token(),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                    <input
                        type="hidden"
                        name="draft_id"
                        value="<?= $draftId ?>"
                    >
                    <input
                        type="hidden"
                        name="recover_stage_token"
                        value="<?= htmlspecialchars(
                            (string) ($batch['token'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                    <button
                        type="submit"
                        name="recover_staged_photos"
                        value="1"
                        class="contribution-submit"
                    >
                        Recover <?= (int) ($batch['count'] ?? 0) ?> staged photo<?= (int) ($batch['count'] ?? 0) === 1 ? '' : 's' ?>
                    </button>
                </form>
            <?php endforeach; ?>
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

    <?php if (isset($_GET['area_submitted'])): ?>
        <div class="contribution-success" role="status">
            Facility submitted for review. It will appear under its parent Place once approved.
            No map marker is required.
        </div>
    <?php endif; ?>
    <div class="contribution-tab-shell" data-contribution-tabs data-contribution-split
        data-contribution-initial="<?= htmlspecialchars($areaInitialTab, ENT_QUOTES, 'UTF-8') ?>">
        <?php require __DIR__ . '/partials/place-report/contribution-tabs.php'; ?>
        <?php // Facilities submit separately through resource-contribution.php. ?>
    <form
        id="add-place-main-form"
        method="post"
        class="contribution-form add-place-form place-report-form"
        data-place-report-completion-form data-contribution-report
    >
        <input type="hidden" name="submission_target_kind"
               value="<?= $legacyPlaceBundle ? 'legacy-bundle' : 'place' ?>">
        <?php if (!$legacyPlaceBundle): ?>
        <label class="contribution-field">
            <span class="place-report-field-label">Date visited *</span>
            <input type="date" name="visited_at" required
                   value="<?= htmlspecialchars((string) ($_POST['visited_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <?php endif; ?>
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

        <input type="hidden" name="site_photo_stage_token"
               value="<?= htmlspecialchars((string) ($_POST['site_photo_stage_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="site_photos_json"
               value="<?= htmlspecialchars((string) ($_POST['site_photos_json'] ?? '[]'), ENT_QUOTES, 'UTF-8') ?>">

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
                <i aria-hidden="true"><?= llama_icon('send') ?></i>

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
                    <i aria-hidden="true"><?= llama_icon('device-floppy') ?></i>

                    Continue Later
                </button>
            <?php endif; ?>

            <a href="/map.php">Cancel</a>
        </div>
    </form>
    </div>
</section>
<script src="<?= htmlspecialchars(
    $siteUrl . '/js/add-area-form.js?v=' . (int) filemtime(__DIR__ . '/js/add-area-form.js'),
    ENT_QUOTES, 'UTF-8'
) ?>" defer></script>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/add-place-location.js?v=' . (int) filemtime(__DIR__ . '/js/add-place-location.js'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<script
    src="<?= htmlspecialchars(
        $siteUrl . '/js/add-site-location.js?v=' . (int) filemtime(__DIR__ . '/js/add-site-location.js'),
        ENT_QUOTES, 'UTF-8'
    ) ?>"
    defer
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
        $siteUrl . '/js/place-report-form.js?v='
        . (is_file(__DIR__ . '/js/place-report-form.js')
            ? (int) filemtime(__DIR__ . '/js/place-report-form.js')
            : 1),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
