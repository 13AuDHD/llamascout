<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-pricing.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';
require_once dirname(__DIR__) . '/app/place-campsite-media.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-warnings.php';
require_once dirname(__DIR__) . '/app/place-report/season-public.php';
require_once dirname(__DIR__) . '/app/place-report/site-booking.php';

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
$mode = strtolower(trim((string) ($_GET['mode'] ?? '')));

if (!in_array($mode, ['', 'media', 'report', 'history'], true)) {
    llama_place_campsite_detail_json(
        ['ok' => false, 'error' => 'Invalid Site detail mode.'],
        400
    );
}

try {
    $db = db();
    $site = llama_place_campsite($db, $placeId, $campsiteId);
    if (!$site) {
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Site not found.'], 404);
    }

    $restrictedSiteIds = $canRead ? [] : llama_site_restricted_visibility_ids($db, $placeId);
    if (!llama_site_can_read($publicPlace, $site, $canRead, $restrictedSiteIds)) {
        // Do not confirm that a restricted dispersed Site ID exists.
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Site not found.'], 404);
    }

    /*
     * Site media has its own access tier.
     *
     * A visible public Site may expose photo previews to Guests and allow any
     * signed-in Member to open those photos. Full Scout answers and immutable
     * history still require Complete Place access.
     */
    if ($mode === '' || $mode === 'media') {
        $images =
            llama_place_campsite_images(
                $db,
                $placeId,
                $campsiteId
            );

        $sitePayload =
            $canRead
                ? $site
                : [
                    'id' => (int) ($site['id'] ?? 0),
                    'display_name' => (string) ($site['display_name'] ?? ''),
                    'site_code' => (string) ($site['site_code'] ?? ''),
                    'site_name' => (string) ($site['site_name'] ?? ''),
                ];

        llama_place_campsite_detail_json([
            'ok' => true,
            'site' => $sitePayload,
            'images' =>
                llama_place_campsite_detail_unique_images(
                    $images
                ),
            'photo_access' => [
                'can_preview' => true,
                'can_open' => $userId > 0,
            ],
        ]);
    }

    if (!$canRead) {
        llama_place_campsite_detail_json(
            ['ok' => false, 'error' => 'Complete Place access is required.'],
            403
        );
    }

    // Authorized Site history is read directly from immutable approved reports.
    // This mode never changes the existing Site detail/report response shape.
    if ($mode === 'history') {
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

    $place = place_member_by_slug($slug);
    if (!$place) {
        llama_place_campsite_detail_json(['ok' => false, 'error' => 'Place not found.'], 404);
    }

    $enriched = llama_campsite_closure_enrich($db, $placeId, [$site]);
    $site = $enriched[0] ?? $site;
    $placeClosure = llama_public_place_active_closure($db, $placeId);
    $site['place_verified_closed'] = $placeClosure !== null;
    if ($placeClosure !== null) {
        $site['is_closed'] = true;
        if (($site['operating_status'] ?? 'open') === 'open') {
            $site['operating_status'] = $placeClosure['status'];
            $site['closure_reason'] = $placeClosure['reason'];
            $site['closure_began'] = $placeClosure['closure_began'];
            $site['expected_reopening'] = $placeClosure['expected_reopening'];
            $site['closure_verified_at'] = $placeClosure['verified_at'];
        }
    }

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

    // Positive official RIDB reservability is a Site baseline fact.
    // Keep its source provenance and never overwrite a canonical Site answer.
    if (empty($currentAnswers['site_reservation_method']['recorded'])) {
        $normalBooking = llama_public_site_booking_methods(
            $db, $placeId, null, [$campsiteId]
        );
        if (($normalBooking[$campsiteId] ?? null) === 'reservable') {
            $field = llama_report_question('site_reservation_method');
            if (is_array($field)) {
                $currentAnswers['site_reservation_method'] =
                    llama_place_report_current_answer_envelope(
                        $field,
                        true,
                        'reservable',
                        [
                            'scope' => 'site',
                            'source' => 'campsite_record',
                            'id' => $campsiteId,
                        ],
                        [
                            'scope' => 'site',
                            'source' => 'campsite_record',
                            'id' => $campsiteId,
                            'origin_type' => 'campsite_baseline',
                            'imported' => true,
                            'provider' => 'Recreation.gov / RIDB',
                            'source_updated_at' => $site['source_updated_at'] ?? null,
                        ]
                    );
            }
        }
    }



    $seasonSpecificSources = [];
    $seasonState = llama_public_season_selection(
        $db, $placeId, $_GET['season'] ?? null
    );
    $activeSeason = $seasonState['selected'] ?? null;
    if (is_array($activeSeason)) {
        $seasonId = (int) $activeSeason['id'];
        $seasonalSite = llama_public_season_site_answers(
            $db, $placeId, $seasonId,
            (array) ($currentModel['target'] ?? []),
            $currentAnswers
        );
        $currentAnswers = $seasonalSite['answers'];
        $seasonSpecificSources = (array) ($seasonalSite['sources'] ?? []);
        $seasonSiteStatuses = llama_public_season_site_statuses(
            $db, $placeId, $seasonId, [$campsiteId]
        );
        $site['seasonal_operating_status'] =
            $seasonSiteStatuses[$campsiteId] ?? null;
    } elseif (!empty($seasonState['seasons'])
        && (string) ($_GET['season'] ?? '') !== '0') {
        // No active season: do not show this Site as currently open.
        $site['seasonal_operating_status'] = 'closed';
    }



    $effectiveValues =
        llama_place_report_current_answer_values(
            $currentAnswers,
            'effective'
        );

    // One canonical report projection for Site and mapped Facility. The
    // report_inapplicable_keys list also prevents legacy client fallback.
    // Keep raw effective answers privately for the separate warning engine.
    $site = array_replace($site, llama_public_scoped_report_projection(
        $currentAnswers,
        ['scope' => 'site', 'source' => 'campsite_record', 'id' => $campsiteId],
        (string) ($place['type'] ?? $publicPlace['type'] ?? ''),
        array_replace(llama_report_answer_sources($db, $currentAnswers), $seasonSpecificSources)
    ));

    $site['report_warnings'] =
        llama_scoped_report_quick_warnings(
            $effectiveValues
        );

    [$rateDoc, $rateDate] = llama_pricing_public_night(
        llama_pricing_document($db, $place), $activeSeason, $_GET['season'] ?? null
    );
    $site['summary'] = llama_place_campsite_summary($site);
    $site = llama_pricing_enrich_sites($db, $place, [$site], $rateDoc, $rateDate)[0];

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
