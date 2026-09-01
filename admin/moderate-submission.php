<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';

$adminUser = moderation_require_admin();
$db = db();
$csrfToken = moderation_csrf_token();
require_once __DIR__ . '/_dashboard.php';

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Review New Place';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'submissions';

require __DIR__ . '/_header.php';

$submissionId =
    (int) (
        $_GET['id']
        ?? $_POST['id']
        ?? 0
    );

$item =
    moderation_submission(
        $db,
        $submissionId
    );

$error = '';
$notice = '';

if (!$item) {
    http_response_code(404);

    echo '<div class="admin-moderation-notice">Submission not found.</div>';

    require __DIR__ . '/_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !moderation_verify_csrf(
                (string) ($_POST['csrf_token'] ?? '')
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $action =
            (string) ($_POST['action'] ?? '');

        $notes =
            trim(
                (string) (
                    $_POST['review_notes']
                    ?? ''
                )
            );

        $points =
            max(
                0,
                (int) (
                    $_POST['points']
                    ?? 0
                )
            );

        $db->beginTransaction();

        if ($action === 'approve') {
            $status =
                (string) (
                    $_POST['publish_status']
                    ?? 'active'
                );

            $placeId =
                moderation_approve_new_place(
                    $db,
                    $submissionId,
                    (int) $adminUser['id'],
                    $status,
                    $notes,
                    $points
                );

            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                'place.submission_approved',
                'Approved new Place submission #' . $submissionId . '.',
                [
                    'submission_id' => $submissionId,
                    'place_id' => $placeId,
                    'publish_status' => $status,
                    'points_awarded' => $points,
                ]
            );

            $db->commit();

            header(
                'Location: /submissions.php?approved=' .
                $placeId
            );

            exit;
        }

        if (
            in_array(
                $action,
                [
                    'needs-changes',
                    'rejected',
                ],
                true
            )
        ) {
            if ($notes === '') {
                throw new InvalidArgumentException(
                    $action === 'needs-changes'
                        ? 'Add clear review notes explaining what the contributor needs to change.'
                        : 'Add review notes explaining why the submission was not approved.'
                );
            }

            moderation_set_submission_status(
                $db,
                $submissionId,
                (int) $adminUser['id'],
                $action,
                $notes
            );

            $auditAction =
                $action === 'needs-changes'
                    ? 'place.submission_changes_requested'
                    : 'place.submission_rejected';

            $auditSummary =
                $action === 'needs-changes'
                    ? 'Requested changes to new Place submission #' . $submissionId . '.'
                    : 'Rejected new Place submission #' . $submissionId . '.';

            admin_users_audit(
                $db,
                (int) $adminUser['id'],
                (int) $item['user_id'],
                $auditAction,
                $auditSummary,
                [
                    'submission_id' => $submissionId,
                    'review_notes' => $notes,
                ]
            );

            $db->commit();

            header(
                'Location: /submissions.php?updated=1'
            );

            exit;
        }

        throw new InvalidArgumentException(
            'Choose a moderation action.'
        );
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error =
            $exception->getMessage();
    }

    $item =
        moderation_submission(
            $db,
            $submissionId
        );
}

$data = $item['data'];

$photos =
    is_array($data['photos'] ?? null)
        ? $data['photos']
        : [];

function submission_review_label(
    string $key
): string {
    $special = [
        'att' => 'AT&T',
        't_mobile' => 'T-Mobile',
        'rv_suitable' => 'RV suitable',
        'four_wheel_drive_recommended' => '4WD recommended',
        'starlink' => 'Starlink',
        'starlink_tested' => 'Starlink tested',
        'public_data_verified' => 'Public data verified',
        'max_vehicle_length_feet' => 'Maximum vehicle length',
        'elevation_feet' => 'Elevation',
        'stay_limit_days' => 'Stay limit',
        'fee' => 'Fee',
        'current_fire_restrictions_url' => 'Current fire restrictions',
        'social_interaction_likelihood' => 'Social interaction likelihood',
        'sensory_comfort' => 'Sensory comfort',
        'other_cell' => 'Other cellular',
    ];

    if (isset($special[$key])) {
        return $special[$key];
    }

    return ucwords(
        str_replace(
            '_',
            ' ',
            $key
        )
    );
}


function submission_review_has_value(
    mixed $value
): bool {
    return !(
        $value === null
        || $value === ''
    );
}


function submission_review_value(
    mixed $value,
    string $key = '',
    string $type = 'auto'
): string {
    if ($value === null || $value === '') {
        return 'Not provided / Unknown';
    }

    if (is_bool($value)) {
        return $value
            ? 'Yes'
            : 'No';
    }

    if ($type === 'rating') {
        return (int) $value . '/5';
    }

    if ($type === 'feet') {
        return number_format((int) $value) . ' ft';
    }

    if ($type === 'days') {
        return number_format((int) $value) . ' days';
    }

    if ($type === 'money') {
        return '$' . number_format((float) $value, 2);
    }

    return (string) $value;
}


function submission_review_field(
    array $source,
    string $key,
    string $label,
    string $type = 'auto'
): array {
    return [
        'key' => $key,
        'label' => $label,
        'type' => $type,
        'value' => array_key_exists($key, $source)
            ? $source[$key]
            : null,
    ];
}


function submission_review_fields(
    array $source,
    array $definitions
): array {
    $fields = [];

    foreach ($definitions as $definition) {
        [$key, $label] = $definition;
        $type = $definition[2] ?? 'auto';

        $fields[] = submission_review_field(
            $source,
            $key,
            $label,
            $type
        );
    }

    return $fields;
}


function submission_review_section_full(
    string $title,
    string $description,
    array $fields,
    string $icon = 'fa-list-check'
): void {
    $known = 0;
    $unknown = 0;

    foreach ($fields as $field) {
        if (submission_review_has_value($field['value'])) {
            $known++;
        } else {
            $unknown++;
        }
    }
    ?>
    <section class="admin-moderation-detail admin-moderation-review-section">
        <header class="admin-moderation-section-header">
            <div>
                <p class="admin-moderation-eyebrow">
                    <i class="fa-solid <?= moderation_e($icon) ?>" aria-hidden="true"></i>
                    Submission Review
                </p>
                <h2><?= moderation_e($title) ?></h2>
                <p><?= moderation_e($description) ?></p>
            </div>

            <div class="admin-moderation-section-counts" aria-label="Section completeness">
                <span class="is-known"><?= number_format($known) ?> provided</span>
                <?php if ($unknown > 0): ?>
                    <span class="is-unknown"><?= number_format($unknown) ?> unknown</span>
                <?php endif; ?>
            </div>
        </header>

        <div class="admin-moderation-grid admin-moderation-full-grid">
            <?php foreach ($fields as $field): ?>
                <?php
                $hasValue = submission_review_has_value($field['value']);
                $rendered = submission_review_value(
                    $field['value'],
                    (string) $field['key'],
                    (string) $field['type']
                );
                ?>

                <div class="admin-moderation-field <?= $hasValue ? 'is-provided' : 'is-unknown' ?>">
                    <span><?= moderation_e((string) $field['label']) ?></span>
                    <div><?= nl2br(moderation_e($rendered)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}


$coreDefinitions = [
    ['name', 'Place name'],
    ['type', 'Place type'],
    ['visited_at', 'Date visited'],
    ['description', 'Description'],
];

$locationDefinitions = [
    ['latitude', 'Latitude'],
    ['longitude', 'Longitude'],
    ['elevation_feet', 'Elevation', 'feet'],
    ['road', 'Road / access road'],
    ['city', 'City / nearest town'],
    ['county', 'County'],
    ['state', 'State'],
    ['region', 'Region'],
    ['land_manager', 'Land manager'],
    ['land_type', 'Land type'],
];

$siteDefinitions = [
    ['vehicle_capacity', 'Vehicle capacity'],
    ['max_vehicle_length_feet', 'Maximum vehicle length', 'feet'],
    ['tent_camping_suitable', 'Tent camping suitable'],
    ['rv_suitable', 'RV suitable'],
    ['trailer_suitable', 'Trailer suitable'],
    ['parking_surface', 'Parking surface'],
    ['ground_condition', 'Ground condition'],
    ['levelness', 'Levelness', 'rating'],
    ['leveling_required', 'Leveling required'],
    ['turnaround_space', 'Turnaround space'],
    ['pull_through', 'Pull-through'],
    ['back_in', 'Back-in'],
    ['site_open_sky', 'Open sky', 'rating'],
    ['tree_cover', 'Tree cover', 'rating'],
    ['site_shade', 'Site shade', 'rating'],
];

$roadDefinitions = [
    ['road_surface', 'Road surface'],
    ['road_width', 'Road width'],
    ['sedan_accessible', 'Sedan accessible'],
    ['high_clearance_recommended', 'High clearance recommended'],
    ['four_wheel_drive_recommended', '4WD recommended'],
    ['water_crossings', 'Water crossings'],
    ['seasonal_closure', 'Seasonal closure'],
    ['site_access_difficulty', 'Site access difficulty', 'rating'],
    ['road_overall_difficulty', 'Road difficulty', 'rating'],
    ['road_stress', 'Driving stress', 'rating'],
    ['rocks', 'Rocks', 'rating'],
    ['washboards', 'Washboards', 'rating'],
    ['potholes', 'Potholes', 'rating'],
    ['mud_risk', 'Mud risk', 'rating'],
    ['steep_grades', 'Steep grades', 'rating'],
    ['drop_off_exposure', 'Drop-off exposure', 'rating'],
    ['downed_tree_risk', 'Downed tree risk'],
];

$amenityDefinitions = [
    ['toilets', 'Toilets'],
    ['potable_water', 'Potable water'],
    ['trash', 'Trash service'],
    ['fire_ring', 'Fire ring'],
    ['picnic_table', 'Picnic table'],
    ['bear_box', 'Bear box'],
    ['showers', 'Showers'],
    ['electricity', 'Electricity'],
    ['dump_station', 'Dump station'],
    ['food_storage_required', 'Food storage required'],
];

$connectivityDefinitions = [
    ['overall', 'Overall cell service', 'rating'],
    ['t_mobile', 'T-Mobile', 'rating'],
    ['verizon', 'Verizon', 'rating'],
    ['att', 'AT&T', 'rating'],
    ['other_cell', 'Other cellular', 'rating'],
    ['starlink', 'Starlink', 'rating'],
    ['starlink_tested', 'Starlink actually tested'],
    ['starlink_note', 'Starlink note'],
];

$sensoryDefinitions = [
    ['noise', 'Noise', 'rating'],
    ['traffic', 'Traffic', 'rating'],
    ['crowds', 'Crowds', 'rating'],
    ['privacy', 'Privacy', 'rating'],
    ['light_pollution', 'Artificial light / light pollution', 'rating'],
    ['sensory_comfort', 'Sensory comfort', 'rating'],
    ['social_interaction_likelihood', 'Social interaction likelihood', 'rating'],
];

$sensoryDetailDefinitions = [
    ['dust_from_traffic', 'Dust from traffic', 'rating'],
    ['generator_noise', 'Generator noise', 'rating'],
    ['aircraft_noise', 'Aircraft noise', 'rating'],
    ['road_noise', 'Road noise', 'rating'],
    ['human_activity', 'Human activity', 'rating'],
    ['wildlife_noise', 'Wildlife noise', 'rating'],
    ['wind_noise', 'Wind noise', 'rating'],
    ['smoke_risk', 'Smoke risk', 'rating'],
    ['strong_odors', 'Strong odors', 'rating'],
    ['visual_exposure', 'Visual exposure', 'rating'],
    ['predictability', 'Predictability', 'rating'],
];

$environmentDefinitions = [
    ['forest', 'Forest'],
    ['mountains', 'Mountains'],
    ['water_nearby', 'Water nearby'],
    ['water_view', 'Water view'],
    ['mountain_view', 'Mountain view'],
    ['forest_view', 'Forest view'],
    ['wildlife', 'Wildlife'],
    ['bugs', 'Bugs / insects'],
    ['wind_exposure', 'Wind exposure', 'rating'],
    ['sun_exposure', 'Sun exposure', 'rating'],
    ['environment_shade', 'Environmental shade', 'rating'],
    ['environment_open_sky', 'Environmental open sky', 'rating'],
];

$accessibilityDefinitions = [
    ['wheelchair_friendly', 'Wheelchair friendly'],
    ['mobility_device_friendly', 'Mobility device friendly'],
    ['flat_walking_surface', 'Flat walking surface'],
    ['walking_distance_from_vehicle', 'Walking distance from vehicle'],
    ['step_free_access', 'Step-free access'],
    ['accessible_toilet', 'Accessible toilet'],
    ['accessible_picnic_table', 'Accessible picnic table'],
];

$safetyDefinitions = [
    ['felt_safe_daytime', 'Felt safe during daytime'],
    ['felt_safe_nighttime', 'Felt safe at nighttime'],
    ['flash_flood_risk', 'Flash flood risk'],
    ['wildfire_risk', 'Wildfire risk'],
    ['fall_hazard', 'Fall hazard'],
    ['cliff_exposure', 'Cliff exposure'],
    ['rockfall_risk', 'Rockfall risk'],
    ['wildlife_risk', 'Wildlife risk'],
    ['traffic_hazard', 'Traffic hazard'],
    ['emergency_access', 'Emergency access'],
];

$warningDefinitions = [
    ['warning_exposed_to_road', 'Exposed to road'],
    ['warning_zero_privacy', 'Zero privacy'],
    ['warning_passing_vehicle_dust', 'Passing vehicle dust'],
    ['warning_possible_downed_trees', 'Possible downed trees'],
    ['warning_no_tent_camping', 'No tent camping'],
    ['warning_limited_vehicle_length', 'Limited vehicle length'],
    ['warning_leveling_may_be_required', 'Leveling may be required'],
    ['warning_no_amenities', 'No amenities'],
    ['warning_motorized_recreation_traffic', 'Motorized recreation traffic'],
    ['warning_blind_turn_traffic_nearby', 'Blind-turn traffic nearby'],
];

$ruleDefinitions = [
    ['best_months', 'Best months'],
    ['winter_access', 'Winter access'],
    ['snow_risk', 'Snow risk', 'rating'],
    ['mud_season_risk', 'Mud-season risk', 'rating'],
    ['monsoon_risk', 'Monsoon risk', 'rating'],
    ['recommended_travel_season', 'Recommended travel season'],
    ['seasonal_access_note', 'Seasonal access note'],
    ['overnight_camping_allowed', 'Overnight camping allowed'],
    ['dispersed_camping_allowed', 'Dispersed camping allowed'],
    ['stay_limit_days', 'Stay limit', 'days'],
    ['permit_required', 'Permit required'],
    ['fee', 'Fee', 'money'],
    ['campfire_allowed', 'Campfire allowed'],
    ['current_fire_restrictions_url', 'Current fire restrictions URL'],
    ['existing_sites_encouraged', 'Existing sites encouraged'],
    ['pack_it_in_pack_it_out', 'Pack it in / pack it out'],
    ['residential_use_prohibited', 'Residential use prohibited'],
    ['nearest_town', 'Nearest town'],
    ['nearest_fuel', 'Nearest fuel'],
    ['nearest_grocery', 'Nearest grocery'],
    ['nearest_water', 'Nearest water'],
    ['nearest_toilet', 'Nearest toilet'],
    ['nearest_hospital', 'Nearest hospital'],
];

$experienceDefinitions = [
    ['sunrise_view', 'Sunrise view', 'rating'],
    ['sunset_view', 'Sunset view', 'rating'],
    ['mountain_view', 'Mountain view', 'rating'],
    ['forest_view', 'Forest view', 'rating'],
    ['night_sky', 'Night sky', 'rating'],
    ['stargazing', 'Stargazing', 'rating'],
    ['quiet_evening', 'Quiet evening', 'rating'],
    ['overnight_comfort', 'Overnight comfort', 'rating'],
    ['extended_stay_comfort', 'Extended-stay comfort', 'rating'],
    ['sensory_retreat', 'Sensory retreat', 'rating'],
    ['remote_work', 'Remote work', 'rating'],
    ['overall_scenery', 'Overall scenery', 'rating'],
    ['recommended_overnight_stop', 'Recommended overnight stop', 'rating'],
    ['recommended_quiet_evening', 'Recommended quiet evening', 'rating'],
    ['recommended_extended_stay', 'Recommended extended stay', 'rating'],
    ['recommended_sensory_retreat', 'Recommended sensory retreat', 'rating'],
    ['recommended_stargazing', 'Recommended stargazing', 'rating'],
    ['recommended_remote_work', 'Recommended remote work', 'rating'],
    ['recommended_solo_travel', 'Recommended for solo travel'],
    ['recommended_families', 'Recommended for families'],
    ['recommended_large_groups', 'Recommended for large groups'],
    ['not_recommended_for', 'Not recommended for'],
];

$summaryDefinitions = [
    ['access_summary', 'Access summary'],
    ['sensory_summary', 'Sensory summary'],
    ['contributor_notes', 'Contributor notes'],
];

$details = is_array($data['details'] ?? null)
    ? $data['details']
    : [];

$amenities = is_array($data['amenities'] ?? null)
    ? $data['amenities']
    : [];

$connectivity = is_array($data['connectivity'] ?? null)
    ? $data['connectivity']
    : [];

$sensory = is_array($data['sensory'] ?? null)
    ? $data['sensory']
    : [];

$daytimeSensory = is_array($sensory['daytime'] ?? null)
    ? $sensory['daytime']
    : [];

$nighttimeSensory = is_array($sensory['nighttime'] ?? null)
    ? $sensory['nighttime']
    : [];

$sensoryDetails = is_array($sensory['details'] ?? null)
    ? $sensory['details']
    : [];

$rules = is_array($data['rules'] ?? null)
    ? $data['rules']
    : [];

$experience = is_array($data['experience'] ?? null)
    ? $data['experience']
    : [];

$reviewSections = [
    ['Basic Place Information', 'Identity, place type, visit date, and description.', submission_review_fields($data, $coreDefinitions), 'fa-location-dot'],
    ['Location + Land Context', 'Coordinates, elevation, road, nearby community, and land-management context.', submission_review_fields($data, $locationDefinitions), 'fa-location-crosshairs'],
    ['Site + Vehicle Fit', 'Physical site fit for vehicles, tents, RVs, trailers, leveling, sky, trees, and shade.', submission_review_fields($details, $siteDefinitions), 'fa-car-side'],
    ['Road Access', 'Road surface, width, vehicle requirements, difficulty, hazards, and seasonal access.', submission_review_fields($details, $roadDefinitions), 'fa-road'],
    ['Amenities', 'Every amenity captured by the Add Place form. No means the contributor explicitly left that amenity unchecked.', submission_review_fields($amenities, $amenityDefinitions), 'fa-circle-info'],
    ['Connectivity', 'Cell-service ratings, carrier-specific information, and Starlink testing.', submission_review_fields($connectivity, $connectivityDefinitions), 'fa-signal'],
    ['Sensory - Daytime', 'Daytime noise, traffic, crowds, privacy, light, social interaction, and overall sensory comfort.', submission_review_fields($daytimeSensory, $sensoryDefinitions), 'fa-sun'],
    ['Sensory - Nighttime', 'Nighttime noise, traffic, crowds, privacy, light, social interaction, and overall sensory comfort.', submission_review_fields($nighttimeSensory, $sensoryDefinitions), 'fa-moon'],
    ['Specific Sensory Conditions', 'Traffic dust, generators, aircraft, roads, wildlife, wind, smoke, odors, exposure, and predictability.', submission_review_fields($sensoryDetails, $sensoryDetailDefinitions), 'fa-brain'],
    ['Environment', 'Terrain, views, wildlife, insects, weather exposure, shade, and open sky.', submission_review_fields($details, $environmentDefinitions), 'fa-tree'],
    ['Accessibility', 'Mobility access, walking surface and distance, step-free access, and accessible facilities.', submission_review_fields($details, $accessibilityDefinitions), 'fa-universal-access'],
    ['Safety + Hazards', 'Personal safety observations, natural hazards, traffic, and emergency access.', submission_review_fields($details, $safetyDefinitions), 'fa-shield-halved'],
    ['Quick Warnings', 'High-visibility conditions that should be obvious to a visitor before relying on the Place.', submission_review_fields($details, $warningDefinitions), 'fa-triangle-exclamation'],
    ['Seasons, Rules + Nearby Services', 'Seasonal access, camping rules, fees, fire, stay limits, and nearby essentials.', submission_review_fields($rules, $ruleDefinitions), 'fa-cloud-sun'],
    ['Experience + Recommendations', 'Views, comfort, quiet, stars, remote work, and suitability for different visitors.', submission_review_fields($experience, $experienceDefinitions), 'fa-star'],
    ['Narrative Summaries + Contributor Notes', 'Free-form context that can explain ratings, exceptions, and conditions that do not fit a structured field.', submission_review_fields($data, $summaryDefinitions), 'fa-pen'],
];

$allReviewFields = [];

foreach ($reviewSections as $section) {
    foreach ($section[2] as $field) {
        $allReviewFields[] = $field;
    }
}

$totalReviewFields = count($allReviewFields);
$knownReviewFields = 0;

foreach ($allReviewFields as $field) {
    if (submission_review_has_value($field['value'])) {
        $knownReviewFields++;
    }
}

$unknownReviewFields = max(
    0,
    $totalReviewFields - $knownReviewFields
);

$coreReviewChecks = [
    'Place name' => trim((string) ($data['name'] ?? '')) !== '',
    'Place type' => trim((string) ($data['type'] ?? '')) !== '',
    'Visit date' => trim((string) ($data['visited_at'] ?? '')) !== '',
    'Latitude' => $data['latitude'] !== null && $data['latitude'] !== '',
    'Longitude' => $data['longitude'] !== null && $data['longitude'] !== '',
    'Land manager' => trim((string) ($data['land_manager'] ?? '')) !== '',
    'At least one photo' => count($photos) > 0,
];

$coreChecksComplete = count(
    array_filter($coreReviewChecks)
);
?>
?>

<?php if ($error !== ''): ?>
    <div class="admin-moderation-notice">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>


<div class="admin-moderation-detail">
    <h2><?= moderation_e($item['place_name']) ?></h2>

    <p>
        Submitted by
        <strong>
            <?= moderation_e(
                $item['display_name']
                ?: $item['username']
            ) ?>
        </strong>
        on
        <?= moderation_e($item['submitted_at']) ?>.
    </p>
</div>


<section class="admin-moderation-detail admin-moderation-review-readiness">
    <header class="admin-moderation-section-header">
        <div>
            <p class="admin-moderation-eyebrow">
                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                Review Readiness
            </p>
            <h2>New Place Submission Completeness</h2>
            <p>
                This does not automatically approve or reject the Place. It shows what the contributor actually supplied so the moderator can assess the submission without hidden gaps.
            </p>
        </div>
    </header>

    <div class="admin-moderation-readiness-grid">
        <div>
            <span>Structured fields</span>
            <strong><?= number_format($knownReviewFields) ?> / <?= number_format($totalReviewFields) ?></strong>
            <small><?= number_format($unknownReviewFields) ?> unknown or not provided</small>
        </div>

        <div>
            <span>Core review checks</span>
            <strong><?= number_format($coreChecksComplete) ?> / <?= number_format(count($coreReviewChecks)) ?></strong>
            <small>These are review cues, not automatic rejection rules.</small>
        </div>

        <div>
            <span>Submitted photos</span>
            <strong><?= number_format(count($photos)) ?></strong>
            <small>Photos help verify signs, gates, access, hazards, and site fit.</small>
        </div>
    </div>

    <div class="admin-moderation-core-checks">
        <?php foreach ($coreReviewChecks as $label => $met): ?>
            <div class="<?= $met ? 'is-met' : 'is-missing' ?>">
                <i class="fa-solid <?= $met ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" aria-hidden="true"></i>
                <span><?= moderation_e($label) ?></span>
                <strong><?= $met ? 'Provided' : 'Missing' ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>


<?php foreach ($reviewSections as $section): ?>
    <?php
    submission_review_section_full(
        (string) $section[0],
        (string) $section[1],
        $section[2],
        (string) $section[3]
    );
    ?>
<?php endforeach; ?>


<?php if ($photos): ?>
    <div class="admin-moderation-detail">
        <h2>Submitted Photos</h2>

        <div class="admin-moderation-photo-grid">
            <?php foreach ($photos as $photo): ?>
                <?php $src = moderation_photo_path($photo); ?>

                <?php if ($src !== ''): ?>
                    <img
                        src="https://llamascout.com<?= moderation_e($src) ?>"
                        alt="<?= moderation_e($photo['alt'] ?? '') ?>"
                    >
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<div class="admin-moderation-detail">
    <h2>Decision</h2>

    <form
        method="post"
        class="admin-moderation-form"
    >
        <input
            type="hidden"
            name="id"
            value="<?= $submissionId ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e($csrfToken) ?>"
        >

        <label>
            Publish status

            <select name="publish_status">
                <option value="active">
                    Active
                </option>

                <option value="featured">
                    Featured
                </option>
            </select>
        </label>

        <label>
            Contribution points

            <input
                type="number"
                name="points"
                min="0"
                step="1"
                value="0"
            >
        </label>

        <label>
            Review notes

            <textarea
                name="review_notes"
                rows="5"
                placeholder="Required when not approving. Also useful for documenting anything you corrected or verified."
            ></textarea>
        </label>

        <div class="admin-moderation-actions">
            <button
                class="admin-moderation-button is-primary"
                type="submit"
                name="action"
                value="approve"
            >
                Approve and Publish
            </button>

            <button
                class="admin-moderation-button is-warning"
                type="submit"
                name="action"
                value="needs-changes"
            >
                Request Changes
            </button>

            <button
                class="admin-moderation-button is-danger"
                type="submit"
                name="action"
                value="rejected"
            >
                Not Approved
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
