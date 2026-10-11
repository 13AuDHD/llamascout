<?php

declare(strict_types=1);

/**
 * Additional SHARED property amenities. Individual campsite amenities are
 * distinct answers; none of these fields claims to describe every site.
 * Existing amenity_toilets, amenity_potable_water, etc. are reused unchanged.
 */
function llama_place_report_add_shared_amenity_fields(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['amenities'],
        'report_scopes' => ['place'],
        'report_scope_group' => 'place',
        'layout_section' => 'place',
        'layout_group' => 'shared_amenities',
    ];

    // A campground host can be present only during its operating season.
    $add('amenity_campground_host', 'Campground host available?',
        'amenities', 'select', 'amenities.campground_host', $common + [
            'options' => [
                '1' => 'Yes',
                '0' => 'No',
                'seasonal' => 'Seasonal',
            ],
        ]);

    foreach ([
        'water_filling_station' => 'Drinking-water filling station available?',
        'dishwashing_station' => 'Dishwashing station available?',
        'accessible_showers' => 'Accessible showers available?',
    ] as $key => $label) {
        $add('amenity_' . $key, $label, 'amenities', 'tri',
            'amenities.' . $key, $common);
    }

    // Property-level accessibility: only shared facilities, not individual
    // campsite paths, slopes or sleeping areas. Existing accessible_toilet
    // and accessible_picnic_table fields remain valid historical answers.
    $add('shared_accessible_facilities', 'Accessible shared facilities available?',
        'amenities', 'tri', 'amenities.shared_accessible_facilities', $common);

    $add('shared_accessible_facility_types', 'Which shared facilities are accessible?',
        'amenities', 'multiselect', 'amenities.shared_accessible_facility_types', $common + [
            'options' => [
                'toilets' => 'Toilets',
                'showers' => 'Showers',
                'laundry' => 'Laundry',
                'picnic_area' => 'Picnic area',
                'other' => 'Other',
            ],
            'applicable_if' => [[
                'field' => 'shared_accessible_facilities',
                'operator' => 'equals',
                'value' => '1',
            ]],
        ]);
}
