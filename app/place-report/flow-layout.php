<?php

declare(strict_types=1);

/**
 * Shared Scout Report information architecture.
 *
 * This file owns presentation grouping and order only. Storage, applicability,
 * scoring, field ownership, and persistence remain elsewhere.
 */
function llama_place_report_layout_groups(): array
{
    return [
        'getting_there' => [
            'road_conditions' => 'Road Conditions',
            'vehicle_access' => 'Vehicle Access',
            'seasonal_access' => 'Seasonal Access',
            'access_restrictions' => 'Access Restrictions',
        ],
        'place' => [
            'information_location' => 'Information & Location',
            'overnight_stays' => 'Camping & Overnight Stays',
            'reservations_pricing' => 'Reservations & Pricing',
            'shared_amenities' => 'Facilities & Amenities',
            'place_accessibility' => 'Accessibility',
            'activities_recreation' => 'Activities & Recreation',
            'seasonal_conditions' => 'Seasonal Conditions',
            'connectivity_services' => 'Connectivity & Services',
            'place_rules' => 'Rules & Restrictions',
            'operating_closures' => 'Operating Hours & Closures',
        ],
        'site' => [
            'selection_type' => 'Site & Capacity',
            'site_dimensions' => 'Site Dimensions',
            'parking_maneuvering' => 'Parking & Maneuvering',
            'camping_equipment' => 'Utilities & Hookups',
            'site_features' => 'Site Features & Equipment',
            'sensory_environment' => 'Sensory & Environment',
            'safety_hazards' => 'Safety & Hazards',
            'site_accessibility' => 'Accessibility',
            'experience_recommendations' => 'Experience & Recommendations',
            'site_photos' => 'Photos & Site Information',
        ],
    ];
}

function llama_place_report_layout_assignments(): array
{
    return [
        'getting_there:road_conditions' => [
            'road_surface', 'road_width', 'approach_road_condition',
            'approach_road_hazards', 'approach_deep_ruts',
            'road_overall_difficulty', 'road_stress', 'rocks',
            'washboards', 'potholes', 'mud_risk', 'steep_grades',
            'drop_off_exposure', 'water_crossings', 'downed_tree_risk',
        ],

        'getting_there:vehicle_access' => [
            'sedan_accessible', 'high_clearance_recommended',
            'four_wheel_drive_recommended', 'road_exposure',
            'approach_width_restricted', 'approach_narrowest_width_feet',
            'approach_turnaround_available', 'approach_overhead_obstacles',
            'approach_access_restrictions',
            'access_summary',
        ],

        'getting_there:seasonal_access' => [
            'approach_year_round', 'approach_seasonal_closure',
            'approach_closure_month', 'approach_closure_day',
            'approach_reopening_month', 'approach_reopening_day',
            'approach_winter_maintenance', 'approach_snow_chains',
            'approach_seasonal_restrictions',
            'winter_access', 'seasonal_closure', 'seasonal_access_note',
            'snow_risk', 'mud_season_risk', 'monsoon_risk',
            'approach_road_conditions_url',
        ],

        'getting_there:access_restrictions' => [
            'approach_road_type', 'approach_permit_required',
            'approach_fee_required', 'approach_gate_present',
            'approach_gate_access_type', 'approach_gate_opens',
            'approach_gate_closes', 'approach_restriction_details',
            'approach_access_info_url',
        ],

        'place:information_location' => [
            'name', 'type', 'description', 'latitude', 'longitude',
            'elevation_feet', 'road', 'city', 'county', 'state', 'land_type',
            'land_manager', 'region', 'visited_at',
        ],

        'place:overnight_stays' => [
            'campsite_count',
            'overnight_camping_allowed',
            'tent_camping_allowed',
            'rv_camping_allowed',
            'car_truck_camping',
            'dispersed_camping_allowed',
            'designated_sites_only',
            'stay_limit_days',
            'overnight_restrictions',
            'camping_conditional_details',
        ],

        'place:reservations_pricing' => [
            // Price is decision-critical. Show all fees before reference URLs.
            'fee',
            'entrance_facility_fee',
            'parking_fee',
            'reservation_required',
            'reservation_fee',
            'membership_required',
            'membership_fee',
            'check_in_required',
            'check_in_begins',
            'check_out_required',
            'checkout_ends',
            'reservation_url',
            'membership_url',
        ],

        'place:shared_amenities' => [
            'amenity_none',
            'amenity_toilets',
            'amenity_potable_water',
            'amenity_water_filling_station',
            'amenity_dishwashing_station',
            'amenity_trash',
            'amenity_recycling',
            'amenity_showers',
            'amenity_laundry',
            'amenity_wifi',
            'amenity_dump_station',
            'amenity_electricity',
            'amenity_picnic_shelter',
            'amenity_playground',
            'amenity_amphitheater',
            'amenity_fishing_pier',
            'amenity_lake_access',
            'amenity_river_access',
            'amenity_trailhead',
            'amenity_trailhead_parking',
            'amenity_camp_store',
            'amenity_campground_host',
            'facility_map_url',
        ],

        'place:place_accessibility' => [
            'place_accessible',
            'shared_accessible_facilities',
            'shared_accessible_facility_types',
            'accessible_toilet',
            'amenity_accessible_showers',
            'accessibility_notes',
        ],

        'place:activities_recreation' => [
            'activities',
        ],

        'place:seasonal_conditions' => [
            'best_months',
            'hurricane_risk',
            'heat_season_risk',
        ],

        'place:connectivity_services' => [
            'connectivity_overall',
            'connectivity_t_mobile',
            'connectivity_verizon',
            'connectivity_att',
            'connectivity_other_cell',
            'connectivity_starlink',
            'connectivity_starlink_tested',
            'connectivity_starlink_note',
            'nearest_town',
            'nearest_fuel',
            'nearest_ev_charging',
            'nearest_alcohol_sales',
            'nearest_propane',
            'nearest_grocery',
            'nearest_water',
            'nearest_toilet',
            'nearest_hospital',
        ],

        'place:place_rules' => [
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
            'collecting_firewood',
            'place_charcoal_grills_allowed',
            'place_propane_fire_pits_allowed',
            'fire_activities_conditional_details',
            'food_storage_required',
            'existing_sites_encouraged',
            'pack_it_in_pack_it_out',
            'residential_use_prohibited',
            'place_quiet_hours',
            'place_quiet_hours_begin',
            'place_quiet_hours_end',
            'place_alcohol_permitted',
            'drone_use_legal',
            'target_shooting_allowed',
            'place_additional_rules',
            'current_fire_restrictions_url',
            'fire_activity_permit_url',
            'camping_permit_url',
            'overnight_vehicle_tent_permit_url',
        ],

        'place:operating_closures' => [
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
            'place_closure_review_date',
            'place_closure_notes',
            'place_closure_source_url',
        ],

        'site:selection_type' => [
            'site_number',
            'capacity_size_rating',
            'max_people',
            'vehicle_capacity',
        ],

        /*
         * Physical dimensions come first. These answer "How big is the
         * actual space?" independently from any published vehicle/RV limit.
         */
        'site:site_dimensions' => [
            'site_length_feet',
            'site_width_feet',
            'parking_length_feet',
            'overhead_clearance_feet',
        ],

        /*
         * How the Site is surfaced, positioned, and entered.
         */
        'site:parking_maneuvering' => [
            'parking_surface',
            'ground_condition',
            'levelness',
            'parking_grade',
            'leveling_required',
            'double_driveway',
            'pull_through',
            'back_in',
            'turnaround_space',
        ],

        'site:camping_equipment' => [
            'site_hookups_available',
            'hookup_electric',
            'hookup_electric_service',
            'hookup_water',
            'hookup_sewer',
        ],

        'site:site_features' => [
            'tent_pad',
            'tent_pad_length_feet',
            'tent_pad_width_feet',
            'amenity_picnic_table',
            'amenity_fire_ring',
            'amenity_grill',
            'amenity_bear_box',
            'amenity_lantern_post',
        ],

        'site:sensory_environment' => [
            'site_shade', 'tree_cover', 'site_open_sky',
            'landscape_primary', 'landscape_details', 'landscape_views',
            'environment_water_nearby', 'environment_wildlife',
            'environment_bugs', 'environment_wind_exposure',
            'environment_sun_exposure', 'environment_shade',
            'environment_open_sky', 'critter_activity',
            'daytime_noise', 'daytime_traffic', 'daytime_crowds',
            'daytime_privacy', 'daytime_light_pollution',
            'daytime_sensory_comfort', 'daytime_social_interaction',
            'nighttime_noise', 'nighttime_traffic', 'nighttime_crowds',
            'nighttime_privacy', 'nighttime_light_pollution',
            'nighttime_sensory_comfort', 'nighttime_social_interaction',
            'sensory_dust_from_traffic', 'sensory_generator_noise',
            'sensory_aircraft_noise', 'sensory_train_noise',
            'sensory_dog_barking', 'sensory_road_noise',
            'sensory_human_activity', 'sensory_wildlife_noise',
            'sensory_wind_noise', 'sensory_smoke_risk',
            'sensory_strong_odors', 'sensory_visual_exposure',
            'sensory_predictability', 'sensory_summary',
        ],

        'site:safety_hazards' => [
            'felt_safe_daytime', 'felt_safe_nighttime',
            'warning_possible_downed_trees',
            'flash_flood_risk', 'wildfire_risk', 'fall_hazard',
            'cliff_exposure', 'rockfall_risk', 'wildlife_risk',
            'traffic_hazard', 'emergency_access',
            'warning_passing_vehicle_dust',
            'warning_motorized_recreation_traffic',
            'warning_blind_turn_traffic_nearby',
        ],

        'site:site_accessibility' => [
            'site_accessible',
            'site_access_difficulty',
            'hike_in_distance_feet',
            'walking_distance_from_vehicle',
            'wheelchair_friendly',
            'mobility_device_friendly',
            'flat_walking_surface',
            'step_free_access',
            'accessible_picnic_table',
        ],

        'site:experience_recommendations' => [
            'site_rating', 'condition_rating', 'location_rating',
            'experience_sunrise_view', 'experience_sunset_view',
            'experience_overall_scenery', 'experience_night_sky',
            'experience_stargazing', 'experience_quiet_evening',
            'experience_overnight_comfort', 'experience_extended_stay_comfort',
            'experience_sensory_retreat', 'experience_remote_work',
            'recommended_overnight_stop', 'recommended_quiet_evening',
            'recommended_extended_stay', 'recommended_sensory_retreat',
            'recommended_stargazing', 'recommended_remote_work',
            'recommended_solo_travel', 'recommended_families',
            'recommended_large_groups', 'not_recommended_for',
        ],

        'site:site_photos' => [
            'contributor_notes',
            'scout_note_1',
            'scout_note_2',
            'scout_note_3',
        ],
    ];
}

function llama_place_report_apply_layout_metadata(
    array &$fields
): void {
    $assignments =
        llama_place_report_layout_assignments();

    $lookup = [];

    foreach ($assignments as $target => $keys) {
        [$section, $group] =
            explode(
                ':',
                $target,
                2
            );

        foreach ($keys as $key) {
            $lookup[$key] = [
                $section,
                $group,
            ];
        }
    }

    $fallbacks = [
        'road_access' => ['getting_there', 'road_conditions'],
        'site_vehicle' => ['site', 'selection_type'],
        'campground' => ['place', 'overnight_stays'],
        'basic' => ['place', 'information_location'],
        'location' => ['place', 'information_location'],
        'amenities' => ['place', 'shared_amenities'],
        'rules' => ['place', 'place_rules'],
        'sensory' => ['site', 'sensory_environment'],
        'landscape_setting' => ['site', 'sensory_environment'],
        'connectivity' => ['place', 'connectivity_services'],
        'accessibility' => ['site', 'site_accessibility'],
        'experience' => ['site', 'experience_recommendations'],
        'safety' => ['site', 'safety_hazards'],
        'summaries' => ['site', 'site_photos'],
    ];

    foreach ($fields as $key => &$field) {
        $current =
            (string) (
                $field['section']
                ?? ''
            );

        [$section, $group] =
            $lookup[$key]
            ?? $fallbacks[$current]
            ?? [
                'place',
                'information_location',
            ];

        $field['layout_section'] =
            $section;

        $field['layout_group'] =
            $group;

        $field['layout_definition_explicit'] =
            array_key_exists(
                $key,
                $lookup
            );

        if ($key === 'capacity_size_rating') {
            $field['label'] =
                'Site size / capacity type';
        }

        if (
            in_array(
                $key,
                [
                    'max_vehicle_length_feet',
                    'max_trailer_length_feet',
                    'max_rv_length_feet',
                    'trailer_suitable',
                    'rv_suitable',
                    'tent_camping_suitable',
                ],
                true
            )
        ) {
            /*
             * Compatibility only. Llama Scout now records objective Site
             * dimensions and capacity instead of asking contributors to make
             * vehicle/RV suitability judgments or duplicate those dimensions
             * with published-fit limits.
             */
            $field['layout_section'] =
                'compatibility';

            $field['layout_group'] =
                'legacy_fit';

            $field['counts_toward_completion'] =
                false;
        }
    }

    unset($field);
}

/**
 * Return active canonical fields that still rely on a legacy-section
 * fallback instead of an explicit shared-layout assignment.
 */
function llama_place_report_unclassified_layout_fields(
    array $fields
): array {
    $missing = [];

    foreach ($fields as $key => $field) {
        if (
            (string) (
                $field['layout_section']
                ?? ''
            ) === 'compatibility'
            || !empty(
                $field['layout_definition_explicit']
            )
        ) {
            continue;
        }

        $missing[$key] = [
            'section' => (string) ($field['section'] ?? ''),
            'layout_section' => (string) ($field['layout_section'] ?? ''),
            'layout_group' => (string) ($field['layout_group'] ?? ''),
        ];
    }

    return $missing;
}

/**
 * Reorder the canonical registry from its final layout metadata.
 *
 * Explicit assignment order is authoritative inside each layout group. Fields
 * that rely on a fallback retain their relative registry order after explicitly
 * assigned fields in that group.
 */
function llama_place_report_reorder_layout_fields(
    array &$fields
): void {
    $ordered = [];
    $used = [];
    $assignments =
        llama_place_report_layout_assignments();

    foreach (
        llama_place_report_layout_groups()
        as $section => $groups
    ) {
        foreach ($groups as $group => $_label) {
            $assignmentKey =
                $section . ':' . $group;

            foreach (
                $assignments[$assignmentKey]
                ?? []
                as $key
            ) {
                if (
                    isset($used[$key])
                    || !isset($fields[$key])
                ) {
                    continue;
                }

                $field =
                    $fields[$key];

                if (
                    (string) (
                        $field['layout_section']
                        ?? ''
                    ) !== $section
                    || (string) (
                        $field['layout_group']
                        ?? ''
                    ) !== $group
                ) {
                    continue;
                }

                $ordered[$key] =
                    $field;

                $used[$key] =
                    true;
            }

            /*
             * Preserve active fallback fields that legitimately belong to this
             * group but are not yet part of its explicit ordered inventory.
             */
            foreach ($fields as $key => $field) {
                if (
                    isset($used[$key])
                    || (string) (
                        $field['layout_section']
                        ?? ''
                    ) !== $section
                    || (string) (
                        $field['layout_group']
                        ?? ''
                    ) !== $group
                ) {
                    continue;
                }

                $ordered[$key] =
                    $field;

                $used[$key] =
                    true;
            }
        }
    }

    /*
     * Compatibility/hidden/unmapped fields stay registered and retain their
     * relative order after the active shared form fields.
     */
    foreach ($fields as $key => $field) {
        if (isset($used[$key])) {
            continue;
        }

        $ordered[$key] =
            $field;
    }

    $fields =
        $ordered;
}
