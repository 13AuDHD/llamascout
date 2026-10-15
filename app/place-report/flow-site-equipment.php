<?php

declare(strict_types=1);

/**
 * Site -> Camping & Equipment organization.
 *
 * These questions describe the individual campsite, not the parent Place.
 * Storage keys remain unchanged. Ownership is defined only by
 * app/place-report/flow-scopes.php.
 */
function llama_place_report_apply_site_equipment_metadata(
    array &$fields
): void {
    $labels = [
        'tent_pad' =>
            'Tent pad / platform?',

        'tent_pad_length_feet' =>
            'Tent pad length (ft)',

        'tent_pad_width_feet' =>
            'Tent pad width (ft)',

        'site_hookups_available' =>
            'Utility hookups at this campsite?',

        'hookup_electric' =>
            'Electric hookup?',

        'hookup_electric_service' =>
            'Electric service',

        'hookup_water' =>
            'Water hookup?',

        'hookup_sewer' =>
            'Sewer hookup?',

        'amenity_fire_ring' =>
            'Fire ring at campsite?',

        'amenity_grill' =>
            'Grill at campsite?',

        'amenity_picnic_table' =>
            'Picnic table at campsite?',

        'amenity_bear_box' =>
            'Food-storage / bear box at campsite?',

        'amenity_lantern_post' =>
            'Lantern post at campsite?',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] =
                $label;
        }
    }

    $help = [
        'tent_pad' =>
            'A defined tent pad, platform, or prepared tent area belonging to this campsite.',

        'tent_pad_length_feet' =>
            'Approximate usable length of the tent pad or platform.',

        'tent_pad_width_feet' =>
            'Approximate usable width of the tent pad or platform.',

        'site_hookups_available' =>
            'Whether this individual campsite has any utility hookups. Shared campground utilities belong under Place facilities.',

        'hookup_electric' =>
            'Electric connection available directly at this campsite.',

        'hookup_electric_service' =>
            'Electrical service available directly at this campsite.',

        'hookup_water' =>
            'Water connection available directly at this campsite, not a shared filling station.',

        'hookup_sewer' =>
            'Sewer connection available directly at this campsite, not a shared dump station.',

        'amenity_fire_ring' =>
            'A permanent or designated fire ring belonging to this campsite.',

        'amenity_grill' =>
            'A permanent grill provided at this campsite.',

        'amenity_picnic_table' =>
            'A picnic table assigned to or located within this campsite.',

        'amenity_bear_box' =>
            'A dedicated food-storage locker or bear box provided at this campsite.',

        'amenity_lantern_post' =>
            'A lantern hook or post provided at this campsite.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] =
                $text;
        }
    }

    foreach (
        [
            'tent_pad_length_feet',
            'tent_pad_width_feet',
        ]
        as $key
    ) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['applicable_if'] = [[
            'field' => 'tent_pad',
            'operator' => 'equals',
            'value' => '1',
        ]];
    }

    foreach (
        [
            'hookup_electric',
            'hookup_water',
            'hookup_sewer',
        ]
        as $key
    ) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['applicable_if'] = [[
            'field' => 'site_hookups_available',
            'operator' => 'equals',
            'value' => '1',
        ]];
    }

    if (isset($fields['hookup_electric_service'])) {
        $fields['hookup_electric_service']['applicable_if'] = [
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
        ];
    }
}
