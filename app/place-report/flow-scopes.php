<?php

declare(strict_types=1);

/**
 * Canonical Place / Area / Site ownership for every Scout Report field.
 *
 * Ownership groups:
 * - place: Place only.
 * - place_area: Place or Area. A Site may inherit the nearest recorded value
 *   but cannot record its own override for this field.
 * - site: Site only. Never inherits from Place or Area.
 * - all: Place, Area, or Site. The nearest recorded value wins.
 *
 * Explicit No, Unknown, numeric 0, and an explicitly recorded empty list are
 * answers. Only a genuinely unrecorded field inherits from an ancestor.
 */
function llama_place_report_scope_groups(): array
{
    return [
        'place' => ['place'],
        'place_area' => ['place', 'area'],
        'site' => ['site'],
        'all' => ['place', 'area', 'site'],
    ];
}

/**
 * Current canonical ownership inventory.
 *
 * Keep this list semantic, not presentation-driven. Layout groups can move
 * without changing which entity owns the underlying fact.
 */
function llama_place_report_scope_overrides(): array
{
    $byGroup = [
        'place' => [
            /*
             * Place identity and geography.
             */
            'name',
            'type',
            'description',
            'latitude',
            'longitude',
            'elevation_feet',
            'road',
            'city',
            'county',
            'state',
            'region',
            'land_type',
            'land_manager',

            /*
             * Getting there describes the route to the Place. Site parking
             * and Site access have separate Site-owned fields below.
             */
            'road_surface',
            'road_width',
            'approach_road_condition',
            'approach_road_hazards',
            'approach_deep_ruts',
            'road_overall_difficulty',
            'road_stress',
            'rocks',
            'washboards',
            'potholes',
            'mud_risk',
            'steep_grades',
            'drop_off_exposure',
            'water_crossings',
            'downed_tree_risk',
            'sedan_accessible',
            'high_clearance_recommended',
            'four_wheel_drive_recommended',
            'approach_width_restricted',
            'approach_narrowest_width_feet',
            'approach_turnaround_available',
            'approach_overhead_obstacles',
            'approach_access_restrictions',
            'approach_year_round',
            'approach_seasonal_closure',
            'approach_closure_month',
            'approach_closure_day',
            'approach_reopening_month',
            'approach_reopening_day',
            'approach_winter_maintenance',
            'approach_snow_chains',
            'approach_seasonal_restrictions',
            'approach_road_conditions_url',
            'approach_road_type',
            'approach_permit_required',
            'approach_fee_required',
            'approach_road_fee',
            'approach_gate_present',
            'approach_gate_access_type',
            'approach_gate_opens',
            'approach_gate_closes',
            'approach_restriction_details',
            'approach_access_info_url',

            /*
             * Place-wide source/context facts.
             */
            'place_accessible',
            'accessibility_notes',
            'facility_map_url',
            'activities',
            'access_summary',
            'emergency_access',

            /*
             * Base/default pricing. Detailed mapped Area/Site nightly rates
             * live in place_map_feature_rates and supersede this fallback.
             */
            'fee',

            /*
             * Nearby services are Place-level context.
             */
            'nearest_town',
            'nearest_fuel',
            'nearest_ev_charging',
            'nearest_alcohol_sales',
            'nearest_propane',
            'nearest_grocery',
            'nearest_water',
            'nearest_toilet',
            'nearest_hospital',

            /*
             * Place operating schedule and closure observations.
             */
            'place_open_year_round',
            'place_operating_start_month',
            'place_operating_start_day',
            'place_operating_end_month',
            'place_operating_end_day',
            'place_open_24_hours',
            'place_opening_time',
            'place_closing_time',
            'place_entrance_gate',
            'place_gate_access_type',
            'place_gate_opening_time',
            'place_gate_closing_time',
            'place_reported_operating_status',
            'place_closure_reason',
            'place_closure_began',
            'place_expected_reopening',
            'place_closure_source_url',
            'place_closure_review_date',
            'place_closure_notes',

        ],

        'place_area' => [
            /*
             * Shared campground/Area capacity and seasonal operation.
             */
            'campsite_count',
            'season_begins',
            'season_ends',
            'best_months',
            'winter_access',
            'seasonal_closure',
            'seasonal_access_note',
            'snow_risk',
            'mud_season_risk',
            'monsoon_risk',
            'hurricane_risk',
            'heat_season_risk',

            /*
             * Rules whose meaning is inherently Place/Area-wide rather than a
             * physical attribute of one campsite.
             */
            'dispersed_camping_allowed',
            'designated_sites_only',
            'stay_limit_days',
            'membership_required',
            'membership_url',
            'membership_fee',
            'entrance_facility_fee',
            'parking_fee',
            'collecting_firewood',
            'drone_use_legal',
            'target_shooting_allowed',
            'food_storage_required',
            'existing_sites_encouraged',
            'pack_it_in_pack_it_out',
            'residential_use_prohibited',
            'current_fire_restrictions_url',
            'camping_permit_url',
            'fire_activity_permit_url',
            'overnight_vehicle_tent_permit_url',

            /*
             * Shared facilities. A Site's own equipment has separate Site
             * fields and must not inherit as if it were a shared amenity.
             */
            'amenity_toilets',
            'amenity_potable_water',
            'amenity_water_filling_station',
            'amenity_dishwashing_station',
            'amenity_trash',
            'amenity_recycling',
            'amenity_showers',
            'amenity_accessible_showers',
            'amenity_laundry',
            'amenity_wifi',
            'amenity_dump_station',
            'amenity_electricity',
            'amenity_picnic_shelter',
            'amenity_playground',
            'amenity_dog_park',
            'amenity_amphitheater',
            'amenity_fishing_pier',
            'amenity_lake_access',
            'amenity_river_access',
            'amenity_trailhead',
            'amenity_trailhead_parking',
            'amenity_camp_store',
            'amenity_campground_host',
            'shared_accessible_facilities',
            'shared_accessible_facility_types',
            'accessible_toilet',
        ],

        'site' => [
            /*
             * Canonical campsite identity and physical Site facts.
             * These never inherit from the Place.
             */
            'site_number',
            'site_accessible',
            'max_people',
            'vehicle_capacity',
            'max_vehicle_length_feet',
            'levelness',
            'parking_length_feet',
            'site_length_feet',
            'site_width_feet',
            'overhead_clearance_feet',
            'tent_pad',
            'tent_pad_length_feet',
            'tent_pad_width_feet',
            'parking_size',
            'double_driveway',
            'hike_in_distance_feet',
            'capacity_size_rating',
            'site_hookups_available',
            'hookup_electric',
            'hookup_electric_service',
            'hookup_water',
            'hookup_sewer',
            'trailer_suitable',
            'rv_suitable',
            'max_rv_length_feet',
            'tent_camping_suitable',
            'pull_through',
            'back_in',
            'turnaround_space',
            'leveling_required',
            'site_access_difficulty',
            'walking_distance_from_vehicle',
            'wheelchair_friendly',
            'mobility_device_friendly',
            'flat_walking_surface',
            'step_free_access',
            'site_shade',
            'site_open_sky',

            /*
             * Equipment physically present at one Site.
             */
            'amenity_picnic_table',
            'accessible_picnic_table',
            'amenity_fire_ring',
            'amenity_grill',
            'amenity_bear_box',
            'amenity_lantern_post',
            'site_potable_water',
            'site_lake_access',
            'site_river_access',
            'site_trail_access',
            'site_wifi_rating',
            'site_amenities_notes',

            /*
             * Ratings whose labels explicitly refer to the individual Site.
             */
            'site_rating',
            'condition_rating',
            'location_rating',
        ],

        'all' => [
            /*
             * Overnight permissions and operating details may have legitimate
             * Site exceptions. They inherit until an Area or Site records an
             * explicit answer.
             */
            'overnight_camping_allowed',
            'tent_camping_allowed',
            'rv_camping_allowed',
            'car_truck_camping',
            'overnight_restrictions',
            'camping_conditional_details',
            'reservation_required',
            'reservation_url',
            'reservation_fee',
            'check_in_required',
            'check_in_begins',
            'check_out_required',
            'checkout_ends',

            /*
             * Rules that can genuinely differ by Area or individual Site.
             */
            'pets_allowed',
            'dogs_required_to_be_leashed',
            'generator_restrictions',
            'generator_prohibited',
            'generator_quiet_hours',
            'generator_quiet_hours_begin',
            'generator_quiet_hours_end',
            'generator_run_restrictions',
            'generator_max_run_hours',
            'generator_restriction_details',
            'campfire_allowed',
            'fire_activities_conditional_details',
            'place_charcoal_grills_allowed',
            'place_propane_fire_pits_allowed',
            'place_quiet_hours',
            'place_quiet_hours_begin',
            'place_quiet_hours_end',
            'place_alcohol_permitted',
            'place_additional_rules',

            /*
             * Shared parking facts can describe an entire Place, a mapped Area,
             * or one individual Site. At Place/Area scope they act as the shared
             * or default condition; a Site can record a more specific override.
             *
             * Individual capacity, parking-space length, Site dimensions, and
             * Site overhead clearance remain Site-only because those facts
             * identify one physical space.
             */
            'parking_surface',
            'ground_condition',
            'parking_grade',

            /*
             * Environment, sensory, safety and experience can describe a
             * single-location Place, a mapped Area, or a selected Site.
             */
            'tree_cover',
            'landscape_primary',
            'landscape_details',
            'landscape_views',
            'environment_water_nearby',
            'environment_wildlife',
            'environment_bugs',
            'environment_wind_exposure',
            'environment_sun_exposure',
            'environment_shade',
            'environment_open_sky',
            'critter_activity',

            'felt_safe_daytime_rating',
            'felt_safe_nighttime_rating',
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
            'sensory_train_noise',
            'sensory_dog_barking',
            'sensory_road_noise',
            'sensory_human_activity',
            'sensory_wildlife_noise',
            'sensory_wind_noise',
            'sensory_smoke_risk',
            'sensory_strong_odors',
            'sensory_visual_exposure',
            'sensory_predictability',
            'road_exposure',

            /*
             * Connectivity can change materially between Areas and Sites.
             */
            'connectivity_overall',
            'connectivity_t_mobile',
            'connectivity_verizon',
            'connectivity_att',
            'connectivity_other_cell',
            'connectivity_satellite',
            'connectivity_services_tested',
            'connectivity_starlink_note',

            'felt_safe_daytime',
            'felt_safe_nighttime',
            'flash_flood_risk',
            'wildfire_risk',
            'fall_hazard',
            'cliff_exposure',
            'rockfall_risk',
            'wildlife_risk',
            'traffic_hazard',
            'warning_possible_downed_trees',
            'warning_motorized_recreation_traffic',
            'warning_blind_turn_traffic_nearby',

            /*
             * Generic accessibility observations can legitimately narrow from
             * Place to Area to Site. Shared-facility accessibility above is
             * deliberately Place/Area-only.
             */
            'experience_sunrise_view',
            'experience_sunset_view',
            'experience_overall_scenery',
            'experience_night_sky',
            'experience_stargazing',
            'experience_quiet_evening',
            'experience_overnight_comfort',
            'experience_extended_stay_comfort',
            'experience_sensory_retreat',
            'experience_remote_work',
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

            /*
             * Narrative summaries and reviewer notes may describe whichever
             * target is currently being reported.
             */
            'sensory_summary',
            'visited_at',
            'scout_note_1',
            'scout_note_2',
            'scout_note_3',
            'visit_length_of_stay',
            'visit_setup_types',
            'contributor_notes',
        ],
    ];

    $result = [];

    foreach ($byGroup as $group => $keys) {
        foreach ($keys as $key) {
            if (isset($result[$key])) {
                throw new LogicException(
                    'Duplicate Scout Report scope ownership for field: '
                    . $key
                );
            }

            $result[$key] = $group;
        }
    }

    return $result;
}

/**
 * Conservative ownership for future fields that have not yet been added to
 * the explicit current-field inventory above.
 *
 * Current fields should all be in llama_place_report_scope_overrides(). This
 * fallback prevents a future new field from accidentally gaining every scope
 * merely because its author forgot to classify it.
 */
function llama_place_report_scope_default_for_section(string $section): string
{
    return match ($section) {
        'site_vehicle' => 'site',

        'sensory',
        'landscape_setting',
        'safety',
        'experience',
        'summaries' => 'all',

        'amenities' => 'place_area',

        default => 'place',
    };
}

function llama_place_report_apply_flow_scopes(array &$fields): void
{
    $groups = llama_place_report_scope_groups();
    $overrides = llama_place_report_scope_overrides();

    foreach ($fields as $key => &$field) {
        $group =
            $overrides[$key]
            ?? llama_place_report_scope_default_for_section(
                (string) (
                    $field['section']
                    ?? ''
                )
            );

        if (!isset($groups[$group])) {
            throw new LogicException(
                'Invalid Scout Report scope group for field: '
                . (string) $key
            );
        }

        $field['report_scopes'] =
            $groups[$group];

        $field['report_scope_group'] =
            $group;

        $field['scope_inheritance'] =
            $group === 'site'
                ? 'none'
                : 'nearest_recorded';

        $field['scope_definition_explicit'] =
            array_key_exists(
                $key,
                $overrides
            );
    }

    unset($field);
}

/**
 * Return current canonical fields that are relying on the future-field
 * fallback rather than the audited ownership inventory.
 *
 * This is intentionally side-effect free so admin/testing code can inspect it
 * without changing report behavior.
 */
function llama_place_report_unclassified_scope_fields(array $fields): array
{
    $overrides =
        llama_place_report_scope_overrides();

    $missing = [];

    foreach ($fields as $key => $field) {
        if (!array_key_exists($key, $overrides)) {
            $missing[$key] = [
                'section' =>
                    (string) (
                        $field['section']
                        ?? ''
                    ),

                'fallback_group' =>
                    llama_place_report_scope_default_for_section(
                        (string) (
                            $field['section']
                            ?? ''
                        )
                    ),
            ];
        }
    }

    return $missing;
}

/**
 * Resolve a field from flat answer maps keyed by canonical field ID.
 *
 * array_key_exists() is deliberate: explicit No, Unknown, 0, null, or an
 * explicit empty-list answer is recorded and therefore stops inheritance.
 */
function llama_place_report_resolve_scoped_answer(
    array $field,
    array $placeAnswers,
    array $areaAnswers = [],
    array $siteAnswers = [],
    string $target = 'place'
): array {
    if (!in_array($target, ['place', 'area', 'site'], true)) {
        throw new InvalidArgumentException(
            'Invalid reporting target.'
        );
    }

    $key =
        (string) (
            $field['key']
            ?? ''
        );

    if ($key === '') {
        throw new InvalidArgumentException(
            'A canonical field key is required.'
        );
    }

    $allowed =
        (array) (
            $field['report_scopes']
            ?? ['place']
        );

    $sources = match ($target) {
        'site' => [
            'site' => $siteAnswers,
            'area' => $areaAnswers,
            'place' => $placeAnswers,
        ],

        'area' => [
            'area' => $areaAnswers,
            'place' => $placeAnswers,
        ],

        default => [
            'place' => $placeAnswers,
        ],
    };

    foreach ($sources as $scope => $answers) {
        if (
            in_array(
                $scope,
                $allowed,
                true
            )
            && array_key_exists(
                $key,
                $answers
            )
        ) {
            return [
                'recorded' =>
                    true,

                'value' =>
                    $answers[$key],

                'source_scope' =>
                    $scope,

                'inherited' =>
                    $scope !== $target,
            ];
        }
    }

    return [
        'recorded' =>
            false,

        'value' =>
            null,

        'source_scope' =>
            null,

        'inherited' =>
            false,
    ];
}
