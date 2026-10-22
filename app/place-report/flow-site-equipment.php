<?php

declare(strict_types=1);

/**
 * Site -> Utilities & Equipment organization.
 *
 * These questions describe the individual Site, not the parent Place.
 * Storage keys remain unchanged. Ownership is defined only by
 * app/place-report/flow-scopes.php.
 */
function llama_place_report_apply_site_equipment_metadata(
    array &$fields
): void {
    $labels = [
        'tent_pad' =>
            'Tent Pad',

        'tent_pad_length_feet' =>
            'Tent pad length (ft)',

        'tent_pad_width_feet' =>
            'Tent pad width (ft)',

        'site_hookups_available' =>
            'Hookups',

        'hookup_electric' =>
            'Power / electric hookup?',

        'hookup_electric_service' =>
            'Electrical Service',

        'hookup_water' =>
            'Water Hookup',

        'hookup_sewer' =>
            'Sewer Hookup',

        'amenity_fire_ring' =>
            'Fire Ring',

        'amenity_grill' =>
            'Grill',

        'amenity_picnic_table' =>
            'Picnic Table',

        'amenity_bear_box' =>
            'Food Storage / Bear Box',

        'amenity_lantern_post' =>
            'Lantern Post',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] =
                $label;
        }
    }

    $help = [
        'tent_pad' =>
            'A defined tent pad, platform, or prepared tent area belonging to this Site.',

        'tent_pad_length_feet' =>
            'Approximate usable length of the tent pad or platform.',

        'tent_pad_width_feet' =>
            'Approximate usable width of the tent pad or platform.',

        'site_hookups_available' =>
            'Whether this individual Site has any utility hookups. Shared Place utilities belong under Facilities & Amenities.',

        'hookup_electric' =>
            'Power or electrical connection available directly at this Site.',

        'hookup_electric_service' =>
            'Electrical service available directly at this Site.',

        'hookup_water' =>
            'Water connection available directly at this Site, not a shared filling station.',

        'hookup_sewer' =>
            'Sewer connection available directly at this Site, not a shared dump station.',

        'amenity_fire_ring' =>
            'A permanent or designated fire ring belonging to this Site.',

        'amenity_grill' =>
            'A permanent grill provided at this Site.',

        'amenity_picnic_table' =>
            'A picnic table assigned to or located within this Site.',

        'amenity_bear_box' =>
            'A dedicated food-storage locker or bear box provided at this Site.',

        'amenity_lantern_post' =>
            'A lantern hook or post provided at this Site.',
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

    /*
     * Keep the individual utility questions visible. The broad hookup answer
     * is useful context, but it must not hide known electric/water/sewer facts.
     */
    foreach (
        [
            'hookup_electric',
            'hookup_water',
            'hookup_sewer',
        ]
        as $key
    ) {
        if (isset($fields[$key])) {
            $fields[$key]['applicable_if'] =
                [];
        }
    }

    if (isset($fields['hookup_electric_service'])) {
        $fields['hookup_electric_service']['applicable_if'] = [[
            'field' => 'hookup_electric',
            'operator' => 'equals',
            'value' => '1',
        ]];
    }
}
