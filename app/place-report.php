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
            'icon' => 'info-circle',
            'open' => true,
        ],
        'location' => [
            'label' => 'Location',
            'description' => 'GPS, elevation, road, town, county, state, and land',
            'icon' => 'current-location',
            'open' => true,
        ],
        'site_vehicle' => [
            'label' => 'Site and vehicle fit',
            'description' => 'Size, parking, tents, RVs, trailers, and leveling',
            'icon' => 'camper',
        ],
        'road_access' => [
            'label' => 'Road access',
            'description' => 'Surface, width, difficulty, stress, mud, rocks, and obstacles',
            'icon' => 'road',
        ],
        'amenities' => [
            'label' => 'Amenities',
            'description' => 'Check only what is actually available',
            'icon' => 'picnic-table',
        ],
        'connectivity' => [
            'label' => 'Connectivity',
            'description' => 'Cell carriers and Starlink',
            'icon' => 'antenna-bars-5',
        ],
        'sensory' => [
            'label' => 'Sensory profile',
            'description' => 'Day, night, noise, traffic, people, smells, and exposure',
            'icon' => 'at-brain',
        ],
        'landscape_setting' => [
            'label' => 'Landscape and setting',
            'description' => 'Setting, nearby features, views, exposure, wildlife, and other conditions',
            'icon' => 'at-landscape',
        ],
        'accessibility' => [
            'label' => 'Accessibility',
            'description' => 'Mobility access, walking surface, facilities, and distance from the vehicle',
            'icon' => 'wheelchair',
        ],
        'safety' => [
            'label' => 'Safety and warnings',
            'description' => 'Hazards and conditions people should see quickly',
            'icon' => 'shield',
        ],
        'rules' => [
            'label' => 'Seasons, rules, and nearby services',
            'description' => 'Access seasons, camping rules, fees, fire, fuel, food, and medical care',
            'icon' => 'calendar-event',
        ],
        'experience' => [
            'label' => 'Experience and recommendations',
            'description' => 'Views, stars, comfort, quiet, remote work, and who it suits',
            'icon' => 'star',
        ],
        'scout_notes' => [
            'label' => 'Scout notes',
            'description' => 'Short useful observations worth spotting at a glance',
            'icon' => 'clipboard-list',
        ],
        'summaries' => [
            'label' => 'Reviewer notes',
            'description' => 'Anything uncertain, temporary, or useful for moderation',
            'icon' => 'file-text',
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
    $options = [
        'On site' => 'On site',
    ];

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
        'Tennessee Valley Authority' => 'Tennessee Valley Authority (TVA)',

        'Colorado Parks & Wildlife' => 'Colorado Parks & Wildlife',
        'Colorado State Land Board' => 'Colorado State Land Board',

        'South Florida Water Management District' => 'South Florida Water Management District',
        'Southwest Florida Water Management District' => 'Southwest Florida Water Management District',
        'St. Johns River Water Management District' => 'St. Johns River Water Management District',
        'Suwannee River Water Management District' => 'Suwannee River Water Management District',
        'Northwest Florida Water Management District' => 'Northwest Florida Water Management District',
        'Florida State Parks' => 'Florida State Parks',
        'Florida Fish and Wildlife Conservation Commission' => 'Florida Fish and Wildlife Conservation Commission',

        'Love\'s Travel Stops' => 'Love\'s Travel Stops',
        'Pilot / Flying J' => 'Pilot / Flying J',
        'TA / Petro' => 'TA / Petro',
        'Maverik' => 'Maverik',
        'Buc-ee\'s' => 'Buc-ee\'s',
        'Walmart' => 'Walmart',
        'Home Depot' => 'Home Depot',
        'Lowe\'s' => 'Lowe\'s',
        'Cracker Barrel' => 'Cracker Barrel',
        'Cabela\'s / Bass Pro Shops' => 'Cabela\'s / Bass Pro Shops',
        'Camping World' => 'Camping World',

        'Harvest Hosts' => 'Harvest Hosts',
        'Boondockers Welcome' => 'Boondockers Welcome',

        'State government' => 'State government / agency',
        'County / regional government' => 'County / regional government',
        'City / municipal government' => 'City / municipal government',
        'Special district / public authority' => 'Special district / public authority',
        'Public utility / power authority' => 'Public utility / power authority',
        'Tribal government' => 'Tribal government',
        'Land trust / conservation organization' => 'Land trust / conservation organization',
        'Private landowner / business' => 'Private landowner / business',
        'Other' => 'Other',
    ];
}

function llama_place_report_land_types(): array
{
    return [
        'Federal Public Land' => 'Federal Public Land',
        'National Forest' => 'National Forest',
        'National Grassland' => 'National Grassland',
        'BLM Land' => 'BLM Land',
        'National Conservation Area' => 'National Conservation Area',
        'National Park' => 'National Park',
        'National Preserve / Reserve' => 'National Preserve / Reserve',
        'National Monument' => 'National Monument',
        'National Recreation Area' => 'National Recreation Area',
        'National Seashore / Lakeshore' => 'National Seashore / Lakeshore',
        'National River / Scenic Riverway' => 'National River / Scenic Riverway',
        'National Wildlife Refuge' => 'National Wildlife Refuge',
        'Federal Water Project / Recreation Land' => 'Federal Water Project / Recreation Land',
        'Military Land' => 'Military Land',

        'State Public Land' => 'State Public Land',
        'State Forest' => 'State Forest',
        'State Park' => 'State Park',
        'State Recreation Area' => 'State Recreation Area',
        'State Natural Area / Preserve' => 'State Natural Area / Preserve',
        'State Trust Land' => 'State Trust Land',
        'Wildlife Management / Game Lands' => 'Wildlife Management / Game Lands',
        'Water Management District' => 'Water Management District',

        'Local Public Land' => 'Local Public Land',
        'County / Regional Park' => 'County / Regional Park',
        'City / Municipal Land' => 'City / Municipal Land',
        'Public Utility / Reservoir Land' => 'Public Utility / Reservoir Land',
        'Public Parking / Civic Property' => 'Public Parking / Civic Property',
        'Rest Area / Transportation Facility' => 'Rest Area / Transportation Facility',
        'Roadside / Highway Right-of-Way' => 'Roadside / Highway Right-of-Way',
        'Fairgrounds / Event Property' => 'Fairgrounds / Event Property',

        'Tribal Land' => 'Tribal Land',
        'Land Trust / Conservation Preserve' => 'Land Trust / Conservation Preserve',

        'Travel Center / Truck Stop Property' => 'Travel Center / Truck Stop Property',
        'Retail / Commercial Property' => 'Retail / Commercial Property',
        'Restaurant Property' => 'Restaurant Property',
        'Medical / Healthcare Property' => 'Medical / Healthcare Property',
        'Religious / Community Property' => 'Religious / Community Property',
        'Casino / Gaming Property' => 'Casino / Gaming Property',
        'Membership / Hosted Property' => 'Membership / Hosted Property',
        'Private Land' => 'Private Land',

        'Other' => 'Other',
    ];
}

function llama_place_report_place_types(): array
{
    $types =
        function_exists('community_place_types')
            ? community_place_types()
            : [];

    $expanded = [
        'dispersed-camping' => 'Dispersed camping',
        'developed-campground' => 'Developed campground',
        'camping-area' => 'Camping area',
        'rv-park-resort' => 'RV park / resort',

        'rest-area' => 'Rest area',
        'scenic-overlook' => 'Scenic overlook / viewpoint',
        'vehicle-pulloff' => 'Roadside / vehicle pull-off',
        'trailhead' => 'Trailhead',
        'day-use' => 'Day-use area',
        'public-parking' => 'Public / civic parking',

        'travel-center' => 'Travel center',
        'truck-stop' => 'Truck stop',
        'retail-parking' => 'Retail parking',
        'restaurant-parking' => 'Restaurant parking',
        'casino-parking' => 'Casino parking',
        'medical-office-parking' => 'Medical office / clinic parking',
        'hospital-parking' => 'Hospital / healthcare parking',
        'church-parking' => 'Church / religious property parking',
        'fairgrounds' => 'Fairgrounds / event grounds',
        'street-parking' => 'Street parking',
        'other-parking' => 'Other parking',

        'membership-host' => 'Membership / hosted stay',
        'private-property' => 'Private property',

        'other' => 'Other',
    ];

    return array_replace(
        $types,
        $expanded
    );
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
        'name' => 'signature',
        'type' => 'tag',
        'visited_at' => 'calendar-check',
        'description' => 'file-text',

        'latitude' => 'current-location',
        'longitude' => 'current-location',
        'elevation_feet' => 'mountain',
        'road' => 'road',
        'city' => 'buildings',
        'county' => 'map',
        'state' => 'map',
        'region' => 'at-directions-post',
        'land_manager' => 'building-community',
        'land_type' => 'trees',

        'vehicle_capacity' => 'camper',
        'max_vehicle_length_feet' => 'ruler-measure',
        'max_trailer_length_feet' => 'ruler-measure',
        'max_rv_length_feet' => 'ruler-measure',
        'campsite_count' => 'numbers',
        'site_number' => 'hash',
        'site_hookups_available' => 'plug-connected',
        'hookup_electric' => 'bolt',
        'hookup_electric_service' => 'at-electricity-socket',
        'hookup_water' => 'at-water-tap',
        'hookup_sewer' => 'at-toilet-paper',
        'parking_surface' => 'parking',
        'ground_condition' => 'ground-condition',
        'tent_camping_suitable' => 'tent',
        'rv_suitable' => 'at-camper-vehicle',
        'trailer_suitable' => 'caravan',
        'leveling_required' => 'at-level-tool',
        'turnaround_space' => 'refresh',
        'pull_through' => 'arrow-right',
        'back_in' => 'arrow-left',
        'levelness' => 'at-level-tool',
        'site_open_sky' => 'sun',
        'tree_cover' => 'trees',
        'site_shade' => 'tree',

        'road_surface' => 'road',
        'road_width' => 'ruler-measure',
        'sedan_accessible' => 'car',
        'high_clearance_recommended' => 'car-suv',
        'four_wheel_drive_recommended' => 'car-4wd',
        'water_crossings' => 'ripple',
        'downed_tree_risk' => 'downed-tree',
        'seasonal_closure' => 'calendar-off',
        'site_access_difficulty' => 'route',
        'road_overall_difficulty' => 'road',
        'road_stress' => 'mood-nervous',
        'rocks' => 'blob',
        'washboards' => 'steering-wheel',
        'potholes' => 'wheel',
        'mud_risk' => 'at-shovel',
        'steep_grades' => 'mountain',
        'drop_off_exposure' => 'cliff-jumping',

        'amenity_none' => 'xbox-x',
        'amenity_toilets' => 'at-toilet',
        'amenity_potable_water' => 'at-water-tap',
        'amenity_trash' => 'trash',
        'amenity_fire_ring' => 'campfire',
        'amenity_picnic_table' => 'picnic-table',
        'amenity_bear_box' => 'bear',
        'amenity_showers' => 'at-shower-facilities',
        'amenity_electricity' => 'at-electricity-socket',
        'amenity_dump_station' => 'caravan',
        'amenity_wifi' => 'at-wifi',
        'amenity_laundry' => 'wash-machine',

        'connectivity_overall' => 'antenna-bars-5',
        'connectivity_t_mobile' => 'at-signal',
        'connectivity_verizon' => 'at-signal',
        'connectivity_att' => 'at-signal',
        'connectivity_other_cell' => 'at-signal',
        'connectivity_starlink' => 'at-satellite-signal',
        'connectivity_starlink_tested' => 'satellite',
        'connectivity_starlink_note' => 'at-satellite-signal',
        'warning_no_cell_service' => 'antenna-bars-off',

        'landscape_primary' => 'at-landscape',
        'landscape_details' => 'at-landscape',
        'landscape_views' => 'eye',
        'critter_activity' => 'deer',
        'environment_water_nearby' => 'water-waves',
        'environment_wildlife' => 'deer',
        'environment_bugs' => 'bug',
        'wheelchair_friendly' => 'wheelchair',
        'mobility_device_friendly' => 'old',
        'flat_walking_surface' => 'footsteps',
        'step_free_access' => 'stairs',
        'accessible_toilet' => 'at-toilet',
        'accessible_picnic_table' => 'picnic-table',
        'walking_distance_from_vehicle' => 'walk',

        'felt_safe_daytime' => 'sun',
        'felt_safe_nighttime' => 'moon-stars',
        'flash_flood_risk' => 'flood',
        'wildfire_risk' => 'wildfire',
        'fall_hazard' => 'trip-fall-hazard',
        'cliff_exposure' => 'cliff-jumping',
        'rockfall_risk' => 'falling-rocks',
        'wildlife_risk' => 'bear-attack',
        'traffic_hazard' => 'at-street-cone',
        'emergency_access' => 'medical-cross',
        'road_exposure' => 'road',
        'warning_possible_downed_trees' => 'downed-tree',
        'warning_passing_vehicle_dust' => 'at-misty-cloud',
        'warning_motorized_recreation_traffic' => 'motorbike',
        'warning_blind_turn_traffic_nearby' => 'alert-triangle',

        'warning_no_tent_camping_derived' => 'tent-off',
        'warning_leveling_required_derived' => 'at-level-tool',
        'warning_no_privacy' => 'eye',
        'warning_high_road_exposure' => 'road',
        'warning_limited_vehicle_length_derived' => 'ruler-measure',
        'warning_limited_trailer_length' => 'ruler-measure',

        'best_months' => 'calendar-check',
        'winter_access' => 'snowflake',
        'snow_risk' => 'at-snowing',
        'mud_season_risk' => 'at-shovel',
        'monsoon_risk' => 'at-heavy-rain',
        'heat_season_risk' => 'temperature-sun',
        'hurricane_risk' => 'hurricane',
        'seasonal_access_note' => 'file-text',
        'overnight_camping_allowed' => 'moon-stars',
        'dispersed_camping_allowed' => 'tent',
        'stay_limit_days' => 'calendar',
        'collecting_firewood' => 'wood',
        'reservation_required' => 'calendar-check',
        'membership_required' => 'id-badge-2',
        'membership_url' => 'link',
        'membership_fee' => 'currency-dollar',
        'check_in_required' => 'door-enter',
        'check_out_required' => 'door-exit',
        'reservation_url' => 'link',
        'reservation_fee' => 'currency-dollar',
        'check_in_begins' => 'clock-hour-3',
        'checkout_ends' => 'clock-hour-11',
        'fee' => 'receipt',
        'entrance_facility_fee' => 'ticket',
        'parking_fee' => 'parking',
        'campfire_allowed' => 'campfire',
        'drone_use_legal' => 'at-drone-tech',
        'target_shooting_allowed' => 'bullseye',
        'designated_sites_only' => 'sign-right',
        'pets_allowed' => 'dog',
        'dogs_required_to_be_leashed' => 'dog-leash',
        'food_storage_required' => 'bear-paw',
        'generator_restrictions' => 'generator',
        'generator_quiet_hours' => 'moon-stars',
        'generator_quiet_hours_begin' => 'clock-hour-9',
        'generator_quiet_hours_end' => 'sunrise',
        'pack_it_in_pack_it_out' => 'trash',
        'existing_sites_encouraged' => 'at-directions-post',
        'residential_use_prohibited' => 'home-off',
        'current_fire_restrictions_url' => 'flame',
        'nearest_town' => 'buildings',
        'nearest_fuel' => 'at-gasoline',
        'nearest_ev_charging' => 'charging-pile',
        'nearest_alcohol_sales' => 'alcohol',
        'nearest_propane' => 'propane',
        'nearest_grocery' => 'basket',
        'nearest_water' => 'at-water-tap',
        'nearest_toilet' => 'at-toilet',
        'nearest_hospital' => 'hospital',

        'recommended_overnight_stop' => 'moon-stars',
        'recommended_quiet_evening' => 'ear-off',
        'recommended_extended_stay' => 'calendar-week',
        'recommended_sensory_retreat' => 'at-brain',
        'recommended_stargazing' => 'shooting-star-line',
        'recommended_remote_work' => 'at-laptop',
        'recommended_solo_travel' => 'user',
        'recommended_families' => 'at-users',
        'recommended_large_groups' => 'users-group',
        'not_recommended_for' => 'at-warning',
        'scout_note_1' => 'file-text',
        'scout_note_2' => 'file-text',
        'scout_note_3' => 'file-text',

        'access_summary' => 'road',
        'sensory_summary' => 'at-brain',
        'contributor_notes' => 'file-text',
    ];

    if (isset($icons[$key])) {
        return $icons[$key];
    }

    return match ((string) ($field['section'] ?? '')) {
        'connectivity' => 'antenna-bars-5',
        'sensory' => 'at-ear',
        'landscape_setting' => 'at-landscape',
        'accessibility' => 'wheelchair',
        'safety' => 'shield',
        'rules' => 'at-directions-post',
        'experience' => 'star',
        'scout_notes' => 'file-text',
        default => 'info-circle',
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
                'counts_toward_completion' => true,
                'completion_group' => null,
                'min_characters' => 0,
                'applicable_if' => [],
                'derived' => false,
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
        'options' => llama_place_report_place_types(),
    ]);
    $add('visited_at', 'Date visited', 'basic', 'date', 'visited_at');
    $add('description', 'Description', 'basic', 'textarea', 'description', [
        'wide' => true,
        'rows' => 5,
        'min_characters' => 1500,
        'placeholder' => 'What is this place, what is it like, and why would someone use it?',
    ]);

    /* Location */
    $add('latitude', 'Latitude', 'location', 'number', 'latitude', [
        'step' => 'any',
        'min' => '-90',
        'max' => '90',
        'location_field' => true,
        'placeholder' => '37.272376',
    ]);
    $add('longitude', 'Longitude', 'location', 'number', 'longitude', [
        'step' => 'any',
        'min' => '-180',
        'max' => '180',
        'location_field' => true,
        'placeholder' => '-107.882456',
    ]);
    $add('elevation_feet', 'Elevation (ft)', 'location', 'number', 'elevation_feet', [
        'step' => '1',
        'min' => '-1500',
        'max' => '30000',
        'location_field' => true,
    ]);
    $add('road', 'Road', 'location', 'text', 'road', [
        'location_field' => true,
        'placeholder' => 'Example Creek Rd. / FR 813',
    ]);
    $add('city', 'Nearest city / town', 'location', 'text', 'city', [
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
        'allow_unknown' => true,
        'location_field' => true,
    ]);
        $add('land_type', 'Land type', 'location', 'select', 'land_type', [
        'options' => llama_place_report_land_types(),
        'allow_unknown' => true,
        'location_field' => true,
    ]);
    $add('land_manager', 'Manager / operator / owner', 'location', 'select', 'land_manager', [
        'options' => llama_place_report_land_managers(),
        'allow_unknown' => true,
        'location_field' => true,
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
            'open-meadow' => 'Open meadow',
            'riparian' => 'Riparian',
            'dunes' => 'Dunes',
            'cliffs-dropoffs' => 'Cliffs / drop-offs',
            'agricultural-nearby' => 'Agricultural nearby',
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

    /* Summaries */
    /*
     * Summaries stay canonically grouped under `summaries` so moderation,
     * history, and any scoring/grouping logic continue treating them as one
     * summary group. `display_section` only controls where the questions are
     * rendered in the form and Scout Report.
     */
    $add('access_summary', 'Access summary', 'summaries', 'textarea', 'access_summary', [
        'wide' => true,
        'rows' => 4,
        'min_characters' => 1500,
        'display_section' => 'road_access',
        'placeholder' => 'Summarize the road, vehicle requirements, turnaround, leveling, and mobility access.',
    ]);
    $add('sensory_summary', 'Sensory summary', 'summaries', 'textarea', 'sensory_summary', [
        'wide' => true,
        'rows' => 4,
        'min_characters' => 1500,
        'display_section' => 'sensory',
        'placeholder' => 'Describe the overall sensory experience and any major day/night differences.',
    ]);
    $add('contributor_notes', 'Notes for the reviewer', 'summaries', 'textarea', 'contributor_notes', [
        'wide' => true,
        'rows' => 4,
        'placeholder' => 'Anything uncertain, unusual, temporary, or important for the moderator to know.',
        'hide_public' => true,
        'counts_toward_completion' => false,
    ]);

    /*
     * Optional contributor helper text.
     *
     * These explanations are deliberately kept on the canonical field
     * definitions so Add Place can show an info popup without duplicating
     * question-specific guidance in the template. Other consumers may choose
     * whether or not to display it.
     */
    $fieldHelp = [
        'type' =>
            'Choose the kind of place someone would recognize when planning a stay. For example: dispersed camping for an undeveloped public-land site, retail parking for an overnight store lot, or travel center for a Loves-style stop.',
        'region' =>
            'Use the local administrative area when one exists. Examples include Pagosa Ranger District, Moab Field Office, a national park district, or a named city neighborhood. For an urban place with no useful region, leave this blank.',
        'land_manager' =>
            'Choose the specific agency, business, membership program, organization, or owner that manages or operates this Place. The available choices narrow automatically from the state, Place type, and property type. Choose Other when the correct organization is not listed.',
        'land_type' =>
            'Describe the property or land system the Place is on. Examples include National Forest, State Park, Water Management District, retail property, travel center property, medical property, hosted membership property, roadside right-of-way, or private land.',
        'vehicle_capacity' =>
            'Estimate how many normal vehicles can fit without blocking the road, entrance, turnaround, or neighboring sites. Do not count sketchy edge parking just because a vehicle could technically squeeze there.',
        'campsite_count' =>
            'Enter the total number of campsites or camping spaces at the campground or developed camping property. Choose ? when you could not determine the count.',
        'site_number' =>
            'If this Scout report describes a specific numbered or named campsite, enter its site identifier here. Leave this blank for a campground-level report or an unnumbered site.',
        'site_hookups_available' =>
            'Choose Yes when the campsite being reported has one or more utility hookups directly at the site. Property-wide electricity, potable water, or a dump station in Amenities does not count as a campsite hookup.',
        'hookup_electric' =>
            'Choose Yes when electrical service is available directly at the campsite.',
        'hookup_electric_service' =>
            'Choose the electrical service available at this campsite. Select the combined option when the pedestal offers more than one receptacle size.',
        'hookup_water' =>
            'Choose Yes when potable-water service connects directly at the campsite.',
        'hookup_sewer' =>
            'Choose Yes when the campsite has its own sewer connection. A shared campground dump station belongs in Amenities instead.',
        'max_vehicle_length_feet' =>
            'Estimate the longest single vehicle that could reasonably enter, park, and maneuver here. Think about the entire approach and parking area, not just whether a long vehicle physically fits in one spot.',
        'max_rv_length_feet' =>
            'Enter the longest self-propelled RV or motorhome that can safely use this location. This can differ from the maximum trailer length and general vehicle length.',
        'max_trailer_length_feet' =>
            'Estimate the longest trailer that could reasonably reach the Place and maneuver into position. Consider tight turns, backing room, turnaround space, and the approach road.',
        'ground_condition' =>
            'Describe the ground where someone would actually park or camp, not the access road. Examples include firm and level, rocky, sandy, grassy, soft, mud-prone, or mixed.',
        'leveling_required' =>
            'Choose Yes when most campers would probably need leveling blocks, ramps, or careful positioning to get reasonably level. This is different from simply noticing that the ground is not perfectly flat.',
        'site_open_sky' =>
            'Rate how open the sky is directly over and around the site. Heavy tree canopy, canyon walls, buildings, or other obstructions reduce open sky and may affect solar or satellite use.',
        'site_access_difficulty' =>
            'Rate the final entry into the actual parking or camping spot. A road can be easy while the last turn, slope, rut, or entrance into the site itself is difficult.',
        'road_overall_difficulty' =>
            'Rate the access road as a whole. Consider surface, rocks, ruts, grades, width, water crossings, and other obstacles from the normal road network to the Place.',
        'road_stress' =>
            'This is about how stressful the drive feels, not just technical difficulty. Narrow roads, exposure, blind corners, drop-offs, traffic, or nowhere to pass can make an otherwise easy road stressful.',
        'sensory_smoke_risk' =>
            'Rate the chance that smoke from nearby campsites or campfires will drift into this site. Closer neighboring campsites, frequent campfires, and normal wind direction can increase the risk; more separation generally lowers it.',
        'sensory_strong_odors' =>
            'Rate the likelihood or intensity of strong unpleasant odors at the site. Examples include animal waste, dumpsters, sewage, factories or industrial activity, livestock, or other persistent smells.',
        'daytime_sensory_comfort' =>
            'Give an overall daytime sensory rating using what you observed: noise, traffic, people, light, smells, movement, and unpredictability. This is broader than any one sensory question.',
        'nighttime_sensory_comfort' =>
            'Give an overall nighttime sensory rating using what you observed: noise, traffic, people, lighting, smells, movement, and unpredictability. Day and night can be very different.',
        'landscape_primary' =>
            'Choose the one setting that best describes the Place itself. Use Mixed / transitional when two major settings genuinely overlap.',
        'critter_activity' =>
            'Rate rodents and other small animals that are likely to get into vehicles, food, trash, or gear. This is separate from dangerous wildlife risk.',
        'hurricane_risk' =>
            'Rate how much hurricanes or hurricane-force tropical systems affect the normal seasonal usability or access risk at this Place.',
        'heat_season_risk' =>
            'Rate how much extreme seasonal heat affects normal use or safety here. Consider dangerous daytime temperatures, lack of shade, vehicle or equipment stress, and seasonal closures caused by heat.',
        'seasonal_closure' =>
            'Choose Yes when this Place normally closes during any recurring part of the year, whether because of winter snow, spring mud, summer heat, fire season, flooding, wildlife management, or another seasonal rule.',
        'sensory_visual_exposure' =>
            'How visually exposed does the site feel to roads, neighboring campers, pedestrians, homes, or businesses? A high rating means people can easily see into or through the site.',
        'sensory_predictability' =>
            'Rate how consistent the environment is. Low means sudden or irregular traffic, people, noises, lights, or other surprises are common. High means the environment is steady and easy to anticipate.',
        'mobility_device_friendly' =>
            'Think about mobility aids such as a cane, walker, rollator, crutches, or mobility scooter. Wheelchair access has its own question. Consider rocks, roots, mud, slopes, and other obstacles around the site.',
        'wildfire_risk' =>
            'Consider the wildfire danger around the Place and, especially, whether dense trees, brush, or fire between the site and the exit could make leaving difficult or unsafe. This is about the setting and escape risk, not a current fire forecast.',
        'wildlife_risk' =>
            'Choose Yes when potentially dangerous wildlife is a realistic concern here. Examples include bears, mountain lions, bison, venomous snakes, or other animals that could seriously injure someone.',
        'traffic_hazard' =>
            'Choose Yes when the site is close enough to a road that passing vehicles could create a safety hazard, such as vehicles passing immediately beside the campsite, parking area, or people outside the vehicle.',
        'emergency_access' =>
            'Can an ambulance, fire engine, or other normal emergency vehicle reasonably reach this location? A Place may be drivable in your own vehicle but still be unsuitable for an ambulance or rescue vehicle. If evacuation would realistically require an airlift, answer No.',
        'warning_motorized_recreation_traffic' =>
            'Choose Yes when OHVs, side-by-sides, ATVs, dirt bikes, or similar recreational vehicles regularly travel through or immediately around the Place.',
        'warning_blind_turn_traffic_nearby' =>
            'Choose Yes when a nearby curve or blind turn limits visibility of the campsite, entrance, or parked vehicles, making it harder for approaching traffic to see the Place in time.',
        'road_exposure' =>
            'How exposed is the campsite itself to the nearby road and passing traffic? Consider how close vehicles pass, how visible the site is from the road, and whether traffic feels intrusive.',
        'designated_sites_only' =>
            'Choose Yes when camping is legally limited to marked, numbered, or otherwise designated sites. This is stricter than merely encouraging people to reuse existing disturbed campsites.',
        'food_storage_required' =>
            'Choose Yes when food, trash, coolers, toiletries, or other scented items must be stored in a specific way, such as a bear box, approved bear-resistant container, or hard-sided vehicle.',
        'generator_restrictions' =>
            'Choose Yes when generator use has any special restriction, such as quiet hours, limited operating hours, generator-free loops, seasonal limits, or a complete prohibition.',
        'generator_quiet_hours' =>
            'Choose Yes when generator use is specifically restricted during a recurring quiet-hours window. Choose No when the generator restriction is something else, such as a generator-free loop or seasonal prohibition.',
        'generator_quiet_hours_begin' =>
            'Choose when generator quiet hours normally begin. Use Sunset when the rule begins at sunset rather than a fixed clock time.',
        'generator_quiet_hours_end' =>
            'Choose when generator quiet hours normally end. Use Sunrise when the rule ends at sunrise rather than a fixed clock time.',
        'existing_sites_encouraged' =>
            'Choose Yes when the land manager asks campers to use already-disturbed or established sites when possible, but does not strictly require camping in marked designated sites.',
        'residential_use_prohibited' =>
            'This refers to rules against using the Place as a residence or long-term living location. Temporary overnight camping may still be allowed even when residential use is prohibited.',
        'monsoon_risk' =>
            'During monsoon season or other intense summer storms, consider whether heavy rain could flood the site or access road, create washes, turn the road to mud, or leave a vehicle stuck or unable to get out.',
        'stay_limit_days' =>
            'Choose the normal stay limit that applies here. Use Permit Limit when the permit itself controls how long someone may remain, or Varies by season when the limit changes during the year.',
        'reservation_required' =>
            'Choose Yes when a reservation must be made before staying overnight. Choose No when overnight use is first-come, first-served or otherwise does not require a reservation.',
        'membership_required' =>
            'Choose Yes when overnight use requires membership in a program, club, campground network, hosted-stay service, or similar organization.',
        'membership_url' =>
            'Use the official membership or enrollment page when membership is required.',
        'membership_fee' =>
            'Enter the required membership cost that applies to using this Place. Enter 0.00 when membership is required but free.',
        'check_in_required' =>
            'Choose Yes when someone must formally check in with a host, office, kiosk, desk, app, or other process before using the overnight space.',
        'reservation_url' =>
            'Use the official reservation or booking page for this Place when a reservation is required.',
        'reservation_fee' =>
            'Enter any separate reservation or booking fee. Enter 0.00 when there is no reservation fee. Do not include the camping, entrance, facility, or parking fee here.',
        'check_in_begins' =>
            'Choose the earliest normal check-in time. Use Anytime when check-in is required but arrival is allowed at any hour.',
        'check_out_required' =>
            'Choose Yes when the Place has a formal checkout requirement or departure deadline.',
        'checkout_ends' =>
            'Choose the latest normal checkout time. Use Anytime when checkout is required but departure is unrestricted by clock time.',
        'fee' =>
            'Enter the camping or overnight-stay fee. Enter 0.00 when overnight camping is free. Do not include entrance, facility, or parking fees here.',
        'entrance_facility_fee' =>
            'Enter any separate entrance, day-use, access, or facility fee. Enter 0.00 when there is no such fee.',
        'parking_fee' =>
            'Enter any separate parking fee or pass cost. Enter 0.00 when parking does not require a paid fee or pass.',
    ];

    foreach ($fieldHelp as $fieldKey => $helpText) {
        if (isset($f[$fieldKey])) {
            $f[$fieldKey]['help'] = $helpText;
        }
    }

    /*
     * =========================================================
     * COMPLETION APPLICABILITY RULES
     * =========================================================
     *
     * These rules affect only whether a question belongs in the
     * current completion denominator. They do not delete stored
     * answers and they do not control Scout contribution points.
     */

    $setApplicable =
        static function (
            array &$fields,
            array $fieldKeys,
            array $rules
        ): void {
            foreach ($fieldKeys as $fieldKey) {
                if (!isset($fields[$fieldKey])) {
                    continue;
                }

                $fields[$fieldKey]['applicable_if'] =
                    $rules;
            }
        };

    $appendApplicable =
        static function (
            array &$fields,
            array $fieldKeys,
            array $rules
        ): void {
            foreach ($fieldKeys as $fieldKey) {
                if (!isset($fields[$fieldKey])) {
                    continue;
                }

                $existing =
                    array_values(
                        (array) (
                            $fields[$fieldKey]['applicable_if']
                            ?? []
                        )
                    );

                $fields[$fieldKey]['applicable_if'] =
                    array_values(
                        array_merge(
                            $existing,
                            $rules
                        )
                    );
            }
        };


    $campingPlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $dispersedRelevantPlaceTypes = [
        'dispersed-camping',
        'camping-area',
        'trailhead',
        'day-use',
        'scenic-overlook',
        'vehicle-pulloff',
    ];

    $campfireRelevantPlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'trailhead',
        'day-use',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $naturalUsePlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'trailhead',
        'day-use',
        'scenic-overlook',
        'vehicle-pulloff',
    ];

    /*
     * Rough-road questions are useful for outdoor/camping access but are
     * noise on ordinary commercial and civic parking Places such as a
     * Love's Travel Stop, Walmart, hospital lot, or street parking.
     */
    $roughAccessPlaceTypes =
        array_values(
            array_unique(
                array_merge(
                    $campingPlaceTypes,
                    $naturalUsePlaceTypes
                )
            )
        );

    $setApplicable(
        $f,
        [
            'ground_condition',
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
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $roughAccessPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['tent_camping_suitable'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['max_trailer_length_feet'],
        [[
            'field' => 'trailer_suitable',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['max_rv_length_feet'],
        [[
            'field' => 'rv_suitable',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['accessible_toilet'],
        [[
            'field' => 'amenity_toilets',
            'operator' => 'truthy',
        ]]
    );

    $setApplicable(
        $f,
        ['accessible_picnic_table'],
        [[
            'field' => 'amenity_picnic_table',
            'operator' => 'truthy',
        ]]
    );

    $setApplicable(
        $f,
        ['dogs_required_to_be_leashed'],
        [[
            'field' => 'pets_allowed',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['dispersed_camping_allowed'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $dispersedRelevantPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'collecting_firewood',
            'target_shooting_allowed',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $naturalUsePlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['campfire_allowed'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campfireRelevantPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['current_fire_restrictions_url'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campfireRelevantPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'amenity_fire_ring',
            'amenity_bear_box',
            'pack_it_in_pack_it_out',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => array_values(
                array_unique(
                    array_merge(
                        $campingPlaceTypes,
                        $naturalUsePlaceTypes
                    )
                )
            ),
        ]]
    );

    $setApplicable(
        $f,
        [
            'designated_sites_only',
            'food_storage_required',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['existing_sites_encouraged'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => [
                'dispersed-camping',
                'camping-area',
            ],
        ]]
    );

    $setApplicable(
        $f,
        ['check_in_begins'],
        [[
            'field' => 'check_in_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['checkout_ends'],
        [[
            'field' => 'check_out_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        [
            'reservation_url',
            'reservation_fee',
        ],
        [[
            'field' => 'reservation_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        [
            'membership_url',
            'membership_fee',
        ],
        [[
            'field' => 'membership_required',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $intrinsicallyOvernightPlaceTypes = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
    ];

    $setApplicable(
        $f,
        [
            'stay_limit_days',
            'generator_restrictions',
            'residential_use_prohibited',
            'nighttime_noise',
            'nighttime_traffic',
            'nighttime_crowds',
            'nighttime_privacy',
            'nighttime_light_pollution',
            'nighttime_sensory_comfort',
            'nighttime_social_interaction',
            'experience_night_sky',
            'experience_stargazing',
            'experience_quiet_evening',
            'experience_extended_stay_comfort',
        ],
        [[
            'operator' => 'any',
            'rules' => [
                [
                    'field' => 'type',
                    'operator' => 'in',
                    'value' => $intrinsicallyOvernightPlaceTypes,
                ],
                [
                    'field' => 'overnight_camping_allowed',
                    'operator' => 'in',
                    'value' => ['1', '2'],
                ],
            ],
        ]]
    );


    $setApplicable(
        $f,
        ['generator_quiet_hours'],
        [[
            'field' => 'generator_restrictions',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        [
            'generator_quiet_hours_begin',
            'generator_quiet_hours_end',
        ],
        [[
            'field' => 'generator_quiet_hours',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['connectivity_starlink_note'],
        [[
            'field' => 'connectivity_starlink_tested',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    $setApplicable(
        $f,
        ['warning_possible_downed_trees'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $campingPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        ['seasonal_access_note'],
        [[
            'field' => 'seasonal_closure',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );


    /*
     * =========================================================
     * DEVELOPED CAMPGROUND PROFILE
     * =========================================================
     *
     * Formal campsite/property questions belong on campground-style
     * Places, not dispersed camping or ordinary parking Places.
     */
    $developedCampgroundPlaceTypes = [
        'developed-campground',
        'camping-area',
        'rv-park-resort',
    ];

    $setApplicable(
        $f,
        [
            'campsite_count',
            'site_number',
            'site_hookups_available',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $developedCampgroundPlaceTypes,
        ]]
    );

    $setApplicable(
        $f,
        [
            'hookup_electric',
            'hookup_water',
            'hookup_sewer',
        ],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $developedCampgroundPlaceTypes,
            ],
            [
                'field' => 'site_hookups_available',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    $setApplicable(
        $f,
        ['hookup_electric_service'],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $developedCampgroundPlaceTypes,
            ],
            [
                'field' => 'site_hookups_available',
                'operator' => 'equals',
                'value' => '1',
            ],
            [
                'field' => 'hookup_electric',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    /*
     * These either restate "developed campground" or describe a
     * dispersed/public-land use pattern rather than a formal campground.
     */
    $appendApplicable(
        $f,
        [
            'overnight_camping_allowed',
            'designated_sites_only',
            'target_shooting_allowed',
            'residential_use_prohibited',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'developed-campground',
        ]]
    );

    /*
     * =========================================================
     * DISPERSED CAMPING PROFILE
     * =========================================================
     *
     * Be conservative here. Managed dispersed camping can still
     * require reservations, permits, check-in, camping/day-use fees,
     * designated sites, food storage, generator limits, and even
     * centralized amenities. Hide only questions that are truly
     * redundant with the Place type itself.
     */
    $appendApplicable(
        $f,
        ['dispersed_camping_allowed'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'dispersed-camping',
        ]]
    );

    /*
     * =========================================================
     * TRAVEL CENTER / TRUCK STOP PROFILE
     * =========================================================
     *
     * A commercial travel center should not read like a public-land
     * campsite inspection. Keep developed-property, overnight,
     * sensory, accessibility, vehicle-fit, connectivity, and
     * operational questions; suppress backcountry/public-land noise.
     */
    $travelCenterPlaceTypes = [
        'travel-center',
        'truck-stop',
    ];

    $travelCenterNotApplicable = [
        /*
         * Location metadata that only makes sense for managed
         * recreation land.
         */
        'region',

        /*
         * Large commercial lots are not campsite-capacity questions.
         * Parking geometry and vehicle fit remain available.
         */
        'vehicle_capacity',
        'tree_cover',

        /*
         * Ordinary paved-property access does not need a road/trail
         * condition inventory.
         */
        'road_surface',
        'road_width',

        /*
         * Backcountry/natural-setting observations that add little
         * value to a commercial overnight stop.
         */
        'environment_water_nearby',
        'environment_wildlife',
        'environment_bugs',
        'critter_activity',
        'sensory_dust_from_traffic',
        'sensory_wildlife_noise',

        /*
         * Natural-terrain / emergency-access warnings that are not
         * useful for an ordinary developed travel-center property.
         * Keep Trip/Fall Hazard and Traffic Hazard because those can
         * absolutely matter in a parking lot.
         */
        'flash_flood_risk',
        'wildfire_risk',
        'cliff_exposure',
        'rockfall_risk',
        'wildlife_risk',
        'emergency_access',
        'warning_possible_downed_trees',
        'warning_passing_vehicle_dust',
        'warning_motorized_recreation_traffic',
        'warning_blind_turn_traffic_nearby',
        'road_exposure',

        /*
         * Travel centers are normally year-round developed
         * businesses. Temporary storm closures belong to live status,
         * not a Scout's seasonal campsite profile.
         */
        'best_months',
        'winter_access',
        'hurricane_risk',
        'snow_risk',
        'mud_season_risk',
        'monsoon_risk',
        'seasonal_access_note',

        /*
         * Public-land / campsite-rule questions.
         */
        'dispersed_camping_allowed',
        'collecting_firewood',
        'campfire_allowed',
        'drone_use_legal',
        'target_shooting_allowed',
        'designated_sites_only',
        'food_storage_required',
        'existing_sites_encouraged',
        'pack_it_in_pack_it_out',
        'current_fire_restrictions_url',

        /*
         * Fuel is intrinsic to the Travel Center / Truck Stop type.
         * Other nearby services remain useful.
         */
        'nearest_fuel',

        /*
         * Scenic/backcountry experience ratings are noise at a
         * commercial stop. Keep Quiet Evening, Overnight Comfort,
         * and Remote Work because those directly affect overnight use.
         */
        'experience_sunrise_view',
        'experience_sunset_view',
        'experience_overall_scenery',
        'experience_night_sky',
        'experience_stargazing',
        'experience_extended_stay_comfort',
        'experience_sensory_retreat',
    ];

    $appendApplicable(
        $f,
        $travelCenterNotApplicable,
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $travelCenterPlaceTypes,
        ]]
    );

    /*
     * =========================================================
     * PARKING AND ROADSIDE PROFILES
     * =========================================================
     * Differentiate commercial parking from rest areas, which can
     * have real picnic facilities, seasonal closures, hazards and
     * occasionally dump stations. Never infer that amenities are absent.
     * The site/road surface and RV/trailer fit questions stay available.
     */
    $commercialParkingPlaceTypes = [
        'retail-parking',
        'restaurant-parking',
        'casino-parking',
        'medical-office-parking',
        'hospital-parking',
        'church-parking',
        'other-parking',
        'public-parking',
        'street-parking',
    ];

    $roadsideParkingPlaceTypes = [
        'rest-area',
        'vehicle-pulloff',
    ];

    /* Parking lots and roadside stops are not numbered campgrounds. */
    $appendApplicable(
        $f,
        [
            'vehicle_capacity',
            'designated_sites_only',
            'existing_sites_encouraged',
            'dispersed_camping_allowed',
            'collecting_firewood',
            'target_shooting_allowed',
            'food_storage_required',
            'warning_possible_downed_trees',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => array_merge(
                $commercialParkingPlaceTypes,
                $roadsideParkingPlaceTypes
            ),
        ]]
    );

    /*
     * Commercial and civic parking is not a campsite or natural-use
     * inventory. Keep traffic, surfaces, hazards that affect vehicles,
     * overnight comfort, fees, restroom/accessibility, and services.
     */
    $appendApplicable(
        $f,
        [
            'region',
            'tree_cover',
            'environment_wildlife',
            'environment_bugs',
            'critter_activity',
            'sensory_wildlife_noise',
            'cliff_exposure',
            'rockfall_risk',
            'wildlife_risk',
            'warning_motorized_recreation_traffic',
            'warning_passing_vehicle_dust',
            'current_fire_restrictions_url',
            'campfire_allowed',
            'drone_use_legal',
            'experience_sensory_retreat',
            'experience_extended_stay_comfort',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $commercialParkingPlaceTypes,
        ]]
    );

    /*
     * Seasonal campground-style ratings are normally irrelevant to
     * an operating retail/business lot. A closure still belongs in
     * the Place status and Scout notes when exceptional.
     * Rest areas retain these questions: some close seasonally.
     */
    $appendApplicable(
        $f,
        [
            'best_months',
            'winter_access',
            'mud_season_risk',
            'snow_risk',
            'monsoon_risk',
            'hurricane_risk',
            'heat_season_risk',
            'seasonal_access_note',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $commercialParkingPlaceTypes,
        ]]
    );

    /* Street parking has no private internal driveway/turnaround. */
    $appendApplicable(
        $f,
        ['turnaround_space', 'pull_through', 'back_in'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'street-parking',
        ]]
    );

    return $f;
}

/**
 * Build the Quick Warnings panel exclusively from canonical Place Report answers.
 *
 * There are no manually maintained warning switches here. Every warning is
 * calculated from the matching report field, so changing that answer changes
 * the warning automatically.
 */
function llama_place_report_quick_warnings(array $data): array
{
    $fields = llama_place_report_fields();
    $warnings = [];

    $applicabilityInput =
        llama_place_report_scoring_input_from_data(
            $data
        );

    $state = static function (
        string $key
    ) use (
        $data,
        $applicabilityInput
    ): string {
        if (
            !llama_place_report_question_applicable(
                $applicabilityInput,
                $key
            )
        ) {
            return 'unanswered';
        }

        return llama_place_report_answer_state(
            $data,
            $key
        );
    };

    $value = static function (string $key) use ($data, $fields): mixed {
        $field = $fields[$key] ?? null;

        if (!is_array($field)) {
            return null;
        }

        return llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );
    };

    $isYes = static fn (mixed $raw): bool =>
        in_array($raw, [true, 1, '1'], true);

    $isNo = static fn (mixed $raw): bool =>
        in_array($raw, [false, 0, '0'], true);

    $add = static function (
        string $key,
        string $label,
        string $iconKey
    ) use (&$warnings, $fields): void {
        $warnings[$key] = [
            'label' => $label,
            'icon' => llama_place_report_field_icon(
                $iconKey,
                $fields[$iconKey] ?? []
            ),
        ];
    };

    /* Amenities */
    if ($state('amenity_none') === 'answered' && $isYes($value('amenity_none'))) {
        $add('amenity_none', 'No amenities', 'amenity_none');
    }

    /* Connectivity */
    if (
        $state('connectivity_overall') === 'answered'
        && is_numeric($value('connectivity_overall'))
        && (int) $value('connectivity_overall') === 1
    ) {
        $add('warning_no_cell_service', 'No cell service', 'warning_no_cell_service');
    }

    /* Safety answers that become warnings when the answer is No. */
    foreach ([
        'felt_safe_daytime' => ['Did not feel safe during the day', 'felt_safe_daytime'],
        'emergency_access' => ['No emergency vehicle access', 'emergency_access'],
    ] as $key => [$label, $iconKey]) {
        if ($state($key) === 'answered' && $isNo($value($key))) {
            $add($key . '_warning', $label, $iconKey);
        }
    }

    /* Safety answers that become warnings when the answer is Yes. */
    foreach ([
        'flash_flood_risk' => 'Flash-flood risk',
        'wildfire_risk' => 'Wildfire risk',
        'fall_hazard' => 'Trip/Fall Hazard',
        'cliff_exposure' => 'Cliff exposure',
        'rockfall_risk' => 'Rockfall risk',
        'wildlife_risk' => 'Wildlife risk',
        'traffic_hazard' => 'Traffic hazard',
        'warning_possible_downed_trees' => 'Downed trees possible at the campsite',
        'warning_passing_vehicle_dust' => 'Passing vehicle dust',
        'warning_motorized_recreation_traffic' => 'Motorized recreation traffic',
        'warning_blind_turn_traffic_nearby' => 'Blind-turn traffic nearby',
    ] as $key => $label) {
        if ($state($key) === 'answered' && $isYes($value($key))) {
            $add($key, $label, $key);
        }
    }

    /* Site + vehicle answers */
    if (
        $state('tent_camping_suitable') === 'answered'
        && $isNo($value('tent_camping_suitable'))
    ) {
        $add(
            'warning_no_tent_camping_derived',
            'No tent camping',
            'warning_no_tent_camping_derived'
        );
    }

    if (
        $state('levelness') === 'answered'
        && is_numeric($value('levelness'))
        && (int) $value('levelness') <= 2
    ) {
        $add(
            'warning_leveling_required_derived',
            'Leveling may be required',
            'warning_leveling_required_derived'
        );
    }

    /* Sensory privacy */
    foreach (['daytime_privacy', 'nighttime_privacy'] as $privacyKey) {
        if (
            $state($privacyKey) === 'answered'
            && is_numeric($value($privacyKey))
            && (int) $value($privacyKey) === 1
        ) {
            $add('warning_no_privacy', 'No privacy', 'warning_no_privacy');
            break;
        }
    }

    /* Road exposure */
    if (
        $state('road_exposure') === 'answered'
        && is_numeric($value('road_exposure'))
        && (int) $value('road_exposure') === 5
    ) {
        $add(
            'warning_high_road_exposure',
            'Highly exposed to road',
            'warning_high_road_exposure'
        );
    }

    /* Vehicle and trailer fit */
    if (
        $state('max_vehicle_length_feet') === 'answered'
        && is_numeric($value('max_vehicle_length_feet'))
        && (float) $value('max_vehicle_length_feet') <= 25
    ) {
        $add(
            'warning_limited_vehicle_length_derived',
            'Limited vehicle length',
            'warning_limited_vehicle_length_derived'
        );
    }

    if ($state('max_trailer_length_feet') === 'answered') {
        $maxTrailer = $value('max_trailer_length_feet');

        if ((string) $maxTrailer === 'not-recommended') {
            $add(
                'warning_limited_trailer_length',
                'Trailers not recommended',
                'warning_limited_trailer_length'
            );
        } elseif (
            is_numeric($maxTrailer)
            && (float) $maxTrailer <= 25
        ) {
            $add(
                'warning_limited_trailer_length',
                'Limited trailer length',
                'warning_limited_trailer_length'
            );
        }
    }

    return $warnings;
}

function llama_place_report_get_path(array $data, string $path): mixed
{
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

function llama_place_report_multiselect_values(mixed $value): array
{
    if (is_string($value)) {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            return [];
        }
    }

    if (!is_array($value)) {
        return [];
    }

    $values = [];

    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $item = trim((string) $item);

        if ($item !== '') {
            $values[$item] = true;
        }
    }

    return array_keys($values);
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

    if ((string) $field['type'] === 'multiselect') {
        return llama_place_report_multiselect_values($value)
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

    if ((string) $field['type'] === 'multiselect') {
        return llama_place_report_multiselect_values($value);
    }

    if ((string) $field['type'] === 'checkbox') {
        return $value ? '1' : '';
    }

    if (
        in_array(
            (string) $field['type'],
            ['tri', 'permission', 'rating'],
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

        if ((string) $field['type'] === 'multiselect') {
            $input[$key] = is_array($value)
                ? array_values($value)
                : [];
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

    if ($type === 'multiselect') {
        $options = (array) ($field['options'] ?? []);
        $rawValues = is_array($raw)
            ? $raw
            : (($raw === null || $raw === '') ? [] : [$raw]);
        $values = [];

        foreach ($rawValues as $rawValue) {
            if (!is_scalar($rawValue)) {
                continue;
            }

            $cleanValue = trim((string) $rawValue);

            if (
                $cleanValue !== ''
                && array_key_exists($cleanValue, $options)
            ) {
                $values[$cleanValue] = true;
            }
        }

        return array_keys($values);
    }

    if ($type === 'tri') {
        if ($raw === '' || $raw === null) {
            return null;
        }

        return (string) $raw === '1';
    }

    if ($type === 'permission') {
        if ($raw === '' || $raw === null) {
            return null;
        }

        $value = (int) $raw;

        return in_array($value, [0, 1, 2], true)
            ? $value
            : null;
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
     * Derived experience recommendations intentionally mirror the paired
     * rating instead of asking the contributor for the same judgment twice.
     * Keep the stored compatibility fields current for downstream consumers.
     */
    if (!isset($data['experience']) || !is_array($data['experience'])) {
        $data['experience'] = [];
    }

    $deriveRecommendation =
        static function (mixed $rating): ?bool {
            if (!is_numeric($rating)) {
                return null;
            }

            $rating = (int) $rating;

            if ($rating >= 4) {
                return true;
            }

            if ($rating <= 2 && $rating >= 1) {
                return false;
            }

            return null;
        };

    foreach (
        [
            'recommended_overnight_stop' => 'overnight_comfort',
            'recommended_quiet_evening' => 'quiet_evening',
            'recommended_extended_stay' => 'extended_stay_comfort',
            'recommended_sensory_retreat' => 'sensory_retreat',
            'recommended_stargazing' => 'stargazing',
            'recommended_remote_work' => 'remote_work',
        ]
        as $derivedKey => $ratingKey
    ) {
        $data['experience'][$derivedKey] =
            $deriveRecommendation(
                $data['experience'][$ratingKey]
                ?? null
            );
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
        foreach ($value as $item) {
            if (
                is_scalar($item)
                && trim((string) $item) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    if ($value === llama_place_report_unanswered_token()) {
        return false;
    }

    return trim((string) $value) !== '';
}

/*
 * =========================================================
 * PLACE REPORT COMPLETION MODEL
 *
 * Completion is intentionally separate from Scout points.
 * These helpers derive applicability and answer state from
 * the canonical Place Report field registry every time.
 * =========================================================
 */


function llama_place_report_normalized_text_length(
    mixed $value
): int {
    if (!is_scalar($value) && $value !== null) {
        return 0;
    }

    $text =
        preg_replace(
            '/\\s+/u',
            ' ',
            trim((string) $value)
        );

    if (!is_string($text)) {
        return 0;
    }

    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }

    $matched =
        preg_match_all(
            '/./us',
            $text,
            $characters
        );

    return $matched !== false
        ? count($characters[0])
        : strlen($text);
}

function llama_place_report_applicability_rule_matches(
    array $input,
    array $rule
): bool {
    $operator =
        (string) (
            $rule['operator']
            ?? 'equals'
        );

    if (
        in_array(
            $operator,
            [
                'any',
                'all',
            ],
            true
        )
    ) {
        $nestedRules =
            array_values(
                array_filter(
                    (array) (
                        $rule['rules']
                        ?? []
                    ),
                    'is_array'
                )
            );

        if (!$nestedRules) {
            return false;
        }

        if ($operator === 'any') {
            foreach ($nestedRules as $nestedRule) {
                if (
                    llama_place_report_applicability_rule_matches(
                        $input,
                        $nestedRule
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        foreach ($nestedRules as $nestedRule) {
            if (
                !llama_place_report_applicability_rule_matches(
                    $input,
                    $nestedRule
                )
            ) {
                return false;
            }
        }

        return true;
    }

    $dependsOn =
        trim(
            (string) (
                $rule['field']
                ?? ''
            )
        );

    if ($dependsOn === '') {
        return false;
    }

    $actual =
        $input[$dependsOn]
        ?? null;

    $expected =
        $rule['value']
        ?? null;

    $actualValues =
        is_array($actual)
            ? array_map('strval', $actual)
            : [(string) ($actual ?? '')];

    $expectedValues =
        array_map(
            'strval',
            (array) $expected
        );

    $primaryExpected =
        $expectedValues[0]
        ?? '';

    $specialFalseValues = [
        '',
        '0',
        'false',
        llama_place_report_unanswered_token(),
        llama_place_report_unknown_token(),
    ];

    $specialFalseLookup =
        array_map(
            'strtolower',
            $specialFalseValues
        );

    $actualTruthy =
        count($actualValues) > 0
        && array_filter(
            $actualValues,
            static fn (string $value): bool =>
                !in_array(
                    strtolower($value),
                    $specialFalseLookup,
                    true
                )
        ) !== [];

    return match ($operator) {
        'equals' =>
            in_array(
                $primaryExpected,
                $actualValues,
                true
            ),

        'not_equals' =>
            !in_array(
                $primaryExpected,
                $actualValues,
                true
            ),

        'in' =>
            array_intersect(
                $actualValues,
                $expectedValues
            ) !== [],

        'not_in' =>
            array_intersect(
                $actualValues,
                $expectedValues
            ) === [],

        'truthy' =>
            $actualTruthy,

        'falsy' =>
            !$actualTruthy,

        'answered' =>
            llama_place_report_question_answered(
                $input,
                $dependsOn
            ),

        default =>
            false,
    };
}

function llama_place_report_question_applicable(
    array $input,
    string $fieldKey
): bool {
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!is_array($field)) {
        return false;
    }

    if (!empty($field['derived'])) {
        return false;
    }

    $rules =
        (array) (
            $field['applicable_if']
            ?? []
        );

    if (!$rules) {
        return true;
    }

    foreach ($rules as $rule) {
        if (
            !is_array($rule)
            || !llama_place_report_applicability_rule_matches(
                $input,
                $rule
            )
        ) {
            return false;
        }
    }

    return true;
}

function llama_place_report_question_answered(
    array $input,
    string $fieldKey
): bool {
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!is_array($field)) {
        return false;
    }

    if (
        !llama_place_report_question_applicable(
            $input,
            $fieldKey
        )
    ) {
        return false;
    }

    if (!array_key_exists($fieldKey, $input)) {
        return false;
    }

    $value =
        $input[$fieldKey];

    if (
        $value
        === llama_place_report_unanswered_token()
    ) {
        return false;
    }

    if (
        $value
        === llama_place_report_unknown_token()
    ) {
        return true;
    }

    /*
     * Checkbox questions are affirmative observations. An unchecked
     * checkbox may be serialized as 0 by moderator/admin forms, but
     * that must not turn a grouped checkbox section into an answered
     * completion item. Only an explicitly checked value answers it.
     */
    if (
        (string) ($field['type'] ?? '')
        === 'checkbox'
    ) {
        return in_array(
            $value,
            [
                true,
                1,
                '1',
            ],
            true
        );
    }

    $minimumCharacters =
        max(
            0,
            (int) (
                $field['min_characters']
                ?? 0
            )
        );

    if ($minimumCharacters > 0) {
        return
            llama_place_report_normalized_text_length(
                $value
            )
            >= $minimumCharacters;
    }

    return llama_place_report_is_answered_input(
        $input,
        $fieldKey
    );
}

function llama_place_report_completion_items(
    array $input,
    array $excludedFieldKeys = []
): array {
    $items = [];

    $excludedFieldLookup =
        array_fill_keys(
            array_map(
                'strval',
                $excludedFieldKeys
            ),
            true
        );

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $fieldKey =
            (string) $fieldKey;

        if (isset($excludedFieldLookup[$fieldKey])) {
            continue;
        }

        if (
            !llama_place_report_question_applicable(
                $input,
                $fieldKey
            )
        ) {
            continue;
        }

        if (
            array_key_exists(
                'counts_toward_completion',
                $field
            )
            && !$field['counts_toward_completion']
        ) {
            continue;
        }

        $group =
            trim(
                (string) (
                    $field['completion_group']
                    ?? ''
                )
            );

        $itemKey =
            $group !== ''
                ? 'group:' . $group
                : 'field:' . $fieldKey;

        if (!isset($items[$itemKey])) {
            $items[$itemKey] = [
                'key' =>
                    $itemKey,

                'label' =>
                    $group !== ''
                        ? ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $group
                            )
                        )
                        : (string) (
                            $field['label']
                            ?? $fieldKey
                        ),

                'fields' =>
                    [],

                'answered' =>
                    false,
            ];
        }

        $items[$itemKey]['fields'][] =
            $fieldKey;

        if (
            llama_place_report_question_answered(
                $input,
                $fieldKey
            )
        ) {
            $items[$itemKey]['answered'] =
                true;
        }
    }

    return array_values($items);
}

function llama_place_report_question_completion_summary(
    array $input,
    int $photoCount = 0,
    array $excludedFieldKeys = []
): array {
    $items =
        llama_place_report_completion_items(
            $input,
            $excludedFieldKeys
        );

    /*
     * Keep current Llama Scout behavior: current photo evidence
     * contributes one completion item, but it is not a question
     * definition and does not receive Scout points here.
     */
    $items[] = [
        'key' => 'evidence:photo',
        'label' => '1 current photo',
        'fields' => [],
        'answered' => $photoCount > 0,
    ];

    $answered = 0;
    $missing = [];

    foreach ($items as $item) {
        if (!empty($item['answered'])) {
            $answered++;
            continue;
        }

        $missing[] = [
            'key' =>
                (string) (
                    $item['key']
                    ?? ''
                ),

            'label' =>
                (string) (
                    $item['label']
                    ?? ''
                ),

            'fields' =>
                array_values(
                    (array) (
                        $item['fields']
                        ?? []
                    )
                ),
        ];
    }

    $total =
        count($items);

    $missingMinimum = [];

    if (
        !llama_place_report_question_answered(
            $input,
            'name'
        )
    ) {
        $missingMinimum[] =
            'Place name';
    }

    if (
        !llama_place_report_question_answered(
            $input,
            'latitude'
        )
        || !llama_place_report_question_answered(
            $input,
            'longitude'
        )
    ) {
        $missingMinimum[] =
            'Exact location';
    }

    if ($photoCount < 1) {
        $missingMinimum[] =
            '1 current photo';
    }

    return [
        'answered' =>
            $answered,

        'total' =>
            $total,

        'percent' =>
            $total > 0
                ? (int) round(
                    100
                    * (
                        $answered
                        / $total
                    )
                )
                : 0,

        'missing' =>
            $missing,

        'missing_minimum' =>
            $missingMinimum,

        'minimum_met' =>
            !$missingMinimum,

        'over_limit' =>
            $total > 200,
    ];
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

    if ($type === 'permission') {
        return match ((int) $value) {
            1 => 'Yes',
            2 => 'With Permit',
            default => 'No',
        };
    }

    if ($type === 'rating') {
        return (int) $value . '/5';
    }

    if ($type === 'checkbox') {
        return $value ? 'Yes' : null;
    }

    if ($type === 'multiselect') {
        $options = (array) ($field['options'] ?? []);
        $labels = [];

        foreach (llama_place_report_multiselect_values($value) as $selected) {
            $selectedKey = (string) $selected;

            if (array_key_exists($selectedKey, $options)) {
                $labels[] = (string) $options[$selectedKey];
            }
        }

        return $labels ? implode(', ', $labels) : null;
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

function llama_place_report_validate_new_place_minimum(
    array $data,
    int $photoCount
): void {
    $missing = [];

    if (trim((string) ($data['name'] ?? '')) === '') {
        $missing[] = 'a Place name';
    }

    if (
        !is_numeric($data['latitude'] ?? null)
        || !is_numeric($data['longitude'] ?? null)
    ) {
        $missing[] = 'the exact map location';
    }

    if ($photoCount < 1) {
        $missing[] = 'at least one current photo';
    }

    if (!$missing) {
        return;
    }

    throw new InvalidArgumentException(
        'Before submitting a new Place, add '
        . implode(', ', $missing)
        . '. Everything else may be left unanswered when you did not observe it.'
    );
}


function llama_place_report_validate_scout_submission(
    PDO $db,
    int $userId,
    array $data
): void {
    if ($userId < 1) {
        return;
    }

    $level = llama_user_contribution_level(
        $db,
        $userId
    );

    if (
        llama_contribution_level_rank($level)
        < llama_contribution_level_rank(
            LLAMA_CONTRIBUTION_LEVEL_SCOUT
        )
    ) {
        return;
    }

    if (trim((string) ($data['visited_at'] ?? '')) === '') {
        throw new InvalidArgumentException(
            'Scout and Master Scout new Place submissions require the date you personally visited the Place.'
        );
    }
}


function llama_place_report_submit_new_place(
    int $userId,
    array $input
): int {
    if (
        $userId < 1
        || !llama_contributor_can(
            db(),
            $userId,
            'submit_place'
        )
    ) {
        throw new RuntimeException(
            'Your account is not eligible to submit new Places.'
        );
    }

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

    llama_place_report_validate_new_place_minimum(
        $data,
        count($submittedPhotos)
    );

    $db = db();

    llama_place_report_validate_scout_submission(
        $db,
        $userId,
        $data
    );
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
    if (
        $userId < 1
        || !llama_contributor_can(
            db(),
            $userId,
            'submit_place'
        )
    ) {
        throw new RuntimeException(
            'Your account is not eligible to submit new Places.'
        );
    }

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

    llama_place_report_validate_new_place_minimum(
        $data,
        count($keptPhotos) + count($submittedPhotos)
    );

    $newPhotos = [];
    $db = db();

    llama_place_report_validate_scout_submission(
        $db,
        $userId,
        $data
    );

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

    /*
     * Multi-select values are stored as JSON in normalized child tables.
     * Submission JSON already contains arrays, so decode only persisted strings.
     */
    foreach (llama_place_report_fields() as $fieldKey => $field) {
        if ((string) ($field['type'] ?? '') !== 'multiselect') {
            continue;
        }

        $storage =
            (string) (
                $field['storage']
                ?? ''
            );

        $value =
            llama_place_report_get_path(
                $data,
                $storage
            );

        llama_place_report_set_path(
            $data,
            $storage,
            llama_place_report_multiselect_values($value)
        );
    }

    /*
     * Recompute derived recommendation flags at read time as well so older
     * published rows cannot display a stale recommendation that conflicts
     * with the current 1â5 experience rating.
     */
    $deriveRecommendation =
        static function (mixed $rating): ?bool {
            if (!is_numeric($rating)) {
                return null;
            }

            $rating = (int) $rating;

            if ($rating >= 4) {
                return true;
            }

            if ($rating >= 1 && $rating <= 2) {
                return false;
            }

            return null;
        };

    foreach (
        [
            'recommended_overnight_stop' => 'overnight_comfort',
            'recommended_quiet_evening' => 'quiet_evening',
            'recommended_extended_stay' => 'extended_stay_comfort',
            'recommended_sensory_retreat' => 'sensory_retreat',
            'recommended_stargazing' => 'stargazing',
            'recommended_remote_work' => 'remote_work',
        ]
        as $derivedKey => $ratingKey
    ) {
        $data['experience'][$derivedKey] =
            $deriveRecommendation(
                $data['experience'][$ratingKey]
                ?? null
            );
    }

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
