<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/site-submissions.php';
require_once __DIR__ . '/app/area-submissions.php';

require_verified_email();
$userId = (int) (current_user()['id'] ?? 0);
$db = db();
if (!llama_contributor_can($db, $userId, 'submit_place')) {
    http_response_code(403);
    exit('Your account cannot submit new Sites.');
}

$ready = llama_site_submissions_ready($db);
$error = '';
$selectedPlaceId = max(0, (int) ($_POST['site_place_id'] ?? $_GET['site_place_id'] ?? $_GET['place_id'] ?? 0));
$search = trim((string) ($_GET['site_q'] ?? ''));
$placeChoices = llama_area_place_choices($db, $search, $selectedPlaceId);
$siteFields = llama_site_submission_fields();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!community_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('Your session expired. Refresh the page and try again.');
        }
        if (!$ready) {
            throw new RuntimeException('The Site submissions database migration is not installed.');
        }

        // Read only canonical Site question names, not an unverified browser
        // target/scope nor Place question data from a stale tab.
        $answers = array_intersect_key($_POST, $siteFields);
        $submissionInput = [
            'site_place_id' => $selectedPlaceId,
            'site_name' => $_POST['site_name'] ?? '',
            'site_coordinates' => $_POST['site_coordinates'] ?? '',
            'site_answers' => $answers,
            'visited_at' => $_POST['visited_at'] ?? '',
        ];
        llama_site_submission_submit(
            $db,
            $userId,
            $submissionInput,
            (string) ($_POST['photo_stage_token'] ?? ''),
            llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]')
        );
        header('Location: /add-site.php?submitted=1', true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'site.submit',
            ['user_id' => $userId, 'place_id' => $selectedPlaceId]);
        $error = 'The Site could not be submitted. Check your answers and try again.';
    }
}

$pageTitle = 'Add a Site | Llama Scout';
$pageStyles = [
    'site/pages/add-place.css',
    'site/features/place-report-form.css',
    'site/features/contribution-tabs.css',
    'photo-uploader.css',
];
$placeReportValues = (array) ($_POST ?? []);
$placeReportMode = 'contributor';
$placeReportOnlyScope = 'site';
$placeReportShowLocate = true;
$placeReportShowNameSuggestion = false;
$placeReportShowFieldHelp = true;
$placeReportShowPhotos = true;
$placeReportRequiredFields = ['visited_at'];
$placeReportPhotoContext = 'add-site';
$placeReportPhotoCsrf = llama_photo_csrf_token();
$placeReportPhotoTitle = 'Photos of this Site';
$placeReportPhotoHelp = 'Show the actual campsite or parking space, its setup and any important observations.';
$placeReportPhotoMax = llama_location_gallery_photo_limit('site');

$e = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/partials/header.php';
?>
<section class="contribution-page add-place-page">
    <header class="contribution-header">
        <p class="eyebrow">Scout contribution</p>
        <h1>Add a Site</h1>
    </header>

    <?php $contributionPickerResourcePage = true; $contributionIsSite = true; ?>
    <?php require __DIR__ . '/partials/place-report/contribution-tabs.php'; ?>

    <?php if (isset($_GET['submitted'])): ?>
        <div class="contribution-message is-success" role="status">
            Site submitted for review. Its parent Place and other Sites have not been changed.
        </div>
    <?php endif; ?>
    <?php if (!$ready): ?>
        <div class="contribution-message is-attention" role="alert">
            Add Site is not enabled yet. An administrator must install
            <code>tools/migrations/20261118_standalone_site_submissions.sql</code>
            before new Site submissions can be accepted.
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="contribution-message is-error" role="alert"><?= $e($error) ?></div>
    <?php endif; ?>

    <section class="contribution-type-intro">
        <h2>Select the parent Place</h2>
        <p>A Site belongs to one existing Place. It will have its own location,
           answers, photos and moderation history.</p>
    </section>
    <form method="get" action="/add-site.php" class="contribution-form">
        <label class="contribution-field" for="site-place-search">Find an existing Place</label>
        <div class="contribution-actions">
            <input id="site-place-search" type="search" name="site_q"
                   value="<?= $e($search) ?>"
                   placeholder="Search by Place name or ID">
            <button type="submit" class="contribution-submit">Search</button>
        </div>
    </form>

    <form method="post" class="contribution-form add-place-form place-report-form"
          data-place-report-completion-form id="add-site-main-form">
        <input type="hidden" name="csrf_token" value="<?= $e(community_csrf_token()) ?>">
        <input type="hidden" name="photo_stage_token"
               value="<?= $e($_POST['photo_stage_token'] ?? '') ?>">
        <input type="hidden" name="photos_json"
               value="<?= $e($_POST['photos_json'] ?? '[]') ?>">

        <div class="contribution-grid">
            <label class="contribution-field">
                <span class="place-report-field-label">Parent Place *</span>
                <select name="site_place_id" required>
                    <option value="">Select an existing Place</option>
                    <?php foreach ($placeChoices as $place): ?>
                        <option value="<?= (int) $place['id'] ?>"
                            <?= $selectedPlaceId === (int) $place['id'] ? 'selected' : '' ?>>
                            <?= $e($place['name']) ?> (<?= $e($place['state'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="contribution-field">
                <span class="place-report-field-label">Site name *</span>
                <input type="text" name="site_name" maxlength="120" required
                       value="<?= $e($_POST['site_name'] ?? '') ?>"
                       placeholder="Site 12, North Parking Spot, etc.">
            </label>
        </div>

        <?php require __DIR__ . '/partials/place-report/form.php'; ?>
        <div class="contribution-actions add-place-submit-bar">
            <button class="contribution-submit" type="submit" <?= $ready ? '' : 'disabled' ?>>
                <?= llama_icon('send') ?> Submit Site for Review
            </button>
            <a href="/map.php">Cancel</a>
        </div>
    </form>
</section>
<script src="<?= $e($siteUrl . '/js/add-site-location.js?v='
    . (int) filemtime(__DIR__ . '/js/add-site-location.js')) ?>" defer></script>
<script src="<?= $e($siteUrl . '/js/place-report-form.js?v='
    . (int) filemtime(__DIR__ . '/js/place-report-form.js')) ?>" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
