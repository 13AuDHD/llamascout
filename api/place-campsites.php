<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/target-model.php';
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

$place = place_member_by_slug($slug);
if (!$place) {
    llama_place_campsites_api_json(['ok' => false, 'error' => 'Place not found.'], 404);
}

try {
    $db = db();
    $sites = llama_place_campsites($db, $placeId);
    $restrictedSiteIds = $canRead ? [] : llama_site_restricted_visibility_ids($db, $placeId);
    $sites = array_values(array_filter(
        $sites,
        static fn(array $site): bool =>
            llama_site_can_read($place, $site, $canRead, $restrictedSiteIds)
    ));
    $sites = llama_campsite_closure_enrich($db, $placeId, $sites);

    // The default/full response includes member-only Site facts and reports.
    // Public developed Site access is limited to the reviewed catalog.
    if (!$canRead && ($_GET['mode'] ?? '') !== 'catalog') {
        llama_place_campsites_api_json([
            'ok' => false,
            'error' => 'Complete Place access is required.',
        ], 403);
    }

    // The Site picker never needs every Site's full Scout Report.
    // Preserve the existing full-response contract for older consumers.
    if (($_GET['mode'] ?? '') === 'catalog') {
        $state = llama_place_campsite_browser_state($place, $sites);
        $catalog = [];

        foreach ($sites as $site) {
            if (!$canRead) {
                $officialDetails =
                    llama_site_public_catalog_details(
                        $site
                    );

                /*
                 * Keep the public payload narrow, but include the raw values
                 * behind the already-approved official detail whitelist. The
                 * shared Site browser can then populate the canonical Scout
                 * Report cards without maintaining a second public-only UI.
                 */
                $hasOfficialDetails =
                    $officialDetails !== [];

                $catalog[] = [
                    'id' => (int) ($site['id'] ?? 0),
                    'feature_id' => (int) ($site['id'] ?? 0),
                    'display_name' => (string) ($site['display_name'] ?? ''),
                    'site_code' => (string) ($site['site_code'] ?? ''),
                    'site_name' => (string) ($site['site_name'] ?? ''),
                    'site_type' => (string) ($site['site_type'] ?? ''),
                    // Public-facing campground basics only. No GPS,
                    // community observations or detailed Scout Report data.
                    'parking_style' => (string) ($site['parking_style'] ?? ''),
                    'hookup_status' => (string) ($site['hookup_status'] ?? ''),
                    'accessible_status' => (string) ($site['accessible_status'] ?? ''),
                    'electric_hookup' => $site['electric_hookup'] ?? null,
                    'max_vehicle_length_ft' =>
                        $hasOfficialDetails
                            ? ($site['max_vehicle_length_ft'] ?? null)
                            : null,
                    'max_people' =>
                        $hasOfficialDetails
                            ? ($site['max_people'] ?? null)
                            : null,
                    'max_vehicles' =>
                        $hasOfficialDetails
                            ? ($site['max_vehicles'] ?? null)
                            : null,
                    'site_length_ft' =>
                        $hasOfficialDetails
                            ? ($site['site_length_ft'] ?? null)
                            : null,
                    'site_width_ft' =>
                        $hasOfficialDetails
                            ? ($site['site_width_ft'] ?? null)
                            : null,
                    'tent_pad' =>
                        $hasOfficialDetails
                            ? ($site['tent_pad'] ?? null)
                            : null,
                    'tent_pad_length_ft' =>
                        $hasOfficialDetails
                            ? ($site['tent_pad_length_ft'] ?? null)
                            : null,
                    'tent_pad_width_ft' =>
                        $hasOfficialDetails
                            ? ($site['tent_pad_width_ft'] ?? null)
                            : null,
                    'water_hookup' =>
                        $hasOfficialDetails
                            ? ($site['water_hookup'] ?? null)
                            : null,
                    'sewer_hookup' =>
                        $hasOfficialDetails
                            ? ($site['sewer_hookup'] ?? null)
                            : null,
                    'is_closed' => (bool) ($site['is_closed'] ?? false),
                    'operating_status' => $site['operating_status'] ?? null,
                    'official_details' => $officialDetails,
                ];
                continue;
            }
            $catalog[] = [
                'id' => (int) ($site['id'] ?? 0),
                'feature_id' => (int) ($site['id'] ?? 0),
                'display_name' => (string) ($site['display_name'] ?? ''),
                'site_code' => (string) ($site['site_code'] ?? ''),
                'site_name' => (string) ($site['site_name'] ?? ''),
                'site_type' => (string) ($site['site_type'] ?? ''),
                'parking_style' => (string) ($site['parking_style'] ?? ''),
                'hookup_status' => (string) ($site['hookup_status'] ?? ''),
                'accessible_status' => (string) ($site['accessible_status'] ?? ''),
                'electric_hookup' => $site['electric_hookup'] ?? null,
                'is_closed' => (bool) ($site['is_closed'] ?? false),
                'operating_status' => $site['operating_status'] ?? null,
                'closure_reason' => $site['closure_reason'] ?? null,
                'expected_reopening' => $site['expected_reopening'] ?? null,
                'closure_verified_at' => $site['closure_verified_at'] ?? null,
                'summary' => llama_place_campsite_summary($site),
            ];
        }

        llama_place_campsites_api_json([
            'ok' => true,
            'browser' => $state,
            'sites' => $catalog,
        ]);
    }


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
        [];

    foreach (
        llama_place_report_fields()
        as $fieldKey => $field
    ) {
        $state =
            llama_place_report_answer_state(
                $placeReportData,
                (string) $fieldKey
            );

        if ($state === 'unknown') {
            $placeReportValues[
                (string) $fieldKey
            ] =
                llama_place_report_unknown_token();

            continue;
        }

        if ($state !== 'answered') {
            continue;
        }

        $placeReportValues[
            (string) $fieldKey
        ] =
            llama_place_report_form_value_from_data(
                $placeReportData,
                (string) $fieldKey
            );
    }

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

        if (is_array($resolved)) {
            $resolved = llama_place_report_with_campsite_baseline($resolved, $site);
        }
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
