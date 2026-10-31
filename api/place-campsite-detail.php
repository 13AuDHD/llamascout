<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';
require_once dirname(__DIR__) . '/app/place-campsite-media.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__) . '/app/place-report.php';
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

try {
    $db = db();
    $site = llama_place_campsite($db, $placeId, $campsiteId);
    if (!$site) {
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Site not found.'], 404);
    }

    $publicSiteIds = $canRead ? [] : llama_site_public_visibility_ids($db, $placeId);
    if (!llama_site_can_read($publicPlace, $site, $canRead, $publicSiteIds)) {
        // Do not confirm that a restricted dispersed Site ID exists.
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Site not found.'], 404);
    }

    // This endpoint includes full Scout answers, media and immutable history.
    // A Site's public catalog listing is not permission to disclose that
    // richer member-only payload. Public detail needs its own field whitelist.
    if (!$canRead) {
        llama_place_campsite_detail_json(
            ['ok' => false, 'error' => 'Complete Place access is required.'], 403
        );
    }

    // Authorized Site history is read directly from immutable approved reports.
    // This mode never changes the existing Site detail/report response shape.
    if (($_GET['mode'] ?? '') === 'history') {
        $target = llama_report_verified_target(
            $db, $placeId, 'site', 'campsite_record', $campsiteId
        );
        $history = llama_scout_report_target_history(
            $db,
            $placeId,
            (string) $target['scope'],
            (string) $target['source'],
            (int) $target['id']
        );
        llama_place_campsite_detail_json([
            'ok' => true,
            'siteId' => $campsiteId,
            'reports' => $history,
        ]);
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

    $currentModel =
        llama_place_report_current_answer_model_with_history(
            $db,
            $placeId,
            [
                'scope' =>
                    'site',

                'source' =>
                    'campsite_record',

                'id' =>
                    $campsiteId,
            ]
        );

    $currentAnswers =
        (array) (
            $currentModel['answers']
            ?? []
        );

    $inheritedValues =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'inherited'
        );

    /*
     * Existing JavaScript expects report_override_values to contain every
     * Site-local value, including campsite baseline values. Preserve that
     * response contract while sourcing it from the canonical answer model.
     */
    $localValues =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'local'
        );

    $effectiveValues =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'effective'
        );

    // Same attribution service as the Place report. Only sources matched
    // to the currently published observation are eligible for display.
    $site['report_answer_sources'] = llama_report_answer_sources($db, $currentAnswers);

    // Final published projection is authoritative over legacy campsite
    // columns. The browser uses the same winners as answer attribution.
    $site['report_effective_values'] =
        llama_scoped_report_display_values(
            $effectiveValues
        );

    // Include the full eligible question inventory, including unanswered
    // keys. Otherwise the browser can resurrect an obsolete value from a
    // legacy campsite column when the canonical answer is unanswered.
    $site['report_answer_states'] = [];
    foreach ($currentAnswers as $key => $answer) {
        $site['report_answer_states'][(string) $key] =
            (string) ($answer['state'] ?? 'unanswered');
    }

    $site['report_inherited_values'] =
        llama_scoped_report_display_values(
            $inheritedValues
        );

    $site['report_override_values'] =
        llama_scoped_report_display_values(
            $localValues
        );

    $site['report_override_keys'] =
        array_keys(
            $localValues
        );

    $site['report_warnings'] =
        llama_scoped_report_quick_warnings(
            $effectiveValues
        );

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
