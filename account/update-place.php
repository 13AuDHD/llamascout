<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_verified_email();

$user = current_user();

$userId =
    (int) ($user['id'] ?? 0);

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

if (!$place) {
    http_response_code(404);

    $pageTitle =
        'Place not found | Llama Scout';

    require dirname(__DIR__) .
        '/partials/header.php';

    echo '<section class="contribution-page"><h1>Place not found</h1><p>This Place is not available for updates.</p></section>';

    require dirname(__DIR__) .
        '/partials/footer.php';

    exit;
}

$error = null;

$openUpdate =
    community_open_update_for_user(
        $userId,
        (int) $place['id']
    );

$isNeedsChanges =
    $openUpdate
    && (string) (
        $openUpdate['status']
        ?? ''
    ) === 'needs-changes';

$isPendingUpdate =
    $openUpdate
    && (string) (
        $openUpdate['status']
        ?? ''
    ) === 'pending';

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
                community_resubmit_place_update(
                    $userId,
                    $place,
                    (int) $openUpdate['id'],
                    $_POST
                );

                header(
                    'Location: /contributions.php?submitted=update-resubmitted',
                    true,
                    303
                );

            } else {
                submit_place_update(
                    $userId,
                    $place,
                    $_POST
                );

                header(
                    'Location: /contributions.php?submitted=update',
                    true,
                    303
                );
            }

            exit;

        } catch (Throwable $exception) {
            $error =
                (
                    $exception
                    instanceof InvalidArgumentException
                    || $exception
                    instanceof RuntimeException
                )
                    ? $exception->getMessage()
                    : 'The update could not be submitted. Please try again.';
        }
    }
}

$db = db();

$definitions =
    community_place_update_field_definitions();

$currentValues =
    community_update_current_values(
        $db,
        (int) $place['id']
    );

$editProposed = [];
$editPhotos = [];
$editVisitedAt = '';
$editContributorNotes = '';

if ($isNeedsChanges) {
    $decodedProposed =
        json_decode(
            (string) (
                $openUpdate['proposed_changes']
                ?? '{}'
            ),
            true
        );

    $editProposed =
        is_array($decodedProposed)
            ? $decodedProposed
            : [];

    $decodedPhotos =
        json_decode(
            (string) (
                $openUpdate['photos']
                ?? '[]'
            ),
            true
        );

    $editPhotos =
        is_array($decodedPhotos)
            ? $decodedPhotos
            : [];

    $editVisitedAt =
        !empty($openUpdate['visited_at'])
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
}

$groups = [];

foreach ($definitions as $path => $definition) {
    $groups[
        (string) $definition['group']
    ][$path] = $definition;
}

function update_place_e(
    mixed $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function update_place_selected(
    mixed $current,
    string $value
): string {
    if (is_bool($current)) {
        $current =
            $current
                ? '1'
                : '0';
    }

    if ($current === null) {
        $current = '__NULL__';
    }

    return (string) $current === $value
        ? 'selected'
        : '';
}

function update_place_display_current(
    mixed $value,
    string $type
): string {
    if ($value === null || $value === '') {
        return 'Unknown';
    }

    if ($type === 'bool') {
        return (int) $value === 1
            ? 'Yes'
            : 'No';
    }

    if ($type === 'rating') {
        return (string) $value . '/5';
    }

    return (string) $value;
}

function update_place_render_control(
    string $path,
    array $definition,
    mixed $current
): void {
    $type =
        (string) $definition['type'];

    $name =
        'field_value[' .
        $path .
        ']';

    if ($type === 'bool') {
        ?>
        <select name="<?= update_place_e($name) ?>">
            <option value="__NULL__" <?= update_place_selected($current, '__NULL__') ?>>Unknown</option>
            <option value="1" <?= update_place_selected($current, '1') ?>>Yes</option>
            <option value="0" <?= update_place_selected($current, '0') ?>>No</option>
        </select>
        <?php
        return;
    }

    if ($type === 'rating') {
        ?>
        <select name="<?= update_place_e($name) ?>">
            <option value="__NULL__" <?= update_place_selected($current, '__NULL__') ?>>Unknown</option>
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <option value="<?= $i ?>" <?= update_place_selected($current, (string) $i) ?>>
                    <?= $i ?>/5
                </option>
            <?php endfor; ?>
        </select>
        <?php
        return;
    }

    if ($type === 'surface') {
        $options = [
            '__NULL__' => 'Unknown',
            'paved' => 'Paved / asphalt',
            'concrete' => 'Concrete',
            'graded-gravel' => 'Graded gravel',
            'loose-gravel' => 'Loose gravel',
            'hard-packed-dirt' => 'Hard-packed dirt',
            'dirt' => 'Dirt',
            'sand' => 'Sand',
            'rock' => 'Rock / bedrock',
            'grass' => 'Grass',
            'mixed' => 'Mixed surface',
        ];
        ?>
        <select name="<?= update_place_e($name) ?>">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?= update_place_e($value) ?>" <?= update_place_selected($current, $value) ?>>
                    <?= update_place_e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return;
    }

    if ($type === 'ground') {
        $options = [
            '__NULL__' => 'Unknown',
            'level-firm' => 'Mostly level and firm',
            'uneven-firm' => 'Uneven but firm',
            'rocky' => 'Rocky',
            'soft' => 'Soft / sandy',
            'mud-prone' => 'Mud-prone',
            'grass' => 'Grassy',
            'mixed' => 'Mixed',
        ];
        ?>
        <select name="<?= update_place_e($name) ?>">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?= update_place_e($value) ?>" <?= update_place_selected($current, $value) ?>>
                    <?= update_place_e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return;
    }

    if ($type === 'road_width') {
        $options = [
            '__NULL__' => 'Unknown',
            'one-lane' => 'One lane',
            'one-and-half-lane' => 'About 1.5 lanes',
            'two-lane' => 'Two lane',
            'wide-two-lane' => 'Wide two lane',
            'varies' => 'Varies significantly',
        ];
        ?>
        <select name="<?= update_place_e($name) ?>">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?= update_place_e($value) ?>" <?= update_place_selected($current, $value) ?>>
                    <?= update_place_e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return;
    }

    if ($type === 'land_manager') {
        $options = [
            '__NULL__' => 'Unknown / not sure',
            'U.S. Forest Service' => 'U.S. Forest Service',
            'Bureau of Land Management' => 'Bureau of Land Management (BLM)',
            'National Park Service' => 'National Park Service',
            'U.S. Fish and Wildlife Service' => 'U.S. Fish and Wildlife Service',
            'U.S. Army Corps of Engineers' => 'U.S. Army Corps of Engineers',
            'Bureau of Reclamation' => 'Bureau of Reclamation',
            'State government' => 'State government',
            'County / regional government' => 'County / regional government',
            'City / municipal government' => 'City / municipal government',
            'Tribal government' => 'Tribal government',
            'Private' => 'Private',
            'Other' => 'Other / mixed management',
        ];
        ?>
        <select name="<?= update_place_e($name) ?>">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?= update_place_e($value) ?>" <?= update_place_selected($current, $value) ?>>
                    <?= update_place_e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return;
    }

    if ($type === 'land_type') {
        $options = [
            '__NULL__' => 'Unknown / not sure',
            'National Forest' => 'National Forest',
            'BLM Land' => 'BLM Land',
            'National Park' => 'National Park',
            'National Monument' => 'National Monument',
            'National Recreation Area' => 'National Recreation Area',
            'National Wildlife Refuge' => 'National Wildlife Refuge',
            'State Forest' => 'State Forest',
            'State Park' => 'State Park',
            'State Trust Land' => 'State Trust Land',
            'Wildlife Management Area' => 'Wildlife Management Area',
            'County / Regional Park' => 'County / Regional Park',
            'City / Municipal Land' => 'City / Municipal Land',
            'Army Corps of Engineers' => 'Army Corps of Engineers',
            'Bureau of Reclamation' => 'Bureau of Reclamation',
            'Tribal Land' => 'Tribal Land',
            'Private Land' => 'Private Land',
            'Roadside / Highway Right-of-Way' => 'Roadside / Highway Right-of-Way',
            'Other' => 'Other',
        ];
        ?>
        <select name="<?= update_place_e($name) ?>">
            <?php foreach ($options as $value => $label): ?>
                <option value="<?= update_place_e($value) ?>" <?= update_place_selected($current, $value) ?>>
                    <?= update_place_e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return;
    }

    if ($type === 'textarea') {
        ?>
        <textarea
            name="<?= update_place_e($name) ?>"
            rows="4"
        ><?= update_place_e((string) ($current ?? '')) ?></textarea>
        <?php
        return;
    }

    $inputType =
        in_array(
            $type,
            ['int', 'float'],
            true
        )
            ? 'number'
            : 'text';

    $step =
        $type === 'float'
            ? 'any'
            : '1';

    $dataAttr = '';

    if (in_array($path, ['latitude','longitude','elevation_feet','road','city','county','state'], true)) {
        $dataAttr =
            ' data-location-field="' .
            update_place_e($path) .
            '"';
    }
    ?>
    <input
        type="<?= update_place_e($inputType) ?>"
        name="<?= update_place_e($name) ?>"
        value="<?= update_place_e((string) ($current ?? '')) ?>"
        <?= $inputType === 'number' ? 'step="' . update_place_e($step) . '"' : '' ?>
        <?= $dataAttr ?>
    >
    <?php
}

$pageTitle =
    'Suggest an Update | Llama Scout';

require dirname(__DIR__) .
    '/partials/header.php';
?>

<section class="contribution-page update-place-overhaul">

<header class="contribution-header">
    <p class="eyebrow">Community contribution</p>

    <h1>Suggest an update</h1>

    <p>
        <?= update_place_e(
            (string) $place['name']
        ) ?>
    </p>

    <div class="add-place-form-note">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>

        <span>
            Select only the fields that actually changed. Current values stay
            visible beside each control so you do not have to remember what the
            Scout Report already says.
        </span>
    </div>
</header>


<?php if ($isPendingUpdate): ?>

<div class="contribution-message">
    <i class="fa-solid fa-clock" aria-hidden="true"></i>

    You already have an open update for this Place.
    You can track it from My contributions.
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

<div class="contribution-message is-attention">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>

    <div>
        <strong>Changes requested by moderation.</strong>

        <?php if (!empty($openUpdate['review_notes'])): ?>
            <span>
                <?= nl2br(
                    update_place_e(
                        (string) $openUpdate['review_notes']
                    )
                ) ?>
            </span>
        <?php else: ?>
            <span>
                Review the proposed fields below, make the requested corrections,
                and resubmit the same update.
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
    <?= update_place_e($error) ?>
</div>

<?php endif; ?>


<form
    method="post"
    class="contribution-form update-place-form"
>

<input
    type="hidden"
    name="csrf_token"
    value="<?= update_place_e(
        community_csrf_token()
    ) ?>"
>

<input
    type="hidden"
    name="slug"
    value="<?= update_place_e($slug) ?>"
>

<?php if ($isNeedsChanges): ?>
<input
    type="hidden"
    name="update_submission_id"
    value="<?= (int) $openUpdate['id'] ?>"
>
<?php endif; ?>

<input
    type="hidden"
    name="photo_stage_token"
    value="<?= update_place_e(
        (string) (
            $_POST['photo_stage_token']
            ?? ''
        )
    ) ?>"
>

<input
    type="hidden"
    name="photos_json"
    value="<?= update_place_e(
        (string) (
            $_POST['photos_json']
            ?? '[]'
        )
    ) ?>"
>


<details class="contribution-section" open>

<summary>
    <span>
        <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
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
            Locate me can populate the new coordinate, elevation,
            road, city, county, and state controls below. Only fields
            you explicitly select for change will be submitted.
        </span>
    </div>

    <button
        class="add-place-locate-button"
        type="button"
        data-locate-place
    >
        <i class="fa-solid fa-crosshairs" aria-hidden="true"></i>
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


<?php foreach ($groups as $groupName => $fields): ?>

<details
    class="contribution-section update-place-section"
    <?= in_array($groupName, ['Basic information','Location'], true)
        ? 'open'
        : '' ?>
>

<summary>
    <span>
        <?= update_place_e($groupName) ?>
    </span>

    <small>
        Check a field only if you are proposing a new value.
    </small>
</summary>

<div class="contribution-section-body">

<div class="update-place-field-list">

<?php foreach ($fields as $path => $definition): ?>

<?php
$current =
    $currentValues[$path]
    ?? null;
?>

<article class="update-place-field-card">

<label class="update-place-change-toggle">

<input
    type="checkbox"
    name="change_fields[]"
    value="<?= update_place_e($path) ?>"
    data-update-field-toggle="<?= update_place_e($path) ?>"
    <?= in_array(
        $path,
        (array) (
            $_POST['change_fields']
            ?? array_keys($editProposed)
        ),
        true
    )
        ? 'checked'
        : '' ?>
>

<span>
    Change
</span>

</label>


<div class="update-place-field-main">

<div class="update-place-field-heading">

<strong>
    <?= update_place_e(
        (string) $definition['label']
    ) ?>
</strong>

<small>
    Current:
    <b>
        <?= update_place_e(
            update_place_display_current(
                $current,
                (string) $definition['type']
            )
        ) ?>
    </b>
</small>

</div>


<div
    class="update-place-field-control"
    data-update-field-control="<?= update_place_e($path) ?>"
>

<?php
$postValues =
    is_array(
        $_POST['field_value']
        ?? null
    )
        ? $_POST['field_value']
        : [];

$renderValue =
    array_key_exists($path, $postValues)
        ? $postValues[$path]
        : (
            array_key_exists(
                $path,
                $editProposed
            )
                ? $editProposed[$path]
                : $current
        );

update_place_render_control(
    $path,
    $definition,
    $renderValue
);
?>

</div>

</div>

</article>

<?php endforeach; ?>

</div>

</div>

</details>

<?php endforeach; ?>


<details class="contribution-section" open>

<summary>
    <span>
        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
        Your visit
    </span>

    <small>
        When you observed the changes
    </small>
</summary>

<div class="contribution-section-body">

<div class="contribution-grid">

<label class="contribution-field">
    <span>Date visited</span>

    <input
        type="date"
        name="visited_at"
        value="<?= update_place_e(
            (string) (
                $_POST['visited_at']
                ?? $editVisitedAt
            )
        ) ?>"
    >
</label>


<label class="contribution-field contribution-field-wide">
    <span>Notes for the reviewer</span>

    <textarea
        name="contributor_notes"
        rows="4"
        placeholder="Explain what changed, what you personally observed, or anything the moderator should verify."
    ><?= update_place_e(
        (string) (
            $_POST['contributor_notes']
            ?? $editContributorNotes
        )
    ) ?></textarea>
</label>

</div>

</div>

</details>


<details class="contribution-section" open>

<summary>
    <span>
        <i class="fa-solid fa-camera" aria-hidden="true"></i>
        Photos from this visit
    </span>

    <small>
        Evidence of changed or current conditions
    </small>
</summary>

<div class="contribution-section-body">

<?php if ($isNeedsChanges && $editPhotos): ?>

<div class="update-place-existing-photos">
    <strong>Already attached to this update</strong>
    <p>
        These photos remain with the resubmission. Add new evidence below if
        moderation requested it.
    </p>

    <div class="contribution-existing-photo-grid">
        <?php foreach ($editPhotos as $photo): ?>
            <?php
            $existingSrc =
                is_array($photo)
                    ? trim(
                        (string) (
                            $photo['src']
                            ?? ''
                        )
                    )
                    : '';
            ?>
            <?php if ($existingSrc !== ''): ?>
                <img
                    src="https://llamascout.com<?= update_place_e($existingSrc) ?>"
                    alt="<?= update_place_e(
                        (string) (
                            $photo['alt']
                            ?? ''
                        )
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
    data-photo-csrf="<?= update_place_e(
        llama_photo_csrf_token()
    ) ?>"
    data-photo-title="Photos from this visit"
    data-photo-help="Add up to 5 current photos showing what changed. Signs, gates, road conditions, closures, amenities, and obstructions are especially useful."
></div>

</div>

</details>


<div class="contribution-actions add-place-submit-bar">

<button
    class="contribution-submit"
    type="submit"
>
    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
    Submit update
</button>

<a
    href="https://llamascout.com/place.php?slug=<?= rawurlencode($slug) ?>"
>
    Cancel
</a>

</div>

</form>

<script src="https://llamascout.com/js/add-place-location.js"></script>

<script>
"use strict";

document.addEventListener("DOMContentLoaded", () => {
    document
        .querySelectorAll("[data-update-field-toggle]")
        .forEach(toggle => {
            const path = toggle.dataset.updateFieldToggle;
            const control = document.querySelector(
                `[data-update-field-control="${CSS.escape(path)}"]`
            );

            if (!control) return;

            const sync = () => {
                control.classList.toggle(
                    "is-disabled",
                    !toggle.checked
                );

                control
                    .querySelectorAll("input, select, textarea")
                    .forEach(field => {
                        field.disabled = !toggle.checked;
                    });
            };

            toggle.addEventListener(
                "change",
                sync
            );

            sync();
        });
});
</script>

<?php endif; ?>

</section>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
