<?php

declare(strict_types=1);

/**
 * Shared read-only form model for an existing Place, Area, or Site.
 *
 * This is deliberately not an HTTP endpoint or an authorization layer.
 * Calling code must authenticate and authorize the Scout before rendering
 * data, and separately authorize, CSRF-check and validate any writes.
 */
require_once __DIR__ . '/report-targets.php';
require_once __DIR__ . '/scoped-answers.php';

/** @return array<string, mixed> */
function llama_scoped_report_form_model(PDO $db, int $placeId, string $scope = 'place',
    string $source = 'place', ?int $scopeId = null): array
{
    require_once __DIR__ . '/target-model.php';
    $targets = llama_report_targets($db, $placeId);
    $id = $scope === 'place' ? $placeId : (int) $scopeId;
    $target = llama_report_verified_target($db, $placeId, $scope, $source, $id);
    $fields = llama_report_questions_for_scope($scope, true);
    $parent = null;
    if (!empty($target['parent_area_id'])) {
        $parent = llama_report_verified_target($db, $placeId, 'area', 'map_feature', (int) $target['parent_area_id']);
    }
    if ($scope === 'place') {
        // Same effective canonical Place observations used by the public
        // report and scoped inheritance, including V3-only questions.
        $values = [];
        foreach (llama_place_report_current_place_answers($db, $placeId) as $key => $answer) {
            if (empty($answer['recorded']) || !isset($fields[$key])) {
                continue;
            }
            $values[$key] = llama_place_report_current_answer_legacy_value($answer);
        }
        $model = ['values' => $values, 'direct' => $values,
            'overrides' => $values, 'area' => [], 'inherited' => [],
            'provenance' => []];
    } else {
        $model = llama_place_report_target_model($db, $placeId, $target);
    }
    return ['place_id'=>$placeId, 'targets'=>$targets, 'selected'=>$target, 'parent_area'=>$parent,
        'fields'=>$fields, 'explicit_answers'=>array_intersect_key($model['overrides'], $fields),
        'baseline_answers'=>array_intersect_key($model['direct'], $fields),
        'parent_area_answers'=>$model['area'], 'effective_answers'=>$model['values'],
        'inherited_answers'=>$model['inherited'], 'provenance'=>$model['provenance'], 'place_answers_loaded'=>true];
}
