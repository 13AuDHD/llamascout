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
require_once __DIR__ . '/place-report/target-model.php';

/** @return array<string, array<string, mixed>> */
function admin_place_area_fields(?array $verifiedTarget = null): array
{
    $fields = $verifiedTarget === null
        ? llama_report_questions_for_scope('area', true)
        : llama_report_questions_for_area_target($verifiedTarget);

    return array_filter(
        $fields,
        static fn (array $field): bool => empty($field['location_field'])
    );
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
            'feature_type' => (string) ($target['feature_type'] ?? ''),
            'facility_kind' => (string) ($target['facility_kind'] ?? ''),
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
 * Validate the complete prospective Facility observation before any writes.
 * A kind change must not erase old kind-specific answers; only explicitly
 * submitted, eligible keys may be changed. Clear operations restore the
 * canonical inherited value, not an invented default.
 */
function admin_place_plan_area_answers(array $target, array $model, array $answers): array
{
    $fields = admin_place_area_fields($target);
    $effective = (array) ($model['values'] ?? []);
    $inherited = (array) ($model['inherited'] ?? []);
    $direct = (array) ($model['direct'] ?? []);
    $writes = [];
    $newKeys = [];
    $unanswered = llama_place_report_unanswered_token();
    foreach ($answers as $key => $raw) {
        if (!is_string($key) || !isset($fields[$key])) {
            throw new InvalidArgumentException('An invalid Area reporting field was submitted.');
        }
        if ($raw === null || $raw === '' || $raw === $unanswered) {
            if (array_key_exists($key, $direct)) { $writes[$key] = $unanswered; }
            unset($effective[$key]);
            if (array_key_exists($key, $inherited)) { $effective[$key] = $inherited[$key]; }
            continue;
        }
        $answer = llama_place_report_submission_answer($fields[$key], $raw);
        if (!$answer['recorded']) {
            throw new InvalidArgumentException('Clear the Facility override explicitly instead.');
        }
        $value = $answer['scoped_value'];
        if (array_key_exists($key, $direct) && llama_scoped_report_values_equal($direct[$key], $value)) {
            continue;
        }
        $effective[$key] = $value;
        $writes[$key] = $value;
        $newKeys[] = $key;
    }
    $input = [];
    foreach ($effective as $key => $value) {
        $field = llama_report_question((string) $key);
        if ($field !== null) { $input[$key] = llama_place_report_answer_form_value($field, $value); }
    }
    foreach ($newKeys as $key) {
        if (!llama_place_report_question_applicable($input, $key)) {
            throw new InvalidArgumentException(($fields[$key]['label'] ?? $key)
                . ' does not apply to the selected Facility. Review the parent answers.');
        }
    }
    return $writes;
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
    $verifiedTarget = llama_report_verified_target(
        $db,
        $placeId,
        'area',
        'map_feature',
        $areaId
    );

    // Metadata is saved first by the enclosing Admin transaction; verify
    // the updated kind and effective parent answers, not stale page data.
    $answers = admin_place_plan_area_answers($verifiedTarget,
        llama_place_report_target_model($db, $placeId, $verifiedTarget), $answers);
    $fields = admin_place_area_fields($verifiedTarget);
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
