<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin-users.php';
require_once dirname(__DIR__) . '/app/moderation-submission-editor.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();

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

if (!$item) {
    http_response_code(404);

    $adminPageTitle = 'Submission Not Found';
    $adminPageEyebrow = 'Moderation';
    $adminActiveNav = 'submissions';

    require __DIR__ . '/_header.php';

    echo '<div class="admin-user-notice is-error">Submission not found.</div>';

    require __DIR__ . '/_footer.php';
    exit;
}

if (
    !in_array(
        (string) ($item['status'] ?? ''),
        [
            'pending',
            'needs-changes',
        ],
        true
    )
) {
    header(
        'Location: /moderate-submission.php?id='
        . $submissionId,
        true,
        303
    );
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !moderation_verify_csrf(
                (string) (
                    $_POST['csrf_token']
                    ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'Your session could not be verified. Reload the page and try again.'
            );
        }

        $postedFields =
            is_array(
                $_POST['fields']
                ?? null
            )
                ? $_POST['fields']
                : [];

        $removePhotos =
            is_array(
                $_POST['remove_existing_photos']
                ?? null
            )
                ? $_POST['remove_existing_photos']
                : [];

        $photoToken =
            trim(
                (string) (
                    $_POST['photo_stage_token']
                    ?? ''
                )
            );

        $submittedPhotos =
            llama_photo_decode_form_photos(
                $_POST['photos_json']
                ?? '[]'
            );

        $db->beginTransaction();

        $result =
            moderation_save_submission_edits(
                $db,
                $submissionId,
                (int) $adminUser['id'],
                $postedFields,
                $removePhotos,
                $photoToken,
                $submittedPhotos
            );

        admin_users_audit(
            $db,
            (int) $adminUser['id'],
            (int) ($item['user_id'] ?? 0),
            'place.submission_edited',
            'Edited new Place submission #'
            . $submissionId
            . ' before moderation decision.',
            [
                'submission_id' =>
                    $submissionId,

                'status' =>
                    (string) ($item['status'] ?? ''),

                'field_changes' =>
                    $result['field_changes'],

                'removed_photos' =>
                    $result['removed_photos'],

                'added_photo_count' =>
                    $result['added_photo_count'],
            ]
        );

        $db->commit();

        foreach (
            $result['removed_photos']
            as $path
        ) {
            $absolute =
                dirname(__DIR__)
                . $path;

            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        header(
            'Location: /moderate-submission.php?id='
            . $submissionId
            . '&edited=1',
            true,
            303
        );
        exit;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.edit_submission',
                [
                    'submission_id' =>
                        $submissionId,
                ],
                [
                    InvalidArgumentException::class,
                    RuntimeException::class,
                ]
            );

        $error =
            $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The submission edits could not be saved.',
                    $reference
                );
    }

    $item =
        moderation_submission(
            $db,
            $submissionId
        );
}

$data =
    is_array(
        $item['data']
        ?? null
    )
        ? $item['data']
        : [];

$flat =
    moderation_submission_editor_flatten(
        $data
    );

$photos =
    is_array(
        $data['photos']
        ?? null
    )
        ? $data['photos']
        : [];

$stats =
    admin_dashboard_stats(
        $db
    );

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Edit New Place Submission';
$adminPageEyebrow = 'Moderation';
$adminActiveNav = 'submissions';
$adminNeedsPhotoUploader = true;

$adminPageActions =
    '<a class="admin-button" href="/moderate-submission.php?id='
    . $submissionId
    . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Review</a>';

$csrfToken =
    moderation_csrf_token();

require __DIR__ . '/_header.php';

function moderator_editor_value(
    array $flat,
    string $path
): mixed {
    return array_key_exists($path, $flat)
        ? $flat[$path]
        : null;
}

function moderator_editor_options(
    string $path,
    mixed $value
): void {
    $options =
        moderation_submission_editor_choice_options(
            $path
        );

    if (!$options) {
        return;
    }

    $current =
        $value === null
            ? ''
            : (string) $value;

    if (
        $current !== ''
        && !array_key_exists(
            $current,
            $options
        )
    ) {
        $options =
            [
                $current =>
                    $current . ' (previous entry)',
            ]
            + $options;
    }

    foreach ($options as $optionValue => $label) {
        ?>
        <option
            value="<?= moderation_e((string) $optionValue) ?>"
            <?= $current === (string) $optionValue ? 'selected' : '' ?>
        >
            <?= moderation_e($label) ?>
        </option>
        <?php
    }
}

function moderator_editor_control(
    array $flat,
    string $path,
    string $label,
    string $low = 'Low',
    string $high = 'High'
): void {
    $value =
        moderator_editor_value(
            $flat,
            $path
        );

    $name =
        'fields['
        . $path
        . ']';

    if (
        in_array(
            $path,
            moderation_submission_editor_bool_paths(),
            true
        )
    ) {
        ?>
        <label class="contribution-field">
            <span><?= moderation_e($label) ?></span>

            <select name="<?= moderation_e($name) ?>">
                <option value="" <?= $value === null ? 'selected' : '' ?>>Unknown</option>
                <option value="1" <?= $value === true ? 'selected' : '' ?>>Yes</option>
                <option value="0" <?= $value === false ? 'selected' : '' ?>>No</option>
            </select>
        </label>
        <?php
        return;
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_rating_paths(),
            true
        )
    ) {
        ?>
        <label class="contribution-field">
            <span><?= moderation_e($label) ?></span>

            <select
                name="<?= moderation_e($name) ?>"
                data-rating-low="<?= moderation_e($low) ?>"
                data-rating-high="<?= moderation_e($high) ?>"
            >
                <option value="" <?= $value === null ? 'selected' : '' ?>>Unknown</option>

                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option
                        value="<?= $i ?>"
                        <?= (int) $value === $i ? 'selected' : '' ?>
                    >
                        <?= $i ?>/5<?= $i === 1 ? ' - ' . moderation_e($low) : '' ?><?= $i === 5 ? ' - ' . moderation_e($high) : '' ?>
                    </option>
                <?php endfor; ?>
            </select>
        </label>
        <?php
        return;
    }

    $options =
        moderation_submission_editor_choice_options(
            $path
        );

    if ($options) {
        ?>
        <label class="contribution-field">
            <span><?= moderation_e($label) ?></span>

            <select name="<?= moderation_e($name) ?>">
                <?php
                moderator_editor_options(
                    $path,
                    $value
                );
                ?>
            </select>
        </label>
        <?php
        return;
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_long_text_paths(),
            true
        )
    ) {
        ?>
        <label class="contribution-field contribution-field-wide">
            <span><?= moderation_e($label) ?></span>

            <textarea
                name="<?= moderation_e($name) ?>"
                rows="4"
            ><?= moderation_e($value ?? '') ?></textarea>
        </label>
        <?php
        return;
    }

    $type =
        $path === 'visited_at'
            ? 'date'
            : (
                in_array(
                    $path,
                    array_merge(
                        moderation_submission_editor_integer_paths(),
                        moderation_submission_editor_float_paths()
                    ),
                    true
                )
                    ? 'number'
                    : 'text'
            );

    ?>
    <label class="contribution-field">
        <span><?= moderation_e($label) ?></span>

        <input
            type="<?= moderation_e($type) ?>"
            name="<?= moderation_e($name) ?>"
            value="<?= moderation_e($value ?? '') ?>"
            <?= $type === 'number' ? 'step="any"' : '' ?>
        >
    </label>
    <?php
}

function moderator_editor_checkbox(
    array $flat,
    string $path,
    string $label
): void {
    $value =
        moderator_editor_value(
            $flat,
            $path
        );
    ?>
    <label class="contribution-check">
        <input
            type="hidden"
            name="fields[<?= moderation_e($path) ?>]"
            value="0"
        >

        <input
            type="checkbox"
            name="fields[<?= moderation_e($path) ?>]"
            value="1"
            <?= $value === true ? 'checked' : '' ?>
        >

        <span><?= moderation_e($label) ?></span>
    </label>
    <?php
}

function moderator_editor_section_start(
    string $title,
    string $description,
    string $icon,
    bool $open = false
): void {
    ?>
    <details
        class="contribution-section"
        <?= $open ? 'open' : '' ?>
    >
        <summary>
            <span>
                <i
                    class="fa-solid <?= moderation_e($icon) ?>"
                    aria-hidden="true"
                ></i>
                <?= moderation_e($title) ?>
            </span>

            <small>
                <?= moderation_e($description) ?>
            </small>
        </summary>

        <div class="contribution-section-body">
    <?php
}

function moderator_editor_section_end(): void
{
    ?>
        </div>
    </details>
    <?php
}
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/pages/add-place.css"
>
<link
    rel="stylesheet"
    href="https://llamascout.com/css/site/pages/add-place-radio-controls.css"
>
<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/edit-submission.css"
>

<section class="contribution-page add-place-page moderator-add-place-shell">

    <header class="contribution-header">
        <p class="eyebrow">Moderator edit</p>

        <h1>
            <?= moderation_e(
                (string) (
                    $data['name']
                    ?? $item['place_name']
                    ?? 'New Place'
                )
            ) ?>
        </h1>

        <p>
            Edit the contributor's submission before making a moderation decision.
            Saving changes here does not approve it, reject it, or send it back.
        </p>

        <div class="add-place-form-note">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>

            <span>
                Status remains
                <strong><?= moderation_e(
                    moderation_status_label(
                        (string) $item['status']
                    )
                ) ?></strong>.
                All moderator changes are written to the audit log.
            </span>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div
            class="contribution-message is-error"
            role="alert"
        >
            <?= moderation_e($error) ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        class="contribution-form add-place-form moderator-submission-editor-form"
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

        <input
            type="hidden"
            name="photo_stage_token"
            value=""
        >

        <input
            type="hidden"
            name="photos_json"
            value="[]"
        >

        <?php
        moderator_editor_section_start(
            'Basic information',
            'Name, type, and when the contributor visited',
            'fa-location-dot',
            true
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'name', 'Place name *'); ?>
                <?php moderator_editor_control($flat, 'type', 'Place type'); ?>
                <?php moderator_editor_control($flat, 'visited_at', 'Date visited'); ?>
                <?php moderator_editor_control($flat, 'description', 'Description'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Location',
            'GPS, elevation, road, locality, county, state, and land',
            'fa-location-crosshairs',
            true
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'latitude', 'Latitude'); ?>
                <?php moderator_editor_control($flat, 'longitude', 'Longitude'); ?>
                <?php moderator_editor_control($flat, 'elevation_feet', 'Elevation (ft)'); ?>
                <?php moderator_editor_control($flat, 'road', 'Road'); ?>
                <?php moderator_editor_control($flat, 'city', 'Nearest city / locality'); ?>
                <?php moderator_editor_control($flat, 'county', 'County / Parish / Municipality'); ?>
                <?php moderator_editor_control($flat, 'state', 'State'); ?>
                <?php moderator_editor_control($flat, 'region', 'Region / ranger district'); ?>
                <?php moderator_editor_control($flat, 'land_manager', 'Land manager'); ?>
                <?php moderator_editor_control($flat, 'land_type', 'Land type'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Site and vehicle fit',
            'Size, parking, tents, RVs, trailers, and leveling',
            'fa-car-side'
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'details.vehicle_capacity', 'Vehicle capacity'); ?>
                <?php moderator_editor_control($flat, 'details.max_vehicle_length_feet', 'Maximum vehicle length'); ?>
                <?php moderator_editor_control($flat, 'details.parking_surface', 'Parking surface'); ?>
                <?php moderator_editor_control($flat, 'details.ground_condition', 'Ground condition'); ?>

                <?php moderator_editor_control($flat, 'details.tent_camping_suitable', 'Tent camping suitable?'); ?>
                <?php moderator_editor_control($flat, 'details.rv_suitable', 'RV suitable?'); ?>
                <?php moderator_editor_control($flat, 'details.trailer_suitable', 'Trailer suitable?'); ?>
                <?php moderator_editor_control($flat, 'details.leveling_required', 'Leveling required?'); ?>
                <?php moderator_editor_control($flat, 'details.turnaround_space', 'Turnaround space?'); ?>
                <?php moderator_editor_control($flat, 'details.pull_through', 'Pull-through site?'); ?>
                <?php moderator_editor_control($flat, 'details.back_in', 'Back-in site?'); ?>

                <?php moderator_editor_control($flat, 'details.levelness', 'Levelness', 'Very uneven', 'Very level'); ?>
                <?php moderator_editor_control($flat, 'details.site_open_sky', 'Open sky', 'Enclosed', 'Wide open'); ?>
                <?php moderator_editor_control($flat, 'details.tree_cover', 'Tree cover', 'None', 'Heavy'); ?>
                <?php moderator_editor_control($flat, 'details.site_shade', 'Shade', 'None', 'Heavy'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Road access',
            'Surface, width, difficulty, stress, mud, rocks, and obstacles',
            'fa-road'
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'details.road_surface', 'Road surface'); ?>
                <?php moderator_editor_control($flat, 'details.road_width', 'Road width'); ?>

                <?php moderator_editor_control($flat, 'details.sedan_accessible', 'Sedan accessible?'); ?>
                <?php moderator_editor_control($flat, 'details.high_clearance_recommended', 'High clearance recommended?'); ?>
                <?php moderator_editor_control($flat, 'details.four_wheel_drive_recommended', '4WD recommended?'); ?>
                <?php moderator_editor_control($flat, 'details.water_crossings', 'Water crossings?'); ?>
                <?php moderator_editor_control($flat, 'details.downed_tree_risk', 'Downed-tree risk?'); ?>
                <?php moderator_editor_control($flat, 'details.seasonal_closure', 'Seasonal closure?'); ?>

                <?php moderator_editor_control($flat, 'details.site_access_difficulty', 'Site access difficulty', 'Easy', 'Very difficult'); ?>
                <?php moderator_editor_control($flat, 'details.road_overall_difficulty', 'Road difficulty', 'Easy', 'Very difficult'); ?>
                <?php moderator_editor_control($flat, 'details.road_stress', 'Driving stress', 'Relaxed', 'Very stressful'); ?>
                <?php moderator_editor_control($flat, 'details.rocks', 'Rocks', 'None', 'Severe'); ?>
                <?php moderator_editor_control($flat, 'details.washboards', 'Washboards', 'None', 'Severe'); ?>
                <?php moderator_editor_control($flat, 'details.potholes', 'Potholes', 'None', 'Severe'); ?>
                <?php moderator_editor_control($flat, 'details.mud_risk', 'Mud risk', 'Low', 'High'); ?>
                <?php moderator_editor_control($flat, 'details.steep_grades', 'Steep grades', 'None', 'Severe'); ?>
                <?php moderator_editor_control($flat, 'details.drop_off_exposure', 'Drop-off exposure', 'None', 'Severe'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Amenities',
            'Check only what is actually available',
            'fa-circle-info'
        );
        ?>
            <p class="contribution-section-help">
                These mirror the Add Place amenity controls. If there are no amenities,
                use the No amenities checkbox.
            </p>

            <div class="contribution-checkbox-grid moderator-amenities-grid">
                <?php
                moderator_editor_checkbox(
                    $flat,
                    'details.warning_no_amenities',
                    'No amenities'
                );

                foreach ([
                    'amenities.toilets' => 'Toilets',
                    'amenities.potable_water' => 'Potable water',
                    'amenities.trash' => 'Trash service',
                    'amenities.fire_ring' => 'Fire ring',
                    'amenities.picnic_table' => 'Picnic table',
                    'amenities.bear_box' => 'Bear box',
                    'amenities.showers' => 'Showers',
                    'amenities.electricity' => 'Electricity',
                    'amenities.dump_station' => 'Dump station',
                    'amenities.food_storage_required' => 'Food storage required',
                ] as $path => $label) {
                    moderator_editor_checkbox(
                        $flat,
                        $path,
                        $label
                    );
                }
                ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Connectivity',
            'Cell carriers and Starlink',
            'fa-signal'
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'connectivity.overall', 'Overall cell service', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'connectivity.t_mobile', 'T-Mobile', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'connectivity.verizon', 'Verizon', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'connectivity.att', 'AT&T', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'connectivity.other_cell', 'Other cellular', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'connectivity.starlink', 'Starlink', 'Poor', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'connectivity.starlink_tested', 'Starlink actually tested?'); ?>
                <?php moderator_editor_control($flat, 'connectivity.starlink_note', 'Starlink notes'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Sensory profile',
            'Day, night, noise, traffic, people, smells, and exposure',
            'fa-brain'
        );
        ?>
            <h3 class="contribution-subheading">Daytime</h3>

            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'sensory.daytime.noise', 'Noise', 'Very quiet', 'Very loud'); ?>
                <?php moderator_editor_control($flat, 'sensory.daytime.traffic', 'Traffic', 'None', 'Heavy'); ?>
                <?php moderator_editor_control($flat, 'sensory.daytime.crowds', 'Crowds', 'Empty', 'Crowded'); ?>
                <?php moderator_editor_control($flat, 'sensory.daytime.privacy', 'Privacy', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'sensory.daytime.light_pollution', 'Artificial light', 'None', 'Heavy'); ?>
                <?php moderator_editor_control($flat, 'sensory.daytime.sensory_comfort', 'Sensory comfort', 'Difficult', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'sensory.daytime.social_interaction_likelihood', 'Chance of social interaction', 'Very low', 'Very high'); ?>
            </div>

            <h3 class="contribution-subheading">Nighttime</h3>

            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'sensory.nighttime.noise', 'Noise', 'Very quiet', 'Very loud'); ?>
                <?php moderator_editor_control($flat, 'sensory.nighttime.traffic', 'Traffic', 'None', 'Heavy'); ?>
                <?php moderator_editor_control($flat, 'sensory.nighttime.crowds', 'Crowds', 'Empty', 'Crowded'); ?>
                <?php moderator_editor_control($flat, 'sensory.nighttime.privacy', 'Privacy', 'None', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'sensory.nighttime.light_pollution', 'Light pollution', 'Dark', 'Bright'); ?>
                <?php moderator_editor_control($flat, 'sensory.nighttime.sensory_comfort', 'Sensory comfort', 'Difficult', 'Excellent'); ?>
                <?php moderator_editor_control($flat, 'sensory.nighttime.social_interaction_likelihood', 'Chance of social interaction', 'Very low', 'Very high'); ?>
            </div>

            <h3 class="contribution-subheading">Specific sensory conditions</h3>

            <div class="contribution-grid">
                <?php
                foreach ([
                    'sensory.details.dust_from_traffic' => 'Dust from traffic',
                    'sensory.details.generator_noise' => 'Generator noise',
                    'sensory.details.aircraft_noise' => 'Aircraft noise',
                    'sensory.details.road_noise' => 'Road noise',
                    'sensory.details.human_activity' => 'Human activity',
                    'sensory.details.wildlife_noise' => 'Wildlife noise',
                    'sensory.details.wind_noise' => 'Wind noise',
                    'sensory.details.smoke_risk' => 'Smoke risk',
                    'sensory.details.strong_odors' => 'Strong odors',
                    'sensory.details.visual_exposure' => 'Visual exposure',
                    'sensory.details.predictability' => 'Predictability',
                ] as $path => $label) {
                    moderator_editor_control(
                        $flat,
                        $path,
                        $label,
                        'Low',
                        'High'
                    );
                }
                ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Environment and accessibility',
            'Terrain, views, exposure, mobility, and walking distance',
            'fa-tree'
        );
        ?>
            <div class="contribution-grid">
                <?php
                foreach ([
                    'details.forest' => 'Forest environment?',
                    'details.mountains' => 'Mountains present?',
                    'details.water_nearby' => 'Water nearby?',
                    'details.water_view' => 'Water view?',
                    'details.mountain_view' => 'Mountain view?',
                    'details.forest_view' => 'Forest view?',
                    'details.wildlife' => 'Wildlife common?',
                    'details.bugs' => 'Bugs significant?',
                    'details.wheelchair_friendly' => 'Wheelchair friendly?',
                    'details.mobility_device_friendly' => 'Outdoor mobility device friendly?',
                    'details.flat_walking_surface' => 'Flat walking surface?',
                    'details.step_free_access' => 'Step-free access?',
                    'details.accessible_toilet' => 'Accessible toilet?',
                    'details.accessible_picnic_table' => 'Accessible picnic table?',
                ] as $path => $label) {
                    moderator_editor_control(
                        $flat,
                        $path,
                        $label
                    );
                }
                ?>

                <?php moderator_editor_control($flat, 'details.wind_exposure', 'Wind exposure', 'Protected', 'Very exposed'); ?>
                <?php moderator_editor_control($flat, 'details.sun_exposure', 'Sun exposure', 'Low', 'Full sun'); ?>
                <?php moderator_editor_control($flat, 'details.environment_shade', 'Environment shade', 'None', 'Heavy'); ?>
                <?php moderator_editor_control($flat, 'details.environment_open_sky', 'Open sky', 'Low', 'Wide open'); ?>
                <?php moderator_editor_control($flat, 'details.walking_distance_from_vehicle', 'Walking distance from vehicle'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Safety and warnings',
            'Hazards and conditions people should see quickly',
            'fa-shield-halved'
        );
        ?>
            <div class="contribution-grid">
                <?php
                foreach ([
                    'details.felt_safe_daytime' => 'Felt safe during the day?',
                    'details.felt_safe_nighttime' => 'Felt safe at night?',
                    'details.flash_flood_risk' => 'Flash-flood risk?',
                    'details.wildfire_risk' => 'Wildfire risk?',
                    'details.fall_hazard' => 'Fall hazard?',
                    'details.cliff_exposure' => 'Cliff exposure?',
                    'details.rockfall_risk' => 'Rockfall risk?',
                    'details.wildlife_risk' => 'Wildlife risk?',
                    'details.traffic_hazard' => 'Traffic hazard?',
                    'details.emergency_access' => 'Emergency vehicle access?',
                    'details.warning_exposed_to_road' => 'Exposed to road?',
                    'details.warning_zero_privacy' => 'Zero privacy?',
                    'details.warning_passing_vehicle_dust' => 'Passing vehicle dust?',
                    'details.warning_possible_downed_trees' => 'Possible downed trees?',
                    'details.warning_no_tent_camping' => 'No tent camping?',
                    'details.warning_limited_vehicle_length' => 'Limited vehicle length?',
                    'details.warning_leveling_may_be_required' => 'Leveling may be required?',
                    'details.warning_motorized_recreation_traffic' => 'Motorized recreation traffic?',
                    'details.warning_blind_turn_traffic_nearby' => 'Blind-turn traffic nearby?',
                ] as $path => $label) {
                    moderator_editor_control(
                        $flat,
                        $path,
                        $label
                    );
                }
                ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Seasons, rules, and nearby services',
            'Access seasons, camping rules, fees, fire, fuel, food, and medical care',
            'fa-cloud-sun'
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'rules.best_months', 'Best months'); ?>
                <?php moderator_editor_control($flat, 'rules.winter_access', 'Winter access?'); ?>
                <?php moderator_editor_control($flat, 'rules.snow_risk', 'Snow risk', 'Low', 'High'); ?>
                <?php moderator_editor_control($flat, 'rules.mud_season_risk', 'Mud-season risk', 'Low', 'High'); ?>
                <?php moderator_editor_control($flat, 'rules.monsoon_risk', 'Monsoon risk', 'Low', 'High'); ?>
                <?php moderator_editor_control($flat, 'rules.seasonal_access_note', 'Seasonal access notes'); ?>

                <?php
                foreach ([
                    'rules.overnight_camping_allowed' => 'Overnight camping allowed?',
                    'rules.dispersed_camping_allowed' => 'Dispersed camping allowed?',
                    'rules.permit_required' => 'Permit required?',
                    'rules.campfire_allowed' => 'Campfire allowed?',
                    'rules.existing_sites_encouraged' => 'Existing sites encouraged?',
                    'rules.pack_it_in_pack_it_out' => 'Pack it in / pack it out?',
                    'rules.residential_use_prohibited' => 'Residential use prohibited?',
                ] as $path => $label) {
                    moderator_editor_control(
                        $flat,
                        $path,
                        $label
                    );
                }
                ?>

                <?php moderator_editor_control($flat, 'rules.stay_limit_days', 'Stay limit'); ?>
                <?php moderator_editor_control($flat, 'rules.fee', 'Fee'); ?>
                <?php moderator_editor_control($flat, 'rules.current_fire_restrictions_url', 'Current fire restrictions URL'); ?>

                <?php moderator_editor_control($flat, 'rules.nearest_town', 'Distance to nearest town'); ?>
                <?php moderator_editor_control($flat, 'rules.nearest_fuel', 'Distance to nearest fuel'); ?>
                <?php moderator_editor_control($flat, 'rules.nearest_grocery', 'Distance to nearest grocery'); ?>
                <?php moderator_editor_control($flat, 'rules.nearest_water', 'Distance to nearest potable water'); ?>
                <?php moderator_editor_control($flat, 'rules.nearest_toilet', 'Distance to nearest public toilet'); ?>
                <?php moderator_editor_control($flat, 'rules.nearest_hospital', 'Distance to nearest hospital / emergency care'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Experience and recommendations',
            'Views, stars, comfort, quiet, remote work, and who it suits',
            'fa-star'
        );
        ?>
            <div class="contribution-grid">
                <?php
                foreach ([
                    'experience.sunrise_view' => 'Sunrise view',
                    'experience.sunset_view' => 'Sunset view',
                    'experience.mountain_view' => 'Mountain view',
                    'experience.forest_view' => 'Forest view',
                    'experience.night_sky' => 'Night sky',
                    'experience.stargazing' => 'Stargazing',
                    'experience.quiet_evening' => 'Quiet evening',
                    'experience.overnight_comfort' => 'Overnight comfort',
                    'experience.extended_stay_comfort' => 'Extended-stay comfort',
                    'experience.sensory_retreat' => 'Sensory retreat',
                    'experience.remote_work' => 'Remote work',
                    'experience.overall_scenery' => 'Overall scenery',
                    'experience.recommended_overnight_stop' => 'Recommended overnight stop',
                    'experience.recommended_quiet_evening' => 'Recommended quiet evening',
                    'experience.recommended_extended_stay' => 'Recommended extended stay',
                    'experience.recommended_sensory_retreat' => 'Recommended sensory retreat',
                    'experience.recommended_stargazing' => 'Recommended stargazing',
                    'experience.recommended_remote_work' => 'Recommended remote work',
                ] as $path => $label) {
                    moderator_editor_control(
                        $flat,
                        $path,
                        $label,
                        'Poor',
                        'Excellent'
                    );
                }
                ?>

                <?php moderator_editor_control($flat, 'experience.recommended_solo_travel', 'Good for solo travel?'); ?>
                <?php moderator_editor_control($flat, 'experience.recommended_families', 'Good for families?'); ?>
                <?php moderator_editor_control($flat, 'experience.recommended_large_groups', 'Good for large groups?'); ?>
                <?php moderator_editor_control($flat, 'experience.not_recommended_for', 'Not recommended for'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Summaries and reviewer notes',
            'Useful context that does not fit into a dropdown',
            'fa-pen'
        );
        ?>
            <div class="contribution-grid">
                <?php moderator_editor_control($flat, 'access_summary', 'Access summary'); ?>
                <?php moderator_editor_control($flat, 'sensory_summary', 'Sensory summary'); ?>
                <?php moderator_editor_control($flat, 'contributor_notes', 'Notes from the contributor'); ?>
            </div>
        <?php moderator_editor_section_end(); ?>


        <?php
        moderator_editor_section_start(
            'Photos',
            'Keep, remove, or add supporting submission photos',
            'fa-camera',
            true
        );
        ?>

            <?php if ($photos): ?>
                <div class="add-place-existing-photos moderator-existing-photos">
                    <strong>Photos already attached</strong>

                    <p>
                        Remove anything that should not be published. If a replacement
                        is needed, add it below before saving or request a new photo from
                        the contributor.
                    </p>

                    <div class="add-place-existing-photo-grid">
                        <?php foreach ($photos as $photo): ?>
                            <?php
                            $src =
                                moderation_photo_path(
                                    $photo
                                );
                            ?>

                            <?php if ($src !== ''): ?>
                                <label class="add-place-existing-photo">
                                    <img
                                        src="https://llamascout.com<?= moderation_e($src) ?>"
                                        alt="<?= moderation_e(
                                            (string) (
                                                $photo['alt']
                                                ?? ''
                                            )
                                        ) ?>"
                                    >

                                    <span>
                                        <input
                                            type="checkbox"
                                            name="remove_existing_photos[]"
                                            value="<?= moderation_e($src) ?>"
                                        >
                                        Remove this photo
                                    </span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="moderator-photo-empty">
                    <i class="fa-regular fa-image" aria-hidden="true"></i>
                    <strong>No submitted photos</strong>
                    <span>
                        You can add a moderator photo below or return to Review and request one from the contributor.
                    </span>
                </div>
            <?php endif; ?>

            <?php if (count($photos) < 10): ?>
                <div
                    data-photo-uploader
                    data-photo-context="add-place"
                    data-photo-max="<?= max(1, 10 - count($photos)) ?>"
                    data-photo-csrf="<?= moderation_e(
                        llama_photo_csrf_token()
                    ) ?>"
                    data-photo-endpoint="/photo-upload.php"
                    data-photo-title="Add moderator photos"
                    data-photo-help="Add replacement or supporting photos. Location metadata is removed before storage."
                ></div>
            <?php else: ?>
                <p class="contribution-section-help">
                    This submission already has 10 photos. Remove one and save before adding another.
                </p>
            <?php endif; ?>

        <?php moderator_editor_section_end(); ?>


        <div class="contribution-actions add-place-submit-bar moderator-save-bar">
            <button
                class="contribution-submit"
                type="submit"
            >
                <i
                    class="fa-solid fa-floppy-disk"
                    aria-hidden="true"
                ></i>
                Save Submission Changes
            </button>

            <a href="/moderate-submission.php?id=<?= $submissionId ?>">
                Cancel
            </a>
        </div>

    </form>
</section>

<script src="https://llamascout.com/js/photo-uploader.js"></script>
<script src="https://llamascout.com/js/admin-edit-submission.js"></script>

<?php require __DIR__ . '/_footer.php'; ?>
