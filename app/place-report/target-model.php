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

        $featureSource = null;
        if (!$isOverride) {
            // Feature-backed questions can have their own provider and date.
            // The remaining imported baseline questions use the Site facts
            // record and its source_updated_at value.
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

        $effectiveProvider = !$isOverride
            ? (trim((string) ($featureSource['source_provider'] ?? '')) ?: $provider)
            : '';

        $baselineImported =
            !$isOverride
            && $effectiveProvider !== ''
            && !str_contains(strtolower($effectiveProvider), 'llama scout');

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
                $baselineImported
                    ? ($featureSource['source_updated_at'] ?? $site['source_updated_at'] ?? null)
                    : null,
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
    $placeValues = llama_scoped_report_values_from_data($data);
    // Use the same current Place answers as the standalone Place reader.
    // Missing unified rows continue to inherit the existing published facts.
    if (llama_report_current_store_available($db)) {
        foreach (llama_report_current_store_load(
            $db, $placeId, 'place', 'place', $placeId
        ) as $key => $answer) {
            $placeValues[$key] = $answer['state'] === 'unknown'
                ? llama_place_report_unknown_token()
                : $answer['value'];
        }
    }
    $resolved = llama_scoped_report_effective_answers($db, $placeId,
        $placeValues, $scope, $id, $source,
        !empty($target['parent_area_id']) ? (int) $target['parent_area_id'] : null);
    $resolved['overrides'] = $resolved['direct'];
    if ($scope === 'site' && $source === 'campsite_record') {
        $site = llama_place_campsite($db, $placeId, $id);
        if (!$site) { throw new InvalidArgumentException('Site not found.'); }
        $resolved = llama_place_report_with_campsite_baseline($resolved, $site);
    }
    return $resolved;
}
