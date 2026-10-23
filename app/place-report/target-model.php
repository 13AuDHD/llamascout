<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/place-report.php';
require_once __DIR__ . '/scoped-answers.php';
require_once __DIR__ . '/report-targets.php';
require_once __DIR__ . '/campsite-baseline.php';
require_once dirname(__DIR__) . '/place-campsites.php';

/** Compose a resolved Site with its imported baseline without writing either. */
function llama_place_report_with_campsite_baseline(array $resolved, array $site): array
{
    $resolved['overrides'] = (array) ($resolved['direct'] ?? []);
    $resolved['direct'] = llama_place_report_campsite_answers($site, $resolved['overrides']);
    $resolved['values'] = array_replace((array) ($resolved['inherited'] ?? []), $resolved['direct']);
    foreach ($resolved['direct'] as $key => $_value) {
        $resolved['provenance'][$key] = ['scope' => 'site', 'source' => 'campsite_record', 'id' => (int) $site['id']];
    }
    return $resolved;
}

/** Caller verifies authentication, access and target; this service owns answer composition. */
function llama_place_report_target_model(PDO $db, int $placeId, array $target): array
{
    $data = [];
    foreach (llama_place_report_current_values($db, $placeId) as $path => $value) {
        llama_place_report_set_path($data, $path, $value);
    }
    $data['_answer_state'] = llama_place_report_published_answer_state($db, $placeId);
    $scope = (string) $target['scope'];
    $source = (string) $target['source'];
    $id = (int) $target['id'];
    $resolved = llama_scoped_report_effective_answers($db, $placeId,
        llama_scoped_report_values_from_data($data), $scope, $id, $source,
        !empty($target['parent_area_id']) ? (int) $target['parent_area_id'] : null);
    $resolved['overrides'] = $resolved['direct'];
    if ($scope === 'site' && $source === 'campsite_record') {
        $site = llama_place_campsite($db, $placeId, $id);
        if (!$site) { throw new InvalidArgumentException('Site not found.'); }
        $resolved = llama_place_report_with_campsite_baseline($resolved, $site);
    }
    return $resolved;
}
