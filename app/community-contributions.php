<?php

declare(strict_types=1);

function community_csrf_token(): string
{
    if (empty($_SESSION['community_csrf_token'])) {
        $_SESSION['community_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['community_csrf_token'];
}

function community_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['community_csrf_token'] ?? '');
    return $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

function community_role_at_submission(int $userId): string
{
    $roles = user_roles($userId);

    foreach (['admin', 'master-scout', 'master_scout', 'scout'] as $role) {
        if (in_array($role, $roles, true)) {
            return str_replace('_', '-', $role);
        }
    }

    if (function_exists('user_has_member_access') && user_has_member_access($userId)) {
        return 'member';
    }

    return 'user';
}

function community_clean_text(mixed $value, int $maxLength = 5000): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function community_clean_float(mixed $value, float $min, float $max): ?float
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return null;
    }

    return $number;
}

function community_clean_int(mixed $value, int $min, int $max): ?int
{
    $value = trim((string) $value);
    if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }

    $number = (int) $value;
    if ($number < $min || $number > $max) {
        return null;
    }

    return $number;
}

function community_place_types(): array
{
    return [
        'dispersed-camping' => 'Dispersed camping',
        'developed-campground' => 'Developed campground',
        'vehicle-pulloff' => 'Vehicle pull-off',
        'trailhead' => 'Trailhead',
        'day-use' => 'Day-use area',
        'other' => 'Other',
    ];
}

function submit_new_place(int $userId, array $input): int
{
    $name = community_clean_text(
        $input['name'] ?? null,
        200
    );

    if ($name === null) {
        throw new InvalidArgumentException(
            'Place name is required.'
        );
    }

    $type = (string) (
        $input['type']
        ?? 'other'
    );

    if (!array_key_exists($type, community_place_types())) {
        $type = 'other';
    }

    $latitude = community_clean_float(
        $input['latitude'] ?? null,
        -90,
        90
    );

    $longitude = community_clean_float(
        $input['longitude'] ?? null,
        -180,
        180
    );

    if (($latitude === null) !== ($longitude === null)) {
        throw new InvalidArgumentException(
            'Enter both latitude and longitude, or leave both blank.'
        );
    }

    $tri = static function (
        array $input,
        string $key
    ): ?bool {
        if (
            !array_key_exists($key, $input)
            || $input[$key] === ''
            || $input[$key] === null
        ) {
            return null;
        }

        return (string) $input[$key] === '1';
    };

    $rating = static function (
        array $input,
        string $key
    ): ?int {
        if (
            !array_key_exists($key, $input)
            || $input[$key] === ''
            || $input[$key] === null
        ) {
            return null;
        }

        $value = (int) $input[$key];

        return ($value >= 1 && $value <= 5)
            ? $value
            : null;
    };

    $cleanSelect = static function (
        array $input,
        string $key,
        int $max = 180
    ): ?string {
        return community_clean_text(
            $input[$key] ?? null,
            $max
        );
    };

    $photoToken =
        trim(
            (string) (
                $input['photo_stage_token']
                ?? ''
            )
        );

    $submittedPhotos =
        llama_photo_decode_form_photos(
            $input['photos_json']
            ?? '[]'
        );

    if ($submittedPhotos && $photoToken === '') {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }

    $details = [
        'vehicle_capacity' =>
            community_clean_int(
                $input['vehicle_capacity'] ?? null,
                0,
                100
            ),

        'max_vehicle_length_feet' =>
            community_clean_int(
                $input['max_vehicle_length_feet'] ?? null,
                0,
                200
            ),

        'tent_camping_suitable' =>
            $tri($input, 'tent_camping_suitable'),

        'rv_suitable' =>
            $tri($input, 'rv_suitable'),

        'trailer_suitable' =>
            $tri($input, 'trailer_suitable'),

        'parking_surface' =>
            $cleanSelect($input, 'parking_surface'),

        'levelness' =>
            $rating($input, 'levelness'),

        'leveling_required' =>
            $tri($input, 'leveling_required'),

        'turnaround_space' =>
            $tri($input, 'turnaround_space'),

        'pull_through' =>
            $tri($input, 'pull_through'),

        'back_in' =>
            $tri($input, 'back_in'),

        'ground_condition' =>
            $cleanSelect($input, 'ground_condition'),

        'site_open_sky' =>
            $rating($input, 'site_open_sky'),

        'tree_cover' =>
            $rating($input, 'tree_cover'),

        'site_shade' =>
            $rating($input, 'site_shade'),

        'site_access_difficulty' =>
            $rating($input, 'site_access_difficulty'),

        'road_overall_difficulty' =>
            $rating($input, 'road_overall_difficulty'),

        'road_difficulty' =>
            $rating($input, 'road_overall_difficulty'),

        'road_stress' =>
            $rating($input, 'road_stress'),

        'sedan_accessible' =>
            $tri($input, 'sedan_accessible'),

        'high_clearance_recommended' =>
            $tri($input, 'high_clearance_recommended'),

        'four_wheel_drive_recommended' =>
            $tri($input, 'four_wheel_drive_recommended'),

        'road_surface' =>
            $cleanSelect($input, 'road_surface'),

        'road_width' =>
            $cleanSelect($input, 'road_width'),

        'rocks' =>
            $rating($input, 'rocks'),

        'washboards' =>
            $rating($input, 'washboards'),

        'potholes' =>
            $rating($input, 'potholes'),

        'mud_risk' =>
            $rating($input, 'mud_risk'),

        'steep_grades' =>
            $rating($input, 'steep_grades'),

        'drop_off_exposure' =>
            $rating($input, 'drop_off_exposure'),

        'water_crossings' =>
            $tri($input, 'water_crossings'),

        'downed_tree_risk' =>
            $tri($input, 'downed_tree_risk'),

        'seasonal_closure' =>
            $tri($input, 'seasonal_closure'),

        'forest' =>
            $tri($input, 'environment_forest'),

        'mountains' =>
            $tri($input, 'environment_mountains'),

        'water_nearby' =>
            $tri($input, 'environment_water_nearby'),

        'water_view' =>
            $tri($input, 'environment_water_view'),

        'mountain_view' =>
            $tri($input, 'environment_mountain_view'),

        'forest_view' =>
            $tri($input, 'environment_forest_view'),

        'wildlife' =>
            $tri($input, 'environment_wildlife'),

        'bugs' =>
            $tri($input, 'environment_bugs'),

        'wind_exposure' =>
            $rating($input, 'environment_wind_exposure'),

        'sun_exposure' =>
            $rating($input, 'environment_sun_exposure'),

        'environment_shade' =>
            $rating($input, 'environment_shade'),

        'environment_open_sky' =>
            $rating($input, 'environment_open_sky'),

        'wheelchair_friendly' =>
            $tri($input, 'wheelchair_friendly'),

        'mobility_device_friendly' =>
            $tri($input, 'mobility_device_friendly'),

        'flat_walking_surface' =>
            $tri($input, 'flat_walking_surface'),

        'walking_distance_from_vehicle' =>
            $cleanSelect(
                $input,
                'walking_distance_from_vehicle'
            ),

        'step_free_access' =>
            $tri($input, 'step_free_access'),

        'accessible_toilet' =>
            $tri($input, 'accessible_toilet'),

        'accessible_picnic_table' =>
            $tri($input, 'accessible_picnic_table'),

        'felt_safe_daytime' =>
            $tri($input, 'felt_safe_daytime'),

        'felt_safe_nighttime' =>
            $tri($input, 'felt_safe_nighttime'),

        'flash_flood_risk' =>
            $tri($input, 'flash_flood_risk'),

        'wildfire_risk' =>
            $tri($input, 'wildfire_risk'),

        'fall_hazard' =>
            $tri($input, 'fall_hazard'),

        'cliff_exposure' =>
            $tri($input, 'cliff_exposure'),

        'rockfall_risk' =>
            $tri($input, 'rockfall_risk'),

        'wildlife_risk' =>
            $tri($input, 'wildlife_risk'),

        'traffic_hazard' =>
            $tri($input, 'traffic_hazard'),

        'emergency_access' =>
            $tri($input, 'emergency_access'),

        'warning_exposed_to_road' =>
            $tri($input, 'warning_exposed_to_road'),

        'warning_zero_privacy' =>
            $tri($input, 'warning_zero_privacy'),

        'warning_passing_vehicle_dust' =>
            $tri($input, 'warning_passing_vehicle_dust'),

        'warning_possible_downed_trees' =>
            $tri($input, 'warning_possible_downed_trees'),

        'warning_no_tent_camping' =>
            $tri($input, 'warning_no_tent_camping'),

        'warning_limited_vehicle_length' =>
            $tri($input, 'warning_limited_vehicle_length'),

        'warning_leveling_may_be_required' =>
            $tri($input, 'warning_leveling_may_be_required'),

        'warning_no_amenities' =>
            $tri($input, 'warning_no_amenities'),

        'warning_motorized_recreation_traffic' =>
            $tri($input, 'warning_motorized_recreation_traffic'),

        'warning_blind_turn_traffic_nearby' =>
            $tri($input, 'warning_blind_turn_traffic_nearby'),
    ];

    $amenities = [
        'toilets' =>
            isset($input['amenity_toilets']),

        'potable_water' =>
            isset($input['amenity_potable_water']),

        'trash' =>
            isset($input['amenity_trash']),

        'fire_ring' =>
            isset($input['amenity_fire_ring']),

        'picnic_table' =>
            isset($input['amenity_picnic_table']),

        'bear_box' =>
            isset($input['amenity_bear_box']),

        'showers' =>
            isset($input['amenity_showers']),

        'electricity' =>
            isset($input['amenity_electricity']),

        'dump_station' =>
            isset($input['amenity_dump_station']),

        'food_storage_required' =>
            isset($input['amenity_food_storage_required']),
    ];

    $connectivity = [
        'overall' =>
            $rating($input, 'connectivity_overall'),

        't_mobile' =>
            $rating($input, 'connectivity_t_mobile'),

        'verizon' =>
            $rating($input, 'connectivity_verizon'),

        'att' =>
            $rating($input, 'connectivity_att'),

        'other_cell' =>
            $rating($input, 'connectivity_other_cell'),

        'starlink' =>
            $rating($input, 'connectivity_starlink'),

        'starlink_tested' =>
            $tri($input, 'connectivity_starlink_tested'),

        'starlink_note' =>
            community_clean_text(
                $input['connectivity_starlink_note']
                ?? null
            ),
    ];

    $sensory = [
        'daytime' => [
            'noise' =>
                $rating($input, 'daytime_noise'),
            'traffic' =>
                $rating($input, 'daytime_traffic'),
            'crowds' =>
                $rating($input, 'daytime_crowds'),
            'privacy' =>
                $rating($input, 'daytime_privacy'),
            'light_pollution' =>
                $rating($input, 'daytime_light_pollution'),
            'sensory_comfort' =>
                $rating($input, 'daytime_sensory_comfort'),
            'social_interaction_likelihood' =>
                $rating($input, 'daytime_social_interaction'),
        ],

        'nighttime' => [
            'noise' =>
                $rating($input, 'nighttime_noise'),
            'traffic' =>
                $rating($input, 'nighttime_traffic'),
            'crowds' =>
                $rating($input, 'nighttime_crowds'),
            'privacy' =>
                $rating($input, 'nighttime_privacy'),
            'light_pollution' =>
                $rating($input, 'nighttime_light_pollution'),
            'sensory_comfort' =>
                $rating($input, 'nighttime_sensory_comfort'),
            'social_interaction_likelihood' =>
                $rating($input, 'nighttime_social_interaction'),
        ],

        'details' => [
            'dust_from_traffic' =>
                $rating($input, 'sensory_dust_from_traffic'),
            'generator_noise' =>
                $rating($input, 'sensory_generator_noise'),
            'aircraft_noise' =>
                $rating($input, 'sensory_aircraft_noise'),
            'road_noise' =>
                $rating($input, 'sensory_road_noise'),
            'human_activity' =>
                $rating($input, 'sensory_human_activity'),
            'wildlife_noise' =>
                $rating($input, 'sensory_wildlife_noise'),
            'wind_noise' =>
                $rating($input, 'sensory_wind_noise'),
            'smoke_risk' =>
                $rating($input, 'sensory_smoke_risk'),
            'strong_odors' =>
                $rating($input, 'sensory_strong_odors'),
            'visual_exposure' =>
                $rating($input, 'sensory_visual_exposure'),
            'predictability' =>
                $rating($input, 'sensory_predictability'),
        ],
    ];

    $rules = [
        'best_months' =>
            $cleanSelect($input, 'best_months'),

        'winter_access' =>
            $tri($input, 'winter_access'),

        'snow_risk' =>
            $rating($input, 'snow_risk'),

        'mud_season_risk' =>
            $rating($input, 'mud_season_risk'),

        'monsoon_risk' =>
            $rating($input, 'monsoon_risk'),

        'recommended_travel_season' =>
            $cleanSelect($input, 'best_months'),

        'seasonal_access_note' =>
            community_clean_text(
                $input['seasonal_access_note']
                ?? null
            ),

        'overnight_camping_allowed' =>
            $tri($input, 'overnight_camping_allowed'),

        'dispersed_camping_allowed' =>
            $tri($input, 'dispersed_camping_allowed'),

        'stay_limit_days' =>
            community_clean_int(
                $input['stay_limit_days'] ?? null,
                0,
                365
            ),

        'permit_required' =>
            $tri($input, 'permit_required'),

        'fee' =>
            community_clean_float(
                $input['fee'] ?? null,
                0,
                100000
            ),

        'campfire_allowed' =>
            $tri($input, 'campfire_allowed'),

        'current_fire_restrictions_url' =>
            community_clean_text(
                $input['current_fire_restrictions_url']
                ?? null,
                1000
            ),

        'existing_sites_encouraged' =>
            $tri($input, 'existing_sites_encouraged'),

        'pack_it_in_pack_it_out' =>
            $tri($input, 'pack_it_in_pack_it_out'),

        'residential_use_prohibited' =>
            $tri($input, 'residential_use_prohibited'),

        'nearest_town' =>
            community_clean_text(
                $input['nearest_town'] ?? null,
                255
            ),

        'nearest_fuel' =>
            community_clean_text(
                $input['nearest_fuel'] ?? null,
                255
            ),

        'nearest_grocery' =>
            community_clean_text(
                $input['nearest_grocery'] ?? null,
                255
            ),

        'nearest_water' =>
            community_clean_text(
                $input['nearest_water'] ?? null,
                255
            ),

        'nearest_toilet' =>
            community_clean_text(
                $input['nearest_toilet'] ?? null,
                255
            ),

        'nearest_hospital' =>
            community_clean_text(
                $input['nearest_hospital'] ?? null,
                255
            ),
    ];

    $experience = [
        'sunrise_view' =>
            $rating($input, 'experience_sunrise_view'),
        'sunset_view' =>
            $rating($input, 'experience_sunset_view'),
        'mountain_view' =>
            $rating($input, 'experience_mountain_view'),
        'forest_view' =>
            $rating($input, 'experience_forest_view'),
        'night_sky' =>
            $rating($input, 'experience_night_sky'),
        'stargazing' =>
            $rating($input, 'experience_stargazing'),
        'quiet_evening' =>
            $rating($input, 'experience_quiet_evening'),
        'overnight_comfort' =>
            $rating($input, 'experience_overnight_comfort'),
        'extended_stay_comfort' =>
            $rating($input, 'experience_extended_stay_comfort'),
        'sensory_retreat' =>
            $rating($input, 'experience_sensory_retreat'),
        'remote_work' =>
            $rating($input, 'experience_remote_work'),
        'overall_scenery' =>
            $rating($input, 'experience_overall_scenery'),
        'recommended_overnight_stop' =>
            $rating($input, 'recommended_overnight_stop'),
        'recommended_quiet_evening' =>
            $rating($input, 'recommended_quiet_evening'),
        'recommended_extended_stay' =>
            $rating($input, 'recommended_extended_stay'),
        'recommended_sensory_retreat' =>
            $rating($input, 'recommended_sensory_retreat'),
        'recommended_stargazing' =>
            $rating($input, 'recommended_stargazing'),
        'recommended_remote_work' =>
            $rating($input, 'recommended_remote_work'),
        'recommended_solo_travel' =>
            $tri($input, 'recommended_solo_travel'),
        'recommended_families' =>
            $tri($input, 'recommended_families'),
        'recommended_large_groups' =>
            $tri($input, 'recommended_large_groups'),
        'not_recommended_for' =>
            community_clean_text(
                $input['not_recommended_for']
                ?? null
            ),
    ];

    $data = [
        'name' => $name,
        'type' => $type,
        'description' =>
            community_clean_text(
                $input['description'] ?? null
            ),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'elevation_feet' =>
            community_clean_int(
                $input['elevation_feet'] ?? null,
                -1500,
                30000
            ),
        'road' =>
            community_clean_text(
                $input['road'] ?? null,
                255
            ),
        'city' =>
            community_clean_text(
                $input['city'] ?? null,
                120
            ),
        'county' =>
            community_clean_text(
                $input['county'] ?? null,
                120
            ),
        'state' =>
            community_clean_text(
                $input['state'] ?? null,
                120
            ),
        'region' =>
            community_clean_text(
                $input['region'] ?? null,
                160
            ),
        'land_manager' =>
            community_clean_text(
                $input['land_manager'] ?? null,
                180
            ),
        'land_type' =>
            community_clean_text(
                $input['land_type'] ?? null,
                180
            ),
        'access_summary' =>
            community_clean_text(
                $input['access_summary'] ?? null
            ),
        'sensory_summary' =>
            community_clean_text(
                $input['sensory_summary'] ?? null
            ),
        'contributor_notes' =>
            community_clean_text(
                $input['contributor_notes'] ?? null
            ),
        'visited_at' =>
            community_clean_text(
                $input['visited_at'] ?? null,
                30
            ),
        'details' => $details,
        'amenities' => $amenities,
        'connectivity' => $connectivity,
        'sensory' => $sensory,
        'rules' => $rules,
        'experience' => $experience,
        'photos' => [],
    ];

    $db = db();
    $submissionId = 0;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO place_submissions
                (
                    user_id,
                    role_at_submission,
                    place_name,
                    source_type,
                    status,
                    submission_data
                )
             VALUES
                (
                    :user_id,
                    :role_at_submission,
                    :place_name,
                    :source_type,
                    :status,
                    :submission_data
                )'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':role_at_submission' =>
                community_role_at_submission($userId),
            ':place_name' => $name,
            ':source_type' => 'community-scouted',
            ':status' => 'pending',
            ':submission_data' =>
                json_encode(
                    $data,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
        ]);

        $submissionId =
            (int) $db->lastInsertId();

        if ($photoToken !== '') {
            $data['photos'] =
                llama_photo_commit_stage(
                    'add-place',
                    $userId,
                    $photoToken,
                    $submittedPhotos,
                    '/uploads/place-submissions/' .
                        $submissionId
                );

            $stmt = $db->prepare(
                'UPDATE place_submissions
                 SET submission_data = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                json_encode(
                    $data,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
                $submissionId,
            ]);
        }

        $db->commit();

        return $submissionId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($submissionId > 0) {
            llama_photo_delete_tree(
                dirname(__DIR__) .
                '/uploads/place-submissions/' .
                $submissionId
            );
        }

        throw $exception;
    }
}

function community_place_update_field_definitions(): array
{
    return [
        // Core Place record.
        'name' => [
            'label' => 'Place name',
            'group' => 'Basic information',
            'type' => 'text',
            'table' => 'places',
            'column' => 'name',
        ],
        'description' => [
            'label' => 'Description',
            'group' => 'Basic information',
            'type' => 'textarea',
            'table' => 'places',
            'column' => 'description',
        ],
        'latitude' => [
            'label' => 'Latitude',
            'group' => 'Location',
            'type' => 'float',
            'table' => 'places',
            'column' => 'latitude',
        ],
        'longitude' => [
            'label' => 'Longitude',
            'group' => 'Location',
            'type' => 'float',
            'table' => 'places',
            'column' => 'longitude',
        ],
        'elevation_feet' => [
            'label' => 'Elevation (ft)',
            'group' => 'Location',
            'type' => 'int',
            'table' => 'places',
            'column' => 'elevation_feet',
        ],
        'road' => [
            'label' => 'Road',
            'group' => 'Location',
            'type' => 'text',
            'table' => 'places',
            'column' => 'road',
        ],
        'city' => [
            'label' => 'Nearest city / locality',
            'group' => 'Location',
            'type' => 'text',
            'table' => 'places',
            'column' => 'city',
        ],
        'county' => [
            'label' => 'County',
            'group' => 'Location',
            'type' => 'text',
            'table' => 'places',
            'column' => 'county',
        ],
        'state' => [
            'label' => 'State',
            'group' => 'Location',
            'type' => 'text',
            'table' => 'places',
            'column' => 'state',
        ],
        'region' => [
            'label' => 'Region / ranger district',
            'group' => 'Location',
            'type' => 'text',
            'table' => 'places',
            'column' => 'region',
        ],
        'land_manager' => [
            'label' => 'Land manager',
            'group' => 'Location',
            'type' => 'land_manager',
            'table' => 'places',
            'column' => 'land_manager',
        ],
        'land_type' => [
            'label' => 'Land type',
            'group' => 'Location',
            'type' => 'land_type',
            'table' => 'places',
            'column' => 'land_type',
        ],
        'access_summary' => [
            'label' => 'Access summary',
            'group' => 'Summaries',
            'type' => 'textarea',
            'table' => 'places',
            'column' => 'access_summary',
        ],
        'sensory_summary' => [
            'label' => 'Sensory summary',
            'group' => 'Summaries',
            'type' => 'textarea',
            'table' => 'places',
            'column' => 'sensory_summary',
        ],

        // Site + road details.
        'details.vehicle_capacity' => [
            'label' => 'Vehicle capacity',
            'group' => 'Site and vehicle fit',
            'type' => 'int',
            'table' => 'place_details',
            'column' => 'vehicle_capacity',
        ],
        'details.max_vehicle_length_feet' => [
            'label' => 'Maximum vehicle length',
            'group' => 'Site and vehicle fit',
            'type' => 'int',
            'table' => 'place_details',
            'column' => 'max_vehicle_length_feet',
        ],
        'details.parking_surface' => [
            'label' => 'Parking surface',
            'group' => 'Site and vehicle fit',
            'type' => 'surface',
            'table' => 'place_details',
            'column' => 'parking_surface',
        ],
        'details.ground_condition' => [
            'label' => 'Ground condition',
            'group' => 'Site and vehicle fit',
            'type' => 'ground',
            'table' => 'place_details',
            'column' => 'ground_condition',
        ],
        'details.tent_camping_suitable' => [
            'label' => 'Tent camping suitable?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'tent_camping_suitable',
        ],
        'details.rv_suitable' => [
            'label' => 'RV suitable?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'rv_suitable',
        ],
        'details.trailer_suitable' => [
            'label' => 'Trailer suitable?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'trailer_suitable',
        ],
        'details.leveling_required' => [
            'label' => 'Leveling required?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'leveling_required',
        ],
        'details.turnaround_space' => [
            'label' => 'Turnaround space?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'turnaround_space',
        ],
        'details.pull_through' => [
            'label' => 'Pull-through site?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'pull_through',
        ],
        'details.back_in' => [
            'label' => 'Back-in site?',
            'group' => 'Site and vehicle fit',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'back_in',
        ],
        'details.levelness' => [
            'label' => 'Levelness',
            'group' => 'Site and vehicle fit',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'levelness',
        ],
        'details.site_open_sky' => [
            'label' => 'Open sky',
            'group' => 'Site and vehicle fit',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'site_open_sky',
        ],
        'details.tree_cover' => [
            'label' => 'Tree cover',
            'group' => 'Site and vehicle fit',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'tree_cover',
        ],
        'details.site_shade' => [
            'label' => 'Shade',
            'group' => 'Site and vehicle fit',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'site_shade',
        ],

        'details.road_surface' => [
            'label' => 'Road surface',
            'group' => 'Road access',
            'type' => 'surface',
            'table' => 'place_details',
            'column' => 'road_surface',
        ],
        'details.road_width' => [
            'label' => 'Road width',
            'group' => 'Road access',
            'type' => 'road_width',
            'table' => 'place_details',
            'column' => 'road_width',
        ],
        'details.sedan_accessible' => [
            'label' => 'Sedan accessible?',
            'group' => 'Road access',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'sedan_accessible',
        ],
        'details.high_clearance_recommended' => [
            'label' => 'High clearance recommended?',
            'group' => 'Road access',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'high_clearance_recommended',
        ],
        'details.four_wheel_drive_recommended' => [
            'label' => '4WD recommended?',
            'group' => 'Road access',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'four_wheel_drive_recommended',
        ],
        'details.water_crossings' => [
            'label' => 'Water crossings?',
            'group' => 'Road access',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'water_crossings',
        ],
        'details.seasonal_closure' => [
            'label' => 'Seasonal closure?',
            'group' => 'Road access',
            'type' => 'bool',
            'table' => 'place_details',
            'column' => 'seasonal_closure',
        ],
        'details.site_access_difficulty' => [
            'label' => 'Site access difficulty',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'site_access_difficulty',
        ],
        'details.road_overall_difficulty' => [
            'label' => 'Road difficulty',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'road_overall_difficulty',
        ],
        'details.road_stress' => [
            'label' => 'Driving stress',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'road_stress',
        ],
        'details.rocks' => [
            'label' => 'Rocks',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'rocks',
        ],
        'details.washboards' => [
            'label' => 'Washboards',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'washboards',
        ],
        'details.potholes' => [
            'label' => 'Potholes',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'potholes',
        ],
        'details.mud_risk' => [
            'label' => 'Mud risk',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'mud_risk',
        ],
        'details.steep_grades' => [
            'label' => 'Steep grades',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'steep_grades',
        ],
        'details.drop_off_exposure' => [
            'label' => 'Drop-off exposure',
            'group' => 'Road access',
            'type' => 'rating',
            'table' => 'place_details',
            'column' => 'drop_off_exposure',
        ],

        // Amenities.
        'amenities.toilets' => [
            'label' => 'Toilets',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'toilets',
        ],
        'amenities.potable_water' => [
            'label' => 'Potable water',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'potable_water',
        ],
        'amenities.trash' => [
            'label' => 'Trash service',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'trash',
        ],
        'amenities.fire_ring' => [
            'label' => 'Fire ring',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'fire_ring',
        ],
        'amenities.picnic_table' => [
            'label' => 'Picnic table',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'picnic_table',
        ],
        'amenities.bear_box' => [
            'label' => 'Bear box',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'bear_box',
        ],
        'amenities.showers' => [
            'label' => 'Showers',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'showers',
        ],
        'amenities.electricity' => [
            'label' => 'Electricity',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'electricity',
        ],
        'amenities.dump_station' => [
            'label' => 'Dump station',
            'group' => 'Amenities',
            'type' => 'bool',
            'table' => 'place_amenities',
            'column' => 'dump_station',
        ],

        // Connectivity.
        'connectivity.overall' => [
            'label' => 'Overall cell service',
            'group' => 'Connectivity',
            'type' => 'rating',
            'table' => 'place_connectivity',
            'column' => 'overall',
        ],
        'connectivity.t_mobile' => [
            'label' => 'T-Mobile',
            'group' => 'Connectivity',
            'type' => 'rating',
            'table' => 'place_connectivity',
            'column' => 't_mobile',
        ],
        'connectivity.verizon' => [
            'label' => 'Verizon',
            'group' => 'Connectivity',
            'type' => 'rating',
            'table' => 'place_connectivity',
            'column' => 'verizon',
        ],
        'connectivity.att' => [
            'label' => 'AT&T',
            'group' => 'Connectivity',
            'type' => 'rating',
            'table' => 'place_connectivity',
            'column' => 'att',
        ],
        'connectivity.starlink' => [
            'label' => 'Starlink',
            'group' => 'Connectivity',
            'type' => 'rating',
            'table' => 'place_connectivity',
            'column' => 'starlink',
        ],
        'connectivity.starlink_tested' => [
            'label' => 'Starlink actually tested?',
            'group' => 'Connectivity',
            'type' => 'bool',
            'table' => 'place_connectivity',
            'column' => 'starlink_tested',
        ],
        'connectivity.starlink_note' => [
            'label' => 'Starlink notes',
            'group' => 'Connectivity',
            'type' => 'textarea',
            'table' => 'place_connectivity',
            'column' => 'starlink_note',
        ],

        // Sensory daytime/nighttime.
        'sensory.daytime.noise' => [
            'label' => 'Daytime noise',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'noise',
            'period' => 'daytime',
        ],
        'sensory.daytime.traffic' => [
            'label' => 'Daytime traffic',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'traffic',
            'period' => 'daytime',
        ],
        'sensory.daytime.crowds' => [
            'label' => 'Daytime crowds',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'crowds',
            'period' => 'daytime',
        ],
        'sensory.daytime.privacy' => [
            'label' => 'Daytime privacy',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'privacy',
            'period' => 'daytime',
        ],
        'sensory.daytime.sensory_comfort' => [
            'label' => 'Daytime sensory comfort',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'sensory_comfort',
            'period' => 'daytime',
        ],
        'sensory.nighttime.noise' => [
            'label' => 'Nighttime noise',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'noise',
            'period' => 'nighttime',
        ],
        'sensory.nighttime.traffic' => [
            'label' => 'Nighttime traffic',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'traffic',
            'period' => 'nighttime',
        ],
        'sensory.nighttime.crowds' => [
            'label' => 'Nighttime crowds',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'crowds',
            'period' => 'nighttime',
        ],
        'sensory.nighttime.privacy' => [
            'label' => 'Nighttime privacy',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'privacy',
            'period' => 'nighttime',
        ],
        'sensory.nighttime.sensory_comfort' => [
            'label' => 'Nighttime sensory comfort',
            'group' => 'Sensory profile',
            'type' => 'rating',
            'table' => 'place_sensory',
            'column' => 'sensory_comfort',
            'period' => 'nighttime',
        ],

        // Rules and season.
        'rules.winter_access' => [
            'label' => 'Winter access?',
            'group' => 'Rules and seasons',
            'type' => 'bool',
            'table' => 'place_rules',
            'column' => 'winter_access',
        ],
        'rules.snow_risk' => [
            'label' => 'Snow risk',
            'group' => 'Rules and seasons',
            'type' => 'rating',
            'table' => 'place_rules',
            'column' => 'snow_risk',
        ],
        'rules.mud_season_risk' => [
            'label' => 'Mud-season risk',
            'group' => 'Rules and seasons',
            'type' => 'rating',
            'table' => 'place_rules',
            'column' => 'mud_season_risk',
        ],
        'rules.monsoon_risk' => [
            'label' => 'Monsoon risk',
            'group' => 'Rules and seasons',
            'type' => 'rating',
            'table' => 'place_rules',
            'column' => 'monsoon_risk',
        ],
        'rules.seasonal_access_note' => [
            'label' => 'Seasonal access notes',
            'group' => 'Rules and seasons',
            'type' => 'textarea',
            'table' => 'place_rules',
            'column' => 'seasonal_access_note',
        ],
        'rules.overnight_camping_allowed' => [
            'label' => 'Overnight camping allowed?',
            'group' => 'Rules and seasons',
            'type' => 'bool',
            'table' => 'place_rules',
            'column' => 'overnight_camping_allowed',
        ],
        'rules.dispersed_camping_allowed' => [
            'label' => 'Dispersed camping allowed?',
            'group' => 'Rules and seasons',
            'type' => 'bool',
            'table' => 'place_rules',
            'column' => 'dispersed_camping_allowed',
        ],
        'rules.stay_limit_days' => [
            'label' => 'Stay limit (days)',
            'group' => 'Rules and seasons',
            'type' => 'int',
            'table' => 'place_rules',
            'column' => 'stay_limit_days',
        ],
        'rules.permit_required' => [
            'label' => 'Permit required?',
            'group' => 'Rules and seasons',
            'type' => 'bool',
            'table' => 'place_rules',
            'column' => 'permit_required',
        ],
        'rules.fee' => [
            'label' => 'Fee',
            'group' => 'Rules and seasons',
            'type' => 'float',
            'table' => 'place_rules',
            'column' => 'fee',
        ],
        'rules.campfire_allowed' => [
            'label' => 'Campfire allowed?',
            'group' => 'Rules and seasons',
            'type' => 'bool',
            'table' => 'place_rules',
            'column' => 'campfire_allowed',
        ],
    ];
}

function community_update_current_values(
    PDO $db,
    int $placeId
): array {
    $definitions =
        community_place_update_field_definitions();

    $values = [];

    $tableRows = [];

    foreach ($definitions as $path => $definition) {
        $table =
            (string) $definition['table'];

        $column =
            (string) $definition['column'];

        if ($table === 'places') {
            if (!isset($tableRows['places'])) {
                $stmt = $db->prepare(
                    'SELECT *
                     FROM places
                     WHERE id = ?
                     LIMIT 1'
                );
                $stmt->execute([$placeId]);
                $tableRows['places'] =
                    $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            $values[$path] =
                $tableRows['places'][$column]
                ?? null;

            continue;
        }

        if ($table === 'place_sensory') {
            $period =
                (string) ($definition['period'] ?? '');

            $cacheKey =
                'place_sensory:' . $period;

            if (!isset($tableRows[$cacheKey])) {
                $stmt = $db->prepare(
                    'SELECT *
                     FROM place_sensory
                     WHERE place_id = ?
                       AND period = ?
                     LIMIT 1'
                );
                $stmt->execute([
                    $placeId,
                    $period,
                ]);
                $tableRows[$cacheKey] =
                    $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            $values[$path] =
                $tableRows[$cacheKey][$column]
                ?? null;

            continue;
        }

        if (!isset($tableRows[$table])) {
            $stmt = $db->prepare(
                "SELECT *
                 FROM `$table`
                 WHERE place_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$placeId]);
            $tableRows[$table] =
                $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $values[$path] =
            $tableRows[$table][$column]
            ?? null;
    }

    return $values;
}

function community_find_place_for_update(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT *
         FROM places
         WHERE slug = :slug
           AND status IN ('active', 'featured')
         LIMIT 1"
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function community_open_update_for_user(
    int $userId,
    int $placeId
): ?array {
    $stmt = db()->prepare(
        "SELECT id, status, submitted_at
         FROM place_update_submissions
         WHERE user_id = :user_id
           AND place_id = :place_id
           AND status IN ('pending', 'needs-changes')
         ORDER BY submitted_at DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':user_id' => $userId,
        ':place_id' => $placeId,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function community_parse_update_value(
    mixed $raw,
    string $type
): mixed {
    if ($raw === '__NULL__') {
        return null;
    }

    if ($type === 'bool') {
        if ((string) $raw === '1') {
            return true;
        }

        if ((string) $raw === '0') {
            return false;
        }

        return null;
    }

    if ($type === 'rating') {
        $value = (int) $raw;

        return $value >= 1 && $value <= 5
            ? $value
            : null;
    }

    if ($type === 'int') {
        return community_clean_int(
            $raw,
            -100000,
            100000
        );
    }

    if ($type === 'float') {
        return community_clean_float(
            $raw,
            -1000000,
            1000000
        );
    }

    return community_clean_text(
        $raw,
        $type === 'textarea'
            ? 5000
            : 500
    );
}

function submit_place_update(
    int $userId,
    array $place,
    array $input
): int {
    $placeId =
        (int) ($place['id'] ?? 0);

    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'Invalid place.'
        );
    }

    if (
        community_open_update_for_user(
            $userId,
            $placeId
        )
    ) {
        throw new RuntimeException(
            'You already have an open update for this place.'
        );
    }

    $definitions =
        community_place_update_field_definitions();

    $current =
        community_update_current_values(
            db(),
            $placeId
        );

    $selected =
        is_array(
            $input['change_fields']
            ?? null
        )
            ? array_values(
                $input['change_fields']
            )
            : [];

    $values =
        is_array(
            $input['field_value']
            ?? null
        )
            ? $input['field_value']
            : [];

    $proposed = [];
    $original = [];

    foreach ($selected as $path) {
        $path =
            (string) $path;

        if (!isset($definitions[$path])) {
            continue;
        }

        $definition =
            $definitions[$path];

        $raw =
            $values[$path]
            ?? '__NULL__';

        $newValue =
            community_parse_update_value(
                $raw,
                (string) $definition['type']
            );

        $oldValue =
            $current[$path]
            ?? null;

        $oldComparable =
            is_bool($oldValue)
                ? ($oldValue ? '1' : '0')
                : (
                    $oldValue === null
                        ? null
                        : (string) $oldValue
                );

        $newComparable =
            is_bool($newValue)
                ? ($newValue ? '1' : '0')
                : (
                    $newValue === null
                        ? null
                        : (string) $newValue
                );

        if (
            $oldComparable
            === $newComparable
        ) {
            continue;
        }

        $proposed[$path] = $newValue;
        $original[$path] = $oldValue;
    }

    $photoToken =
        trim(
            (string) (
                $input['photo_stage_token']
                ?? ''
            )
        );

    $submittedPhotos =
        llama_photo_decode_form_photos(
            $input['photos_json']
            ?? '[]'
        );

    if (
        !$proposed
        && !$submittedPhotos
    ) {
        throw new InvalidArgumentException(
            'Select at least one field that changed, or add a current photo.'
        );
    }

    if (
        $submittedPhotos
        && $photoToken === ''
    ) {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }

    $visitedAt =
        community_clean_text(
            $input['visited_at']
            ?? null,
            30
        );

    $notes =
        community_clean_text(
            $input['contributor_notes']
            ?? null
        );

    $db = db();
    $updateId = 0;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO place_update_submissions
                (
                    place_id,
                    user_id,
                    update_type,
                    status,
                    role_at_submission,
                    visited_at,
                    proposed_changes,
                    original_values,
                    photos,
                    contributor_notes
                )
             VALUES
                (
                    :place_id,
                    :user_id,
                    :update_type,
                    :status,
                    :role_at_submission,
                    :visited_at,
                    :proposed_changes,
                    :original_values,
                    :photos,
                    :contributor_notes
                )'
        );

        $stmt->execute([
            ':place_id' => $placeId,
            ':user_id' => $userId,
            ':update_type' => 'update',
            ':status' => 'pending',
            ':role_at_submission' =>
                community_role_at_submission(
                    $userId
                ),
            ':visited_at' =>
                $visitedAt !== null
                    ? $visitedAt .
                        (
                            strlen($visitedAt) === 10
                                ? ' 00:00:00'
                                : ''
                        )
                    : null,
            ':proposed_changes' =>
                json_encode(
                    $proposed,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
            ':original_values' =>
                json_encode(
                    $original,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
            ':photos' => '[]',
            ':contributor_notes' => $notes,
        ]);

        $updateId =
            (int) $db->lastInsertId();

        if ($photoToken !== '') {
            $committedPhotos =
                llama_photo_commit_stage(
                    'update-place',
                    $userId,
                    $photoToken,
                    $submittedPhotos,
                    '/uploads/place-updates/' .
                        $updateId
                );

            $photoUpdate =
                $db->prepare(
                    'UPDATE place_update_submissions
                     SET photos = :photos
                     WHERE id = :id
                       AND user_id = :user_id'
                );

            $photoUpdate->execute([
                ':photos' =>
                    json_encode(
                        $committedPhotos,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                        | JSON_THROW_ON_ERROR
                    ),
                ':id' => $updateId,
                ':user_id' => $userId,
            ]);
        }

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($updateId > 0) {
            llama_photo_remove_tree(
                dirname(__DIR__) .
                '/uploads/place-updates/' .
                $updateId
            );
        }

        throw $exception;
    }

    return $updateId;
}

function community_submissions_for_user(int $userId): array
{
    $newStmt = db()->prepare(
        "SELECT id, place_name AS name, status, submitted_at, reviewed_at, review_notes
         FROM place_submissions
         WHERE user_id = :user_id
         ORDER BY submitted_at DESC"
    );
    $newStmt->execute([':user_id' => $userId]);
    $newPlaces = $newStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($newPlaces as &$row) {
        $row['kind'] = 'new-place';
        $row['label'] = 'New place';
        $row['slug'] = null;
    }
    unset($row);

    $updateStmt = db()->prepare(
        "SELECT u.id, p.name, p.slug, u.status, u.submitted_at, u.reviewed_at, u.review_notes
         FROM place_update_submissions u
         JOIN places p ON p.id = u.place_id
         WHERE u.user_id = :user_id
         ORDER BY u.submitted_at DESC"
    );
    $updateStmt->execute([':user_id' => $userId]);
    $updates = $updateStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($updates as &$row) {
        $row['kind'] = 'update';
        $row['label'] = 'Place update';
    }
    unset($row);

    $all = array_merge($newPlaces, $updates);
    usort($all, static fn (array $a, array $b): int => strcmp((string) $b['submitted_at'], (string) $a['submitted_at']));

    return $all;
}

function community_submission_counts(int $userId): array
{
    $all = community_submissions_for_user($userId);
    $counts = ['total' => count($all), 'open' => 0];

    foreach ($all as $item) {
        if (in_array((string) ($item['status'] ?? ''), ['pending', 'needs-changes', 'investigating'], true)) {
            $counts['open']++;
        }
    }

    return $counts;
}
