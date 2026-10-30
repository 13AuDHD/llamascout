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
    $resolved['overrides'] =
        (array) ($resolved['direct'] ?? []);

    $resolved['baseline'] =
        llama_place_report_campsite_answers(
            $site
        );

    $resolved['direct'] =
        array_replace(
            $resolved['baseline'],
            $resolved['overrides']
        );

    $resolved['values'] =
        array_replace(
            (array) ($resolved['inherited'] ?? []),
            $resolved['direct']
        );

    $siteId =
        (int) ($site['id'] ?? 0);

    $provider =
        trim(
            (string) (
                $site['source_provider']
                ?? ''
            )
        );

    $externalId =
        trim(
            (string) (
                $site['source_external_id']
                ?? ''
            )
        );

    foreach ($resolved['direct'] as $key => $_value) {
        $isOverride =
            array_key_exists(
                $key,
                $resolved['overrides']
            );

        $baselineImported =
            !$isOverride
            && $provider !== ''
            && !str_contains(
                strtolower($provider),
                'llama scout'
            );

        $featureSource = null;
        if (!$isOverride) {
            // Only fields read directly from an imported feature can inherit
            // that feature's own source date. Site-level timestamps are not
            // evidence of when a specific fact was observed.
            foreach ((array) ($site['features'] ?? []) as $feature) {
                if ((string) ($feature['feature_key'] ?? '') !== (string) $key
                    || (string) ($feature['feature_value'] ?? '') !== (string) $_value) {
                    continue;
                }
                $featureProvider = trim((string) ($feature['source_provider'] ?? ''));
                if ($featureProvider === '' || str_contains(strtolower($featureProvider), 'llama scout')) {
                    continue;
                }
                $featureSource = $feature;
                break;
            }
        }

        $resolved['provenance'][$key] = [
            'scope' =>
                'site',

            'source' =>
                'campsite_record',

            'id' =>
                $siteId,

            'origin_type' =>
                $isOverride
                    ? 'scoped_override'
                    : 'campsite_baseline',

            'imported' =>
                $baselineImported,

            'provider' =>
                !$isOverride
                    ? (trim((string) ($featureSource['source_provider'] ?? '')) ?: ($provider ?: null))
                    : null,

            'external_id' =>
                !$isOverride && $externalId !== ''
                    ? $externalId
                    : null,

            'source_updated_at' =>
                $featureSource['source_updated_at'] ?? null,
        ];
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

    $data =
        llama_place_report_derive_compatibility(
            $data
        );

    $data['_answer_state'] =
        llama_place_report_published_answer_state(
            $db,
            $placeId
        );
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
