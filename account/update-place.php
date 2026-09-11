<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-update.php';

require_verified_email();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$slug = trim(
    (string) (
        $_GET['slug']
        ?? $_POST['slug']
        ?? ''
    )
);

$place = $slug !== ''
    ? community_find_place_for_update($slug)
    : null;

if (!$place) {
    http_response_code(404);

    $pageTitle = 'Place not found | Llama Scout';

    require dirname(__DIR__) . '/partials/header.php';

    echo '<section class="contribution-page"><h1>Place not found</h1><p>This Place is not available for updates.</p></section>';

    require dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$error = null;

$openUpdate = community_open_update_for_user(
    $userId,
    (int) $place['id']
);

$isNeedsChanges =
    $openUpdate
    && (string) ($openUpdate['status'] ?? '') === 'needs-changes';

$isPendingUpdate =
    $openUpdate
    && (string) ($openUpdate['status'] ?? '') === 'pending';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$isPendingUpdate
) {
    if (
        !community_verify_csrf(
            (string) ($_POST['csrf_token'] ?? '')
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
            } else {
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
            }

            exit;

        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'account.place_update_submit',
                [
                    'place_id' => (int) ($place['id'] ?? 0),
                    'user_id' => $userId,
                ],
                [
                    InvalidArgumentException::class,
                    RuntimeException::class,
                ]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The update could not be submitted. Please try again.',
                    $reference
                );
        }
    }
}

$db = db();

$sections = llama_place_update_sections();

$currentValues = community_update_current_values(
    $db,
    (int) $place['id']
);

$editProposed = [];
$editPhotos = [];
$editVisitedAt = '';
$editContributorNotes = '';

if ($isNeedsChanges) {
    $editProposed = llama_place_update_decode_json(
        $openUpdate['proposed_changes'] ?? '{}'
    );

    $editPhotos = llama_place_update_decode_json(
        $openUpdate['photos'] ?? '[]'
    );

    $editVisitedAt = !empty($openUpdate['visited_at'])
        ? substr((string) $openUpdate['visited_at'], 0, 10)
        : '';

    $editContributorNotes = (string) (
        $openUpdate['contributor_notes']
        ?? ''
    );
}

$pageTitle = 'Suggest an Update | Llama Scout';

$pageStyles = [
    'account/pages/update-place.css',
    'site/features/place-report-form.css',
];

require dirname(__DIR__) . '/partials/header.php';

$e = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$postSelected = is_array($_POST['change_fields'] ?? null)
    ? array_values($_POST['change_fields'])
    : array_keys($editProposed);

$postValues = is_array($_POST['field_value'] ?? null)
    ? $_POST['field_value']
    : [];
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
            <?= $e($place['name']) ?>
        </p>

        <div class="add-place-form-note">
            <i
                class="fa-solid fa-circle-info"
                aria-hidden="true"
            ></i>

            <span>
                Check <strong>Change</strong> only for information you are updating.
                The moderator receives the old value and your proposed value side by side.
            </span>
        </div>
    </header>

    <?php if ($isPendingUpdate): ?>

        <div class="contribution-message">
            <i class="fa-solid fa-clock" aria-hidden="true"></i>

            <div>
                <strong>This update is already in review.</strong>
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
                        <span>Moderator request</span>
                        <strong>
                            Changes are needed before this update can be approved.
                        </strong>
                    </div>
                </div>

                <?php if (!empty($openUpdate['review_notes'])): ?>
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
                <?= $e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            class="contribution-form place-update-form"
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
                value="<?= $e($slug) ?>"
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

            <details class="contribution-section" open>
                <summary>
                    <span>
                        <i
                            class="fa-solid fa-location-crosshairs"
                            aria-hidden="true"
                        ></i>
                        Location helper
                    </span>

                    <small>
                        Optional GPS, elevation, and reverse-location lookup
                    </small>
                </summary>

                <div class="contribution-section-body">
                    <div class="add-place-locate-panel">
                        <div>
                            <strong>Standing at the Place?</strong>

                            <span>
                                Locate Me can populate coordinate, elevation,
                                road, city, county, and state controls.
                                Only fields marked Change are submitted.
                            </span>
                        </div>

                        <button
                            class="add-place-locate-button"
                            type="button"
                            data-locate-place
                        >
                            <i
                                class="fa-solid fa-crosshairs"
                                aria-hidden="true"
                            ></i>
                            Locate me
                        </button>
                    </div>

                    <div
                        class="add-place-location-status"
                        data-location-status
                        aria-live="polite"
                    ></div>
                </div>
            </details>

            <?php foreach ($sections as $sectionKey => $section): ?>
                <details
                    class="contribution-section place-update-section"
                    <?= in_array(
                        $sectionKey,
                        ['basic', 'location'],
                        true
                    )
                        ? 'open'
                        : '' ?>
                >
                    <summary>
                        <span>
                            <i
                                class="fa-solid <?= $e(
                                    $section['icon']
                                    ?? 'fa-pen-to-square'
                                ) ?>"
                                aria-hidden="true"
                            ></i>

                            <?= $e($section['label'] ?? 'Other') ?>
                        </span>

                        <small>
                            <?= $e(
                                $section['description']
                                ?? 'Select only what changed.'
                            ) ?>
                        </small>
                    </summary>

                    <div class="contribution-section-body">
                        <div class="place-update-field-list">

                            <?php foreach (
                                (array) ($section['fields'] ?? [])
                                as $path => $definition
                            ): ?>
                                <?php
                                $current = $currentValues[$path] ?? null;

                                $checked = in_array(
                                    $path,
                                    $postSelected,
                                    true
                                );

                                $renderValue = array_key_exists(
                                    $path,
                                    $postValues
                                )
                                    ? $postValues[$path]
                                    : (
                                        array_key_exists(
                                            $path,
                                            $editProposed
                                        )
                                            ? $editProposed[$path]
                                            : $current
                                    );

                                $placeUpdatePath = $path;
                                $placeUpdateDefinition = $definition;
                                $placeUpdateCurrent = $current;
                                $placeUpdateValue = $renderValue;
                                $placeUpdateChecked = $checked;

                                require dirname(__DIR__)
                                    . '/partials/place-report/update-field.php';
                                ?>
                            <?php endforeach; ?>

                        </div>
                    </div>
                </details>
            <?php endforeach; ?>

            <details class="contribution-section" open>
                <summary>
                    <span>
                        <i
                            class="fa-solid fa-calendar-check"
                            aria-hidden="true"
                        ></i>
                        Your visit
                    </span>

                    <small>
                        When you personally observed these changes
                    </small>
                </summary>

                <div class="contribution-section-body">
                    <div class="contribution-grid">

                        <label class="contribution-field">
                            <span>Date visited</span>

                            <input
                                type="date"
                                name="visited_at"
                                value="<?= $e(
                                    $_POST['visited_at']
                                    ?? $editVisitedAt
                                ) ?>"
                            >
                        </label>

                        <label class="contribution-field contribution-field-wide">
                            <span>Notes for the reviewer</span>

                            <textarea
                                name="contributor_notes"
                                rows="4"
                                placeholder="Explain what changed, what you personally observed, or anything the moderator should verify."
                            ><?= $e(
                                $_POST['contributor_notes']
                                ?? $editContributorNotes
                            ) ?></textarea>
                        </label>

                    </div>
                </div>
            </details>

            <details class="contribution-section" open>
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

                    <?php if ($isNeedsChanges && $editPhotos): ?>
                        <div class="place-update-existing-photos">
                            <strong>Already attached</strong>

                            <p>
                                These remain with this update. Add more evidence below
                                if moderation requested it.
                            </p>

                            <div class="place-update-photo-grid">
                                <?php foreach ($editPhotos as $photo): ?>
                                    <?php
                                    $src = is_array($photo)
                                        ? trim(
                                            (string) (
                                                $photo['src']
                                                ?? $photo['path']
                                                ?? ''
                                            )
                                        )
                                        : '';

                                    $photoUrl = $src !== ''
                                        ? (
                                            preg_match('#^https?://#i', $src)
                                                ? $src
                                                : 'https://llamascout.com/'
                                                    . ltrim($src, '/')
                                        )
                                        : '';
                                    ?>

                                    <?php if ($photoUrl !== ''): ?>
                                        <img
                                            src="<?= $e($photoUrl) ?>"
                                            alt="<?= $e(
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
                    href="https://llamascout.com/place.php?slug=<?= rawurlencode($slug) ?>"
                >
                    Cancel
                </a>
            </div>
        </form>

    <?php endif; ?>

</section>

<script src="https://llamascout.com/js/add-place-location.js"></script>
<script src="https://llamascout.com/js/photo-uploader.js"></script>
<script src="https://llamascout.com/js/place-update.js"></script>

<?php
require dirname(__DIR__)
    . '/partials/footer.php';
?>
