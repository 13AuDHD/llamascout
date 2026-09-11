<?php

declare(strict_types=1);

/*
 * =========================================================
 * LLAMA SCOUT PLACE REPORT
 * SINGLE SOURCE OF TRUTH
 *
 * Defines every Place Report question, label, section,
 * input type, choices, storage path, answer-state rules,
 * display behavior, and point-category membership.
 * =========================================================
 */

function llama_place_report_unknown_token(): string
{
    return '__LLAMA_UNKNOWN__';
}

function llama_place_report_unanswered_token(): string
{
    return '__LLAMA_UNANSWERED__';
}

function llama_place_report_category_definitions(): array
{
    return [
        'site_vehicle' => [
            'label' => 'Site + Vehicle',
            'policy_key' => 'new_place_site_vehicle',
            'mode' => 'weighted',
        ],
        'road_access' => [
            'label' => 'Road Access',
            'policy_key' => 'new_place_road_access',
            'mode' => 'weighted',
        ],
        'amenities' => [
            'label' => 'Amenities',
            'policy_key' => 'new_place_amenities',
            'mode' => 'any',
        ],
        'connectivity' => [
            'label' => 'Connectivity',
            'policy_key' => 'new_place_connectivity',
            'mode' => 'any',
        ],
        'sensory' => [
            'label' => 'Sensory',
            'policy_key' => 'new_place_sensory',
            'mode' => 'weighted',
        ],
        'environment' => [
            'label' => 'Environment',
            'policy_key' => 'new_place_environment',
            'mode' => 'weighted',
        ],
        'accessibility' => [
            'label' => 'Accessibility',
            'policy_key' => 'new_place_accessibility',
            'mode' => 'weighted',
        ],
        'safety_warnings' => [
            'label' => 'Safety + Warnings',
            'policy_key' => 'new_place_safety_warnings',
            'mode' => 'weighted',
        ],
        'seasons_rules_services' => [
            'label' => 'Seasons + Rules + Services',
            'policy_key' => 'new_place_seasons_rules_services',
            'mode' => 'weighted',
        ],
        'experience_recommendations' => [
            'label' => 'Experience + Recommendations',
            'policy_key' => 'new_place_experience_recommendations',
            'mode' => 'weighted',
        ],
    ];
}

function llama_place_report_sections(): array
{
    return [
        'basic' => [
            'label' => 'Basic information',
            'description' => 'Name, type, and when you visited',
            'icon' => 'fa-location-dot',
            'open' => true,
        ],
        'location' => [
            'label' => 'Location',
            'description' => 'GPS, elevation, road, town, county, state, and land',
            'icon' => 'fa-location-crosshairs',
            'open' => true,
        ],
        'site_vehicle' => [
            'label' => 'Site and vehicle fit',
            'description' => 'Size, parking, tents, RVs, trailers, and leveling',
            'icon' => 'fa-car-side',
        ],
        'road_access' => [
            'label' => 'Road access',
            'description' => 'Surface, width, difficulty, stress, mud, rocks, and obstacles',
            'icon' => 'fa-road',
        ],
        'amenities' => [
            'label' => 'Amenities',
            'description' => 'Check only what is actually available',
            'icon' => 'fa-circle-info',
        ],
        'connectivity' => [
            'label' => 'Connectivity',
            'description' => 'Cell carriers and Starlink',
            'icon' => 'fa-signal',
        ],
        'sensory' => [
            'label' => 'Sensory profile',
            'description' => 'Day, night, noise, traffic, people, smells, and exposure',
            'icon' => 'fa-brain',
        ],
        'environment_accessibility' => [
            'label' => 'Environment and accessibility',
            'description' => 'Terrain, views, exposure, mobility, and walking distance',
            'icon' => 'fa-tree',
        ],
        'safety' => [
            'label' => 'Safety and warnings',
            'description' => 'Hazards and conditions people should see quickly',
            'icon' => 'fa-shield-halved',
        ],
        'rules' => [
            'label' => 'Seasons, rules, and nearby services',
            'description' => 'Access seasons, camping rules, fees, fire, fuel, food, and medical care',
            'icon' => 'fa-cloud-sun',
        ],
        'experience' => [
            'label' => 'Experience and recommendations',
            'description' => 'Views, stars, comfort, quiet, remote work, and who it suits',
            'icon' => 'fa-star',
        ],
        'summaries' => [
            'label' => 'Summaries and reviewer notes',
            'description' => 'Useful context that does not fit into a dropdown',
            'icon' => 'fa-pen',
        ],
    ];
}

function llama_place_report_states(): array
{
    return [
        'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California',
        'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia',
        'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas',
        'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts',
        'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana',
        'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico',
        'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma',
        'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina',
        'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
        'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
        'District of Columbia', 'Puerto Rico',
    ];
}

function llama_place_report_distance_options(): array
{
    $options = [];

    for ($i = 1; $i <= 20; $i++) {
        $label = $i . ' mile' . ($i === 1 ? '' : 's');
        $options[$label] = $label;
    }

    $options['Over 20 miles'] = 'Over 20 miles';

    return $options;
}

function llama_place_report_land_managers(): array
{
    return [
        'U.S. Forest Service' => 'U.S. Forest Service',
        'Bureau of Land Management' => 'Bureau of Land Management (BLM)',
        'National Park Service' => 'National Park Service',
        'U.S. Fish and Wildlife Service' => 'U.S. Fish and Wildlife Service',
        'U.S. Army Corps of Engineers' => 'U.S. Army Corps of Engineers',
        'Bureau of Reclamation' => 'Bureau of Reclamation',
        'State government' => 'State government',
        'County / regional government' => 'County / regional government',
        'City / municipal government' => 'City / municipal government',
        'Tribal government' => 'Tribal government',
        'Private' => 'Private',
        'Other' => 'Other / mixed management',
    ];
}

function llama_place_report_land_types(): array
{
    return [
        'National Forest' => 'National Forest',
        'BLM Land' => 'BLM Land',
        'National Park' => 'National Park',
        'National Monument' => 'National Monument',
        'National Recreation Area' => 'National Recreation Area',
        'National Wildlife Refuge' => 'National Wildlife Refuge',
        'State Forest' => 'State Forest',
        'State Park' => 'State Park',
        'State Trust Land' => 'State Trust Land',
        'Wildlife Management Area' => 'Wildlife Management Area',
        'County / Regional Park' => 'County / Regional Park',
        'City / Municipal Land' => 'City / Municipal Land',
        'Army Corps of Engineers' => 'Army Corps of Engineers',
        'Bureau of Reclamation' => 'Bureau of Reclamation',
        'Tribal Land' => 'Tribal Land',
        'Private Land' => 'Private Land',
        'Roadside / Highway Right-of-Way' => 'Roadside / Highway Right-of-Way',
        'Other' => 'Other',
    ];
}

function llama_place_report_surface_options(bool $allowGrass = true): array
{
    $options = [
        'paved' => 'Paved / asphalt',
        'concrete' => 'Concrete',
        'graded-gravel' => 'Graded gravel',
        'loose-gravel' => 'Loose gravel',
        'hard-packed-dirt' => 'Hard-packed dirt',
        'dirt' => 'Dirt',
        'sand' => 'Sand',
        'rock' => 'Rock / bedrock',
    ];

    if ($allowGrass) {
        $options['grass'] = 'Grass';
    }

    $options['mixed'] = 'Mixed surface';

    return $options;
}

function llama_place_report_field_icon(
    string $key,
    array $field = []
): string {
    $icons = [
        'name' => 'fa-signature',
        'type' => 'fa-map-location-dot',
        'visited_at' => 'fa-calendar-check',
        'description' => 'fa-align-left',

        'latitude' => 'fa-location-crosshairs',
        'longitude' => 'fa-location-crosshairs',
        'elevation_feet' => 'fa-mountain',
        'road' => 'fa-road',
        'city' => 'fa-city',
        'county' => 'fa-map',
        'state' => 'fa-map',
        'region' => 'fa-signs-post',
        'land_manager' => 'fa-building-columns',
        'land_type' => 'fa-tree',

        'vehicle_capacity' => 'fa-car-side',
        'max_vehicle_length_feet' => 'fa-ruler-horizontal',
        'parking_surface' => 'fa-square-parking',
        'ground_condition' => 'fa-mountain-sun',
        'tent_camping_suitable' => 'fa-tent',
        'rv_suitable' => 'fa-caravan',
        'trailer_suitable' => 'fa-trailer',
        'leveling_required' => 'fa-scale-balanced',
        'turnaround_space' => 'fa-rotate',
        'pull_through' => 'fa-arrow-right',
        'back_in' => 'fa-arrow-left',

        'road_surface' => 'fa-road',
        'road_width' => 'fa-arrows-left-right',
        'sedan_accessible' => 'fa-car',
        'high_clearance_recommended' => 'fa-truck-pickup',
        'four_wheel_drive_recommended' => 'fa-truck-monster',
        'water_crossings' => 'fa-water',
        'downed_tree_risk' => 'fa-tree',
        'seasonal_closure' => 'fa-calendar-xmark',

        'amenity_none' => 'fa-circle-xmark',
        'amenity_toilets' => 'fa-restroom',
        'amenity_potable_water' => 'fa-faucet-drip',
        'amenity_trash' => 'fa-trash-can',
        'amenity_fire_ring' => 'fa-fire',
        'amenity_picnic_table' => 'fa-table-picnic',
        'amenity_bear_box' => 'fa-box',
        'amenity_showers' => 'fa-shower',
        'amenity_electricity' => 'fa-bolt',
        'amenity_dump_station' => 'fa-truck-droplet',
        'amenity_food_storage_required' => 'fa-box-archive',

        'connectivity_starlink_tested' => 'fa-satellite',
        'connectivity_starlink_note' => 'fa-satellite-dish',
        'warning_no_cell_service' => 'fa-signal',

        'wheelchair_friendly' => 'fa-wheelchair',
        'mobility_device_friendly' => 'fa-person-walking',
        'flat_walking_surface' => 'fa-road',
        'step_free_access' => 'fa-person-walking-arrow-right',
        'accessible_toilet' => 'fa-restroom',
        'accessible_picnic_table' => 'fa-table-picnic',
        'walking_distance_from_vehicle' => 'fa-person-walking',

        'felt_safe_daytime' => 'fa-sun',
        'felt_safe_nighttime' => 'fa-moon',
        'flash_flood_risk' => 'fa-water',
        'wildfire_risk' => 'fa-fire-flame-curved',
        'fall_hazard' => 'fa-person-falling',
        'cliff_exposure' => 'fa-mountain',
        'rockfall_risk' => 'fa-hill-rockslide',
        'wildlife_risk' => 'fa-paw',
        'traffic_hazard' => 'fa-car-burst',
        'emergency_access' => 'fa-truck-medical',

        'warning_exposed_to_road' => 'fa-road',
        'warning_zero_privacy' => 'fa-eye',
        'warning_passing_vehicle_dust' => 'fa-smog',
        'warning_possible_downed_trees' => 'fa-tree',
        'warning_no_tent_camping' => 'fa-tent-arrow-turn-left',
        'warning_limited_vehicle_length' => 'fa-ruler-horizontal',
        'warning_leveling_may_be_required' => 'fa-scale-balanced',
        'warning_motorized_recreation_traffic' => 'fa-motorcycle',
        'warning_blind_turn_traffic_nearby' => 'fa-triangle-exclamation',

        'best_months' => 'fa-calendar-check',
        'winter_access' => 'fa-snowflake',
        'overnight_camping_allowed' => 'fa-moon',
        'dispersed_camping_allowed' => 'fa-campground',
        'stay_limit_days' => 'fa-calendar-day',
        'permit_required' => 'fa-file-signature',
        'fee' => 'fa-dollar-sign',
        'campfire_allowed' => 'fa-fire',
        'pack_it_in_pack_it_out' => 'fa-trash-arrow-up',
        'existing_sites_encouraged' => 'fa-signs-post',
        'residential_use_prohibited' => 'fa-house-circle-xmark',
        'nearest_town' => 'fa-city',
        'nearest_fuel' => 'fa-gas-pump',
        'nearest_grocery' => 'fa-cart-shopping',
        'nearest_water' => 'fa-faucet-drip',
        'nearest_toilet' => 'fa-restroom',
        'nearest_hospital' => 'fa-hospital',

        'access_summary' => 'fa-road',
        'sensory_summary' => 'fa-brain',
        'contributor_notes' => 'fa-note-sticky',
    ];

    if (isset($icons[$key])) {
        return $icons[$key];
    }

    return match ((string) ($field['section'] ?? '')) {
        'connectivity' => 'fa-signal',
        'sensory' => 'fa-ear-listen',
        'environment_accessibility' => 'fa-tree',
        'safety' => 'fa-shield-halved',
        'rules' => 'fa-signs-post',
        'experience' => 'fa-star',
        default => 'fa-circle-info',
    };
}


function llama_place_report_fields(): array
{
    $distance = llama_place_report_distance_options();
    $f = [];

    $add = static function (
        string $key,
        string $label,
        string $section,
        string $type,
        string $storage,
        array $extra = []
    ) use (&$f): void {
        $f[$key] = array_merge(
            [
                'key' => $key,
                'label' => $label,
                'section' => $section,
                'type' => $type,
                'storage' => $storage,
                'points_categories' => [],
                'allow_unknown' => false,
            ],
            $extra
        );
    };

    /* Basic information */
    $add('name', 'Place name *', 'basic', 'text', 'name', [
        'required' => true,
        'maxlength' => 200,
        'wide' => true,
        'placeholder' => 'A suggested name will appear here',
        'name_suggestion' => true,
    ]);
    $add('type', 'Place type', 'basic', 'select', 'type', [
        'default' => 'dispersed-camping',
        'options' =>
            function_exists('community_place_types')
                ? community_place_types()
                : [
                    'dispersed-camping' => 'Dispersed camping',
                    'developed-campground' => 'Developed campground',
                    'vehicle-pulloff' => 'Vehicle pull-off',
                    'trailhead' => 'Trailhead',
                    'day-use' => 'Day-use area',
                    'other' => 'Other',
                ],
    ]);
    $add('visited_at', 'Date visited', 'basic', 'date', 'visited_at');
    $add('description', 'Description', 'basic', 'textarea', 'description', [
        'wide' => true,
        'rows' => 5,
        'placeholder' => 'What is this place, what is it like, and why would someone use it?',
    ]);

    /* Location */
    $add('latitude', 'Latitude', 'location', 'number', 'latitude', [
        'step' => 'any',
        'min' => '-90',
        'max' => '90',
        'location_field' => true,
        'placeholder' => '37.25222',
    ]);
    $add('longitude', 'Longitude', 'location', 'number', 'longitude', [
        'step' => 'any',
        'min' => '-180',
        'max' => '180',
        'location_field' => true,
        'placeholder' => '-107.2192',
    ]);
    $add('elevation_feet', 'Elevation (ft)', 'location', 'number', 'elevation_feet', [
        'step' => '1',
        'min' => '-1500',
        'max' => '30000',
        'location_field' => true,
    ]);
    $add('road', 'Road', 'location', 'text', 'road', [
        'location_field' => true,
        'placeholder' => 'Forest Road 622',
    ]);
    $add('city', 'Nearest city / locality', 'location', 'text', 'city', [
        'location_field' => true,
    ]);
    $add('county', 'County / Parish / Municipality', 'location', 'text', 'county', [
        'location_field' => true,
    ]);
    $stateList = llama_place_report_states();
    $add('state', 'State', 'location', 'select', 'state', [
        'location_field' => true,
        'options' => array_combine($stateList, $stateList) ?: [],
    ]);
    $add('region', 'Region / ranger district', 'location', 'text', 'region', [
        'placeholder' => 'Pagosa Ranger District',
    ]);
    $add('land_manager', 'Land manager', 'location', 'select', 'land_manager', [
        'options' => llama_place_report_land_managers(),
        'allow_unknown' => true,
    ]);
    $add('land_type', 'Land type', 'location', 'select', 'land_type', [
        'options' => llama_place_report_land_types(),
        'allow_unknown' => true,
    ]);

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
            'level-firm' => 'Mostly level and firm',
            'uneven-firm' => 'Uneven but firm',
            'rocky' => 'Rocky',
            'soft' => 'Soft / sandy',
            'mud-prone' => 'Mud-prone',
            'grass' => 'Grassy',
            'mixed' => 'Mixed',
        ],
        'allow_unknown' => true,
        'points_categories' => ['site_vehicle'],
    ]);

    foreach ([
        'tent_camping_suitable' => 'Tent camping suitable?',
        'rv_suitable' => 'RV suitable?',
        'trailer_suitable' => 'Trailer suitable?',
        'leveling_required' => 'Leveling required?',
        'turnaround_space' => 'Turnaround space?',
        'pull_through' => 'Pull-through site?',
        'back_in' => 'Back-in site?',
    ] as $key => $label) {
        $add($key, $label, 'site_vehicle', 'tri', 'details.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['site_vehicle'],
        ]);
    }

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
        'seasonal_closure' => 'Seasonal closure?',
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
        'warning' => true,
    ]);
    foreach ([
        'toilets' => 'Toilets',
        'potable_water' => 'Potable water',
        'trash' => 'Trash service',
        'fire_ring' => 'Fire ring',
        'picnic_table' => 'Picnic table',
        'bear_box' => 'Bear box',
        'showers' => 'Showers',
        'electricity' => 'Electricity',
        'dump_station' => 'Dump station',
        'food_storage_required' => 'Food storage required',
    ] as $suffix => $label) {
        $add('amenity_' . $suffix, $label, 'amenities', 'checkbox', 'amenities.' . $suffix, [
            'points_categories' => ['amenities'],
        ]);
    }

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
    $add('connectivity_starlink_tested', 'Starlink actually tested?', 'connectivity', 'tri', 'connectivity.starlink_tested', [
        'allow_unknown' => true,
        'points_categories' => ['connectivity'],
    ]);
    $add('connectivity_starlink_note', 'Starlink notes', 'connectivity', 'textarea', 'connectivity.starlink_note', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Clear northern sky, heavy tree obstruction, not personally tested, etc.',
        'points_categories' => ['connectivity'],
    ]);

    /*
     * Derived warning.
     *
     * This field is intentionally assigned to a non-rendered section and
     * a computed storage path. It never creates a duplicate form question,
     * never creates a database column, and is excluded automatically from
     * the Place Update persistence map.
     *
     * Quick warnings still see it because they iterate the shared field
     * definitions directly. The computed value is resolved below by
     * llama_place_report_get_path().
     */
    $add(
        'warning_no_cell_service',
        'No cell service',
        '__derived',
        'derived',
        'computed.warning_no_cell_service',
        [
            'warning' => true,
        ]
    );

    /* Sensory */
    foreach ([
        'daytime_noise' => ['Noise', 'Very quiet', 'Very loud', 'sensory.daytime.noise', 'Daytime'],
        'daytime_traffic' => ['Traffic', 'None', 'Heavy', 'sensory.daytime.traffic', 'Daytime'],
        'daytime_crowds' => ['Crowds', 'Empty', 'Crowded', 'sensory.daytime.crowds', 'Daytime'],
        'daytime_privacy' => ['Privacy', 'None', 'Excellent', 'sensory.daytime.privacy', 'Daytime'],
        'daytime_light_pollution' => ['Artificial light', 'None', 'Heavy', 'sensory.daytime.light_pollution', 'Daytime'],
        'daytime_sensory_comfort' => ['Sensory comfort', 'Difficult', 'Excellent', 'sensory.daytime.sensory_comfort', 'Daytime'],
        'daytime_social_interaction' => ['Chance of social interaction', 'Very low', 'Very high', 'sensory.daytime.social_interaction_likelihood', 'Daytime'],
        'nighttime_noise' => ['Noise', 'Very quiet', 'Very loud', 'sensory.nighttime.noise', 'Nighttime'],
        'nighttime_traffic' => ['Traffic', 'None', 'Heavy', 'sensory.nighttime.traffic', 'Nighttime'],
        'nighttime_crowds' => ['Crowds', 'Empty', 'Crowded', 'sensory.nighttime.crowds', 'Nighttime'],
        'nighttime_privacy' => ['Privacy', 'None', 'Excellent', 'sensory.nighttime.privacy', 'Nighttime'],
        'nighttime_light_pollution' => ['Light pollution', 'Dark', 'Bright', 'sensory.nighttime.light_pollution', 'Nighttime'],
        'nighttime_sensory_comfort' => ['Sensory comfort', 'Difficult', 'Excellent', 'sensory.nighttime.sensory_comfort', 'Nighttime'],
        'nighttime_social_interaction' => ['Chance of social interaction', 'Very low', 'Very high', 'sensory.nighttime.social_interaction_likelihood', 'Nighttime'],
        'sensory_dust_from_traffic' => ['Dust from traffic', 'Low', 'High', 'sensory.details.dust_from_traffic', 'Specific sensory conditions'],
        'sensory_generator_noise' => ['Generator noise', 'Low', 'High', 'sensory.details.generator_noise', 'Specific sensory conditions'],
        'sensory_aircraft_noise' => ['Aircraft noise', 'Low', 'High', 'sensory.details.aircraft_noise', 'Specific sensory conditions'],
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

    /* Environment and accessibility */
    foreach ([
        'environment_forest' => ['Forest environment?', 'details.forest', 'environment'],
        'environment_mountains' => ['Mountains present?', 'details.mountains', 'environment'],
        'environment_water_nearby' => ['Water nearby?', 'details.water_nearby', 'environment'],
        'environment_water_view' => ['Water view?', 'details.water_view', 'environment'],
        'environment_mountain_view' => ['Mountain view?', 'details.mountain_view', 'environment'],
        'environment_forest_view' => ['Forest view?', 'details.forest_view', 'environment'],
        'environment_wildlife' => ['Wildlife common?', 'details.wildlife', 'environment'],
        'environment_bugs' => ['Bugs significant?', 'details.bugs', 'environment'],
        'wheelchair_friendly' => ['Wheelchair friendly?', 'details.wheelchair_friendly', 'accessibility'],
        'mobility_device_friendly' => ['Outdoor mobility device friendly?', 'details.mobility_device_friendly', 'accessibility'],
        'flat_walking_surface' => ['Flat walking surface?', 'details.flat_walking_surface', 'accessibility'],
        'step_free_access' => ['Step-free access?', 'details.step_free_access', 'accessibility'],
        'accessible_toilet' => ['Accessible toilet?', 'details.accessible_toilet', 'accessibility'],
        'accessible_picnic_table' => ['Accessible picnic table?', 'details.accessible_picnic_table', 'accessibility'],
    ] as $key => [$label, $storage, $category]) {
        $add($key, $label, 'environment_accessibility', 'tri', $storage, [
            'allow_unknown' => true,
            'points_categories' => [$category],
        ]);
    }

    foreach ([
        'environment_wind_exposure' => ['Wind exposure', 'Protected', 'Very exposed', 'details.wind_exposure'],
        'environment_sun_exposure' => ['Sun exposure', 'Low', 'Full sun', 'details.sun_exposure'],
        'environment_shade' => ['Environment shade', 'None', 'Heavy', 'details.environment_shade'],
        'environment_open_sky' => ['Open sky', 'Low', 'Wide open', 'details.environment_open_sky'],
    ] as $key => [$label, $low, $high, $storage]) {
        $add($key, $label, 'environment_accessibility', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => $low,
            'high' => $high,
            'points_categories' => ['environment'],
        ]);
    }
    $add('walking_distance_from_vehicle', 'Walking distance from vehicle', 'environment_accessibility', 'select', 'details.walking_distance_from_vehicle', [
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
        'felt_safe_daytime' => ['Felt safe during the day?', 'details.felt_safe_daytime', false],
        'felt_safe_nighttime' => ['Felt safe at night?', 'details.felt_safe_nighttime', false],
        'flash_flood_risk' => ['Flash-flood risk?', 'details.flash_flood_risk', false],
        'wildfire_risk' => ['Wildfire risk?', 'details.wildfire_risk', false],
        'fall_hazard' => ['Fall hazard?', 'details.fall_hazard', false],
        'cliff_exposure' => ['Cliff exposure?', 'details.cliff_exposure', false],
        'rockfall_risk' => ['Rockfall risk?', 'details.rockfall_risk', false],
        'wildlife_risk' => ['Wildlife risk?', 'details.wildlife_risk', false],
        'traffic_hazard' => ['Traffic hazard?', 'details.traffic_hazard', false],
        'emergency_access' => ['Emergency vehicle access?', 'details.emergency_access', false],
        'warning_exposed_to_road' => ['Exposed to road?', 'details.warning_exposed_to_road', true],
        'warning_zero_privacy' => ['Zero privacy?', 'details.warning_zero_privacy', true],
        'warning_passing_vehicle_dust' => ['Passing vehicle dust?', 'details.warning_passing_vehicle_dust', true],
        'warning_possible_downed_trees' => ['Possible downed trees?', 'details.warning_possible_downed_trees', true],
        'warning_no_tent_camping' => ['No tent camping?', 'details.warning_no_tent_camping', true],
        'warning_limited_vehicle_length' => ['Limited vehicle length?', 'details.warning_limited_vehicle_length', true],
        'warning_leveling_may_be_required' => ['Leveling may be required?', 'details.warning_leveling_may_be_required', true],
        'warning_motorized_recreation_traffic' => ['Motorized recreation traffic?', 'details.warning_motorized_recreation_traffic', true],
        'warning_blind_turn_traffic_nearby' => ['Blind-turn traffic nearby?', 'details.warning_blind_turn_traffic_nearby', true],
    ] as $key => [$label, $storage, $warning]) {
        $add($key, $label, 'safety', 'tri', $storage, [
            'allow_unknown' => true,
            'warning' => $warning,
            'points_categories' => ['safety_warnings'],
        ]);
    }

    /* Seasons, rules, and nearby services */
    $add('best_months', 'Best months', 'rules', 'select', 'rules.best_months', [
        'allow_unknown' => true,
        'options' => [
            'year-round' => 'Year-round',
            'spring' => 'Spring',
            'summer' => 'Summer',
            'fall' => 'Fall',
            'winter' => 'Winter',
            'spring-summer' => 'Spring through summer',
            'summer-fall' => 'Summer through fall',
            'late-spring-fall' => 'Late spring through fall',
            'snow-free-months' => 'Generally snow-free months',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('winter_access', 'Winter access?', 'rules', 'tri', 'rules.winter_access', [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ]);
    foreach ([
        'snow_risk' => ['Snow risk', 'Low', 'High'],
        'mud_season_risk' => ['Mud-season risk', 'Low', 'High'],
        'monsoon_risk' => ['Monsoon risk', 'Low', 'High'],
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
    ]);
    foreach ([
        'overnight_camping_allowed' => 'Overnight camping allowed?',
        'dispersed_camping_allowed' => 'Dispersed camping allowed?',
        'permit_required' => 'Permit required?',
        'campfire_allowed' => 'Campfire allowed?',
        'existing_sites_encouraged' => 'Existing sites encouraged?',
        'pack_it_in_pack_it_out' => 'Pack it in / pack it out?',
        'residential_use_prohibited' => 'Residential use prohibited?',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'tri', 'rules.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['seasons_rules_services'],
        ]);
    }
    $add('stay_limit_days', 'Stay limit', 'rules', 'select', 'rules.stay_limit_days', [
        'allow_unknown' => true,
        'options' => [
            '1' => '1 day', '3' => '3 days', '5' => '5 days',
            '7' => '7 days', '10' => '10 days', '14' => '14 days',
            '16' => '16 days', '21' => '21 days', '28' => '28 days',
        ],
        'points_categories' => ['seasons_rules_services'],
    ]);
    $add('fee', 'Fee', 'rules', 'number', 'rules.fee', [
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
        'nearest_town' => 'Distance to nearest town',
        'nearest_fuel' => 'Distance to nearest fuel',
        'nearest_grocery' => 'Distance to nearest grocery',
        'nearest_water' => 'Distance to nearest potable water',
        'nearest_toilet' => 'Distance to nearest public toilet',
        'nearest_hospital' => 'Distance to nearest hospital / emergency care',
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
        'experience_mountain_view' => ['Mountain view', 'experience.mountain_view'],
        'experience_forest_view' => ['Forest view', 'experience.forest_view'],
        'experience_night_sky' => ['Night sky', 'experience.night_sky'],
        'experience_stargazing' => ['Stargazing', 'experience.stargazing'],
        'experience_quiet_evening' => ['Quiet evening', 'experience.quiet_evening'],
        'experience_overnight_comfort' => ['Overnight comfort', 'experience.overnight_comfort'],
        'experience_extended_stay_comfort' => ['Extended-stay comfort', 'experience.extended_stay_comfort'],
        'experience_sensory_retreat' => ['Sensory retreat', 'experience.sensory_retreat'],
        'experience_remote_work' => ['Remote work', 'experience.remote_work'],
        'experience_overall_scenery' => ['Overall scenery', 'experience.overall_scenery'],
        'recommended_overnight_stop' => ['Recommended overnight stop', 'experience.recommended_overnight_stop'],
        'recommended_quiet_evening' => ['Recommended quiet evening', 'experience.recommended_quiet_evening'],
        'recommended_extended_stay' => ['Recommended extended stay', 'experience.recommended_extended_stay'],
        'recommended_sensory_retreat' => ['Recommended sensory retreat', 'experience.recommended_sensory_retreat'],
        'recommended_stargazing' => ['Recommended stargazing', 'experience.recommended_stargazing'],
        'recommended_remote_work' => ['Recommended remote work', 'experience.recommended_remote_work'],
    ] as $key => [$label, $storage]) {
        $add($key, $label, 'experience', 'rating', $storage, [
            'allow_unknown' => true,
            'low' => 'Poor',
            'high' => 'Excellent',
            'points_categories' => ['experience_recommendations'],
        ]);
    }
    foreach ([
        'recommended_solo_travel' => 'Good for solo travel?',
        'recommended_families' => 'Good for families?',
        'recommended_large_groups' => 'Good for large groups?',
    ] as $key => $label) {
        $add($key, $label, 'experience', 'tri', 'experience.' . $key, [
            'allow_unknown' => true,
            'points_categories' => ['experience_recommendations'],
        ]);
    }
    $add('not_recommended_for', 'Not recommended for', 'experience', 'textarea', 'experience.not_recommended_for', [
        'wide' => true,
        'rows' => 3,
        'placeholder' => 'Example: low-clearance vehicles, people sensitive to road noise, large trailers...',
        'points_categories' => ['experience_recommendations'],
    ]);

    /* Summaries */
    $add('access_summary', 'Access summary', 'summaries', 'textarea', 'access_summary', [
        'wide' => true,
        'rows' => 4,
        'placeholder' => 'Summarize the road, vehicle requirements, turnaround, leveling, and mobility access.',
    ]);
    $add('sensory_summary', 'Sensory summary', 'summaries', 'textarea', 'sensory_summary', [
        'wide' => true,
        'rows' => 4,
        'placeholder' => 'Describe the overall sensory experience and any major day/night differences.',
    ]);
    $add('contributor_notes', 'Notes for the reviewer', 'summaries', 'textarea', 'contributor_notes', [
        'wide' => true,
        'rows' => 4,
        'placeholder' => 'Anything uncertain, unusual, temporary, or important for the moderator to know.',
        'hide_public' => true,
    ]);

    return $f;
}

function llama_place_report_get_path(array $data, string $path): mixed
{
    /*
     * Derived warnings live in the shared Place Report schema without
     * creating duplicate questions or database columns.
     *
     * Overall cell service uses the existing 1-5 rating where 1 means
     * None. When that answer is present, expose a computed true value so
     * the existing Quick Warnings renderer can treat it like every other
     * warning field.
     */
    if ($path === 'computed.warning_no_cell_service') {
        $overall =
            $data['connectivity']['overall']
            ?? null;

        if ($overall === null || $overall === '') {
            return null;
        }

        return
            is_numeric($overall)
            && (int) $overall === 1;
    }

    $value = $data;

    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }

        $value = $value[$part];
    }

    return $value;
}

function llama_place_report_set_path(array &$data, string $path, mixed $value): void
{
    $parts = explode('.', $path);
    $cursor =& $data;

    foreach ($parts as $index => $part) {
        if ($index === count($parts) - 1) {
            $cursor[$part] = $value;
            return;
        }

        if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
            $cursor[$part] = [];
        }

        $cursor =& $cursor[$part];
    }
}

function llama_place_report_unknown_fields(array $data): array
{
    $raw = $data['_answer_state'] ?? [];

    if (!is_array($raw)) {
        return [];
    }

    $unknown = [];

    foreach ($raw as $key => $value) {
        if (is_int($key)) {
            $field = trim((string) $value);

            if ($field !== '') {
                $unknown[$field] = true;
            }

            continue;
        }

        if (
            (string) $value === 'unknown'
            || $value === true
            || $value === 1
        ) {
            $unknown[(string) $key] = true;
        }
    }

    return array_keys($unknown);
}

function llama_place_report_answer_state(array $data, string $fieldKey): string
{
    if (
        in_array(
            $fieldKey,
            llama_place_report_unknown_fields($data),
            true
        )
    ) {
        return 'unknown';
    }

    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return 'unanswered';
    }

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    if ((string) $field['type'] === 'checkbox') {
        return $value === true || $value === 1 || $value === '1'
            ? 'answered'
            : 'unanswered';
    }

    if ($value === null || $value === '') {
        return 'unanswered';
    }

    return 'answered';
}

function llama_place_report_form_value_from_data(array $data, string $fieldKey): mixed
{
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return null;
    }

    if (llama_place_report_answer_state($data, $fieldKey) === 'unknown') {
        return llama_place_report_unknown_token();
    }

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    if ((string) $field['type'] === 'checkbox') {
        return $value ? '1' : '';
    }

    if (
        in_array(
            (string) $field['type'],
            ['tri', 'rating'],
            true
        )
        && $value === null
    ) {
        return llama_place_report_unanswered_token();
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return $value;
}

function llama_place_report_form_input_from_data(array $data): array
{
    $input = [];

    foreach (llama_place_report_fields() as $key => $field) {
        $value =
            llama_place_report_form_value_from_data(
                $data,
                $key
            );

        if ((string) $field['type'] === 'checkbox') {
            if ($value === '1') {
                $input[$key] = '1';
            }

            continue;
        }

        if ($value !== null) {
            $input[$key] = (string) $value;
        }
    }

    return $input;
}

function llama_place_report_parse_field(
    array $field,
    mixed $raw,
    array &$unknownFields
): mixed {
    $key = (string) $field['key'];
    $type = (string) $field['type'];

    if ($raw === llama_place_report_unknown_token()) {
        if (!empty($field['allow_unknown'])) {
            $unknownFields[$key] = true;
        }

        return null;
    }

    if ($raw === llama_place_report_unanswered_token()) {
        return null;
    }

    if ($type === 'checkbox') {
        return (string) $raw === '1';
    }

    if ($type === 'tri') {
        if ($raw === '' || $raw === null) {
            return null;
        }

        return (string) $raw === '1';
    }

    if ($type === 'rating') {
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            return null;
        }

        $value = (int) $raw;

        return $value >= 1 && $value <= 5
            ? $value
            : null;
    }

    if ($type === 'number') {
        $clean = trim((string) $raw);

        if ($clean === '') {
            return null;
        }

        if (!is_numeric($clean)) {
            throw new InvalidArgumentException(
                (string) $field['label']
                . ' must be numeric.'
            );
        }

        $number =
            str_contains($clean, '.')
                ? (float) $clean
                : (int) $clean;

        if (
            isset($field['min'])
            && $number < (float) $field['min']
        ) {
            return null;
        }

        if (
            isset($field['max'])
            && $number > (float) $field['max']
        ) {
            return null;
        }

        return $number;
    }

    $clean = trim((string) $raw);

    if ($clean === '') {
        return null;
    }

    if ($type === 'select') {
        $options =
            (array) ($field['options'] ?? []);

        /*
         * Place type is the one select that has historically been
         * normalized server-side. Preserve that protection.
         */
        if (
            $key === 'type'
            && !array_key_exists($clean, $options)
        ) {
            return 'other';
        }
    }

    $max = (int) ($field['maxlength'] ?? 5000);

    return mb_substr($clean, 0, $max);
}

function llama_place_report_build_data(
    array $input,
    ?array $baseData = null
): array {
    $data =
        is_array($baseData)
            ? $baseData
            : [
                'details' => [],
                'amenities' => [],
                'connectivity' => [],
                'sensory' => [
                    'daytime' => [],
                    'nighttime' => [],
                    'details' => [],
                ],
                'rules' => [],
                'experience' => [],
                'photos' => [],
            ];

    $unknownFields = [];

    foreach (llama_place_report_fields() as $key => $field) {
        $type = (string) $field['type'];

        if (!array_key_exists($key, $input)) {
            if ($baseData === null && $type === 'checkbox') {
                llama_place_report_set_path(
                    $data,
                    (string) $field['storage'],
                    false
                );
            }

            continue;
        }

        $value =
            llama_place_report_parse_field(
                $field,
                $input[$key],
                $unknownFields
            );

        llama_place_report_set_path(
            $data,
            (string) $field['storage'],
            $value
        );
    }

    $noAmenities =
        !empty(
            $data['details']['warning_no_amenities']
            ?? false
        );

    if ($noAmenities) {
        foreach ((array) ($data['amenities'] ?? []) as $amenity => $_) {
            $data['amenities'][$amenity] = false;
        }
    } else {
        foreach ((array) ($data['amenities'] ?? []) as $value) {
            if ($value) {
                $data['details']['warning_no_amenities'] = false;
                break;
            }
        }
    }

    if (isset($data['details']) && is_array($data['details'])) {
        $data['details']['road_difficulty'] =
            $data['details']['road_overall_difficulty']
            ?? null;
    }

    if (isset($data['rules']) && is_array($data['rules'])) {
        $data['rules']['recommended_travel_season'] =
            $data['rules']['best_months']
            ?? null;
    }

    /*
     * Preserve existing explicit Unknown answers when a moderator edits
     * only some fields. Then apply the current submitted state over them.
     */
    if ($baseData !== null) {
        foreach (llama_place_report_unknown_fields($baseData) as $existingUnknown) {
            if (!array_key_exists($existingUnknown, $input)) {
                $unknownFields[$existingUnknown] = true;
            }
        }
    }

    $data['_answer_state'] =
        array_values(
            array_keys($unknownFields)
        );

    $name = trim((string) ($data['name'] ?? ''));

    if ($name === '') {
        throw new InvalidArgumentException(
            'Place name is required.'
        );
    }

    $lat = $data['latitude'] ?? null;
    $lng = $data['longitude'] ?? null;

    if (($lat === null) !== ($lng === null)) {
        throw new InvalidArgumentException(
            'Enter both latitude and longitude, or leave both blank.'
        );
    }

    return $data;
}

function llama_place_report_scoring_input_from_data(array $data): array
{
    $input =
        llama_place_report_form_input_from_data(
            $data
        );

    foreach ($input as $key => $value) {
        if ($value === llama_place_report_unanswered_token()) {
            $input[$key] = '';
        }
    }

    return $input;
}

function llama_place_report_is_answered_input(
    array $input,
    string $fieldKey
): bool {
    if (!array_key_exists($fieldKey, $input)) {
        return false;
    }

    $value = $input[$fieldKey];

    if (is_array($value)) {
        return count($value) > 0;
    }

    if ($value === llama_place_report_unanswered_token()) {
        return false;
    }

    return trim((string) $value) !== '';
}

function llama_place_report_display_value(
    array $data,
    string $fieldKey
): ?string {
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return null;
    }

    $state =
        llama_place_report_answer_state(
            $data,
            $fieldKey
        );

    if ($state === 'unknown') {
        return 'Unknown';
    }

    if ($state === 'unanswered') {
        return null;
    }

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    $type = (string) $field['type'];

    if ($type === 'tri') {
        return $value ? 'Yes' : 'No';
    }

    if ($type === 'rating') {
        return (int) $value . '/5';
    }

    if ($type === 'checkbox') {
        return $value ? 'Yes' : null;
    }

    if ($type === 'select') {
        $options = (array) ($field['options'] ?? []);
        $key = (string) $value;

        if (array_key_exists($key, $options)) {
            return (string) $options[$key];
        }
    }

    if (($field['format'] ?? '') === 'currency') {
        return '$' . number_format((float) $value, 2);
    }

    return (string) $value;
}

function llama_place_report_normalize_committed_photos(array $photos): array
{
    foreach ($photos as &$photo) {
        if (!is_array($photo)) {
            continue;
        }

        $path =
            trim(
                (string) (
                    $photo['path']
                    ?? $photo['src']
                    ?? ''
                )
            );

        if ($path !== '') {
            $photo['path'] = $path;
            $photo['src'] = $path;
        }
    }
    unset($photo);

    return array_values(
        array_filter(
            $photos,
            'is_array'
        )
    );
}

function llama_place_report_photo_url(mixed $photo): string
{
    $path =
        llama_place_report_photo_path(
            $photo
        );

    if ($path === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $path
        )
    ) {
        return $path;
    }

    return
        'https://llamascout.com/'
        . ltrim(
            $path,
            '/'
        );
}

function llama_place_report_photo_path(mixed $photo): string
{
    if (!is_array($photo)) {
        return '';
    }

    return trim(
        (string) (
            $photo['src']
            ?? $photo['path']
            ?? $photo['url']
            ?? ''
        )
    );
}

function llama_place_report_submit_new_place(
    int $userId,
    array $input
): int {
    $data =
        llama_place_report_build_data(
            $input
        );

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
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            community_role_at_submission($userId),
            (string) $data['name'],
            'community-scouted',
            'pending',
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
                llama_place_report_normalize_committed_photos(
                    llama_photo_commit_stage(
                        'add-place',
                        $userId,
                        $photoToken,
                        $submittedPhotos,
                        '/uploads/place-submissions/'
                        . $submissionId
                    )
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
                dirname(__DIR__)
                . '/uploads/place-submissions/'
                . $submissionId
            );
        }

        throw $exception;
    }
}

function llama_place_report_resubmit_new_place(
    int $userId,
    int $submissionId,
    array $input
): int {
    $existing =
        community_new_place_submission_for_user(
            $userId,
            $submissionId
        );

    if (
        !$existing
        || (string) ($existing['status'] ?? '') !== 'needs-changes'
    ) {
        throw new RuntimeException(
            'This new Place submission is no longer available for resubmission.'
        );
    }

    $data =
        llama_place_report_build_data(
            $input
        );

    $existingData =
        is_array($existing['data'] ?? null)
            ? $existing['data']
            : [];

    $existingPhotos =
        is_array($existingData['photos'] ?? null)
            ? $existingData['photos']
            : [];

    $removePhotos =
        is_array($input['remove_existing_photos'] ?? null)
            ? array_values(
                array_filter(
                    array_map(
                        static fn (mixed $value): string =>
                            trim((string) $value),
                        $input['remove_existing_photos']
                    ),
                    static fn (string $value): bool =>
                        $value !== ''
                )
            )
            : [];

    $keptPhotos = [];

    foreach ($existingPhotos as $photo) {
        $path =
            llama_place_report_photo_path(
                $photo
            );

        if (
            $path !== ''
            && in_array(
                $path,
                $removePhotos,
                true
            )
        ) {
            continue;
        }

        if (is_array($photo)) {
            $keptPhotos[] = $photo;
        }
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

    $newPhotos = [];
    $db = db();

    try {
        $db->beginTransaction();

        if ($photoToken !== '') {
            $newPhotos =
                llama_place_report_normalize_committed_photos(
                    llama_photo_commit_stage(
                        'add-place',
                        $userId,
                        $photoToken,
                        $submittedPhotos,
                        '/uploads/place-submissions/'
                        . $submissionId
                    )
                );
        }

        $data['photos'] =
            array_values(
                array_merge(
                    $keptPhotos,
                    $newPhotos
                )
            );

        $stmt = $db->prepare(
            'UPDATE place_submissions
             SET
                place_name = ?,
                role_at_submission = ?,
                status = "pending",
                submission_data = ?,
                submitted_at = CURRENT_TIMESTAMP,
                reviewed_at = NULL,
                reviewed_by = NULL,
                review_notes = NULL
             WHERE id = ?
               AND user_id = ?
               AND status = "needs-changes"'
        );

        $stmt->execute([
            (string) $data['name'],
            community_role_at_submission($userId),
            json_encode(
                $data,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
            $submissionId,
            $userId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The Place submission changed before it could be resubmitted.'
            );
        }

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($newPhotos as $photo) {
            $path =
                llama_place_report_photo_path(
                    $photo
                );

            if ($path !== '') {
                $absolute =
                    dirname(__DIR__)
                    . $path;

                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }

        throw $exception;
    }

    foreach ($removePhotos as $path) {
        if (
            !str_starts_with(
                $path,
                '/uploads/place-submissions/'
                . $submissionId
                . '/'
            )
        ) {
            continue;
        }

        $absolute =
            dirname(__DIR__)
            . $path;

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    return $submissionId;
}


function llama_place_report_data_from_published_place(
    array $place,
    array $unknownFields = []
): array {
    $data = $place;

    if (!isset($data['details']) || !is_array($data['details'])) {
        $data['details'] = [];
    }

    if (!isset($data['amenities']) || !is_array($data['amenities'])) {
        $data['amenities'] = [];
    }

    if (!isset($data['connectivity']) || !is_array($data['connectivity'])) {
        $data['connectivity'] = [];
    }

    if (!isset($data['rules']) || !is_array($data['rules'])) {
        $data['rules'] = [];
    }

    if (!isset($data['experience']) || !is_array($data['experience'])) {
        $data['experience'] = [];
    }

    $sensory =
        is_array($data['sensory'] ?? null)
            ? $data['sensory']
            : [];

    $sensory['details'] =
        is_array($data['sensory_details'] ?? null)
            ? $data['sensory_details']
            : (
                is_array($sensory['details'] ?? null)
                    ? $sensory['details']
                    : []
            );

    $data['sensory'] = $sensory;
    $data['_answer_state'] = array_values($unknownFields);

    return $data;
}


function llama_place_report_publish_answer_state(
    PDO $db,
    int $placeId,
    array $submissionData
): void {
    $unknown =
        llama_place_report_unknown_fields(
            $submissionData
        );

    $stmt = $db->prepare(
        'UPDATE places
         SET place_report_answer_state = ?
         WHERE id = ?'
    );

    $stmt->execute([
        json_encode(
            $unknown,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $placeId,
    ]);
}

function llama_place_report_published_answer_state(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT place_report_answer_state
         FROM places
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$placeId]);

    $json = $stmt->fetchColumn();

    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded =
        json_decode(
            $json,
            true
        );

    if (!is_array($decoded)) {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                'strval',
                $decoded
            ),
            static fn (string $value): bool =>
                $value !== ''
        )
    );
}
