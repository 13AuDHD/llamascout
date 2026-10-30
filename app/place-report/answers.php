<?php

declare(strict_types=1);

/** Canonical answer contract. Transport tokens never become published facts. */
function llama_place_report_parse_answer(array $field, mixed $raw): array
{
    $key = (string) $field['key'];
    $type = (string) $field['type'];
    $label = rtrim((string) $field['label'], ' *');
    $invalid = static function (string $reason) use ($label): never {
        throw new InvalidArgumentException($label . ': ' . $reason);
    };
    if ($raw === llama_place_report_unknown_token()) {
        if (empty($field['allow_unknown'])) {
            $invalid('Unknown is not an available answer.');
        }
        return ['state' => 'unknown', 'value' => null];
    }
    if ($raw === null || $raw === '' || $raw === llama_place_report_unanswered_token()) {
        return ['state' => 'unanswered', 'value' => null];
    }
    if ($type === 'multiselect') {
        if (!is_array($raw) || !array_is_list($raw)) {
            $invalid('Choose a list of available answers.');
        }
        $values = [];
        $options = (array) ($field['options'] ?? []);
        foreach ($raw as $item) {
            if (!is_scalar($item) || !array_key_exists((string) $item, $options)) {
                $invalid('Choose only available answers.');
            }
            $values[(string) $item] = true;
        }
        // An explicit empty set is a recorded scoped answer and stops inheritance.
        return ['state' => 'answered', 'value' => array_keys($values)];
    }
    if (!is_scalar($raw)) {
        $invalid('Enter one answer.');
    }
    $text = is_bool($raw) ? ($raw ? '1' : '0') : trim((string) $raw);
    if ($text === '') {
        return ['state' => 'unanswered', 'value' => null];
    }
    if (in_array($type, ['tri', 'checkbox'], true)) {
        if (!in_array($text, ['0', '1'], true)) {
            $invalid('Choose Yes or No.');
        }
        $value = $text === '1';
    } elseif ($type === 'rating' || $type === 'permission') {
        $allowed = $type === 'rating' ? ['1','2','3','4','5'] : ['0','1','2','3'];
        if (!in_array($text, $allowed, true)) {
            $invalid('Choose an available answer.');
        }
        $value = (int) $text;
    } elseif ($type === 'number') {
        if (!is_numeric($text) || !is_finite((float) $text)) {
            $invalid('Enter a finite number.');
        }
        $value = (float) $text;
        if (isset($field['min']) && $value < (float) $field['min']
            || isset($field['max']) && $value > (float) $field['max']) {
            $invalid('Enter a number within the allowed range.');
        }
        if (($field['step'] ?? '') === '1' && floor($value) !== $value) {
            $invalid('Enter a whole number.');
        }
        if (floor($value) === $value && abs($value) < PHP_INT_MAX) {
            $value = (int) $value;
        }
    } else {
        if ($type === 'select' && $key === 'type' && str_starts_with($text, 'Other: ')) {
            $text = 'other';
        }
        if ($type === 'select' && empty($field['allow_custom'])
            && !array_key_exists($text, (array) ($field['options'] ?? []))
            && !array_key_exists($text, (array) ($field['legacy_values'] ?? []))) {
            // Imported physical measurements can fall between dropdown increments.
            if (!str_ends_with($key, '_feet') || !is_numeric($text)
                || !is_finite((float) $text) || (float) $text < 0 || (float) $text > 10000) {
                $invalid('Choose an available answer.');
            }
        }
        if ($type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
            if (!$date || $date->format('Y-m-d') !== $text) {
                $invalid('Enter a valid date.');
            }
        }
        if (in_array($key, ['season_begins', 'season_ends'], true)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', '2000-' . $text);
            if (!$date || $date->format('m-d') !== $text) {
                $invalid('Enter a valid month and day as MM-DD.');
            }
        }
        if ($type === 'url' && (!filter_var($text, FILTER_VALIDATE_URL)
            || !in_array(strtolower((string) parse_url($text, PHP_URL_SCHEME)), ['http','https'], true))) {
            $invalid('Enter a complete http or https URL.');
        }
        if (mb_strlen($text, 'UTF-8') > (int) ($field['maxlength'] ?? 5000)) {
            $invalid('The answer is too long.');
        }
        $value = $text;
    }
    return ['state' => 'answered', 'value' => $value];
}

/**
 * Canonical answer envelope for a newly submitted report observation.
 *
 * This is deliberately independent of the current published answer. A Scout
 * may confirm the same value in a later report and that remains an Answered
 * observation. Unanswered is the only state that is omitted from a partial
 * report.
 *
 * @return array{
 *     state:string,
 *     value:mixed,
 *     recorded:bool,
 *     scoped_value:mixed
 * }
 */
function llama_place_report_submission_answer(
    array $field,
    mixed $raw
): array {
    $answer =
        llama_place_report_parse_answer(
            $field,
            $raw
        );

    $state =
        (string) $answer['state'];

    return [
        'state' =>
            $state,

        'value' =>
            $answer['value'],

        'recorded' =>
            $state !== 'unanswered',

        'scoped_value' =>
            $state === 'unknown'
                ? llama_place_report_unknown_token()
                : $answer['value'],
    ];
}

/** Convert a parsed answer to the existing scoped JSON representation. */
function llama_place_report_scoped_value(array $field, mixed $raw): mixed
{
    $answer =
        llama_place_report_submission_answer(
            $field,
            $raw
        );

    return $answer['scoped_value'];
}

/** Side-effect-free checks for the shared report-submission answer contract. */
function llama_place_report_submission_answer_contract_issues(): array
{
    $issues = [];

    $tri = [
        'key' => 'diagnostic_tri',
        'label' => 'Diagnostic Yes or No',
        'type' => 'tri',
        'allow_unknown' => true,
    ];

    $number = [
        'key' => 'diagnostic_number',
        'label' => 'Diagnostic Number',
        'type' => 'number',
        'min' => 0,
        'max' => 10,
    ];

    $multiselect = [
        'key' => 'diagnostic_list',
        'label' => 'Diagnostic List',
        'type' => 'multiselect',
        'options' => [
            'one' => 'One',
        ],
    ];

    $unanswered =
        llama_place_report_submission_answer(
            $tri,
            llama_place_report_unanswered_token()
        );

    if (
        $unanswered['state'] !== 'unanswered'
        || $unanswered['recorded'] !== false
        || $unanswered['value'] !== null
    ) {
        $issues[] =
            'Submission-answer Unanswered contract mismatch.';
    }

    $explicitNo =
        llama_place_report_submission_answer(
            $tri,
            '0'
        );

    if (
        $explicitNo['state'] !== 'answered'
        || $explicitNo['recorded'] !== true
        || $explicitNo['value'] !== false
    ) {
        $issues[] =
            'Submission-answer explicit No contract mismatch.';
    }

    $zero =
        llama_place_report_submission_answer(
            $number,
            '0'
        );

    if (
        $zero['state'] !== 'answered'
        || $zero['recorded'] !== true
        || $zero['value'] !== 0
    ) {
        $issues[] =
            'Submission-answer numeric zero contract mismatch.';
    }

    $unknown =
        llama_place_report_submission_answer(
            $tri,
            llama_place_report_unknown_token()
        );

    if (
        $unknown['state'] !== 'unknown'
        || $unknown['recorded'] !== true
        || $unknown['value'] !== null
        || $unknown['scoped_value']
            !== llama_place_report_unknown_token()
    ) {
        $issues[] =
            'Submission-answer Unknown contract mismatch.';
    }

    $emptyList =
        llama_place_report_submission_answer(
            $multiselect,
            []
        );

    if (
        $emptyList['state'] !== 'answered'
        || $emptyList['recorded'] !== true
        || $emptyList['value'] !== []
    ) {
        $issues[] =
            'Submission-answer empty-list contract mismatch.';
    }

    return $issues;
}

/** Compare persisted representations without changing historical records. */
function llama_place_report_values_equal(mixed $left, mixed $right, ?array $field = null): bool
{
    if ($left === llama_place_report_unknown_token() || $right === llama_place_report_unknown_token()) {
        return $left === $right;
    }
    if ($left === null || $right === null) {
        return $left === $right;
    }
    $type = (string) ($field['type'] ?? '');
    if ($type === 'multiselect') {
        $left = llama_place_report_multiselect_values($left);
        $right = llama_place_report_multiselect_values($right);
        sort($left, SORT_STRING);
        sort($right, SORT_STRING);
        return $left === $right;
    }
    if (is_array($left) || is_array($right)) {
        return $left === $right;
    }
    if ((in_array($type, ['number','rating','permission'], true)
        || ($type === 'select' && str_ends_with((string) ($field['key'] ?? ''), '_feet')))
        && is_numeric($left) && is_numeric($right)) {
        return (float) $left === (float) $right;
    }
    if (is_bool($left)) { $left = $left ? '1' : '0'; }
    if (is_bool($right)) { $right = $right ? '1' : '0'; }
    return (string) $left === (string) $right;
}

/** One form-value adapter for published data, proposals and scoped answers. */
function llama_place_report_answer_form_value(array $field, mixed $value, bool $unknown = false): mixed
{
    if ($unknown || $value === llama_place_report_unknown_token()) {
        return llama_place_report_unknown_token();
    }
    if (($field['type'] ?? '') === 'multiselect') {
        return llama_place_report_multiselect_values($value);
    }
    if ($value === null) {
        return in_array($field['type'], ['tri','permission','rating'], true)
            ? llama_place_report_unanswered_token() : '';
    }
    return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
}

/** Registered writable fields for an existing reporting target. */
function llama_place_report_fields_for_scope(string $scope): array
{
    if (!in_array($scope, ['place','area','site'], true)) {
        throw new InvalidArgumentException('Invalid reporting scope.');
    }
    return array_filter(llama_place_report_fields(), static fn(array $field): bool =>
        empty($field['derived']) && in_array($scope, (array) $field['report_scopes'], true));
}

/** Adapt the former path-based update form without duplicating its workflow. */
function llama_place_report_legacy_update_input(array $input): array
{
    if (!isset($input['change_fields'])) { return $input; }
    foreach ((array) $input['change_fields'] as $path) {
        if (!is_string($path)) { throw new InvalidArgumentException('Invalid update field.'); }
        $field = llama_place_report_field_by_storage($path);
        if (!$field) { throw new InvalidArgumentException('Unsupported update field.'); }
        $raw = $input['field_value'][$path] ?? null;
        $input[$field['key']] = $raw === '__NULL__' ? llama_place_report_unanswered_token() : $raw;
    }
    return $input;
}

function llama_scoped_report_values_from_data(array $data): array
{
    $values = [];

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $fieldKey =
            (string) $fieldKey;

        $state =
            llama_place_report_answer_state(
                $data,
                $fieldKey
            );

        if ($state === 'unknown') {
            $values[$fieldKey] =
                llama_place_report_unknown_token();

            continue;
        }

        if ($state !== 'answered') {
            continue;
        }

        $values[$fieldKey] =
            llama_place_report_form_value_from_data(
                $data,
                $fieldKey
            );
    }

    return $values;
}


/** HTML checkboxes and sets omit unchecked controls; only declared controls clear. */
function llama_place_report_expand_presence(array $input): array
{
    foreach ((array) ($input['_report_present'] ?? []) as $key => $present) {
        $field = llama_place_report_fields()[$key] ?? null;
        if (!$field || array_key_exists($key, $input) || (string) $present !== '1') { continue; }
        if ($field['type'] === 'checkbox') { $input[$key] = '0'; }
        if ($field['type'] === 'multiselect') { $input[$key] = []; }
    }
    foreach ((array) ($input['_report_unknown'] ?? []) as $key => $selected) {
        $field = llama_place_report_fields()[$key] ?? null;
        if ($field && !empty($field['allow_unknown']) && (string) $selected === '1') {
            $input[$key] = llama_place_report_unknown_token();
        }
    }
    return $input;
}

function llama_place_report_metadata(array $input, string $key): mixed
{
    $field = llama_place_report_fields()[$key] ?? null;
    if (!$field) { throw new InvalidArgumentException('Invalid report metadata.'); }
    return llama_place_report_parse_answer($field, $input[$key] ?? null)['value'];
}

function llama_place_report_mirror_value(mixed $value, string $transform): mixed
{
    if ($transform === 'identity') {
        return $value;
    }

    if ($transform === 'toilet_presence') {
        if (in_array($value, ['pit', 'flush', 'portable', 'present_unknown_type'], true)) {
            return true;
        }

        if ($value === 'no') {
            return false;
        }

        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return (float) $value >= 4
        ? true
        : ((float) $value <= 2 && (float) $value >= 1
            ? false
            : null);
}

function llama_place_report_derive_compatibility(array $data, ?array $changedKeys = null): array
{
    foreach (llama_place_report_fields() as $key => $field) {
        if (empty($field['compatibility_mirror'])) { continue; }
        if ($changedKeys !== null && !in_array($key, $changedKeys, true)) { continue; }
        if (!llama_place_report_has_path($data, $field['storage'])) { continue; }
        $value = llama_place_report_get_path($data, $field['storage']);
        if ($changedKeys === null && $value === null) { continue; }
        $mirror = $field['compatibility_mirror'];
        llama_place_report_set_path($data, $mirror['storage'],
            llama_place_report_mirror_value($value, $mirror['transform']));
    }
    return $data;
}

/** Compare canonical values and explicit Unknown, preserving untouched legacy mirrors. */
function llama_place_report_changed_keys(array $before, array $after, array $keys): array
{
    $changed = [];
    foreach ($keys as $key) {
        $field = llama_place_report_fields()[$key] ?? null;
        if (!$field) { continue; }
        if (llama_place_report_answer_state($before, $key) !== llama_place_report_answer_state($after, $key)
            || !llama_place_report_values_equal(llama_place_report_get_path($before, $field['storage']),
                llama_place_report_get_path($after, $field['storage']), $field)) {
            $changed[] = $key;
        }
    }
    return $changed;
}
