<?php

declare(strict_types=1);

/**
 * Resource and Place-owned Facility Experience contract.
 *
 * Question identity, type and choices for existing Area facility questions
 * come directly from the canonical Place Report catalog. New service-specific
 * questions live here. Both editors, moderation, validation and public views
 * consume this same manifest; do not copy lists into templates or JavaScript.
 */
function llama_resource_experience_manifest(): array
{
    require_once __DIR__ . '/place-report.php';
    require_once __DIR__ . '/resource-report-service-questions.php';
    $areaQuestions = llama_report_questions();

    $extra = llama_resource_service_question_definitions();

    // Area questions are referenced by key, not redefined. A change in
    // app/place-report/question-custom.php or questions-area-approved.php
    // flows into both the Resource and Facility Experience editors.
    $groupKeys = [
        'potable_water' => ['Water' => [
            'facility_water_year_round', 'service_water_type',
            'facility_water_test_posted', 'facility_water_flow',
            'facility_water_threaded', 'facility_accessible',
            'service_water_key_required', 'service_water_key_type',
            'facility_vehicle_fill_access', 'facility_hose_length',
            'service_water_fill_points', 'service_access_notes',
        ]],
        'water_fill' => ['Water fill' => [
            'facility_water_year_round', 'service_water_type',
            'facility_water_test_posted', 'facility_water_flow',
            'facility_water_threaded', 'service_water_key_required',
            'service_water_key_type', 'facility_vehicle_fill_access',
            'facility_hose_length', 'service_water_fill_points',
            'service_access_notes',
        ]],
        'dump_station' => ['Dump station' => [
            'facility_dump_rinse_water', 'facility_dump_separate_potable',
            'facility_dump_rv_access', 'facility_dump_fee',
            'service_dump_hose_provided', 'service_access_notes',
        ]],
        'shower' => ['Showers' => [
            'facility_shower_stalls', 'facility_timed_shower',
            'service_shower_minutes', 'facility_hot_water',
            'facility_cleanliness', 'facility_shower_pressure',
            'facility_shower_privacy', 'facility_shower_cost',
            'facility_accessible_shower', 'facility_changing_area',
            'facility_shower_storage', 'service_access_notes',
        ]],
        'laundry' => ['Laundry' => [
            'facility_washer_count', 'facility_dryer_count',
            'facility_washers_operational', 'facility_dryers_operational',
            'facility_cleanliness', 'service_laundry_machine_types',
            'facility_wash_cost', 'facility_dry_cost',
            'facility_payment_methods', 'facility_detergent',
            'service_access_notes',
        ]],
        'fuel' => ['Fuel pumps' => [
            'service_fuel_grades', 'service_fuel_pumps',
            'service_fuel_pay_at_pump', 'service_access_notes',
        ]],
        'ice' => ['Ice' => [
            'service_ice_types', 'service_ice_bag_sizes',
            'service_ice_self_serve', 'service_access_notes',
        ]],
        'propane' => ['Propane' => [
            'service_propane_method', 'service_propane_staff',
            'service_access_notes',
        ]],
        'ev_charging' => ['EV charging' => [
            'service_ev_connectors', 'service_ev_stalls', 'service_access_notes',
        ]],
        'air' => ['Air pump' => [
            'service_air_cost', 'service_air_gauge', 'service_access_notes',
        ]],
        'firewood' => ['Firewood' => [
            'service_firewood_bundle', 'service_access_notes',
        ]],
        'recycling' => ['Recycling' => [
            'service_recycling_types', 'service_access_notes',
        ]],
        'trash' => ['Trash' => [
            'service_trash_dumpster', 'service_access_notes',
        ]],
        'groceries' => ['Groceries' => [
            'service_groceries_refrigerated', 'service_access_notes',
        ]],
        'other' => ['Other service' => [
            'facility_condition', 'service_access_notes',
        ]],
    ];

    // Each restroom has independent answers. Blank = not assessed / absent;
    // do not assume men's, women's, or all-gender facilities are present.
    $restroomQuestions = [
        'facility_toilet_count', 'service_toilet_stalls',
        'service_toilet_urinals', 'facility_toilet_type',
        'facility_cleanliness', 'facility_toilet_paper',
        'facility_accessible_stall', 'facility_handwashing_sink',
        'facility_sink_running_water', 'facility_soap',
        'facility_hand_drying', 'facility_restroom_privacy',
        'facility_restroom_ventilation',
    ];
    $groupKeys['toilet'] = [
        "Men's restroom" => $restroomQuestions,
        "Women's restroom" => $restroomQuestions,
        'All-gender restroom' => $restroomQuestions,
    ];

    $manifest = [];
    foreach ($groupKeys as $service => $groups) {
        $manifest[$service] = [];
        foreach ($groups as $label => $keys) {
            $group = match ($label) {
                "Men's restroom" => 'men',
                "Women's restroom" => 'women',
                'All-gender restroom' => 'all_gender',
                default => 'general',
            };
            $questions = [];
            foreach ($keys as $key) {
                if (isset($extra[$key])) {
                    [$title, $type, $options] = array_pad($extra[$key], 3, []);
                    $field = ['label' => $title, 'type' => $type, 'options' => $options];
                } else {
                    $original = $areaQuestions[$key] ?? null;
                    if ($original === null) {
                        throw new RuntimeException('Missing canonical Area question: ' . $key);
                    }
                    $field = [
                        'label' => $original['label'] ?? $key,
                        'type' => $original['type'] ?? 'text',
                        'options' => $original['options'] ?? [],
                    ];
                }
                $questions[$key] = $field;
            }
            $manifest[$service][$group] = ['label' => $label, 'questions' => $questions];
        }
    }
    return $manifest;
}

/**
 * Verify every submitted Service, group, and question belongs to the
 * canonical Experience manifest for THIS Resource/Facility.
 *
 * A stale or mismatched question is an error, never an invitation to silently
 * discard an observation. Old published JSON is not modified by this check;
 * it applies to newly submitted and moderator-edited answers only.
 *
 * @param array<string,mixed> $raw
 * @param list<string> $enabledServices
 * @param array<string,mixed> $manifest
 */
function llama_resource_experience_assert_target_keys(
    array $raw,
    array $enabledServices,
    array $manifest
): void {
    $enabled = array_fill_keys($enabledServices, true);
    foreach ($raw as $service => $groups) {
        if (!is_string($service) || !isset($enabled[$service])
            || !isset($manifest[$service]) || !is_array($groups)) {
            throw new InvalidArgumentException(
                'Experience answers belong to a Service that is not selected. '
                . 'Review the selected Services and try again.'
            );
        }
        foreach ($groups as $group => $answers) {
            if (!is_string($group) || !isset($manifest[$service][$group])
                || !is_array($answers)) {
                throw new InvalidArgumentException(
                    'This Experience group is not available for the selected Service.'
                );
            }
            $questions = $manifest[$service][$group]['questions'];
            foreach ($answers as $key => $_value) {
                if (!is_string($key) || !isset($questions[$key])) {
                    throw new InvalidArgumentException(
                        'An Experience question does not belong to the selected Service. '
                        . 'Reload the form before saving.'
                    );
                }
            }
        }
    }
}

/** Validate and keep only answers for enabled Services and canonical keys. */
function llama_resource_experience_clean(mixed $raw, array $enabledServices): array
{
    if (!is_array($raw)) {
        throw new InvalidArgumentException('Check the Experience answers.');
    }
    $manifest = llama_resource_experience_manifest();
    llama_resource_experience_assert_target_keys($raw, $enabledServices, $manifest);
    $answers = [];
    foreach ($enabledServices as $service) {
        foreach ($manifest[$service] ?? [] as $group => $definition) {
            $submitted = $raw[$service][$group] ?? [];
            if (!is_array($submitted)) {
                throw new InvalidArgumentException('Check the ' . $service . ' Experience answers.');
            }
            foreach ($definition['questions'] as $key => $question) {
                $value = $submitted[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $type = $question['type'];
                if ($type === 'multiselect') {
                    if (!is_array($value) || !array_is_list($value)
                        || count($value) > count($question['options'])) {
                        throw new InvalidArgumentException('Invalid choice for ' . $question['label'] . '.');
                    }
                    foreach ($value as $choice) {
                        if (!is_string($choice) && !is_int($choice)) {
                            throw new InvalidArgumentException('Invalid choice for ' . $question['label'] . '.');
                        }
                    }
                    $value = array_values(array_unique(array_map('strval', $value)));
                    if (array_diff($value, array_keys($question['options']))) {
                        throw new InvalidArgumentException('Invalid choice for ' . $question['label'] . '.');
                    }
                    if ($value === []) {
                        continue;
                    }
                } else {
                    if (!is_scalar($value)) {
                        throw new InvalidArgumentException('Invalid answer for ' . $question['label'] . '.');
                    }
                    $value = trim((string) $value);
                    if ($value === '') {
                        continue;
                    }
                    if ($type === 'tri' && !in_array($value, ['yes', 'no', 'unknown'], true)) {
                        throw new InvalidArgumentException('Invalid answer for ' . $question['label'] . '.');
                    }
                    if ($type === 'select' && !array_key_exists($value, $question['options'])) {
                        throw new InvalidArgumentException('Invalid choice for ' . $question['label'] . '.');
                    }
                    if ($type === 'rating' && !in_array($value, ['1', '2', '3', '4', '5'], true)) {
                        throw new InvalidArgumentException('Rate ' . $question['label'] . ' from 1 to 5.');
                    }
                    if ($type === 'number' && (!is_numeric($value) || (float) $value < 0
                        || strlen($value) > 16 || !is_finite((float) $value))) {
                        throw new InvalidArgumentException('Enter a valid number for ' . $question['label'] . '.');
                    }
                    if ($type === 'number' && in_array($key, [
                        'facility_toilet_count', 'facility_shower_stalls', 'facility_washer_count',
                        'facility_dryer_count', 'service_toilet_stalls', 'service_toilet_urinals',
                        'service_water_fill_points', 'service_fuel_pumps', 'service_ev_stalls',
                        'service_shower_minutes',
                    ], true) && !ctype_digit($value)) {
                        throw new InvalidArgumentException('Enter a whole number for ' . $question['label'] . '.');
                    }
                    if (in_array($type, ['text', 'textarea'], true)) {
                        $value = mb_substr($value, 0, $type === 'textarea' ? 1000 : 255);
                    }
                }
                $answers[$service][$group][$key] = $value;
            }
        }
    }
    if (strlen(json_encode($answers, JSON_THROW_ON_ERROR)) > 16000) {
        throw new InvalidArgumentException('Shorten the Experience answers.');
    }
    return $answers;
}

function llama_resource_experience_label(array $question, mixed $value): string
{
    $options = $question['options'];
    if (is_array($value)) {
        return implode(', ', array_map(
            static fn (string $v): string => (string) ($options[$v] ?? $v),
            $value
        ));
    }
    return match ($question['type']) {
        'tri' => ['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown'][(string) $value] ?? '',
        'select' => (string) ($options[(string) $value] ?? $value),
        'rating' => (string) $value . ' / 5',
        default => (string) $value,
    };
}
