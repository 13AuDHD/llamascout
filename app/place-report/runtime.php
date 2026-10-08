<?php

declare(strict_types=1);

/* Answer state, parsing, completion, validation, persistence, and publishing. */

function llama_place_report_quick_warnings(array $data): array
{
    $fields = llama_place_report_fields();
    $warnings = [];

    $applicabilityInput =
        llama_place_report_scoring_input_from_data(
            $data
        );

    $state = static function (
        string $key
    ) use (
        $data,
        $applicabilityInput
    ): string {
        if (
            !llama_place_report_question_applicable(
                $applicabilityInput,
                $key
            )
        ) {
            return 'unanswered';
        }

        return llama_place_report_answer_state(
            $data,
            $key
        );
    };

    $value = static function (string $key) use ($data, $fields): mixed {
        $field = $fields[$key] ?? null;

        if (!is_array($field)) {
            return null;
        }

        return llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );
    };

    $isYes = static fn (mixed $raw): bool =>
        in_array($raw, [true, 1, '1'], true);

    $isNo = static fn (mixed $raw): bool =>
        in_array($raw, [false, 0, '0'], true);

    $add = static function (
        string $key,
        string $label,
        string $iconKey
    ) use (&$warnings, $fields): void {
        $warnings[$key] = [
            'label' => $label,
            'icon' => llama_place_report_field_icon(
                $iconKey,
                $fields[$iconKey] ?? []
            ),
        ];
    };

    /* Amenities */
    if ($state('amenity_none') === 'answered' && $isYes($value('amenity_none'))) {
        $add('amenity_none', 'No amenities', 'amenity_none');
    }

    /* Connectivity */
    if (
        $state('connectivity_overall') === 'answered'
        && is_numeric($value('connectivity_overall'))
        && (int) $value('connectivity_overall') === 1
    ) {
        $add('warning_no_cell_service', 'No cell service', 'warning_no_cell_service');
    }

    /* Safety answers that become warnings when the answer is No. */
    foreach ([
        'felt_safe_daytime' => ['Did not feel safe during the day', 'felt_safe_daytime'],
        'emergency_access' => ['No emergency vehicle access', 'emergency_access'],
    ] as $key => [$label, $iconKey]) {
        if ($state($key) === 'answered' && $isNo($value($key))) {
            $add($key . '_warning', $label, $iconKey);
        }
    }

    /* Safety answers that become warnings when the answer is Yes. */
    foreach ([
        'flash_flood_risk' => 'Flash-flood risk',
        'wildfire_risk' => 'Wildfire risk',
        'fall_hazard' => 'Trip/Fall Hazard',
        'cliff_exposure' => 'Cliff exposure',
        'rockfall_risk' => 'Rockfall risk',
        'wildlife_risk' => 'Wildlife risk',
        'traffic_hazard' => 'Traffic hazard',
        'warning_possible_downed_trees' => 'Downed trees possible at the campsite',
        'warning_passing_vehicle_dust' => 'Passing vehicle dust',
        'warning_motorized_recreation_traffic' => 'Motorized recreation traffic',
        'warning_blind_turn_traffic_nearby' => 'Blind-turn traffic nearby',
    ] as $key => $label) {
        if ($state($key) === 'answered' && $isYes($value($key))) {
            $add($key, $label, $key);
        }
    }

    /* Site + vehicle answers */
    if (
        $state('tent_camping_suitable') === 'answered'
        && $isNo($value('tent_camping_suitable'))
    ) {
        $add(
            'warning_no_tent_camping_derived',
            'No tent camping',
            'warning_no_tent_camping_derived'
        );
    }

    if (
        $state('levelness') === 'answered'
        && is_numeric($value('levelness'))
        && (int) $value('levelness') <= 2
    ) {
        $add(
            'warning_leveling_required_derived',
            'Leveling may be required',
            'warning_leveling_required_derived'
        );
    }

    /* Sensory privacy */
    foreach (['daytime_privacy', 'nighttime_privacy'] as $privacyKey) {
        if (
            $state($privacyKey) === 'answered'
            && is_numeric($value($privacyKey))
            && (int) $value($privacyKey) === 1
        ) {
            $add('warning_no_privacy', 'No privacy', 'warning_no_privacy');
            break;
        }
    }

    /* Road exposure */
    if (
        $state('road_exposure') === 'answered'
        && is_numeric($value('road_exposure'))
        && (int) $value('road_exposure') === 5
    ) {
        $add(
            'warning_high_road_exposure',
            'Highly exposed to road',
            'warning_high_road_exposure'
        );
    }

    /* Vehicle and trailer fit */
    if (
        $state('max_vehicle_length_feet') === 'answered'
        && is_numeric($value('max_vehicle_length_feet'))
        && (float) $value('max_vehicle_length_feet') <= 25
    ) {
        $add(
            'warning_limited_vehicle_length_derived',
            'Limited vehicle length',
            'warning_limited_vehicle_length_derived'
        );
    }

    if ($state('max_trailer_length_feet') === 'answered') {
        $maxTrailer = $value('max_trailer_length_feet');

        if ((string) $maxTrailer === 'not-recommended') {
            $add(
                'warning_limited_trailer_length',
                'Trailers not recommended',
                'warning_limited_trailer_length'
            );
        } elseif (
            is_numeric($maxTrailer)
            && (float) $maxTrailer <= 25
        ) {
            $add(
                'warning_limited_trailer_length',
                'Limited trailer length',
                'warning_limited_trailer_length'
            );
        }
    }

    return $warnings;
}

function llama_place_report_get_path(array $data, string $path): mixed
{
    $value = $data;

    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }

        $value = $value[$part];
    }

    return $value;
}

function llama_place_report_set_path(array &$data, string $path, mixed $value): void
{
    $parts = explode('.', $path);
    $cursor =& $data;

    foreach ($parts as $index => $part) {
        if ($index === count($parts) - 1) {
            $cursor[$part] = $value;
            return;
        }

        if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
            $cursor[$part] = [];
        }
        $cursor =& $cursor[$part];
    }
}

function llama_place_report_unknown_fields(array $data): array
{
    $raw = $data['_answer_state'] ?? [];

    if (!is_array($raw)) {
        return [];
    }

    $unknown = [];

    foreach ($raw as $key => $value) {
        if (is_int($key)) {
            $field = trim((string) $value);

            if ($field !== '') {
                $unknown[$field] = true;
            }

            continue;
        }

        if (
            (string) $value === 'unknown'
            || $value === true
            || $value === 1
        ) {
            $unknown[(string) $key] = true;
        }
    }

    return array_keys($unknown);
}

function llama_place_report_multiselect_values(mixed $value): array
{
    if (is_string($value)) {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            return [];
        }
    }

    if (!is_array($value)) {
        return [];
    }

    $values = [];

    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }

        $item = trim((string) $item);

        if ($item !== '') {
            $values[$item] = true;
        }
    }

    return array_keys($values);
}

function llama_place_report_answer_state(array $data, string $fieldKey): string
{
    if (
        in_array(
            $fieldKey,
            llama_place_report_unknown_fields($data),
            true
        )
    ) {
        return 'unknown';
    }

    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return 'unanswered';
    }

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    if ((string) $field['type'] === 'checkbox') {
        return $value === true || $value === 1 || $value === '1'
            ? 'answered'
            : 'unanswered';
    }

    if ((string) $field['type'] === 'multiselect') {
        return llama_place_report_multiselect_values($value)
            ? 'answered'
            : 'unanswered';
    }

    if ($value === null || $value === '') {
        return 'unanswered';
    }

    return 'answered';
}

function llama_place_report_form_value_from_data(array $data, string $fieldKey): mixed
{
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return null;
    }

    if (llama_place_report_answer_state($data, $fieldKey) === 'unknown') {
        return llama_place_report_unknown_token();
    }

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    if ((string) $field['type'] === 'multiselect') {
        return llama_place_report_multiselect_values($value);
    }

    if ((string) $field['type'] === 'checkbox') {
        return $value ? '1' : '';
    }

    if (
        in_array(
            (string) $field['type'],
            ['tri', 'permission', 'rating'],
            true
        )
        && $value === null
    ) {
        return llama_place_report_unanswered_token();
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return $value;
}

function llama_place_report_form_input_from_data(array $data): array
{
    $input = [];

    foreach (llama_place_report_fields() as $key => $field) {
        $value =
            llama_place_report_form_value_from_data(
                $data,
                $key
            );

        if ((string) $field['type'] === 'checkbox') {
            if ($value === '1') {
                $input[$key] = '1';
            }

            continue;
        }

        if ((string) $field['type'] === 'multiselect') {
            $input[$key] = is_array($value)
                ? array_values($value)
                : [];
            continue;
        }

        if ($value !== null) {
            $input[$key] = (string) $value;
        }
    }

    return $input;
}

function llama_place_report_parse_field(
    array $field,
    mixed $raw,
    array &$unknownFields
): mixed {
    $key = (string) $field['key'];
    $type = (string) $field['type'];

    if ($raw === llama_place_report_unknown_token()) {
        if (!empty($field['allow_unknown'])) {
            $unknownFields[$key] = true;
        }

        return null;
    }

    if ($raw === llama_place_report_unanswered_token()) {
        return null;
    }

    if ($type === 'checkbox') {
        return (string) $raw === '1';
    }

    if ($type === 'multiselect') {
        $options = (array) ($field['options'] ?? []);
        $rawValues = is_array($raw)
            ? $raw
            : (($raw === null || $raw === '') ? [] : [$raw]);
        $values = [];

        foreach ($rawValues as $rawValue) {
            if (!is_scalar($rawValue)) {
                continue;
            }

            $cleanValue = trim((string) $rawValue);

            if (
                $cleanValue !== ''
                && array_key_exists($cleanValue, $options)
            ) {
                $values[$cleanValue] = true;
            }
        }

        return array_keys($values);
    }

    if ($type === 'tri') {
        if ($raw === '' || $raw === null) {
            return null;
        }

        return (string) $raw === '1';
    }

    if ($type === 'permission') {
        if ($raw === '' || $raw === null) {
            return null;
        }

        $value = (int) $raw;

        return in_array($value, [0, 1, 2], true)
            ? $value
            : null;
    }

    if ($type === 'rating') {
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            return null;
        }

        $value = (int) $raw;

        return $value >= 1 && $value <= 5
            ? $value
            : null;
    }

    if ($type === 'number') {
        $clean = trim((string) $raw);

        if ($clean === '') {
            return null;
        }

        if (!is_numeric($clean)) {
            throw new InvalidArgumentException(
                (string) $field['label']
                . ' must be numeric.'
            );
        }

        $number =
            str_contains($clean, '.')
                ? (float) $clean
                : (int) $clean;

        if (
            isset($field['min'])
            && $number < (float) $field['min']
        ) {
            return null;
        }

        if (
            isset($field['max'])
            && $number > (float) $field['max']
        ) {
            return null;
        }

        return $number;
    }

    $clean = trim((string) $raw);

    if ($clean === '') {
        return null;
    }

    if ($type === 'select') {
        $options =
            (array) ($field['options'] ?? []);

        /*
         * Place type is the one select that has historically been
         * normalized server-side. Preserve that protection.
         */
        if (
            $key === 'type'
            && !array_key_exists($clean, $options)
        ) {
            return 'other';
        }
    }

    $max = (int) ($field['maxlength'] ?? 5000);

    return mb_substr($clean, 0, $max);
}

function llama_place_report_build_data(
    array $input,
    ?array $baseData = null
): array {
    $data =
        is_array($baseData)
            ? $baseData
            : [
                'details' => [],
                'amenities' => [],
                'connectivity' => [],
                'sensory' => [
                    'daytime' => [],
                    'nighttime' => [],
                    'details' => [],
                ],
                'rules' => [],
                'experience' => [],
                'photos' => [],
            ];

    $unknownFields = [];

    foreach (llama_place_report_fields() as $key => $field) {
        $type = (string) $field['type'];

        if (!array_key_exists($key, $input)) {
            if ($baseData === null && $type === 'checkbox') {
                llama_place_report_set_path(
                    $data,
                    (string) $field['storage'],
                    false
                );
            }

            continue;
        }

        $value =
            llama_place_report_parse_field(
                $field,
                $input[$key],
                $unknownFields
            );

        llama_place_report_set_path(
            $data,
            (string) $field['storage'],
            $value
        );
    }

    $noAmenities =
        !empty(
            $data['details']['warning_no_amenities']
            ?? false
        );

    if ($noAmenities) {
        foreach ((array) ($data['amenities'] ?? []) as $amenity => $_) {
            $data['amenities'][$amenity] = false;
        }
    } else {
        foreach ((array) ($data['amenities'] ?? []) as $value) {
            if ($value) {
                $data['details']['warning_no_amenities'] = false;
                break;
            }
        }
    }

    if (isset($data['details']) && is_array($data['details'])) {
        $data['details']['road_difficulty'] =
            $data['details']['road_overall_difficulty']
            ?? null;
    }

    if (isset($data['rules']) && is_array($data['rules'])) {
        $data['rules']['recommended_travel_season'] =
            $data['rules']['best_months']
            ?? null;
    }

    /*
     * Derived experience recommendations intentionally mirror the paired
     * rating instead of asking the contributor for the same judgment twice.
     * Keep the stored compatibility fields current for downstream consumers.
     */
    if (!isset($data['experience']) || !is_array($data['experience'])) {
        $data['experience'] = [];
    }

    $deriveRecommendation =
        static function (mixed $rating): ?bool {
            if (!is_numeric($rating)) {
                return null;
            }

            $rating = (int) $rating;

            if ($rating >= 4) {
                return true;
            }

            if ($rating <= 2 && $rating >= 1) {
                return false;
            }

            return null;
        };

    foreach (
        [
            'recommended_overnight_stop' => 'overnight_comfort',
            'recommended_quiet_evening' => 'quiet_evening',
            'recommended_extended_stay' => 'extended_stay_comfort',
            'recommended_sensory_retreat' => 'sensory_retreat',
            'recommended_stargazing' => 'stargazing',
            'recommended_remote_work' => 'remote_work',
        ]
        as $derivedKey => $ratingKey
    ) {
        $data['experience'][$derivedKey] =
            $deriveRecommendation(
                $data['experience'][$ratingKey]
                ?? null
            );
    }

    /*
     * Preserve existing explicit Unknown answers when a moderator edits
     * only some fields. Then apply the current submitted state over them.
     */
    if ($baseData !== null) {
        foreach (llama_place_report_unknown_fields($baseData) as $existingUnknown) {
            if (!array_key_exists($existingUnknown, $input)) {
                $unknownFields[$existingUnknown] = true;
            }
        }
    }

    $data['_answer_state'] =
        array_values(
            array_keys($unknownFields)
        );

    $name = trim((string) ($data['name'] ?? ''));

    if ($name === '') {
        throw new InvalidArgumentException(
            'Place name is required.'
        );
    }

    $lat = $data['latitude'] ?? null;
    $lng = $data['longitude'] ?? null;

    if (($lat === null) !== ($lng === null)) {
        throw new InvalidArgumentException(
            'Enter both latitude and longitude, or leave both blank.'
        );
    }

    return $data;
}

function llama_place_report_scoring_input_from_data(array $data): array
{
    $input =
        llama_place_report_form_input_from_data(
            $data
        );

    foreach ($input as $key => $value) {
        if ($value === llama_place_report_unanswered_token()) {
            $input[$key] = '';
        }
    }

    return $input;
}

function llama_place_report_is_answered_input(
    array $input,
    string $fieldKey
): bool {
    if (!array_key_exists($fieldKey, $input)) {
        return false;
    }

    $value = $input[$fieldKey];

    if (is_array($value)) {
        foreach ($value as $item) {
            if (
                is_scalar($item)
                && trim((string) $item) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    if ($value === llama_place_report_unanswered_token()) {
        return false;
    }

    return trim((string) $value) !== '';
}

/*
 * =========================================================
 * PLACE REPORT COMPLETION MODEL
 *
 * Completion is intentionally separate from Scout points.
 * These helpers derive applicability and answer state from
 * the canonical Place Report field registry every time.
 * =========================================================
 */


function llama_place_report_normalized_text_length(
    mixed $value
): int {
    if (!is_scalar($value) && $value !== null) {
        return 0;
    }

    $text =
        preg_replace(
            '/\\s+/u',
            ' ',
            trim((string) $value)
        );

    if (!is_string($text)) {
        return 0;
    }

    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }

    $matched =
        preg_match_all(
            '/./us',
            $text,
            $characters
        );

    return $matched !== false
        ? count($characters[0])
        : strlen($text);
}

function llama_place_report_applicability_rule_matches(
    array $input,
    array $rule
): bool {
    $operator =
        (string) (
            $rule['operator']
            ?? 'equals'
        );

    if (
        in_array(
            $operator,
            [
                'any',
                'all',
            ],
            true
        )
    ) {
        $nestedRules =
            array_values(
                array_filter(
                    (array) (
                        $rule['rules']
                        ?? []
                    ),
                    'is_array'
                )
            );

        if (!$nestedRules) {
            return false;
        }

        if ($operator === 'any') {
            foreach ($nestedRules as $nestedRule) {
                if (
                    llama_place_report_applicability_rule_matches(
                        $input,
                        $nestedRule
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        foreach ($nestedRules as $nestedRule) {
            if (
                !llama_place_report_applicability_rule_matches(
                    $input,
                    $nestedRule
                )
            ) {
                return false;
            }
        }

        return true;
    }

    $dependsOn =
        trim(
            (string) (
                $rule['field']
                ?? ''
            )
        );

    if ($dependsOn === '') {
        return false;
    }

    /*
     * A stored Yes on an inapplicable parent must not activate children.
     * Example: generator restrictions and quiet hours from an older
     * report must not become active when overnight use is disallowed.
     */
    if (
        $dependsOn !== 'type'
        && isset(llama_place_report_fields()[$dependsOn])
        && !llama_place_report_question_applicable(
            $input,
            $dependsOn
        )
    ) {
        return false;
    }

    $actual =
        $input[$dependsOn]
        ?? null;

    $expected =
        $rule['value']
        ?? null;

    $actualValues =
        is_array($actual)
            ? array_map('strval', $actual)
            : [(string) ($actual ?? '')];

    $expectedValues =
        array_map(
            'strval',
            (array) $expected
        );

    $primaryExpected =
        $expectedValues[0]
        ?? '';

    $specialFalseValues = [
        '',
        '0',
        'false',
        llama_place_report_unanswered_token(),
        llama_place_report_unknown_token(),
    ];

    $specialFalseLookup =
        array_map(
            'strtolower',
            $specialFalseValues
        );

    $actualTruthy =
        count($actualValues) > 0
        && array_filter(
            $actualValues,
            static fn (string $value): bool =>
                !in_array(
                    strtolower($value),
                    $specialFalseLookup,
                    true
                )
        ) !== [];

    return match ($operator) {
        'equals' =>
            in_array(
                $primaryExpected,
                $actualValues,
                true
            ),

        'not_equals' =>
            !in_array(
                $primaryExpected,
                $actualValues,
                true
            ),

        'in' =>
            array_intersect(
                $actualValues,
                $expectedValues
            ) !== [],

        'not_in' =>
            array_intersect(
                $actualValues,
                $expectedValues
            ) === [],

        'truthy' =>
            $actualTruthy,

        'falsy' =>
            !$actualTruthy,

        'answered' =>
            llama_place_report_question_answered(
                $input,
                $dependsOn
            ),

        default =>
            false,
    };
}

function llama_place_report_question_applicable(
    array $input,
    string $fieldKey
): bool {
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!is_array($field)) {
        return false;
    }

    if (!empty($field['derived'])) {
        return false;
    }

    $rules =
        (array) (
            $field['applicable_if']
            ?? []
        );

    if (!$rules) {
        return true;
    }

    foreach ($rules as $rule) {
        if (
            !is_array($rule)
            || !llama_place_report_applicability_rule_matches(
                $input,
                $rule
            )
        ) {
            return false;
        }
    }

    return true;
}

function llama_place_report_question_answered(
    array $input,
    string $fieldKey
): bool {
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!is_array($field)) {
        return false;
    }

    if (
        !llama_place_report_question_applicable(
            $input,
            $fieldKey
        )
    ) {
        return false;
    }

    if (!array_key_exists($fieldKey, $input)) {
        return false;
    }

    $value =
        $input[$fieldKey];

    if (
        $value
        === llama_place_report_unanswered_token()
    ) {
        return false;
    }

    if (
        $value
        === llama_place_report_unknown_token()
    ) {
        return true;
    }

    /*
     * Checkbox questions are affirmative observations. An unchecked
     * checkbox may be serialized as 0 by moderator/admin forms, but
     * that must not turn a grouped checkbox section into an answered
     * completion item. Only an explicitly checked value answers it.
     */
    if (
        (string) ($field['type'] ?? '')
        === 'checkbox'
    ) {
        return in_array(
            $value,
            [
                true,
                1,
                '1',
            ],
            true
        );
    }

    $minimumCharacters =
        max(
            0,
            (int) (
                $field['min_characters']
                ?? 0
            )
        );

    if ($minimumCharacters > 0) {
        return
            llama_place_report_normalized_text_length(
                $value
            )
            >= $minimumCharacters;
    }

    return llama_place_report_is_answered_input(
        $input,
        $fieldKey
    );
}

function llama_place_report_completion_items(
    array $input,
    array $excludedFieldKeys = []
): array {
    $items = [];

    $excludedFieldLookup =
        array_fill_keys(
            array_map(
                'strval',
                $excludedFieldKeys
            ),
            true
        );

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $fieldKey =
            (string) $fieldKey;

        if (isset($excludedFieldLookup[$fieldKey])) {
            continue;
        }

        if (
            !llama_place_report_question_applicable(
                $input,
                $fieldKey
            )
        ) {
            continue;
        }

        if (
            array_key_exists(
                'counts_toward_completion',
                $field
            )
            && !$field['counts_toward_completion']
        ) {
            continue;
        }

        $group =
            trim(
                (string) (
                    $field['completion_group']
                    ?? ''
                )
            );

        $itemKey =
            $group !== ''
                ? 'group:' . $group
                : 'field:' . $fieldKey;

        if (!isset($items[$itemKey])) {
            $items[$itemKey] = [
                'key' =>
                    $itemKey,

                'label' =>
                    $group !== ''
                        ? ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $group
                            )
                        )
                        : (string) (
                            $field['label']
                            ?? $fieldKey
                        ),

                'fields' =>
                    [],

                'answered' =>
                    false,
            ];
        }

        $items[$itemKey]['fields'][] =
            $fieldKey;

        if (
            llama_place_report_question_answered(
                $input,
                $fieldKey
            )
        ) {
            $items[$itemKey]['answered'] =
                true;
        }
    }

    return array_values($items);
}

function llama_place_report_question_completion_summary(
    array $input,
    int $photoCount = 0,
    array $excludedFieldKeys = []
): array {
    $items =
        llama_place_report_completion_items(
            $input,
            $excludedFieldKeys
        );

    /*
     * Keep current Llama Scout behavior: current photo evidence
     * contributes one completion item, but it is not a question
     * definition and does not receive Scout points here.
     */
    $items[] = [
        'key' => 'evidence:photo',
        'label' => '1 current photo',
        'fields' => [],
        'answered' => $photoCount > 0,
    ];

    $answered = 0;
    $missing = [];

    foreach ($items as $item) {
        if (!empty($item['answered'])) {
            $answered++;
            continue;
        }

        $missing[] = [
            'key' =>
                (string) (
                    $item['key']
                    ?? ''
                ),

            'label' =>
                (string) (
                    $item['label']
                    ?? ''
                ),

            'fields' =>
                array_values(
                    (array) (
                        $item['fields']
                        ?? []
                    )
                ),
        ];
    }

    $total =
        count($items);

    $missingMinimum = [];

    if (
        !llama_place_report_question_answered(
            $input,
            'name'
        )
    ) {
        $missingMinimum[] =
            'Place name';
    }

    if (
    !llama_place_report_question_answered(
        $input,
        'type'
    )
    ) {
        $missingMinimum[] =
            'Place type';
    }
    
    if (
        !llama_place_report_question_answered(
            $input,
            'visited_at'
        )
    ) {
        $missingMinimum[] =
            'Date visited';
    }
    
    if (
        !llama_place_report_question_answered(
            $input,
            'description'
        )
    ) {
        $missingMinimum[] =
            'Description';
    }
    
    if (
        !llama_place_report_question_answered(
            $input,
            'latitude'
        )
        || !llama_place_report_question_answered(
            $input,
            'longitude'
        )
    ) {
        $missingMinimum[] =
            'Exact location';
    }

    if ($photoCount < 1) {
        $missingMinimum[] =
            '1 current photo';
    }

    return [
        'answered' =>
            $answered,

        'total' =>
            $total,

        'percent' =>
            $total > 0
                ? (int) round(
                    100
                    * (
                        $answered
                        / $total
                    )
                )
                : 0,

        'missing' =>
            $missing,

        'missing_minimum' =>
            $missingMinimum,

        'minimum_met' =>
            !$missingMinimum,

        'over_limit' =>
            $total > 200,
    ];
}


function llama_place_report_display_value(
    array $data,
    string $fieldKey
): ?string {
    $field =
        llama_place_report_fields()[$fieldKey]
        ?? null;

    if (!$field) {
        return null;
    }

    $state =
        llama_place_report_answer_state(
            $data,
            $fieldKey
        );

    if ($state === 'unknown') {
        return 'Unknown';
    }

    if ($state === 'unanswered') {
        return null;
    }

    $value =
        llama_place_report_get_path(
            $data,
            (string) $field['storage']
        );

    $type = (string) $field['type'];

    if ($type === 'tri') {
        return $value ? 'Yes' : 'No';
    }

    if ($type === 'permission') {
        return match ((int) $value) {
            1 => 'Yes',
            2 => 'With Permit',
            default => 'No',
        };
    }

    if ($type === 'rating') {
        return (int) $value . '/5';
    }

    if ($type === 'checkbox') {
        return $value ? 'Yes' : null;
    }

    if ($type === 'multiselect') {
        $options = (array) ($field['options'] ?? []);
        $labels = [];

        foreach (llama_place_report_multiselect_values($value) as $selected) {
            $selectedKey = (string) $selected;

            if (array_key_exists($selectedKey, $options)) {
                $labels[] = (string) $options[$selectedKey];
            }
        }

        return $labels ? implode(', ', $labels) : null;
    }

    if ($type === 'select') {
        $options = (array) ($field['options'] ?? []);
        $key = (string) $value;

        if (array_key_exists($key, $options)) {
            return (string) $options[$key];
        }
    }

    if (($field['format'] ?? '') === 'currency') {
        return '$' . number_format((float) $value, 2);
    }

    return (string) $value;
}

function llama_place_report_normalize_committed_photos(array $photos): array
{
    foreach ($photos as &$photo) {
        if (!is_array($photo)) {
            continue;
        }

        $path =
            trim(
                (string) (
                    $photo['path']
                    ?? $photo['src']
                    ?? ''
                )
            );

        if ($path !== '') {
            $photo['path'] = $path;
            $photo['src'] = $path;
        }
    }
    unset($photo);

    return array_values(
        array_filter(
            $photos,
            'is_array'
        )
    );
}

function llama_place_report_photo_url(mixed $photo): string
{
    $path =
        llama_place_report_photo_path(
            $photo
        );

    if ($path === '') {
        return '';
    }

    if (
        preg_match(
            '#^https?://#i',
            $path
        )
    ) {
        return $path;
    }

    return
        'https://llamascout.com/'
        . ltrim(
            $path,
            '/'
        );
}

function llama_place_report_photo_path(mixed $photo): string
{
    if (!is_array($photo)) {
        return '';
    }

    return trim(
        (string) (
            $photo['src']
            ?? $photo['path']
            ?? $photo['url']
            ?? ''
        )
    );
}

function llama_place_report_validate_new_place_minimum(
    array $data,
    int $photoCount
): void {
    $missing = [];

    if (trim((string) ($data['name'] ?? '')) === '') {
        $missing[] = 'a Place name';
    }

    if (trim((string) ($data['type'] ?? '')) === '') {
        $missing[] = 'a Place type';
    }
    
    if (trim((string) ($data['visited_at'] ?? '')) === '') {
        $missing[] = 'the date visited';
    }
    
    if (trim((string) ($data['description'] ?? '')) === '') {
        $missing[] = 'a description';
    }
    
    if (
        !is_numeric($data['latitude'] ?? null)
        || !is_numeric($data['longitude'] ?? null)
    ) {
        $missing[] = 'the exact map location';
    }

    if ($photoCount < 1) {
        $missing[] = 'at least one current photo';
    }

    if (!$missing) {
        return;
    }

    throw new InvalidArgumentException(
        'Before submitting a new Place, add '
        . implode(', ', $missing)
        . '. Everything else may be left unanswered when you did not observe it.'
    );
}


function llama_place_report_validate_scout_submission(
    PDO $db,
    int $userId,
    array $data
): void {
    if ($userId < 1) {
        return;
    }

    $level = llama_user_contribution_level(
        $db,
        $userId
    );

    if (
        llama_contribution_level_rank($level)
        < llama_contribution_level_rank(
            LLAMA_CONTRIBUTION_LEVEL_SCOUT
        )
    ) {
        return;
    }

    if (trim((string) ($data['visited_at'] ?? '')) === '') {
        throw new InvalidArgumentException(
            'Scout and Master Scout new Place submissions require the date you personally visited the Place.'
        );
    }
}


function llama_place_report_submit_new_place(
    int $userId,
    array $input
): int {
    if (
        $userId < 1
        || !llama_contributor_can(
            db(),
            $userId,
            'submit_place'
        )
    ) {
        throw new RuntimeException(
            'Your account is not eligible to submit new Places.'
        );
    }

    $data =
        llama_place_report_build_data(
            $input
        );

    $photoToken =
        trim(
            (string) (
                $input['photo_stage_token']
                ?? ''
            )
        );

    $submittedPhotos =
        llama_photo_decode_form_photos(
            $input['photos_json']
            ?? '[]'
        );

    if ($submittedPhotos && $photoToken === '') {
        throw new InvalidArgumentException(
            'The photo upload session is missing. Please upload the photos again.'
        );
    }

    llama_place_report_validate_new_place_minimum(
        $data,
        count($submittedPhotos)
    );

    $db = db();

    llama_place_report_validate_scout_submission(
        $db,
        $userId,
        $data
    );
    $submissionId = 0;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare(
            'INSERT INTO place_submissions
                (
                    user_id,
                    role_at_submission,
                    place_name,
                    source_type,
                    status,
                    submission_data
                )
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            community_role_at_submission($userId),
            (string) $data['name'],
            'community-scouted',
            'pending',
            json_encode(
                $data,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $submissionId =
            (int) $db->lastInsertId();

        if ($photoToken !== '') {
            $data['photos'] =
                llama_place_report_normalize_committed_photos(
                    llama_photo_commit_stage(
                        'add-place',
                        $userId,
                        $photoToken,
                        $submittedPhotos,
                        '/uploads/place-submissions/'
                        . $submissionId
                    )
                );

            $stmt = $db->prepare(
                'UPDATE place_submissions
                 SET submission_data = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                json_encode(
                    $data,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
                $submissionId,
            ]);
        }

        $db->commit();

        return $submissionId;

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($submissionId > 0) {
            llama_photo_delete_tree(
                dirname(__DIR__)
                . '/uploads/place-submissions/'
                . $submissionId
            );
        }

        throw $exception;
    }
}

function llama_place_report_resubmit_new_place(
    int $userId,
    int $submissionId,
    array $input
): int {
    if (
        $userId < 1
        || !llama_contributor_can(
            db(),
            $userId,
            'submit_place'
        )
    ) {
        throw new RuntimeException(
            'Your account is not eligible to submit new Places.'
        );
    }

    $existing =
        community_new_place_submission_for_user(
            $userId,
            $submissionId
        );

    if (
        !$existing
        || (string) ($existing['status'] ?? '') !== 'needs-changes'
    ) {
        throw new RuntimeException(
            'This new Place submission is no longer available for resubmission.'
        );
    }

    $data =
        llama_place_report_build_data(
            $input
        );

    $existingData =
        is_array($existing['data'] ?? null)
            ? $existing['data']
            : [];

    $existingPhotos =
        is_array($existingData['photos'] ?? null)
            ? $existingData['photos']
            : [];

    $removePhotos =
        is_array($input['remove_existing_photos'] ?? null)
            ? array_values(
                array_filter(
                    array_map(
                        static fn (mixed $value): string =>
                            trim((string) $value),
                        $input['remove_existing_photos']
                    ),
                    static fn (string $value): bool =>
                        $value !== ''
                )
            )
            : [];

    $keptPhotos = [];

    foreach ($existingPhotos as $photo) {
        $path =
            llama_place_report_photo_path(
                $photo
            );

        if (
            $path !== ''
            && in_array(
                $path,
                $removePhotos,
                true
            )
        ) {
            continue;
        }

        if (is_array($photo)) {
            $keptPhotos[] = $photo;
        }
    }

    $photoToken =
        trim(
            (string) (
                $input['photo_stage_token']
                ?? ''
            )
        );

    $submittedPhotos =
        llama_photo_decode_form_photos(
            $input['photos_json']
            ?? '[]'
        );

    llama_place_report_validate_new_place_minimum(
        $data,
        count($keptPhotos) + count($submittedPhotos)
    );

    $newPhotos = [];
    $db = db();

    llama_place_report_validate_scout_submission(
        $db,
        $userId,
        $data
    );

    try {
        $db->beginTransaction();

        if ($photoToken !== '') {
            $newPhotos =
                llama_place_report_normalize_committed_photos(
                    llama_photo_commit_stage(
                        'add-place',
                        $userId,
                        $photoToken,
                        $submittedPhotos,
                        '/uploads/place-submissions/'
                        . $submissionId
                    )
                );
        }

        $data['photos'] =
            array_values(
                array_merge(
                    $keptPhotos,
                    $newPhotos
                )
            );

        $stmt = $db->prepare(
            'UPDATE place_submissions
             SET
                place_name = ?,
                role_at_submission = ?,
                status = "pending",
                submission_data = ?,
                submitted_at = CURRENT_TIMESTAMP,
                reviewed_at = NULL,
                reviewed_by = NULL,
                review_notes = NULL
             WHERE id = ?
               AND user_id = ?
               AND status = "needs-changes"'
        );

        $stmt->execute([
            (string) $data['name'],
            community_role_at_submission($userId),
            json_encode(
                $data,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
            $submissionId,
            $userId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The Place submission changed before it could be resubmitted.'
            );
        }

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($newPhotos as $photo) {
            $path =
                llama_place_report_photo_path(
                    $photo
                );

            if ($path !== '') {
                $absolute =
                    dirname(__DIR__)
                    . $path;

                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
        }

        throw $exception;
    }

    foreach ($removePhotos as $path) {
        if (
            !str_starts_with(
                $path,
                '/uploads/place-submissions/'
                . $submissionId
                . '/'
            )
        ) {
            continue;
        }

        $absolute =
            dirname(__DIR__)
            . $path;

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    return $submissionId;
}


function llama_place_report_data_from_published_place(
    array $place,
    array $unknownFields = []
): array {
    $data = $place;

    if (!isset($data['details']) || !is_array($data['details'])) {
        $data['details'] = [];
    }

    if (!isset($data['amenities']) || !is_array($data['amenities'])) {
        $data['amenities'] = [];
    }

    if (!isset($data['connectivity']) || !is_array($data['connectivity'])) {
        $data['connectivity'] = [];
    }

    if (!isset($data['rules']) || !is_array($data['rules'])) {
        $data['rules'] = [];
    }

    if (!isset($data['experience']) || !is_array($data['experience'])) {
        $data['experience'] = [];
    }

    $sensory =
        is_array($data['sensory'] ?? null)
            ? $data['sensory']
            : [];

    $sensory['details'] =
        is_array($data['sensory_details'] ?? null)
            ? $data['sensory_details']
            : (
                is_array($sensory['details'] ?? null)
                    ? $sensory['details']
                    : []
            );

    $data['sensory'] = $sensory;

    /*
     * Multi-select values are stored as JSON in normalized child tables.
     * Submission JSON already contains arrays, so decode only persisted strings.
     */
    foreach (llama_place_report_fields() as $fieldKey => $field) {
        if ((string) ($field['type'] ?? '') !== 'multiselect') {
            continue;
        }

        $storage =
            (string) (
                $field['storage']
                ?? ''
            );

        $value =
            llama_place_report_get_path(
                $data,
                $storage
            );

        llama_place_report_set_path(
            $data,
            $storage,
            llama_place_report_multiselect_values($value)
        );
    }

    /*
     * Recompute derived recommendation flags at read time as well so older
     * published rows cannot display a stale recommendation that conflicts
     * with the current 1â5 experience rating.
     */
    $deriveRecommendation =
        static function (mixed $rating): ?bool {
            if (!is_numeric($rating)) {
                return null;
            }

            $rating = (int) $rating;

            if ($rating >= 4) {
                return true;
            }

            if ($rating >= 1 && $rating <= 2) {
                return false;
            }

            return null;
        };

    foreach (
        [
            'recommended_overnight_stop' => 'overnight_comfort',
            'recommended_quiet_evening' => 'quiet_evening',
            'recommended_extended_stay' => 'extended_stay_comfort',
            'recommended_sensory_retreat' => 'sensory_retreat',
            'recommended_stargazing' => 'stargazing',
            'recommended_remote_work' => 'remote_work',
        ]
        as $derivedKey => $ratingKey
    ) {
        $data['experience'][$derivedKey] =
            $deriveRecommendation(
                $data['experience'][$ratingKey]
                ?? null
            );
    }

    $data['_answer_state'] = array_values($unknownFields);

    return $data;
}


function llama_place_report_publish_answer_state(
    PDO $db,
    int $placeId,
    array $submissionData
): void {
    $unknown =
        llama_place_report_unknown_fields(
            $submissionData
        );

    $stmt = $db->prepare(
        'UPDATE places
         SET place_report_answer_state = ?
         WHERE id = ?'
    );

    $stmt->execute([
        json_encode(
            $unknown,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR
        ),
        $placeId,
    ]);
}

function llama_place_report_published_answer_state(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT place_report_answer_state
         FROM places
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$placeId]);

    $json = $stmt->fetchColumn();

    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded =
        json_decode(
            $json,
            true
        );

    if (!is_array($decoded)) {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                'strval',
                $decoded
            ),
            static fn (string $value): bool =>
                $value !== ''
        )
    );
}
