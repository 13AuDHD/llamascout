<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_verified_email();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            submit_new_place($userId, $_POST);
            header('Location: https://account.llamascout.com/contributions.php?submitted=new', true, 303);
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'The place could not be submitted. Please try again.';
        }
    }
}

$pageTitle = 'Add a Place | Llama Scout';
require __DIR__ . '/partials/header.php';
?>

<section class="contribution-page">
    <header class="contribution-header">
        <p class="eyebrow">Community contribution</p>
        <h1>Add a place</h1>
        <p>Share a campsite, pull-off, trailhead, or other useful outdoor place. Nothing is published automatically. A moderator reviews the submission first.</p>
    </header>

    <?php if ($error): ?>
        <div class="contribution-message is-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="contribution-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(community_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="photo_stage_token" value="<?= htmlspecialchars((string) ($_POST['photo_stage_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="photos_json" value="<?= htmlspecialchars((string) ($_POST['photos_json'] ?? '[]'), ENT_QUOTES, 'UTF-8') ?>">

        <fieldset>
            <legend>Place</legend>
            <div class="contribution-grid">
                <label class="contribution-field contribution-field-wide">
                    <span>Place name *</span>
                    <input name="name" required maxlength="200" value="<?= htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="contribution-field">
                    <span>Type</span>
                    <select name="type">
                        <?php foreach (community_place_types() as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (($_POST['type'] ?? 'dispersed-camping') === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="contribution-field">
                    <span>Date visited</span>
                    <input type="date" name="visited_at" value="<?= htmlspecialchars((string) ($_POST['visited_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="contribution-field contribution-field-wide">
                    <span>Description</span>
                    <textarea name="description" rows="5"><?= htmlspecialchars((string) ($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Location</legend>
            <div class="contribution-grid">
                <label class="contribution-field"><span>Latitude</span><input inputmode="decimal" name="latitude" value="<?= htmlspecialchars((string) ($_POST['latitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>Longitude</span><input inputmode="decimal" name="longitude" value="<?= htmlspecialchars((string) ($_POST['longitude'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>Elevation (ft)</span><input inputmode="numeric" name="elevation_feet" value="<?= htmlspecialchars((string) ($_POST['elevation_feet'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>Road</span><input name="road" value="<?= htmlspecialchars((string) ($_POST['road'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>City</span><input name="city" value="<?= htmlspecialchars((string) ($_POST['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>County</span><input name="county" value="<?= htmlspecialchars((string) ($_POST['county'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>State</span><input name="state" value="<?= htmlspecialchars((string) ($_POST['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>Region / district</span><input name="region" value="<?= htmlspecialchars((string) ($_POST['region'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>Land manager</span><input name="land_manager" value="<?= htmlspecialchars((string) ($_POST['land_manager'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="contribution-field"><span>Land type</span><input name="land_type" value="<?= htmlspecialchars((string) ($_POST['land_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
            </div>
        </fieldset>

        <fieldset>
            <legend>What should people know?</legend>
            <div class="contribution-grid">
                <label class="contribution-field contribution-field-wide"><span>Access summary</span><textarea name="access_summary" rows="4"><?= htmlspecialchars((string) ($_POST['access_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
                <label class="contribution-field contribution-field-wide"><span>Sensory summary</span><textarea name="sensory_summary" rows="4"><?= htmlspecialchars((string) ($_POST['sensory_summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
                <label class="contribution-field contribution-field-wide"><span>Notes for the reviewer</span><textarea name="contributor_notes" rows="3"><?= htmlspecialchars((string) ($_POST['contributor_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Photos</legend>
            <div
                data-photo-uploader
                data-photo-context="add-place"
                data-photo-max="10"
                data-photo-csrf="<?= htmlspecialchars(llama_photo_csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
                data-photo-title="Photos of this place"
                data-photo-help="Add up to 10 current photos. You can remove anything before submitting, and abandoned uploads are cleaned up automatically."
            ></div>
        </fieldset>

        <div class="contribution-actions">
            <button class="contribution-submit" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit place</button>
            <a href="/map.php">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
