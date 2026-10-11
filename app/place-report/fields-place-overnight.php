<?php

declare(strict_types=1);

/**
 * Place-wide overnight permissions. These are independent facts: a property
 * may allow tent stays without allowing vehicles to be occupied overnight.
 * All values use the existing 0/1/2/3 permission convention.
 */
function llama_place_report_add_overnight_permission_fields(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
        'report_scopes' => ['place'],
        'report_scope_group' => 'place',
        'layout_section' => 'place',
        'layout_group' => 'overnight_stays',
    ];
    $permission = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit required',
        '3' => 'Conditional',
    ];

    foreach ([
        'tent_camping_allowed' => 'Tent camping permitted?',
        'rv_camping_allowed' => 'RV camping permitted?',
        'trailer_camping_allowed' => 'Trailer camping permitted?',
    ] as $key => $label) {
        $add($key, $label, 'rules', 'select', 'rules.' . $key,
            $common + ['options' => $permission]);
    }

    // The previously approved question 15. One explanation avoids three
    // redundant text boxes and covers unusual conditions across permissions.
    $add('overnight_restrictions', 'Additional overnight restrictions',
        'rules', 'textarea', 'rules.overnight_restrictions', $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'placeholder' => 'Only include restrictions that affect overnight stays.',
        ]);

    // A single permit URL can support several permission types. It is
    // requested only when a newly added permission explicitly requires one.
    $requiresPermit = [];
    foreach (['tent_camping_allowed','rv_camping_allowed','trailer_camping_allowed'] as $key) {
        $requiresPermit[] = [
            'field' => $key,
            'operator' => 'equals',
            'value' => '2',
        ];
    }

    $add('overnight_vehicle_tent_permit_url',
        'Tent / RV / trailer permit URL', 'rules', 'url',
        'rules.overnight_vehicle_tent_permit_url', $common + [
            'applicable_if' => [[
                'operator' => 'any',
                'rules' => $requiresPermit,
            ]],
            'counts_toward_completion' => false,
        ]);
}
