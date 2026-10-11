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
            'place_rules' => 'Rules & Restrictions',
            'operating_closures' => 'Operating Hours & Closures',
        ],
        'site' => [
            'selection_type' => 'Site Selection & Type',
            'parking_dimensions' => 'Parking & Dimensions',
            'camping_equipment' => 'Camping & Equipment',
            'site_amenities' => 'Amenities',
            'site_rules' => 'Rules & Restrictions',
            'sensory_environment' => 'Sensory & Environment',
            'site_accessibility' => 'Accessibility',
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
        'place:place_rules' => [
            'generator_restrictions','generator_quiet_hours',
            'generator_quiet_hours_begin','generator_quiet_hours_end',
            'generator_run_restrictions','generator_max_run_hours',
            'campfire_allowed','collecting_firewood','current_fire_restrictions_url',
            'drone_use_legal','target_shooting_allowed',
        ],
        'site:selection_type' => [
            'site_number','capacity_size_rating','max_people','vehicle_capacity',
            'rv_suitable','trailer_suitable','tent_camping_suitable',
        ],
        'site:parking_dimensions' => [
            'site_length_feet','site_width_feet','parking_length_feet',
            'overhead_clearance_feet','parking_surface','parking_grade',
            'double_driveway','leveling_required','hike_in_distance_feet',
            'walking_distance_from_vehicle','ground_condition',
        ],
        'site:camping_equipment' => [
            'tent_pad','tent_pad_length_feet','tent_pad_width_feet',
            'site_hookups_available','hookup_electric','hookup_electric_service',
            'hookup_water','hookup_sewer',
        ],
        'site:sensory_environment' => [
            'site_shade','tree_cover','site_open_sky','landscape_primary',
            'landscape_details','landscape_views','environment_water_nearby',
            'critter_activity','environment_bugs','sensory_summary',
        ],
        'site:site_accessibility' => [
            'site_accessible','wheelchair_friendly','mobility_device_friendly',
            'flat_walking_surface','step_free_access',
        ],
    ];

    $lookup = [];
    foreach ($assignments as $target => $keys) {
        [$section, $group] = explode(':', $target, 2);
        foreach ($keys as $key) {
            $lookup[$key] = [$section, $group];
        }
    }

    $fallbacks = [
        'road_access' => ['getting_there','road_conditions'],
        'site_vehicle' => ['site','parking_dimensions'],
        'campground' => ['place','overnight_stays'],
        'basic' => ['place','information_location'],
        'location' => ['place','information_location'],
        'amenities' => ['place','shared_amenities'],
        'rules' => ['place','place_rules'],
        'sensory' => ['site','sensory_environment'],
        'landscape_setting' => ['site','sensory_environment'],
        'connectivity' => ['site','sensory_environment'],
        'accessibility' => ['site','site_accessibility'],
        'experience' => ['site','sensory_environment'],
        'safety' => ['site','sensory_environment'],
        'summaries' => ['site','site_photos'],
    ];
    foreach ($fields as $key => &$field) {
        $current = (string) ($field['section'] ?? '');
        [$section, $group] = $lookup[$key]
            ?? $fallbacks[$current]
            ?? ['place', 'information_location'];
        $field['layout_section'] = $section;
        $field['layout_group'] = $group;
        // No inactive-field layer: Ground Condition remains a normal Site field.
    }
    unset($field);
}
