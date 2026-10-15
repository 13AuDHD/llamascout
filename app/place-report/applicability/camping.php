<?php

declare(strict_types=1);

/**
 * Camping-property profiles.
 *
 * Place type describes what the Place is. It must not silently answer a rule
 * question. Developed campground, RV park, and dispersed-camping labels never
 * substitute for explicit permission/restriction answers.
 */
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

    /*
     * Hosted/private/fairground Places are not public-land management units.
     * Keep Region out of their normal profile unless a later dedicated field
     * gives that concept a different meaning.
     */
    llama_place_report_append_applicable(
        $fields,
        ['region'],
        [[
            'field' => 'type',
            'operator' => 'not_in',
            'value' => $types['hosted_private_fairground'],
        ]]
    );

    /*
     * Fairgrounds describe a venue containing possible overnight spaces.
     * Whole-venue vehicle capacity is misleading; campsite/space count is the
     * appropriate Place/Area fact.
     */
    llama_place_report_append_applicable(
        $fields,
        ['vehicle_capacity'],
        [[
            'field' => 'type',
            'operator' => 'not_equals',
            'value' => 'fairgrounds',
        ]]
    );
}
