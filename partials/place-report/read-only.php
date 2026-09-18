<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$placeReportData =
    is_array($placeReportData ?? null)
        ? $placeReportData
        : [];

$placeReportReadMode =
    (string) ($placeReportReadMode ?? 'scout-report');

$placeReportFields =
    llama_place_report_fields();

$placeReportSections =
    llama_place_report_sections();

?>
<link
    rel="stylesheet"
    href="/css/site/features/scout-warning-compact.css"
>
<?php

$e = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );


$iconRoot = dirname(__DIR__, 2) . '/assets/icons/';

$localIcon =
    static function (
        string $legacyIcon,
        string $key = ''
    ) use (
        $iconRoot
    ): string {
        if ($key === 'warning_no_cell_service') {
            return 'antenna-bars-off';
        }

        $prefix = 'fa' . '-';
        $name = trim($legacyIcon);

        if (str_starts_with($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }

        $aliases = [
            'align-left' => 'file-text',
            'arrows-left-right' => 'arrows-diff',
            'arrows-rotate' => 'refresh',
            'box' => 'package',
            'box-archive' => 'packages',
            'building-columns' => 'building-factory',
            'calendar-day' => 'calendar',
            'calendar-xmark' => 'calendar-off',
            'campground' => 'tent',
            'car-burst' => 'alert-triangle',
            'car-side' => 'car',
            'cart-shopping' => 'shopping-cart',
            'circle-info' => 'info-circle',
            'circle-xmark' => 'xbox-x',
            'city' => 'building-store',
            'cloud-sun' => 'temperature-sun',
            'dollar-sign' => 'receipt',
            'ear-listen' => 'ear',
            'faucet-drip' => 'droplet',
            'file-signature' => 'signature',
            'fire' => 'campfire',
            'fire-flame-curved' => 'flame',
            'gas-pump' => 'gas-station',
            'hill-rockslide' => 'mountain',
            'hospital' => 'medical-cross',
            'house-circle-xmark' => 'door-off',
            'location-crosshairs' => 'current-location',
            'location-dot' => 'map-pin',
            'map-location-dot' => 'map-pin',
            'moon' => 'moon-stars',
            'motorcycle' => 'motorbike',
            'mountain-sun' => 'mountain',
            'note-sticky' => 'file-text',
            'pen' => 'edit',
            'person-falling' => 'cliff-jumping',
            'person-walking' => 'walk',
            'person-walking-arrow-right' => 'walk',
            'restroom' => 'at-toilet',
            'rotate' => 'refresh',
            'ruler-horizontal' => 'scale',
            'satellite-dish' => 'satellite',
            'scale-balanced' => 'scale',
            'shield-halved' => 'shield',
            'signal' => 'antenna-bars-5',
            'signs-post' => 'sign-right',
            'smog' => 'at-misty-cloud',
            'snowflake' => 'snowflake',
            'square-parking' => 'parking',
            'table-picnic' => 'picnic-table',
            'tent-arrow-turn-left' => 'tent-off',
            'trailer' => 'caravan',
            'trash-arrow-up' => 'trash',
            'trash-can' => 'trash',
            'triangle-exclamation' => 'alert-triangle',
            'truck-droplet' => 'caravan',
            'truck-medical' => 'medical-cross',
            'truck-monster' => 'car-4wd',
            'truck-pickup' => 'car-suv',
            'utensils' => 'picnic-table',
            'water' => 'ripple',
            'tree' => 'trees',
        ];

        $candidate = $aliases[$name] ?? $name;

        if (
            $candidate !== ''
            && is_file($iconRoot . $candidate . '.svg')
        ) {
            return $candidate;
        }

        return 'info-circle';
    };

$renderValue =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportData,
        $placeReportReadMode,
        $e,
        $localIcon
    ): void {
        $state =
            llama_place_report_answer_state(
                $placeReportData,
                $key
            );

        $value =
            llama_place_report_display_value(
                $placeReportData,
                $key
            );

        if ($state === 'unanswered') {
            $value = 'Not provided';
        }

        $type =
            (string) $field['type'];

        $isRating =
            $type === 'rating';

        $class =
            'scout-report-item '
            . (
                $isRating
                    ? 'scout-report-rating-item'
                    : 'scout-report-value-item'
            )
            . (
                !empty($field['wide'])
                    ? ' scout-report-item-wide'
                    : ''
            )
            . (
                $type === 'textarea'
                    ? ' scout-report-long-text'
                    : ''
            )
            . (
                $state === 'unknown'
                    ? ' is-explicit-unknown'
                    : ''
            )
            . (
                $state === 'unanswered'
                    ? ' is-unanswered'
                    : ''
            );
        ?>

        <div class="<?= $e($class) ?>">

            <?php if ($isRating): ?>
                <div class="scout-rating-content">
                    <span>
                        <?= $e(
                            rtrim(
                                (string) $field['label'],
                                '*'
                            )
                        ) ?>
                    </span>

                    <strong><?= $e($value) ?></strong>
                </div>

                <?php
                $rating =
                    $state === 'answered'
                        ? (int) llama_place_report_get_path(
                            $placeReportData,
                            (string) $field['storage']
                        )
                        : 0;
                ?>

                <div
                    class="scout-rating-dots"
                    aria-label="<?= $state === 'answered' ? $rating . ' out of 5' : $e((string) $value) ?>"
                >
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span
                            class="scout-rating-dot<?= $i <= $rating ? ' is-filled' : '' ?>"
                            aria-hidden="true"
                        ></span>
                    <?php endfor; ?>
                </div>

            <?php else: ?>
                <?php
                $icon =
                    $localIcon(
                        llama_place_report_field_icon(
                            $key,
                            $field
                        ),
                        $key
                    );
                ?>

                <?php
                $safeUrl = null;

                if (
                    $type === 'url'
                    && $state === 'answered'
                    && is_string($value)
                    && $value !== ''
                ) {
                    $urlParts = parse_url($value);
                    $urlScheme = strtolower(
                        (string) ($urlParts['scheme'] ?? '')
                    );

                    if (
                        in_array(
                            $urlScheme,
                            ['http', 'https'],
                            true
                        )
                    ) {
                        $safeUrl = $value;
                    }
                }
                ?>

                <div class="scout-report-value-content">
                    <span>
                        <?= $e(
                            rtrim(
                                (string) $field['label'],
                                '*'
                            )
                        ) ?>
                    </span>

                    <strong>
                        <?php if ($safeUrl !== null): ?>
                            <a
                                href="<?= $e($safeUrl) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            ><?= $e($value) ?></a>
                        <?php else: ?>
                            <?= $e($value) ?>
                        <?php endif; ?>
                    </strong>
                </div>

                <?= llama_icon(
                    $icon,
                    [
                        'class' => 'scout-report-value-icon',
                    ]
                ) ?>
            <?php endif; ?>
        </div>
        <?php
    };

/*
 * Quick warnings are calculated from the canonical Place Report answers.
 * Do not maintain a second set of warning flags here.
 */
$warnings =
    llama_place_report_quick_warnings(
        $placeReportData
    );

if ($warnings):
?>
    <section class="scout-report-section scout-report-warning-section">
        <h3>
            <?= llama_icon('alert-triangle') ?>
            Quick warnings
        </h3>

        <p class="scout-report-summary">
            Important conditions reported for this Place.
        </p>

        <div class="scout-report-grid">
            <?php foreach ($warnings as $key => $warning): ?>
                <?php
                $warningIcon =
                    $localIcon(
                        (string) (
                            $warning['icon']
                            ?? 'alert-triangle'
                        ),
                        $key
                    );
                ?>

                <div
                    class="scout-report-item scout-report-value-item scout-report-warning-item"
                >
                    <div class="scout-report-value-content">
                        <span>
                            <?= $e(
                                (string) (
                                    $warning['label']
                                    ?? 'Warning'
                                )
                            ) ?>
                        </span>

                        <strong>Warning</strong>
                    </div>

                    <?= llama_icon(
                        $warningIcon,
                        [
                            'class' => 'scout-report-value-icon',
                        ]
                    ) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php
endif;

foreach (
    $placeReportSections
    as $sectionKey => $section
):
    if (
        $placeReportReadMode === 'scout-report'
        && in_array(
            $sectionKey,
            [
                'basic',
                'location',
                'amenities',
            ],
            true
        )
    ) {
        /*
         * The public Place wrapper already presents basic identity,
         * exact location, weather, and map context. The field
         * definitions still originate here.
         */
        continue;
    }

    $fields =
        array_filter(
            $placeReportFields,
            static fn (array $field): bool =>
                (string) $field['section'] === $sectionKey
                && (
                    $placeReportReadMode === 'moderation'
                    || empty($field['hide_public'])
                )
        );

    if (!$fields) {
        continue;
    }

    if (
        $sectionKey === 'scout_notes'
        && $placeReportReadMode === 'scout-report'
    ) {
        $fields = array_filter(
            $fields,
            static function (array $field) use ($placeReportData): bool {
                $key = (string) ($field['key'] ?? '');

                if (
                    $key === ''
                    || llama_place_report_answer_state(
                        $placeReportData,
                        $key
                    ) !== 'answered'
                ) {
                    return false;
                }

                return trim(
                    (string) llama_place_report_get_path(
                        $placeReportData,
                        (string) ($field['storage'] ?? '')
                    )
                ) !== '';
            }
        );

        if (!$fields) {
            continue;
        }
    }
?>
    <?php
    $sectionIcon =
        $localIcon(
            (string) ($section['icon'] ?? ''),
            'section:' . (string) $sectionKey
        );
    ?>
    <section class="scout-report-section">
        <h3>
            <?= llama_icon($sectionIcon) ?>

            <?= $e(
                $sectionKey === 'summaries'
                && $placeReportReadMode === 'scout-report'
                    ? 'Summaries'
                    : (string) $section['label']
            ) ?>
        </h3>

        <?php if ($sectionKey === 'sensory'): ?>
            <?php foreach (
                [
                    'Daytime',
                    'Nighttime',
                    'Specific sensory conditions',
                ]
                as $subsection
            ): ?>
                <div class="scout-report-subsection">
                    <h4><?= $e($subsection) ?></h4>

                    <div class="scout-report-grid">
                        <?php foreach ($fields as $key => $field): ?>
                            <?php if (
                                ($field['subsection'] ?? '')
                                === $subsection
                            ): ?>
                                <?php $renderValue($key, $field); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php elseif ($sectionKey === 'scout_notes'): ?>
            <ul class="scout-report-notes-list">
                <?php foreach ($fields as $key => $field): ?>
                    <?php
                    $note =
                        llama_place_report_display_value(
                            $placeReportData,
                            (string) $key
                        );

                    if (
                        $placeReportReadMode === 'scout-report'
                        && ($note === null || trim((string) $note) === '')
                    ) {
                        continue;
                    }
                    ?>
                    <li>
                        <?= $e($note ?? 'Not provided') ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php elseif ($sectionKey === 'amenities'): ?>
            <div class="scout-report-grid">
                <?php foreach ($fields as $key => $field): ?>
                    <?php
                    if ($key === 'amenity_none') {
                        continue;
                    }

                    $rawAmenityValue =
                        llama_place_report_get_path(
                            $placeReportData,
                            (string) $field['storage']
                        );

                    $amenityValue =
                        !empty($rawAmenityValue)
                            ? 'Yes'
                            : 'No';

                    $amenityIcon =
                        $localIcon(
                            llama_place_report_field_icon(
                                $key,
                                $field
                            ),
                            $key
                        );

                    $amenityLabel =
                        $key === 'amenity_fire_ring'
                            ? 'Metal fire ring'
                            : (string) $field['label'];
                    ?>

                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span><?= $e($amenityLabel) ?></span>
                            <strong><?= $amenityValue ?></strong>
                        </div>

                        <?= llama_icon(
                            $amenityIcon,
                            [
                                'class' => 'scout-report-value-icon',
                            ]
                        ) ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="scout-report-grid">
                <?php foreach ($fields as $key => $field): ?>
                    <?php $renderValue($key, $field); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
