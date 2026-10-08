<?php

declare(strict_types=1);

/* Connectivity through Scout notes. */

function llama_place_report_add_experience_fields(
    array &$f,
    callable $add,
    array $distance
): void {
    /* Connectivity */
    foreach ([
        'connectivity_overall' => ['Overall cell service', 'None', 'Excellent', 'connectivity.overall'],
        'connectivity_t_mobile' => ['T-Mobile', 'None', 'Excellent', 'connectivity.t_mobile'],
        'connectivity_verizon' => ['Verizon', 'None', 'Excellent', 'connectivity.verizon'],
        'connectivity_att' => ['AT&T', 'None', 'Excellent', 'connectivity.att'],
        'connectivity_other_cell' => ['Other cellular', 'None', 'Excellent', 'connectivity.other_cell'],
        'connectivity_starlink' => ['Starlink', 'Poor', 'Excellent', 'connectivity.starlink'],
    ] as $key => [$label, $low, $high, $storage]) {
        $add($key, $label, 'connectivity', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['connectivity'],
        ]);
    }
    $add('connectivity_starlink_tested', 'Starlink tested?', 'connectivity', 'tri', 'connectivity.starlink_tested', [
        'allow_unknown' => true,
        'points_categories' => ['connectivity'],
    ]);
    $add('connectivity_starlink_note', 'Starlink notes', 'connectivity', 'textarea', 'connectivity.starlink_note', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Clear northern sky, heavy tree obstruction, not personally tested, etc.',
        'points_categories' => ['connectivity'],
        'counts_toward_completion' => false,
    ]);


    /* Sensory */
    foreach ([
        'daytime_noise' => ['Noise', 'Very quiet', 'Very loud', 'sensory.daytime.noise', 'Daytime'],
        'daytime_traffic' => ['Traffic', 'None', 'Heavy', 'sensory.daytime.traffic', 'Daytime'],
        'daytime_crowds' => ['People', 'None', 'Many', 'sensory.daytime.crowds', 'Daytime'],
        'daytime_privacy' => ['Privacy', 'None', 'Excellent', 'sensory.daytime.privacy', 'Daytime'],
        'daytime_light_pollution' => ['Natural light', 'Low', 'Full sun', 'sensory.daytime.light_pollution', 'Daytime'],
        'daytime_sensory_comfort' => ['Sensory comfort', 'Difficult', 'Excellent', 'sensory.daytime.sensory_comfort', 'Daytime'],
        'daytime_social_interaction' => ['Chance of social interaction', 'Very low', 'Very high', 'sensory.daytime.social_interaction_likelihood', 'Daytime'],
        'nighttime_noise' => ['Noise', 'Very quiet', 'Very loud', 'sensory.nighttime.noise', 'Nighttime'],
        'nighttime_traffic' => ['Traffic', 'None', 'Heavy', 'sensory.nighttime.traffic', 'Nighttime'],
        'nighttime_crowds' => ['People', 'None', 'Many', 'sensory.nighttime.crowds', 'Nighttime'],
        'nighttime_privacy' => ['Privacy', 'None', 'Excellent', 'sensory.nighttime.privacy', 'Nighttime'],
        'nighttime_light_pollution' => ['Light pollution', 'Dark', 'Bright', 'sensory.nighttime.light_pollution', 'Nighttime'],
        'nighttime_sensory_comfort' => ['Sensory comfort', 'Difficult', 'Excellent', 'sensory.nighttime.sensory_comfort', 'Nighttime'],
        'nighttime_social_interaction' => ['Chance of social interaction', 'Very low', 'Very high', 'sensory.nighttime.social_interaction_likelihood', 'Nighttime'],
        'sensory_dust_from_traffic' => ['Dust from traffic', 'Low', 'High', 'sensory.details.dust_from_traffic', 'Specific sensory conditions'],
        'sensory_generator_noise' => ['Generator noise', 'Low', 'High', 'sensory.details.generator_noise', 'Specific sensory conditions'],
        'sensory_aircraft_noise' => ['Aircraft noise', 'Low', 'High', 'sensory.details.aircraft_noise', 'Specific sensory conditions'],
        'sensory_train_noise' => ['Train noise', 'Low', 'High', 'sensory.details.train_noise', 'Specific sensory conditions'],
        'sensory_dog_barking' => ['Dog barking', 'Low', 'High', 'sensory.details.dog_barking', 'Specific sensory conditions'],
        'sensory_road_noise' => ['Road noise', 'Low', 'High', 'sensory.details.road_noise', 'Specific sensory conditions'],
        'sensory_human_activity' => ['Human activity', 'Low', 'High', 'sensory.details.human_activity', 'Specific sensory conditions'],
        'sensory_wildlife_noise' => ['Wildlife noise', 'Low', 'High', 'sensory.details.wildlife_noise', 'Specific sensory conditions'],
        'sensory_wind_noise' => ['Wind noise', 'Low', 'High', 'sensory.details.wind_noise', 'Specific sensory conditions'],
        'sensory_smoke_risk' => ['Smoke risk', 'Low', 'High', 'sensory.details.smoke_risk', 'Specific sensory conditions'],
        'sensory_strong_odors' => ['Strong odors', 'Low', 'High', 'sensory.details.strong_odors', 'Specific sensory conditions'],
        'sensory_visual_exposure' => ['Visual exposure', 'Low', 'High', 'sensory.details.visual_exposure', 'Specific sensory conditions'],
        'sensory_predictability' => ['Predictability', 'Low', 'High', 'sensory.details.predictability', 'Specific sensory conditions'],
    ] as $key => [$label, $low, $high, $storage, $subsection]) {
        $add($key, $label, 'sensory', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'subsection' => $subsection,
            'points_categories' => ['sensory'],
        ]);
    }

    /* Landscape and setting */
    $add('landscape_primary', 'Primary setting', 'landscape_setting', 'select', 'details.landscape_primary', [
        'wide' => true,
        'options' => [
            'forest-woodland' => 'Forest / woodland',
            'mountain-alpine' => 'Mountain / alpine',
            'canyon' => 'Canyon',
            'grassland-prairie' => 'Grassland / prairie',
            'shrubland-scrubland' => 'Shrubland / scrubland',
            'high-desert' => 'High desert',
            'low-desert' => 'Low desert',
            'wetland' => 'Wetland / swamp / bog',
            'beach-coastal' => 'Beach / coastal',
            'lakeside-reservoir' => 'Lakeside / reservoir',
            'riverside-creekside' => 'Riverside / creekside',
            'urban-city' => 'Urban / city',
            'suburban' => 'Suburban',
            'farmland-ranchland' => 'Farmland / ranchland',
            'mixed-transitional' => 'Mixed / transitional',
            'other' => 'Other',
        ],
        'allow_unknown' => true,
        'points_categories' => ['environment'],
    ]);

    $add('landscape_details', 'Setting details', 'landscape_setting', 'multiselect', 'details.landscape_details', [
        'wide' => true,
        'summary' => '+ Add setting detail',
        'search_placeholder' => 'Search setting details...',
        'options' => [
            'rocky' => 'Rocky',
            'sandy' => 'Sandy',
            'mud-prone' => 'Mud-prone',
            'tree-cover' => 'Tree cover',
            'wooded' => 'Wooded',
            'open-meadow' => 'Open meadow',
            'riparian' => 'Riparian',
            'dunes' => 'Dunes',
            'cliffs-dropoffs' => 'Cliffs / drop-offs',
            'agricultural-nearby' => 'Agricultural nearby',
            'commercial-nearby' => 'Commercial nearby',
            'residential-nearby' => 'Residential nearby',
            'industrial-nearby' => 'Industrial nearby',
        ],
        'points_categories' => ['environment'],
    ]);

    $add('landscape_views', 'Views', 'landscape_setting', 'multiselect', 'details.landscape_views', [
        'wide' => true,
        'summary' => '+ Add view',
        'search_placeholder' => 'Search views...',
        'options' => [
            'forest-woodland' => 'Forest / woodland',
            'mountain' => 'Mountain',
            'water' => 'Water',
            'high-desert' => 'High desert',
            'low-desert' => 'Low desert',
            'canyon' => 'Canyon',
            'grassland-prairie' => 'Grassland / prairie',
            'wetland' => 'Wetland',
            'beach-coast' => 'Beach / coast',
            'city-urban' => 'City / urban',
            'night-sky' => 'Night sky',
        ],
        'points_categories' => ['environment'],
    ]);

    foreach ([
        'environment_water_nearby' => ['Water nearby?', 'details.water_nearby'],
        'environment_wildlife' => ['Wildlife common?', 'details.wildlife'],
        'environment_bugs' => ['Bugs significant?', 'details.bugs'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'landscape_setting', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => ['environment'],
        ]);
    }

    foreach ([
        'environment_wind_exposure' => ['Wind exposure', 'Protected', 'Very exposed', 'details.wind_exposure'],
        'environment_sun_exposure' => ['Sun exposure', 'Low', 'Full sun', 'details.sun_exposure'],
        'environment_shade' => ['Environment shade', 'None', 'Heavy', 'details.environment_shade'],
        'environment_open_sky' => ['Open sky', 'Low', 'Wide open', 'details.environment_open_sky'],
        'critter_activity' => ['Critter activity', 'None noticed', 'Heavy / persistent', 'details.critter_activity'],
    ] as $key => [$label, $low, $high, $storage]) {
        $add($key, $label, 'landscape_setting', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['environment'],
        ]);
    }

    /* Accessibility */
    foreach ([
        'wheelchair_friendly' => ['Wheelchair friendly?', 'details.wheelchair_friendly'],
        'mobility_device_friendly' => ['Outdoor mobility device friendly?', 'details.mobility_device_friendly'],
        'flat_walking_surface' => ['Flat walking surface?', 'details.flat_walking_surface'],
        'step_free_access' => ['Step-free access?', 'details.step_free_access'],
        'accessible_toilet' => ['Accessible toilet?', 'details.accessible_toilet'],
        'accessible_picnic_table' => ['Accessible picnic table?', 'details.accessible_picnic_table'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'accessibility', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => ['accessibility'],
        ]);
    }

    $add('walking_distance_from_vehicle', 'Walking distance from vehicle', 'accessibility', 'select', 'details.walking_distance_from_vehicle', [
        'allow_unknown' => true,
        'options' => [
            'at-vehicle' => 'At / beside vehicle',
            'under-50-ft' => 'Under 50 ft',
            '50-100-ft' => '50-100 ft',
            '100-250-ft' => '100-250 ft',
            '250-500-ft' => '250-500 ft',
            '500-plus-ft' => '500+ ft / short hike',
        ],
        'points_categories' => ['accessibility'],
    ]);

    /* Safety and warnings */
    foreach ([
        'felt_safe_daytime' => ['Felt safe during the day?', 'details.felt_safe_daytime'],
        'flash_flood_risk' => ['Flash-flood risk?', 'details.flash_flood_risk'],
        'wildfire_risk' => ['Wildfire risk?', 'details.wildfire_risk'],
        'fall_hazard' => ['Trip/Fall Hazard?', 'details.fall_hazard'],
        'cliff_exposure' => ['Cliff exposure?', 'details.cliff_exposure'],
        'rockfall_risk' => ['Rockfall risk?', 'details.rockfall_risk'],
        'wildlife_risk' => ['Wildlife risk?', 'details.wildlife_risk'],
        'traffic_hazard' => ['Traffic hazard?', 'details.traffic_hazard'],
        'emergency_access' => ['Emergency vehicle access?', 'details.emergency_access'],
        'warning_possible_downed_trees' => ['Downed trees possible at the campsite?', 'details.warning_possible_downed_trees'],
        'warning_passing_vehicle_dust' => ['Passing vehicle dust?', 'details.warning_passing_vehicle_dust'],
        'warning_motorized_recreation_traffic' => ['Motorized recreation traffic?', 'details.warning_motorized_recreation_traffic'],
        'warning_blind_turn_traffic_nearby' => ['Blind-turn traffic nearby?', 'details.warning_blind_turn_traffic_nearby'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'safety', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => ['safety_warnings'],
        ]);
    }

    if (isset($f['felt_safe_nighttime'])) {
        $f['felt_safe_nighttime']['derived'] = true;
        $f['felt_safe_nighttime']['counts_toward_completion'] = false;
        $f['felt_safe_nighttime']['points_categories'] = [];
    }

    /*
     * Legacy compatibility only. Nighttime experience is represented
     * by Overnight Comfort plus the specific nighttime sensory and
     * safety observations.
     */
    $add('felt_safe_nighttime', 'Felt safe at night?', 'safety', 'derived', 'details.felt_safe_nighttime', [
        'derived' => true,
        'counts_toward_completion' => false,
        'points_categories' => [],
    ]);

    $add('road_exposure', 'Road exposure', 'safety', 'rating', 'details.road_exposure', [
        'allow_unknown' => true,
        'low' => 'Not exposed',
        'high' => 'Very exposed',
        'points_categories' => ['safety_warnings'],
    ]);


    /* Seasons, rules, and nearby services */
    $add('best_months', 'Best months', 'rules', 'select', 'rules.best_months', [
        'allow_unknown' => true,
        'options' => [
            'year-round' => 'Year-round',
            'spring' => 'Spring',
            'summer' => 'Summer',
            'fall' => 'Fall',
            'winter' => 'Winter',
            'spring-summer' => 'Spring through Summer',
            'summer-fall' => 'Summer through Fall',
            'late-spring-fall' => 'Late Spring through Fall',
            'snow-free-months' => 'Generally snow-free months',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('winter_access', 'Winter access?', 'rules', 'tri', 'rules.winter_access', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('seasonal_closure', 'Seasonal closure?', 'rules', 'tri', 'details.seasonal_closure', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    foreach ([
        'snow_risk' => ['Snow risk', 'Low', 'High'],
        'mud_season_risk' => ['Mud-season risk', 'Low', 'High'],
        'monsoon_risk' => ['Monsoon risk', 'Low', 'High'],
        'hurricane_risk' => ['Hurricane risk', 'Low', 'High'],
        'heat_season_risk' => ['Heat-season risk', 'Low', 'High'],
    ] as $key => [$label, $low, $high]) {
        $add($key, $label, 'rules', 'rating', 'rules.' . $key, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }
    $add('seasonal_access_note', 'Seasonal access notes', 'rules', 'textarea', 'rules.seasonal_access_note', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Gated in winter, impassable after heavy rain, snow usually lingers until June, etc.',
        'points_categories' => ['seasons_rules_services'],
        'counts_toward_completion' => false,
    ]);
    foreach ([
        'overnight_camping_allowed' => 'Overnight camping allowed?',
        'dispersed_camping_allowed' => 'Dispersed camping allowed?',
        'collecting_firewood' => 'Collecting firewood?',
        'campfire_allowed' => 'Campfire allowed?',
        'drone_use_legal' => 'Drone use allowed?',
        'target_shooting_allowed' => 'Target shooting allowed?',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'permission', 'rules.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }
    foreach ([
        'designated_sites_only' => 'Designated sites only?',
        'pets_allowed' => 'Pets allowed?',
        'dogs_required_to_be_leashed' => 'Dogs require leash?',
        'food_storage_required' => 'Food storage required?',
        'existing_sites_encouraged' => 'Existing sites encouraged?',
        'pack_it_in_pack_it_out' => 'Pack it in / pack it out?',
        'residential_use_prohibited' => 'Residential use prohibited?',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'tri', 'rules.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }
    $add('generator_restrictions', 'Generator restrictions?', 'rules', 'tri', 'rules.generator_restrictions', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('generator_quiet_hours', 'Generator quiet hours?', 'rules', 'tri', 'rules.generator_quiet_hours', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('generator_quiet_hours_begin', 'Generator quiet hours begin', 'rules', 'select', 'rules.generator_quiet_hours_begin', [
        'allow_unknown' => true,
        'options' => [
            'sunset' => 'Sunset',
            '19:00' => '7 PM',
            '20:00' => '8 PM',
            '21:00' => '9 PM',
            '22:00' => '10 PM',
            '23:00' => '11 PM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('generator_quiet_hours_end', 'Generator quiet hours end', 'rules', 'select', 'rules.generator_quiet_hours_end', [
        'allow_unknown' => true,
        'options' => [
            'sunrise' => 'Sunrise',
            '06:00' => '6 AM',
            '07:00' => '7 AM',
            '08:00' => '8 AM',
            '09:00' => '9 AM',
            '10:00' => '10 AM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('stay_limit_days', 'Stay limit', 'rules', 'select', 'rules.stay_limit_days', [
        'allow_unknown' => true,
        'options' => [
            '1' => '1 day', '3' => '3 days', '5' => '5 days',
            '7' => '7 days', '10' => '10 days', '14' => '14 days',
            '16' => '16 days', '21' => '21 days', '28' => '28 days',
            '30' => '30 days',
            'permit-limit' => 'Permit Limit',
            'varies-by-season' => 'Varies by season',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('reservation_required', 'Reservation required?', 'rules', 'tri', 'rules.reservation_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('reservation_url', 'Reservation URL', 'rules', 'url', 'rules.reservation_url', [
        'wide' => true,
        'placeholder' => 'https://...',
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('reservation_fee', 'Reservation / booking fee', 'rules', 'number', 'rules.reservation_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('membership_required', 'Membership required?', 'rules', 'tri', 'rules.membership_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('membership_url', 'Membership URL', 'rules', 'url', 'rules.membership_url', [
        'wide' => true,
        'placeholder' => 'https://...',
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('membership_fee', 'Membership fee', 'rules', 'number', 'rules.membership_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('check_in_required', 'Check-in required?', 'rules', 'tri', 'rules.check_in_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('check_in_begins', 'Check-in begins', 'rules', 'select', 'rules.check_in_begins', [
        'allow_unknown' => true,
        'options' => [
            'anytime' => 'Anytime',
            '10:00' => '10 AM',
            '11:00' => '11 AM',
            '12:00' => '12 PM',
            '13:00' => '1 PM',
            '14:00' => '2 PM',
            '15:00' => '3 PM',
            '16:00' => '4 PM',
            '17:00' => '5 PM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('check_out_required', 'Check-out required?', 'rules', 'tri', 'rules.check_out_required', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('checkout_ends', 'Check-out ends', 'rules', 'select', 'rules.checkout_ends', [
        'allow_unknown' => true,
        'options' => [
            'anytime' => 'Anytime',
            '06:00' => '6 AM',
            '07:00' => '7 AM',
            '08:00' => '8 AM',
            '09:00' => '9 AM',
            '10:00' => '10 AM',
            '11:00' => '11 AM',
            '12:00' => '12 PM',
            '13:00' => '1 PM',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);

    $add('fee', 'Camping fee', 'rules', 'number', 'rules.fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('entrance_facility_fee', 'Entrance / day-use / facility fee', 'rules', 'number', 'rules.entrance_facility_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('parking_fee', 'Parking fee / pass', 'rules', 'number', 'rules.parking_fee', [
        'step' => '.01',
        'min' => '0',
        'max' => '100000',
        'placeholder' => '0.00',
        'format' => 'currency',
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('current_fire_restrictions_url', 'Current fire restrictions URL', 'rules', 'url', 'rules.current_fire_restrictions_url', [
        'wide' => true,
        'placeholder' => 'https://...',
        'points_categories' => ['seasons_rules_services'],
    ]);
    foreach ([
        'nearest_town' => 'Nearest town',
        'nearest_fuel' => 'Nearest fuel',
        'nearest_ev_charging' => 'Nearest EV charging',
        'nearest_alcohol_sales' => 'Nearest alcohol sales',
        'nearest_propane' => 'Nearest propane',
        'nearest_grocery' => 'Nearest grocery',
        'nearest_water' => 'Nearest potable water',
        'nearest_toilet' => 'Nearest public toilet',
        'nearest_hospital' => 'Nearest hospital / emergency care',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'select', 'rules.' . $key, [
            'allow_unknown' => true,
            'options' => $distance,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }

    /* Experience and recommendations */
    foreach ([
        'experience_sunrise_view' => ['Sunrise view', 'experience.sunrise_view'],
        'experience_sunset_view' => ['Sunset view', 'experience.sunset_view'],
        'experience_overall_scenery' => ['Overall scenery', 'experience.overall_scenery'],
        'experience_night_sky' => ['Night sky', 'experience.night_sky'],
        'experience_stargazing' => ['Stargazing', 'experience.stargazing'],
        'experience_quiet_evening' => ['Quiet evening', 'experience.quiet_evening'],
        'experience_overnight_comfort' => ['Overnight comfort', 'experience.overnight_comfort'],
        'experience_extended_stay_comfort' => ['Extended-stay comfort', 'experience.extended_stay_comfort'],
        'experience_sensory_retreat' => ['Sensory retreat', 'experience.sensory_retreat'],
        'experience_remote_work' => ['Remote work', 'experience.remote_work'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'experience', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => 'Poor',
            'high' => 'Excellent',
            'points_categories' => ['experience_recommendations'],
        ]);
    }

    foreach ([
        'recommended_overnight_stop' => 'Recommended for overnight stop?',
        'recommended_quiet_evening' => 'Recommended for a quiet evening?',
        'recommended_extended_stay' => 'Recommended for an extended stay?',
        'recommended_sensory_retreat' => 'Recommended for a sensory retreat?',
        'recommended_stargazing' => 'Recommended for stargazing?',
        'recommended_remote_work' => 'Recommended for remote work?',
        'recommended_solo_travel' => 'Good for solo travel?',
        'recommended_families' => 'Good for families?',
        'recommended_large_groups' => 'Good for large groups?',
    ] as $key => $label) {
        $add($key, $label, 'experience', 'tri', 'experience.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['experience_recommendations'],
        ]);
    }

    /*
     * These six recommendation flags are derived from the paired 1â5
     * experience rating instead of asking the contributor the same thing twice.
     *
     * 4â5 = recommended
     * 1â2 = not recommended
     * 3   = neutral / no derived recommendation
     */
    $derivedRecommendations = [
        'recommended_overnight_stop' => 'experience_overnight_comfort',
        'recommended_quiet_evening' => 'experience_quiet_evening',
        'recommended_extended_stay' => 'experience_extended_stay_comfort',
        'recommended_sensory_retreat' => 'experience_sensory_retreat',
        'recommended_stargazing' => 'experience_stargazing',
        'recommended_remote_work' => 'experience_remote_work',
    ];

    foreach ($derivedRecommendations as $derivedKey => $sourceKey) {
        if (!isset($f[$derivedKey])) {
            continue;
        }

        $f[$derivedKey]['derived'] = true;
        $f[$derivedKey]['derived_from'] = $sourceKey;
        $f[$derivedKey]['counts_toward_completion'] = false;
        $f[$derivedKey]['points_categories'] = [];
    }
    $add('not_recommended_for', 'Not recommended for', 'experience', 'textarea', 'experience.not_recommended_for', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Example: low-clearance vehicles, people sensitive to road noise, large trailers...',
        'points_categories' => ['experience_recommendations'],
        'counts_toward_completion' => false,
    ]);

    /* Scout notes */
    foreach ([1, 2, 3] as $noteNumber) {
        $add(
            'scout_note_' . $noteNumber,
            'Scout note ' . $noteNumber,
            'scout_notes',
            'textarea',
            'experience.scout_note_' . $noteNumber,
            [
                'wide' => true,
                'rows' => 2,
                'maxlength' => 200,
                'placeholder' => 'Short observation, tip, or useful detail worth spotting at a glance.',
                'points_categories' => ['experience_recommendations'],
                'counts_toward_completion' => false,
            ]
        );
    }

}
