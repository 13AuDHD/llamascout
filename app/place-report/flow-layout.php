<?php

declare(strict_types=1);

/**
 * Shared Scout Report information architecture.
 *
 * This file owns presentation grouping and order only. Storage, applicability,
 * scoring, field ownership, and persistence remain elsewhere.
 */
/**
 * Approved question grouping and order from the completed Scout Report mapping.
 *
 * This inventory belongs to the shared reporting layer, not a page template.
 * Keys continue to resolve their labels, answer choices, scope and storage
 * through llama_place_report_fields().
 */
function llama_place_report_approved_question_groups(): array
{
    return [
    'basic' => ['Basic', ['name','type','description']],
    'location' => ['Location', ['latitude','longitude','elevation_feet','road','city','county','state','land_type','land_manager','region','activities']],
    'amenities' => ['Amenities', ['amenity_toilets','amenity_potable_water','amenity_trash','amenity_dump_station','amenity_showers','amenity_picnic_shelter','amenity_wifi','amenity_laundry','amenity_dishwashing_station','amenity_playground','amenity_dog_park','amenity_amphitheater','amenity_campground_host','amenity_camp_store','amenity_trailhead','amenity_fishing_pier']],
    'operating' => ['Operating Hours', ['place_open_year_round','seasonal_closure','place_operating_start_month','place_operating_start_day','place_operating_end_month','place_operating_end_day','place_open_24_hours','place_opening_time','place_closing_time']],
    'road_conditions' => ['Road Conditions', ['approach_road_type','approach_permit_required','approach_fee_required','approach_road_fee','road_surface','road_width','approach_road_condition','approach_road_hazards','approach_deep_ruts','rocks','washboards','mud_risk','steep_grades','drop_off_exposure','water_crossings','downed_tree_risk','approach_access_restrictions']],
    'vehicle_access' => ['Vehicle Access', ['approach_overhead_obstacles','sedan_accessible','high_clearance_recommended','four_wheel_drive_recommended','approach_turnaround_available']],
    'seasonal_access' => ['Seasonal Access', ['best_months','approach_seasonal_closure','approach_closure_month','approach_closure_day','approach_reopening_month','approach_reopening_day','hurricane_risk','monsoon_risk','mud_season_risk','snow_risk','approach_winter_maintenance','approach_snow_chains','approach_seasonal_restrictions']],
    'gate_access' => ['Gate Access', ['approach_gate_present','approach_gate_access_type','approach_gate_opens','approach_gate_closes','place_entrance_gate','place_gate_access_type','place_gate_opening_time','place_gate_closing_time']],
    'nearest_services' => ['Nearest Services', ['nearest_town','nearest_fuel','nearest_ev_charging','nearest_propane','nearest_alcohol_sales','nearest_grocery','nearest_water','nearest_toilet','nearest_hospital']],
    'site_info' => ['Site Info', ['site_number','site_accessible','tent_camping_suitable','rv_suitable','capacity_size_rating','max_people','vehicle_capacity','site_length_feet','site_width_feet','ground_condition','levelness','parking_grade']],
    'fees' => ['Fees and Reservations', ['stay_limit_days','fee','extra_vehicle_fee','reservation_required','reservation_fee','reservation_url','membership_required','membership_fee','membership_url','parking_entrance_required','parking_entrance_fee','check_in_required','check_in_begins','check_out_required','checkout_ends','discounts_available']],
    'setting' => ['Setting and Environment', ['landscape_primary','landscape_details','landscape_views','environment_water_nearby','environment_wildlife','critter_activity','environment_bugs','environment_sun_exposure','environment_shade','tree_cover','environment_open_sky','environment_notes']],
    'parking' => ['Parking and Maneuvering', ['parking_surface','back_in','pull_through','turnaround_space','parking_size','parking_length_feet','max_vehicle_length_feet','max_rv_length_feet','overhead_clearance_feet','leveling_required','parking_notes']],
    'site_accessibility' => ['Accessibility', ['accessible_picnic_table','accessible_toilet','wheelchair_friendly','mobility_device_friendly','step_free_access','flat_walking_surface','walking_distance_from_vehicle','accessibility_notes']],
    'site_amenities' => ['Site Amenities', ['amenity_fire_ring','amenity_grill','amenity_picnic_table','amenity_lantern_post','site_potable_water','site_hookups_available','hookup_electric_service','hookup_water','hookup_sewer','tent_pad','tent_pad_width_feet','tent_pad_length_feet','amenity_bear_box','site_lake_access','site_river_access','site_trail_access','site_wifi_rating','site_amenities_notes']],
    'connectivity_public' => ['Connectivity', ['connectivity_overall','connectivity_att','connectivity_t_mobile','connectivity_verizon','connectivity_other_cell','connectivity_satellite','connectivity_services_tested','connectivity_starlink_note']],
    'daytime' => ['Daytime', ['daytime_social_interaction','daytime_noise','daytime_traffic','daytime_crowds','daytime_privacy','daytime_light_pollution','daytime_sensory_comfort','felt_safe_daytime_rating']],
    'nighttime' => ['Nighttime', ['nighttime_social_interaction','nighttime_noise','nighttime_traffic','nighttime_crowds','nighttime_privacy','nighttime_light_pollution','nighttime_sensory_comfort','felt_safe_nighttime_rating']],
    'specific_sensory' => ['Specific Sensory', ['sensory_dust_from_traffic','sensory_generator_noise','sensory_aircraft_noise','sensory_train_noise','sensory_dog_barking','sensory_wildlife_noise','sensory_wind_noise','sensory_strong_odors','sensory_smoke_risk','road_exposure','sensory_visual_exposure','sensory_predictability','sensory_summary']],
    'safety_public' => ['Safety and Hazards', ['warning_possible_downed_trees','fall_hazard','wildlife_risk','wildfire_risk','flash_flood_risk','rockfall_risk','traffic_hazard','warning_blind_turn_traffic_nearby','emergency_access']],
    'rules_public' => ['Rules and Restrictions', ['overnight_camping_allowed','dispersed_camping_allowed','designated_sites_only','pets_allowed','dogs_required_to_be_leashed','generator_restrictions','generator_prohibited','generator_quiet_hours','generator_quiet_hours_begin','generator_quiet_hours_end','generator_run_restrictions','generator_max_run_hours','generator_restriction_details','place_quiet_hours','place_quiet_hours_begin','place_quiet_hours_end','campfire_allowed','collecting_firewood','place_charcoal_grills_allowed','place_propane_fire_pits_allowed','fire_activity_permit_url','current_fire_restrictions_url','food_storage_required','pack_it_in_pack_it_out','place_alcohol_permitted','drone_use_legal','target_shooting_allowed','place_additional_rules']],
    'experience_public' => ['Experience and Recommendations', ['site_rating','condition_rating','location_rating','experience_sunrise_view','experience_sunset_view','experience_overall_scenery','experience_stargazing','experience_sensory_retreat','experience_quiet_evening','recommended_remote_work','recommended_overnight_stop','recommended_extended_stay','recommended_solo_travel','recommended_families','recommended_large_groups','not_recommended_for','experience_summary']],
    'notes_public' => ['Scout Notes', ['scout_note_1','scout_note_2','scout_note_3']],
    'history_public' => ['Place History', ['visited_at','visit_length_of_stay','visit_setup_types']],
];
}

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
            'camping_equipment' => 'Site Amenities',
            'site_features' => 'Site Amenities (continued)',
            'sensory_environment' => 'Sensory & Environment',
            'safety_hazards' => 'Safety & Hazards',
            'site_accessibility' => 'Accessibility',
            'experience_recommendations' => 'Experience & Recommendations',
            'site_photos' => 'Scout Notes & Place History',
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
            'approach_fee_required', 'approach_road_fee', 'approach_gate_present',
            'approach_gate_access_type', 'approach_gate_opens',
            'approach_gate_closes', 'approach_restriction_details',
            'approach_access_info_url',
        ],

        'place:information_location' => [
            'name', 'type', 'description', 'latitude', 'longitude',
            'elevation_feet', 'road', 'city', 'county', 'state', 'land_type',
            'land_manager', 'region',
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
            'amenity_trash',
            'amenity_picnic_shelter',
            'amenity_showers',
            'amenity_dump_station',
            'amenity_wifi',
            'amenity_laundry',
            'amenity_playground',
            'amenity_dog_park',
            'amenity_amphitheater',
            'amenity_campground_host',
            'amenity_water_filling_station',
            'amenity_dishwashing_station',
            'amenity_recycling',
            'amenity_electricity',
            'amenity_fishing_pier',
            'amenity_lake_access',
            'amenity_river_access',
            'amenity_trailhead',
            'amenity_trailhead_parking',
            'amenity_camp_store',
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
            'connectivity_satellite',
            'connectivity_services_tested',
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
            'overnight_camping_allowed',
            'dispersed_camping_allowed',
            'designated_sites_only',
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
            'place_quiet_hours',
            'place_quiet_hours_begin',
            'place_quiet_hours_end',
            'campfire_allowed',
            'collecting_firewood',
            'place_charcoal_grills_allowed',
            'place_propane_fire_pits_allowed',
            'fire_activities_conditional_details',
            'current_fire_restrictions_url',
            'food_storage_required',
            'pack_it_in_pack_it_out',
            'place_alcohol_permitted',
            'drone_use_legal',
            'target_shooting_allowed',
            'place_additional_rules',
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
            'site_accessible',
            'tent_camping_suitable',
            'rv_suitable',
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
            'max_vehicle_length_feet',
            'max_rv_length_feet',
            'parking_surface',
            'ground_condition',
            'levelness',
            'parking_grade',
            'leveling_required',
            'parking_size',
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
            'amenity_fire_ring',
            'amenity_grill',
            'amenity_picnic_table',
            'amenity_lantern_post',
            'tent_pad',
            'tent_pad_width_feet',
            'tent_pad_length_feet',
            'amenity_bear_box',
            'site_potable_water',
            'site_lake_access',
            'site_river_access',
            'site_trail_access',
            'site_wifi_rating',
            'site_amenities_notes',
        ],

        'site:sensory_environment' => [
            'landscape_primary', 'landscape_details', 'landscape_views',
            'environment_water_nearby', 'environment_wildlife', 'critter_activity',
            'environment_bugs', 'environment_sun_exposure', 'environment_shade',
            'tree_cover', 'environment_open_sky',
            'daytime_social_interaction', 'daytime_noise', 'daytime_traffic',
            'daytime_crowds', 'daytime_privacy', 'daytime_light_pollution',
            'daytime_sensory_comfort', 'felt_safe_daytime_rating',
            'nighttime_social_interaction', 'nighttime_noise', 'nighttime_traffic',
            'nighttime_crowds', 'nighttime_privacy', 'nighttime_light_pollution',
            'nighttime_sensory_comfort', 'felt_safe_nighttime_rating',
            'sensory_dust_from_traffic', 'sensory_generator_noise',
            'sensory_aircraft_noise', 'sensory_train_noise',
            'sensory_dog_barking', 'sensory_wildlife_noise',
            'sensory_wind_noise', 'sensory_strong_odors', 'sensory_smoke_risk',
            'road_exposure', 'sensory_visual_exposure',
            'sensory_predictability', 'sensory_summary',
        ],

        'site:safety_hazards' => [
            'warning_possible_downed_trees',
            'fall_hazard',
            'wildlife_risk',
            'wildfire_risk',
            'flash_flood_risk',
            'rockfall_risk',
            'traffic_hazard',
            'warning_blind_turn_traffic_nearby',
            'emergency_access',
        ],

        'site:site_accessibility' => [
            'accessible_picnic_table',
            'accessible_toilet',
            'wheelchair_friendly',
            'mobility_device_friendly',
            'step_free_access',
            'flat_walking_surface',
            'walking_distance_from_vehicle',
            'accessibility_notes',
        ],

        'site:experience_recommendations' => [
            'site_rating', 'condition_rating', 'location_rating',
            'experience_sunrise_view', 'experience_sunset_view',
            'experience_overall_scenery', 'experience_stargazing',
            'experience_sensory_retreat', 'experience_quiet_evening',
            'recommended_remote_work', 'recommended_overnight_stop',
            'recommended_extended_stay', 'recommended_solo_travel',
            'recommended_families', 'recommended_large_groups',
            'not_recommended_for',
        ],

        'site:site_photos' => [
            'scout_note_1',
            'scout_note_2',
            'scout_note_3',
            'visited_at',
            'visit_length_of_stay',
            'visit_setup_types',
            'contributor_notes',
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
                    'max_trailer_length_feet',
                    'trailer_suitable',
                    'rv_suitable',
                    'tent_camping_suitable',
                ],
                true
            )
        ) {
            /*
             * Older Site suitability records remain available for review,
             * without turning them into active contributor questions.
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

    /*
     * Use the approved Scout Report questionnaire as the shared question
     * order. Keep the existing Place/Site groups and scope assignments:
     * this changes ordering, not answer storage or field ownership.
     */
    $approvedOrder = [];
    foreach (llama_place_report_approved_question_groups() as $definition) {
        foreach ($definition[1] as $key) {
            if (!isset($approvedOrder[$key])) {
                $approvedOrder[$key] = count($approvedOrder);
            }
        }
    }

    foreach ($assignments as &$keys) {
        usort(
            $keys,
            static function (string $a, string $b) use ($approvedOrder): int {
                $aRank = $approvedOrder[$a] ?? PHP_INT_MAX;
                $bRank = $approvedOrder[$b] ?? PHP_INT_MAX;
                return $aRank <=> $bRank;
            }
        );
    }
    unset($keys);

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
