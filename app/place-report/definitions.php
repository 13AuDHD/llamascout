<?php

declare(strict_types=1);

/* Sections, choices, place types, and canonical field icons. */

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
        'campground' => [
            'label' => 'Campground',
            'description' => 'Campground-wide facts that apply to the property as a whole',
            'icon' => 'tent',
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

        'site_accessible' => 'wheelchair',
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
        'max_people' => 'users-group',
        'overhead_clearance_feet' => 'ruler-measure',
        'parking_length_feet' => 'ruler-measure',
        'parking_grade' => 'at-level-tool',
        'site_length_feet' => 'ruler-measure',
        'site_width_feet' => 'ruler-measure',
        'tent_pad' => 'tent',
        'tent_pad_length_feet' => 'ruler-measure',
        'tent_pad_width_feet' => 'ruler-measure',
        'double_driveway' => 'parking',
        'hike_in_distance_feet' => 'walk',
        'site_rating' => 'star',
        'condition_rating' => 'star',
        'location_rating' => 'map-pin',
        'capacity_size_rating' => 'ruler-measure',
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
        'amenity_grill' => 'campfire',
        'amenity_picnic_table' => 'picnic-table',
        'amenity_bear_box' => 'bear',
        'amenity_lantern_post' => 'bulb',
        'amenity_recycling' => 'trash',
        'amenity_amphitheater' => 'building-community',
        'amenity_playground' => 'users-group',
        'amenity_picnic_shelter' => 'picnic-table',
        'amenity_fishing_pier' => 'water-waves',
        'amenity_lake_access' => 'water-waves',
        'amenity_river_access' => 'water-waves',
        'amenity_trailhead' => 'at-directions-post',
        'amenity_trailhead_parking' => 'parking',
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

