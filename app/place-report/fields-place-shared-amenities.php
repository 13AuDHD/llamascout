<?php

declare(strict_types=1);

/**
 * Shared facilities are deliberately distinct from campsite equipment.
 * Existing canonical amenity keys are reused so old answers remain readable.
 *
 * Ownership is intentionally not defined here. app/place-report/flow-scopes.php
 * is the single canonical source for Place / Area / Site ownership.
 */
function llama_place_report_add_shared_amenity_fields(
    array &$fields,
    callable $add
): void {
    $placeShared = [
        'amenity_none' => [
            'No shared facilities or amenities',
            'Use this only when the Place itself provides no shared facilities. Individual campsite equipment is recorded under Site.',
        ],
        'amenity_toilets' => [
            'Shared toilets',
            'Toilets available somewhere at the Place. Private campsite toilets are recorded under Site.',
        ],
        'amenity_potable_water' => [
            'Shared potable water',
            'Potable water available as a shared Place facility, not an individual campsite hookup.',
        ],
        'amenity_trash' => [
            'Shared trash service',
            'Trash collection or dumpsters available for visitors somewhere at the Place.',
        ],
        'amenity_recycling' => [
            'Shared recycling',
            'Recycling collection available somewhere at the Place.',
        ],
        'amenity_showers' => [
            'Shared showers',
            'Shared shower facilities available at the Place. Private campsite showers are recorded under Site.',
        ],
        'amenity_laundry' => [
            'Shared laundry',
            'Laundry facilities available for visitors somewhere at the Place.',
        ],
        'amenity_wifi' => [
            'Shared WiFi',
            'WiFi provided by the Place in a shared area. Cellular coverage is recorded separately.',
        ],
        'amenity_dump_station' => [
            'Dump station',
            'A shared RV waste dump station is available at or within the Place.',
        ],
        'amenity_electricity' => [
            'Shared electrical service',
            'Electrical service or outlets are available in a shared facility. Individual campsite hookups are recorded under Site.',
        ],
        'amenity_amphitheater' => [
            'Amphitheater',
            'A shared amphitheater or programmed gathering area is available.',
        ],
        'amenity_playground' => [
            'Playground',
            'A shared playground is available at the Place.',
        ],
        'amenity_picnic_shelter' => [
            'Picnic area / pavilion',
            'A shared picnic area, shelter, or pavilion is available at the Place.',
        ],
        'amenity_fishing_pier' => [
            'Fishing pier',
            'A shared fishing pier is available at the Place.',
        ],
        'amenity_lake_access' => [
            'Lake access',
            'The Place provides shared access to a lake.',
        ],
        'amenity_river_access' => [
            'River access',
            'The Place provides shared access to a river.',
        ],
        'amenity_trailhead' => [
            'Trailhead',
            'A trailhead is part of or directly served by the Place.',
        ],
        'amenity_trailhead_parking' => [
            'Trailhead parking',
            'Dedicated trailhead parking is available at the Place.',
        ],
    ];

    $publicNames = [
        'amenity_toilets' => 'Toilets',
        'amenity_potable_water' => 'Potable water',
        'amenity_trash' => 'Trash bins',
        'amenity_picnic_shelter' => 'Picnic pavilion',
        'amenity_showers' => 'Showers',
        'amenity_dump_station' => 'Dump station',
        'amenity_wifi' => 'WiFi',
        'amenity_laundry' => 'Laundry',
        'amenity_playground' => 'Playground',
        'amenity_amphitheater' => 'Amphitheater',
    ];

    foreach ($placeShared as $key => [$label, $help]) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['label'] = $publicNames[$key] ?? $label;
        $fields[$key]['help'] = $help;
        $fields[$key]['layout_section'] = 'place';
        $fields[$key]['layout_group'] = 'shared_amenities';
    }

    /*
     * These legacy amenity keys describe the campsite itself. Move them into
     * Site -> Camping & Equipment without renaming their storage paths.
     *
     * section=site_vehicle is intentional: the existing Admin campsite
     * persistence layer discovers campsite-editable fields from that section.
     */
    foreach ([
        'amenity_fire_ring',
        'amenity_grill',
        'amenity_picnic_table',
        'amenity_bear_box',
        'amenity_lantern_post',
    ] as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['section'] = 'site_vehicle';
        $fields[$key]['layout_section'] = 'site';
        $fields[$key]['layout_group'] = 'camping_equipment';
    }

    $common = [
        'allow_unknown' => true,
        'points_categories' => ['amenities'],
        'layout_section' => 'place',
        'layout_group' => 'shared_amenities',
    ];

    $add(
        'amenity_dog_park',
        'Dog park',
        'amenities',
        'tri',
        'amenities.dog_park',
        $common + [
            'icon' => 'dog',
            'help' => 'A designated shared dog park or off-leash area at this Place.',
        ]
    );

    $add(
        'amenity_campground_host',
        'Camp host',
        'amenities',
        'select',
        'amenities.campground_host',
        $common + [
            'icon' => 'user',
            'control' => 'yes-no',
            'options' => [
                '1' => 'Yes',
                '0' => 'No',
                'seasonal' => 'Seasonal',
            ],
            'help' => 'Whether an on-site campground host is normally available. Choose Seasonal when a host is present only during the operating season.',
        ]
    );

    $add(
        'amenity_camp_store',
        'Camp store available?',
        'amenities',
        'tri',
        'amenities.camp_store',
        $common + [
            'help' => 'A store or staffed retail counter at the Place where visitors can normally buy basic camping supplies, food, ice, firewood, or similar essentials.',
        ]
    );

    foreach ([
        'water_filling_station' => [
            'Drinking-water filling station available?',
            'A shared location specifically intended for filling drinking-water containers or RV water tanks.',
        ],
        'dishwashing_station' => [
            'Dishwashing station available?',
            'A designated shared sink or station intended for washing dishes.',
        ],
        'accessible_showers' => [
            'Accessible showers available?',
            'Shared shower facilities identified or designed as accessible.',
        ],
    ] as $key => [$label, $help]) {
        $add(
            'amenity_' . $key,
            $label,
            'amenities',
            'tri',
            'amenities.' . $key,
            $common + ['help' => $help]
        );
    }

    $add(
        'shared_accessible_facilities',
        'Accessible shared facilities available?',
        'amenities',
        'tri',
        'amenities.shared_accessible_facilities',
        $common + [
            'help' => 'This describes shared Place facilities only. Accessibility of an individual campsite is recorded under Site.',
        ]
    );

    $add(
        'shared_accessible_facility_types',
        'Which shared facilities are accessible?',
        'amenities',
        'multiselect',
        'amenities.shared_accessible_facility_types',
        $common + [
            'options' => [
                'toilets' => 'Toilets',
                'showers' => 'Showers',
                'laundry' => 'Laundry',
                'picnic_area' => 'Picnic area / pavilion',
                'water' => 'Drinking water',
                'camp_store' => 'Camp store',
                'other' => 'Other',
            ],
            'summary' => 'Choose accessible shared facilities',
            'applicable_if' => [[
                'field' => 'shared_accessible_facilities',
                'operator' => 'equals',
                'value' => '1',
            ]],
        ]
    );
}
