<?php
declare(strict_types=1);

// Render the actual contribution partial, not a hand-written approximation.
require_once dirname(__DIR__, 2) . '/app/icons.php';
require_once dirname(__DIR__, 2) . '/app/place-report.php';

$scope = $argv[1] ?? 'place';
if (!in_array($scope, ['place','site'], true)) exit(1);
$placeReportMode = 'contributor';
$placeReportOnlyScope = $scope;
$placeReportNewPlaceOnly = $scope === 'place';
$placeReportValues = [];
$placeReportShowLocate = true;
$placeReportShowFieldHelp = true;
$placeReportShowPhotos = true;
$placeReportPhotoContext = 'add-' . $scope;
$placeReportPhotoCsrf = 'fixture';
if ($scope === 'place') {
    $placeReportExternalFieldKeys = ['visited_at'];
} else {
    $placeReportContextKeys = ['type','amenity_wifi','amenity_toilets'];
    $placeReportRequiredFields = ['visited_at'];
    $placeReportSiteIdentityPartial = dirname(__DIR__, 2) . '/partials/place-report/site-identity-fields.php';
    $isNeedsChanges = false;
    $selectedParentPlace = ['id'=>17,'name'=>'Example campground','status'=>'published'];
    $siteNameValue = '';
}
?>
<!doctype html><html><body class="add-place-page">
<form id="add-<?= $scope ?>-main-form" class="place-report-form add-place-form"
    data-report-context='<?= $scope === 'site' ? '{"type":"developed-campground","amenity_wifi":"1"}' : '{}' ?>'>
    <div data-scout-card-navigation hidden>
        <button type="button" data-scout-card-toggle></button>
        <div data-scout-card-progress-wrap><progress data-scout-card-meter max="100"></progress><span data-scout-card-progress></span></div>
        <h2 data-scout-card-heading tabindex="-1"></h2>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/place-report/form.php'; ?>
    <div data-scout-card-step="Review and submit"><button type="submit">Submit</button></div>
    <div data-scout-card-controls hidden><span data-scout-card-gesture-help>← Swipe →</span>
        <div class="scout-card-buttons"><button type="button" data-scout-card-back>Back</button><button type="button" data-scout-card-next>Next</button></div>
    </div>
</form>
</body></html>
