<?php

declare(strict_types=1);

/**
 * Phase 3B: first live dependency family.
 *
 * Keep the existing registered answers, including Permit (2), intact.
 * New answers are stored in the existing rules payload. Applicability is
 * shared by form visibility, server validation and completion calculations.
 */
function llama_place_report_add_flow_condition_fields(array &$fields, callable $add): void
{
    $common = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ];

    $add('generator_run_restrictions', 'Generator run-time limits?', 'rules', 'tri',
        'rules.generator_run_restrictions', $common + [
            'help' => 'Choose Yes when generators may run only for a limited number of hours at a time.',
        ]);

    $add('generator_max_run_hours', 'Maximum generator run time', 'rules', 'select',
        'rules.generator_max_run_hours', $common + [
            'options' => [
                '1' => '1 hour',
                '2' => '2 hours',
                '3' => '3 hours',
                '4' => '4 hours',
                '5' => '5 hours',
                '6' => '6 hours',
                '8' => '8 hours',
                'other' => 'Other / varies',
            ],
        ]);

    $add('generator_prohibited', 'Generators prohibited?', 'rules', 'tri',
        'rules.generator_prohibited', $common + [
            'help' => 'Use Yes for a full prohibition in this reporting scope. Generator-free loops should be recorded at the Area level when available.',
        ]);

    $add('generator_restriction_details', 'Generator restriction details', 'rules', 'textarea',
        'rules.generator_restriction_details', $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'help' => 'Explain unusual time limits, generator-free loops, seasonal restrictions, and exceptions.',
        ]);
}

function llama_place_report_apply_flow_conditions(array &$fields): void
{
    $equals = static fn (string $key, string $value): array => [
        'field' => $key,
        'operator' => 'equals',
        'value' => $value,
    ];

    // Keep existing Place-type limitations and add actual dependencies.
    $generatorApplicable = $fields['generator_restrictions']['applicable_if'] ?? [];
    $quietHoursNo = [
        $equals('generator_restrictions', '1'),
        $equals('generator_quiet_hours', '0'),
    ];

    $fields['generator_run_restrictions']['applicable_if'] = array_merge(
        $generatorApplicable, $quietHoursNo
    );
    $fields['generator_max_run_hours']['applicable_if'] = [
        $equals('generator_run_restrictions', '1'),
    ];
    $fields['generator_prohibited']['applicable_if'] = [
        $equals('generator_restrictions', '1'),
        $equals('generator_quiet_hours', '0'),
        $equals('generator_run_restrictions', '0'),
    ];
    $fields['generator_restriction_details']['applicable_if'] = [
        $equals('generator_restrictions', '1'),
    ];

    // Independently useful regardless of the campsite's normal fire policy.
    // Retain any original Place-type rules but never gate on campfire_allowed.
    $fields['current_fire_restrictions_url']['flow_independent_of'] = [
        'campfire_allowed',
    ];

    // A 'No' or 'Unknown' to dispersed camping must not hide information
    // about designated spaces. Existing place-type applicability is retained.
    $fields['designated_sites_only']['flow_independent_of'] = [
        'dispersed_camping_allowed',
    ];
}
