<?php

declare(strict_types=1);

/**
 * Place-wide overnight permissions.
 *
 * RVs and trailers are intentionally represented by one question because a
 * trailer is functionally part of the same overnight vehicle permission for
 * the Place form. Historical trailer_camping_allowed data can remain in
 * storage, but new reports use rv_camping_allowed as the canonical combined
 * RV / trailer answer.
 *
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
        'rv_camping_allowed' => 'RV / trailer camping permitted?',
    ] as $key => $label) {
        $add(
            $key,
            $label,
            'rules',
            'select',
            'rules.' . $key,
            $common + [
                'options' => $permission,
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
        ]
    );

    /*
     * One permit URL supports either camping mode. The old standalone
     * trailer permission is no longer an active contributor question.
     */
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
        ]
    );
}
