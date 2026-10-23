<?php

declare(strict_types=1);

/* Connectivity, sensory/environment, accessibility/safety, and experience. */

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
        'connectivity_satellite' => ['Satellite internet service', 'None', 'Excellent', 'connectivity.satellite'],
    ] as $key => [$label, $low, $high, $storage]) {
        $add($key, $label, 'connectivity', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['connectivity'],
        ]);
    }

    $add('connectivity_services_tested', 'Service Tested', 'connectivity', 'multiselect', 'connectivity.services_tested', [
        'wide' => true,
        'summary' => 'Select services tested',
        'options' => [
            'starlink' => 'Starlink',
            'amazon_leo' => 'Amazon Leo',
            'other' => 'Other',
            'none' => 'None Tested',
        ],
        'help' => 'Select the satellite services you personally tested during this visit. None Tested cannot be combined with a service.',
        'points_categories' => ['connectivity'],
    ]);

    $add('connectivity_starlink_tested', 'Starlink tested?', 'connectivity', 'tri', 'connectivity.starlink_tested', [
        'allow_unknown' => true,
        'points_categories' => [],
        'counts_toward_completion' => false,
        'derived' => true,
    ]);

    $add('connectivity_amazon_leo_tested', 'Amazon Leo tested?', 'connectivity', 'tri', 'connectivity.amazon_leo_tested', [
        'allow_unknown' => true,
        'points_categories' => [],
        'counts_toward_completion' => false,
        'derived' => true,
    ]);

    $add('connectivity_starlink_note', 'Connectivity Summary', 'connectivity', 'textarea', 'connectivity.starlink_note', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Providers tested, obstruction, speeds, or other connectivity details.',
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
        'daytime_social_interaction' => ['Social interaction', 'Very low', 'Very high', 'sensory.daytime.social_interaction_likelihood', 'Daytime'],
        'nighttime_noise' => ['Noise', 'Very quiet', 'Very loud', 'sensory.nighttime.noise', 'Nighttime'],
        'nighttime_traffic' => ['Traffic', 'None', 'Heavy', 'sensory.nighttime.traffic', 'Nighttime'],
        'nighttime_crowds' => ['People', 'None', 'Many', 'sensory.nighttime.crowds', 'Nighttime'],
        'nighttime_privacy' => ['Privacy', 'None', 'Excellent', 'sensory.nighttime.privacy', 'Nighttime'],
        'nighttime_light_pollution' => ['Artificial light', 'Dark', 'Bright', 'sensory.nighttime.light_pollution', 'Nighttime'],
        'nighttime_sensory_comfort' => ['Sensory comfort', 'Difficult', 'Excellent', 'sensory.nighttime.sensory_comfort', 'Nighttime'],
        'nighttime_social_interaction' => ['Social interaction', 'Very low', 'Very high', 'sensory.nighttime.social_interaction_likelihood', 'Nighttime'],
        'felt_safe_daytime_rating' => ['Felt safe', 'Not safe', 'Very safe', 'sensory.daytime.felt_safe_rating', 'Daytime'],
        'felt_safe_nighttime_rating' => ['Felt safe', 'Not safe', 'Very safe', 'sensory.nighttime.felt_safe_rating', 'Nighttime'],
        'sensory_dust_from_traffic' => ['Dust from traffic', 'Low', 'High', 'sensory.details.dust_from_traffic', 'Specific sensory conditions'],
        'road_exposure' => ['Road exposure', 'Not exposed', 'Very exposed', 'details.road_exposure', 'Specific sensory conditions'],
        'sensory_generator_noise' => ['Generator noise', 'Low', 'High', 'sensory.details.generator_noise', 'Specific sensory conditions'],
        'sensory_aircraft_noise' => ['Aircraft noise', 'Low', 'High', 'sensory.details.aircraft_noise', 'Specific sensory conditions'],
        'sensory_train_noise' => ['Train noise', 'Low', 'High', 'sensory.details.train_noise', 'Specific sensory conditions'],
        'sensory_dog_barking' => ['Dogs barking', 'Low', 'High', 'sensory.details.dog_barking', 'Specific sensory conditions'],
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

    $add(
        'warning_motorized_recreation_traffic',
        'Motorized recreation traffic?',
        'sensory',
        'tri',
        'details.warning_motorized_recreation_traffic',
        [
            'allow_unknown' => true,
            'subsection' => 'Specific sensory conditions',
            'points_categories' => ['sensory'],
        ]
    );

    /* Safety and warnings */
    foreach ([
        'felt_safe_daytime' => ['Felt safe during the day?', 'details.felt_safe_daytime'],
        'felt_safe_nighttime' => ['Felt safe at night?', 'details.felt_safe_nighttime'],
        'flash_flood_risk' => ['Flash Flood Risk', 'details.flash_flood_risk'],
        'wildfire_risk' => ['Wildfire Risk', 'details.wildfire_risk'],
        'fall_hazard' => ['Trip / Fall Hazard', 'details.fall_hazard'],
        'cliff_exposure' => ['Cliff exposure?', 'details.cliff_exposure'],
        'rockfall_risk' => ['Rockfall Risk', 'details.rockfall_risk'],
        'wildlife_risk' => ['Wildlife Risk', 'details.wildlife_risk'],
        'traffic_hazard' => ['Traffic Risk', 'details.traffic_hazard'],
        'emergency_access' => ['Emergency Vehicle Access', 'details.emergency_access'],
        'warning_possible_downed_trees' => ['Downed Tree Risk', 'details.warning_possible_downed_trees'],
        'warning_blind_turn_traffic_nearby' => ['Blind Turn Nearby', 'details.warning_blind_turn_traffic_nearby'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'safety', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => ['safety_warnings'],
        ]);
    }

    require_once __DIR__ . '/fields-rules-services.php';
    llama_place_report_add_rules_service_fields(
        $add,
        $distance
    );

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

    /* Recommendations are direct Scout answers, not derived from ratings. */
    foreach ([
        'recommended_overnight_stop',
        'recommended_extended_stay',
        'recommended_remote_work',
        'recommended_solo_travel',
        'recommended_families',
        'recommended_large_groups',
    ] as $key) {
        if (!isset($f[$key])) {
            continue;
        }
        $f[$key]['derived'] = false;
        unset($f[$key]['derived_from']);
    }

    $add('not_recommended_for', 'Not recommended for', 'experience', 'textarea', 'experience.not_recommended_for', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Example: low-clearance vehicles, people sensitive to road noise, large trailers...',
        'points_categories' => ['experience_recommendations'],
        'counts_toward_completion' => false,
    ]);

    $add('environment_notes', 'Environment notes', 'landscape_setting', 'textarea', 'details.environment_notes', [
        'wide' => true, 'rows' => 3, 'counts_toward_completion' => false,
        'points_categories' => ['environment'],
    ]);
    $add('parking_notes', 'Parking notes', 'site_vehicle', 'textarea', 'details.parking_notes', [
        'wide' => true, 'rows' => 3, 'counts_toward_completion' => false,
    ]);
    $add('experience_summary', 'Experience summary', 'experience', 'textarea', 'experience.experience_summary', [
        'wide' => true, 'rows' => 3, 'counts_toward_completion' => false,
        'points_categories' => ['experience_recommendations'],
    ]);

    /* Visit-specific history. Preserve the Scout's own observations. */
    $add('visit_length_of_stay', 'Length of Stay', 'scout_notes', 'select', 'experience.visit_length_of_stay', [
        'options' => array_merge(
            ['overnight' => 'Overnight'],
            array_combine(array_map('strval', range(1, 15)), array_map(static fn(int $days): string => $days . ($days === 1 ? ' Day' : ' Days'), range(1, 15))),
            ['28' => '28 Days', '30' => '30 Days', 'other' => 'Other']
        ),
        'help' => 'Select the length of this visit. Overnight is distinct from a one-day visit.',
        'points_categories' => [],
        'counts_toward_completion' => false,
    ]);

    $add('visit_setup_types', 'Type of Setup', 'scout_notes', 'multiselect', 'experience.visit_setup_types', [
        'wide' => true,
        'summary' => 'Select setups used',
        'options' => [
            'tent' => 'Tent',
            'motorhome' => 'Motorhome',
            'trailer' => 'Trailer',
            'truck_camper' => 'Truck Camper',
            'car_camping' => 'Car Camping',
            'hammock' => 'Hammock',
        ],
        'help' => 'Select every setup you personally used during this visit.',
        'points_categories' => [],
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

