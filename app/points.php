<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT POINTS

   points_policy is the single configuration source for all
   point values.

   Historical awards remain in points_ledger and
   place_contributions. Changing policy affects future awards
   and estimates only.
   ========================================================= */

function llama_points_policy(
    PDO $db,
    string $key,
    int $default = 0
): int {
    try {
        $stmt = $db->prepare(
            'SELECT points_value
             FROM points_policy
             WHERE policy_key = ?
             LIMIT 1'
        );

        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false
            ? $default
            : (int) $value;
    } catch (Throwable) {
        return $default;
    }
}

function llama_points_policy_required(
    PDO $db,
    string $key
): int {
    $stmt = $db->prepare(
        'SELECT points_value
         FROM points_policy
         WHERE policy_key = ?
         LIMIT 1'
    );

    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    if ($value === false) {
        throw new RuntimeException(
            'Points policy setting "' . $key . '" is not configured.'
        );
    }

    return max(0, (int) $value);
}

function llama_points_total(
    PDO $db,
    int $userId
): int {
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points), 0)
             FROM points_ledger
             WHERE user_id = ?'
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points_awarded), 0)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"'
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}

function llama_points_record(
    PDO $db,
    int $userId,
    int $points,
    string $sourceType,
    ?int $sourceId,
    string $reason,
    ?int $awardedBy = null,
    ?int $contributionId = null
): int {
    if ($points === 0) {
        return 0;
    }

    if ($contributionId) {
        $exists = $db->prepare(
            'SELECT id
             FROM points_ledger
             WHERE contribution_id = ?
             LIMIT 1'
        );

        $exists->execute([$contributionId]);

        $existingId = (int) ($exists->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO points_ledger (
            user_id,
            points,
            source_type,
            source_id,
            contribution_id,
            reason,
            awarded_by,
            created_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );

    $stmt->execute([
        $userId,
        $points,
        $sourceType,
        $sourceId,
        $contributionId,
        $reason,
        $awardedBy,
    ]);

    return (int) $db->lastInsertId();
}


/* =========================================================
   NEW PLACE SCORING POLICY

   Basic information, location, and photos establish whether a
   Place is ready to submit. They do not add contribution
   points.

   The ten categories below are the point-bearing categories.
   Each category's maximum comes from points_policy.
   ========================================================= */

function llama_points_new_place_categories(): array
{
    return [
        'site_vehicle' => [
            'label' => 'Site + Vehicle',
            'policy_key' => 'new_place_site_vehicle',
            'mode' => 'weighted',
            'fields' => [
                'vehicle_capacity',
                'max_vehicle_length_feet',
                'parking_surface',
                'ground_condition',
                'tent_camping_suitable',
                'rv_suitable',
                'trailer_suitable',
                'leveling_required',
                'turnaround_space',
                'pull_through',
                'back_in',
                'levelness',
                'site_open_sky',
                'tree_cover',
                'site_shade',
            ],
        ],

        'road_access' => [
            'label' => 'Road Access',
            'policy_key' => 'new_place_road_access',
            'mode' => 'weighted',
            'fields' => [
                'road_surface',
                'road_width',
                'sedan_accessible',
                'high_clearance_recommended',
                'four_wheel_drive_recommended',
                'water_crossings',
                'downed_tree_risk',
                'seasonal_closure',
                'site_access_difficulty',
                'road_overall_difficulty',
                'road_stress',
                'rocks',
                'washboards',
                'potholes',
                'mud_risk',
                'steep_grades',
                'drop_off_exposure',
            ],
        ],

        'amenities' => [
            'label' => 'Amenities',
            'policy_key' => 'new_place_amenities',
            'mode' => 'any',
            'fields' => [
                'amenity_none',
                'warning_no_amenities',
                'amenity_toilets',
                'amenity_potable_water',
                'amenity_trash',
                'amenity_fire_ring',
                'amenity_picnic_table',
                'amenity_bear_box',
                'amenity_showers',
                'amenity_electricity',
                'amenity_dump_station',
                'amenity_food_storage_required',
            ],
        ],

        'connectivity' => [
            'label' => 'Connectivity',
            'policy_key' => 'new_place_connectivity',
            'mode' => 'any',
            'fields' => [
                'connectivity_overall',
                'connectivity_t_mobile',
                'connectivity_verizon',
                'connectivity_att',
                'connectivity_other_cell',
                'connectivity_starlink',
                'connectivity_starlink_tested',
                'connectivity_starlink_note',
            ],
        ],

        'sensory' => [
            'label' => 'Sensory',
            'policy_key' => 'new_place_sensory',
            'mode' => 'weighted',
            'fields' => [
                'daytime_noise',
                'daytime_traffic',
                'daytime_crowds',
                'daytime_privacy',
                'daytime_light_pollution',
                'daytime_sensory_comfort',
                'daytime_social_interaction',
                'nighttime_noise',
                'nighttime_traffic',
                'nighttime_crowds',
                'nighttime_privacy',
                'nighttime_light_pollution',
                'nighttime_sensory_comfort',
                'nighttime_social_interaction',
                'sensory_dust_from_traffic',
                'sensory_generator_noise',
                'sensory_aircraft_noise',
                'sensory_road_noise',
                'sensory_human_activity',
                'sensory_wildlife_noise',
                'sensory_wind_noise',
                'sensory_smoke_risk',
                'sensory_strong_odors',
                'sensory_visual_exposure',
                'sensory_predictability',
            ],
        ],

        'environment' => [
            'label' => 'Environment',
            'policy_key' => 'new_place_environment',
            'mode' => 'weighted',
            'fields' => [
                'environment_forest',
                'environment_mountains',
                'environment_water_nearby',
                'environment_water_view',
                'environment_mountain_view',
                'environment_forest_view',
                'environment_wildlife',
                'environment_bugs',
                'environment_wind_exposure',
                'environment_sun_exposure',
                'environment_shade',
                'environment_open_sky',
            ],
        ],

        'accessibility' => [
            'label' => 'Accessibility',
            'policy_key' => 'new_place_accessibility',
            'mode' => 'weighted',
            'fields' => [
                'wheelchair_friendly',
                'mobility_device_friendly',
                'flat_walking_surface',
                'step_free_access',
                'accessible_toilet',
                'accessible_picnic_table',
                'walking_distance_from_vehicle',
            ],
        ],

        'safety_warnings' => [
            'label' => 'Safety + Warnings',
            'policy_key' => 'new_place_safety_warnings',
            'mode' => 'weighted',
            'fields' => [
                'felt_safe_daytime',
                'felt_safe_nighttime',
                'flash_flood_risk',
                'wildfire_risk',
                'fall_hazard',
                'cliff_exposure',
                'rockfall_risk',
                'wildlife_risk',
                'traffic_hazard',
                'emergency_access',
                'warning_exposed_to_road',
                'warning_zero_privacy',
                'warning_passing_vehicle_dust',
                'warning_possible_downed_trees',
                'warning_no_tent_camping',
                'warning_limited_vehicle_length',
                'warning_leveling_may_be_required',
                'warning_no_amenities',
                'warning_motorized_recreation_traffic',
                'warning_blind_turn_traffic_nearby',
            ],
        ],

        'seasons_rules_services' => [
            'label' => 'Seasons + Rules + Services',
            'policy_key' => 'new_place_seasons_rules_services',
            'mode' => 'weighted',
            'fields' => [
                'best_months',
                'winter_access',
                'snow_risk',
                'mud_season_risk',
                'monsoon_risk',
                'seasonal_access_note',
                'overnight_camping_allowed',
                'dispersed_camping_allowed',
                'permit_required',
                'campfire_allowed',
                'existing_sites_encouraged',
                'pack_it_in_pack_it_out',
                'residential_use_prohibited',
                'stay_limit_days',
                'fee',
                'current_fire_restrictions_url',
                'nearest_town',
                'nearest_fuel',
                'nearest_grocery',
                'nearest_water',
                'nearest_toilet',
                'nearest_hospital',
            ],
        ],

        'experience_recommendations' => [
            'label' => 'Experience + Recommendations',
            'policy_key' => 'new_place_experience_recommendations',
            'mode' => 'weighted',
            'fields' => [
                'experience_sunrise_view',
                'experience_sunset_view',
                'experience_mountain_view',
                'experience_forest_view',
                'experience_night_sky',
                'experience_stargazing',
                'experience_quiet_evening',
                'experience_overnight_comfort',
                'experience_extended_stay_comfort',
                'experience_sensory_retreat',
                'experience_remote_work',
                'experience_overall_scenery',
                'recommended_overnight_stop',
                'recommended_quiet_evening',
                'recommended_extended_stay',
                'recommended_sensory_retreat',
                'recommended_stargazing',
                'recommended_remote_work',
                'recommended_solo_travel',
                'recommended_families',
                'recommended_large_groups',
                'not_recommended_for',
            ],
        ],
    ];
}

function llama_points_has_answer(
    array $data,
    string $key
): bool {
    if (!array_key_exists($key, $data)) {
        return false;
    }

    $value = $data[$key];

    if (is_array($value)) {
        return count($value) > 0;
    }

    return trim((string) $value) !== '';
}

function llama_points_new_place_max_points(
    PDO $db
): int {
    $total = 0;

    foreach (llama_points_new_place_categories() as $category) {
        $total +=
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );
    }

    return $total;
}

function llama_points_estimate_new_place(
    PDO $db,
    array $data,
    int $photoCount
): array {
    $categoryRows = [];
    $estimatedPoints = 0;
    $maxPoints = 0;
    $answeredTotal = 0;
    $fieldTotal = 0;

    foreach (llama_points_new_place_categories() as $slug => $category) {
        $fields = (array) $category['fields'];
        $answered = 0;

        foreach ($fields as $field) {
            if (
                llama_points_has_answer(
                    $data,
                    (string) $field
                )
            ) {
                $answered++;
            }
        }

        $fieldCount = count($fields);
        $fieldTotal += $fieldCount;
        $answeredTotal += $answered;

        $categoryMax =
            llama_points_policy_required(
                $db,
                (string) $category['policy_key']
            );

        $maxPoints += $categoryMax;

        if ((string) $category['mode'] === 'any') {
            $points =
                $answered > 0
                    ? $categoryMax
                    : 0;
        } else {
            $points =
                $fieldCount > 0
                    ? (int) round(
                        $categoryMax
                        * ($answered / $fieldCount)
                    )
                    : 0;
        }

        $points =
            max(
                0,
                min(
                    $categoryMax,
                    $points
                )
            );

        $estimatedPoints += $points;

        $categoryRows[] = [
            'slug' => $slug,
            'label' => (string) $category['label'],
            'policy_key' => (string) $category['policy_key'],
            'mode' => (string) $category['mode'],
            'answered' => $answered,
            'total' => $fieldCount,
            'points' => $points,
            'max_points' => $categoryMax,
            'started' => $answered > 0,
        ];
    }

    $otherFields = [
        'name',
        'type',
        'visited_at',
        'description',
        'latitude',
        'longitude',
        'elevation_feet',
        'road',
        'city',
        'county',
        'state',
        'region',
        'land_manager',
        'land_type',
        'access_summary',
        'sensory_summary',
        'contributor_notes',
    ];

    foreach ($otherFields as $field) {
        $fieldTotal++;

        if (
            llama_points_has_answer(
                $data,
                $field
            )
        ) {
            $answeredTotal++;
        }
    }

    $fieldTotal++;

    if ($photoCount > 0) {
        $answeredTotal++;
    }

    $missingMinimum = [];

    if (!llama_points_has_answer($data, 'name')) {
        $missingMinimum[] =
            'Basic information';
    }

    if (
        !llama_points_has_answer(
            $data,
            'latitude'
        )
        ||
        !llama_points_has_answer(
            $data,
            'longitude'
        )
    ) {
        $missingMinimum[] =
            'Location';
    }

    if ($photoCount < 1) {
        $missingMinimum[] =
            '1 photo';
    }

    return [
        'completion_percent' =>
            $fieldTotal > 0
                ? (int) round(
                    100
                    * ($answeredTotal / $fieldTotal)
                )
                : 0,

        'estimated_points' =>
            max(
                0,
                min(
                    $maxPoints,
                    $estimatedPoints
                )
            ),

        'max_points' =>
            $maxPoints,

        'categories_started' =>
            count(
                array_filter(
                    $categoryRows,
                    static fn (array $row): bool =>
                        !empty($row['started'])
                )
            ),

        'category_count' =>
            count($categoryRows),

        'categories' =>
            $categoryRows,

        'minimum_ready' =>
            !$missingMinimum,

        'missing_minimum' =>
            $missingMinimum,
    ];
}
