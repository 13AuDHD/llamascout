<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/admin-places.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser =
    moderation_require_admin();

$db = db();

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$placeId =
    (int) (
        $_GET['id']
        ?? $_POST['place_id']
        ?? 0
    );

if ($placeId < 1) {
    header('Location: /places.php');
    exit;
}

function admin_place_shared_report_data(
    PDO $db,
    int $placeId
): array {
    $place =
        admin_place_get(
            $db,
            $placeId
        );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $place['amenities'] =
        admin_place_row(
            $db,
            'place_amenities',
            $placeId
        );

    $place['connectivity'] =
        admin_place_row(
            $db,
            'place_connectivity',
            $placeId
        );

    $place['details'] =
        admin_place_row(
            $db,
            'place_details',
            $placeId
        );

    $place['rules'] =
        admin_place_row(
            $db,
            'place_rules',
            $placeId
        );

    $place['experience'] =
        admin_place_row(
            $db,
            'place_experience',
            $placeId
        );

    $place['sensory'] = [
        'daytime' =>
            admin_place_sensory_period(
                $db,
                $placeId,
                'daytime'
            ),
        'nighttime' =>
            admin_place_sensory_period(
                $db,
                $placeId,
                'nighttime'
            ),
    ];

    $place['sensory_details'] =
        admin_place_row(
            $db,
            'place_sensory_details',
            $placeId
        );

    return
        llama_place_report_data_from_published_place(
            $place,
            llama_place_report_published_answer_state(
                $db,
                $placeId
            )
        );
}


function admin_place_save_shared_report(
    PDO $db,
    int $actorUserId,
    int $placeId,
    array $input
): array {
    $place =
        admin_place_get(
            $db,
            $placeId
        );

    if (!$place) {
        throw new RuntimeException(
            'Place not found.'
        );
    }

    $baseData =
        admin_place_shared_report_data(
            $db,
            $placeId
        );

    $reportData =
        llama_place_report_build_data(
            $input,
            $baseData
        );

    $coreData = [
        'name' =>
            $reportData['name']
            ?? $place['name']
            ?? '',
        'type' =>
            $reportData['type']
            ?? $place['type']
            ?? 'other',
        'slug' =>
            trim(
                (string) (
                    $input['admin_slug']
                    ?? $place['slug']
                    ?? ''
                )
            ),
        'source_type' =>
            trim(
                (string) (
                    $input['admin_source_type']
                    ?? $place['source_type']
                    ?? 'llama-scouted'
                )
            ),
        'description' =>
            $reportData['description']
            ?? null,
        'public_summary' =>
            trim(
                (string) (
                    $input['admin_public_summary']
                    ?? $place['public_summary']
                    ?? ''
                )
            ),
        'public_location_label' =>
            trim(
                (string) (
                    $input['admin_public_location_label']
                    ?? $place['public_location_label']
                    ?? ''
                )
            ),
        'latitude' =>
            $reportData['latitude']
            ?? null,
        'longitude' =>
            $reportData['longitude']
            ?? null,
        'public_latitude' =>
            $place['public_latitude']
            ?? null,
        'public_longitude' =>
            $place['public_longitude']
            ?? null,
        'elevation_feet' =>
            $reportData['elevation_feet']
            ?? null,
        'road' =>
            $reportData['road']
            ?? null,
        'city' =>
            $reportData['city']
            ?? null,
        'county' =>
            $reportData['county']
            ?? null,
        'state' =>
            $reportData['state']
            ?? null,
        'region' =>
            $reportData['region']
            ?? null,
        'land_manager' =>
            $reportData['land_manager']
            ?? null,
        'land_type' =>
            $reportData['land_type']
            ?? null,
        'sensory_summary' =>
            $reportData['sensory_summary']
            ?? null,
        'access_summary' =>
            $reportData['access_summary']
            ?? null,
    ];

    $amenities =
        is_array(
            $reportData['amenities']
            ?? null
        )
            ? $reportData['amenities']
            : [];

    $connectivity =
        is_array(
            $reportData['connectivity']
            ?? null
        )
            ? $reportData['connectivity']
            : [];

    $details =
        is_array(
            $reportData['details']
            ?? null
        )
            ? $reportData['details']
            : [];

    $rules =
        is_array(
            $reportData['rules']
            ?? null
        )
            ? $reportData['rules']
            : [];

    $experience =
        is_array(
            $reportData['experience']
            ?? null
        )
            ? $reportData['experience']
            : [];

    $sensory =
        is_array(
            $reportData['sensory']
            ?? null
        )
            ? $reportData['sensory']
            : [];

    $sensoryPayload =
        is_array(
            $sensory['details']
            ?? null
        )
            ? $sensory['details']
            : [];

    foreach (
        ['daytime', 'nighttime']
        as $period
    ) {
        $periodData =
            is_array(
                $sensory[$period]
                ?? null
            )
                ? $sensory[$period]
                : [];

        foreach (
            [
                'noise',
                'traffic',
                'crowds',
                'privacy',
                'light_pollution',
                'sensory_comfort',
                'social_interaction_likelihood',
            ]
            as $field
        ) {
            $sensoryPayload[
                $period . '_' . $field
            ] =
                $periodData[$field]
                ?? null;
        }
    }

    $db->beginTransaction();

    try {
        admin_place_save_core(
            $db,
            $actorUserId,
            $placeId,
            $coreData
        );

        admin_place_save_amenities(
            $db,
            $actorUserId,
            $placeId,
            $amenities
        );

        admin_place_save_connectivity(
            $db,
            $actorUserId,
            $placeId,
            $connectivity
        );

        admin_place_save_details(
            $db,
            $actorUserId,
            $placeId,
            $details
        );

        admin_place_save_sensory_details(
            $db,
            $actorUserId,
            $placeId,
            $sensoryPayload
        );

        admin_place_save_rules(
            $db,
            $actorUserId,
            $placeId,
            $rules
        );

        admin_place_save_experience(
            $db,
            $actorUserId,
            $placeId,
            $experience
        );

        llama_place_report_publish_answer_state(
            $db,
            $placeId,
            $reportData
        );

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.shared_report_updated',
            'Updated the shared Place Report.',
            [
                'place_id' =>
                    $placeId,
            ]
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return $reportData;
}


function admin_place_photo_url(
    mixed $photo
): string {
    if (is_array($photo)) {
        $src =
            trim(
                (string) (
                    $photo['src']
                    ?? $photo['path']
                    ?? ''
                )
            );
    } else {
        $src =
            trim(
                (string) $photo
            );
    }

    if ($src === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $src
        )
    ) {
        return $src;
    }

    return
        'https://llamascout.com/'
        . ltrim(
            $src,
            '/'
        );
}


$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                (string) (
                    $_POST['place_admin_action']
                    ?? ''
                );

            if ($action === 'save-report') {

                admin_place_save_shared_report(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $removePaths =
                    is_array(
                        $_POST['remove_existing_photos']
                        ?? null
                    )
                        ? array_values(
                            array_unique(
                                array_filter(
                                    array_map(
                                        'strval',
                                        $_POST['remove_existing_photos']
                                    )
                                )
                            )
                        )
                        : [];

                if ($removePaths) {
                    foreach (
                        admin_place_images(
                            $db,
                            $placeId
                        )
                        as $image
                    ) {
                        if (
                            in_array(
                                (string) ($image['src'] ?? ''),
                                $removePaths,
                                true
                            )
                        ) {
                            admin_place_delete_image(
                                $db,
                                $actorUserId,
                                $placeId,
                                (int) $image['id']
                            );
                        }
                    }
                }

                $photoToken =
                    trim(
                        (string) (
                            $_POST['photo_stage_token']
                            ?? ''
                        )
                    );

                $newPhotos =
                    llama_photo_decode_form_photos(
                        $_POST['photos_json']
                        ?? '[]'
                    );

                if ($newPhotos) {
                    if ($photoToken === '') {
                        throw new RuntimeException(
                            'The photo upload session is missing. Upload the photos again.'
                        );
                    }

                    admin_place_add_photos(
                        $db,
                        $actorUserId,
                        $placeId,
                        $photoToken,
                        $newPhotos
                    );
                }

                $notice =
                    'Place Report updated.';

            } elseif ($action === 'change-status') {

                admin_place_change_status(
                    $db,
                    $actorUserId,
                    $placeId,
                    (string) (
                        $_POST['status']
                        ?? ''
                    ),
                    (string) (
                        $_POST['status_reason']
                        ?? ''
                    )
                );

                $notice =
                    'Place status updated.';

            } elseif ($action === 'add-verification') {

                admin_place_add_verification(
                    $db,
                    $actorUserId,
                    $placeId,
                    $_POST
                );

                $notice =
                    'Verification added.';

            } elseif ($action === 'featured-image') {

                admin_place_set_featured_image(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                $notice =
                    'Featured image updated.';

            } elseif ($action === 'delete-image') {

                admin_place_delete_image(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    )
                );

                $notice =
                    'Place image deleted.';

            } elseif ($action === 'save-image-meta') {

                admin_place_save_image_metadata(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['image_id']
                        ?? 0
                    ),
                    (string) (
                        $_POST['alt_text']
                        ?? ''
                    ),
                    (int) (
                        $_POST['sort_order']
                        ?? 0
                    )
                );

                $notice =
                    'Photo caption and order updated.';

            } elseif ($action === 'add-note') {

                admin_place_add_note(
                    $db,
                    $actorUserId,
                    $placeId,
                    (string) (
                        $_POST['note']
                        ?? ''
                    )
                );

                $notice =
                    'Place note added.';

            } elseif ($action === 'delete-note') {

                admin_place_delete_note(
                    $db,
                    $actorUserId,
                    $placeId,
                    (int) (
                        $_POST['note_id']
                        ?? 0
                    )
                );

                $notice =
                    'Place note deleted.';
            }

        } catch (Throwable $exception) {
            $error =
                $exception->getMessage();
        }
    }
}


$place =
    admin_place_get(
        $db,
        $placeId
    );

if (!$place) {
    header('Location: /places.php');
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
        30 - count($images)
    );

$reportData =
    admin_place_shared_report_data(
        $db,
        $placeId
    );

$stats =
    admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
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
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );

$placeReportValues =
    (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && ($action ?? '') === 'save-report'
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
    false;

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
            . min(10, $remainingPhotos)
            . ' new photos in this batch.'
        : 'This Place already has the maximum of 30 photos.';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/pages/add-place.css"
>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/features/place-report-form.css"
>

<style>
.admin-place-shared-page {
    display: grid;
    gap: 20px;
}

.admin-place-summary,
.admin-place-operations-strip,
.admin-place-section-nav,
.admin-place-admin-meta,
.admin-place-report-shell,
.admin-place-admin-section {
    border: 1px solid var(--border);
    border-radius: 12px;
    background: var(--surface);
}

.admin-place-summary {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: center;
    padding: 20px 22px;
}

.admin-place-summary-main {
    min-width: 0;
    display: grid;
    gap: 7px;
}

.admin-place-summary-heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.admin-place-summary-heading p,
.admin-place-summary-heading h2 {
    margin: 0;
}

.admin-place-summary-heading p {
    color: var(--text-muted);
    font-size: .7rem;
    font-weight: 850;
    text-transform: uppercase;
}

.admin-status-pill {
    padding: 5px 9px;
    border: 1px solid var(--border);
    border-radius: 999px;
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 800;
}

.admin-place-summary-location {
    color: var(--text-muted);
}

.admin-place-summary-actions {
    flex: 0 0 auto;
}

.admin-place-operations-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1px;
    overflow: hidden;
}

.admin-place-operations-strip > div {
    min-width: 0;
    display: grid;
    gap: 4px;
    padding: 14px 16px;
    background: var(--background);
}

.admin-place-operations-strip span {
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 800;
}

.admin-place-operations-strip strong {
    font-size: .82rem;
}

.admin-place-operations-strip .is-good {
    color: #55ad70;
}

.admin-place-section-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 18px;
    padding: 14px 18px;
}

.admin-place-section-nav a {
    color: var(--text-muted);
    font-size: .72rem;
    font-weight: 800;
    text-decoration: none;
}

.admin-place-section-nav a:hover,
.admin-place-section-nav a:focus-visible {
    color: var(--text);
}

.admin-place-admin-meta,
.admin-place-report-shell,
.admin-place-admin-section {
    padding: 20px 22px;
}

.admin-place-admin-section > header,
.admin-place-admin-meta > header,
.admin-place-report-header {
    display: grid;
    gap: 4px;
    margin-bottom: 16px;
}

.admin-place-admin-section > header p,
.admin-place-admin-meta > header p,
.admin-place-report-header p {
    margin: 0;
    color: var(--text-muted);
    font-size: .68rem;
    font-weight: 850;
    text-transform: uppercase;
    letter-spacing: .08em;
}

.admin-place-admin-section > header h2,
.admin-place-admin-meta > header h2,
.admin-place-report-header h2 {
    margin: 0;
}

.admin-place-meta-grid,
.admin-place-status-grid,
.admin-place-verification-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.admin-place-meta-grid label,
.admin-place-status-grid label,
.admin-place-verification-grid label,
.admin-place-note-form label {
    min-width: 0;
    display: grid;
    gap: 6px;
}

.admin-place-meta-grid label > span,
.admin-place-status-grid label > span,
.admin-place-verification-grid label > span,
.admin-place-note-form label > span {
    font-size: .72rem;
    font-weight: 800;
}

.admin-place-meta-grid input,
.admin-place-meta-grid select,
.admin-place-meta-grid textarea,
.admin-place-status-grid input,
.admin-place-status-grid select,
.admin-place-status-grid textarea,
.admin-place-verification-grid input,
.admin-place-verification-grid select,
.admin-place-verification-grid textarea,
.admin-place-note-form textarea {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--background);
    color: var(--text);
    font: inherit;
}

.admin-place-meta-wide,
.admin-place-verification-wide,
.admin-place-status-wide {
    grid-column: 1 / -1;
}

.admin-place-report-form {
    display: grid;
    gap: 14px;
}

.admin-place-report-form .contribution-section {
    background: var(--background);
}

.admin-place-report-form .contribution-section-body {
    background: var(--surface);
}

.admin-place-report-form label:has(input[name="visited_at"]) {
    display: none;
}

.admin-place-report-savebar {
    position: sticky;
    bottom: 12px;
    z-index: 50;
    display: flex;
    justify-content: flex-end;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 11px;
    background: color-mix(in srgb, var(--surface) 94%, transparent);
    backdrop-filter: blur(10px);
}

.admin-place-photo-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.admin-place-photo-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--background);
}

.admin-place-photo-card img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    display: block;
}

.admin-place-photo-card-body {
    display: grid;
    gap: 9px;
    padding: 10px;
}

.admin-place-photo-card-body form {
    display: grid;
    gap: 8px;
}

.admin-place-photo-card-body input {
    width: 100%;
    box-sizing: border-box;
}

.admin-place-photo-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.admin-place-note-list,
.admin-place-history-list,
.admin-place-verification-list {
    display: grid;
    gap: 9px;
}

.admin-place-note-card,
.admin-place-history-card,
.admin-place-verification-card {
    display: grid;
    gap: 5px;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--background);
}

.admin-place-note-card header,
.admin-place-history-card header,
.admin-place-verification-card header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px 14px;
}

.admin-place-muted {
    color: var(--text-muted);
    font-size: .7rem;
}

.admin-place-history-group + .admin-place-history-group {
    margin-top: 18px;
}

.admin-place-history-group h3 {
    margin: 0 0 9px;
}

.admin-place-empty {
    padding: 14px;
    border: 1px dashed var(--border);
    border-radius: 9px;
    color: var(--text-muted);
}

@media (max-width: 800px) {
    .admin-place-summary {
        align-items: flex-start;
        flex-direction: column;
    }

    .admin-place-operations-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-place-meta-grid,
    .admin-place-status-grid,
    .admin-place-verification-grid {
        grid-template-columns: 1fr;
    }

    .admin-place-meta-wide,
    .admin-place-verification-wide,
    .admin-place-status-wide {
        grid-column: auto;
    }

    .admin-place-photo-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .admin-place-operations-strip,
    .admin-place-photo-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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

    <section class="admin-place-summary">
        <div class="admin-place-summary-main">
            <div class="admin-place-summary-heading">
                <p>Place #<?= (int) $place['id'] ?></p>

                <h2>
                    <?= $e($place['name']) ?>
                </h2>

                <span class="admin-status-pill">
                    <?= $e(
                        ucfirst(
                            (string) $place['status']
                        )
                    ) ?>
                </span>
            </div>

            <span class="admin-place-summary-location">
                <?= $e(
                    $place['public_location_label']
                    ?: implode(
                        ', ',
                        array_filter(
                            [
                                $place['city']
                                ?? null,
                                $place['state']
                                ?? null,
                            ]
                        )
                    )
                ) ?>
            </span>
        </div>

        <div class="admin-place-summary-actions">
            <?php if (
                in_array(
                    (string) $place['status'],
                    ['active', 'featured'],
                    true
                )
            ): ?>
                <a
                    class="admin-button is-muted"
                    href="https://llamascout.com/place.php?slug=<?= rawurlencode(
                        (string) $place['slug']
                    ) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    View public Place
                </a>
            <?php endif; ?>
        </div>
    </section>


    <section
        class="admin-place-operations-strip"
        aria-label="Place operational summary"
    >
        <div>
            <span>Scout status</span>

            <strong class="<?= !empty($llamaScouted['ever_scouted']) ? 'is-good' : '' ?>">
                <i
                    class="fa-solid <?= !empty($llamaScouted['ever_scouted']) ? 'fa-binoculars' : 'fa-circle-minus' ?>"
                    aria-hidden="true"
                ></i>

                <?= !empty($llamaScouted['ever_scouted'])
                    ? 'Llama Scouted'
                    : 'Not yet Llama Scouted' ?>
            </strong>
        </div>

        <div>
            <span>Contributions</span>
            <strong><?= number_format((int) $operationalCounts['contributions']) ?></strong>
        </div>

        <div>
            <span>Pending updates</span>
            <strong><?= number_format((int) $operationalCounts['pending_updates']) ?></strong>
        </div>

        <div>
            <span>Open reports</span>
            <strong><?= number_format((int) $operationalCounts['open_reports']) ?></strong>
        </div>

        <div>
            <span>Photos</span>
            <strong><?= number_format(count($images)) ?></strong>
        </div>

        <div>
            <span>Verifications</span>
            <strong><?= number_format((int) $operationalCounts['verifications']) ?></strong>
        </div>
    </section>


    <nav class="admin-place-section-nav">
        <a href="#place-report">Place Report</a>
        <a href="#photos">Photos</a>
        <a href="#notes">Notes</a>
        <a href="#verification">Verification</a>
        <a href="#status">Status</a>
        <a href="#history">History</a>
    </nav>


    <form
        method="post"
        class="admin-place-report-form place-report-form"
        id="place-report"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= $e(
                moderation_csrf_token()
            ) ?>"
        >

        <input
            type="hidden"
            name="place_id"
            value="<?= $placeId ?>"
        >

        <input
            type="hidden"
            name="place_admin_action"
            value="save-report"
        >

        <input
            type="hidden"
            name="photo_stage_token"
            value="<?= $e(
                (string) (
                    $_POST['photo_stage_token']
                    ?? ''
                )
            ) ?>"
        >

        <input
            type="hidden"
            name="photos_json"
            value="<?= $e(
                (string) (
                    $_POST['photos_json']
                    ?? '[]'
                )
            ) ?>"
        >

        <section class="admin-place-admin-meta">
            <header>
                <p>Admin metadata</p>
                <h2>Publishing + URL</h2>
            </header>

            <div class="admin-place-meta-grid">
                <label>
                    <span>URL slug</span>

                    <input
                        type="text"
                        name="admin_slug"
                        value="<?= $e(
                            $place['slug']
                            ?? ''
                        ) ?>"
                        required
                    >
                </label>

                <label>
                    <span>Record source</span>

                    <select name="admin_source_type">
                        <?php foreach (
                            [
                                'llama-scouted' => 'Llama Scouted',
                                'community-scouted' => 'Community Scouted',
                                'external' => 'External source',
                                'legacy' => 'Legacy',
                            ]
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= $e($value) ?>"
                                <?= (string) ($place['source_type'] ?? '') === $value
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="admin-place-meta-wide">
                    <span>Public summary / metadata note</span>

                    <textarea
                        name="admin_public_summary"
                        rows="3"
                    ><?= $e(
                        $place['public_summary']
                        ?? ''
                    ) ?></textarea>
                </label>

                <label class="admin-place-meta-wide">
                    <span>Public location label</span>

                    <input
                        type="text"
                        name="admin_public_location_label"
                        value="<?= $e(
                            $place['public_location_label']
                            ?? ''
                        ) ?>"
                        placeholder="Pagosa Springs, Colorado"
                    >
                </label>
            </div>
        </section>


        <section class="admin-place-report-shell">
            <header class="admin-place-report-header">
                <p>Shared Place Report</p>
                <h2>Edit Place Report</h2>

                <span class="admin-place-muted">
                    This is the same question set and control system used by Add Place and moderation.
                </span>
            </header>

            <?php
            require dirname(__DIR__)
                . '/partials/place-report/form.php';
            ?>
        </section>


        <div class="admin-place-report-savebar">
            <button
                class="admin-button"
                type="submit"
            >
                <i
                    class="fa-solid fa-floppy-disk"
                    aria-hidden="true"
                ></i>
                Save Place Report
            </button>
        </div>
    </form>


    <section
        class="admin-place-admin-section"
        id="photos"
    >
        <header>
            <p>Media</p>
            <h2>Photo management</h2>
        </header>

        <?php if ($images): ?>
            <div class="admin-place-photo-grid">
                <?php foreach ($images as $image): ?>
                    <?php
                    $imageUrl =
                        admin_place_photo_url(
                            $image
                        );
                    ?>

                    <article class="admin-place-photo-card">
                        <?php if ($imageUrl !== ''): ?>
                            <img
                                src="<?= $e($imageUrl) ?>"
                                alt="<?= $e(
                                    $image['alt_text']
                                    ?? ''
                                ) ?>"
                            >
                        <?php endif; ?>

                        <div class="admin-place-photo-card-body">
                            <?php if (
                                (int) (
                                    $image['is_featured']
                                    ?? 0
                                ) === 1
                            ): ?>
                                <strong>
                                    Featured image
                                </strong>
                            <?php endif; ?>

                            <form method="post">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= $e(
                                        moderation_csrf_token()
                                    ) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="place_id"
                                    value="<?= $placeId ?>"
                                >

                                <input
                                    type="hidden"
                                    name="place_admin_action"
                                    value="save-image-meta"
                                >

                                <input
                                    type="hidden"
                                    name="image_id"
                                    value="<?= (int) $image['id'] ?>"
                                >

                                <label>
                                    <span>Alt text / caption</span>

                                    <input
                                        type="text"
                                        name="alt_text"
                                        value="<?= $e(
                                            $image['alt_text']
                                            ?? ''
                                        ) ?>"
                                    >
                                </label>

                                <label>
                                    <span>Sort order</span>

                                    <input
                                        type="number"
                                        name="sort_order"
                                        min="0"
                                        max="999"
                                        value="<?= (int) (
                                            $image['sort_order']
                                            ?? 0
                                        ) ?>"
                                    >
                                </label>

                                <button
                                    class="admin-button is-muted"
                                    type="submit"
                                >
                                    Save photo details
                                </button>
                            </form>

                            <div class="admin-place-photo-actions">
                                <?php if (
                                    (int) (
                                        $image['is_featured']
                                        ?? 0
                                    ) !== 1
                                ): ?>
                                    <form method="post">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= $e(
                                                moderation_csrf_token()
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="place_id"
                                            value="<?= $placeId ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="place_admin_action"
                                            value="featured-image"
                                        >

                                        <input
                                            type="hidden"
                                            name="image_id"
                                            value="<?= (int) $image['id'] ?>"
                                        >

                                        <button
                                            class="admin-button is-muted"
                                            type="submit"
                                        >
                                            Make featured
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= $e(
                                            moderation_csrf_token()
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="place_id"
                                        value="<?= $placeId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="place_admin_action"
                                        value="delete-image"
                                    >

                                    <input
                                        type="hidden"
                                        name="image_id"
                                        value="<?= (int) $image['id'] ?>"
                                    >

                                    <button
                                        class="admin-button is-danger"
                                        type="submit"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="admin-place-empty">
                No photos are attached to this Place.
            </div>
        <?php endif; ?>

        <p class="admin-place-muted">
            New photos can be added from the Photos section inside the shared Place Report above.
        </p>
    </section>


    <section
        class="admin-place-admin-section"
        id="notes"
    >
        <header>
            <p>Internal</p>
            <h2>Place Notes</h2>
        </header>

        <?php if ($placeNotes): ?>
            <div class="admin-place-note-list">
                <?php foreach ($placeNotes as $note): ?>
                    <article class="admin-place-note-card">
                        <header>
                            <strong>
                                <?= $e(
                                    $note['author_name']
                                    ?? 'System'
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $note['created_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <div>
                            <?= nl2br(
                                $e(
                                    $note['note']
                                    ?? ''
                                )
                            ) ?>
                        </div>

                        <form method="post">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= $e(
                                    moderation_csrf_token()
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="place_id"
                                value="<?= $placeId ?>"
                            >

                            <input
                                type="hidden"
                                name="place_admin_action"
                                value="delete-note"
                            >

                            <input
                                type="hidden"
                                name="note_id"
                                value="<?= (int) $note['id'] ?>"
                            >

                            <button
                                class="admin-button is-danger"
                                type="submit"
                            >
                                Delete note
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            class="admin-place-note-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="place_admin_action"
                value="add-note"
            >

            <label>
                <span>Add internal note</span>

                <textarea
                    name="note"
                    rows="4"
                    maxlength="2000"
                ></textarea>
            </label>

            <button
                class="admin-button"
                type="submit"
            >
                Add note
            </button>
        </form>
    </section>


    <section
        class="admin-place-admin-section"
        id="verification"
    >
        <header>
            <p>Verification</p>
            <h2>Field + source verification</h2>
        </header>

        <?php if ($verifications): ?>
            <div class="admin-place-verification-list">
                <?php foreach ($verifications as $verification): ?>
                    <article class="admin-place-verification-card">
                        <header>
                            <strong>
                                <?= $e(
                                    $verification['verification_type']
                                    ?? 'Verification'
                                ) ?>
                            </strong>

                            <span class="admin-place-muted">
                                <?= $e(
                                    $verification['verified_at']
                                    ?? ''
                                ) ?>
                            </span>
                        </header>

                        <span class="admin-place-muted">
                            By
                            <?= $e(
                                $verification['verifier_name']
                                ?? 'System'
                            ) ?>
                        </span>

                        <?php if (!empty($verification['notes'])): ?>
                            <div>
                                <?= nl2br(
                                    $e(
                                        $verification['notes']
                                    )
                                ) ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="place_admin_action"
                value="add-verification"
            >

            <div class="admin-place-verification-grid">
                <label>
                    <span>Verification type</span>

                    <select
                        name="verification_type"
                        required
                    >
                        <option value="">
                            Select...
                        </option>

                        <option value="field-verified">
                            Field verified
                        </option>

                        <option value="source-verified">
                            Source verified
                        </option>

                        <option value="community-confirmed">
                            Community confirmed
                        </option>
                    </select>
                </label>

                <label>
                    <span>Date visited</span>

                    <input
                        type="date"
                        name="visited_at"
                    >
                </label>

                <label>
                    <span>Source</span>

                    <input
                        type="text"
                        name="source"
                        placeholder="Llama Scouted, USFS, BLM..."
                    >
                </label>

                <label>
                    <span>Public data verified</span>

                    <span>
                        <input
                            type="checkbox"
                            name="public_data_verified"
                            value="1"
                        >
                        Yes
                    </span>
                </label>

                <label class="admin-place-verification-wide">
                    <span>Notes</span>

                    <textarea
                        name="notes"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <button
                class="admin-button"
                type="submit"
            >
                Add verification
            </button>
        </form>
    </section>


    <section
        class="admin-place-admin-section"
        id="status"
    >
        <header>
            <p>Publishing</p>
            <h2>Status</h2>
        </header>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= $e(
                    moderation_csrf_token()
                ) ?>"
            >

            <input
                type="hidden"
                name="place_id"
                value="<?= $placeId ?>"
            >

            <input
                type="hidden"
                name="place_admin_action"
                value="change-status"
            >

            <div class="admin-place-status-grid">
                <label>
                    <span>Status</span>

                    <select name="status">
                        <?php foreach (
                            [
                                'draft',
                                'active',
                                'featured',
                                'unlisted',
                                'removed',
                                'archived',
                            ]
                            as $status
                        ): ?>
                            <option
                                value="<?= $e($status) ?>"
                                <?= (string) $place['status'] === $status
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $e(
                                    ucwords(
                                        str_replace(
                                            '-',
                                            ' ',
                                            $status
                                        )
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="admin-place-status-wide">
                    <span>
                        Reason for status change
                    </span>

                    <textarea
                        name="status_reason"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <button
                class="admin-button"
                type="submit"
            >
                Update status
            </button>
        </form>
    </section>


    <section
        class="admin-place-admin-section"
        id="history"
    >
        <header>
            <p>Record</p>
            <h2>History + Provenance</h2>
        </header>

        <?php if ($provenance): ?>
            <div class="admin-place-history-group">
                <h3>Origin</h3>

                <article class="admin-place-history-card">
                    <strong>
                        <?= $e(
                            $provenance['origin_type']
                            ?? 'Unknown'
                        ) ?>
                    </strong>

                    <?php if (!empty($provenance['contributor_name'])): ?>
                        <span>
                            Original contributor:
                            <?= $e(
                                $provenance['contributor_name']
                            ) ?>
                        </span>
                    <?php endif; ?>

                    <span class="admin-place-muted">
                        Established
                        <?= $e(
                            $provenance['established_at']
                            ?? ''
                        ) ?>
                    </span>
                </article>
            </div>
        <?php endif; ?>


        <div class="admin-place-history-group">
            <h3>Status timeline</h3>

            <?php if ($statusHistory): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($statusHistory as $entry): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        ($entry['old_status'] ?? 'New')
                                        . ' â '
                                        . ($entry['new_status'] ?? '')
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $entry['changed_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                By
                                <?= $e(
                                    $entry['changed_by_name']
                                    ?? 'System'
                                ) ?>
                            </span>

                            <?php if (!empty($entry['reason'])): ?>
                                <div>
                                    <?= nl2br(
                                        $e(
                                            $entry['reason']
                                        )
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No status history.
                </div>
            <?php endif; ?>
        </div>


        <div class="admin-place-history-group">
            <h3>
                Contributions
                (<?= count($contributions) ?>)
            </h3>

            <?php if ($contributions): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($contributions as $contribution): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        $contribution['contribution_type']
                                        ?? 'Contribution'
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $contribution['approved_at']
                                        ?? $contribution['created_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $contribution['contributor_name']
                                    ?? 'Former Llama Scout Member'
                                ) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No contribution history.
                </div>
            <?php endif; ?>
        </div>


        <div class="admin-place-history-group">
            <h3>
                Update submissions
                (<?= count($updateHistory) ?>)
            </h3>

            <?php if ($updateHistory): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($updateHistory as $update): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        ucfirst(
                                            (string) (
                                                $update['status']
                                                ?? 'pending'
                                            )
                                        )
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $update['submitted_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $update['contributor_name']
                                    ?? 'Former Llama Scout Member'
                                ) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No Place Update history.
                </div>
            <?php endif; ?>
        </div>


        <div class="admin-place-history-group">
            <h3>
                Reports
                (<?= count($reportHistory) ?>)
            </h3>

            <?php if ($reportHistory): ?>
                <div class="admin-place-history-list">
                    <?php foreach ($reportHistory as $report): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        ucfirst(
                                            (string) (
                                                $report['status']
                                                ?? 'open'
                                            )
                                        )
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $report['created_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $report['reporter_name']
                                    ?? 'Former Llama Scout Member'
                                ) ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="admin-place-empty">
                    No reports.
                </div>
            <?php endif; ?>
        </div>


        <?php if ($placeAuditHistory): ?>
            <div class="admin-place-history-group">
                <h3>
                    Admin audit
                    (<?= count($placeAuditHistory) ?>)
                </h3>

                <div class="admin-place-history-list">
                    <?php foreach ($placeAuditHistory as $audit): ?>
                        <article class="admin-place-history-card">
                            <header>
                                <strong>
                                    <?= $e(
                                        $audit['action']
                                        ?? 'Admin action'
                                    ) ?>
                                </strong>

                                <span class="admin-place-muted">
                                    <?= $e(
                                        $audit['created_at']
                                        ?? ''
                                    ) ?>
                                </span>
                            </header>

                            <span>
                                <?= $e(
                                    $audit['actor_name']
                                    ?? 'System'
                                ) ?>
                            </span>

                            <?php if (!empty($audit['summary'])): ?>
                                <div>
                                    <?= $e(
                                        $audit['summary']
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

</div>


<script src="https://llamascout.com/js/add-place-location.js"></script>
<script src="https://llamascout.com/js/place-report-form.js"></script>

<?php
require __DIR__
    . '/_footer.php';
?>
