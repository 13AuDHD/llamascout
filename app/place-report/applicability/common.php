<?php

declare(strict_types=1);

function llama_place_report_set_applicable(
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
}

function llama_place_report_append_applicable(
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
}

function llama_place_report_applicability_types(): array
{
    $camping = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'rv-park-resort',
        'membership-host',
        'private-property',
        'fairgrounds',
    ];

    $natural = [
        'dispersed-camping',
        'developed-campground',
        'camping-area',
        'trailhead',
        'day-use',
        'scenic-overlook',
        'vehicle-pulloff',
    ];

    return [
        'camping' => $camping,

        'dispersed_relevant' => [
            'dispersed-camping',
            'camping-area',
            'trailhead',
            'day-use',
            'scenic-overlook',
            'vehicle-pulloff',
        ],

        'campfire_relevant' => [
            'dispersed-camping',
            'developed-campground',
            'camping-area',
            'rv-park-resort',
            'trailhead',
            'day-use',
            'membership-host',
            'private-property',
            'fairgrounds',
        ],

        'natural' => $natural,

        'rough_access' =>
            array_values(
                array_unique(
                    array_merge(
                        $camping,
                        $natural
                    )
                )
            ),

        'intrinsically_overnight' => [
            'dispersed-camping',
            'developed-campground',
            'camping-area',
            'rv-park-resort',
            'membership-host',
        ],

        'developed_campground' => [
            'developed-campground',
            'camping-area',
            'rv-park-resort',
            'membership-host',
            'private-property',
            'fairgrounds',
        ],

        'hosted_private_fairground' => [
            'membership-host',
            'private-property',
            'fairgrounds',
        ],

        'travel_center' => [
            'travel-center',
            'truck-stop',
        ],

        'commercial_parking' => [
            'retail-parking',
            'restaurant-parking',
            'casino-parking',
            'medical-office-parking',
            'hospital-parking',
            'church-parking',
            'other-parking',
            'public-parking',
            'street-parking',
        ],

        'roadside_parking' => [
            'rest-area',
            'vehicle-pulloff',
        ],

        'natural_day_use' => [
            'trailhead',
            'scenic-overlook',
            'vehicle-pulloff',
            'day-use',
        ],

        'tent_overnight' =>
            array_values(
                array_unique(
                    array_merge(
                        $camping,
                        [
                            'trailhead',
                            'scenic-overlook',
                            'vehicle-pulloff',
                            'day-use',
                        ]
                    )
                )
            ),
    ];
}


function llama_place_report_overnight_positive_rule(): array
{
    return [
        'field' => 'overnight_camping_allowed',
        'operator' => 'in',
        'value' => [
            '1',
            '2',
            '3',
        ],
    ];
}

function llama_place_report_apply_common_applicability(
    array &$fields
): void {
    $types =
        llama_place_report_applicability_types();

    // Owner-completed Question Matrix, rows 8 and 25. These are type
    // filters only, not assumptions about camping permission or site counts.
    llama_place_report_set_applicable(
        $fields,
        ['campsite_count'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => [
                'dispersed-camping', 'developed-campground', 'camping-area',
                'rv-park-resort', 'day-use', 'fairgrounds',
                'membership-host', 'private-property', 'other',
            ],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        ['region'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => [
                'dispersed-camping', 'developed-campground', 'camping-area',
                'vehicle-pulloff', 'trailhead', 'day-use', 'other',
            ],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'ground_condition',
            'water_crossings',
            'downed_tree_risk',
            'site_access_difficulty',
            'road_overall_difficulty',
            'road_stress',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['rough_access'],
        ]]
    );

    // Owner Question Matrix rows 9–14: contact and link questions.
    llama_place_report_append_applicable($fields, [
        'contact_phone', 'contact_email', 'contact_website_url',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'trailhead', 'day-use', 'public-parking', 'travel-center',
            'truck-stop', 'retail-parking', 'restaurant-parking', 'casino-parking',
            'medical-office-parking', 'hospital-parking', 'church-parking', 'fairgrounds',
            'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'facility_map_url',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'rest-area',
            'scenic-overlook', 'day-use', 'fairgrounds', 'membership-host',
            'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'contact_reservations_url',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'scenic-overlook',
            'day-use', 'fairgrounds', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    llama_place_report_append_applicable($fields, [
        'contact_related_links',
    ], [[
        'field' => 'type',
        'operator' => 'in',
        'value' => [
            'developed-campground', 'camping-area', 'rv-park-resort', 'day-use',
            'fairgrounds', 'membership-host', 'private-property', 'other',
        ],
    ]]);

    // Owner Question Matrix rows 45–88. Apply only the Place-type portion
    // here, retaining the original answer-dependent rules registered on fields.
    $typeRule = static fn (string $operator, array $values): array => [[
        'field' => 'type',
        'operator' => $operator,
        'value' => $values,
    ]];

    llama_place_report_append_applicable($fields, [
        'place_open_24_hours', 'place_opening_time', 'place_closing_time',
    ], $typeRule('not_in', ['dispersed-camping', 'street-parking']));

    llama_place_report_append_applicable($fields, [
        'place_entrance_gate', 'place_gate_access_type',
        'place_gate_opening_time', 'place_gate_closing_time',
    ], $typeRule('not_in', ['vehicle-pulloff', 'street-parking']));

    $approachTypes = [
        'dispersed-camping', 'developed-campground', 'camping-area',
        'rv-park-resort', 'rest-area', 'scenic-overlook',
        'vehicle-pulloff', 'trailhead', 'fairgrounds', 'street-parking',
        'other-parking', 'membership-host', 'private-property', 'other',
    ];

    llama_place_report_append_applicable($fields, [
        'approach_permit_required',
    ], $typeRule('in', array_values(array_diff($approachTypes, ['membership-host']))));

    llama_place_report_append_applicable($fields, [
        'approach_fee_required', 'approach_road_fee',
    ], $typeRule('in', $approachTypes));

    llama_place_report_append_applicable($fields, [
        'approach_road_hazards', 'approach_deep_ruts', 'rocks',
        'washboards', 'potholes', 'mud_risk', 'steep_grades', 'drop_off_exposure',
    ], $typeRule('in', array_values(array_diff($approachTypes, ['rv-park-resort']))));

    llama_place_report_append_applicable($fields, [
        'approach_gate_present', 'approach_gate_access_type',
        'approach_gate_opens', 'approach_gate_closes',
    ], $typeRule('in', [
        'dispersed-camping', 'developed-campground', 'camping-area',
        'rv-park-resort', 'rest-area', 'scenic-overlook', 'vehicle-pulloff',
        'trailhead', 'day-use', 'membership-host', 'private-property', 'other',
    ]));

    llama_place_report_append_applicable($fields, [
        'high_clearance_recommended', 'four_wheel_drive_recommended',
    ], [[
        'field' => 'sedan_accessible', 'operator' => 'equals', 'value' => '0',
    ]]);

    /*
     * Active Site facts. Historical suitability fields are deliberately not
     * used as parents. Objective measurements stand on their own.
     */
    llama_place_report_set_applicable(
        $fields,
        [
            'site_number',
            'site_accessible',
            'max_people',
            'overhead_clearance_feet',
            'parking_length_feet',
            'parking_grade',
            'site_length_feet',
            'site_width_feet',
            'tent_pad',
            'double_driveway',
            'hike_in_distance_feet',
            'max_rv_length_feet',
            'site_rating',
            'condition_rating',
            'location_rating',
            'capacity_size_rating',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['camping'],
        ]]
    );

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
                'value' => $types['camping'],
            ],
            [
                'field' => 'tent_pad',
                'operator' => 'equals',
                'value' => '1',
            ],
        ]
    );

    llama_place_report_set_applicable(
        $fields,
        ['accessible_toilet'],
        [[
            'field' => 'amenity_toilets',
            'operator' => 'in',
            'value' => [
                'pit',
                'flush',
                'portable',
                'present_unknown_type',
            ],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        ['accessible_picnic_table'],
        [[
            'field' => 'amenity_picnic_table',
            'operator' => 'truthy',
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        ['dogs_required_to_be_leashed'],
        [[
            'field' => 'pets_allowed',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        ['dispersed_camping_allowed'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['dispersed_relevant'],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'collecting_firewood',
            'target_shooting_allowed',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['natural'],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'campfire_allowed',
            'current_fire_restrictions_url',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['campfire_relevant'],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'amenity_fire_ring',
            'amenity_bear_box',
            'pack_it_in_pack_it_out',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' =>
                array_values(
                    array_unique(
                        array_merge(
                            $types['camping'],
                            $types['natural']
                        )
                    )
                ),
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        [
            'designated_sites_only',
            'food_storage_required',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['camping'],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
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

    llama_place_report_set_applicable(
        $fields,
        ['generator_quiet_hours'],
        [[
            'field' => 'generator_restrictions',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
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

    llama_place_report_set_applicable(
        $fields,
        ['warning_possible_downed_trees'],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['camping'],
        ]]
    );

    llama_place_report_set_applicable(
        $fields,
        ['seasonal_access_note'],
        [[
            'field' => 'seasonal_closure',
            'operator' => 'equals',
            'value' => '1',
        ]]
    );
}
