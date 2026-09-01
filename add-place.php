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
            header(
                'Location: https://account.llamascout.com/contributions.php?submitted=new',
                true,
                303
            );
            exit;
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'The place could not be submitted. Please try again.';
        }
    }
}

function add_place_e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function add_place_selected(
    string $name,
    string $value,
    string $default = ''
): string {
    $current = (string) ($_POST[$name] ?? $default);

    return $current === $value
        ? 'selected'
        : '';
}

function add_place_checked(
    string $name
): string {
    return isset($_POST[$name])
        ? 'checked'
        : '';
}

function add_place_yes_no_unknown(
    string $name,
    string $label,
    string $help = ''
): void {
    ?>
    <label class="contribution-field">
        <span><?= add_place_e($label) ?></span>

        <select name="<?= add_place_e($name) ?>">
            <option value="" <?= add_place_selected($name, '') ?>>
                Unknown
            </option>
            <option value="1" <?= add_place_selected($name, '1') ?>>
                Yes
            </option>
            <option value="0" <?= add_place_selected($name, '0') ?>>
                No
            </option>
        </select>

        <?php if ($help !== ''): ?>
            <small><?= add_place_e($help) ?></small>
        <?php endif; ?>
    </label>
    <?php
}

function add_place_rating(
    string $name,
    string $label,
    string $low = 'Low',
    string $high = 'High'
): void {
    ?>
    <label class="contribution-field">
        <span><?= add_place_e($label) ?></span>

        <select name="<?= add_place_e($name) ?>">
            <option value="" <?= add_place_selected($name, '') ?>>
                Unknown
            </option>

            <?php for ($i = 1; $i <= 5; $i++): ?>
                <option
                    value="<?= $i ?>"
                    <?= add_place_selected($name, (string) $i) ?>
                >
                    <?= $i ?>/5
                    <?= $i === 1 ? ' - ' . add_place_e($low) : '' ?>
                    <?= $i === 5 ? ' - ' . add_place_e($high) : '' ?>
                </option>
            <?php endfor; ?>
        </select>
    </label>
    <?php
}

$landManagers = [
    '' => 'Unknown / not sure',
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

$landTypes = [
    '' => 'Unknown / not sure',
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

$pageTitle = 'Add a Place | Llama Scout';
require __DIR__ . '/partials/header.php';
?>

<section class="contribution-page add-place-page">

    <header class="contribution-header">
        <p class="eyebrow">Community contribution</p>
        <h1>Add a Place</h1>
        <p>
            Share what you actually observed. The form is long because outdoor
            places are complicated, but most questions are quick selections.
            Unknown is always better than guessing.
        </p>

        <div class="add-place-form-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>
                Nothing publishes automatically. A moderator reviews the full
                submission, photos, and location data before it becomes a Place.
            </span>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="contribution-message is-error" role="alert">
            <?= add_place_e($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="contribution-form add-place-form">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= add_place_e(community_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="photo_stage_token"
            value="<?= add_place_e((string) ($_POST['photo_stage_token'] ?? '')) ?>"
        >
        <input
            type="hidden"
            name="photos_json"
            value="<?= add_place_e((string) ($_POST['photos_json'] ?? '[]')) ?>"
        >

        <details class="contribution-section" open>
            <summary>
                <span>
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    Basic information
                </span>
                <small>Name, type, and when you visited</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field contribution-field-wide add-place-name-field">
                        <span>Place name *</span>

                        <div class="add-place-name-control">
                            <input
                                name="name"
                                data-place-name
                                required
                                maxlength="200"
                                value="<?= add_place_e((string) ($_POST['name'] ?? '')) ?>"
                                placeholder="A suggested name will appear here"
                            >

                            <button
                                class="add-place-name-refresh"
                                type="button"
                                data-refresh-place-name
                                aria-label="Suggest another Place name"
                                title="Suggest another name"
                            >
                                <i
                                    class="fa-solid fa-arrows-rotate"
                                    aria-hidden="true"
                                ></i>

                                <span>Another name</span>
                            </button>
                        </div>

                        <small>
                            We suggest simple location-neutral names so the title
                            does not accidentally reveal a road, landmark, or exact
                            location. You can still edit the suggestion.
                        </small>
                    </label>

                    <label class="contribution-field">
                        <span>Place type</span>
                        <select name="type">
                            <?php foreach (community_place_types() as $value => $label): ?>
                                <option
                                    value="<?= add_place_e($value) ?>"
                                    <?= add_place_selected('type', $value, 'dispersed-camping') ?>
                                >
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Date visited</span>
                        <input
                            type="date"
                            name="visited_at"
                            value="<?= add_place_e((string) ($_POST['visited_at'] ?? '')) ?>"
                        >
                    </label>

                    <label class="contribution-field contribution-field-wide">
                        <span>Description</span>
                        <textarea
                            name="description"
                            rows="5"
                            placeholder="What is this place, what is it like, and why would someone use it?"
                        ><?= add_place_e((string) ($_POST['description'] ?? '')) ?></textarea>
                    </label>
                </div>
            </div>
        </details>


        <details class="contribution-section" open>
            <summary>
                <span>
                    <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                    Location
                </span>
                <small>GPS, elevation, road, town, county, state, and land</small>
            </summary>

            <div class="contribution-section-body">
                <div class="add-place-locate-panel">
                    <div>
                        <strong>At the Place right now?</strong>
                        <span>
                            Use your device location to fill GPS coordinates,
                            elevation, road, city, county, and state.
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

                <div class="contribution-grid">
                    <label class="contribution-field">
                        <span>Latitude</span>
                        <input
                            inputmode="decimal"
                            name="latitude"
                            data-location-field="latitude"
                            value="<?= add_place_e((string) ($_POST['latitude'] ?? '')) ?>"
                            placeholder="37.25222"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Longitude</span>
                        <input
                            inputmode="decimal"
                            name="longitude"
                            data-location-field="longitude"
                            value="<?= add_place_e((string) ($_POST['longitude'] ?? '')) ?>"
                            placeholder="-107.2192"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Elevation (ft)</span>
                        <input
                            inputmode="numeric"
                            name="elevation_feet"
                            data-location-field="elevation_feet"
                            value="<?= add_place_e((string) ($_POST['elevation_feet'] ?? '')) ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Road</span>
                        <input
                            name="road"
                            data-location-field="road"
                            value="<?= add_place_e((string) ($_POST['road'] ?? '')) ?>"
                            placeholder="Forest Road 622"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Nearest city / locality</span>
                        <input
                            name="city"
                            data-location-field="city"
                            value="<?= add_place_e((string) ($_POST['city'] ?? '')) ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>County</span>
                        <input
                            name="county"
                            data-location-field="county"
                            value="<?= add_place_e((string) ($_POST['county'] ?? '')) ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>State</span>
                        <input
                            name="state"
                            data-location-field="state"
                            value="<?= add_place_e((string) ($_POST['state'] ?? '')) ?>"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Region / ranger district</span>
                        <input
                            name="region"
                            value="<?= add_place_e((string) ($_POST['region'] ?? '')) ?>"
                            placeholder="Pagosa Ranger District"
                        >
                    </label>

                    <label class="contribution-field">
                        <span>Land manager</span>
                        <select name="land_manager">
                            <?php foreach ($landManagers as $value => $label): ?>
                                <option
                                    value="<?= add_place_e($value) ?>"
                                    <?= add_place_selected('land_manager', $value) ?>
                                >
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Land type</span>
                        <select name="land_type">
                            <?php foreach ($landTypes as $value => $label): ?>
                                <option
                                    value="<?= add_place_e($value) ?>"
                                    <?= add_place_selected('land_type', $value) ?>
                                >
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-car-side" aria-hidden="true"></i>
                    Site and vehicle fit
                </span>
                <small>Size, parking, tents, RVs, trailers, and leveling</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field">
                        <span>Vehicle capacity</span>
                        <select name="vehicle_capacity">
                            <option value="" <?= add_place_selected('vehicle_capacity', '') ?>>Unknown</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= add_place_selected('vehicle_capacity', (string) $i) ?>>
                                    <?= $i ?> vehicle<?= $i === 1 ? '' : 's' ?>
                                </option>
                            <?php endfor; ?>
                            <option value="11" <?= add_place_selected('vehicle_capacity', '11') ?>>10+ vehicles</option>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Maximum vehicle length</span>
                        <select name="max_vehicle_length_feet">
                            <option value="" <?= add_place_selected('max_vehicle_length_feet', '') ?>>Unknown</option>
                            <?php foreach ([15,20,25,30,35,40,45,50] as $feet): ?>
                                <option value="<?= $feet ?>" <?= add_place_selected('max_vehicle_length_feet', (string) $feet) ?>>
                                    About <?= $feet ?> ft
                                </option>
                            <?php endforeach; ?>
                            <option value="60" <?= add_place_selected('max_vehicle_length_feet', '60') ?>>50+ ft</option>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Parking surface</span>
                        <select name="parking_surface">
                            <?php foreach ([
                                '' => 'Unknown',
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
                            ] as $value => $label): ?>
                                <option value="<?= add_place_e($value) ?>" <?= add_place_selected('parking_surface', $value) ?>>
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Ground condition</span>
                        <select name="ground_condition">
                            <?php foreach ([
                                '' => 'Unknown',
                                'level-firm' => 'Mostly level and firm',
                                'uneven-firm' => 'Uneven but firm',
                                'rocky' => 'Rocky',
                                'soft' => 'Soft / sandy',
                                'mud-prone' => 'Mud-prone',
                                'grass' => 'Grassy',
                                'mixed' => 'Mixed',
                            ] as $value => $label): ?>
                                <option value="<?= add_place_e($value) ?>" <?= add_place_selected('ground_condition', $value) ?>>
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?php add_place_yes_no_unknown('tent_camping_suitable', 'Tent camping suitable?'); ?>
                    <?php add_place_yes_no_unknown('rv_suitable', 'RV suitable?'); ?>
                    <?php add_place_yes_no_unknown('trailer_suitable', 'Trailer suitable?'); ?>
                    <?php add_place_yes_no_unknown('leveling_required', 'Leveling required?'); ?>
                    <?php add_place_yes_no_unknown('turnaround_space', 'Turnaround space?'); ?>
                    <?php add_place_yes_no_unknown('pull_through', 'Pull-through site?'); ?>
                    <?php add_place_yes_no_unknown('back_in', 'Back-in site?'); ?>

                    <?php add_place_rating('levelness', 'Levelness', 'Very uneven', 'Very level'); ?>
                    <?php add_place_rating('site_open_sky', 'Open sky', 'Enclosed', 'Wide open'); ?>
                    <?php add_place_rating('tree_cover', 'Tree cover', 'None', 'Heavy'); ?>
                    <?php add_place_rating('site_shade', 'Shade', 'None', 'Heavy'); ?>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-road" aria-hidden="true"></i>
                    Road access
                </span>
                <small>Surface, width, difficulty, stress, mud, rocks, and obstacles</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field">
                        <span>Road surface</span>
                        <select name="road_surface">
                            <?php foreach ([
                                '' => 'Unknown',
                                'paved' => 'Paved / asphalt',
                                'concrete' => 'Concrete',
                                'graded-gravel' => 'Graded gravel',
                                'loose-gravel' => 'Loose gravel',
                                'hard-packed-dirt' => 'Hard-packed dirt',
                                'dirt' => 'Dirt',
                                'sand' => 'Sand',
                                'rock' => 'Rock / bedrock',
                                'mixed' => 'Mixed surface',
                            ] as $value => $label): ?>
                                <option value="<?= add_place_e($value) ?>" <?= add_place_selected('road_surface', $value) ?>>
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Road width</span>
                        <select name="road_width">
                            <?php foreach ([
                                '' => 'Unknown',
                                'one-lane' => 'One lane',
                                'one-and-half-lane' => 'About 1.5 lanes',
                                'two-lane' => 'Two lane',
                                'wide-two-lane' => 'Wide two lane',
                                'varies' => 'Varies significantly',
                            ] as $value => $label): ?>
                                <option value="<?= add_place_e($value) ?>" <?= add_place_selected('road_width', $value) ?>>
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?php add_place_yes_no_unknown('sedan_accessible', 'Sedan accessible?'); ?>
                    <?php add_place_yes_no_unknown('high_clearance_recommended', 'High clearance recommended?'); ?>
                    <?php add_place_yes_no_unknown('four_wheel_drive_recommended', '4WD recommended?'); ?>
                    <?php add_place_yes_no_unknown('water_crossings', 'Water crossings?'); ?>
                    <?php add_place_yes_no_unknown('downed_tree_risk', 'Downed-tree risk?'); ?>
                    <?php add_place_yes_no_unknown('seasonal_closure', 'Seasonal closure?'); ?>

                    <?php add_place_rating('site_access_difficulty', 'Site access difficulty', 'Easy', 'Very difficult'); ?>
                    <?php add_place_rating('road_overall_difficulty', 'Road difficulty', 'Easy', 'Very difficult'); ?>
                    <?php add_place_rating('road_stress', 'Driving stress', 'Relaxed', 'Very stressful'); ?>
                    <?php add_place_rating('rocks', 'Rocks', 'None', 'Severe'); ?>
                    <?php add_place_rating('washboards', 'Washboards', 'None', 'Severe'); ?>
                    <?php add_place_rating('potholes', 'Potholes', 'None', 'Severe'); ?>
                    <?php add_place_rating('mud_risk', 'Mud risk', 'Low', 'High'); ?>
                    <?php add_place_rating('steep_grades', 'Steep grades', 'None', 'Severe'); ?>
                    <?php add_place_rating('drop_off_exposure', 'Drop-off exposure', 'None', 'Severe'); ?>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Amenities
                </span>
                <small>Check only what is actually available</small>
            </summary>

            <div class="contribution-section-body">
                <p class="contribution-section-help">
                    Unchecked means the amenity was not present when you visited.
                    If you are unsure, mention that in reviewer notes.
                </p>

                <div class="contribution-checkbox-grid">
                    <?php foreach ([
                        'amenity_toilets' => 'Toilets',
                        'amenity_potable_water' => 'Potable water',
                        'amenity_trash' => 'Trash service',
                        'amenity_fire_ring' => 'Fire ring',
                        'amenity_picnic_table' => 'Picnic table',
                        'amenity_bear_box' => 'Bear box',
                        'amenity_showers' => 'Showers',
                        'amenity_electricity' => 'Electricity',
                        'amenity_dump_station' => 'Dump station',
                        'amenity_food_storage_required' => 'Food storage required',
                    ] as $name => $label): ?>
                        <label class="contribution-check">
                            <input
                                type="checkbox"
                                name="<?= add_place_e($name) ?>"
                                value="1"
                                <?= add_place_checked($name) ?>
                            >
                            <span><?= add_place_e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-signal" aria-hidden="true"></i>
                    Connectivity
                </span>
                <small>Cell carriers and Starlink</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <?php add_place_rating('connectivity_overall', 'Overall cell service', 'None', 'Excellent'); ?>
                    <?php add_place_rating('connectivity_t_mobile', 'T-Mobile', 'None', 'Excellent'); ?>
                    <?php add_place_rating('connectivity_verizon', 'Verizon', 'None', 'Excellent'); ?>
                    <?php add_place_rating('connectivity_att', 'AT&T', 'None', 'Excellent'); ?>
                    <?php add_place_rating('connectivity_other_cell', 'Other cellular', 'None', 'Excellent'); ?>
                    <?php add_place_rating('connectivity_starlink', 'Starlink', 'Poor', 'Excellent'); ?>
                    <?php add_place_yes_no_unknown('connectivity_starlink_tested', 'Starlink actually tested?'); ?>

                    <label class="contribution-field contribution-field-wide">
                        <span>Starlink notes</span>
                        <textarea
                            name="connectivity_starlink_note"
                            rows="3"
                            placeholder="Clear northern sky, heavy tree obstruction, not personally tested, etc."
                        ><?= add_place_e((string) ($_POST['connectivity_starlink_note'] ?? '')) ?></textarea>
                    </label>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-brain" aria-hidden="true"></i>
                    Sensory profile
                </span>
                <small>Day, night, noise, traffic, people, smells, and exposure</small>
            </summary>

            <div class="contribution-section-body">
                <h3 class="contribution-subheading">Daytime</h3>
                <div class="contribution-grid">
                    <?php add_place_rating('daytime_noise', 'Noise', 'Very quiet', 'Very loud'); ?>
                    <?php add_place_rating('daytime_traffic', 'Traffic', 'None', 'Heavy'); ?>
                    <?php add_place_rating('daytime_crowds', 'Crowds', 'Empty', 'Crowded'); ?>
                    <?php add_place_rating('daytime_privacy', 'Privacy', 'None', 'Excellent'); ?>
                    <?php add_place_rating('daytime_light_pollution', 'Artificial light', 'None', 'Heavy'); ?>
                    <?php add_place_rating('daytime_sensory_comfort', 'Sensory comfort', 'Difficult', 'Excellent'); ?>
                    <?php add_place_rating('daytime_social_interaction', 'Chance of social interaction', 'Very low', 'Very high'); ?>
                </div>

                <h3 class="contribution-subheading">Nighttime</h3>
                <div class="contribution-grid">
                    <?php add_place_rating('nighttime_noise', 'Noise', 'Very quiet', 'Very loud'); ?>
                    <?php add_place_rating('nighttime_traffic', 'Traffic', 'None', 'Heavy'); ?>
                    <?php add_place_rating('nighttime_crowds', 'Crowds', 'Empty', 'Crowded'); ?>
                    <?php add_place_rating('nighttime_privacy', 'Privacy', 'None', 'Excellent'); ?>
                    <?php add_place_rating('nighttime_light_pollution', 'Light pollution', 'Dark', 'Bright'); ?>
                    <?php add_place_rating('nighttime_sensory_comfort', 'Sensory comfort', 'Difficult', 'Excellent'); ?>
                    <?php add_place_rating('nighttime_social_interaction', 'Chance of social interaction', 'Very low', 'Very high'); ?>
                </div>

                <h3 class="contribution-subheading">Specific sensory conditions</h3>
                <div class="contribution-grid">
                    <?php foreach ([
                        'sensory_dust_from_traffic' => 'Dust from traffic',
                        'sensory_generator_noise' => 'Generator noise',
                        'sensory_aircraft_noise' => 'Aircraft noise',
                        'sensory_road_noise' => 'Road noise',
                        'sensory_human_activity' => 'Human activity',
                        'sensory_wildlife_noise' => 'Wildlife noise',
                        'sensory_wind_noise' => 'Wind noise',
                        'sensory_smoke_risk' => 'Smoke risk',
                        'sensory_strong_odors' => 'Strong odors',
                        'sensory_visual_exposure' => 'Visual exposure',
                        'sensory_predictability' => 'Predictability',
                    ] as $name => $label): ?>
                        <?php add_place_rating($name, $label, 'Low', 'High'); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-tree" aria-hidden="true"></i>
                    Environment and accessibility
                </span>
                <small>Terrain, views, exposure, mobility, and walking distance</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <?php foreach ([
                        'environment_forest' => 'Forest environment?',
                        'environment_mountains' => 'Mountains present?',
                        'environment_water_nearby' => 'Water nearby?',
                        'environment_water_view' => 'Water view?',
                        'environment_mountain_view' => 'Mountain view?',
                        'environment_forest_view' => 'Forest view?',
                        'environment_wildlife' => 'Wildlife common?',
                        'environment_bugs' => 'Bugs significant?',
                        'wheelchair_friendly' => 'Wheelchair friendly?',
                        'mobility_device_friendly' => 'Outdoor mobility device friendly?',
                        'flat_walking_surface' => 'Flat walking surface?',
                        'step_free_access' => 'Step-free access?',
                        'accessible_toilet' => 'Accessible toilet?',
                        'accessible_picnic_table' => 'Accessible picnic table?',
                    ] as $name => $label): ?>
                        <?php add_place_yes_no_unknown($name, $label); ?>
                    <?php endforeach; ?>

                    <?php add_place_rating('environment_wind_exposure', 'Wind exposure', 'Protected', 'Very exposed'); ?>
                    <?php add_place_rating('environment_sun_exposure', 'Sun exposure', 'Low', 'Full sun'); ?>
                    <?php add_place_rating('environment_shade', 'Environment shade', 'None', 'Heavy'); ?>
                    <?php add_place_rating('environment_open_sky', 'Open sky', 'Low', 'Wide open'); ?>

                    <label class="contribution-field">
                        <span>Walking distance from vehicle</span>
                        <select name="walking_distance_from_vehicle">
                            <?php foreach ([
                                '' => 'Unknown',
                                'at-vehicle' => 'At / beside vehicle',
                                'under-50-ft' => 'Under 50 ft',
                                '50-100-ft' => '50-100 ft',
                                '100-250-ft' => '100-250 ft',
                                '250-500-ft' => '250-500 ft',
                                '500-plus-ft' => '500+ ft / short hike',
                            ] as $value => $label): ?>
                                <option value="<?= add_place_e($value) ?>" <?= add_place_selected('walking_distance_from_vehicle', $value) ?>>
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    Safety and warnings
                </span>
                <small>Hazards and conditions people should see quickly</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <?php foreach ([
                        'felt_safe_daytime' => 'Felt safe during the day?',
                        'felt_safe_nighttime' => 'Felt safe at night?',
                        'flash_flood_risk' => 'Flash-flood risk?',
                        'wildfire_risk' => 'Wildfire risk?',
                        'fall_hazard' => 'Fall hazard?',
                        'cliff_exposure' => 'Cliff exposure?',
                        'rockfall_risk' => 'Rockfall risk?',
                        'wildlife_risk' => 'Wildlife risk?',
                        'traffic_hazard' => 'Traffic hazard?',
                        'emergency_access' => 'Emergency vehicle access?',
                        'warning_exposed_to_road' => 'Exposed to road?',
                        'warning_zero_privacy' => 'Zero privacy?',
                        'warning_passing_vehicle_dust' => 'Passing vehicle dust?',
                        'warning_possible_downed_trees' => 'Possible downed trees?',
                        'warning_no_tent_camping' => 'No tent camping?',
                        'warning_limited_vehicle_length' => 'Limited vehicle length?',
                        'warning_leveling_may_be_required' => 'Leveling may be required?',
                        'warning_no_amenities' => 'No amenities?',
                        'warning_motorized_recreation_traffic' => 'Motorized recreation traffic?',
                        'warning_blind_turn_traffic_nearby' => 'Blind-turn traffic nearby?',
                    ] as $name => $label): ?>
                        <?php add_place_yes_no_unknown($name, $label); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-cloud-sun" aria-hidden="true"></i>
                    Seasons, rules, and nearby services
                </span>
                <small>Access seasons, camping rules, fees, fire, fuel, food, and medical care</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field contribution-field-wide">
                        <span>Best months</span>
                        <select name="best_months">
                            <?php foreach ([
                                '' => 'Unknown',
                                'year-round' => 'Year-round',
                                'spring' => 'Spring',
                                'summer' => 'Summer',
                                'fall' => 'Fall',
                                'winter' => 'Winter',
                                'spring-summer' => 'Spring through summer',
                                'summer-fall' => 'Summer through fall',
                                'late-spring-fall' => 'Late spring through fall',
                                'snow-free-months' => 'Generally snow-free months',
                            ] as $value => $label): ?>
                                <option value="<?= add_place_e($value) ?>" <?= add_place_selected('best_months', $value) ?>>
                                    <?= add_place_e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <?php add_place_yes_no_unknown('winter_access', 'Winter access?'); ?>
                    <?php add_place_rating('snow_risk', 'Snow risk', 'Low', 'High'); ?>
                    <?php add_place_rating('mud_season_risk', 'Mud-season risk', 'Low', 'High'); ?>
                    <?php add_place_rating('monsoon_risk', 'Monsoon risk', 'Low', 'High'); ?>

                    <label class="contribution-field contribution-field-wide">
                        <span>Seasonal access notes</span>
                        <textarea
                            name="seasonal_access_note"
                            rows="3"
                            placeholder="Gated in winter, impassable after heavy rain, snow usually lingers until June, etc."
                        ><?= add_place_e((string) ($_POST['seasonal_access_note'] ?? '')) ?></textarea>
                    </label>

                    <?php foreach ([
                        'overnight_camping_allowed' => 'Overnight camping allowed?',
                        'dispersed_camping_allowed' => 'Dispersed camping allowed?',
                        'permit_required' => 'Permit required?',
                        'campfire_allowed' => 'Campfire allowed?',
                        'existing_sites_encouraged' => 'Existing sites encouraged?',
                        'pack_it_in_pack_it_out' => 'Pack it in / pack it out?',
                        'residential_use_prohibited' => 'Residential use prohibited?',
                    ] as $name => $label): ?>
                        <?php add_place_yes_no_unknown($name, $label); ?>
                    <?php endforeach; ?>

                    <label class="contribution-field">
                        <span>Stay limit</span>
                        <select name="stay_limit_days">
                            <option value="" <?= add_place_selected('stay_limit_days', '') ?>>Unknown</option>
                            <?php foreach ([1,3,5,7,10,14,16,21,28] as $days): ?>
                                <option value="<?= $days ?>" <?= add_place_selected('stay_limit_days', (string) $days) ?>>
                                    <?= $days ?> day<?= $days === 1 ? '' : 's' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="contribution-field">
                        <span>Fee</span>
                        <input
                            type="number"
                            min="0"
                            step=".01"
                            name="fee"
                            value="<?= add_place_e((string) ($_POST['fee'] ?? '')) ?>"
                            placeholder="0.00"
                        >
                    </label>

                    <label class="contribution-field contribution-field-wide">
                        <span>Current fire restrictions URL</span>
                        <input
                            type="url"
                            name="current_fire_restrictions_url"
                            value="<?= add_place_e((string) ($_POST['current_fire_restrictions_url'] ?? '')) ?>"
                            placeholder="https://..."
                        >
                    </label>

                    <?php foreach ([
                        'nearest_town' => 'Nearest town',
                        'nearest_fuel' => 'Nearest fuel',
                        'nearest_grocery' => 'Nearest grocery',
                        'nearest_water' => 'Nearest water',
                        'nearest_toilet' => 'Nearest toilet',
                        'nearest_hospital' => 'Nearest hospital / emergency care',
                    ] as $name => $label): ?>
                        <label class="contribution-field">
                            <span><?= add_place_e($label) ?></span>
                            <input
                                name="<?= add_place_e($name) ?>"
                                value="<?= add_place_e((string) ($_POST[$name] ?? '')) ?>"
                            >
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    Experience and recommendations
                </span>
                <small>Views, stars, comfort, quiet, remote work, and who it suits</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <?php foreach ([
                        'experience_sunrise_view' => 'Sunrise view',
                        'experience_sunset_view' => 'Sunset view',
                        'experience_mountain_view' => 'Mountain view',
                        'experience_forest_view' => 'Forest view',
                        'experience_night_sky' => 'Night sky',
                        'experience_stargazing' => 'Stargazing',
                        'experience_quiet_evening' => 'Quiet evening',
                        'experience_overnight_comfort' => 'Overnight comfort',
                        'experience_extended_stay_comfort' => 'Extended-stay comfort',
                        'experience_sensory_retreat' => 'Sensory retreat',
                        'experience_remote_work' => 'Remote work',
                        'experience_overall_scenery' => 'Overall scenery',
                        'recommended_overnight_stop' => 'Recommended overnight stop',
                        'recommended_quiet_evening' => 'Recommended quiet evening',
                        'recommended_extended_stay' => 'Recommended extended stay',
                        'recommended_sensory_retreat' => 'Recommended sensory retreat',
                        'recommended_stargazing' => 'Recommended stargazing',
                        'recommended_remote_work' => 'Recommended remote work',
                    ] as $name => $label): ?>
                        <?php add_place_rating($name, $label, 'Poor', 'Excellent'); ?>
                    <?php endforeach; ?>

                    <?php add_place_yes_no_unknown('recommended_solo_travel', 'Good for solo travel?'); ?>
                    <?php add_place_yes_no_unknown('recommended_families', 'Good for families?'); ?>
                    <?php add_place_yes_no_unknown('recommended_large_groups', 'Good for large groups?'); ?>

                    <label class="contribution-field contribution-field-wide">
                        <span>Not recommended for</span>
                        <textarea
                            name="not_recommended_for"
                            rows="3"
                            placeholder="Example: low-clearance vehicles, people sensitive to road noise, large trailers..."
                        ><?= add_place_e((string) ($_POST['not_recommended_for'] ?? '')) ?></textarea>
                    </label>
                </div>
            </div>
        </details>


        <details class="contribution-section">
            <summary>
                <span>
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                    Summaries and reviewer notes
                </span>
                <small>Useful context that does not fit into a dropdown</small>
            </summary>

            <div class="contribution-section-body">
                <div class="contribution-grid">
                    <label class="contribution-field contribution-field-wide">
                        <span>Access summary</span>
                        <textarea
                            name="access_summary"
                            rows="4"
                            placeholder="Summarize the road, vehicle requirements, turnaround, leveling, and mobility access."
                        ><?= add_place_e((string) ($_POST['access_summary'] ?? '')) ?></textarea>
                    </label>

                    <label class="contribution-field contribution-field-wide">
                        <span>Sensory summary</span>
                        <textarea
                            name="sensory_summary"
                            rows="4"
                            placeholder="Describe the overall sensory experience and any major day/night differences."
                        ><?= add_place_e((string) ($_POST['sensory_summary'] ?? '')) ?></textarea>
                    </label>

                    <label class="contribution-field contribution-field-wide">
                        <span>Notes for the reviewer</span>
                        <textarea
                            name="contributor_notes"
                            rows="4"
                            placeholder="Anything uncertain, unusual, temporary, or important for the moderator to know."
                        ><?= add_place_e((string) ($_POST['contributor_notes'] ?? '')) ?></textarea>
                    </label>
                </div>
            </div>
        </details>


        <details class="contribution-section" open>
            <summary>
                <span>
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                    Photos
                </span>
                <small>Signs, gates, roads, obstructions, the site, and important context</small>
            </summary>

            <div class="contribution-section-body">
                <div
                    data-photo-uploader
                    data-photo-context="add-place"
                    data-photo-max="10"
                    data-photo-csrf="<?= add_place_e(llama_photo_csrf_token()) ?>"
                    data-photo-title="Photos of this Place"
                    data-photo-help="Add up to 10 current photos. Signs, gates, washouts, road conditions, parking areas, and obstructions are especially useful. Location metadata is removed before permanent storage."
                ></div>
            </div>
        </details>


        <div class="contribution-actions add-place-submit-bar">
            <button class="contribution-submit" type="submit">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Submit Place for review
            </button>

            <a href="/map.php">Cancel</a>
        </div>
    </form>
</section>

<script src="<?= add_place_e($siteUrl . '/js/add-place-location.js') ?>"></script>
<script src="<?= add_place_e($siteUrl . '/js/add-place-name.js') ?>"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
