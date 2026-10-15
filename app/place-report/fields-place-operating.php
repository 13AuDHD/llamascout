<?php

declare(strict_types=1);

/**
 * Place operating hours and closure observations.
 *
 * Ownership is intentionally not defined here. app/place-report/flow-scopes.php
 * is the single canonical source for Place / Area / Site ownership.
 *
 * A reported closure/reopening is evidence for the administrative closure
 * workflow. It enters the Closed Places verification queue but never changes
 * the public closure marker until an authorized administrator verifies it.
 */
function llama_place_report_add_operating_fields(callable $add): void
{
    $base = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
    ];

    $yes = static fn (string $key): array => [
        'field' => $key,
        'operator' => 'equals',
        'value' => '1',
    ];

    $no = static fn (string $key): array => [
        'field' => $key,
        'operator' => 'equals',
        'value' => '0',
    ];

    $months = [];

    for ($month = 1; $month <= 12; $month++) {
        $months[(string) $month] =
            date('F', mktime(0, 0, 0, $month, 1));
    }

    $days = [];

    for ($day = 1; $day <= 31; $day++) {
        $days[(string) $day] =
            (string) $day;
    }

    $hours = [];

    for ($hour = 0; $hour < 24; $hour++) {
        $key = sprintf('%02d:00', $hour);

        $hours[$key] =
            date('g A', strtotime('2000-01-01 ' . $key));
    }

    $add(
        'place_open_year_round',
        'Open year-round?',
        'rules',
        'tri',
        'rules.place_open_year_round',
        $base + [
            'help' =>
                'This describes the Place operating season, not seasonal '
                . 'closure of the approach road.',
        ]
    );

    foreach ([
        'place_operating_start_month' =>
            ['Operating season begins: month', $months],

        'place_operating_start_day' =>
            ['Operating season begins: day', $days],

        'place_operating_end_month' =>
            ['Operating season ends: month', $months],

        'place_operating_end_day' =>
            ['Operating season ends: day', $days],
    ] as $key => [$label, $options]) {
        $add(
            $key,
            $label,
            'rules',
            'select',
            'rules.' . $key,
            $base + [
                'options' => $options,
                'applicable_if' => [$no('place_open_year_round')],
            ]
        );
    }

    $add(
        'place_open_24_hours',
        'Open 24 hours?',
        'rules',
        'tri',
        'rules.place_open_24_hours',
        $base
    );

    foreach ([
        'place_opening_time' => 'Daily opening time',
        'place_closing_time' => 'Daily closing time',
    ] as $key => $label) {
        $add(
            $key,
            $label,
            'rules',
            'select',
            'rules.' . $key,
            $base + [
                'options' => $hours,
                'applicable_if' => [$no('place_open_24_hours')],
            ]
        );
    }

    $add(
        'place_entrance_gate',
        'Property entrance gate present?',
        'rules',
        'tri',
        'rules.place_entrance_gate',
        $base + [
            'help' =>
                'This is the gate at the Place itself. Record an access-road '
                . 'gate under Getting There / Access Restrictions.',
        ]
    );

    $gateCondition = [
        $yes('place_entrance_gate'),
    ];

    $gateOptions = [
        'always_open' => 'Always open',
        'scheduled' => 'Scheduled hours',
        'code_key' => 'Code or key required',
        'staff' => 'Staff-controlled',
    ];

    $add(
        'place_gate_access_type',
        'Property gate access type',
        'rules',
        'select',
        'rules.place_gate_access_type',
        $base + [
            'options' => $gateOptions,
            'applicable_if' => $gateCondition,
        ]
    );

    $gateHours = array_merge(
        $gateCondition,
        [[
            'field' => 'place_gate_access_type',
            'operator' => 'equals',
            'value' => 'scheduled',
        ]]
    );

    foreach ([
        'place_gate_opening_time' => 'Property gate opening time',
        'place_gate_closing_time' => 'Property gate closing time',
    ] as $key => $label) {
        $add(
            $key,
            $label,
            'rules',
            'select',
            'rules.' . $key,
            $base + [
                'options' => $hours,
                'applicable_if' => $gateHours,
            ]
        );
    }

    $closure = $base + [
        'counts_toward_completion' => false,
    ];

    $statusOptions = [
        'open' => 'Open',
        'temporary' => 'Temporarily closed',
        'seasonal' => 'Seasonally closed',
        'permanent' => 'Permanently closed',
    ];

    $add(
        'place_reported_operating_status',
        'Observed operating status',
        'rules',
        'select',
        'rules.place_reported_operating_status',
        $closure + [
            'options' => $statusOptions,
            'help' =>
                'A reported closure or reopening is sent to the Closed Places '
                . 'verification queue. It does not change the public marker '
                . 'until an administrator verifies it.',
        ]
    );

    $closedWhen = [
        'field' => 'place_reported_operating_status',
        'operator' => 'in',
        'value' => ['temporary', 'seasonal', 'permanent'],
    ];

    $temporaryWhen = [
        'field' => 'place_reported_operating_status',
        'operator' => 'in',
        'value' => ['temporary', 'seasonal'],
    ];

    $add(
        'place_closure_reason',
        'Observed closure reason',
        'rules',
        'select',
        'rules.place_closure_reason',
        $closure + [
            'options' => [
                'unknown' => 'Unknown',
                'maintenance' => 'Maintenance',
                'construction' => 'Construction',
                'weather' => 'Weather',
                'fire' => 'Fire',
                'flooding' => 'Flooding',
                'road_damage' => 'Road damage',
                'administrative' => 'Administrative',
                'other' => 'Other',
            ],
            'applicable_if' => [$closedWhen],
        ]
    );

    $add(
        'place_closure_began',
        'Observed closure began',
        'rules',
        'date',
        'rules.place_closure_began',
        $closure + [
            'applicable_if' => [$closedWhen],
        ]
    );

    $add(
        'place_expected_reopening',
        'Reported expected reopening',
        'rules',
        'date',
        'rules.place_expected_reopening',
        $closure + [
            'applicable_if' => [$temporaryWhen],
            'help' =>
                'Use an officially reported estimate when one exists. '
                . 'This date never reopens the Place automatically.',
        ]
    );

    $add(
        'place_closure_source_url',
        'Closure / reopening source URL',
        'rules',
        'url',
        'rules.place_closure_source_url',
        $closure + [
            'wide' => true,
            'help' =>
                'Link to the official or best available source supporting '
                . 'the observed status.',
        ]
    );

    $add(
        'place_closure_review_date',
        'Suggested check-back date',
        'rules',
        'date',
        'rules.place_closure_review_date',
        $closure + [
            'applicable_if' => [$closedWhen],
            'help' =>
                'Optional suggestion for when staff should check the closure '
                . 'again. Administrators set the verified review schedule.',
        ]
    );

    $add(
        'place_closure_notes',
        'Closure / reopening notes',
        'rules',
        'textarea',
        'rules.place_closure_notes',
        $closure + [
            'wide' => true,
            'rows' => 3,
            'help' =>
                'Add useful context that is not captured by the status, '
                . 'reason, dates, or source.',
        ]
    );
}

function llama_place_report_apply_operating_metadata(array &$fields): void
{
    foreach ($fields as $key => &$field) {
        if (
            !str_starts_with((string) $key, 'place_open_')
            && !str_starts_with((string) $key, 'place_operating_')
            && !str_starts_with((string) $key, 'place_entrance_gate')
            && !str_starts_with((string) $key, 'place_gate_')
            && !str_starts_with((string) $key, 'place_reported_operating_')
            && !str_starts_with((string) $key, 'place_closure_')
            && $key !== 'place_expected_reopening'
            && !in_array(
                $key,
                [
                    'place_opening_time',
                    'place_closing_time',
                ],
                true
            )
        ) {
            continue;
        }

        $field['layout_section'] = 'place';
        $field['layout_group'] = 'operating_closures';
    }

    unset($field);
}
