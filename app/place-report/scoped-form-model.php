<?php

declare(strict_types=1);

/**
 * Phase 3F: read-only form model for an existing Place, Area, or Site.
 *
 * This is deliberately not an HTTP endpoint or an authorization layer.
 * Calling code must authenticate and authorize the Scout before rendering
 * data, and separately authorize, CSRF-check and validate any writes.
 */
require_once __DIR__ . '/report-targets.php';
require_once __DIR__ . '/scoped-answers.php';

/** @return array<string, mixed> */
function llama_scoped_report_form_model(
    PDO $db,
    int $placeId,
    string $scope = 'place',
    string $source = 'place',
    ?int $scopeId = null
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException('A valid Place ID is required.');
    }

    $targets = llama_report_targets($db, $placeId);
    $selectedId = $scope === 'place' ? $placeId : (int) $scopeId;
    $selected = llama_report_verified_target(
        $db, $placeId, $scope, $source, $selectedId
    );

    $fields = [];
    foreach (llama_place_report_fields() as $key => $field) {
        if (!in_array($scope, (array) ($field['report_scopes'] ?? ['place']), true)) {
            continue;
        }
        if (!empty($field['derived'])) {
            continue;
        }
        $fields[$key] = $field;
    }

    $overrides = $scope === 'place'
        ? []
        : llama_scoped_report_load($db, $placeId, $scope, $selectedId, $source);

    // Never present an inherited value as an explicit edit. The caller may
    // separately show inherited read-only hints alongside empty controls.
    $explicit = [];
    foreach ($overrides as $key => $value) {
        if (isset($fields[$key])) {
            $explicit[$key] = $value;
        }
    }

    $parent = null;
    if ($scope === 'site' && $source === 'map_feature' && !empty($selected['parent_area_id'])) {
        $areaId = (int) $selected['parent_area_id'];
        foreach ($targets as $candidate) {
            if (
                $candidate['scope'] === 'area'
                && $candidate['source'] === 'map_feature'
                && (int) $candidate['id'] === $areaId
            ) {
                $parent = $candidate;
                break;
            }
        }
    }

    $parentOverrides = $parent === null ? [] : llama_scoped_report_load(
        $db, $placeId, 'area', (int) $parent['id'], 'map_feature'
    );

    return [
        'place_id' => $placeId,
        'targets' => $targets,
        'selected' => $selected,
        'parent_area' => $parent,
        'fields' => $fields,
        'explicit_answers' => $explicit,
        'parent_area_answers' => $parentOverrides,
        // Place-level values come from the existing canonical Place Report
        // loader; this function intentionally does not guess their storage.
        'place_answers_loaded' => false,
    ];
}
