<?php

declare(strict_types=1);

/**
 * Conditional Place Report question families.
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
            'help' => 'Use Yes when generators are prohibited throughout this Place.',
        ]);

    $add('generator_restriction_details', 'Generator restriction details', 'rules', 'textarea',
        'rules.generator_restriction_details', $common + [
            'wide' => true,
            'rows' => 3,
            'counts_toward_completion' => false,
            'help' => 'Explain unusual generator hours, run-time limits, seasonal restrictions, or exceptions.',
        ]);
}

function llama_place_report_apply_flow_conditions(array &$fields): void
{
    $equals = static fn (string $key, string $value): array => [
        'field' => $key,
        'operator' => 'equals',
        'value' => $value,
    ];

    $generatorApplicable = $fields['generator_restrictions']['applicable_if'] ?? [];
    $notProhibited = [
        $equals('generator_restrictions', '1'),
        $equals('generator_prohibited', '0'),
    ];

    $fields['generator_prohibited']['applicable_if'] = array_merge(
        $generatorApplicable,
        [$equals('generator_restrictions', '1')]
    );

    $fields['generator_quiet_hours']['applicable_if'] = array_merge(
        $generatorApplicable,
        $notProhibited
    );

    $fields['generator_quiet_hours_begin']['applicable_if'] = array_merge(
        $notProhibited,
        [$equals('generator_quiet_hours', '1')]
    );

    $fields['generator_quiet_hours_end']['applicable_if'] = array_merge(
        $notProhibited,
        [$equals('generator_quiet_hours', '1')]
    );

    $fields['generator_run_restrictions']['applicable_if'] = array_merge(
        $generatorApplicable,
        $notProhibited
    );

    $fields['generator_max_run_hours']['applicable_if'] = array_merge(
        $notProhibited,
        [$equals('generator_run_restrictions', '1')]
    );

    $fields['generator_restriction_details']['applicable_if'] = [
        $equals('generator_restrictions', '1'),
    ];

    /*
     * The previously proposed generator-free-area/loop question is deliberately
     * not registered. Generator rules remain Place-wide here; more restrictive
     * individual-site rules belong under Site → Rules & Restrictions.
     */

    $fields['current_fire_restrictions_url']['flow_independent_of'] = [
        'campfire_allowed',
    ];

    $fields['designated_sites_only']['flow_independent_of'] = [
        'dispersed_camping_allowed',
    ];
}
