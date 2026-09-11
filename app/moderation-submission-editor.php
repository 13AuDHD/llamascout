<?php

declare(strict_types=1);

/*
 * Moderator editing for New Place submissions.
 *
 * No schema changes are performed here.
 */

function moderation_submission_editor_flatten(
    array $data,
    string $prefix = ''
): array {
    $flat = [];

    foreach ($data as $key => $value) {
        if ($key === 'photos' && $prefix === '') {
            continue;
        }

        $path =
            $prefix === ''
                ? (string) $key
                : $prefix . '.' . $key;

        if (is_array($value)) {
            $flat += moderation_submission_editor_flatten(
                $value,
                $path
            );

            continue;
        }

        $flat[$path] = $value;
    }

    return $flat;
}


function moderation_submission_editor_set_path(
    array &$data,
    string $path,
    mixed $value
): void {
    $parts = explode('.', $path);
    $cursor =& $data;

    foreach ($parts as $index => $part) {
        $last = $index === count($parts) - 1;

        if ($last) {
            $cursor[$part] = $value;
            return;
        }

        if (
            !isset($cursor[$part])
            || !is_array($cursor[$part])
        ) {
            $cursor[$part] = [];
        }

        $cursor =& $cursor[$part];
    }
}


function moderation_submission_editor_bool_paths(): array
{
    return [
        'details.tent_camping_suitable',
        'details.rv_suitable',
        'details.trailer_suitable',
        'details.leveling_required',
        'details.turnaround_space',
        'details.pull_through',
        'details.back_in',
        'details.sedan_accessible',
        'details.high_clearance_recommended',
        'details.four_wheel_drive_recommended',
        'details.water_crossings',
        'details.downed_tree_risk',
        'details.seasonal_closure',
        'details.forest',
        'details.mountains',
        'details.water_nearby',
        'details.water_view',
        'details.mountain_view',
        'details.forest_view',
        'details.wildlife',
        'details.bugs',
        'details.wheelchair_friendly',
        'details.mobility_device_friendly',
        'details.flat_walking_surface',
        'details.step_free_access',
        'details.accessible_toilet',
        'details.accessible_picnic_table',
        'details.felt_safe_daytime',
        'details.felt_safe_nighttime',
        'details.flash_flood_risk',
        'details.wildfire_risk',
        'details.fall_hazard',
        'details.cliff_exposure',
        'details.rockfall_risk',
        'details.wildlife_risk',
        'details.traffic_hazard',
        'details.emergency_access',
        'details.warning_exposed_to_road',
        'details.warning_zero_privacy',
        'details.warning_passing_vehicle_dust',
        'details.warning_possible_downed_trees',
        'details.warning_no_tent_camping',
        'details.warning_limited_vehicle_length',
        'details.warning_leveling_may_be_required',
        'details.warning_no_amenities',
        'details.warning_motorized_recreation_traffic',
        'details.warning_blind_turn_traffic_nearby',
        'connectivity.starlink_tested',
        'rules.winter_access',
        'rules.overnight_camping_allowed',
        'rules.dispersed_camping_allowed',
        'rules.permit_required',
        'rules.campfire_allowed',
        'rules.existing_sites_encouraged',
        'rules.pack_it_in_pack_it_out',
        'rules.residential_use_prohibited',
        'experience.recommended_solo_travel',
        'experience.recommended_families',
        'experience.recommended_large_groups',
        'amenities.toilets',
        'amenities.potable_water',
        'amenities.trash',
        'amenities.fire_ring',
        'amenities.picnic_table',
        'amenities.bear_box',
        'amenities.showers',
        'amenities.electricity',
        'amenities.dump_station',
        'amenities.food_storage_required',
    ];
}


function moderation_submission_editor_rating_paths(): array
{
    return [
        'details.levelness',
        'details.site_open_sky',
        'details.tree_cover',
        'details.site_shade',
        'details.site_access_difficulty',
        'details.road_overall_difficulty',
        'details.road_difficulty',
        'details.road_stress',
        'details.rocks',
        'details.washboards',
        'details.potholes',
        'details.mud_risk',
        'details.steep_grades',
        'details.drop_off_exposure',
        'details.wind_exposure',
        'details.sun_exposure',
        'details.environment_shade',
        'details.environment_open_sky',
        'connectivity.overall',
        'connectivity.t_mobile',
        'connectivity.verizon',
        'connectivity.att',
        'connectivity.other_cell',
        'connectivity.starlink',
        'sensory.daytime.noise',
        'sensory.daytime.traffic',
        'sensory.daytime.crowds',
        'sensory.daytime.privacy',
        'sensory.daytime.light_pollution',
        'sensory.daytime.sensory_comfort',
        'sensory.daytime.social_interaction_likelihood',
        'sensory.nighttime.noise',
        'sensory.nighttime.traffic',
        'sensory.nighttime.crowds',
        'sensory.nighttime.privacy',
        'sensory.nighttime.light_pollution',
        'sensory.nighttime.sensory_comfort',
        'sensory.nighttime.social_interaction_likelihood',
        'sensory.details.dust_from_traffic',
        'sensory.details.generator_noise',
        'sensory.details.aircraft_noise',
        'sensory.details.road_noise',
        'sensory.details.human_activity',
        'sensory.details.wildlife_noise',
        'sensory.details.wind_noise',
        'sensory.details.smoke_risk',
        'sensory.details.strong_odors',
        'sensory.details.visual_exposure',
        'sensory.details.predictability',
        'rules.snow_risk',
        'rules.mud_season_risk',
        'rules.monsoon_risk',
        'experience.sunrise_view',
        'experience.sunset_view',
        'experience.mountain_view',
        'experience.forest_view',
        'experience.night_sky',
        'experience.stargazing',
        'experience.quiet_evening',
        'experience.overnight_comfort',
        'experience.extended_stay_comfort',
        'experience.sensory_retreat',
        'experience.remote_work',
        'experience.overall_scenery',
        'experience.recommended_overnight_stop',
        'experience.recommended_quiet_evening',
        'experience.recommended_extended_stay',
        'experience.recommended_sensory_retreat',
        'experience.recommended_stargazing',
        'experience.recommended_remote_work',
    ];
}


function moderation_submission_editor_integer_paths(): array
{
    return [
        'elevation_feet',
        'details.vehicle_capacity',
        'details.max_vehicle_length_feet',
        'rules.stay_limit_days',
    ];
}


function moderation_submission_editor_float_paths(): array
{
    return [
        'latitude',
        'longitude',
        'rules.fee',
    ];
}


function moderation_submission_editor_long_text_paths(): array
{
    return [
        'description',
        'access_summary',
        'sensory_summary',
        'contributor_notes',
        'connectivity.starlink_note',
        'rules.seasonal_access_note',
        'experience.not_recommended_for',
    ];
}


function moderation_submission_editor_label(
    string $path
): string {
    $special = [
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'elevation_feet' => 'Elevation (ft)',
        'city' => 'Nearest city / locality',
        'county' => 'County / Parish / Municipality',
        'state' => 'State',
        'details.rv_suitable' => 'RV suitable',
        'details.four_wheel_drive_recommended' => '4WD recommended',
        'connectivity.t_mobile' => 'T-Mobile',
        'connectivity.att' => 'AT&T',
        'connectivity.starlink' => 'Starlink',
        'connectivity.starlink_tested' => 'Starlink tested',
        'rules.nearest_town' => 'Distance to nearest town',
        'rules.nearest_fuel' => 'Distance to nearest fuel',
        'rules.nearest_grocery' => 'Distance to nearest grocery',
        'rules.nearest_water' => 'Distance to nearest potable water',
        'rules.nearest_toilet' => 'Distance to nearest public toilet',
        'rules.nearest_hospital' => 'Distance to nearest hospital / emergency care',
    ];

    if (isset($special[$path])) {
        return $special[$path];
    }

    $leaf = basename(
        str_replace('.', '/', $path)
    );

    return ucwords(
        str_replace('_', ' ', $leaf)
    );
}


function moderation_submission_editor_group(
    string $path
): string {
    if (!str_contains($path, '.')) {
        return in_array(
            $path,
            [
                'name',
                'type',
                'description',
                'visited_at',
            ],
            true
        )
            ? 'Basic information'
            : 'Location and summaries';
    }

    $first = explode('.', $path, 2)[0];

    return match ($first) {
        'details' => 'Site, road, environment, accessibility, and safety',
        'amenities' => 'Amenities',
        'connectivity' => 'Connectivity',
        'sensory' => 'Sensory profile',
        'rules' => 'Seasons, rules, and nearby services',
        'experience' => 'Experience and recommendations',
        default => 'Other',
    };
}


function moderation_submission_editor_parse_value(
    string $path,
    mixed $raw
): mixed {
    $raw = is_string($raw)
        ? trim($raw)
        : $raw;

    if (
        in_array(
            $path,
            moderation_submission_editor_bool_paths(),
            true
        )
    ) {
        if ($raw === '' || $raw === '__NULL__') {
            return null;
        }

        return (string) $raw === '1';
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_rating_paths(),
            true
        )
    ) {
        if ($raw === '' || $raw === '__NULL__') {
            return null;
        }

        $number = (int) $raw;

        if ($number < 1 || $number > 5) {
            throw new InvalidArgumentException(
                moderation_submission_editor_label($path)
                . ' must be 1 through 5 or Unknown.'
            );
        }

        return $number;
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_integer_paths(),
            true
        )
    ) {
        if ($raw === '') {
            return null;
        }

        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException(
                moderation_submission_editor_label($path)
                . ' must be a whole number.'
            );
        }

        return (int) $raw;
    }

    if (
        in_array(
            $path,
            moderation_submission_editor_float_paths(),
            true
        )
    ) {
        if ($raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException(
                moderation_submission_editor_label($path)
                . ' must be numeric.'
            );
        }

        return (float) $raw;
    }

    return $raw === ''
        ? null
        : mb_substr(
            (string) $raw,
            0,
            5000
        );
}


function moderation_save_submission_edits(
    PDO $db,
    int $submissionId,
    int $adminId,
    array $postedFields,
    array $removePhotoPaths,
    string $photoToken,
    array $submittedPhotos
): array {
    if (!$db->inTransaction()) {
        throw new RuntimeException(
            'Moderator submission editing requires an active database transaction.'
        );
    }

    $submission =
        moderation_submission(
            $db,
            $submissionId,
            true
        );

    if (!$submission) {
        throw new RuntimeException(
            'The Place submission could not be found.'
        );
    }

    if (
        !in_array(
            (string) $submission['status'],
            [
                'pending',
                'needs-changes',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Only Pending or Needs Changes submissions can be edited before approval.'
        );
    }

    $data =
        is_array(
            $submission['data']
            ?? null
        )
            ? $submission['data']
            : [];

    $before = moderation_submission_editor_flatten(
        $data
    );

    foreach ($before as $path => $oldValue) {
        if (!array_key_exists($path, $postedFields)) {
            continue;
        }

        $newValue =
            moderation_submission_editor_parse_value(
                $path,
                $postedFields[$path]
            );

        moderation_submission_editor_set_path(
            $data,
            $path,
            $newValue
        );
    }

    $existingPhotos =
        is_array($data['photos'] ?? null)
            ? $data['photos']
            : [];

    $removeLookup = [];

    foreach ($removePhotoPaths as $path) {
        $path = '/' . ltrim(
            trim((string) $path),
            '/'
        );

        if (
            str_starts_with(
                $path,
                '/uploads/place-submissions/'
                . $submissionId
                . '/'
            )
        ) {
            $removeLookup[$path] = true;
        }
    }

    $keptPhotos = [];

    foreach ($existingPhotos as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        $path = moderation_photo_path(
            $photo
        );

        if (
            $path !== ''
            && isset($removeLookup[$path])
        ) {
            continue;
        }

        $keptPhotos[] = $photo;
    }

    $addedPhotos = [];

    if (
        $photoToken !== ''
        && $submittedPhotos
    ) {
        $addedPhotos =
            llama_photo_commit_stage(
                'add-place',
                $adminId,
                $photoToken,
                $submittedPhotos,
                '/uploads/place-submissions/'
                . $submissionId
            );
    }

    /*
     * Keep the historical submission photo shape compatible with
     * member resubmission code, which reads the permanent path from
     * the `src` key.
     */
    foreach ($addedPhotos as &$addedPhoto) {
        if (!is_array($addedPhoto)) {
            continue;
        }

        $addedPath =
            trim(
                (string) (
                    $addedPhoto['path']
                    ?? ''
                )
            );

        if ($addedPath !== '') {
            $addedPhoto['src'] =
                $addedPath;
        }
    }
    unset($addedPhoto);

    $data['photos'] =
        array_values(
            array_merge(
                $keptPhotos,
                $addedPhotos
            )
        );

    $name =
        trim(
            (string) (
                $data['name']
                ?? ''
            )
        );

    if ($name === '') {
        throw new InvalidArgumentException(
            'Place name cannot be blank.'
        );
    }

    $encoded =
        json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        );

    $update =
        $db->prepare(
            'UPDATE place_submissions
             SET
                place_name = ?,
                submission_data = ?
             WHERE id = ?
               AND status IN ("pending","needs-changes")'
        );

    $update->execute([
        $name,
        $encoded,
        $submissionId,
    ]);

    $after =
        moderation_submission_editor_flatten(
            $data
        );

    $fieldChanges = [];

    foreach ($before as $path => $oldValue) {
        $newValue =
            $after[$path]
            ?? null;

        if ($oldValue === $newValue) {
            continue;
        }

        $fieldChanges[] = [
            'field' =>
                $path,

            'label' =>
                moderation_submission_editor_label(
                    $path
                ),

            'before' =>
                $oldValue,

            'after' =>
                $newValue,
        ];
    }

    return [
        'submission' =>
            $submission,

        'field_changes' =>
            $fieldChanges,

        'removed_photos' =>
            array_keys($removeLookup),

        'added_photo_count' =>
            count($addedPhotos),
    ];
}
