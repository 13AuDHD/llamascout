<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-campsite-media.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/target-model.php';
require_once dirname(__DIR__) . '/app/place-report/report-targets.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-warnings.php';

header('Content-Type: application/json; charset=utf-8');

function llama_place_campsite_detail_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Avoid transferring the same Site photograph repeatedly when an import
 * has created multiple image rows referencing the same asset.
 *
 * Distinct images remain untouched. Records aren't deleted or changed.
 */
function llama_place_campsite_detail_unique_images(array $images): array
{
    $unique = [];
    $seen = [];

    foreach ($images as $image) {
        if (!is_array($image)) {
            continue;
        }

        $src = trim((string) ($image['src'] ?? ''));
        if ($src === '') {
            continue;
        }

        // The same URL may occur with a different fragment or query ordering.
        // Ignore fragment identifiers, but preserve query parameters since they
        // can identify legitimately different image variants/assets.
        $withoutFragment = explode('#', $src, 2)[0];
        $key = strtolower($withoutFragment);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $unique[] = $image;
    }

    return $unique;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    llama_place_campsite_detail_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$campsiteId = max(0, (int) ($_GET['site'] ?? 0));
if ($slug === '' || $campsiteId < 1) {
    llama_place_campsite_detail_json(['ok' => false, 'error' => 'A Place and Site are required.'], 400);
}

$publicPlace = place_public_by_slug($slug);
if (!$publicPlace) {
    llama_place_campsite_detail_json(['ok' => false, 'error' => 'Place not found.'], 404);
}

$placeId = (int) ($publicPlace['id'] ?? 0);
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$canRead = $userId > 0 && user_has_place_complete_access($placeId, $userId);
if (!$canRead) {
    llama_place_campsite_detail_json(['ok' => false, 'error' => 'Complete Place access is required.'], 403);
}

try {
    $db = db();
    $site = llama_place_campsite($db, $placeId, $campsiteId);
    if (!$site) {
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Site not found.'], 404);
    }

    // Media and report consumers continue using their existing response shape.
    if (($_GET['mode'] ?? '') !== 'report') {
        $images = llama_place_campsite_images($db, $placeId, $campsiteId);

        llama_place_campsite_detail_json([
            'ok' => true,
            'site' => $site,
            'images' => llama_place_campsite_detail_unique_images($images),
        ]);
    }

    $place = place_member_by_slug($slug);
    if (!$place) {
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Place not found.'], 404);
    }

    $enriched = llama_campsite_closure_enrich($db, $placeId, [$site]);
    $site = $enriched[0] ?? $site;

    $unknown = llama_place_report_published_answer_state($db, $placeId);
    $reportData = llama_place_report_data_from_published_place($place, $unknown);
    $placeValues = llama_scoped_report_values_from_data($reportData);

    $target = null;
    foreach (llama_report_targets($db, $placeId) as $candidate) {
        if (
            (string) ($candidate['scope'] ?? '') === 'site'
            && (string) ($candidate['source'] ?? '') === 'campsite_record'
            && (int) ($candidate['id'] ?? 0) === $campsiteId
        ) {
            $target = $candidate;
            break;
        }
    }

    $resolved = null;
    if (is_array($target)) {
        $reports = llama_scoped_report_effective_answers_for_targets(
            $db, $placeId, $placeValues, [$target]
        );
        $resolved = $reports['site:campsite_record:' . $campsiteId] ?? null;
    }

    if (is_array($resolved)) {
        $resolved = llama_place_report_with_campsite_baseline($resolved, $site);
    }
    $site['report_inherited_values'] = is_array($resolved)
        ? llama_scoped_report_display_values((array) ($resolved['inherited'] ?? [])) : [];
    $site['report_override_values'] = is_array($resolved)
        ? llama_scoped_report_display_values((array) ($resolved['direct'] ?? [])) : [];
    $site['report_override_keys'] = is_array($resolved)
        ? array_keys((array) ($resolved['direct'] ?? [])) : [];
    $site['report_warnings'] = is_array($resolved)
        ? llama_scoped_report_quick_warnings((array) ($resolved['values'] ?? [])) : [];

    $mapFeatureId = (int) ($site['map_feature_id'] ?? 0);
    $rates = [];
    if ($mapFeatureId > 0) {
        foreach (llama_place_map_features($db, $placeId, true) as $feature) {
            if (
                (int) ($feature['id'] ?? 0) === $mapFeatureId
                && (string) ($feature['feature_type'] ?? '') === 'camping_site'
            ) {
                $rates = (array) ($feature['effective_rates'] ?? $feature['rates'] ?? []);
                break;
            }
        }
    }

    $effectiveRates = llama_place_map_effective_rates_with_place_base(
        $rates, $place['rules']['fee'] ?? null
    );
    $site['effective_rates'] = $effectiveRates;
    $site['effective_rate_summary'] = llama_place_map_feature_rate_summary($effectiveRates);
    $site['pricing_summary'] = llama_place_map_rate_summary_label($effectiveRates);
    $site['summary'] = array_values(array_unique(array_filter([
        $site['pricing_summary'], ...llama_place_campsite_summary($site),
    ], static fn ($value): bool => trim((string) $value) !== '')));

    llama_place_campsite_detail_json([
        'ok' => true,
        'site' => $site,
        'images' => [],
    ]);
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'place.campsite.detail.api', [
        'place_id' => $placeId,
        'campsite_id' => $campsiteId,
        'user_id' => $userId,
    ]);
    llama_place_campsite_detail_json([
        'ok' => false,
        'error' => 'Site details could not be loaded.',
    ], 500);
}
