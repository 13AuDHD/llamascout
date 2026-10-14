<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once dirname(__DIR__) . '/app/admin-place-sites.php';
require_once dirname(__DIR__) . '/app/admin-place-report.php';
require_once dirname(__DIR__) . '/app/admin-place-actions.php';
require_once dirname(__DIR__) . '/app/place-verifications.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db =
    db();

$actorUserId =
    (int) (
        $adminUser['id']
        ?? 0
    );

$placeId =
    (int) (
        $_GET['id']
        ?? $_POST['place_id']
        ?? 0
    );

if ($placeId < 1) {
    header(
        'Location: /places.php'
    );

    exit;
}

$actionState =
    admin_place_handle_actions(
        $db,
        $actorUserId,
        $placeId
    );

$notice =
    (string) (
        $actionState['notice']
        ?? ''
    );

$error =
    (string) (
        $actionState['error']
        ?? ''
    );

$action =
    (string) (
        $actionState['action']
        ?? ''
    );

$place =
    admin_place_get(
        $db,
        $placeId
    );

if (!$place) {
    header(
        'Location: /places.php'
    );

    exit;
}

$amenities =
    admin_place_row(
        $db,
        'place_amenities',
        $placeId
    );

$connectivity =
    admin_place_row(
        $db,
        'place_connectivity',
        $placeId
    );

$details =
    admin_place_row(
        $db,
        'place_details',
        $placeId
    );

$sensoryDetails =
    admin_place_row(
        $db,
        'place_sensory_details',
        $placeId
    );

$daytimeSensory =
    admin_place_sensory_period(
        $db,
        $placeId,
        'daytime'
    );

$nighttimeSensory =
    admin_place_sensory_period(
        $db,
        $placeId,
        'nighttime'
    );

$rules =
    admin_place_row(
        $db,
        'place_rules',
        $placeId
    );

$experience =
    admin_place_row(
        $db,
        'place_experience',
        $placeId
    );

$images =
    admin_place_images(
        $db,
        $placeId
    );

$verifications =
    admin_place_verifications(
        $db,
        $placeId
    );

$statusHistory =
    admin_place_status_history(
        $db,
        $placeId
    );

$provenance =
    admin_place_provenance(
        $db,
        $placeId
    );

$contributions =
    admin_place_contributions(
        $db,
        $placeId
    );

$updateHistory =
    admin_place_update_history(
        $db,
        $placeId
    );

$reportHistory =
    admin_place_reports_history(
        $db,
        $placeId
    );

$placeNotes =
    admin_place_notes(
        $db,
        $placeId
    );

$placeAuditHistory =
    admin_place_audit_history(
        $db,
        $placeId
    );

$llamaScouted =
    admin_place_llama_scouted_state(
        $db,
        $placeId
    );

$operationalCounts =
    admin_place_operational_counts(
        $db,
        $placeId
    );

$remainingPhotos =
    max(
        0,
        30 - count(
            $images
        )
    );

$reportData =
    admin_place_shared_report_data(
        $db,
        $placeId
    );

$adminPlaceCampsites =
    admin_place_campsite_form_catalog(
        $db,
        $placeId
    );

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' =>
        $stats['new_places'],

    'updates' =>
        $stats['updates'],

    'reports' =>
        $stats['reports'],

    'orders' =>
        $stats['orders'],

    'scout_reviews' =>
        $stats['scout_reviews'],
];

$adminPageTitle =
    (string) $place['name'];

$adminPageEyebrow =
    'Place Administration';

$adminActiveNav =
    'places';

$adminNeedsPhotoUploader =
    true;

require __DIR__
    . '/_header.php';

$e =
    static fn (
        mixed $value
    ): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

$placeReportValues =
    (
        ($_SERVER['REQUEST_METHOD'] ?? '')
            === 'POST'
        && $action === 'save-report'
        && $error !== ''
    )
        ? $_POST
        : llama_place_report_form_input_from_data(
            $reportData
        );

$placeReportMode =
    'moderator';

$placeReportExistingPhotos =
    $images;

$placeReportShowLocate =
    true;

$placeReportShowNameSuggestion =
    true;

$placeReportPhotoEndpoint =
    '/photo-upload.php';

$placeReportPhotoCsrf =
    llama_photo_csrf_token();

$placeReportPhotoMax =
    max(
        1,
        min(
            10,
            $remainingPhotos > 0
                ? $remainingPhotos
                : 1
        )
    );

$placeReportPhotoTitle =
    'Place photos';

$placeReportPhotoHelp =
    $remainingPhotos > 0
        ? 'Add up to '
            . min(
                10,
                $remainingPhotos
            )
            . ' new photos in this batch.'
        : 'This Place already has the maximum of 30 photos.';
?>

<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= $e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= $e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-place-shared-page">

    <?php
    require __DIR__
        . '/partials/place-summary-report.php';

    require __DIR__
        . '/partials/place-photos-notes.php';

    require __DIR__
        . '/partials/place-verification-status.php';

    require __DIR__
        . '/partials/place-history.php';
    ?>

</div>

<script src="https://llamascout.com/js/add-place-location.js"></script>
<script src="https://llamascout.com/js/add-place-name.js"></script>
<script src="https://llamascout.com/js/place-report-form.js"></script>
<script src="https://llamascout.com/js/admin/place-report-save.js"></script>
<script src="https://llamascout.com/js/admin/place-verifications.js"></script>

<?php
require __DIR__
    . '/_footer.php';
?>
