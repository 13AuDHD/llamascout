<?php

declare(strict_types=1);

/**
 * Individual Site applicability and dependency chains.
 *
 * This file owns behavioral conditions for Site facts. Presentation metadata
 * must not rewrite applicable_if after this pass.
 */
function llama_place_report_apply_site_applicability(
    array &$fields
): void {
    $types =
        llama_place_report_applicability_types();

    $sitePlaceTypes =
        $types['camping'];

    /*
     * Objective Site facts belong only where individual overnight Sites are
     * meaningful.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'site_number',
            'site_accessible',
            'max_people',
            'vehicle_capacity',
            'max_vehicle_length_feet',
            'parking_surface',
            'ground_condition',
            'levelness',
            'overhead_clearance_feet',
            'parking_length_feet',
            'parking_grade',
            'site_length_feet',
            'site_width_feet',
            'tent_pad',
            'double_driveway',
            'hike_in_distance_feet',
            'capacity_size_rating',
            'site_hookups_available',
            'max_rv_length_feet',
            'pull_through',
            'back_in',
            'turnaround_space',
            'leveling_required',
            'site_access_difficulty',
            'walking_distance_from_vehicle',
            'wheelchair_friendly',
            'mobility_device_friendly',
            'flat_walking_surface',
            'step_free_access',
            'site_shade',
            'site_open_sky',
            'site_rating',
            'condition_rating',
            'location_rating',
            'amenity_picnic_table',
            'amenity_fire_ring',
            'amenity_grill',
            'amenity_bear_box',
            'amenity_lantern_post',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $sitePlaceTypes,
        ]]
    );

    /*
     * Accessible picnic-table status belongs to the individual Site because
     * the picnic table itself is Site-owned.
     */
    llama_place_report_set_applicable(
        $fields,
        ['accessible_picnic_table'],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $sitePlaceTypes,
            ],
            [
                'field' => 'amenity_picnic_table',
                'operator' => 'truthy',
            ],
        ]
    );

    /*
     * Tent-pad dimensions exist only when a tent pad/platform exists.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'tent_pad_length_feet',
            'tent_pad_width_feet',
        ],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $sitePlaceTypes,
            ],
            [
                'field' => 'tent_pad',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    /*
     * Hookup children require the Site-level parent to be Yes.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'hookup_electric',
            'hookup_water',
            'hookup_sewer',
        ],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $sitePlaceTypes,
            ],
            [
                'field' => 'site_hookups_available',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        ['hookup_electric_service'],
        [
            [
                'field' => 'type',
                'operator' => 'in',
                'value' => $sitePlaceTypes,
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
}
