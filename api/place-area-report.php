<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/target-model.php';
require_once dirname(__DIR__) . '/app/place-report/season-public.php';

header('Content-Type: application/json; charset=utf-8');

function llama_public_area_report_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    llama_public_area_report_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$areaId = (int) ($_GET['area'] ?? 0);

if ($slug === '' || $areaId < 1) {
    llama_public_area_report_json(['ok' => false, 'error' => 'A Place and Area are required.'], 400);
}

$place = place_public_by_slug($slug);
if (!$place) {
    llama_public_area_report_json(['ok' => false, 'error' => 'Place not found.'], 404);
}

$placeId = (int) ($place['id'] ?? 0);
$userId = (int) ((current_user()['id'] ?? 0));

// Follow the same Complete Access boundary as the existing Site report API.
// The map/browser can name an Area without authorizing its report answers.
if ($userId < 1 || !user_has_place_complete_access($placeId, $userId)) {
    llama_public_area_report_json(
        ['ok' => false, 'error' => 'Complete Place access is required.'],
        403
    );
}

try {
    $db = db();
    $target = llama_report_verified_target(
        $db, $placeId, 'area', 'map_feature', $areaId
    );

    $model = llama_place_report_current_answer_model_with_history(
        $db, $placeId, $target
    );
    $answers = (array) ($model['answers'] ?? []);

    $seasonSources = [];
    $seasonSelection = llama_public_season_selection(
        $db, $placeId, $_GET['season'] ?? null
    );
    $season = $seasonSelection['selected'] ?? null;
    if (is_array($season)) {
        $seasonModel = llama_public_season_site_answers(
            $db, $placeId, (int) $season['id'], $target, $answers
        );
        $answers = (array) ($seasonModel['answers'] ?? $answers);
        $seasonSources = (array) ($seasonModel['sources'] ?? []);
    }

    $effective = llama_place_report_current_answer_values($answers, 'effective');
    $local = llama_place_report_current_answer_values($answers, 'local');
    $inherited = llama_place_report_current_answer_values($answers, 'inherited');

    $inapplicable = [];
    $applicability = ['type' => (string) ($place['type'] ?? '')];
    foreach ($effective as $key => $value) {
        $applicability[(string) $key] = $value;
    }
    foreach (llama_report_questions_for_scope('area', true) as $key => $field) {
        if (!llama_report_question_applies_to_area_target($field, $target)
            || (!empty($field['applicable_if'])
                && !llama_place_report_question_applicable($applicability, (string) $key))) {
            $inapplicable[] = (string) $key;
        }
    }

    $states = [];
    foreach ($answers as $key => $answer) {
        $states[(string) $key] = (string) ($answer['state'] ?? 'unanswered');
    }

    llama_public_area_report_json([
        'ok' => true,
        'area' => [
            'id' => $areaId,
            'display_name' => (string) ($target['label'] ?? $target['name'] ?? ''),
            'report_effective_values' => llama_scoped_report_display_values($effective),
            'report_override_values' => llama_scoped_report_display_values($local),
            'report_inherited_values' => llama_scoped_report_display_values($inherited),
            'report_answer_states' => $states,
            'report_answer_sources' => array_replace(
                llama_report_answer_sources($db, $answers),
                $seasonSources
            ),
            'report_inapplicable_keys' => $inapplicable,
        ],
    ]);
} catch (InvalidArgumentException $exception) {
    llama_public_area_report_json(['ok' => false, 'error' => 'Area not found.'], 404);
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'place.area.report.api', [
        'place_id' => $placeId, 'area_id' => $areaId, 'user_id' => $userId,
    ]);
    llama_public_area_report_json(
        ['ok' => false, 'error' => 'Area report could not be loaded.'],
        500
    );
}
