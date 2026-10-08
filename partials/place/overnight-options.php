<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/app/place-map-features.php';

$overnightMapFeatures = [];

try {
    $overnightMapFeatures =
        llama_place_map_features(
            db(),
            (int) ($place['id'] ?? 0),
            true
        );
} catch (Throwable $exception) {
    error_log(
        'Llama Scout overnight options error for Place #'
        . (int) ($place['id'] ?? 0)
        . ': '
        . $exception->getMessage()
    );

    $overnightMapFeatures = [];
}

$overnightCampingAreas =
    array_values(
        array_filter(
            $overnightMapFeatures,
            static fn (array $feature): bool =>
                (string) (
                    $feature['feature_type']
                    ?? ''
                ) === 'camping_area'
        )
    );

$overnightParkingAreas =
    array_values(
        array_filter(
            $overnightMapFeatures,
            static function (
                array $feature
            ): bool {
                if (
                    (string) (
                        $feature['feature_type']
                        ?? ''
                    ) !== 'parking_area'
                ) {
                    return false;
                }

                $details =
                    (array) (
                        $feature['area_details']
                        ?? []
                    );

                return
                    (string) (
                        $details['area_use']
                        ?? ''
                    ) === 'overnight_vehicle_parking'
                    || (
                        (string) (
                            $details['overnight_status']
                            ?? ''
                        ) !== ''
                    );
            }
        )
    );

if (
    !$overnightCampingAreas
    && !$overnightParkingAreas
) {
    return;
}

$overnightSiteCounts = [];

foreach ($overnightMapFeatures as $feature) {
    if (
        (string) (
            $feature['feature_type']
            ?? ''
        ) !== 'camping_site'
    ) {
        continue;
    }

    $parentId =
        (int) (
            $feature['site_details']['camping_area_feature_id']
            ?? 0
        );

    if ($parentId < 1) {
        continue;
    }

    $overnightSiteCounts[$parentId] =
        (
            $overnightSiteCounts[$parentId]
            ?? 0
        ) + 1;
}

$overnightAreaUseLabels = [
    'developed_campground' =>
        'Developed campground',

    'designated_camping' =>
        'Designated camping area',

    'dispersed_camping' =>
        'Dispersed camping area',

    'general_parking' =>
        'General parking',

    'overnight_vehicle_parking' =>
        'Overnight vehicle parking',
];

$overnightFeeLabels = [
    'free' =>
        'Free',

    'paid' =>
        'Paid',

    'varies' =>
        'Cost varies',

    'unknown' =>
        'Cost unknown',
];

$overnightStatusLabels = [
    'allowed' =>
        'Overnight stay allowed',

    'prohibited' =>
        'Overnight stay not allowed',

    'varies' =>
        'Overnight rules vary',

    'unknown' =>
        'Overnight status unknown',
];

$overnightMoney =
    static function (
        mixed $amount,
        string $currency = 'USD'
    ): string {
        if (!is_numeric($amount)) {
            return '';
        }

        $amount =
            (float) $amount;

        if ($currency === 'USD') {
            return
                '$'
                . number_format(
                    $amount,
                    abs(
                        $amount
                        - round($amount)
                    ) < 0.001
                        ? 0
                        : 2
                );
        }

        return
            number_format(
                $amount,
                2
            )
            . ' '
            . $currency;
    };

$overnightRateText =
    static function (
        array $feature
    ) use (
        $overnightMoney
    ): string {
        $summary =
            (array) (
                $feature['effective_rate_summary']
                ?? $feature['rate_summary']
                ?? []
            );

        if (
            empty($summary['count'])
            && !empty(
                $feature['site_class_rate_summary']
            )
        ) {
            $summary =
                (array) $feature[
                    'site_class_rate_summary'
                ];
        }

        if (
            !isset(
                $summary['minimum']
            )
            || !is_numeric(
                $summary['minimum']
            )
        ) {
            return '';
        }

        $minimum =
            (float) $summary['minimum'];

        $maximum =
            isset(
                $summary['maximum']
            )
            && is_numeric(
                $summary['maximum']
            )
                ? (float) $summary['maximum']
                : $minimum;

        $currency =
            (string) (
                $summary['currency']
                ?? 'USD'
            );

        if (
            abs(
                $minimum
                - $maximum
            ) < 0.001
        ) {
            return
                $overnightMoney(
                    $minimum,
                    $currency
                )
                . ' / night';
        }

        return
            $overnightMoney(
                $minimum,
                $currency
            )
            . '–'
            . $overnightMoney(
                $maximum,
                $currency
            )
            . ' / night';
    };

$overnightEditorBaseUrl =
    '/place-map-editor.php?slug='
    . rawurlencode(
        (string) (
            $place['slug']
            ?? ''
        )
    );

$overnightCanEdit =
    !empty(
        $placeMapCanEditAreas
    );

?>
<link
    rel="stylesheet"
    href="/css/site/features/place-overnight-options.css"
>

<section
    class="place-section place-overnight-options"
    aria-labelledby="place-overnight-options-heading"
>
    <div class="place-overnight-options-heading">
        <div>
            <p class="eyebrow">
                Overnight options
            </p>

            <h2 id="place-overnight-options-heading">
                Where you can stay
            </h2>
        </div>

        <a
            class="place-overnight-options-map-link"
            href="#place-map-heading"
        >
            <?= llama_icon('map') ?>
            View on map
        </a>
    </div>

    <div class="place-overnight-options-grid">
        <?php foreach (
            array_merge(
                $overnightParkingAreas,
                $overnightCampingAreas
            )
            as $feature
        ): ?>
            <?php
            $featureType =
                (string) (
                    $feature['feature_type']
                    ?? ''
                );

            $details =
                (array) (
                    $feature['area_details']
                    ?? []
                );

            $featureId =
                (int) (
                    $feature['id']
                    ?? 0
                );

            $label =
                trim(
                    (string) (
                        $feature['label']
                        ?? ''
                    )
                );

            $fallbackLabel =
                $featureType === 'parking_area'
                    ? 'Overnight vehicle parking'
                    : 'Camping area';

            $areaUse =
                (string) (
                    $details['area_use']
                    ?? ''
                );

            $feeStatus =
                (string) (
                    $details['fee_status']
                    ?? ''
                );

            $overnightStatus =
                (string) (
                    $details['overnight_status']
                    ?? ''
                );

            $rateText =
                $overnightRateText(
                    $feature
                );

            $mappedSiteCount =
                $featureType === 'camping_area'
                    ? (
                        $overnightSiteCounts[
                            $featureId
                        ]
                        ?? 0
                    )
                    : 0;

            $pricingClassCount =
                $featureType === 'camping_area'
                    ? count(
                        (array) (
                            $feature['site_classes']
                            ?? []
                        )
                    )
                    : 0;
            ?>

            <article
                class="place-overnight-option-card is-<?= place_h(
                    str_replace(
                        '_',
                        '-',
                        $featureType
                    )
                ) ?>"
            >
                <div class="place-overnight-option-card-heading">
                    <div>
                        <span class="place-overnight-option-kind">
                            <?= place_h(
                                $overnightAreaUseLabels[
                                    $areaUse
                                ]
                                ?? $fallbackLabel
                            ) ?>
                        </span>

                        <h3>
                            <?= place_h(
                                $label !== ''
                                    ? $label
                                    : $fallbackLabel
                            ) ?>
                        </h3>
                    </div>

                    <i aria-hidden="true">
                        <?= llama_icon(
                            $featureType === 'parking_area'
                                ? 'parking'
                                : 'tent'
                        ) ?>
                    </i>
                </div>

                <div class="place-overnight-option-facts">
                    <?php if (
                        isset(
                            $overnightFeeLabels[
                                $feeStatus
                            ]
                        )
                    ): ?>
                        <div>
                            <span>Cost</span>
                            <strong>
                                <?= place_h(
                                    $overnightFeeLabels[
                                        $feeStatus
                                    ]
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $rateText !== ''
                    ): ?>
                        <div>
                            <span>Nightly rates</span>
                            <strong>
                                <?= place_h(
                                    $rateText
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $featureType
                        === 'parking_area'
                        && isset(
                            $overnightStatusLabels[
                                $overnightStatus
                            ]
                        )
                    ): ?>
                        <div>
                            <span>Vehicle stay</span>
                            <strong>
                                <?= place_h(
                                    $overnightStatusLabels[
                                        $overnightStatus
                                    ]
                                ) ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $featureType
                        === 'camping_area'
                        && $pricingClassCount > 0
                    ): ?>
                        <div>
                            <span>Pricing classes</span>
                            <strong>
                                <?= $pricingClassCount ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                    <?php if (
                        $featureType
                        === 'camping_area'
                        && $mappedSiteCount > 0
                    ): ?>
                        <div>
                            <span>Mapped sites</span>
                            <strong>
                                <?= $mappedSiteCount ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="place-overnight-option-actions">
                    <a href="#place-map-heading">
                        View area on map
                    </a>

                    <?php if (
                        $overnightCanEdit
                        && $featureId > 0
                    ): ?>
                        <a
                            href="<?= place_h(
                                $overnightEditorBaseUrl
                                . '&feature='
                                . $featureId
                            ) ?>"
                        >
                            Edit this area
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($overnightCanEdit): ?>
        <p class="place-overnight-options-editor-note">
            Area use, overnight permission, campground/site details, and rates
            are managed in Mapped Areas.
            <a href="<?= place_h($overnightEditorBaseUrl) ?>">
                Edit mapped areas
            </a>
        </p>
    <?php endif; ?>
</section>
