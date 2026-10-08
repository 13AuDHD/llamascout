<?php

declare(strict_types=1);

/* Site + vehicle, road access, and amenities questions. */

function llama_place_report_add_site_fields(
    callable $add
): void {
    /* Site and vehicle */
    $add('vehicle_capacity', 'Vehicle capacity', 'site_vehicle', 'select', 'details.vehicle_capacity', [
        'options' => [
            '1' => '1 vehicle', '2' => '2 vehicles', '3' => '3 vehicles',
            '4' => '4 vehicles', '5' => '5 vehicles', '6' => '6 vehicles',
            '7' => '7 vehicles', '8' => '8 vehicles', '9' => '9 vehicles',
            '10' => '10 vehicles', '11' => '10+ vehicles',
        ],
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);
    $add('max_vehicle_length_feet', 'Maximum vehicle length', 'site_vehicle', 'select', 'details.max_vehicle_length_feet', [
        'options' => [
            '15' => 'About 15 ft', '20' => 'About 20 ft', '25' => 'About 25 ft',
            '30' => 'About 30 ft', '35' => 'About 35 ft', '40' => 'About 40 ft',
            '45' => 'About 45 ft', '50' => 'About 50 ft', '60' => '50+ ft',
        ],
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);
    $add('parking_surface', 'Parking surface', 'site_vehicle', 'select', 'details.parking_surface', [
        'options' => llama_place_report_surface_options(),
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);
    $add('ground_condition', 'Ground condition', 'site_vehicle', 'select', 'details.ground_condition', [
        'options' => [
            'paved' => 'Paved',
            'level-firm' => 'Mostly level and firm',
            'uneven-firm' => 'Uneven but firm',
            'rocky' => 'Rocky',
            'gravel' => 'Gravel',
            'soft' => 'Soft / sandy',
            'mud-prone' => 'Mud-prone',
            'grass' => 'Grassy',
            'mixed' => 'Mixed',
        ],
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('campsite_count', 'Number of campsites', 'site_vehicle', 'number', 'details.campsite_count', [
        'step' => '1',
        'min' => '1',
        'max' => '10000',
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('site_number', 'Site number / identifier', 'site_vehicle', 'text', 'details.site_number', [
        'maxlength' => 80,
        'placeholder' => 'Example: 14, B-27, Loop C #8',
        'counts_toward_completion' => false,
        'points_categories' => [],
    ]);

    $add('site_hookups_available', 'Hookups at this site?', 'site_vehicle', 'tri', 'details.site_hookups_available', [
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('hookup_electric', 'Electric hookup at site?', 'site_vehicle', 'tri', 'details.hookup_electric', [
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('hookup_electric_service', 'Electric hookup service', 'site_vehicle', 'select', 'details.hookup_electric_service', [
        'allow_unknown' => true,
        'options' => [
            '20a' => '15 / 20 amp',
            '30a' => '30 amp',
            '50a' => '50 amp',
            '20-30a' => '15 / 20 + 30 amp',
            '30-50a' => '30 + 50 amp',
            '20-30-50a' => '15 / 20 + 30 + 50 amp',
        ],
        'points_categories' => ['site_vehicle'],
    ]);

    $add('hookup_water', 'Water hookup at site?', 'site_vehicle', 'tri', 'details.hookup_water', [
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('hookup_sewer', 'Sewer hookup at site?', 'site_vehicle', 'tri', 'details.hookup_sewer', [
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    /* Each length question follows its own suitability answer. */
    $add('trailer_suitable', 'Trailer suitable?', 'site_vehicle', 'tri', 'details.trailer_suitable', [
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('max_trailer_length_feet', 'Maximum trailer length', 'site_vehicle', 'select', 'details.max_trailer_length_feet', [
        'options' => [
            '10' => 'About 10 ft', '15' => 'About 15 ft', '20' => 'About 20 ft',
            '25' => 'About 25 ft', '30' => 'About 30 ft', '35' => 'About 35 ft',
            '40' => 'About 40 ft', '45' => 'About 45 ft', '50' => 'About 50 ft',
            '60' => '50+ ft',
        ],
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('rv_suitable', 'RV suitable?', 'site_vehicle', 'tri', 'details.rv_suitable', [
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    $add('max_rv_length_feet', 'Maximum RV length', 'site_vehicle', 'select', 'details.max_rv_length_feet', [
        'options' => [
            '15' => 'About 15 ft', '20' => 'About 20 ft', '25' => 'About 25 ft',
            '30' => 'About 30 ft', '35' => 'About 35 ft', '40' => 'About 40 ft',
            '45' => 'About 45 ft', '50' => 'About 50 ft', '60' => '50+ ft',
        ],
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    foreach ([
        'tent_camping_suitable' => 'Tent camping suitable?',
        'turnaround_space' => 'Turnaround space?',
        'pull_through' => 'Pull-through site?',
        'back_in' => 'Back-in site?',
    ] as $key => $label) {
        $add($key, $label, 'site_vehicle', 'tri', 'details.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['site_vehicle'],
        ]);
    }

    /*
     * Legacy compatibility only. Leveling Required is derived from the
     * Levelness rating and is no longer a separate contributor question.
     */
    $add('leveling_required', 'Leveling required?', 'site_vehicle', 'derived', 'details.leveling_required', [
        'derived' => true,
        'counts_toward_completion' => false,
        'points_categories' => [],
    ]);

    foreach ([
        'levelness' => ['Levelness', 'Very uneven', 'Very level'],
        'site_open_sky' => ['Open sky', 'Enclosed', 'Wide open'],
        'tree_cover' => ['Tree cover', 'None', 'Heavy'],
        'site_shade' => ['Shade', 'None', 'Heavy'],
    ] as $key => [$label, $low, $high]) {
        $add($key, $label, 'site_vehicle', 'rating', 'details.' . $key, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['site_vehicle'],
        ]);
    }

    /* Road access */
    $add('road_surface', 'Road surface', 'road_access', 'select', 'details.road_surface', [
        'options' => llama_place_report_surface_options(false),
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ]);
    $add('road_width', 'Road width', 'road_access', 'select', 'details.road_width', [
        'options' => [
            'one-lane' => 'One lane',
            'one-and-half-lane' => 'About 1.5 lanes',
            'two-lane' => 'Two lane',
            'wide-two-lane' => 'Wide two lane',
            'varies' => 'Varies significantly',
        ],
        'allow_unknown' => true,
        'points_categories' => ['road_access'],
    ]);

    foreach ([
        'sedan_accessible' => 'Sedan accessible?',
        'high_clearance_recommended' => 'High clearance recommended?',
        'four_wheel_drive_recommended' => '4WD recommended?',
        'water_crossings' => 'Water crossings?',
        'downed_tree_risk' => 'Downed-tree risk?',
    ] as $key => $label) {
        $add($key, $label, 'road_access', 'tri', 'details.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['road_access'],
        ]);
    }

    foreach ([
        'site_access_difficulty' => ['Site access difficulty', 'Easy', 'Very difficult'],
        'road_overall_difficulty' => ['Road difficulty', 'Easy', 'Very difficult'],
        'road_stress' => ['Driving stress', 'Relaxed', 'Very stressful'],
        'rocks' => ['Rocks', 'None', 'Severe'],
        'washboards' => ['Washboards', 'None', 'Severe'],
        'potholes' => ['Potholes', 'None', 'Severe'],
        'mud_risk' => ['Mud risk', 'Low', 'High'],
        'steep_grades' => ['Steep grades', 'None', 'Severe'],
        'drop_off_exposure' => ['Drop-off exposure', 'None', 'Severe'],
    ] as $key => [$label, $low, $high]) {
        $add($key, $label, 'road_access', 'rating', 'details.' . $key, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['road_access'],
        ]);
    }

    /* Amenities */
    $add('amenity_none', 'No amenities', 'amenities', 'checkbox', 'details.warning_no_amenities', [
        'points_categories' => ['amenities', 'safety_warnings'],
        'completion_group' => 'amenities',
    ]);
    foreach ([
        'toilets' => 'Toilets',
        'potable_water' => 'Potable water',
        'trash' => 'Trash service',
        'fire_ring' => 'Metal Fire ring',
        'picnic_table' => 'Picnic table',
        'bear_box' => 'Bear box',
        'showers' => 'Showers',
        'electricity' => 'Electricity',
        'dump_station' => 'Dump station',
        'wifi' => 'WiFi',
        'laundry' => 'Laundry',
    ] as $suffix => $label) {
        $add('amenity_' . $suffix, $label, 'amenities', 'checkbox', 'amenities.' . $suffix, [
            'points_categories' => ['amenities'],
            'completion_group' => 'amenities',
        ]);
    }

}
