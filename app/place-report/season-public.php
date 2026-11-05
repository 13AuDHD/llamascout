<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/place-report.php';
require_once __DIR__ . '/current-answers.php';
require_once __DIR__ . '/season-answers.php';

/**
 * A selection changes the presentation of a canonical Scout Report.
 * It never changes verified closure/visibility decisions or stored answers.
 */
function llama_public_season_selection(
    PDO $db,
    int $placeId,
    mixed $requestedSeason = null,
    ?string $today = null
): array {
    $seasons = llama_seasons_for_place($db, $placeId);
    $selected = null;

    $requested = trim((string) ($requestedSeason ?? ''));
    // A deliberate "Normal report" selection must not be replaced by
    // automatic date matching on the next page load.
    if ($requested === '0') {
        return ['seasons' => $seasons, 'selected' => null];
    }
    if (preg_match('/^[1-9][0-9]*$/D', $requested) === 1) {
        foreach ($seasons as $season) {
            if ((int) $season['id'] === (int) $requested) {
                $selected = $season;
                break;
            }
        }
    }
    if ($selected === null && $seasons) {
        $selected = llama_season_for_date(
            $seasons, $today ?? date('Y-m-d')
        );
    }
    return ['seasons' => $seasons, 'selected' => $selected];
}

/** Source marker for a season-specific answer, not an invented Scout visit. */
function llama_public_season_source(array $seasonalAnswer): array
{
    $approved = (string) ($seasonalAnswer['approved_at'] ?? '');
    return [
        'label' => 'Seasonal conditions',
        'dateLabel' => preg_match('/^\d{4}-\d{2}-\d{2}/', $approved)
            ? substr($approved, 0, 10) : '',
    ];
}

/** Project Place-season answers only into their existing canonical paths. */
function llama_public_season_project_place(
    array $data,
    array $overrides
): array {
    $unknown = array_values(
        array_unique(array_map('strval', (array) ($data['_answer_state'] ?? [])))
    );
    foreach ($overrides as $key => $answer) {
        $field = llama_season_question_for_scope((string) $key, 'place');
        $path = (string) ($field['storage'] ?? '');
        if ($path === '') {
            continue;
        }
        if ((string) ($answer['state'] ?? '') === 'unknown') {
            llama_place_report_set_path($data, $path, null);
            $unknown[] = (string) $key;
        } else {
            llama_place_report_set_path($data, $path, $answer['value']);
            $unknown = array_values(array_filter(
                $unknown, static fn (string $item): bool => $item !== $key
            ));
        }
    }
    $data['_answer_state'] = array_values(array_unique($unknown));
    return $data;
}

/**
 * Pure, scope-aware answer projection:
 * a Site-specific normal answer beats an inherited seasonal Place/Area change.
 * Site-season beats Site-normal; Area-season beats Area-normal; and so on.
 * No inactive season or unrelated Site enters this function.
 */
function llama_public_season_overlay_answer_layers(
    array $normalAnswers,
    array $layers,
    array $target
): array {
    $scopeRank = ['place' => 1, 'area' => 2, 'site' => 3];
    $sources = [];
    foreach ($layers as $layer) {
        $scope = (string) ($layer['scope'] ?? '');
        if (!isset($scopeRank[$scope])) {
            continue;
        }
        foreach ((array) ($layer['answers'] ?? []) as $key => $seasonal) {
            $field = llama_season_question_for_scope((string) $key, $scope);
            $normal = (array) ($normalAnswers[$key] ?? []);
            $originScope = (string) (($normal['origin'] ?? [])['scope'] ?? '');
            if (!empty($normal['recorded'])
                && ($scopeRank[$originScope] ?? 0) > $scopeRank[$scope]) {
                continue;
            }
            $state = (string) ($seasonal['state'] ?? '');
            if (!in_array($state, ['answered', 'unknown'], true)) {
                continue;
            }
            $sourceTarget = (array) ($layer['target'] ?? []);
            $raw = $state === 'unknown'
                ? llama_place_report_unknown_token()
                : ($seasonal['value'] ?? null);
            $normalAnswers[$key] = llama_place_report_current_answer_envelope(
                $field, true, $raw, $target,
                [
                    'scope' => $scope,
                    'source' => (string) ($sourceTarget['source'] ?? ''),
                    'id' => (int) ($sourceTarget['id'] ?? 0),
                    'origin_type' => 'seasonal_override',
                ]
            );
            $sources[$key] = llama_public_season_source($seasonal);
        }
    }
    return ['answers' => $normalAnswers, 'sources' => $sources];
}

/** The same canonical Place -> Area -> Site targets used by normal reporting. */
function llama_public_season_site_answers(
    PDO $db, int $placeId, int $seasonId,
    array $target, array $normalAnswers
): array {
    $verified = llama_report_verified_target(
        $db, $placeId, 'site',
        (string) ($target['source'] ?? ''),
        (int) ($target['id'] ?? 0)
    );
    $layers = [];
    $placeTarget = ['scope' => 'place', 'source' => 'place', 'id' => $placeId];
    $layers[] = [
        'scope' => 'place', 'target' => $placeTarget,
        'answers' => llama_season_answers_for_target($db, $placeId, $seasonId, $placeTarget),
    ];
    $areaId = (int) ($verified['parent_area_id'] ?? 0);
    if ($areaId > 0) {
        $areaTarget = ['scope' => 'area', 'source' => 'map_feature', 'id' => $areaId];
        $layers[] = [
            'scope' => 'area', 'target' => $areaTarget,
            'answers' => llama_season_answers_for_target($db, $placeId, $seasonId, $areaTarget),
        ];
    }
    $layers[] = [
        'scope' => 'site', 'target' => $verified,
        'answers' => llama_season_answers_for_target($db, $placeId, $seasonId, $verified),
    ];

    return llama_public_season_overlay_answer_layers($normalAnswers, $layers, $verified);
}

/**
 * Approved Site operating schedule for already-visible canonical campsite IDs.
 * No Place/Site discovery or authorization occurs here; callers filter first.
 */
function llama_public_season_site_statuses(
    PDO $db, int $placeId, int $seasonId, array $visibleSiteIds
): array {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $visibleSiteIds),
        static fn (int $id): bool => $id > 0
    )));
    if (!$ids || $seasonId < 1) {
        return [];
    }
    llama_season_verified_place($db, $placeId, $seasonId);
    $statuses = [];
    foreach (array_chunk($ids, 400) as $batch) {
        $marks = implode(',', array_fill(0, count($batch), '?'));
        $query = $db->prepare(
            'SELECT target_id, operating_status
             FROM place_season_site_status
             WHERE place_id = ? AND season_id = ?
               AND target_source = ? AND target_id IN (' . $marks . ')'
        );
        $query->execute([$placeId, $seasonId, 'campsite_record', ...$batch]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $status = (string) ($row['operating_status'] ?? '');
            if (in_array($status, ['open', 'closed'], true)) {
                $statuses[(int) $row['target_id']] = $status;
            }
        }
    }
    return $statuses;
}
