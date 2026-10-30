<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
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
if (!$canRead) {
    llama_place_campsite_detail_json(['ok' => false, 'error' => 'Complete Place access is required.'], 403);
}

try {
    $db = db();
    $site = llama_place_campsite($db, $placeId, $campsiteId);
    if (!$site) {
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Site not found.'], 404);
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

    // Resolve public contributor usernames and imported source labels.
    // Only data already permitted by the Site access gate is returned.
    $contributorIds = [];
    foreach ($currentAnswers as $answer) {
        $id = (int) ($answer['approved_report']['contributor_id'] ?? 0);
        if ($id > 0) {
            $contributorIds[$id] = $id;
        }
    }
    $usernames = [];
    if ($contributorIds) {
        $placeholders = implode(',', array_fill(0, count($contributorIds), '?'));
        $userStmt = $db->prepare(
            'SELECT id, username FROM users WHERE id IN (' . $placeholders . ')'
        );
        $userStmt->execute(array_values($contributorIds));
        while ($userRow = $userStmt->fetch(PDO::FETCH_ASSOC)) {
            $usernames[(int) $userRow['id']] = (string) $userRow['username'];
        }
    }

    $site['report_answer_sources'] = [];
    foreach ($currentAnswers as $fieldKey => $answer) {
        $report = $answer['approved_report'] ?? null;
        if (is_array($report)) {
            $memberId = (int) ($report['contributor_id'] ?? 0);
            $username = trim((string) ($usernames[$memberId] ?? ''));
            $site['report_answer_sources'][(string) $fieldKey] = [
                'visitDate' => $report['visit_date'] ?? null,
                'submittedAt' => $report['submitted_at'] ?? null,
                'label' => $username !== '' ? $username : 'Former member',
                'kind' => 'member',
            ];
            continue;
        }
        if (!empty($answer['imported'])) {
            $origin = (array) ($answer['origin'] ?? []);
            $provider = trim((string) ($origin['provider'] ?? ''));
            if ($provider !== '') {
                $site['report_answer_sources'][(string) $fieldKey] = [
                    'label' => $provider,
                    'kind' => 'source',
                    'visitDate' => null,
                    'submittedAt' => null,
                    'sourceUpdatedAt' => $origin['source_updated_at'] ?? null,
                ];
            }
        }
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
