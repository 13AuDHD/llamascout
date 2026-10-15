<?php

declare(strict_types=1);

/**
 * Canonical permission answers.
 *
 * Permission means whether an activity is allowed under the applicable rule.
 * It never means that the physical space is suitable, comfortable, or large
 * enough for a particular vehicle or camping setup.
 *
 * Legacy values 0, 1, and 2 retain their meanings. A value of 3 represents
 * Conditional.
 */
function llama_place_report_apply_permission_flows(
    array &$fields,
    callable $add
): void {
    $options = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit required',
        '3' => 'Conditional',
    ];

    $permissionKeys = [
        'overnight_camping_allowed',
        'dispersed_camping_allowed',
        'campfire_allowed',
        'collecting_firewood',
        'drone_use_legal',
        'target_shooting_allowed',
    ];

    foreach ($permissionKeys as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['type'] =
            'select';

        $fields[$key]['options'] =
            $options;

        $fields[$key]['allow_unknown'] =
            true;
    }

    if (isset($fields['overnight_camping_allowed'])) {
        $fields['overnight_camping_allowed']['label'] =
            'Overnight stay permitted?';

        $fields['overnight_camping_allowed']['help'] =
            'The overall official rule for remaining overnight at this Place or Area. This is separate from whether tents, sleeping inside a vehicle, or RV/trailer camping are individually permitted.';
    }

    if (isset($fields['dispersed_camping_allowed'])) {
        $fields['dispersed_camping_allowed']['label'] =
            'Dispersed camping permitted?';

        $fields['dispersed_camping_allowed']['help'] =
            'Whether camping outside a developed campground or formally assigned campsite is permitted under the applicable rules.';
    }

    $add(
        'car_truck_camping',
        'Sleeping in a car / truck permitted?',
        'rules',
        'select',
        'rules.car_truck_camping',
        [
            'allow_unknown' => true,
            'options' => $options,
            'points_categories' => ['seasons_rules_services'],
            'help' =>
                'Whether overnight sleeping or camping inside a car, SUV, van, pickup, or similar non-RV vehicle is permitted. This is separate from permission to park the vehicle.',
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
                'Explain permits, conditions, limitations, or exceptions affecting the overall overnight rule, dispersed camping, or sleeping inside a vehicle.',
        ],

        'fire_activities' => [
            'label' =>
                'Fire and activity permits / conditions',

            'fields' => [
                'campfire_allowed',
                'collecting_firewood',
                'drone_use_legal',
                'target_shooting_allowed',
            ],

            'help' =>
                'Explain permit requirements or other conditions for campfires, collecting firewood, drones, or shooting. Verify current fire restrictions separately.',
        ],
    ];

    foreach ($groups as $suffix => $group) {
        $key =
            $suffix
            . '_conditional_details';

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
                'Link to the official permit application or permit information page. This is not a booking URL.',
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

    /*
     * Conditional overnight permission still means overnight use is possible.
     * Existing applicability that recognized Yes or Permit must also recognize
     * Conditional.
     */
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
                && ($node['operator'] ?? null)
                    === 'in'
                && is_array(
                    $node['value']
                    ?? null
                )
                && in_array(
                    '1',
                    $node['value'],
                    true
                )
                && in_array(
                    '2',
                    $node['value'],
                    true
                )
                && !in_array(
                    '3',
                    $node['value'],
                    true
                )
            ) {
                $node['value'][] =
                    '3';
            }

            foreach ($node as &$child) {
                if (is_array($child)) {
                    $expandConditionalOvernight(
                        $child
                    );
                }
            }

            unset($child);
        };

    foreach ($fields as &$field) {
        if (
            isset($field['applicable_if'])
            && is_array(
                $field['applicable_if']
            )
        ) {
            $expandConditionalOvernight(
                $field['applicable_if']
            );
        }
    }

    unset($field);
}
