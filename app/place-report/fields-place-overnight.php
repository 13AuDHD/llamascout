<?php

declare(strict_types=1);

/**
 * Overnight-mode permission questions.
 *
 * Permission is kept separate from physical Site fit. A Site may physically
 * fit an RV or tent even when that use is prohibited, and permission does not
 * imply that every Site can physically accommodate the equipment.
 *
 * Ownership is defined only by app/place-report/flow-scopes.php.
 */
function llama_place_report_add_overnight_permission_fields(
    callable $add
): void {
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
        'layout_section' => 'place',
        'layout_group' => 'overnight_stays',
    ];

    $permission = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit required',
        '3' => 'Conditional',
    ];

    $definitions = [
        'tent_camping_allowed' => [
            'label' =>
                'Tent camping permitted?',

            'help' =>
                'Whether setting up and using a tent is permitted under the applicable rules. This does not say whether an individual Site has a tent pad or enough usable ground for a tent.',
        ],

        'rv_camping_allowed' => [
            'label' =>
                'RV / trailer camping permitted?',

            'help' =>
                'Whether overnight use of an RV, motorhome, travel trailer, fifth wheel, truck camper, or similar camping rig is permitted. Physical size and fit are recorded separately for an individual Site.',
        ],
    ];

    foreach ($definitions as $key => $definition) {
        $add(
            $key,
            $definition['label'],
            'rules',
            'select',
            'rules.' . $key,
            $common + [
                'options' => $permission,
                'help' => $definition['help'],
            ]
        );
    }

    $add(
        'overnight_restrictions',
        'Additional overnight restrictions',
        'rules',
        'textarea',
        'rules.overnight_restrictions',
        $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'placeholder' =>
                'Only include restrictions that affect overnight stays.',
            'help' =>
                'Use this for overnight restrictions that are not already represented by the specific permission questions.',
        ]
    );

    $requiresPermit = [];

    foreach (
        [
            'tent_camping_allowed',
            'rv_camping_allowed',
        ]
        as $key
    ) {
        $requiresPermit[] = [
            'field' => $key,
            'operator' => 'equals',
            'value' => '2',
        ];
    }

    $add(
        'overnight_vehicle_tent_permit_url',
        'Tent / RV / trailer permit URL',
        'rules',
        'url',
        'rules.overnight_vehicle_tent_permit_url',
        $common + [
            'applicable_if' => [[
                'operator' => 'any',
                'rules' => $requiresPermit,
            ]],
            'counts_toward_completion' => false,
            'help' =>
                'Link to the official permit information for tent or RV/trailer overnight use. This is separate from a reservation or booking page.',
        ]
    );
}
