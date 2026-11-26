<?php

declare(strict_types=1);

/**
 * Site Info, Parking and Site Amenities applicability from the owner matrix
 * (rows 98–111, 138–148, 158–173 and 175).
 *
 * This pass follows presentation metadata, which defines the direct tent pad
 * and hookup dependencies. Type rules do not change permission, ownership,
 * imported source facts or historical answers.
 */
function llama_place_report_apply_camping_profiles(array &$fields): void
{
    llama_place_report_append_applicable($fields, [
        'site_number',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'day-use', 'other-parking', 'membership-host', 'private-property',
            'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'site_loop', 'site_hookups_available', 'hookup_electric_service', 'hookup_water',
        'hookup_sewer',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'day-use',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'site_type_of_use',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use',
            'travel-center', 'other-parking', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'tent_camping_suitable',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use',
            'fairgrounds', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'capacity_size_rating',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'vehicle-pulloff', 'day-use', 'fairgrounds', 'other-parking', 'membership-host',
            'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'max_people',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'day-use',
            'fairgrounds', 'other-parking', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'vehicle_capacity',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'vehicle-pulloff',
            'trailhead', 'day-use', 'fairgrounds', 'other-parking', 'membership-host',
            'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'site_length_feet', 'site_width_feet',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'vehicle-pulloff', 'trailhead', 'day-use', 'fairgrounds', 'other-parking',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'overhead_clearance_feet',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use',
            'public-parking', 'fairgrounds', 'street-parking', 'other-parking',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'back_in', 'pull_through',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use',
            'public-parking', 'travel-center', 'truck-stop', 'retail-parking',
            'restaurant-parking', 'casino-parking', 'medical-office-parking',
            'hospital-parking', 'church-parking', 'fairgrounds', 'other-parking',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'parking_size', 'parking_length_feet',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use',
            'other-parking', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'max_vehicle_length_feet', 'max_rv_length_feet',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use', 'public-parking',
            'travel-center', 'truck-stop', 'retail-parking', 'restaurant-parking',
            'casino-parking', 'medical-office-parking', 'hospital-parking', 'church-parking',
            'fairgrounds', 'street-parking', 'other-parking', 'membership-host',
            'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_fire_ring', 'amenity_lantern_post', 'site_potable_water', 'tent_pad',
        'tent_pad_width_feet', 'tent_pad_length_feet',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'day-use', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_grill', 'amenity_picnic_table',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'day-use', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_bear_box',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'scenic-overlook',
            'trailhead', 'day-use', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'site_lake_access', 'site_river_access',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'rest-area', 'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'site_trail_access', 'site_amenities_notes',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'vehicle-pulloff', 'trailhead', 'day-use', 'membership-host',
            'private-property', 'other',
        ],
    ]]);
    // Row 174 (site_wifi_rating) needs separate cross-scope parent handling.
}
