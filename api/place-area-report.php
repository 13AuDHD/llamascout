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
        $seasonId = (int) $season['id'];
        $placeTarget = ['scope' => 'place', 'source' => 'place', 'id' => $placeId];
        $seasonModel = llama_public_season_overlay_answer_layers(
            $answers,
            [
                [
                    'scope' => 'place',
                    'target' => $placeTarget,
                    'answers' => llama_public_season_place_conditions(
                        $db, $placeId, $seasonId
                    ),
                ],
                [
                    'scope' => 'area',
                    'target' => $target,
                    'answers' => llama_season_answers_for_target(
                        $db, $placeId, $seasonId, $target
                    ),
                ],
            ],
            $target
        );
        $answers = (array) ($seasonModel['answers'] ?? $answers);
        $seasonSources = (array) ($seasonModel['sources'] ?? []);
    }

    $projection = llama_public_scoped_report_projection(
        $answers, $target, (string) ($place['type'] ?? ''),
        array_replace(llama_report_answer_sources($db, $answers), $seasonSources)
    );

    llama_public_area_report_json([
        'ok' => true,
        'area' => array_replace([
            'id' => $areaId,
            'report_target_scope' => 'area',
            'display_name' => (string) ($target['label'] ?? $target['name'] ?? ''),
        ], $projection),
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
