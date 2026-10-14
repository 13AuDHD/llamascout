<?php

declare(strict_types=1);

/**
 * Approved Scout Report information architecture.
 * This is display metadata only: storage, scoring, scopes, and field values
 * remain unchanged until the coordinated form/completion implementation.
 * Unmapped questions remain registered and are never silently discarded.
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
            'connectivity_services' => 'Connectivity & Services',
            'place_rules' => 'Rules & Restrictions',
            'operating_closures' => 'Operating Hours & Closures',
        ],
        'site' => [
            'selection_type' => 'Site Selection & Type',
            'parking_dimensions' => 'Parking & Dimensions',
            'camping_equipment' => 'Camping & Equipment',
            'sensory_environment' => 'Sensory & Environment',
            'safety_hazards' => 'Safety & Hazards',
            'site_accessibility' => 'Accessibility',
            'experience_recommendations' => 'Experience & Recommendations',
            'site_photos' => 'Photos & Site Information',
        ],
    ];
}

function llama_place_report_apply_layout_metadata(array &$fields): void
{
    $assignments = [
        'getting_there:road_conditions' => [
            'road_surface', 'road_width', 'approach_road_condition',
            'approach_road_hazards',
            'approach_deep_ruts', 'road_overall_difficulty', 'road_stress',
            'rocks', 'washboards', 'potholes', 'mud_risk', 'steep_grades',
            'drop_off_exposure', 'water_crossings', 'downed_tree_risk',
            'warning_possible_downed_trees',
        ],
        'getting_there:vehicle_access' => [
            'sedan_accessible', 'high_clearance_recommended',
            'four_wheel_drive_recommended', 'road_exposure',
            'approach_width_restricted', 'approach_narrowest_width_feet',
            'approach_turnaround_available', 'approach_overhead_obstacles',
            'approach_access_restrictions',
        ],
        'getting_there:seasonal_access' => [
            'approach_year_round', 'approach_seasonal_closure',
            'approach_closure_month', 'approach_closure_day',
            'approach_reopening_month', 'approach_reopening_day',
            'approach_winter_maintenance', 'approach_snow_chains',
            'approach_seasonal_restrictions', 'approach_road_conditions_url',
            'winter_access', 'seasonal_closure', 'seasonal_access_note',
            'snow_risk', 'mud_season_risk', 'monsoon_risk',
        ],
        'getting_there:access_restrictions' => [
            'approach_road_type', 'approach_permit_required',
            'approach_fee_required', 'approach_gate_present',
            'approach_gate_access_type', 'approach_gate_opens',
            'approach_gate_closes', 'approach_restriction_details',
            'approach_access_info_url',
        ],
        'place:information_location' => [
            'name','type','description','latitude','longitude','elevation_feet',
            'road','city','county','state','land_type','land_manager','region',
            'visited_at',
        ],
        'place:overnight_stays' => [
            'campsite_count','overnight_camping_allowed','dispersed_camping_allowed',
            'car_truck_camping','designated_sites_only','stay_limit_days',
            'reservation_required','membership_required','check_in_required',
            'check_in_begins','check_out_required','checkout_ends',
        ],
        'place:reservations_pricing' => [
            'fee','entrance_facility_fee','parking_fee','reservation_fee',
            'membership_fee','reservation_url','membership_url',
            'place_rate_cards_json',
        ],
        'place:shared_amenities' => [
            'amenity_none',
            'amenity_toilets','amenity_potable_water','amenity_water_filling_station',
            'amenity_dishwashing_station','amenity_trash','amenity_recycling',
            'amenity_showers','amenity_accessible_showers','amenity_laundry',
            'amenity_wifi','amenity_dump_station','amenity_electricity',
            'amenity_picnic_shelter','amenity_playground','amenity_amphitheater',
            'amenity_fishing_pier','amenity_lake_access','amenity_river_access',
            'amenity_trailhead','amenity_trailhead_parking','amenity_camp_store',
            'amenity_campground_host','shared_accessible_facilities',
            'shared_accessible_facility_types',
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
            'pets_allowed','dogs_required_to_be_leashed',
            'generator_restrictions','generator_prohibited',
            'generator_quiet_hours','generator_quiet_hours_begin',
            'generator_quiet_hours_end','generator_run_restrictions',
            'generator_max_run_hours','generator_restriction_details',
            'campfire_allowed','collecting_firewood',
            'place_charcoal_grills_allowed','place_propane_fire_pits_allowed',
            'current_fire_restrictions_url','fire_activities_conditional_details',
            'fire_activity_permit_url','place_quiet_hours',
            'place_quiet_hours_begin','place_quiet_hours_end',
            'place_alcohol_permitted','drone_use_legal','target_shooting_allowed',
            'place_additional_rules',
        ],

        /*
         * Site & vehicle fit display order.
         *
         * Keep the order here with the rest of the report layout metadata
         * instead of creating another helper file solely to rearrange fields.
         */
        'site:selection_type' => [
            'site_accessible',
            'max_people',
            'capacity_size_rating',
            'vehicle_capacity',
            'rv_suitable',
            'max_vehicle_length_feet',
            'max_rv_length_feet',
            'overhead_clearance_feet',
            'double_driveway',
            'parking_surface',
            'parking_length_feet',
            'parking_grade',
            'pull_through',
            'back_in',
            'turnaround_space',
            'tent_camping_suitable',
            'tent_pad',
            'tent_pad_length_feet',
            'tent_pad_width_feet',
            'site_hookups_available',
            'hookup_electric',
            'hookup_electric_service',
            'hookup_water',
            'hookup_sewer',
            'hike_in_distance_feet',
            'site_length_feet',
            'site_width_feet',
            'ground_condition',
            'levelness',
            'amenity_picnic_table',
            'amenity_fire_ring',
            'amenity_grill',
            'amenity_bear_box',
            'amenity_lantern_post',
        ],

        /*
         * These remain part of the site model but are not part of the
         * preferred Site & vehicle fit sequence above.
         */
        'site:parking_dimensions' => [
            'walking_distance_from_vehicle',
            'leveling_required',
            'site_access_difficulty',
        ],
        'site:camping_equipment' => [],
        'site:sensory_environment' => [
            'site_shade','tree_cover','site_open_sky',
            'landscape_primary','landscape_details','landscape_views',
            'environment_water_nearby','environment_wildlife',
            'environment_bugs','environment_wind_exposure',
            'environment_sun_exposure','environment_shade',
            'environment_open_sky','critter_activity',
            'daytime_noise','daytime_traffic','daytime_crowds',
            'daytime_privacy','daytime_light_pollution',
            'daytime_sensory_comfort','daytime_social_interaction',
            'nighttime_noise','nighttime_traffic','nighttime_crowds',
            'nighttime_privacy','nighttime_light_pollution',
            'nighttime_sensory_comfort','nighttime_social_interaction',
            'sensory_dust_from_traffic','sensory_generator_noise',
            'sensory_aircraft_noise','sensory_train_noise',
            'sensory_dog_barking','sensory_road_noise',
            'sensory_human_activity','sensory_wildlife_noise',
            'sensory_wind_noise','sensory_smoke_risk',
            'sensory_strong_odors','sensory_visual_exposure',
            'sensory_predictability','sensory_summary',
        ],
        'site:safety_hazards' => [
            'felt_safe_daytime','felt_safe_nighttime',
            'flash_flood_risk','wildfire_risk','fall_hazard',
            'cliff_exposure','rockfall_risk','wildlife_risk',
            'traffic_hazard','emergency_access',
            'warning_passing_vehicle_dust',
            'warning_motorized_recreation_traffic',
            'warning_blind_turn_traffic_nearby',
        ],
        'site:site_accessibility' => [
            'wheelchair_friendly','mobility_device_friendly',
            'flat_walking_surface','step_free_access',
            'walking_distance_from_vehicle',
        ],
        'site:experience_recommendations' => [
            'site_rating','condition_rating','location_rating',
            'experience_sunrise_view','experience_sunset_view',
            'experience_overall_scenery','experience_night_sky',
            'experience_stargazing','experience_quiet_evening',
            'experience_overnight_comfort','experience_extended_stay_comfort',
            'experience_sensory_retreat','experience_remote_work',
            'recommended_overnight_stop','recommended_quiet_evening',
            'recommended_extended_stay','recommended_sensory_retreat',
            'recommended_stargazing','recommended_remote_work',
            'recommended_solo_travel','recommended_families',
            'recommended_large_groups','not_recommended_for',
        ],
    ];

    $lookup = [];

    foreach ($assignments as $target => $keys) {
        [$section, $group] =
            explode(':', $target, 2);

        foreach ($keys as $key) {
            $lookup[$key] = [
                $section,
                $group,
            ];
        }
    }

    $fallbacks = [
        'road_access' => ['getting_there','road_conditions'],
        'site_vehicle' => ['site','selection_type'],
        'campground' => ['place','overnight_stays'],
        'basic' => ['place','information_location'],
        'location' => ['place','information_location'],
        'amenities' => ['place','shared_amenities'],
        'rules' => ['place','place_rules'],
        'sensory' => ['site','sensory_environment'],
        'landscape_setting' => ['site','sensory_environment'],
        'connectivity' => ['place','connectivity_services'],
        'accessibility' => ['site','site_accessibility'],
        'experience' => ['site','experience_recommendations'],
        'safety' => ['site','safety_hazards'],
        'summaries' => ['site','site_photos'],
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
    }

    unset($field);

    /*
     * Reorder the canonical registry itself so the public Scout Report and
     * field-driven forms read in the same order. This stays here because field
     * order is presentation/layout metadata.
     */
    $siteVehicleOrder = $assignments['site:selection_type'];

    $siteVehicleFields = [];
    $siteVehicleKeys = [];

    foreach ($fields as $key => $field) {
        if (
            (string) (
                $field['section']
                ?? ''
            ) !== 'site_vehicle'
        ) {
            continue;
        }

        $siteVehicleFields[$key] = $field;
        $siteVehicleKeys[$key] = true;
    }

    $orderedSiteVehicle = [];

    foreach ($siteVehicleOrder as $key) {
        if (!isset($siteVehicleFields[$key])) {
            continue;
        }

        $orderedSiteVehicle[$key] =
            $siteVehicleFields[$key];

        unset($siteVehicleFields[$key]);
    }

    foreach ($siteVehicleFields as $key => $field) {
        $orderedSiteVehicle[$key] = $field;
    }

    $rebuilt = [];
    $inserted = false;

    foreach ($fields as $key => $field) {
        if (isset($siteVehicleKeys[$key])) {
            if (!$inserted) {
                foreach (
                    $orderedSiteVehicle
                    as $orderedKey => $orderedField
                ) {
                    $rebuilt[$orderedKey] =
                        $orderedField;
                }

                $inserted = true;
            }

            continue;
        }

        $rebuilt[$key] = $field;
    }

    $fields = $rebuilt;
}
