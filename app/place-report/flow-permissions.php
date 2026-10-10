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
            'label' => 'Camping permit / conditions',
            'fields' => ['overnight_camping_allowed', 'dispersed_camping_allowed', 'car_truck_camping'],
            'help' => 'Explain camping permits, conditions, limitations, and exceptions for tents or sleeping inside a vehicle.',
        ],
        'fire_activities' => [
            'label' => 'Fire and activity permits / conditions',
            'fields' => ['campfire_allowed', 'collecting_firewood', 'drone_use_legal', 'target_shooting_allowed'],
            'help' => 'Explain permit requirements or other conditions for campfires, collecting firewood, drones, or shooting. Verify current fire restrictions separately.',
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
                'rules' => array_merge(
                    array_map(
                        static fn (string $fieldKey): array => [
                            'field' => $fieldKey,
                            'operator' => 'in',
                            'value' => ['2', '3'],
                        ],
                        $group['fields']
                    )
                ),
            ]],
        ]);
    }
    $permitGroups = [
        'camping_permit_url' => [
            'label' => 'Camping permit URL',
            'fields' => $groups['camping']['fields'],
            'help' => 'Link to the official permit application or permit information page. This is not a booking URL.',
        ],
        'fire_activity_permit_url' => [
            'label' => 'Fire / activity permit URL',
            'fields' => $groups['fire_activities']['fields'],
            'help' => 'Link to the official firewood, fire, drone or target-shooting permit page that applies. Current fire restrictions are a separate URL.',
        ],
    ];
    foreach ($permitGroups as $key => $group) {
        $add($key, $group['label'], 'rules', 'url', 'rules.' . $key, [
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
        ]);
    }

}
