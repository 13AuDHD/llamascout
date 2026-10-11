<?php
declare(strict_types=1);

/**
 * Supplemental PLACE-wide rules. Existing pets, leashes, generator
 * restrictions, generator hours, run limits, campfires, firewood, drones,
 * target shooting and fire-restriction URL remain in their canonical fields.
 * Do not define duplicate copies here.
 */
function llama_place_report_add_place_rules_fields(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
        'report_scopes' => ['place'],
        'report_scope_group' => 'place',
    ];
    $conditional = [
        '0' => 'No',
        '1' => 'Yes',
        '3' => 'Conditional',
    ];

    $add('place_charcoal_grills_allowed', 'Charcoal grills allowed?',
        'rules', 'select', 'rules.place_charcoal_grills_allowed',
        $common + ['options' => $conditional]);

    $add('place_propane_fire_pits_allowed', 'Propane fire pits allowed?',
        'rules', 'select', 'rules.place_propane_fire_pits_allowed',
        $common + ['options' => $conditional]);

    $add('place_quiet_hours', 'Quiet hours?',
        'rules', 'tri', 'rules.place_quiet_hours', $common);

    // Hourly options, reusing simple scalar storage and standard select UI.
    $hours = [];
    for ($hour = 0; $hour < 24; $hour++) {
        $value = sprintf('%02d:00', $hour);
        $hours[$value] = date('g A', strtotime('2000-01-01 ' . $value));
    }
    $whenQuiet = [
        'field' => 'place_quiet_hours',
        'operator' => 'equals',
        'value' => '1',
    ];
    $add('place_quiet_hours_begin', 'Quiet hours begin',
        'rules', 'select', 'rules.place_quiet_hours_begin',
        $common + ['options' => $hours, 'applicable_if' => [$whenQuiet]]);
    $add('place_quiet_hours_end', 'Quiet hours end',
        'rules', 'select', 'rules.place_quiet_hours_end',
        $common + ['options' => $hours, 'applicable_if' => [$whenQuiet]]);

    $add('place_alcohol_permitted', 'Alcohol permitted?',
        'rules', 'select', 'rules.place_alcohol_permitted',
        $common + ['options' => $conditional]);

    $add('place_additional_rules', 'Additional rules or restrictions',
        'rules', 'textarea', 'rules.place_additional_rules',
        $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'placeholder' => 'Only restrictions not captured above.',
        ]);
}

/** Apply the approved layout after the legacy applicability/scope setup. */
function llama_place_report_apply_place_rules_metadata(array &$fields): void
{
    foreach ([
        'place_charcoal_grills_allowed',
        'place_propane_fire_pits_allowed',
        'place_quiet_hours',
        'place_quiet_hours_begin',
        'place_quiet_hours_end',
        'place_alcohol_permitted',
        'place_additional_rules',
    ] as $key) {
        if (!isset($fields[$key])) {
            continue;
        }
        $fields[$key]['report_scopes'] = ['place'];
        $fields[$key]['report_scope_group'] = 'place';
        $fields[$key]['layout_section'] = 'place';
        $fields[$key]['layout_group'] = 'place_rules';
    }
}
