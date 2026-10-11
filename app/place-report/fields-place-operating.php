<?php
declare(strict_types=1);

/**
 * Place-level operating hours and closure information.
 * These fields describe the Place, not approach-road gate restrictions.
 * Closure status entered here is NOT itself a published closure action:
 * map markers and Closed Places reviews require an authorized workflow.
 */
function llama_place_report_add_operating_fields(callable $add): void
{
    $base = [
        'allow_unknown' => true,
        'points_categories' => ['seasons_rules_services'],
        'report_scopes' => ['place'],
        'report_scope_group' => 'place',
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
    for ($m = 1; $m <= 12; $m++) {
        $months[(string) $m] = date('F', mktime(0, 0, 0, $m, 1));
    }
    $days = [];
    for ($d = 1; $d <= 31; $d++) {
        $days[(string) $d] = (string) $d;
    }
    $hours = [];
    for ($h = 0; $h < 24; $h++) {
        $key = sprintf('%02d:00', $h);
        $hours[$key] = date('g A', strtotime('2000-01-01 ' . $key));
    }

    $add('place_open_year_round', 'Open year-round?', 'rules', 'tri',
        'rules.place_open_year_round', $base);

    foreach ([
        'place_operating_start_month' => ['Operating season begins: month', $months],
        'place_operating_start_day' => ['Operating season begins: day', $days],
        'place_operating_end_month' => ['Operating season ends: month', $months],
        'place_operating_end_day' => ['Operating season ends: day', $days],
    ] as $key => [$label, $options]) {
        $add($key, $label, 'rules', 'select', 'rules.' . $key,
            $base + ['options' => $options, 'applicable_if' => [$no('place_open_year_round')]]);
    }

    $add('place_open_24_hours', 'Open 24 hours?', 'rules', 'tri',
        'rules.place_open_24_hours', $base);
    foreach (['place_opening_time' => 'Daily opening time',
              'place_closing_time' => 'Daily closing time'] as $key => $label) {
        $add($key, $label, 'rules', 'select', 'rules.' . $key,
            $base + ['options' => $hours, 'applicable_if' => [$no('place_open_24_hours')]]);
    }

    $add('place_entrance_gate', 'Entrance gate present?', 'rules', 'tri',
        'rules.place_entrance_gate', $base);
    $gateCondition = [$yes('place_entrance_gate')];
    $gateOptions = ['always_open' => 'Always open',
        'scheduled' => 'Scheduled hours', 'code_key' => 'Code or key required',
        'staff' => 'Staff-controlled'];
    $add('place_gate_access_type', 'Entrance gate access type', 'rules', 'select',
        'rules.place_gate_access_type', $base + [
            'options' => $gateOptions, 'applicable_if' => $gateCondition,
        ]);
    $gateHours = array_merge($gateCondition, [[
        'field' => 'place_gate_access_type', 'operator' => 'equals', 'value' => 'scheduled',
    ]]);
    foreach (['place_gate_opening_time' => 'Gate opening time',
              'place_gate_closing_time' => 'Gate closing time'] as $key => $label) {
        $add($key, $label, 'rules', 'select', 'rules.' . $key,
            $base + ['options' => $hours, 'applicable_if' => $gateHours]);
    }

    // Closure observations are saved for review; they do not alter map status.
    // These are informational until authenticated closure moderation is wired.
    $closure = $base + ['counts_toward_completion' => false];
    $statusOptions = [
        'open' => 'Open', 'temporary' => 'Temporarily closed',
        'seasonal' => 'Seasonally closed', 'permanent' => 'Permanently closed',
    ];
    $add('place_reported_operating_status', 'Reported operating status', 'rules',
        'select', 'rules.place_reported_operating_status',
        $closure + ['options' => $statusOptions,
            'help' => 'A submitted closure is pending verification, not automatically published.']);
    $closedWhen = ['field' => 'place_reported_operating_status',
        'operator' => 'in', 'value' => ['temporary', 'seasonal', 'permanent']];
    $temporaryWhen = ['field' => 'place_reported_operating_status',
        'operator' => 'in', 'value' => ['temporary', 'seasonal']];
    $add('place_closure_reason', 'Closure reason', 'rules', 'select',
        'rules.place_closure_reason', $closure + [
            'options' => [
                'maintenance' => 'Maintenance', 'construction' => 'Construction',
                'weather' => 'Weather', 'fire' => 'Fire', 'flooding' => 'Flooding',
                'road_damage' => 'Road damage', 'administrative' => 'Administrative',
                'other' => 'Other',
            ], 'applicable_if' => [$closedWhen],
        ]);
    $add('place_closure_began', 'Closure began', 'rules', 'date',
        'rules.place_closure_began', $closure + ['applicable_if' => [$closedWhen]]);
    $add('place_expected_reopening', 'Expected reopening date', 'rules', 'date',
        'rules.place_expected_reopening', $closure + [
            'applicable_if' => [$temporaryWhen],
            'help' => 'Tentative; a review date never reopens the Place automatically.',
        ]);
    $add('place_closure_source_url', 'Closure verification URL', 'rules', 'url',
        'rules.place_closure_source_url', $closure + [
            'wide' => true, 'applicable_if' => [$closedWhen],
        ]);
    $add('place_closure_review_date', 'Next closure review date', 'rules', 'date',
        'rules.place_closure_review_date', $closure + ['applicable_if' => [$closedWhen]]);
    $add('place_closure_notes', 'Closure notes', 'rules', 'textarea',
        'rules.place_closure_notes', $closure + [
            'wide' => true, 'rows' => 3, 'applicable_if' => [$closedWhen],
        ]);
    // Verified-at must be set by the server on an authorized review, not editable.
}

function llama_place_report_apply_operating_metadata(array &$fields): void
{
    foreach ($fields as $key => &$field) {
        if (!str_starts_with((string) $key, 'place_open_')
            && !str_starts_with((string) $key, 'place_operating_')
            && !str_starts_with((string) $key, 'place_entrance_gate')
            && !str_starts_with((string) $key, 'place_gate_')
            && !str_starts_with((string) $key, 'place_reported_operating_')
            && !str_starts_with((string) $key, 'place_closure_')
            && $key !== 'place_expected_reopening'
            && !in_array($key, ['place_opening_time', 'place_closing_time'], true)) {
            continue;
        }
        $field['report_scopes'] = ['place'];
        $field['report_scope_group'] = 'place';
        $field['layout_section'] = 'place';
        $field['layout_group'] = 'operating_closures';
    }
    unset($field);
}
