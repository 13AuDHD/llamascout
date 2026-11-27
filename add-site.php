<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/site-submissions.php';

require_verified_email();
$userId = (int) (current_user()['id'] ?? 0);
$db = db();
if (!llama_contributor_can($db, $userId, 'submit_place')) {
    http_response_code(403);
    exit('Your account cannot submit new Sites.');
}

$ready = llama_site_submissions_ready($db);
$error = '';
$revisionId = max(0, (int) ($_POST['submission_id'] ?? $_GET['submission'] ?? 0));
$revision = $revisionId > 0
    ? llama_site_submission_for_user($db, $userId, $revisionId) : null;
if ($revisionId > 0 && !$revision) {
    http_response_code(404);
    exit('That Site submission could not be found.');
}
if ($revision && (string) $revision['status'] !== 'needs-changes') {
    header('Location: https://account.llamascout.com/contributions.php', true, 303);
    exit;
}
$isNeedsChanges = $revision !== null;
$selectedPlaceId = $isNeedsChanges
    ? llama_site_submission_parent_reference($revision)
    : (string) ($_POST['site_place_id'] ?? $_GET['site_place_id'] ?? $_GET['place_id'] ?? '');

// The same authenticated Add Site URL supplies on-demand suggestions for
// the Parent Place combobox. Never load the entire Places table in the DOM.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['parent_place_suggestions'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        echo json_encode(
            ['places' => llama_site_parent_place_choices($db, (string) ($_GET['q'] ?? ''), $userId)],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'site.parent_search', ['user_id' => $userId]);
        http_response_code(503);
        echo json_encode(['error' => 'Parent Place search is temporarily unavailable.']);
    }
    exit;
}
// Fetch the selected Place's limited, read-only applicability context.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['parent_report_context'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        $parent = llama_site_parent_reference($db, (string) $_GET['parent_report_context'], $userId);
        echo json_encode(['context' => $parent['context']],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
    } catch (InvalidArgumentException $exception) {
        http_response_code(404);
        echo json_encode(['error' => 'Parent Place not available']);
    }
    exit;
}
$selectedParentPlace = null;
if ($selectedPlaceId !== '' && $selectedPlaceId !== '0') {
    try {
        $selectedParentPlace = llama_site_parent_reference($db, $selectedPlaceId, $userId);
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
    }
}
// The preview uses the entered Site point, not device or Parent Place GPS.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['nearest_services'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        require_once __DIR__ . '/app/nearest-services.php';
        $result = llama_nearest_services($db, $_GET['lat'] ?? null, $_GET['lng'] ?? null,
            (int) ($selectedParentPlace['place_id'] ?? 0));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'site.nearest_services', ['user_id' => $userId]);
        http_response_code(503);
        echo json_encode(['state' => 'unavailable']);
    }
    exit;
}
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
            'site_place_id' => $isNeedsChanges
                ? (string) ($_POST['site_place_id'] ?? $selectedPlaceId)
                : $selectedPlaceId,
            'site_name' => $_POST['site_name'] ?? '',
            'site_coordinates' => $_POST['site_coordinates'] ?? '',
            'site_answers' => $answers,
            'visited_at' => $_POST['visited_at'] ?? '',
        ];
        if ($isNeedsChanges) {
            llama_site_submission_resubmit(
                $db, $userId, $revisionId, $submissionInput,
                (string) ($_POST['photo_stage_token'] ?? ''),
                llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]'),
                (array) ($_POST['remove_existing_photos'] ?? [])
            );
        } else {
            llama_site_submission_submit(
                $db, $userId, $submissionInput,
                (string) ($_POST['photo_stage_token'] ?? ''),
                llama_photo_decode_form_photos($_POST['photos_json'] ?? '[]')
            );
        }
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
$siteNameValue = (string) ($_POST['site_name'] ?? $revision['site_name'] ?? '');
if ($isNeedsChanges && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    foreach ((array) ($revision['data']['answers'] ?? []) as $key => $value) {
        $field = llama_site_submission_fields()[$key] ?? null;
        if (is_array($field)) {
            $placeReportValues[$key] = llama_place_report_answer_form_value($field, $value);
        }
    }
    $coords = $revision['data']['coordinates'] ?? null;
    if (is_array($coords)
        && isset($coords['latitude'], $coords['longitude'])) {
        $placeReportValues['site_coordinates'] = sprintf(
            '%.7f, %.7f', (float) $coords['latitude'], (float) $coords['longitude']
        );
    }
}
$placeReportExistingPhotos = $isNeedsChanges
    ? llama_site_submission_photos((array) $revision['data'], $revisionId) : [];
$placeReportMode = 'contributor';
$placeReportOnlyScope = 'site';
$placeReportContextKeys = ['type', 'amenity_wifi', 'amenity_toilets'];
$placeReportParentContext = $selectedParentPlace['context'] ?? [];
$placeReportSiteIdentityPartial = __DIR__ . '/partials/place-report/site-identity-fields.php';
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
        <h1><?= $isNeedsChanges ? 'Revise Site Submission' : 'Add a Site' ?></h1>
    </header>

    <?php $contributionPickerResourcePage = true; $contributionIsSite = true; ?>
    <?php require __DIR__ . '/partials/place-report/contribution-tabs.php'; ?>

    <?php if (isset($_GET['submitted'])): ?>
        <div class="contribution-message is-success" role="status">
            Site submitted for review. Its parent Place and other Sites have not been changed.
        </div>
    <?php endif; ?>
    <?php if ($isNeedsChanges): ?>
        <div class="contribution-message is-attention" role="status">
            <strong>Changes requested for Site #<?= $revisionId ?></strong>
            <?php if (!empty($revision['review_notes'])): ?>
                <p><?= nl2br($e($revision['review_notes'])) ?></p>
            <?php endif; ?>
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

    <form method="post" class="contribution-form add-place-form place-report-form"
          data-place-report-completion-form id="add-site-main-form"
          data-report-context="<?= $e(json_encode((object) $placeReportParentContext, JSON_THROW_ON_ERROR)) ?>">
        <div class="scout-card-navigation" data-scout-card-navigation hidden>
            <div class="scout-card-toolbar">
                <div class="scout-card-progress" data-scout-card-progress-wrap>
                    <progress data-scout-card-meter max="100" value="0" aria-label="Questionnaire progress"></progress>
                    <span data-scout-card-progress role="status" aria-live="polite">0%</span>
                </div>
                <button class="scout-card-view-link" type="button" data-scout-card-toggle aria-pressed="false">Full questionnaire</button>
            </div>
            <h2 data-scout-card-heading tabindex="-1" class="scout-card-heading"></h2>
        </div>
        <input type="hidden" name="csrf_token" value="<?= $e(community_csrf_token()) ?>">
        <?php if ($isNeedsChanges): ?>
            <input type="hidden" name="submission_id" value="<?= $revisionId ?>">
        <?php endif; ?>
        <input type="hidden" name="photo_stage_token"
               value="<?= $e($_POST['photo_stage_token'] ?? '') ?>">
        <input type="hidden" name="photos_json"
               value="<?= $e($_POST['photos_json'] ?? '[]') ?>">

        <?php require __DIR__ . '/partials/place-report/form.php'; ?>
        <div class="contribution-actions add-place-submit-bar" data-scout-card-step="Review and submit">
            <button class="contribution-submit" type="submit" <?= $ready ? '' : 'disabled' ?>>
                <?= llama_icon('send') ?> <?= $isNeedsChanges ? 'Resubmit Site for Review' : 'Submit Site for Review' ?>
            </button>
            <a href="/map.php">Cancel</a>
        </div>
        <div class="scout-card-controls" data-scout-card-controls hidden>
            <span class="scout-card-gesture" data-scout-card-gesture-help aria-hidden="true">← Swipe →</span>
            <div class="scout-card-buttons">
                <button type="button" data-scout-card-back>Back</button>
                <button type="button" data-scout-card-next>Next</button>
            </div>
        </div>
    </form>
</section>
<script src="<?= $e($siteUrl . '/js/add-site-parent-search.js?v='
    . (int) filemtime(__DIR__ . '/js/add-site-parent-search.js')) ?>" defer></script>
<script src="<?= $e($siteUrl . '/js/add-place-name.js?v='
    . (int) filemtime(__DIR__ . '/js/add-place-name.js')) ?>" defer></script>
<script src="<?= $e($siteUrl . '/js/add-site-location.js?v='
    . (int) filemtime(__DIR__ . '/js/add-site-location.js')) ?>" defer></script>
<script src="<?= $e($siteUrl . '/js/nearest-services.js?v='
    . (int) filemtime(__DIR__ . '/js/nearest-services.js')) ?>" defer></script>
<script src="<?= $e($siteUrl . '/js/place-report-form.js?v='
    . (int) filemtime(__DIR__ . '/js/place-report-form.js')) ?>" defer></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
