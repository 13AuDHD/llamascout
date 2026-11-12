<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-campsites.php';
require_once dirname(__DIR__) . '/app/place-pricing.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';
require_once dirname(__DIR__) . '/app/place-map-features.php';
require_once dirname(__DIR__) . '/app/campsite-closure-display.php';
require_once dirname(__DIR__) . '/app/place-report.php';
require_once dirname(__DIR__) . '/app/place-report/target-model.php';
require_once dirname(__DIR__) . '/app/place-report/report-targets.php';
require_once dirname(__DIR__) . '/app/place-report/scoped-warnings.php';
require_once dirname(__DIR__) . '/app/place-report/season-public.php';
require_once dirname(__DIR__) . '/app/place-report/site-booking.php';

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
    $placeVerifiedClosed = llama_public_place_verified_closed($db, $placeId);
    foreach ($sites as &$site) {
        $site['place_verified_closed'] = $placeVerifiedClosed;
    }
    unset($site);


    // Only use IDs that survived the existing visibility restrictions.
    // Seasonal open/closed describes the normal schedule; verified closures
    // remain on the original is_closed / operating_status fields.
    $seasonSelection = llama_public_season_selection(
        $db, $placeId, $_GET['season'] ?? null
    );
    $season = $seasonSelection['selected'] ?? null;
    if (is_array($season)) {
        $siteStatuses = llama_public_season_site_statuses(
            $db, $placeId, (int) $season['id'],
            array_column($sites, 'id')
        );
        foreach ($sites as &$site) {
            $site['seasonal_operating_status'] =
                $siteStatuses[(int) ($site['id'] ?? 0)] ?? null;
        }
        unset($site);
    } elseif (!empty($seasonSelection['seasons'])
        && (string) ($_GET['season'] ?? '') !== '0') {
        // A configured campground has no current operating season.
        // This is seasonal unavailability, not a verified safety closure.
        foreach ($sites as &$site) {
            $site['seasonal_operating_status'] = 'closed';
        }
        unset($site);
    }

    [$rateDoc, $rateDate] = llama_pricing_public_night(
        llama_pricing_document($db, $place), $season, $_GET['season'] ?? null
    );
    $sites = llama_pricing_enrich_sites($db, $place, $sites, $rateDoc, $rateDate);

    $bookingMethods = llama_public_site_booking_methods(
        $db,
        $placeId,
        is_array($season) ? (int) $season['id'] : null,
        array_column($sites, 'id')
    );
    foreach ($sites as &$site) {
        $site['reservation_method'] =
            $bookingMethods[(int) ($site['id'] ?? 0)] ?? null;
    }
    unset($site);

    $authorizedAccessible = $canRead
        ? llama_authorized_site_accessibility_overrides(
            $db, $placeId, array_column($sites, 'id')
        )
        : [];
    foreach ($sites as &$site) {
        $siteId = (int) ($site['id'] ?? 0);
        $officialAccessible = in_array(
            strtolower(trim((string) ($site['accessible_status'] ?? ''))),
            ['yes', 'true', '1'],
            true
        );
        $site['accessible_pill'] =
            $canRead && array_key_exists($siteId, $authorizedAccessible)
                ? $authorizedAccessible[$siteId]
                : $officialAccessible;
    }
    unset($site);




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
                $officialDetailKeys =
                    array_fill_keys(
                        array_values(
                            array_filter(
                                array_map(
                                    static fn (array $detail): string =>
                                        (string) ($detail['source_key'] ?? ''),
                                    $officialDetails
                                ),
                                static fn (string $key): bool =>
                                    $key !== ''
                            )
                        ),
                        true
                    );

                // Whitelisted official RIDB Site facts now live inside the
                // canonical Site Info cards. Never publish community-only
                // features here or bypass the existing Site visibility check.
                $officialSiteInfo = [];
                $officialSiteSources = [];
                $officialFeatureMap = [
                    'ridb_loop' => 'site_loop',
                    'ridb_type_of_use' => 'site_type_of_use',
                ];
                foreach ((array) ($site['features'] ?? []) as $feature) {
                    $ridbKey = (string) ($feature['feature_key'] ?? '');
                    $canonicalKey = $officialFeatureMap[$ridbKey] ?? null;
                    if ($canonicalKey === null || isset($officialSiteInfo[$canonicalKey])) {
                        continue;
                    }
                    $provider = trim((string) ($feature['source_provider'] ?? ''));
                    $lowerProvider = strtolower($provider);
                    if (!str_contains($lowerProvider, 'ridb')
                        && !str_contains($lowerProvider, 'recreation.gov')) {
                        continue;
                    }
                    $value = trim((string) ($feature['feature_value'] ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    $officialSiteInfo[$canonicalKey] = $value;
                    $officialSiteSources[$canonicalKey] = [
                        'label' => $provider,
                        'dateLabel' => (string) ($feature['source_updated_at'] ?? ''),
                    ];
                }

                $publicReportKeys = ['site_number'];
                foreach (array_keys($officialSiteInfo) as $key) {
                    $publicReportKeys[] = $key;
                }

                $siteType =
                    strtolower(
                        trim(
                            (string) ($site['site_type'] ?? '')
                        )
                    );

                // RIDB's official permitted-equipment Tent entry is a
                // public, affirmative Site suitability answer even if the
                // broad RIDB category is RV Site.
                $importedTentAllowed = llama_place_report_campsite_permits_tent($site);
                $importedTentPad = llama_place_report_campsite_bool_form_value(
                    $site['tent_pad'] ?? null
                ) === '1';
                if (in_array($siteType, ['tent_site', 'mixed_site'], true)
                    || $importedTentAllowed || $importedTentPad) {
                    $publicReportKeys[] = 'tent_camping_suitable';
                }
                if ($importedTentAllowed || $importedTentPad) {
                    $officialSiteInfo['tent_camping_suitable'] = 'Yes';
                }

                if (in_array(
                    $siteType,
                    ['rv_site', 'mixed_site', 'vehicle_site'],
                    true
                )) {
                    $publicReportKeys[] = 'rv_suitable';
                }

                $accessibleStatus =
                    strtolower(
                        trim(
                            (string) ($site['accessible_status'] ?? '')
                        )
                    );

                if ($accessibleStatus !== '' && $accessibleStatus !== 'unknown') {
                    $publicReportKeys[] = 'site_accessible';
                }

                $parkingStyle =
                    strtolower(
                        trim(
                            (string) ($site['parking_style'] ?? '')
                        )
                    );

                if ($parkingStyle !== '' && $parkingStyle !== 'unknown') {
                    $publicReportKeys[] = 'pull_through';
                    $publicReportKeys[] = 'back_in';
                }

                $hookupStatus =
                    strtolower(
                        trim(
                            (string) ($site['hookup_status'] ?? '')
                        )
                    );

                if ($hookupStatus !== '' && $hookupStatus !== 'unknown') {
                    $publicReportKeys[] = 'site_hookups_available';
                }

                if (($site['electric_hookup'] ?? null) !== null) {
                    $publicReportKeys[] = 'hookup_electric';
                }

                $officialReportKeyMap = [
                    'max_vehicle_length_ft' => 'max_vehicle_length_feet',
                    'max_people' => 'max_people',
                    'max_vehicles' => 'vehicle_capacity',
                    'site_length_ft' => 'site_length_feet',
                    'site_width_ft' => 'site_width_feet',
                    'tent_pad' => 'tent_pad',
                    'tent_pad_length_ft' => 'tent_pad_length_feet',
                    'tent_pad_width_ft' => 'tent_pad_width_feet',
                    'water_hookup' => 'hookup_water',
                    'sewer_hookup' => 'hookup_sewer',
                ];

                foreach ($officialReportKeyMap as $sourceKey => $reportKey) {
                    if (isset($officialDetailKeys[$sourceKey])) {
                        $publicReportKeys[] = $reportKey;
                    }
                }

                if (!empty($site['reservation_method'])) {
                    $publicReportKeys[] = 'site_reservation_method';
                }

                $publicReportKeys =
                    array_values(
                        array_unique(
                            $publicReportKeys
                        )
                    );

                $publicReportSources = $officialSiteSources;
                if ($importedTentAllowed) {
                    foreach ((array) ($site['features'] ?? []) as $feature) {
                        if ((string) ($feature['feature_key'] ?? '') !== 'permitted_equipment') {
                            continue;
                        }
                        $name = strtolower(trim((string) ($feature['feature_value'] ?? '')));
                        $provider = trim((string) ($feature['source_provider'] ?? ''));
                        if (preg_match('/^tents?(?:\\s+.*)?$/D', $name) !== 1
                            || (!str_contains(strtolower($provider), 'ridb')
                                && !str_contains(strtolower($provider), 'recreation.gov'))) {
                            continue;
                        }
                        $publicReportSources['tent_camping_suitable'] = [
                            'label' => $provider,
                            'dateLabel' => (string) ($feature['source_updated_at'] ?? ''),
                        ];
                        break;
                    }
                }
                $officialSourceLabel =
                    trim(
                        (string) ($site['source_provider'] ?? '')
                    );
                $officialSourceDate =
                    trim(
                        (string) ($site['source_updated_at'] ?? '')
                    );

                foreach ($officialReportKeyMap as $sourceKey => $reportKey) {
                    if (!isset($officialDetailKeys[$sourceKey])) {
                        continue;
                    }

                    $publicReportSources[$reportKey] = [
                        'label' =>
                            $officialSourceLabel !== ''
                                ? $officialSourceLabel
                                : 'Official source',
                        'dateLabel' =>
                            $officialSourceDate,
                    ];
                }

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
                        isset($officialDetailKeys['max_vehicle_length_ft'])
                            ? ($site['max_vehicle_length_ft'] ?? null)
                            : null,
                    'max_people' =>
                        isset($officialDetailKeys['max_people'])
                            ? ($site['max_people'] ?? null)
                            : null,
                    'max_vehicles' =>
                        isset($officialDetailKeys['max_vehicles'])
                            ? ($site['max_vehicles'] ?? null)
                            : null,
                    'site_length_ft' =>
                        isset($officialDetailKeys['site_length_ft'])
                            ? ($site['site_length_ft'] ?? null)
                            : null,
                    'site_width_ft' =>
                        isset($officialDetailKeys['site_width_ft'])
                            ? ($site['site_width_ft'] ?? null)
                            : null,
                    'tent_pad' =>
                        isset($officialDetailKeys['tent_pad'])
                            ? ($site['tent_pad'] ?? null)
                            : null,
                    'tent_pad_length_ft' =>
                        isset($officialDetailKeys['tent_pad_length_ft'])
                            ? ($site['tent_pad_length_ft'] ?? null)
                            : null,
                    'tent_pad_width_ft' =>
                        isset($officialDetailKeys['tent_pad_width_ft'])
                            ? ($site['tent_pad_width_ft'] ?? null)
                            : null,
                    'water_hookup' =>
                        isset($officialDetailKeys['water_hookup'])
                            ? ($site['water_hookup'] ?? null)
                            : null,
                    'sewer_hookup' =>
                        isset($officialDetailKeys['sewer_hookup'])
                            ? ($site['sewer_hookup'] ?? null)
                            : null,
                    'is_closed' => (bool) ($site['is_closed'] ?? false),
                    'place_verified_closed' => (bool) ($site['place_verified_closed'] ?? false),
                    'operating_status' => $site['operating_status'] ?? null,
                    'seasonal_operating_status' => $site['seasonal_operating_status'] ?? null,
                    'reservation_method' => $site['reservation_method'] ?? null,
                    'accessible_pill' => (bool) ($site['accessible_pill'] ?? false),
                    'report_effective_values' => array_replace(
                        $officialSiteInfo,
                        !empty($site['reservation_method'])
                            ? ['site_reservation_method' => (string) $site['reservation_method']]
                            : []
                    ),
                    'pricing_summary' => $site['pricing_summary'],
                    'summary' => [$site['pricing_summary']],
                    'official_details' =>
                        array_map(
                            static fn (array $detail): array => [
                                'label' => (string) ($detail['label'] ?? ''),
                                'value' => (string) ($detail['value'] ?? ''),
                            ],
                            $officialDetails
                        ),
                    'public_report_keys' =>
                        $publicReportKeys,
                    'public_report_sources' =>
                        array_replace($publicReportSources, $officialSiteSources),
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
                'place_verified_closed' => (bool) ($site['place_verified_closed'] ?? false),
                'operating_status' => $site['operating_status'] ?? null,
                'seasonal_operating_status' => $site['seasonal_operating_status'] ?? null,
                'reservation_method' => $site['reservation_method'] ?? null,
                'accessible_pill' => (bool) ($site['accessible_pill'] ?? false),
                'closure_reason' => $site['closure_reason'] ?? null,
                'expected_reopening' => $site['expected_reopening'] ?? null,
                'closure_verified_at' => $site['closure_verified_at'] ?? null,
                'pricing_summary' => $site['pricing_summary'],
                'summary' => array_merge([$site['pricing_summary']], llama_place_campsite_summary($site)),
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
