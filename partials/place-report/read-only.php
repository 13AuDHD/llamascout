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

$e = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$renderValue =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportData,
        $placeReportReadMode,
        $e
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

        $class =
            'scout-report-item scout-report-value-item'
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

        $type = (string) $field['type'];
        ?>
        <div class="<?= $e($class) ?>">
            <div class="scout-report-value-content">
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

            <?php if (
                $type === 'rating'
                && $state === 'answered'
            ): ?>
                <?php
                $rating =
                    (int) llama_place_report_get_path(
                        $placeReportData,
                        (string) $field['storage']
                    );
                ?>

                <div
                    class="scout-rating-dots"
                    aria-label="<?= $rating ?> out of 5"
                >
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span
                            class="scout-rating-dot<?= $i <= $rating ? ' is-filled' : '' ?>"
                            aria-hidden="true"
                        ></span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    };

/*
 * Quick warnings are derived from the same field definitions.
 */
$warnings = [];

foreach ($placeReportFields as $key => $field) {
    if (empty($field['warning'])) {
        continue;
    }

    $state =
        llama_place_report_answer_state(
            $placeReportData,
            $key
        );

    $raw =
        llama_place_report_get_path(
            $placeReportData,
            (string) $field['storage']
        );

    if (
        $state === 'answered'
        && (bool) $raw
    ) {
        $warnings[$key] = $field;
    }
}

if ($warnings):
?>
    <section class="scout-report-section scout-report-warning-section">
        <h3>
            <i
                class="fa-solid fa-triangle-exclamation"
                aria-hidden="true"
            ></i>
            Quick warnings
        </h3>

        <p class="scout-report-summary">
            Important conditions reported for this Place.
        </p>

        <div class="scout-report-grid">
            <?php foreach ($warnings as $field): ?>
                <div
                    class="scout-report-item scout-report-value-item scout-report-warning-item"
                >
                    <div class="scout-report-value-content">
                        <span>
                            <?= $e(
                                rtrim(
                                    (string) $field['label'],
                                    '?'
                                )
                            ) ?>
                        </span>

                        <strong>Warning</strong>
                    </div>
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
?>
    <section class="scout-report-section">
        <h3>
            <i
                class="fa-solid <?= $e($section['icon']) ?>"
                aria-hidden="true"
            ></i>

            <?= $e($section['label']) ?>
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

        <?php elseif ($sectionKey === 'amenities'): ?>
            <?php
            $noAmenities =
                !empty(
                    llama_place_report_get_path(
                        $placeReportData,
                        'details.warning_no_amenities'
                    )
                );

            $selectedAmenities = [];

            foreach ($fields as $key => $field) {
                if ($key === 'amenity_none') {
                    continue;
                }

                if (
                    (bool) llama_place_report_get_path(
                        $placeReportData,
                        (string) $field['storage']
                    )
                ) {
                    $selectedAmenities[$key] = $field;
                }
            }
            ?>

            <div class="scout-report-grid">
                <?php if ($noAmenities): ?>
                    <?php
                    $renderValue(
                        'amenity_none',
                        $placeReportFields['amenity_none']
                    );
                    ?>

                <?php elseif ($selectedAmenities): ?>
                    <?php foreach ($fields as $key => $field): ?>
                        <?php if ($key === 'amenity_none'): ?>
                            <?php continue; ?>
                        <?php endif; ?>

                        <?php if (isset($selectedAmenities[$key])): ?>
                            <?php $renderValue($key, $field); ?>
                        <?php elseif ($placeReportReadMode === 'moderation'): ?>
                            <div
                                class="scout-report-item scout-report-value-item is-unanswered"
                            >
                                <div class="scout-report-value-content">
                                    <span><?= $e($field['label']) ?></span>
                                    <strong>No</strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div
                        class="scout-report-item scout-report-value-item is-unanswered"
                    >
                        <div class="scout-report-value-content">
                            <span>Amenities observation</span>
                            <strong>Not provided</strong>
                        </div>
                    </div>
                <?php endif; ?>
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
