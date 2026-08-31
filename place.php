<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function place_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function place_image_url(?string $src): string
{
    $src = trim((string) $src);
    if ($src === '') return '';
    if (preg_match('~^https?://~i', $src)) return $src;
    return '/' . ltrim($src, '/');
}

function place_yes_no(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int) $value === 1 ? 'Yes' : 'No';
}

function place_report_rating_item(string $label, mixed $value): void
{
    if ($value === null || $value === '') {
        return;
    }

    $rating = (int) $value;

    if ($rating < 1 || $rating > 5) {
        return;
    }
    ?>

    <div class="scout-report-item scout-report-rating-item">

        <div class="scout-rating-content">
            <span><?= place_h($label) ?></span>
            <strong><?= place_h($rating) ?>/5</strong>
        </div>

        <div
            class="scout-rating-dots"
            aria-label="<?= place_h($rating) ?> out of 5"
        >
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span
                    class="scout-rating-dot<?= $i <= $rating ? ' is-filled' : '' ?>"
                    aria-hidden="true"
                ></span>
            <?php endfor; ?>
        </div>

    </div>

    <?php
}


function place_report_item(string $label, mixed $value, ?string $icon = null): void
{
    if ($value === null || $value === '') {
        return;
    }
    ?>
    <div class="scout-report-item scout-report-value-item">

        <div class="scout-report-value-content">
            <span><?= place_h($label) ?></span>
            <strong><?= place_h($value) ?></strong>
        </div>

        <?php if ($icon): ?>
            <i
                class="fa-solid <?= place_h($icon) ?> scout-report-value-icon"
                aria-hidden="true"
            ></i>
        <?php endif; ?>

    </div>
    <?php
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$user = current_user();
$userId = !empty($user['id']) ? (int) $user['id'] : 0;
$hasMemberAccess = user_has_member_access();
$place = null;

if ($slug !== '') {
    $place = $hasMemberAccess
        ? place_member_by_slug($slug)
        : place_public_by_slug($slug);
}

if (!$place) {
    http_response_code(404);
    $pageTitle = 'Place Not Found | Llama Scout';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="place-not-found">
        <h1>Place not found</h1>
        <p>This place is unavailable or has not been published.</p>
        <p><a href="/map.php">Return to the map</a></p>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

$isSaved = $userId > 0
    ? user_has_saved_place($userId, (int) $place['id'])
    : false;

$reportError = null;
$reportSubmitted = isset($_GET['reported']) && $_GET['reported'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['saved_place_action'])) {
        $action = (string) ($_POST['saved_place_action'] ?? '');
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (
            $userId < 1
            || !in_array($action, ['save', 'remove'], true)
            || !saved_places_verify_csrf($csrfToken)
        ) {
            http_response_code(400);
            exit('Invalid request.');
        }

        if ($action === 'save') {
            save_place_for_user(
                $userId,
                (int) $place['id'],
                (string) $place['slug'],
                (string) $place['name']
            );
        } else {
            remove_saved_place_for_user(
                $userId,
                (int) $place['id']
            );
        }

        header(
            'Location: /place.php?slug=' .
            rawurlencode((string) $place['slug']),
            true,
            303
        );
        exit;
    }

    if (isset($_POST['place_report_action'])) {
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');
        $problemType = trim((string) ($_POST['problem_type'] ?? ''));
        $reportDetails = trim((string) ($_POST['report_details'] ?? ''));
        $reportPhotoToken = trim((string) ($_POST['photo_stage_token'] ?? ''));
        $reportPhotos = llama_photo_decode_form_photos(
            $_POST['photos_json'] ?? '[]'
        );

        if ($userId < 1 || !place_report_verify_csrf($csrfToken)) {
            http_response_code(400);
            exit('Invalid request.');
        }

        try {
            submit_place_report(
                $userId,
                (int) $place['id'],
                $problemType,
                $reportDetails,
                $reportPhotoToken,
                $reportPhotos
            );

            header(
                'Location: /place.php?slug=' .
                rawurlencode((string) $place['slug']) .
                '&reported=1#report-place',
                true,
                303
            );
            exit;
        } catch (InvalidArgumentException $exception) {
            $reportError = $exception->getMessage();
        }
    }
}

$pageTitle = $place['name'] . ' | Llama Scout';

$locationParts = array_filter([
    $place['city'] ?? null,
    !empty($place['county']) ? $place['county'] . ' County' : null,
    $place['state'] ?? null,
]);

$amenityLabels = [
    'toilets' => ['fa-restroom', 'Toilets'],
    'potable_water' => ['fa-faucet-drip', 'Potable water'],
    'trash' => ['fa-trash-can', 'Trash'],
    'fire_ring' => ['fa-fire', 'Fire ring'],
    'picnic_table' => ['fa-table-picnic', 'Picnic table'],
    'bear_box' => ['fa-box', 'Bear box'],
    'showers' => ['fa-shower', 'Showers'],
    'electricity' => ['fa-bolt', 'Electricity'],
    'dump_station' => ['fa-truck-droplet', 'Dump station'],
    'food_storage_required' => ['fa-box-archive', 'Food storage required'],
];

$details = $hasMemberAccess ? ($place['details'] ?? []) : [];
$connectivity = $hasMemberAccess ? ($place['connectivity'] ?? []) : [];
$sensory = $hasMemberAccess ? ($place['sensory'] ?? []) : [];
$sensoryDetails = $hasMemberAccess ? ($place['sensory_details'] ?? []) : [];
$rules = $hasMemberAccess ? ($place['rules'] ?? []) : [];
$experience = $hasMemberAccess ? ($place['experience'] ?? []) : [];
$db = db();

$historyProvenance = [];
$recentPlaceActivity = [];

try {
    $stmt = $db->prepare(
        'SELECT pp.origin_type, pp.established_at, pp.original_contributor_id,
                u.username AS contributor_username,
                u.display_name AS contributor_display_name
         FROM place_provenance pp
         LEFT JOIN users u ON u.id = pp.original_contributor_id
         WHERE pp.place_id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $place['id']]);
    $historyProvenance = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $db->prepare(
        'SELECT pc.id, pc.user_id, pc.contribution_type,
                pc.points_awarded, pc.visited_at, pc.approved_at,
                u.username, u.display_name
         FROM place_contributions pc
         LEFT JOIN users u ON u.id = pc.user_id
         WHERE pc.place_id = ?
           AND pc.status = ?
         ORDER BY COALESCE(pc.approved_at, pc.created_at) DESC, pc.id DESC
         LIMIT 8'
    );
    $stmt->execute([(int) $place['id'], 'approved']);
    $recentPlaceActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('Llama Scout public Place history error: ' . $exception->getMessage());
}

$galleryImages = [];
$heroImage = null;

if ($hasMemberAccess && !empty($place['images'])) {
    $galleryImages = array_values(array_filter(
        $place['images'],
        static fn(array $image): bool =>
            trim((string) ($image['src'] ?? '')) !== ''
    ));

    foreach ($galleryImages as $image) {
        if (!empty($image['is_featured'])) {
            $heroImage = $image;
            break;
        }
    }

    $heroImage ??= $galleryImages[0] ?? null;
} elseif (!empty($place['featured_image'])) {
    $heroImage = $place['featured_image'];
}

require __DIR__ . '/partials/header.php';
?>

<article class="place-page">

    <header class="place-detail-hero<?= $heroImage ? ' has-image' : ' no-image' ?>">

        <?php if ($heroImage): ?>
            <img
                class="place-detail-hero-image"
                src="<?= place_h(place_image_url($heroImage['src'] ?? '')) ?>"
                alt="<?= place_h(($heroImage['alt_text'] ?? '') ?: $place['name']) ?>"
            >
        <?php endif; ?>

        <div class="place-detail-hero-shade" aria-hidden="true"></div>

        <div class="place-detail-hero-inner">

            <a class="place-detail-back" href="/map.php">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Explore Map
            </a>

            <div class="place-detail-hero-content">

                <div class="place-detail-title-block">
                    <p class="place-detail-eyebrow">
                        <?= place_h(ucwords(str_replace(['-', '_'], ' ', (string) $place['type']))) ?>
                    </p>

                    <h1><?= place_h($place['name']) ?></h1>

                    <?php if ($locationParts): ?>
                        <p class="place-detail-location">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <?= place_h(implode(', ', $locationParts)) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($place['land_manager'])): ?>
                        <p class="place-detail-land">
                            <?= place_h($place['land_manager']) ?>
                            <?php if (!empty($place['land_type'])): ?>
                                / <?= place_h($place['land_type']) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="place-detail-actions">
                    <?php if ($userId > 0): ?>
                        <form method="post" class="place-save-form">
                            <input type="hidden" name="csrf_token" value="<?= place_h(saved_places_csrf_token()) ?>">
                            <input type="hidden" name="saved_place_action" value="<?= $isSaved ? 'remove' : 'save' ?>">
                            <button
                                type="submit"
                                class="place-detail-action-button<?= $isSaved ? ' is-saved' : '' ?>"
                                aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                            >
                                <i class="<?= $isSaved ? 'fa-solid' : 'fa-regular' ?> fa-bookmark" aria-hidden="true"></i>
                                <?= $isSaved ? 'Saved' : 'Save Place' ?>
                            </button>
                        </form>

                        <a
                            class="place-detail-action-button"
                            href="https://account.llamascout.com/update-place.php?place_id=<?= (int) $place['id'] ?>"
                        >
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            Suggest Update
                        </a>
                    <?php else: ?>
                        <a class="place-detail-action-button" href="https://account.llamascout.com/login.php">
                            <i class="fa-regular fa-bookmark" aria-hidden="true"></i>
                            Sign in to save
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>

    <?php if ($hasMemberAccess && $galleryImages): ?>
        <section class="place-photo-gallery-section" aria-labelledby="place-photo-gallery-heading">
            <div class="place-detail-container">
                <div class="place-photo-gallery-heading">
                    <div>
                        <p class="place-detail-eyebrow">Photos</p>
                        <h2 id="place-photo-gallery-heading">Photo gallery</h2>
                    </div>
                    <span><?= count($galleryImages) ?> <?= count($galleryImages) === 1 ? 'photo' : 'photos' ?></span>
                </div>

                <div class="place-photo-gallery" data-place-gallery>
                    <?php foreach ($galleryImages as $index => $image): ?>
                        <button
                            class="place-photo-thumb<?= !empty($image['is_featured']) ? ' is-featured' : '' ?>"
                            type="button"
                            data-place-gallery-open="<?= (int) $index ?>"
                            aria-label="Open photo <?= (int) $index + 1 ?> of <?= count($galleryImages) ?>"
                        >
                            <img
                                src="<?= place_h(place_image_url($image['src'] ?? '')) ?>"
                                alt="<?= place_h(($image['alt_text'] ?? '') ?: $place['name']) ?>"
                                loading="<?= $index < 4 ? 'eager' : 'lazy' ?>"
                            >
                            <?php if (!empty($image['is_featured'])): ?>
                                <span class="place-photo-featured-label">Hero</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <p class="place-photo-gallery-help">
                    Tap any photo to view it larger and move through the full gallery.
                </p>
            </div>
        </section>

        <dialog class="place-gallery-lightbox" id="place-gallery-lightbox" aria-label="Place photo viewer">
            <div class="place-gallery-lightbox-inner">
                <div class="place-gallery-lightbox-top">
                    <span id="place-gallery-counter"></span>
                    <button type="button" class="place-gallery-close" id="place-gallery-close" aria-label="Close photo viewer">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="place-gallery-stage">
                    <button type="button" class="place-gallery-arrow is-previous" id="place-gallery-previous" aria-label="Previous photo">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                    </button>

                    <img id="place-gallery-large-image" src="" alt="">

                    <button type="button" class="place-gallery-arrow is-next" id="place-gallery-next" aria-label="Next photo">
                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>

                <p class="place-gallery-caption" id="place-gallery-caption"></p>

                <div class="place-gallery-lightbox-thumbs" id="place-gallery-lightbox-thumbs">
                    <?php foreach ($galleryImages as $index => $image): ?>
                        <button type="button" data-place-gallery-jump="<?= (int) $index ?>" aria-label="View photo <?= (int) $index + 1 ?>">
                            <img src="<?= place_h(place_image_url($image['src'] ?? '')) ?>" alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </dialog>
    <?php endif; ?>

    <section class="place-facts" aria-label="Place details">
        <?php if (!empty($place['elevation_feet'])): ?>
            <div class="place-fact">
                <i class="fa-solid fa-mountain" aria-hidden="true"></i>
                <span>Elevation</span>
                <strong><?= number_format((int) $place['elevation_feet']) ?> ft</strong>
            </div>
        <?php endif; ?>

        <?php if ($hasMemberAccess && !empty($place['road'])): ?>
            <div class="place-fact">
                <i class="fa-solid fa-road" aria-hidden="true"></i>
                <span>Road</span>
                <strong><?= place_h($place['road']) ?></strong>
            </div>
        <?php endif; ?>

        <?php if (
            $hasMemberAccess
            && ($place['latitude'] ?? null) !== null
            && ($place['longitude'] ?? null) !== null
        ): ?>
            <div class="place-fact">
                <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                <span>GPS coordinates</span>
                <strong>
                    <?= place_h($place['latitude']) ?>,
                    <?= place_h($place['longitude']) ?>
                </strong>
            </div>
        <?php endif; ?>
    </section>

    <section
        class="place-section place-weather"
        aria-labelledby="weather-heading"
        data-place-weather
        data-place-slug="<?= place_h($place['slug']) ?>"
    >
        <div class="place-weather-heading">
            <div>
                <p class="eyebrow">Weather</p>
                <h2 id="weather-heading">
                    <?= $hasMemberAccess ? 'Campsite weather' : 'Local weather' ?>
                </h2>
            </div>
            <i class="fa-solid fa-cloud-sun place-weather-heading-icon" aria-hidden="true"></i>
        </div>

        <div
            class="place-weather-content"
            data-place-weather-content
            aria-live="polite"
        >
            <p class="place-weather-loading">Loading weather…</p>
        </div>
    </section>

    <?php if (!empty($place['amenities'])): ?>
        <section class="place-section">
            <h2>Amenities</h2>

            <div class="amenity-grid">
                <?php foreach ($amenityLabels as $key => [$icon, $label]): ?>
                    <?php
                    $value = $place['amenities'][$key] ?? null;
                    if ($value === null) {
                        continue;
                    }
                    ?>
                    <div class="amenity-item <?= $value ? 'is-available' : 'is-unavailable' ?>">
                        <i class="fa-solid <?= place_h($icon) ?>" aria-hidden="true"></i>
                        <span><?= place_h($label) ?></span>
                        <strong><?= $value ? 'Yes' : 'No' ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasMemberAccess): ?>

        <?php if (!empty($place['description'])): ?>
            <section class="place-section">
                <h2>About this place</h2>
                <p><?= nl2br(place_h($place['description'])) ?></p>
            </section>
        <?php endif; ?>

        <section class="scout-report" aria-labelledby="scout-report-heading">
            <header class="scout-report-header">
                <p class="eyebrow">Member details</p>
                <h2 id="scout-report-heading">Scout Report</h2>
            </header>

            <?php if (!empty($details)): ?>
                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-campground" aria-hidden="true"></i>
                        Site &amp; vehicle
                    </h3>

                    <div class="scout-report-grid">
                        <?php place_report_item('Vehicle capacity', $details['vehicle_capacity'] ?? null, 'fa-car-side'); ?>
                        <?php place_report_item(
                            'Maximum vehicle length',
                            isset($details['max_vehicle_length_feet']) && $details['max_vehicle_length_feet'] !== null
                                ? $details['max_vehicle_length_feet'] . ' ft'
                                : null,
                            'fa-ruler-horizontal'
                        ); ?>
                        <?php place_report_item('Tent camping', place_yes_no($details['tent_camping_suitable'] ?? null), 'fa-tent'); ?>
                        <?php place_report_item('RV suitable', place_yes_no($details['rv_suitable'] ?? null), 'fa-caravan'); ?>
                        <?php place_report_item('Trailer suitable', place_yes_no($details['trailer_suitable'] ?? null), 'fa-trailer'); ?>
                        <?php place_report_item('Parking surface', $details['parking_surface'] ?? null, 'fa-square-parking'); ?>
                        <?php place_report_item('Ground condition', $details['ground_condition'] ?? null, 'fa-mountain-sun'); ?>
                        <?php place_report_rating_item('Levelness', $details['levelness'] ?? null); ?>
                        <?php place_report_item('Leveling required', place_yes_no($details['leveling_required'] ?? null), 'fa-scale-balanced'); ?>
                        <?php place_report_item('Turnaround space', place_yes_no($details['turnaround_space'] ?? null), 'fa-rotate'); ?>
                        <?php place_report_item('Pull-through', place_yes_no($details['pull_through'] ?? null), 'fa-arrow-right'); ?>
                        <?php place_report_item('Back-in', place_yes_no($details['back_in'] ?? null), 'fa-arrow-left'); ?>
                        <?php place_report_rating_item('Open sky', $details['site_open_sky'] ?? null); ?>
                        <?php place_report_rating_item('Tree cover', $details['tree_cover'] ?? null); ?>
                        <?php place_report_rating_item('Shade', $details['site_shade'] ?? null); ?>
                    </div>
                </section>

                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-road" aria-hidden="true"></i>
                        Road &amp; access
                    </h3>

                    <?php if (!empty($place['access_summary'])): ?>
                        <p class="scout-report-summary">
                            <?= nl2br(place_h($place['access_summary'])) ?>
                        </p>
                    <?php endif; ?>

                    <div class="scout-report-grid">
                        <?php place_report_rating_item('Site access difficulty', $details['site_access_difficulty'] ?? null); ?>
                        <?php place_report_rating_item('Road difficulty', $details['road_overall_difficulty'] ?? null); ?>
                        <?php place_report_rating_item('Road stress', $details['road_stress'] ?? null); ?>
                        <?php place_report_item('Road surface', $details['road_surface'] ?? null, 'fa-road'); ?>
                        <?php place_report_item('Road width', $details['road_width'] ?? null, 'fa-arrows-left-right'); ?>
                        <?php place_report_item('Sedan accessible', place_yes_no($details['sedan_accessible'] ?? null), 'fa-car'); ?>
                        <?php place_report_item('High clearance recommended', place_yes_no($details['high_clearance_recommended'] ?? null), 'fa-truck-pickup'); ?>
                        <?php place_report_item('4WD recommended', place_yes_no($details['four_wheel_drive_recommended'] ?? null), 'fa-truck-monster'); ?>
                        <?php place_report_rating_item('Rocks', $details['rocks'] ?? null); ?>
                        <?php place_report_rating_item('Washboards', $details['washboards'] ?? null); ?>
                        <?php place_report_rating_item('Potholes', $details['potholes'] ?? null); ?>
                        <?php place_report_rating_item('Mud risk', $details['mud_risk'] ?? null); ?>
                        <?php place_report_rating_item('Steep grades', $details['steep_grades'] ?? null); ?>
                        <?php place_report_rating_item('Drop-off exposure', $details['drop_off_exposure'] ?? null); ?>
                        <?php place_report_item('Water crossings', place_yes_no($details['water_crossings'] ?? null), 'fa-water'); ?>
                        <?php place_report_item('Downed-tree risk', place_yes_no($details['downed_tree_risk'] ?? null), 'fa-tree'); ?>
                        <?php place_report_item('Seasonal closure', place_yes_no($details['seasonal_closure'] ?? null), 'fa-calendar-xmark'); ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($connectivity)): ?>
                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-signal" aria-hidden="true"></i>
                        Connectivity
                    </h3>

                    <div class="scout-report-grid">
                        <?php place_report_rating_item('Overall', $connectivity['overall'] ?? null); ?>
                        <?php place_report_rating_item('T-Mobile', $connectivity['t_mobile'] ?? null); ?>
                        <?php place_report_rating_item('Verizon', $connectivity['verizon'] ?? null); ?>
                        <?php place_report_rating_item('AT&T', $connectivity['att'] ?? null); ?>
                        <?php place_report_rating_item('Other cell', $connectivity['other_cell'] ?? null); ?>
                        <?php place_report_rating_item('Starlink', $connectivity['starlink'] ?? null); ?>
                        <?php place_report_item('Starlink tested', place_yes_no($connectivity['starlink_tested'] ?? null), 'fa-satellite'); ?>
                    </div>

                    <?php if (!empty($connectivity['starlink_note'])): ?>
                        <p class="scout-report-note">
                            <strong>Starlink note:</strong>
                            <?= place_h($connectivity['starlink_note']) ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($sensory) || !empty($sensoryDetails) || !empty($place['sensory_summary'])): ?>
                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-ear-listen" aria-hidden="true"></i>
                        Sensory
                    </h3>

                    <?php if (!empty($place['sensory_summary'])): ?>
                        <p class="scout-report-summary">
                            <?= nl2br(place_h($place['sensory_summary'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php foreach (['daytime' => 'Daytime', 'nighttime' => 'Nighttime'] as $periodKey => $periodLabel): ?>
                        <?php if (!empty($sensory[$periodKey])): ?>
                            <div class="scout-report-subsection">
                                <h4><?= place_h($periodLabel) ?></h4>
                                <div class="scout-report-grid">
                                    <?php place_report_rating_item('Noise', $sensory[$periodKey]['noise'] ?? null); ?>
                                    <?php place_report_rating_item('Traffic', $sensory[$periodKey]['traffic'] ?? null); ?>
                                    <?php place_report_rating_item('Crowds', $sensory[$periodKey]['crowds'] ?? null); ?>
                                    <?php place_report_rating_item('Privacy', $sensory[$periodKey]['privacy'] ?? null); ?>
                                    <?php place_report_rating_item('Light pollution', $sensory[$periodKey]['light_pollution'] ?? null); ?>
                                    <?php place_report_rating_item('Sensory comfort', $sensory[$periodKey]['sensory_comfort'] ?? null); ?>
                                    <?php place_report_rating_item('Social interaction', $sensory[$periodKey]['social_interaction_likelihood'] ?? null); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if (!empty($sensoryDetails)): ?>
                        <div class="scout-report-subsection">
                            <h4>Other sensory conditions</h4>
                            <div class="scout-report-grid">
                                <?php place_report_rating_item('Traffic dust', $sensoryDetails['dust_from_traffic'] ?? null); ?>
                                <?php place_report_rating_item('Generator noise', $sensoryDetails['generator_noise'] ?? null); ?>
                                <?php place_report_rating_item('Aircraft noise', $sensoryDetails['aircraft_noise'] ?? null); ?>
                                <?php place_report_rating_item('Road noise', $sensoryDetails['road_noise'] ?? null); ?>
                                <?php place_report_rating_item('Human activity', $sensoryDetails['human_activity'] ?? null); ?>
                                <?php place_report_rating_item('Wildlife noise', $sensoryDetails['wildlife_noise'] ?? null); ?>
                                <?php place_report_rating_item('Wind noise', $sensoryDetails['wind_noise'] ?? null); ?>
                                <?php place_report_rating_item('Smoke risk', $sensoryDetails['smoke_risk'] ?? null); ?>
                                <?php place_report_rating_item('Strong odors', $sensoryDetails['strong_odors'] ?? null); ?>
                                <?php place_report_rating_item('Visual exposure', $sensoryDetails['visual_exposure'] ?? null); ?>
                                <?php place_report_rating_item('Predictability', $sensoryDetails['predictability'] ?? null); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($rules)): ?>
                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                        Season &amp; rules
                    </h3>

                    <?php if (!empty($rules['seasonal_access_note'])): ?>
                        <p class="scout-report-summary">
                            <?= nl2br(place_h($rules['seasonal_access_note'])) ?>
                        </p>
                    <?php endif; ?>

                    <div class="scout-report-grid">
                        <?php place_report_item('Best months', $rules['best_months'] ?? null, 'fa-calendar-check'); ?>
                        <?php place_report_item('Recommended season', $rules['recommended_travel_season'] ?? null, 'fa-leaf'); ?>
                        <?php place_report_item('Winter access', place_yes_no($rules['winter_access'] ?? null), 'fa-snowflake'); ?>
                        <?php place_report_rating_item('Snow risk', $rules['snow_risk'] ?? null); ?>
                        <?php place_report_rating_item('Mud-season risk', $rules['mud_season_risk'] ?? null); ?>
                        <?php place_report_rating_item('Monsoon risk', $rules['monsoon_risk'] ?? null); ?>
                        <?php place_report_item('Overnight camping', place_yes_no($rules['overnight_camping_allowed'] ?? null), 'fa-moon'); ?>
                        <?php place_report_item('Dispersed camping', place_yes_no($rules['dispersed_camping_allowed'] ?? null), 'fa-campground'); ?>
                        <?php place_report_item(
                            'Stay limit',
                            isset($rules['stay_limit_days']) && $rules['stay_limit_days'] !== null
                                ? $rules['stay_limit_days'] . ' days'
                                : null,
                            'fa-calendar-day'
                        ); ?>
                        <?php place_report_item('Permit required', place_yes_no($rules['permit_required'] ?? null), 'fa-file-signature'); ?>
                        <?php place_report_item(
                            'Fee',
                            isset($rules['fee']) && $rules['fee'] !== null
                                ? '$' . number_format((float) $rules['fee'], 2)
                                : null,
                            'fa-dollar-sign'
                        ); ?>
                        <?php place_report_item('Campfire allowed', place_yes_no($rules['campfire_allowed'] ?? null), 'fa-fire'); ?>
                        <?php place_report_item('Pack it in, pack it out', place_yes_no($rules['pack_it_in_pack_it_out'] ?? null), 'fa-trash-arrow-up'); ?>
                        <?php place_report_item('Existing sites encouraged', place_yes_no($rules['existing_sites_encouraged'] ?? null), 'fa-signs-post'); ?>
                        <?php place_report_item('Nearest town', $rules['nearest_town'] ?? null, 'fa-city'); ?>
                        <?php place_report_item('Nearest fuel', $rules['nearest_fuel'] ?? null, 'fa-gas-pump'); ?>
                        <?php place_report_item('Nearest grocery', $rules['nearest_grocery'] ?? null, 'fa-cart-shopping'); ?>
                        <?php place_report_item('Nearest water', $rules['nearest_water'] ?? null, 'fa-faucet-drip'); ?>
                        <?php place_report_item('Nearest toilet', $rules['nearest_toilet'] ?? null, 'fa-restroom'); ?>
                        <?php place_report_item('Nearest hospital', $rules['nearest_hospital'] ?? null, 'fa-hospital'); ?>
                    </div>

                    <?php if (!empty($rules['current_fire_restrictions_url'])): ?>
                        <p class="scout-report-note">
                            <a
                                href="<?= place_h($rules['current_fire_restrictions_url']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Check current fire restrictions
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($place['notes'])): ?>
                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                        Scout notes
                    </h3>

                    <ul class="scout-report-notes-list">
                        <?php foreach ($place['notes'] as $note): ?>
                            <?php if (!empty($note['note'])): ?>
                                <li><?= place_h($note['note']) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if (!empty($experience)): ?>
                <section class="scout-report-section">
                    <h3>
                        <i class="fa-solid fa-binoculars" aria-hidden="true"></i>
                        Experience &amp; recommendations
                    </h3>

                    <div class="scout-report-subsection">
                        <h4>Experience</h4>

                        <div class="scout-report-grid">
                            <?php place_report_rating_item('Sunrise view', $experience['sunrise_view'] ?? null); ?>
                            <?php place_report_rating_item('Sunset view', $experience['sunset_view'] ?? null); ?>
                            <?php place_report_rating_item('Mountain view', $experience['mountain_view'] ?? null); ?>
                            <?php place_report_rating_item('Forest view', $experience['forest_view'] ?? null); ?>
                            <?php place_report_rating_item('Night sky', $experience['night_sky'] ?? null); ?>
                            <?php place_report_rating_item('Stargazing', $experience['stargazing'] ?? null); ?>
                            <?php place_report_rating_item('Quiet evening', $experience['quiet_evening'] ?? null); ?>
                            <?php place_report_rating_item('Overnight comfort', $experience['overnight_comfort'] ?? null); ?>
                            <?php place_report_rating_item('Extended stay comfort', $experience['extended_stay_comfort'] ?? null); ?>
                            <?php place_report_rating_item('Sensory retreat', $experience['sensory_retreat'] ?? null); ?>
                            <?php place_report_rating_item('Remote work', $experience['remote_work'] ?? null); ?>
                            <?php place_report_rating_item('Overall scenery', $experience['overall_scenery'] ?? null); ?>
                        </div>
                    </div>

                    <div class="scout-report-subsection">
                        <h4>Recommended for</h4>

                        <div class="scout-report-grid">
                            <?php place_report_rating_item('Overnight stop', $experience['recommended_overnight_stop'] ?? null); ?>
                            <?php place_report_rating_item('Quiet evening', $experience['recommended_quiet_evening'] ?? null); ?>
                            <?php place_report_rating_item('Extended stay', $experience['recommended_extended_stay'] ?? null); ?>
                            <?php place_report_rating_item('Sensory retreat', $experience['recommended_sensory_retreat'] ?? null); ?>
                            <?php place_report_rating_item('Stargazing', $experience['recommended_stargazing'] ?? null); ?>
                            <?php place_report_rating_item('Remote work', $experience['recommended_remote_work'] ?? null); ?>
                            <?php place_report_item('Solo travel', place_yes_no($experience['recommended_solo_travel'] ?? null), 'fa-person-hiking'); ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </section>

    <?php else: ?>
        <section class="member-preview">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            <div>
                <h2>Scout Report</h2>
                <p>
                    Members can see the exact location, full photo gallery,
                    access information, sensory details, connectivity, and
                    detailed site information.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <section class="place-history-section">
        <div class="place-detail-container">

            <div class="place-history-heading">
                <p class="place-detail-eyebrow">Place history</p>
                <h2>Who helped document this Place</h2>
                <p>
                    Scout status, provenance, and recent approved activity live at
                    the bottom so the main page stays focused on planning.
                </p>
            </div>

            <?php if ($historyProvenance): ?>
                <?php
                $originType = (string) ($historyProvenance['origin_type'] ?? '');
                $originIsScout = in_array($originType, ['llama-scouted', 'scout', 'admin'], true);
                $originName = trim((string) (
                    $historyProvenance['contributor_display_name']
                    ?: $historyProvenance['contributor_username']
                    ?: ''
                ));
                ?>
                <div class="place-history-origin">
                    <div class="place-history-badge<?= $originIsScout ? ' is-scouted' : '' ?>">
                        <i class="fa-solid <?= $originIsScout ? 'fa-binoculars' : 'fa-people-group' ?>" aria-hidden="true"></i>
                        <div>
                            <span><?= $originIsScout ? 'Llama Scouted' : 'Member contributed' ?></span>
                            <strong>
                                <?= $originIsScout
                                    ? 'This Place has been documented in the field.'
                                    : 'This Place began with a member contribution.'
                                ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ($originName !== ''): ?>
                        <p>
                            Original contributor:
                            <?php if (!empty($historyProvenance['contributor_username'])): ?>
                                <a href="/<?= rawurlencode((string) $historyProvenance['contributor_username']) ?>">
                                    <?= place_h($originName) ?>
                                </a>
                            <?php else: ?>
                                <?= place_h($originName) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($recentPlaceActivity): ?>
                <div class="place-activity-list">
                    <?php foreach ($recentPlaceActivity as $activity): ?>
                        <?php
                        $activityName = trim((string) (
                            $activity['display_name']
                            ?: $activity['username']
                            ?: 'Llama Scout member'
                        ));
                        $activityType = ucwords(str_replace(
                            ['_', '-'],
                            ' ',
                            (string) $activity['contribution_type']
                        ));
                        ?>
                        <article class="place-activity-item">
                            <div class="place-activity-icon">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                            </div>

                            <div>
                                <strong>
                                    <?php if (!empty($activity['username'])): ?>
                                        <a href="/<?= rawurlencode((string) $activity['username']) ?>">
                                            <?= place_h($activityName) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= place_h($activityName) ?>
                                    <?php endif; ?>
                                </strong>

                                <span>
                                    <?= place_h($activityType) ?>
                                    <?php if (!empty($activity['approved_at'])): ?>
                                        / <?= place_h(date('M j, Y', strtotime((string) $activity['approved_at']))) ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ((int) ($activity['points_awarded'] ?? 0) > 0): ?>
                                <span class="place-activity-points">+<?= (int) $activity['points_awarded'] ?></span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$historyProvenance): ?>
                <p class="place-history-empty">No approved Place history is available yet.</p>
            <?php endif; ?>

        </div>
    </section>

    <section class="place-report-section" id="report-place">
        <details class="place-report"<?= $reportError !== null ? ' open' : '' ?>>
            <summary>
                <i class="fa-regular fa-flag" aria-hidden="true"></i>
                Report a problem with this place
            </summary>

            <div class="place-report-body">
                <?php if ($reportSubmitted): ?>
                    <div class="place-report-message is-success" role="status">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <p>Thanks. Your report has been submitted for review.</p>
                    </div>
                <?php endif; ?>

                <?php if ($reportError !== null): ?>
                    <div class="place-report-message is-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <p><?= place_h($reportError) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($userId > 0): ?>
                    <p>
                        Tell us what changed or what looks wrong. Reports are reviewed before
                        information on the place is changed.
                    </p>

                    <form method="post" class="place-report-form">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= place_h(place_report_csrf_token()) ?>"
                        >
                        <input type="hidden" name="place_report_action" value="submit">

                        <input
                            type="hidden"
                            name="photo_stage_token"
                            value="<?= place_h((string) ($_POST['photo_stage_token'] ?? '')) ?>"
                        >

                        <input
                            type="hidden"
                            name="photos_json"
                            value="<?= place_h((string) ($_POST['photos_json'] ?? '[]')) ?>"
                        >

                        <label for="problem-type">What is the problem?</label>
                        <select id="problem-type" name="problem_type" required>
                            <option value="">Choose one</option>
                            <?php foreach (place_report_problem_types() as $value => $label): ?>
                                <option
                                    value="<?= place_h($value) ?>"
                                    <?= isset($problemType) && $problemType === $value ? 'selected' : '' ?>
                                >
                                    <?= place_h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="report-details">What should we know?</label>
                        <textarea
                            id="report-details"
                            name="report_details"
                            rows="5"
                            maxlength="4000"
                            required
                        ><?= place_h($reportDetails ?? '') ?></textarea>

                        <div class="place-report-photo-section">
                            <div
                                data-photo-uploader
                                data-photo-context="place-report"
                                data-photo-max="5"
                                data-photo-csrf="<?= place_h(llama_photo_csrf_token()) ?>"
                                data-photo-endpoint="/photo-upload.php"
                                data-photo-title="Photos of the problem"
                                data-photo-help="Add up to 5 photos showing signs, gates, closures, downed trees, washouts, road damage, obstructions, or anything else that helps document the problem."
                            ></div>
                        </div>

                        <button type="submit" class="place-report-submit">
                            <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                            Submit report
                        </button>
                    </form>
                <?php else: ?>
                    <p>You need to be signed in to submit a place report.</p>
                    <a class="place-report-signin" href="https://account.llamascout.com/login.php">
                        Sign in
                    </a>
                <?php endif; ?>
            </div>
        </details>
    </section>

</article>

<script src="/js/place-gallery.js"></script>
<script src="/js/place.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
