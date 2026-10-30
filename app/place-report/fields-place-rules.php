<?php

declare(strict_types=1);

/**
 * Rules and restrictions.
 *
 * Rule fields describe documented or posted policy. They are not observations
 * of what visitors commonly do or what staff appear to enforce on a particular
 * visit.
 *
 * Ownership is defined only by app/place-report/flow-scopes.php.
 */
function llama_place_report_add_place_rules_fields(callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ];

    $permitRuleOptions = [
        '0' => 'No',
        '1' => 'Yes',
        '2' => 'Permit required',
    ];

    $legacyConditional = [
        '3' => 'Conditional',
    ];

    $add(
        'place_charcoal_grills_allowed',
        'Charcoal Grills',
        'rules',
        'select',
        'rules.place_charcoal_grills_allowed',
        $common + [
            'options' => $permitRuleOptions,
            'legacy_values' => $legacyConditional,
            'help' =>
                'Record the normal documented or posted rule for charcoal grills or charcoal fires. Current temporary fire restrictions may override this normal rule.',
        ]
    );

    $add(
        'place_propane_fire_pits_allowed',
        'Propane Fire Pits',
        'rules',
        'select',
        'rules.place_propane_fire_pits_allowed',
        $common + [
            'options' => $permitRuleOptions,
            'legacy_values' => $legacyConditional,
            'help' =>
                'Record the normal documented or posted rule for portable propane fire pits or similar contained propane fires. Current temporary fire restrictions may override this normal rule.',
        ]
    );

    $add(
        'place_quiet_hours',
        'Quiet Hours',
        'rules',
        'tri',
        'rules.place_quiet_hours',
        $common + [
            'help' =>
                'Whether documented or posted quiet hours apply. This is separate from generator-specific operating hours.',
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
        'Alcohol Allowed',
        'rules',
        'select',
        'rules.place_alcohol_permitted',
        $common + [
            'options' => [
                '0' => 'No',
                '1' => 'Yes',
                'no-glass' => 'No Glass',
            ],
            'legacy_values' => [
                '3' => 'Conditional',
            ],
            'help' =>
                'Record the posted alcohol policy. No Glass means alcohol is allowed but glass containers are prohibited.',
        ]
    );

    $add(
        'place_additional_rules',
        'Rules Summary',
        'rules',
        'textarea',
        'rules.place_additional_rules',
        $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'placeholder' =>
                'Additional documented rules, restrictions, exceptions, or clarifications not captured by other questions.',
            'help' =>
                'Add documented rules, restrictions, exceptions, or clarifications not covered by the questions above. Do not repeat existing answers.',
        ]
    );
}

/**
 * Apply labels and dependencies after the legacy field registry has
 * been assembled. Scope ownership remains exclusively in flow-scopes.php.
 */
function llama_place_report_apply_place_rules_metadata(array &$fields): void
{
    $labels = [
        'pets_allowed' =>
            'Pets Allowed',

        'dogs_required_to_be_leashed' =>
            'Leash Required',

        'generator_restrictions' =>
            'Generator Restrictions',

        'generator_prohibited' =>
            'Generators Prohibited',

        'generator_quiet_hours' =>
            'Generator Quiet Hours',

        'generator_run_restrictions' =>
            'Run-Time Restrictions',

        'campfire_allowed' =>
            'Campfires Allowed',

        'collecting_firewood' =>
            'Collecting Firewood',

        'drone_use_legal' =>
            'Drone Use',

        'target_shooting_allowed' =>
            'Target Shooting',
    ];

    foreach ($labels as $key => $label) {
        if (isset($fields[$key])) {
            $fields[$key]['label'] = $label;
        }
    }

    $help = [
        'pets_allowed' =>
            'Record the documented or posted pet rule for the current reporting target. Do not infer permission because pets were present.',

        'dogs_required_to_be_leashed' =>
            'Record whether the documented or posted rule requires dogs to be leashed.',

        'generator_restrictions' =>
            'Choose Yes when documented or posted generator rules prohibit generators, limit operating hours, impose run-time limits, or create generator-free areas.',

        'generator_prohibited' =>
            'Choose Yes only when the applicable documented or posted rule prohibits generator use.',

        'generator_quiet_hours' =>
            'Choose Yes when documented or posted generator rules include a recurring no-generator time window.',

        'generator_run_restrictions' =>
            'Choose Yes when documented or posted generator rules limit total run time or duration.',

        'campfire_allowed' =>
            'Record the normal documented or posted campfire rule. Current temporary fire restrictions are recorded separately.',

        'collecting_firewood' =>
            'Record the documented or posted firewood-collection rule. Do not infer permission from seeing cut wood, fire rings, or other visitors collecting wood.',

        'drone_use_legal' =>
            'Record the documented or posted drone-use rule. Do not infer permission from observed drone activity.',

        'target_shooting_allowed' =>
            'Record the documented or posted target-shooting rule. Do not infer permission from spent brass, targets, or observed shooting.',
    ];

    foreach ($help as $key => $text) {
        if (isset($fields[$key])) {
            $fields[$key]['help'] = $text;
        }
    }

    if (isset($fields['dogs_required_to_be_leashed'])) {
        $fields['dogs_required_to_be_leashed']['applicable_if'] = [[
            'field' => 'pets_allowed',
            'operator' => 'equals',
            'value' => '1',
        ]];
    }

    if (isset($fields['current_fire_restrictions_url'])) {
        $fields['current_fire_restrictions_url']['label'] =
            'Current fire restrictions URL';

        $fields['current_fire_restrictions_url']['wide'] =
            true;

        $fields['current_fire_restrictions_url']['counts_toward_completion'] =
            false;

        $fields['current_fire_restrictions_url']['help'] =
            'Link to the official current fire-restrictions page. Temporary restrictions can override the normal fire rules recorded above.';
    }

    if (isset($fields['fire_activities_conditional_details'])) {
        $fields['fire_activities_conditional_details']['label'] =
            'Fire / activity rule conditions';
    }
}
