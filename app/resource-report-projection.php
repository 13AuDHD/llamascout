<?php

declare(strict_types=1);

require_once __DIR__ . '/resources.php';
require_once __DIR__ . '/resource-report-field-reconciliation.php';

/**
 * Resource-backed Facilities and standalone Resources share one persisted
 * identity in resources.id. A linked Facility is NOT a mapped Area, and
 * parent_place_id is an association, not an answer-storage destination.
 *
 * This is an additive read-only adapter. Never write its projection back to
 * resources.experience_json or the Place scoped-answer tables.
 */
function llama_resource_report_target(array $resource): array
{
    $id = (int) ($resource['id'] ?? 0);
    if ($id < 1) {
        throw new InvalidArgumentException('A persisted Resource ID is required.');
    }
    $parentId = (int) ($resource['parent_place_id'] ?? 0);
    if ($parentId < 0) {
        throw new InvalidArgumentException('Invalid Facility parent Place.');
    }

    $kind = $parentId > 0 ? 'facility' : 'resource';
    return [
        'kind' => $kind,
        'source' => 'resource_record',
        'id' => $id,
        'key' => $kind . ':resource_record:' . $id,
        'parent_place_id' => $parentId ?: null,
    ];
}

/**
 * Expose the current Resource-owned Experience answers with the same answer
 * states used elsewhere: answered, unknown and unanswered. No inference from
 * a parent Place or from another Resource is permitted.
 *
 * The compound answer identity is necessary because e.g. cleanliness appears
 * independently in men's, women's and all-gender restroom observations.
 *
 * @return array{
 *   target: array<string,mixed>,
 *   legacy_details: array<string,mixed>,
 *   groups: list<array<string,mixed>>,
 *   unmapped: list<array<string,mixed>>
 * }
 */
function llama_resource_report_projection(array $resource): array
{
    $target = llama_resource_report_target($resource);
    $manifest = llama_resource_experience_manifest();
    $rawExperience = is_array($resource['experience'] ?? null)
        ? $resource['experience'] : [];

    // Resource-specific metadata has no equivalent canonical Place question
    // yet. Keep each original key and value unchanged; do not invent a field
    // mapping or imply this data already lives in scout_report_answers.
    $detailKeys = llama_resource_report_legacy_detail_keys();
    $details = array_intersect_key($resource, array_fill_keys($detailKeys, true));

    $groups = [];
    $covered = [];
    $selected = [];
    foreach ((array) ($resource['services'] ?? []) as $serviceRow) {
        if (!is_array($serviceRow)) {
            continue;
        }
        $service = (string) ($serviceRow['service_type'] ?? '');
        if ($service === '' || isset($selected[$service])) {
            continue;
        }
        $selected[$service] = true;
        foreach ($manifest[$service] ?? [] as $groupKey => $definition) {
            $rows = [];
            foreach ($definition['questions'] as $questionKey => $field) {
                $storedGroup = $rawExperience[$service][$groupKey] ?? null;
                $hasAnswer = is_array($storedGroup)
                    && array_key_exists($questionKey, $storedGroup)
                    && $storedGroup[$questionKey] !== null
                    && $storedGroup[$questionKey] !== '';
                $rawValue = $hasAnswer ? $storedGroup[$questionKey] : null;
                $unknown = $hasAnswer && is_string($rawValue)
                    && in_array($rawValue, ['unknown', '__LLAMA_UNKNOWN__'], true);
                $state = !$hasAnswer ? 'unanswered' : ($unknown ? 'unknown' : 'answered');
                $answerId = $service . '/' . $groupKey . '/' . $questionKey;
                $covered[$answerId] = true;

                $rows[] = [
                    'answer_key' => $answerId,
                    'question_key' => $questionKey,
                    'service_type' => $service,
                    'group_key' => (string) $groupKey,
                    'label' => (string) ($field['label'] ?? $questionKey),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'options' => (array) ($field['options'] ?? []),
                    'state' => $state,
                    'recorded' => $hasAnswer,
                    'value' => $rawValue,
                    'display_value' => !$hasAnswer ? ''
                        : ($unknown ? 'Unknown'
                            : llama_resource_experience_label($field, $rawValue)),
                    'origin' => 'resources.experience_json',
                ];
            }
            $groups[] = [
                'service_type' => $service,
                'service_label' => (string) ($serviceRow['service_type_label']
                    ?? llama_resource_service_type_label($service)),
                'group_key' => (string) $groupKey,
                'group_label' => (string) $definition['label'],
                'answers' => $rows,
            ];
        }
    }

    // Preserve visibility of anomalous older JSON to auditing code, without
    // presenting it as an approved question or deleting it during reads.
    $unmapped = [];
    foreach ($rawExperience as $service => $serviceGroups) {
        if (!is_array($serviceGroups)) {
            $unmapped[] = ['path' => (string) $service, 'value' => $serviceGroups];
            continue;
        }
        foreach ($serviceGroups as $group => $values) {
            if (!is_array($values)) {
                $unmapped[] = [
                    'path' => (string) $service . '/' . (string) $group,
                    'value' => $values,
                ];
                continue;
            }
            foreach ($values as $key => $value) {
                $path = (string) $service . '/' . (string) $group . '/' . (string) $key;
                if (!isset($covered[$path])) {
                    $unmapped[] = ['path' => $path, 'value' => $value];
                }
            }
        }
    }

    return [
        'target' => $target,
        'legacy_details' => $details,
        'groups' => $groups,
        'unmapped' => $unmapped,
    ];
}
