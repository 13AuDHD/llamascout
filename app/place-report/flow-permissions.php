<?php

declare(strict_types=1);

/**
 * Canonical permission answers.
 *
 * Permission means the documented or posted rule for an activity.
 * It never means:
 * - that the activity is physically suitable for the Site;
 * - that people commonly do it;
 * - that staff currently enforce it;
 * - or that ordinary parking is permitted.
 *
 * Legacy values 0, 1, and 2 retain their meanings. A value of 3 represents
 * Conditional.
 */

function llama_place_report_add_permission_fields(
    array &$fields,
    callable $add
): void {
    $yesNoOptions = [
        '0' => 'No',
        '1' => 'Yes',
    ];

    $permitOptions = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit required',
    ];

    $legacyConditional = [
        '3' => 'Conditional',
    ];

    $permissionDefinitions = [
        'overnight_camping_allowed' => [
            'options' => $yesNoOptions,
            'legacy_values' => [
                '2' => 'Permit required',
                '3' => 'Conditional',
            ],
        ],
        'dispersed_camping_allowed' => [
            'options' => $yesNoOptions,
            'legacy_values' => [
                '2' => 'Permit required',
                '3' => 'Conditional',
            ],
        ],
        'campfire_allowed' => [
            'options' => $permitOptions,
            'legacy_values' => $legacyConditional,
        ],
        'collecting_firewood' => [
            'options' => $permitOptions,
            'legacy_values' => $legacyConditional,
        ],
        'drone_use_legal' => [
            'options' => $permitOptions,
            'legacy_values' => $legacyConditional,
        ],
        'target_shooting_allowed' => [
            'options' => $permitOptions,
            'legacy_values' => $legacyConditional,
        ],
    ];

    foreach ($permissionDefinitions as $key => $definition) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['type'] = 'select';
        $fields[$key]['options'] =
            (array) ($definition['options'] ?? []);
        $fields[$key]['legacy_values'] =
            (array) ($definition['legacy_values'] ?? []);
        $fields[$key]['allow_unknown'] = true;
    }

    $carTruckOptions = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit required',
        '3' => 'Conditional',
    ];

    $labels = [
        'overnight_camping_allowed' =>
            'Overnight stay permitted?',

        'dispersed_camping_allowed' =>
            'Dispersed camping permitted?',

        'campfire_allowed' =>
            'Campfires permitted?',

        'collecting_firewood' =>
            'Collecting firewood permitted?',

        'drone_use_legal' =>
            'Drone use permitted?',

        'target_shooting_allowed' =>
            'Target shooting permitted?',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] = $label;
        }
    }

    $help = [
        'overnight_camping_allowed' =>
            'Record the documented or posted rule for remaining overnight at this Place or Area. This does not answer whether ordinary parking is permitted and does not imply that tents, sleeping inside a vehicle, or RV/trailer camping are individually permitted.',

        'dispersed_camping_allowed' =>
            'Record the documented or posted rule for camping outside a developed campground or formally assigned Site.',

        'campfire_allowed' =>
            'Record the normal documented or posted rule for campfires. Current temporary fire restrictions are recorded separately and may override this normal rule.',

        'collecting_firewood' =>
            'Record the documented or posted rule for gathering firewood. This is separate from whether firewood is available or whether campfires are currently allowed.',

        'drone_use_legal' =>
            'Record the documented or posted rule for recreational drone use at this location. Do not infer permission from seeing other people fly drones.',

        'target_shooting_allowed' =>
            'Record the documented or posted rule for recreational target shooting. Do not infer permission from evidence that others have shot here.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] = $text;
        }
    }

    $add(
        'car_truck_camping',
        'Sleeping in a car / truck permitted?',
        'rules',
        'select',
        'rules.car_truck_camping',
        [
            'allow_unknown' => true,
            'options' => $carTruckOptions,
            'points_categories' => ['seasons_rules_services'],
            'help' =>
                'Record the documented or posted rule for sleeping overnight inside a car, SUV, van, pickup, or similar non-RV vehicle. This is not a parking-permission question: a vehicle may be allowed to park while sleeping in it is prohibited.',
        ]
    );

    $groups = [
        'camping' => [
            'label' =>
                'Camping permit / conditions',

            'fields' => [
                'overnight_camping_allowed',
                'dispersed_camping_allowed',
                'car_truck_camping',
            ],

            'help' =>
                'Explain documented permits, conditions, limitations, or exceptions affecting the overall overnight rule, dispersed camping, or sleeping inside a vehicle. Observed behavior is not a substitute for the rule.',
        ],

        'fire_activities' => [
            'label' =>
                'Fire and activity permit / conditions',

            'fields' => [
                'campfire_allowed',
                'collecting_firewood',
                'drone_use_legal',
                'target_shooting_allowed',
            ],

            'help' =>
                'Explain documented permit requirements or conditions for campfires, collecting firewood, drones, or target shooting. Current temporary fire restrictions are recorded separately.',
        ],
    ];

    foreach ($groups as $suffix => $group) {
        $key = $suffix . '_conditional_details';

        $add(
            $key,
            $group['label'],
            'rules',
            'textarea',
            'rules.' . $key,
            [
                'allow_unknown' => false,
                'wide' => true,
                'rows' => 3,
                'counts_toward_completion' => true,
                'points_categories' => ['seasons_rules_services'],
                'help' => $group['help'],
                'applicable_if' => [[
                    'operator' => 'any',
                    'rules' => array_map(
                        static fn (string $fieldKey): array => [
                            'field' => $fieldKey,
                            'operator' => 'in',
                            'value' => ['2', '3'],
                        ],
                        $group['fields']
                    ),
                ]],
            ]
        );
    }

    $permitGroups = [
        'camping_permit_url' => [
            'label' =>
                'Camping permit URL',

            'fields' =>
                $groups['camping']['fields'],

            'help' =>
                'Link to the official permit application or permit-information page. This is not a reservation or booking URL.',
        ],

        'fire_activity_permit_url' => [
            'label' =>
                'Fire / activity permit URL',

            'fields' =>
                $groups['fire_activities']['fields'],

            'help' =>
                'Link to the official firewood, fire, drone, or target-shooting permit page that applies. Current fire restrictions are separate.',
        ],
    ];

    foreach ($permitGroups as $key => $group) {
        $add(
            $key,
            $group['label'],
            'rules',
            'url',
            'rules.' . $key,
            [
                'wide' => true,
                'placeholder' => 'https://...',
                'counts_toward_completion' => false,
                'points_categories' => ['seasons_rules_services'],
                'help' => $group['help'],
                'applicable_if' => [[
                    'operator' => 'any',
                    'rules' => array_map(
                        static fn (string $fieldKey): array => [
                            'field' => $fieldKey,
                            'operator' => 'equals',
                            'value' => '2',
                        ],
                        $group['fields']
                    ),
                ]],
            ]
        );
    }
}

/**
 * Post-applicability adjustment.
 *
 * Applicability profiles are applied after all fields exist. This pass only
 * expands older Yes/Permit overnight conditions so Conditional is treated as
 * an overnight-positive answer too.
 */
function llama_place_report_apply_permission_applicability(
    array &$fields
): void {
    $expandConditionalOvernight =
        static function (mixed &$node) use (
            &$expandConditionalOvernight
        ): void {
            if (!is_array($node)) {
                return;
            }

            if (
                ($node['field'] ?? null)
                === 'overnight_camping_allowed'
                && ($node['operator'] ?? null) === 'in'
                && is_array($node['value'] ?? null)
                && in_array('1', $node['value'], true)
                && in_array('2', $node['value'], true)
                && !in_array('3', $node['value'], true)
            ) {
                $node['value'][] = '3';
            }

            foreach ($node as &$child) {
                if (is_array($child)) {
                    $expandConditionalOvernight($child);
                }
            }

            unset($child);
        };

    foreach ($fields as &$field) {
        if (
            isset($field['applicable_if'])
            && is_array($field['applicable_if'])
        ) {
            $expandConditionalOvernight(
                $field['applicable_if']
            );
        }
    }

    unset($field);
}

/**
 * Compatibility wrapper for older callers.
 *
 * New registry assembly should call the two phases explicitly.
 */
function llama_place_report_apply_permission_flows(
    array &$fields,
    callable $add
): void {
    llama_place_report_add_permission_fields(
        $fields,
        $add
    );

    llama_place_report_apply_permission_applicability(
        $fields
    );
}
