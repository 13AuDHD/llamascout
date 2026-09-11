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
                : 'Community contribution' ?>

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
                class="fa-solid fa-circle-info"
                aria-hidden="true"
            ></i>

            <span>
                Your edits do not change the live Place immediately.
                A moderator reviews the differences first.
                Only approved changes are published.
            </span>

        </div>


    </header>


    <?php if ($isPendingUpdate): ?>


        <div class="contribution-message">

            <i
                class="fa-solid fa-clock"
                aria-hidden="true"
            ></i>


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
                        class="fa-solid fa-triangle-exclamation"
                        aria-hidden="true"
                    ></i>


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
            require dirname(__DIR__)
                . '/partials/place-report/form.php';

            ?>


            <details
                class="contribution-section place-update-observation-section"
                open
            >


                <summary>

                    <span>

                        <i
                            class="fa-solid fa-calendar-check"
                            aria-hidden="true"
                        ></i>

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
                            class="fa-solid fa-camera"
                            aria-hidden="true"
                        ></i>

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
                        class="fa-solid fa-paper-plane"
                        aria-hidden="true"
                    ></i>


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
