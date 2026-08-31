<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function place_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
    <div class="scout-report-item">
        <?php if ($icon): ?>
            <i class="fa-solid <?= place_h($icon) ?>" aria-hidden="true"></i>
        <?php endif; ?>
        <span><?= place_h($label) ?></span>
        <strong><?= place_h($value) ?></strong>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$provenance = $place['provenance'] ?? [];

require __DIR__ . '/partials/header.php';
?>

<article class="place-page">

    <header class="place-header">
        <p class="eyebrow">
            <?= place_h(ucwords(str_replace('-', ' ', (string) $place['type']))) ?>
        </p>

        <h1><?= place_h($place['name']) ?></h1>

        <div class="place-header-actions">
            <?php if ($userId > 0): ?>
                <form method="post" class="place-save-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= place_h(saved_places_csrf_token()) ?>"
                    >
                    <input
                        type="hidden"
                        name="saved_place_action"
                        value="<?= $isSaved ? 'remove' : 'save' ?>"
                    >
                    <button
                        type="submit"
                        class="place-save-button<?= $isSaved ? ' is-saved' : '' ?>"
                        aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                    >
                        <i
                            class="<?= $isSaved ? 'fa-solid' : 'fa-regular' ?> fa-bookmark"
                            aria-hidden="true"
                        ></i>
                        <?= $isSaved ? 'Saved' : 'Save place' ?>
                    </button>
                </form>
            <?php else: ?>
                <a
                    class="place-save-button"
                    href="https://account.llamascout.com/login.php"
                >
                    <i class="fa-regular fa-bookmark" aria-hidden="true"></i>
                    Sign in to save
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($provenance['label'])): ?>
            <?php $isLlamaScouted = ($provenance['status'] ?? '') === 'llama-scouted'; ?>
            <p class="place-provenance-line">
                <span class="place-provenance-badge <?= $isLlamaScouted ? 'is-scouted' : 'is-community' ?>">
                    <i
                        class="fa-solid <?= $isLlamaScouted ? 'fa-binoculars' : 'fa-people-group' ?>"
                        aria-hidden="true"
                    ></i>
                    <?= place_h($provenance['label']) ?>
                </span>
            </p>
        <?php endif; ?>

        <?php if ($locationParts): ?>
            <p class="place-location">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <?= place_h(implode(', ', $locationParts)) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($place['land_manager'])): ?>
            <p class="place-land">
                <?= place_h($place['land_manager']) ?>
                <?php if (!empty($place['land_type'])): ?>
                    Â· <?= place_h($place['land_type']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if ($hasMemberAccess && !empty($place['images'])): ?>
        <section class="place-gallery" aria-label="Place photos">
            <?php foreach ($place['images'] as $image): ?>
                <img
                    src="/<?= place_h(ltrim($image['src'], '/')) ?>"
                    alt="<?= place_h($image['alt_text'] ?: $place['name']) ?>"
                    loading="lazy"
                >
            <?php endforeach; ?>
        </section>
    <?php elseif (!empty($place['featured_image'])): ?>
        <section class="place-hero-image" aria-label="Place photo">
            <img
                src="/<?= place_h(ltrim($place['featured_image']['src'], '/')) ?>"
                alt="<?= place_h($place['featured_image']['alt_text'] ?: $place['name']) ?>"
            >
        </section>
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
            <p class="place-weather-loading">Loading weatherâ¦</p>
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

</article>

<script src="/js/place.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
