<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-map-features.php';
require_once dirname(__DIR__, 2) . '/app/place-map-pricing.php';
require_once dirname(__DIR__, 2) . '/app/place-campsites.php';
require_once dirname(__DIR__, 2) . '/app/place-report.php';
require_once dirname(__DIR__, 2) . '/app/place-report/scoped-answers.php';
require_once dirname(__DIR__, 2) . '/app/place-report/scoped-warnings.php';
require_once dirname(__DIR__, 2) . '/app/place-area-media.php';

$placeId =
    (int) (
        $place['id']
        ?? 0
    );

$stayViewerHasCompleteAccess =
    (bool) ($hasCompleteAccess ?? false);

$baseCampingFee =
    $place['rules']['fee']
    ?? null;

$stayFeatures = [];
$stayDb = db();
$stayPlaceReportValues = [];

try {
    $stayPlaceReportData =
        llama_place_report_data_from_published_place(
            $place,
            llama_place_report_published_answer_state(
                $stayDb,
                $placeId
            )
        );

    $stayPlaceReportValues =
        llama_scoped_report_values_from_data(
            $stayPlaceReportData
        );
} catch (Throwable $exception) {
    llama_log_caught_exception(
        $exception,
        'public.overnight-options.place_report',
        [
            'place_id' =>
                $placeId,
        ]
    );
}

try {
    $stayFeatures =
        llama_place_map_features(
            $stayDb,
            $placeId,
            true
        );
} catch (Throwable $exception) {
    error_log(
        'Llama Scout stay options: '
        . $exception->getMessage()
    );
}

/*
 * Canonical campsite records remain the public Site identity when they are
 * associated with map geometry. Their map_feature_id tells us which mapped
 * camping_site polygons are already represented by the canonical campsite
 * browser and therefore must not be rendered a second time below.
 */
$canonicalMappedSiteIds = [];

try {
    foreach (
        llama_place_campsites(
            $stayDb,
            $placeId
        )
        as $campsite
    ) {
        $mapFeatureId =
            max(
                0,
                (int) (
                    $campsite['map_feature_id']
                    ?? 0
                )
            );

        if ($mapFeatureId > 0) {
            $canonicalMappedSiteIds[
                $mapFeatureId
            ] = true;
        }
    }
} catch (Throwable $exception) {
    error_log(
        'Llama Scout canonical stay options: '
        . $exception->getMessage()
    );
}


/*
 * Only historical map-only Site polygons belong in this supplemental list.
 *
 * New mapped Sites receive a canonical place_campsites identity during map
 * save. Admin Place also normalizes older map-only Sites. Keeping this fallback
 * prevents a legacy orphan from disappearing before it has been repaired.
 */
$stayMappedSites = [];

foreach ($stayFeatures as $feature) {
    if (
        (string) (
            $feature['feature_type']
            ?? ''
        ) !== 'camping_site'
    ) {
        continue;
    }

    $id =
        (int) (
            $feature['id']
            ?? 0
        );

    if (
        $id < 1
        || isset(
            $canonicalMappedSiteIds[
                $id
            ]
        )
    ) {
        continue;
    }

    $details =
        (array) (
            $feature['site_details']
            ?? []
        );

    $code =
        trim(
            (string) (
                $details['site_code']
                ?? ''
            )
        );

    $name =
        trim(
            (string) (
                $feature['label']
                ?? ''
            )
        );

    $label =
        $code !== ''
            ? $code
            : (
                $name !== ''
                    ? $name
                    : 'Site ' . $id
            );

    $style =
        trim(
            (string) (
                $details['parking_style']
                ?? ''
            )
        );

    $type =
        trim(
            (string) (
                $details['site_type']
                ?? ''
            )
        );

    $effectiveRates =
        llama_place_map_effective_rates_with_place_base(
            (array) (
                $feature['effective_rates']
                ?? $feature['rates']
                ?? []
            ),
            $baseCampingFee
        );

    $pricingSummary =
        llama_place_map_rate_summary_label(
            $effectiveRates
        );

    $summaryParts =
        array_values(
            array_filter([
                $pricingSummary,
                $type !== ''
                    ? ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $type
                        )
                    )
                    : 'Site',

                $style !== ''
                    ? ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $style
                        )
                    )
                    : '',
            ])
        );

    $stayMappedSites[] = [
        'id' =>
            $id,

        'name' =>
            $label,

        'summary_parts' =>
            $summaryParts,

        'area' =>
            trim(
                (string) (
                    $details['parent_area_label']
                    ?? ''
                )
            ),
    ];
}


if (!$stayViewerHasCompleteAccess) {
    /*
     * Legacy map-only Site identities remain Complete Access-only. Public
     * viewers use the canonical Site catalog instead.
     */
    $stayMappedSites = [];
}

$stayReportFields =
    llama_place_report_fields();

$stayAreas = [];

foreach ($stayFeatures as $feature) {
    $featureType =
        (string) (
            $feature['feature_type']
            ?? ''
        );

    if (
        !in_array(
            $featureType,
            [
                'camping_area',
                'parking_area',
            ],
            true
        )
    ) {
        continue;
    }

    $details =
        (array) (
            $feature['area_details']
            ?? []
        );

    $use =
        (string) (
            $details['area_use']
            ?? ''
        );

    $overnight =
        (string) (
            $details['overnight_status']
            ?? ''
        );

    /*
     * Every mapped Area is a Place child. Do not hide day-use parking Areas
     * merely because they have no overnight-specific metadata.
     */

    $id =
        (int) (
            $feature['id']
            ?? 0
        );

    if ($id < 1) {
        continue;
    }

    $name =
        trim(
            (string) (
                $feature['label']
                ?? ''
            )
        );

    $areaKind =
        $featureType === 'camping_area'
            ? 'Camping area'
            : 'Parking area';

    if ($name === '') {
        $name =
            $featureType === 'parking_area'
            && $use === 'overnight_vehicle_parking'
                ? 'Overnight vehicle parking'
                : $areaKind;
    }

    $summaryParts = [
        $areaKind,
    ];

    $fee =
        (string) (
            $details['fee_status']
            ?? ''
        );

    $feeLabel =
        match ($fee) {
            'paid' =>
                'Paid',

            'free' =>
                'Free',

            'varies' =>
                'Cost varies',

            default =>
                '',
        };

    if ($feeLabel !== '') {
        $summaryParts[] =
            $feeLabel;
    }

    $statusLabel =
        match ($overnight) {
            'allowed' =>
                'Overnight allowed',

            'prohibited' =>
                'Overnight prohibited',

            'varies' =>
                'Rules vary',

            default =>
                '',
        };

    if ($statusLabel !== '') {
        $summaryParts[] =
            $statusLabel;
    }

    $reportValues = [];
    $reportWarnings = [];
    $areaImages = [];

    try {
        $resolvedAreaReport =
            llama_scoped_report_effective_answers(
                $stayDb,
                $placeId,
                $stayPlaceReportValues,
                'area',
                $id,
                'map_feature'
            );

        $resolvedAreaValues =
            (array) (
                $resolvedAreaReport['values']
                ?? []
            );

        $visibleAreaValues =
            array_filter(
                $resolvedAreaValues,
                static function (
                    mixed $value,
                    string $fieldKey
                ) use (
                    $stayReportFields,
                    $stayViewerHasCompleteAccess
                ): bool {
                    $field =
                        $stayReportFields[
                            $fieldKey
                        ]
                        ?? null;

                    return is_array($field)
                        && in_array(
                            'area',
                            (array) (
                                $field['report_scopes']
                                ?? []
                            ),
                            true
                        )
                        && llama_place_report_field_visibility_allows(
                            $field,
                            $stayViewerHasCompleteAccess
                        );
                },
                ARRAY_FILTER_USE_BOTH
            );

        $reportValues =
            llama_scoped_report_display_values(
                $visibleAreaValues
            );

        $reportWarnings =
            $stayViewerHasCompleteAccess
                ? llama_scoped_report_quick_warnings(
                    $resolvedAreaValues
                )
                : [];
    } catch (Throwable $exception) {
        llama_log_caught_exception(
            $exception,
            'public.overnight-options.area_report',
            [
                'place_id' =>
                    $placeId,

                'area_id' =>
                    $id,
            ]
        );
    }

    try {
        $areaImages =
            array_values(
                array_map(
                    static function (array $image): array {
                        $src =
                            trim(
                                (string) (
                                    $image['src']
                                    ?? ''
                                )
                            );

                        return [
                            'src' =>
                                $src !== ''
                                    ? place_image_url($src)
                                    : '',
                            'alt' =>
                                trim(
                                    (string) (
                                        $image['alt_text']
                                        ?? ''
                                    )
                                ),
                            'caption' =>
                                trim(
                                    (string) (
                                        $image['caption']
                                        ?? ''
                                    )
                                ),
                            'credit' =>
                                trim(
                                    (string) (
                                        $image['source_credit']
                                        ?? ''
                                    )
                                ),
                        ];
                    },
                    llama_place_area_images(
                        $stayDb,
                        $placeId,
                        $id
                    )
                )
            );
    } catch (Throwable $exception) {
        llama_log_caught_exception(
            $exception,
            'public.overnight-options.area_images',
            [
                'place_id' =>
                    $placeId,
                'area_id' =>
                    $id,
            ]
        );
    }

    $stayAreas[] = [
        'id' =>
            $id,

        'name' =>
            $name,

        'kind' =>
            $featureType === 'camping_area'
                ? 'camping'
                : 'parking',

        'summary_parts' =>
            $summaryParts,

        'report_values' =>
            $reportValues,

        'report_warnings' =>
            $reportWarnings,

        'images' =>
            array_values(
                array_filter(
                    $areaImages,
                    static fn (array $image): bool =>
                        trim(
                            (string) (
                                $image['src']
                                ?? ''
                            )
                        ) !== ''
                )
            ),
    ];
}


$hasSupplementalStayOptions =
    $stayAreas
    || $stayMappedSites;
?>


<script src="/js/place-campsite-horizontal-scroll.js"></script>

<link
    rel="stylesheet"
    href="/css/site/features/place-overnight-options.css"
>

<link
    rel="stylesheet"
    href="/css/site/features/place-campsite-detail-media.css"
>

<section
    class="place-section place-overnight-options"
    data-stay-options
    data-area-photo-open="<?= (int) ($userId ?? 0) > 0 ? '1' : '0' ?>"
    aria-labelledby="place-overnight-options-heading"
>
    <header class="place-overnight-options-heading">
        <div>
            <p class="eyebrow">
                Stay and parking options
            </p>

            <h2 id="place-overnight-options-heading">
                Where you can park or stay
            </h2>

            <p class="place-stay-intro">
                Browse Sites and mapped Areas, including places where overnight
                use is prohibited. Select an option to see its details in the Scout Report.
            </p>
        </div>

        <a
            class="place-overnight-options-map-link"
            href="#place-map-heading"
        >
            <?= llama_icon('map') ?>
            View on map
        </a>
    </header>

    <div data-stay-browser-slot></div>

    <div
        class="place-stay-scroll-navigation"
        data-stay-scroll-navigation
        aria-label="Scroll overnight options"
        hidden
    >
        <button
            type="button"
            data-stay-scroll-back
            aria-label="Scroll to previous overnight options"
        >
            <?= llama_icon('chevron-left') ?>
        </button>

        <button
            type="button"
            data-stay-scroll-next
            aria-label="Scroll to next overnight options"
        >
            <?= llama_icon('chevron-right') ?>
        </button>

    </div>

    <div
        class="place-stay-options-list"
        data-stay-options-list
        aria-label="Mapped Sites and overnight Areas"
        <?= $hasSupplementalStayOptions ? '' : 'hidden' ?>
    >
        <?php foreach ($stayMappedSites as $site): ?>
            <button
                type="button"
                class="place-campsite-row place-stay-map-site-row"
                data-stay-map-site-id="<?= (int) $site['id'] ?>"
                data-stay-map-site-name="<?= place_h($site['name']) ?>"
                data-stay-map-site-area="<?= place_h($site['area']) ?>"
                data-stay-map-site-meta="<?= place_h(
                    json_encode(
                        $site['summary_parts'],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    ?: '[]'
                ) ?>"
            >
                <span class="place-campsite-row-name">
                    <?= place_h($site['name']) ?>
                </span>

                <span class="place-campsite-row-summary">
                    <?php foreach ($site['summary_parts'] as $index => $part): ?>
                        <?php if ($index > 0): ?>
                            <?= llama_icon('separator-dot') ?>
                        <?php endif; ?>

                        <span><?= place_h($part) ?></span>
                    <?php endforeach; ?>
                </span>
            </button>
        <?php endforeach; ?>

        <?php foreach ($stayAreas as $area): ?>
            <button
                type="button"
                class="place-campsite-row place-stay-area-row"
                data-stay-area-id="<?= (int) $area['id'] ?>"
                data-stay-area-name="<?= place_h($area['name']) ?>"
                data-stay-area-kind="<?= place_h($area['kind']) ?>"
                data-stay-area-meta="<?= place_h(
                    json_encode(
                        $area['summary_parts'],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    ?: '[]'
                ) ?>"
                data-stay-report-values="<?= place_h(
                    json_encode(
                        $area['report_values'],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    ?: '{}'
                ) ?>"
                data-stay-report-warnings="<?= place_h(
                    json_encode(
                        $area['report_warnings'],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    ?: '{}'
                ) ?>"
                data-stay-area-images="<?= place_h(
                    json_encode(
                        $area['images'],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    ?: '[]'
                ) ?>"
            >
                <span class="place-campsite-row-name">
                    <?= place_h($area['name']) ?>
                </span>

                <span class="place-campsite-row-summary">
                    <?php foreach ($area['summary_parts'] as $index => $part): ?>
                        <?php if ($index > 0): ?>
                            <?= llama_icon('separator-dot') ?>
                        <?php endif; ?>

                        <span><?= place_h($part) ?></span>
                    <?php endforeach; ?>
                </span>
            </button>
        <?php endforeach; ?>

    </div>

    <div
        class="place-area-photo-detail"
        data-stay-area-photo-detail
        hidden
    ></div>

</section>

<script
    src="/js/place-overnight-options.js"
    defer
></script>

<script
    src="/js/place-stay-selection-map.js"
    defer
></script>

<script
    src="/js/place-campsite-detail-media.js"
    defer
></script>
