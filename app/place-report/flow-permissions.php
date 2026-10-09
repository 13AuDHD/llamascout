<?php

declare(strict_types=1);

/**
 * Conditional permission answers, preserving existing storage keys.
 * The legacy permission values 0, 1, and 2 retain their meanings.
 * A value of 3 represents Conditional; the shared select renderer
 * supports this without changing the legacy answer parser.
 */
function llama_place_report_apply_permission_flows(array &$fields, callable $add): void
{
    $options = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit',
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
        // No permission state is inferred from its parent or neighbors.
        $fields[$key]['type'] = 'select';
        $fields[$key]['options'] = $options;
        $fields[$key]['allow_unknown'] = true;
    }

    $add('car_truck_camping', 'Car/Truck Camping', 'rules', 'select',
        'rules.car_truck_camping', [
            'allow_unknown' => true,
            'options' => $options,
            'points_categories' => ['seasons_rules_services'],
            'help' => 'Sleeping inside a car or truck. This is separate from permission to park, pitch a tent, or occupy an RV campsite.',
        ]);

    $groups = [
        'camping' => [
            'label' => 'Camping conditions',
            'fields' => ['overnight_camping_allowed', 'dispersed_camping_allowed', 'car_truck_camping'],
            'help' => 'Explain restrictions for camping, sleeping inside a vehicle, or designated sites. Describe who may stay and under what conditions.',
        ],
        'fire_activities' => [
            'label' => 'Fire and activity conditions',
            'fields' => ['campfire_allowed', 'collecting_firewood', 'drone_use_legal', 'target_shooting_allowed'],
            'help' => 'Explain conditional fire, firewood, drone, or shooting rules. The current official fire restrictions URL remains independently available.',
        ],
    ];

    foreach ($groups as $suffix => $group) {
        $key = $suffix . '_conditional_details';
        $add($key, $group['label'], 'rules', 'textarea', 'rules.' . $key, [
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
                        'operator' => 'equals',
                        'value' => '3',
                    ],
                    $group['fields']
                ),
            ]],
        ]);
    }
}
