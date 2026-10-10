<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-update.php';

require_verified_email();


$user =
    current_user();


$userId =
    (int) (
        $user['id']
        ?? 0
    );


$currentContributionLevel =
    llama_user_contribution_level(
        db(),
        $userId
    );


$currentContributionShortLabel =
    llama_contribution_level_short_label(
        $currentContributionLevel
    );

$currentContributionLabel =
    llama_contribution_level_label(
        $currentContributionLevel
    );


if (
    !llama_contributor_can(
        db(),
        $userId,
        'submit_update'
    )
) {
    http_response_code(403);
    exit('Your account is not eligible to submit Place updates.');
}


$slug =
    trim(
        (string) (
            $_GET['slug']
            ?? $_POST['slug']
            ?? ''
        )
    );


$place =
    $slug !== ''
        ? community_find_place_for_update(
            $slug
        )
        : null;


/*
 * =========================================================
 * PLACE NOT FOUND
 * =========================================================
 */

if (!$place) {
    http_response_code(404);

    $pageTitle =
        'Place not found | Llama Scout';


    require dirname(__DIR__)
        . '/partials/header.php';
    ?>

    <section class="contribution-page place-update-page">

        <header class="contribution-header">

            <h1>
                Place not found
            </h1>

            <p>
                This Place is not available for updates.
            </p>

        </header>

    </section>

    <?php

    require dirname(__DIR__)
        . '/partials/footer.php';

    exit;
}


$db =
    db();


$placeId =
    (int) (
        $place['id']
        ?? 0
    );


/*
 * =========================================================
 * COMPLETE ACCESS REQUIRED
 * =========================================================
 *
 * The update form is pre-filled with the complete published
 * Place report, including information that is normally hidden
 * behind Complete Access.
 *
 * Never render that form unless this user is entitled to see
 * the complete data for this specific Place.
 *
 * Free contributors retain access to Places they originally
 * contributed through user_has_place_complete_access().
 */
if (
    !user_has_place_complete_access(
        $placeId,
        $userId
    )
) {
    http_response_code(403);

    $pageTitle =
        'Complete Access Required | Llama Scout';

    require dirname(__DIR__)
        . '/partials/header.php';
    ?>

    <section class="contribution-page place-update-page">

        <header class="contribution-header">

            <p class="eyebrow">
                Place updates
            </p>

            <h1>
                Complete Access required
            </h1>

            <p>
                You do not have access to the complete information
                for this Place, so its update form cannot be opened.
            </p>

            <p>
                <a
                    class="contribution-submit"
                    href="<?= htmlspecialchars(
                        'https://llamascout.com/place.php?slug='
                        . rawurlencode($slug),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    Return to Place
                </a>
            </p>

        </header>

    </section>

    <?php

    require dirname(__DIR__)
        . '/partials/footer.php';

    exit;
}


$error =
    null;

/*
 * =========================================================
 * EXISTING OPEN UPDATE
 * =========================================================
 */

$openUpdate =
    community_open_update_for_user(
        $userId,
        $placeId
    );


$isNeedsChanges =
    $openUpdate
    && (
        (string) (
            $openUpdate['status']
            ?? ''
        )
        === 'needs-changes'
    );


$isPendingUpdate =
    $openUpdate
    && (
        (string) (
            $openUpdate['status']
            ?? ''
        )
        === 'pending'
    );


/*
 * =========================================================
 * EXISTING UPDATE DATA
 * =========================================================
 */

$editProposed =
    [];


$editPhotos =
    [];


$editVisitedAt =
    '';


$editContributorNotes =
    '';


$editProposedUnknownFields =
    [];


if ($isNeedsChanges) {

    $editProposed =
        llama_place_update_decode_json(
            $openUpdate['proposed_changes']
            ?? '{}'
        );


    $editPhotos =
        llama_place_update_decode_json(
            $openUpdate['photos']
            ?? '[]'
        );


    $editVisitedAt =
        !empty(
            $openUpdate['visited_at']
        )
            ? substr(
                (string) $openUpdate['visited_at'],
                0,
                10
            )
            : '';


    $editContributorNotes =
        (string) (
            $openUpdate['contributor_notes']
            ?? ''
        );


    /*
     * The proposed value itself can be NULL for both:
     *
     * 1. explicitly Unknown
     * 2. blank / not provided
     *
     * Revision history preserves which one the contributor
     * actually submitted.
     */
    $editHistoryRow =
        llama_place_update_fetch_row(
            $db,
            (int) $openUpdate['id']
        );


    if ($editHistoryRow) {
        $editProposedUnknownFields =
            llama_place_update_latest_unknown_fields(
                $editHistoryRow
            );
    }
}


/*
 * Only the Entire Place submission path has moderation and point handling.
 * Until Area and Site approval is connected, never silently save a scoped
 * answer as a Place-wide change.
 */
$reportTargetScope = trim((string) ($_POST['report_target_scope'] ?? 'place'));
$reportTargetSource = trim((string) ($_POST['report_target_source'] ?? 'place'));
$reportTargetId = (int) ($_POST['report_target_id'] ?? $placeId);
if ($reportTargetScope !== 'place'
    || $reportTargetSource !== 'place'
    || $reportTargetId !== $placeId) {
    $reportTargetIsScoped = true;
} else {
    $reportTargetIsScoped = false;
}

/*
 * =========================================================
 * SUBMIT
 * =========================================================
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$isPendingUpdate
) {

    if (
        !community_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session expired. Refresh the page and try again.';

    } else {

        try {
            if ($reportTargetIsScoped) {
                if ($isNeedsChanges) {
                    throw new InvalidArgumentException('Area and Site revisions requested by a moderator are not yet supported.');
                }
                require_once dirname(__DIR__) . '/app/place-report/scoped-update-review.php';
                llama_scoped_update_submit($userId, $place, $_POST);
                header('Location: https://account.llamascout.com/contributions.php?submitted=update', true, 303);
                exit;
            }

            if ($isNeedsChanges) {

                llama_place_update_resubmit(
                    $userId,
                    $place,
                    (int) $openUpdate['id'],
                    $_POST
                );


                header(
                    'Location: https://account.llamascout.com/contributions.php?submitted=update-resubmitted',
                    true,
                    303
                );


                exit;
            }


            llama_place_update_submit(
                $userId,
                $place,
                $_POST
            );


            header(
                'Location: https://account.llamascout.com/contributions.php?submitted=update',
                true,
                303
            );


            exit;

        } catch (Throwable $exception) {

            $reference =
                llama_log_caught_exception(
                    $exception,
                    'account.place_update_submit',
                    [
                        'place_id' =>
                            $placeId,

                        'user_id' =>
                            $userId,
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
                        'The update could not be submitted. Please try again.',
                        $reference
                    );
        }
    }
}


/*
 * =========================================================
 * CURRENT PUBLISHED PLACE
 * =========================================================
 */

$currentValues =
    llama_place_update_current_values(
        $db,
        $placeId
    );


$publishedUnknownFields =
    llama_place_report_published_answer_state(
        $db,
        $placeId
    );


/*
 * =========================================================
 * SHARED FORM VALUES
 * =========================================================
 *
 * Normal update:
 *     Published Place values and answer state.
 *
 * Needs Changes:
 *     Published Place values, overlaid with the contributor's
 *     latest proposed values and proposed Unknown state.
 */

$placeReportValues =
    llama_place_update_shared_form_values(
        $currentValues,
        $publishedUnknownFields,
        $isNeedsChanges
            ? $editProposed
            : [],
        $isNeedsChanges
            ? $editProposedUnknownFields
            : []
    );


/*
 * =========================================================
 * PRESERVE FAILED POST
 * =========================================================
 *
 * The posted form must win over database values after an
 * unsuccessful submission.
 *
 * Checkbox inputs require special handling because unchecked
 * boxes do not appear in $_POST.
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $error !== null
) {

    foreach (
        llama_place_update_definitions()
        as $definition
    ) {
        $field =
            $definition['shared']
            ?? null;


        if (!is_array($field)) {
            continue;
        }


        $fieldKey =
            (string) (
                $field['key']
                ?? ''
            );


        if ($fieldKey === '') {
            continue;
        }


        $fieldType =
            (string) (
                $field['type']
                ?? ''
            );


        if ($fieldType === 'checkbox') {

            if (
                !empty(
                    $_POST[$fieldKey]
                )
            ) {
                $placeReportValues[$fieldKey] =
                    '1';

            } else {
                /*
                 * No array value means the user intentionally
                 * submitted this checkbox unchecked.
                 */
                unset(
                    $placeReportValues[$fieldKey]
                );
            }


            continue;
        }


        if (
            array_key_exists(
                $fieldKey,
                $_POST
            )
        ) {
            $postedValue =
                $_POST[$fieldKey];


            if (
                is_scalar(
                    $postedValue
                )
                || $postedValue === null
            ) {
                $placeReportValues[$fieldKey] =
                    (string) $postedValue;
            }
        }
    }
}


/*
 * =========================================================
 * SHARED FORM FIELD ALLOW-LIST
 * =========================================================
 */

$placeReportAllowedStoragePaths =
    array_keys(
        llama_place_update_definitions()
    );


/*
 * =========================================================
 * SHARED PLACE REPORT CONFIG
 * =========================================================
 */

$placeReportMode =
    'contributor';


$placeReportShowLocate =
    true;


$placeReportShowNameSuggestion =
    false;


/*
 * Updates use their own evidence-photo section below.
 */
$placeReportShowPhotos =
    false;


$placeReportExistingPhotos =
    [];


$placeReportPhotoContext =
    'update-place';


$placeReportPhotoMax =
    5;


$placeReportPhotoCsrf =
    llama_photo_csrf_token();


/*
 * =========================================================
 * UPDATE-SPECIFIC VALUES
 * =========================================================
 */

$visitedAtValue =
    (string) (
        $_POST['visited_at']
        ?? $editVisitedAt
    );


$contributorNotesValue =
    (string) (
        $_POST['contributor_notes']
        ?? $editContributorNotes
    );


/*
 * =========================================================
 * PAGE
 * =========================================================
 */

$pageTitle =
    $isNeedsChanges
        ? 'Revise Update | Llama Scout'
        : 'Suggest an Update | Llama Scout';


$pageStyles = [
    'account/pages/update-place.css',
    'site/features/place-report-form.css',
];


require dirname(__DIR__)
    . '/partials/header.php';


$e =
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

?>


<section class="contribution-page place-update-page">


    <header class="contribution-header place-update-header">


        <p class="eyebrow">

            <?= $isNeedsChanges
                ? 'Changes requested'
                : $e(
                    $currentContributionShortLabel
                    . ' contribution'
                ) ?>

        </p>


        <h1>

            <?= $isNeedsChanges
                ? 'Revise this update'
                : 'Suggest an update' ?>

        </h1>


        <p class="place-update-place-name">

            <?= $e(
                $place['name']
                ?? ''
            ) ?>

        </p>


        <p class="place-update-intro">

            Update anything that has changed.
            The current Place information is already filled in.
            Edit only what needs correcting or updating.
            Llama Scout will automatically detect what changed
            and send those changes for review.

        </p>


        <div class="add-place-form-note">

            <i
                aria-hidden="true"
            ><?= llama_icon('info-circle') ?></i>

            <span>
                If approved, this field work is recorded as
                <strong><?= $e($currentContributionLabel) ?></strong>.
                Your edits do not change the live Place immediately. A moderator
                reviews the differences first, and only approved changes are published.
            </span>

        </div>


    </header>


    <?php if ($isPendingUpdate): ?>


        <div class="contribution-message">

            <i
                aria-hidden="true"
            ><?= llama_icon('clock') ?></i>


            <div>

                <strong>
                    This update is already in review.
                </strong>

                <span>
                    You can track its status from My Contributions.
                </span>

            </div>

        </div>


        <p>

            <a
                class="contribution-submit"
                href="/contributions.php"
            >
                View my contributions
            </a>

        </p>


    <?php else: ?>


        <?php if ($isNeedsChanges): ?>


            <section class="place-update-request-card">


                <div class="place-update-request-heading">

                    <i
                        aria-hidden="true"
                    ><?= llama_icon('alert-triangle') ?></i>


                    <div>

                        <span>
                            Moderator request
                        </span>

                        <strong>
                            Changes are needed before this update can be approved.
                        </strong>

                    </div>

                </div>


                <?php if (
                    !empty(
                        $openUpdate['review_notes']
                    )
                ): ?>

                    <p>
                        <?= nl2br(
                            $e(
                                $openUpdate['review_notes']
                            )
                        ) ?>
                    </p>

                <?php endif; ?>


            </section>


        <?php endif; ?>


        <?php if ($error): ?>


            <div
                class="contribution-message is-error"
                role="alert"
            >
                <?= $e(
                    $error
                ) ?>
            </div>


        <?php endif; ?>


        <form
            method="post"
            class="contribution-form add-place-form place-report-form place-update-form"
        >


            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    community_csrf_token()
                ) ?>"
            >


            <input
                type="hidden"
              name="slug"
                value="<?= $e(
                    $slug
                ) ?>"
            >


            <input
                type="hidden"
                name="photo_stage_token"
                value="<?= $e(
                    $_POST['photo_stage_token']
                    ?? ''
                ) ?>"
            >


            <input
                type="hidden"
                name="photos_json"
                value="<?= $e(
                    $_POST['photos_json']
                    ?? '[]'
                ) ?>"
            >


            <?php

            /*
             * Shared Place Report form.
             *
             * This is the same field definition and rendering
             * system used by the rest of the Place workflow.
             */
            /* Reporting target is displayed inside the established update form. */
            require_once dirname(__DIR__) . '/app/place-report/report-targets.php';
            $availableReportTargets = llama_report_targets(db(), $placeId);
            ?>
            <section class="contribution-section place-update-report-target">
                <h3>What are you reporting?</h3>
                <p>Choose the entire Place or find a camping Area or Site.</p>
                <label for="place-update-report-target">Reporting target</label>
                <input
                    id="place-update-report-target"
                    type="search"
                    list="place-update-report-target-options"
                    value="<?= $e('Entire Place (#' . $placeId . ', place)') ?>"
                    autocomplete="off"
                    data-report-target-search
                >
                <datalist id="place-update-report-target-options">
                    <?php foreach ($availableReportTargets as $target): ?>
                        <option value="<?= $e((string) $target['label'] . ' (#' . $target['id'] . ', ' . $target['scope'] . ')') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="report_target_scope" value="place" data-report-target-scope>
                <input type="hidden" name="report_target_source" value="place" data-report-target-source>
                <input type="hidden" name="report_target_id" value="<?= (int) $placeId ?>" data-report-target-id>
                <p class="place-update-report-target-status" data-report-target-status role="status">
                    Entire Place updates use the existing review process.
                </p>
            </section>
            <script type="application/json" id="place-update-report-target-data"><?= json_encode(
                $availableReportTargets,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
            ) ?></script>
            <script defer src="/js/place-update-report-target.js"></script>
            ?>
            <div data-place-update-place-fields>
            <?php require dirname(__DIR__) . '/partials/place-report/form.php'; ?>
            </div>
            <section class="contribution-section" data-place-update-scoped-fields hidden>
                <h3>Area or Site Scout Report</h3>
                <p>Answer the questions that apply to the selected Area or Site. Only answers you enter here will be proposed for review. Other areas and the parent Place will not be changed.</p>
                <div data-scoped-report-questions></div>
                <input type="hidden" name="scoped_answers_json" value="" data-scoped-answers-json>
                <p data-scoped-field-notice role="status"></p>
            </section>
            <script type="application/json" id="place-update-scoped-fields-data"><?= json_encode(
                array_values(array_map(static function (array $field): array {
                    return [
                        'key'=>(string)$field['key'],
                        'label'=>(string)$field['label'],
                        'section'=>(string)($field['section']??'Other'),
                        'applicable_if'=>(array)($field['applicable_if']??[]),
                        'type'=>(string)$field['type'],
                        'scopes'=>array_values((array)($field['report_scopes']??['place'])),
                        'options'=>(array)($field['options']??[]),
                        'unknown'=>!empty($field['allow_unknown']),
                        'derived'=>!empty($field['derived']),
                        'location'=>!empty($field['location_field']),
                    ];
                }, llama_place_report_fields())), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>

            ?>


            <details
                class="contribution-section place-update-observation-section"
                open
            >


                <summary>

                    <span>

                        <i
                            aria-hidden="true"
                        ><?= llama_icon('calendar-check') ?></i>

                        Your observation

                    </span>


                    <small>
                        When you personally observed these changes
                    </small>

                </summary>


                <div class="contribution-section-body">


                    <div class="contribution-grid">


                        <label class="contribution-field">

                            <span>
                                Date visited
                            </span>

                            <input
                                type="date"
                                name="visited_at"
                                value="<?= $e(
                                    $visitedAtValue
                                ) ?>"
                            >

                        </label>


                        <label
                            class="contribution-field contribution-field-wide"
                        >

                            <span>
                                Notes for the reviewer
                            </span>

                            <textarea
                                name="contributor_notes"
                                rows="4"
                                placeholder="Explain what changed, what you personally observed, or anything the moderator should verify."
                            ><?= $e(
                                $contributorNotesValue
                            ) ?></textarea>

                        </label>


                    </div>


                </div>


            </details>


            <details
                class="contribution-section place-update-photo-section"
                open
            >


                <summary>

                    <span>

                        <i
                            aria-hidden="true"
                        ><?= llama_icon('camera') ?></i>

                        Photos from this visit

                    </span>


                    <small>
                        Evidence of changed or current conditions
                    </small>

                </summary>


                <div class="contribution-section-body">


                    <?php if (
                        $isNeedsChanges
                        && $editPhotos
                    ): ?>


                        <div class="place-update-existing-photos">


                            <strong>
                                Already attached
                            </strong>


                            <p>
                                These photos remain attached to this update.
                                Add more evidence below if moderation requested it.
                            </p>


                            <div class="place-update-photo-grid">


                                <?php foreach (
                                    $editPhotos
                                    as $photo
                                ): ?>


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


                                    <?php if (
                                        $src !== ''
                                        && $photoUrl !== ''
                                    ): ?>


                                        <img
                                            src="<?= $e(
                                                $photoUrl
                                            ) ?>"
                                            alt="<?= $e(
                                                is_array($photo)
                                                    ? (
                                                        $photo['alt']
                                                        ?? ''
                                                    )
                                                    : ''
                                            ) ?>"
                                            loading="lazy"
                                        >


                                    <?php endif; ?>


                                <?php endforeach; ?>


                            </div>


                        </div>


                    <?php endif; ?>


                    <div
                        data-photo-uploader
                        data-photo-context="update-place"
                        data-photo-max="5"
                        data-photo-csrf="<?= $e(
                            llama_photo_csrf_token()
                        ) ?>"
                        data-photo-title="Photos from this visit"
                        data-photo-help="Add up to 5 current photos showing what changed. Signs, gates, roads, closures, amenities, and obstructions are especially useful."
                    ></div>


                </div>


            </details>


            <div class="contribution-actions add-place-submit-bar">


                <button
                    class="contribution-submit"
                    type="submit"
                >

                    <i
                        aria-hidden="true"
                    ><?= llama_icon('send') ?></i>


                    <?= $isNeedsChanges
                        ? 'Resubmit Update'
                        : 'Submit Update' ?>

                </button>


                <a
                    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                        $slug
                    ) ?>"
                >
                    Cancel
                </a>


            </div>


        </form>


    <?php endif; ?>


</section>


<script
    src="https://llamascout.com/js/add-place-location.js"
></script>

<script
    src="https://llamascout.com/js/place-report-form.js"
></script>


<?php

require dirname(__DIR__)
    . '/partials/footer.php';

?>
