<?php

declare(strict_types=1);

function llama_place_report_apply_camping_profiles(
    array &$fields
): void {
    $types =
        llama_place_report_applicability_types();

    llama_place_report_set_applicable(
        $fields,
        [
            'campsite_count',
            'site_hookups_available',
        ],
        [[
            'field' => 'type',
            'operator' => 'in',
            'value' => $types['developed_campground'],
        ]]
    );

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
                'value' => $types['developed_campground'],
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
                'value' => $types['developed_campground'],
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

    llama_place_report_append_applicable(
        $fields,
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

    llama_place_report_append_applicable(
        $fields,
        [
            'overnight_camping_allowed',
            'designated_sites_only',
        ],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'rv-park-resort',
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        ['region'],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['hosted_private_fairground'],
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        ['vehicle_capacity'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'fairgrounds',
        ]]
    );

    llama_place_report_append_applicable(
        $fields,
        ['dispersed_camping_allowed'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'dispersed-camping',
        ]]
    );
}
