<?php

declare(strict_types=1);

/**
 * Place operating schedule and observed closure evidence.
 *
 * Normal opening hours and gate schedules describe the Place's documented
 * operating pattern. Closure-report fields are observations/evidence only:
 * they do not become verified public closure status until moderation confirms
 * them.
 *
 * Ownership is defined only by app/place-report/flow-scopes.php.
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
        'Normally open year-round?',
        'rules',
        'tri',
        'rules.place_open_year_round',
        $base + [
            'help' =>
                'Record the normal documented operating season for the Place. This is not a temporary closure report and is separate from seasonal closure of the approach road.',
        ]
    );

    foreach ([
        'place_operating_start_month' =>
            ['Normal operating season begins: month', $months],

        'place_operating_start_day' =>
            ['Normal operating season begins: day', $days],

        'place_operating_end_month' =>
            ['Normal operating season ends: month', $months],

        'place_operating_end_day' =>
            ['Normal operating season ends: day', $days],
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
        'Normally open 24 hours?',
        'rules',
        'tri',
        'rules.place_open_24_hours',
        $base + [
            'help' =>
                'Record the normal documented daily operating schedule, not whether the Place happened to be accessible at the time of one visit.',
        ]
    );

    foreach ([
        'place_opening_time' => 'Normal daily opening time',
        'place_closing_time' => 'Normal daily closing time',
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
        'Place entrance gate present?',
        'rules',
        'tri',
        'rules.place_entrance_gate',
        $base + [
            'help' =>
                'This is the gate at the Place itself. Record a gate on the approach road under Getting There / Access Restrictions.',
        ]
    );

    $gateCondition = [
        $yes('place_entrance_gate'),
    ];

    $gateOptions = [
        'always_open' => 'Normally left open',
        'scheduled' => 'Scheduled hours',
        'code_key' => 'Code or key required',
        'staff' => 'Staff-controlled',
    ];

    $add(
        'place_gate_access_type',
        'Place gate access type',
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
        'place_gate_opening_time' => 'Normal gate opening time',
        'place_gate_closing_time' => 'Normal gate closing time',
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

    /*
     * Closure evidence deliberately does not count toward completeness.
     * These fields describe what the contributor observed or what a source
     * reported. They never directly establish verified public status.
     */
    $closure = $base + [
        'counts_toward_completion' => false,
    ];

    $statusOptions = [
        'open' => 'Appeared open',
        'temporary' => 'Appeared temporarily closed',
        'seasonal' => 'Appeared seasonally closed',
        'permanent' => 'Appeared permanently closed',
    ];

    $add(
        'place_reported_operating_status',
        'Observed Place status',
        'rules',
        'select',
        'rules.place_reported_operating_status',
        $closure + [
            'options' => $statusOptions,
            'help' =>
                'Record what you observed or what a source reported. This is closure evidence, not verified public truth. It enters the Closed Places verification queue and does not change the public marker until an administrator verifies it.',
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
        'Observed / reported closure reason',
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
        'Observed / reported closure began',
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
                'Use a reported estimate when one exists. This date never reopens the Place automatically and remains unverified until moderation.',
        ]
    );

    $add(
        'place_closure_source_url',
        'Closure / reopening evidence URL',
        'rules',
        'url',
        'rules.place_closure_source_url',
        $closure + [
            'wide' => true,
            'help' =>
                'Link to the official or best available source supporting the observed or reported status.',
        ]
    );

    $add(
        'place_closure_review_date',
        'Suggested status check date',
        'rules',
        'date',
        'rules.place_closure_review_date',
        $closure + [
            'applicable_if' => [$closedWhen],
            'help' =>
                'Optional suggestion for when staff should check the reported closure again. Administrators own the verified review schedule.',
        ]
    );

    $add(
        'place_closure_notes',
        'Closure / reopening evidence notes',
        'rules',
        'textarea',
        'rules.place_closure_notes',
        $closure + [
            'wide' => true,
            'rows' => 3,
            'help' =>
                'Add observations or source context that support the reported status. Do not present an unverified observation as an official closure determination.',
        ]
    );
}

function llama_place_report_apply_operating_metadata(
    array &$fields
): void {
    /*
     * Active layout placement is owned by flow-layout.php.
     * Keep this hook for operating-specific presentation metadata.
     */
}
