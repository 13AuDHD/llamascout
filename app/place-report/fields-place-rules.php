<?php

declare(strict_types=1);

/**
 * Place-wide rules and restrictions.
 *
 * Existing canonical answers are reused whenever possible. These questions
 * describe rules for the Place as a whole; individual campsite exceptions
 * belong in Site → Rules & Restrictions.
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

    $add(
        'place_charcoal_grills_allowed',
        'Charcoal grills allowed?',
        'rules',
        'select',
        'rules.place_charcoal_grills_allowed',
        $common + [
            'options' => $conditional,
            'help' => 'Answer for charcoal grills or charcoal fires that are allowed by the property rules. Current fire restrictions can temporarily override this answer.',
        ]
    );

    $add(
        'place_propane_fire_pits_allowed',
        'Propane fire pits allowed?',
        'rules',
        'select',
        'rules.place_propane_fire_pits_allowed',
        $common + [
            'options' => $conditional,
            'help' => 'Answer for portable propane fire pits or similar contained propane fires. Current fire restrictions can temporarily override this answer.',
        ]
    );

    $add(
        'place_quiet_hours',
        'Quiet hours?',
        'rules',
        'tri',
        'rules.place_quiet_hours',
        $common + [
            'help' => 'Use this for the property-wide quiet period, separate from generator-specific hours.',
        ]
    );

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

    $add(
        'place_quiet_hours_begin',
        'Quiet hours begin',
        'rules',
        'select',
        'rules.place_quiet_hours_begin',
        $common + [
            'options' => $hours,
            'applicable_if' => [$whenQuiet],
        ]
    );

    $add(
        'place_quiet_hours_end',
        'Quiet hours end',
        'rules',
        'select',
        'rules.place_quiet_hours_end',
        $common + [
            'options' => $hours,
            'applicable_if' => [$whenQuiet],
        ]
    );

    $add(
        'place_alcohol_permitted',
        'Alcohol permitted?',
        'rules',
        'select',
        'rules.place_alcohol_permitted',
        $common + [
            'options' => $conditional,
            'help' => 'Choose Conditional when alcohol is allowed only in certain areas, containers, seasons, or circumstances.',
        ]
    );

    $add(
        'place_additional_rules',
        'Additional rules or restrictions',
        'rules',
        'textarea',
        'rules.place_additional_rules',
        $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'placeholder' => 'Only rules or restrictions not captured above.',
        ]
    );
}

/**
 * Apply the approved Place-wide ordering and dependencies after the legacy
 * field registry has been assembled.
 */
function llama_place_report_apply_place_rules_metadata(array &$fields): void
{
    $placeRuleKeys = [
        'pets_allowed',
        'dogs_required_to_be_leashed',
        'generator_restrictions',
        'generator_prohibited',
        'generator_quiet_hours',
        'generator_quiet_hours_begin',
        'generator_quiet_hours_end',
        'generator_run_restrictions',
        'generator_max_run_hours',
        'generator_restriction_details',
        'campfire_allowed',
        'collecting_firewood',
        'place_charcoal_grills_allowed',
        'place_propane_fire_pits_allowed',
        'current_fire_restrictions_url',
        'fire_activities_conditional_details',
        'fire_activity_permit_url',
        'place_quiet_hours',
        'place_quiet_hours_begin',
        'place_quiet_hours_end',
        'place_alcohol_permitted',
        'drone_use_legal',
        'target_shooting_allowed',
        'place_additional_rules',
    ];

    foreach ($placeRuleKeys as $key) {
        if (!isset($fields[$key])) {
            continue;
        }

        $fields[$key]['report_scopes'] = ['place'];
        $fields[$key]['report_scope_group'] = 'place';
        $fields[$key]['scope_inheritance'] = 'nearest_recorded';
        $fields[$key]['layout_section'] = 'place';
        $fields[$key]['layout_group'] = 'place_rules';
    }

    if (isset($fields['pets_allowed'])) {
        $fields['pets_allowed']['label'] = 'Pets allowed?';
        $fields['pets_allowed']['help'] = 'Answer for the Place as a whole. Individual campsite exceptions belong under Site rules.';
    }

    if (isset($fields['dogs_required_to_be_leashed'])) {
        $fields['dogs_required_to_be_leashed']['label'] = 'Leash required?';
        $fields['dogs_required_to_be_leashed']['applicable_if'] = [[
            'field' => 'pets_allowed',
            'operator' => 'equals',
            'value' => '1',
        ]];
    }

    if (isset($fields['generator_restrictions'])) {
        $fields['generator_restrictions']['label'] = 'Generator restrictions?';
        $fields['generator_restrictions']['help'] = 'Choose Yes when generators are prohibited, limited to certain hours, or subject to run-time restrictions.';
    }

    if (isset($fields['generator_prohibited'])) {
        $fields['generator_prohibited']['label'] = 'Generators prohibited?';
    }

    if (isset($fields['generator_quiet_hours'])) {
        $fields['generator_quiet_hours']['label'] = 'Generator quiet hours?';
    }

    if (isset($fields['generator_run_restrictions'])) {
        $fields['generator_run_restrictions']['label'] = 'Generator run-time limits?';
    }

    if (isset($fields['campfire_allowed'])) {
        $fields['campfire_allowed']['label'] = 'Campfires allowed?';
    }

    if (isset($fields['collecting_firewood'])) {
        $fields['collecting_firewood']['label'] = 'Collecting firewood allowed?';
    }

    if (isset($fields['current_fire_restrictions_url'])) {
        $fields['current_fire_restrictions_url']['label'] = 'Current fire restrictions URL';
        $fields['current_fire_restrictions_url']['wide'] = true;
        $fields['current_fire_restrictions_url']['counts_toward_completion'] = false;
        $fields['current_fire_restrictions_url']['help'] = 'Link to the official current fire-restrictions page. This can temporarily override the normal fire rules recorded above.';
    }

    if (isset($fields['fire_activities_conditional_details'])) {
        $fields['fire_activities_conditional_details']['label'] = 'Fire / activity conditions';
    }
}
