<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/place-report.php';

/** Flatten Place and scoped proposals into ordered canonical review rows. */
function llama_place_report_review_changes(array $proposed, array $original,
    array $unknown = [], array $originalUnknown = []): array
{
    $rows = [];
    $payload = $proposed['__scoped_report__'] ?? null;
    if (is_array($payload)) {
        $answers = (array) ($payload['answers'] ?? []);
        $before = (array) ($original['__scoped_report__']['answers'] ?? []);
        if (!$answers && isset($payload['field_key'])) {
            $answers = [(string) $payload['field_key'] => $payload['value'] ?? null];
            $before = [(string) $payload['field_key'] => $original['__scoped_report__'] ?? []];
        }
        foreach (llama_place_report_fields() as $key => $field) {
            if (!array_key_exists($key, $answers)) { continue; }
            $prior = (array) ($before[$key] ?? []);
            $old = $prior['effective_value'] ?? $prior['value'] ?? null;
            $rows[] = ['path' => $field['storage'], 'definition' => ['shared' => $field] + $field,
                'old' => $old, 'new' => $answers[$key],
                'old_unknown' => $old === llama_place_report_unknown_token(),
                'new_unknown' => $answers[$key] === llama_place_report_unknown_token(),
                'target_label' => ucfirst((string) ($payload['scope'] ?? '')) . ': ' . (string) ($payload['target_label'] ?? '')];
        }
    } else {
        foreach (llama_place_report_published_definitions() as $path => $definition) {
            if (!array_key_exists($path, $proposed)) { continue; }
            $key = $definition['key'];
            $rows[] = ['path' => $path, 'definition' => $definition, 'old' => $original[$path] ?? null,
                'new' => $proposed[$path], 'old_unknown' => in_array($key, $originalUnknown, true),
                'new_unknown' => in_array($key, $unknown, true), 'target_label' => ''];
        }
        // Unknown pending storage paths stay visible for review, never silently disappear.
        foreach ($proposed as $path => $value) {
            if (llama_place_report_field_by_storage((string) $path)) { continue; }
            $rows[] = ['path' => (string) $path, 'definition' => ['label' => 'Unsupported field: ' . $path, 'key' => ''],
                'old' => $original[$path] ?? null, 'new' => $value,
                'old_unknown' => false, 'new_unknown' => false, 'target_label' => ''];
        }
    }
    return $rows;
}

function llama_place_report_review_value(string $path, mixed $value, bool $unknown = false): string
{
    if ($unknown || $value === llama_place_report_unknown_token()) { return 'Unknown'; }
    $field = llama_place_report_field_by_storage($path);
    if (!$field) { return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $value; }
    if ($field['type'] === 'multiselect' && is_array($value) && $value === []) { return 'None selected'; }
    $data = [];
    llama_place_report_set_path($data, $path, $value);
    return llama_place_report_display_value($data, $field['key']) ?? 'Not provided';
}
