<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/report-targets.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-warnings.php';

header('Content-Type: application/json; charset=utf-8');

function llama_place_campsites_api_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    llama_place_campsites_api_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    llama_place_campsites_api_json(['ok' => false, 'error' => 'A Place slug is required.'], 400);
}

$publicPlace = place_public_by_slug($slug);
if (!$publicPlace) {
    llama_place_campsites_api_json(['ok' => false, 'error' => 'Place not found.'], 404);
}

$placeId = (int) ($publicPlace['id'] ?? 0);
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$canRead = $userId > 0 && user_has_place_complete_access($placeId, $userId);
if (!$canRead) {
    llama_place_campsites_api_json([
        'ok' => false,
        'error' => 'Complete Place access is required.',
    ], 403);
}

$place = place_member_by_slug($slug);
if (!$place) {
    llama_place_campsites_api_json(['ok' => false, 'error' => 'Place not found.'], 404);
}

try {
    $db = db();
    $sites = llama_place_campsites($db, $placeId);
    $sites = llama_campsite_closure_enrich($db, $placeId, $sites);

    /*
     * Resolve Place -> Area -> Site Scout Report layers in one bulk read.
     *
     * Site facts are still carried on each Site row. The client applies
     * inherited report values first, Site facts second, and direct Site
     * overrides last.
     */
    $publishedUnknownFields =
        llama_place_report_published_answer_state(
            $db,
            $placeId
        );

    $placeReportData =
        llama_place_report_data_from_published_place(
            $place,
            $publishedUnknownFields
        );

    $placeReportValues =
        llama_scoped_report_values_from_data(
            $placeReportData
        );

    $siteTargets =
        [];

    $siteTargetsById =
        [];

    foreach (
        llama_report_targets(
            $db,
            $placeId
        )
        as $target
    ) {
        if (
            (string) (
                $target['scope']
                ?? ''
            ) !== 'site'
            || (string) (
                $target['source']
                ?? ''
            ) !== 'campsite_record'
        ) {
            continue;
        }

        $siteId =
            (int) (
                $target['id']
                ?? 0
            );

        if ($siteId < 1) {
            continue;
        }

        $siteTargets[] =
            $target;

        $siteTargetsById[
            $siteId
        ] =
            $target;
    }

    $resolvedSiteReports =
        llama_scoped_report_effective_answers_for_targets(
            $db,
            $placeId,
            $placeReportValues,
            $siteTargets
        );

    foreach ($sites as &$site) {
        $siteId =
            (int) (
                $site['id']
                ?? 0
            );

        $target =
            $siteTargetsById[
                $siteId
            ]
            ?? null;

        $resolved =
            is_array($target)
                ? (
                    $resolvedSiteReports[
                        'site:campsite_record:'
                        . $siteId
                    ]
                    ?? null
                )
                : null;

        $site['report_inherited_values'] =
            is_array($resolved)
                ? llama_scoped_report_display_values(
                    (array) (
                        $resolved['inherited']
                        ?? []
                    )
                )
                : [];

        $site['report_override_values'] =
            is_array($resolved)
                ? llama_scoped_report_display_values(
                    (array) (
                        $resolved['direct']
                        ?? []
                    )
                )
                : [];

        $site['report_override_keys'] =
            is_array($resolved)
                ? array_values(
                    array_keys(
                        (array) (
                            $resolved['direct']
                            ?? []
                        )
                    )
                )
                : [];

        /*
         * Warning rules stay server-side. These are calculated from the same
         * effective Place -> Area -> Site answers used by the Scout Report.
         */
        $site['report_warnings'] =
            is_array($resolved)
                ? llama_scoped_report_quick_warnings(
                    (array) (
                        $resolved['values']
                        ?? []
                    )
                )
                : [];
    }
    unset($site);

    $baseCampingFee =
        $place['rules']['fee']
        ?? null;

    $mappedSitePricing = [];

    foreach (
        llama_place_map_features(
            $db,
            $placeId,
            true
        )
        as $feature
    ) {
        if (
            (string) (
                $feature['feature_type']
                ?? ''
            ) !== 'camping_site'
        ) {
            continue;
        }

        $featureId =
            (int) (
                $feature['id']
                ?? 0
            );

        if ($featureId < 1) {
            continue;
        }

        $mappedSitePricing[
            $featureId
        ] =
            (array) (
                $feature['effective_rates']
                ?? $feature['rates']
                ?? []
            );
    }

    foreach ($sites as &$site) {
        $mapFeatureId =
            max(
                0,
                (int) (
                    $site['map_feature_id']
                    ?? 0
                )
            );

        $effectiveRates =
            llama_place_map_effective_rates_with_place_base(
                (array) (
                    $mappedSitePricing[
                        $mapFeatureId
                    ]
                    ?? []
                ),
                $baseCampingFee
            );

        $site['effective_rates'] =
            $effectiveRates;

        $site['effective_rate_summary'] =
            llama_place_map_feature_rate_summary(
                $effectiveRates
            );

        $site['pricing_summary'] =
            llama_place_map_rate_summary_label(
                $effectiveRates
            );
    }
    unset($site);

    $facilityFacts = llama_place_facility_facts($db, $placeId);
    $facilityFeatures = llama_place_facility_features($db, $placeId);
    $state = llama_place_campsite_browser_state($place, $sites);
    $selectedFeatureId = max(0, (int) ($_GET['site'] ?? 0));
    $selectedSite = null;

    if ($selectedFeatureId > 0) {
        foreach ($sites as $site) {
            if ((int) ($site['id'] ?? 0) === $selectedFeatureId) {
                $selectedSite = $site;
                break;
            }
        }
    }

    llama_place_campsites_api_json([
        'ok' => true,
        'place' => [
            'id' => $placeId,
            'slug' => (string) $place['slug'],
            'name' => (string) $place['name'],
            'type' => (string) ($place['type'] ?? ''),
            'experience_mode' => $state['mode'],
        ],
        'browser' => $state,
        'facility_facts' => $facilityFacts,
        'facility_features' => $facilityFeatures,
        'sites' => array_map(
            static function (
                array $site
            ): array {
                $summary =
                    llama_place_campsite_summary(
                        $site
                    );

                $pricingSummary =
                    trim(
                        (string) (
                            $site['pricing_summary']
                            ?? ''
                        )
                    );

                if ($pricingSummary !== '') {
                    array_unshift(
                        $summary,
                        $pricingSummary
                    );
                }

                $site['summary'] =
                    array_values(
                        array_unique(
                            $summary
                        )
                    );

                return $site;
            },
            $sites
        ),
        'selected_site' =>
            $selectedSite
                ? array_merge(
                    $selectedSite,
                    [
                        'summary' =>
                            array_values(
                                array_unique(
                                    array_filter(
                                        [
                                            trim(
                                                (string) (
                                                    $selectedSite[
                                                        'pricing_summary'
                                                    ]
                                                    ?? ''
                                                )
                                            ),
                                            ...llama_place_campsite_summary(
                                                $selectedSite
                                            ),
                                        ],
                                        static fn (
                                            mixed $value
                                        ): bool =>
                                            trim(
                                                (string) $value
                                            ) !== ''
                                    )
                                )
                            ),
                    ]
                )
                : null,
    ]);
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'place.campsites.api', [
        'place_id' => $placeId,
        'user_id' => $userId,
    ]);

    llama_place_campsites_api_json([
        'ok' => false,
        'error' => 'Site data could not be loaded.',
    ], 500);
}
