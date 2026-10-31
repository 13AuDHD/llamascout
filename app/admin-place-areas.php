<?php

declare(strict_types=1);

/**
 * Admin Area reporting support.
 *
 * Uses the canonical reporting-target registry, field schema, and scoped
 * answer store. No independent Area table or duplicate answer store.
 *
 * The calling Admin Place workflow must enforce admin authorization, CSRF,
 * and wrap the save with its existing database transaction.
 */

require_once __DIR__ . '/place-report.php';
require_once __DIR__ . '/place-report/report-targets.php';
require_once __DIR__ . '/place-report/scoped-answers.php';

/** @return array<string, array<string, mixed>> */
function admin_place_area_fields(): array
{
    $fields = [];

    foreach (llama_place_report_fields() as $key => $field) {
        if (
            !in_array('area', (array) ($field['report_scopes'] ?? []), true)
            || !empty($field['derived'])
            || !empty($field['location_field'])
        ) {
            continue;
        }

        $fields[(string) $key] = $field;
    }

    return $fields;
}

/**
 * @return list<array{id:string,scope_id:int,scope_source:string,label:string,form_values:array}>
 */
function admin_place_area_form_catalog(PDO $db, int $placeId): array
{
    $catalog = [];
    $allowedFields = admin_place_area_fields();

    foreach (llama_report_targets($db, $placeId) as $target) {
        if (
            ($target['scope'] ?? null) !== 'area'
            || ($target['source'] ?? null) !== 'map_feature'
        ) {
            continue;
        }

        $areaId = (int) ($target['id'] ?? 0);
        if ($areaId < 1) {
            continue;
        }

        $overrides = llama_scoped_report_load(
            $db,
            $placeId,
            'area',
            $areaId,
            'map_feature'
        );

        $values = [];
        foreach ($overrides as $key => $value) {
            if (!isset($allowedFields[$key])) {
                continue;
            }

            if ($value === llama_place_report_unknown_token()) {
                $values[$key] = $value;
            } elseif (is_bool($value)) {
                $values[$key] = $value ? '1' : '0';
            } elseif ($value !== null && !is_array($value)) {
                $values[$key] = (string) $value;
            } elseif (is_array($value)) {
                $values[$key] = $value;
            }
        }

        $catalog[] = [
            'id' => 'map_feature:' . $areaId,
            'scope_id' => $areaId,
            'scope_source' => 'map_feature',
            'label' => trim((string) ($target['label'] ?? ''))
                ?: 'Area #' . $areaId,
            'form_values' => $values,
        ];
    }

    return $catalog;
}

/**
 * Save the explicitly selected Area's answers only.
 *
 * $answers must contain Area-owned field keys, not the entire Place form.
 * An omitted key is unchanged. An empty/unanswered value clears its override
 * and restores inheritance. Unknown, No, false, and zero remain explicit.
 *
 * The caller owns the surrounding transaction and audit logging.
 *
 * @param array<string, mixed> $answers
 */
function admin_place_save_area_answers(
    PDO $db,
    int $placeId,
    int $areaId,
    array $answers
): int {
    llama_report_verified_target(
        $db,
        $placeId,
        'area',
        'map_feature',
        $areaId
    );

    $fields = admin_place_area_fields();
    $unknownToken = llama_place_report_unknown_token();
    $unansweredToken = llama_place_report_unanswered_token();
    $saved = 0;

    foreach ($answers as $key => $raw) {
        if (!is_string($key) || !array_key_exists($key, $fields)) {
            throw new InvalidArgumentException(
                'An invalid Area reporting field was submitted.'
            );
        }

        $field = $fields[$key];

        if ($raw === null || $raw === '' || $raw === $unansweredToken) {
            llama_scoped_report_clear_override($db, $placeId, 'area', $areaId, 'map_feature', $key);
            continue;
        }
        // The shared scoped writer owns normalization and answer state.
        llama_scoped_report_save(
            $db,
            $placeId,
            'area',
            $areaId,
            'map_feature',
            $key,
            $raw
        );

        $saved++;
    }

    return $saved;
}
