<?php

declare(strict_types=1);

/**
 * Place-type applicability for Activities and shared Amenities.
 *
 * Owner-completed Question Matrix rows 26–44 replace the old broad
 * recreation profiles. These are visibility rules only: they do not
 * assert a facility exists or change any stored or inherited answers.
 */
function llama_place_report_apply_facility_profiles(
    array &$fields
): void {
    llama_place_report_append_applicable($fields, [
        'activities',
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
        'amenity_toilets', 'amenity_showers',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'trailhead', 'day-use', 'travel-center', 'truck-stop',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_potable_water', 'amenity_trash', 'amenity_dump_station',
        'amenity_picnic_shelter',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'trailhead', 'day-use', 'public-parking', 'travel-center',
            'truck-stop', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_wifi',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'day-use', 'public-parking', 'travel-center', 'truck-stop',
            'retail-parking', 'restaurant-parking', 'casino-parking',
            'medical-office-parking', 'hospital-parking', 'church-parking', 'fairgrounds',
            'street-parking', 'other-parking', 'membership-host', 'private-property',
            'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_laundry', 'amenity_dishwashing_station',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'day-use', 'travel-center', 'truck-stop', 'membership-host',
            'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_playground', 'amenity_dog_park',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'day-use', 'public-parking', 'travel-center', 'truck-stop',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_amphitheater',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'day-use', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_campground_host',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'day-use', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'amenity_camp_store',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'dispersed-camping', 'developed-campground', 'camping-area', 'rv-park-resort',
            'membership-host', 'other',
        ],
    ]]);

    // Rows 41–44 are applicable to every Place type. No type restriction
    // is needed for trailhead, river access, lake access or fishing pier.
    // Retired amenity_trailhead_parking remains retired.
}
