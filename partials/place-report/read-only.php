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

/* Keep booking and fees near the beginning of the public Scout Report.
 * Moderation continues to use its original registry section sequence.
 */
if ($placeReportReadMode === 'scout-report' && isset($placeReportSections['rules'])) {
    $ruleSection = $placeReportSections['rules'];
    unset($placeReportSections['rules']);
    $earlySections = [];
    foreach ($placeReportSections as $key => $definition) {
        $earlySections[$key] = $definition;
        if ($key === 'campground') {
            $earlySections['rules'] = $ruleSection;
        }
    }
    $placeReportSections = $earlySections;
}


/*
 * The contributor form and completion meter evaluate applicability against
 * flattened scoring input. The public Scout Report must use that exact same
 * input and the exact same canonical applicability function so a question
 * that was never available for this Place cannot reappear as a read-only
 * "Not provided" card.
 */
$placeReportApplicabilityInput =
    llama_place_report_scoring_input_from_data(
        $placeReportData
    );

$placeReportUpdateUrl = '';

if (
    $placeReportReadMode === 'scout-report'
    && !empty($canSuggestUpdate)
    && !empty($place['slug'])
) {
    $placeReportUpdateUrl =
        'https://account.llamascout.com/update-place.php?slug='
        . rawurlencode(
            (string) $place['slug']
        );
}

?>
<link
    rel="stylesheet"
    href="/css/site/features/scout-warning-compact.css"
>
<link
    rel="stylesheet"
    href="/css/site/features/place-report-empty-state.css"
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

$campsiteSwitchableFields =
    array_keys(
        array_filter(
            $placeReportFields,
            static function (
                array $field
            ): bool {
                return (string) (
                        $field[
                            'report_scope_group'
                        ]
                        ?? 'place'
                    )
                    !== 'place';
            }
        )
    );

/* Official contact information is rendered in the existing Campground cards. */
$officialFacility = [];
if ($placeReportReadMode === 'scout-report' && !empty($place['id'])) {
    require_once dirname(__DIR__, 2) . '/app/ridb-facility-details.php';
    try {
        $officialFacility = llama_place_facility_public_data(db(), (int) $place['id']);
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'place.scout-report.facility');
    }
}

/* Preserve simple source formatting without allowing scripts or HTML attributes. */
$renderDescriptionHtml = static function (string $raw) use ($e): string {
    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = preg_replace('~<(script|style|iframe|object|svg|math)\b[^>]*>.*?</\1\s*>~is', '', $decoded) ?? $decoded;
    $safe = strip_tags($decoded, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote>');
    $safe = preg_replace('~<(p|br|strong|b|em|i|u|h2|h3|h4|ul|ol|li|blockquote)\b[^>]*>~i', '<$1>', $safe) ?? $safe;
    $safe = preg_replace('~<br>~i', '<br>', $safe) ?? $safe;
    return $safe;
};

$renderValue =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportData,
        $placeReportReadMode,
        $e,
        $localIcon,
        $campsiteSwitchableFields,
        $renderDescriptionHtml
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

        /*
         * During Issue #5 reconciliation, unanswered values stay visible in
         * every read-only flow so they can be compared against editable input.
         */
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

        <?php
        $isCampsiteSwitchable =
            $placeReportReadMode === 'scout-report'
            && in_array(
                $key,
                $campsiteSwitchableFields,
                true
            );

        /*
         * Reconciliation mode keeps unanswered Site/Area-capable questions
         * visible instead of waiting for a Site selection.
         */
        $hideUntilCampsite =
            false;
        ?>

        <div
            class="<?= $e($class) ?>"
            <?php if ($isCampsiteSwitchable): ?>
                data-campsite-report-field="<?= $e($key) ?>"
                data-campsite-report-type="<?= $e($type) ?>"
            <?php endif; ?>
            <?= $hideUntilCampsite ? 'hidden' : '' ?>
        >

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
                        <?php elseif ($key === 'description' && $state === 'answered'): ?>
                            <?= $renderDescriptionHtml((string) $value) ?>
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

$renderContributionPrompt =
    static function (
        string $sectionLabel,
        int $missingCount,
        bool $empty
    ) use (
        $placeReportUpdateUrl,
        $e
    ): void {
        if ($empty) {
            $message =
                'No one has reported '
                . strtolower($sectionLabel)
                . ' information here yet.';
        } else {
            $message =
                $missingCount
                . ' applicable detail'
                . ($missingCount === 1 ? '' : 's')
                . ' in this section '
                . ($missingCount === 1 ? 'has' : 'have')
                . ' not been reported yet.';
        }
        ?>

        <div
            class="scout-report-contribution-prompt<?= $empty ? ' is-empty' : ' is-partial' ?>"
        >
            <div class="scout-report-contribution-copy">
                <strong>
                    <?= $empty
                        ? 'Be the first to add this'
                        : 'Know something we are missing?' ?>
                </strong>

                <p><?= $e($message) ?></p>
            </div>

            <?php if ($placeReportUpdateUrl !== ''): ?>
                <a
                    href="<?= $e($placeReportUpdateUrl) ?>"
                    class="scout-report-contribution-action"
                >
                    <?= llama_icon('edit') ?>
                    Update this Place
                </a>
            <?php endif; ?>
        </div>
        <?php
    };

/*
 * Warning rules come from the shared Scout Report runtime. Keep a stable
 * container in the page even when the Place itself has no warnings so an Area
 * or Site selection can reveal its own warning set without rebuilding layout.
 */
$warnings =
    llama_place_report_quick_warnings(
        $placeReportData
    );
?>
    <section
        class="scout-report-section scout-report-warning-section"
        data-report-warnings
        <?= $warnings ? '' : 'hidden' ?>
    >
        <h3>
            <?= llama_icon('alert-triangle') ?>
            Quick warnings
        </h3>

        <p
            class="scout-report-summary"
            data-report-warning-summary
        >
            Important conditions reported for this Place.
        </p>

        <div
            class="scout-report-grid"
            data-report-warning-grid
        >
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

<script
    src="/js/place-report-warnings.js"
    defer
></script>
<?php

foreach (
    $placeReportSections
    as $sectionKey => $section
):
    $fields =
        array_filter(
            $placeReportFields,
            static function (
                array $field,
                string|int $fieldKey
            ) use (
                $sectionKey,
                $placeReportReadMode,
                $placeReportApplicabilityInput
            ): bool {
                if (
                    (string) (
                        $field['display_section']
                        ?? $field['section']
                        ?? ''
                    ) !== $sectionKey
                ) {
                    return false;
                }

                if (
                    $placeReportReadMode !== 'moderation'
                    && !empty($field['hide_public'])
                ) {
                    return false;
                }

                return true;
            },
            ARRAY_FILTER_USE_BOTH
        );

    if (!$fields) {
        continue;
    }

    $applicableFields =
        $fields;

    /*
     * Issue #5 reconciliation mode:
     *
     * Do not remove unanswered questions from the Scout Report. The public
     * read-only view now exposes the same question inventory and sequence as
     * the editable flows so answer-state mismatches can be found directly.
     */
    $answeredFields =
        $applicableFields;

    $reportedFields =
        $answeredFields;

    if ($placeReportReadMode === 'scout-report') {
        foreach (
            $applicableFields
            as $fieldKey => $field
        ) {
            if (
                in_array(
                    (string) $fieldKey,
                    $campsiteSwitchableFields,
                    true
                )
            ) {
                $reportedFields[$fieldKey] =
                    $field;
            }
        }
    }

    $missingApplicableCount =
        max(
            0,
            count($applicableFields)
            - count($answeredFields)
        );

    $switchableApplicableCount = 0;
    $switchableAnsweredCount = 0;

    if ($placeReportReadMode === 'scout-report') {
        foreach (
            $applicableFields
            as $fieldKey => $field
        ) {
            if (
                !in_array(
                    (string) $fieldKey,
                    $campsiteSwitchableFields,
                    true
                )
            ) {
                continue;
            }

            $switchableApplicableCount++;

            if (
                llama_place_report_answer_state(
                    $placeReportData,
                    (string) $fieldKey
                ) !== 'unanswered'
            ) {
                $switchableAnsweredCount++;
            }
        }
    }

    $fixedMissingCount =
        max(
            0,
            $missingApplicableCount
            - (
                $switchableApplicableCount
                - $switchableAnsweredCount
            )
        );

    $sectionHasReportedData =
        count($reportedFields) > 0;
?>
    <?php
    $sectionIcon =
        $localIcon(
            (string) (
                $section['icon']
                ?? ''
            ),
            'section:'
            . (string) $sectionKey
        );
    ?>

    <section
        class="scout-report-section"
        <?php if (
            $placeReportReadMode === 'scout-report'
            && $switchableApplicableCount > 0
        ): ?>
            data-report-target-section="1"
            data-report-section-label="<?= $e(
                (string) $section['label']
            ) ?>"
            data-report-applicable-count="<?= count(
                $applicableFields
            ) ?>"
            data-report-fixed-missing="<?= $fixedMissingCount ?>"
        <?php endif; ?>
    >
        <h3>
            <?= llama_icon($sectionIcon) ?>

            <?= $e(
                (string) $section['label']
            ) ?>
        </h3>

        <?php if (
            $placeReportReadMode === 'scout-report'
            && !$sectionHasReportedData
        ): ?>
            <?php if ($switchableApplicableCount < 1): ?>
                <?php
                $renderContributionPrompt(
                    (string) $section['label'],
                    count($applicableFields),
                    true
                );
                ?>
            <?php endif; ?>

        <?php elseif ($sectionKey === 'sensory'): ?>
            <?php foreach (
                [
                    'Daytime',
                    'Nighttime',
                    'Specific sensory conditions',
                ]
                as $subsection
            ): ?>
                <?php
                $subsectionFields =
                    array_filter(
                        $reportedFields,
                        static fn (
                            array $field
                        ): bool =>
                            (string) (
                                $field['subsection']
                                ?? ''
                            )
                            === $subsection
                    );
                ?>

                <?php if (!$subsectionFields): ?>
                    <?php continue; ?>
                <?php endif; ?>

                <div class="scout-report-subsection">
                    <h4><?= $e($subsection) ?></h4>

                    <div class="scout-report-grid">
                        <?php foreach (
                            $subsectionFields
                            as $key => $field
                        ): ?>
                            <?php
                            $renderValue(
                                (string) $key,
                                $field
                            );
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
            $sensorySummaryFields =
                array_filter(
                    $reportedFields,
                    static fn (
                        array $field
                    ): bool =>
                        trim(
                            (string) (
                                $field['subsection']
                                ?? ''
                            )
                        ) === ''
                );
            ?>

            <?php if ($sensorySummaryFields): ?>
                <div class="scout-report-grid">
                    <?php foreach (
                        $sensorySummaryFields
                        as $key => $field
                    ): ?>
                        <?php
                        $renderValue(
                            (string) $key,
                            $field
                        );
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (
                $placeReportReadMode === 'scout-report'
                && $missingApplicableCount > 0
                && $switchableApplicableCount < 1
            ): ?>
                <?php
                $renderContributionPrompt(
                    (string) $section['label'],
                    $missingApplicableCount,
                    false
                );
                ?>
            <?php endif; ?>

        <?php elseif ($sectionKey === 'scout_notes'): ?>
            <ul class="scout-report-notes-list">
                <?php foreach (
                    $reportedFields
                    as $key => $field
                ): ?>
                    <?php
                    $note =
                        llama_place_report_display_value(
                            $placeReportData,
                            (string) $key
                        );

                    /* Issue #6: unanswered notes remain visible for review. */
                    if ($note === null || trim((string) $note) === '') {
                        $note = 'Not provided';
                    }
                    ?>

                    <li>
                        <?= $e(
                            $note
                            ?? 'Not provided'
                        ) ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php elseif ($sectionKey === 'amenities'): ?>
            <?php
            /*
             * Use the canonical Scout Report renderer for every amenity.
             * This shares the same question, answer, and icon positioning as
             * all other report sections, without a separate visual system.
             *
             * The summary-only amenity_none answer does not need a card.
             */
            ?>
            <div class="scout-report-grid">
                <?php foreach ($reportedFields as $key => $field): ?>
                    <?php if ((string) $key !== 'amenity_none'): ?>
                        <?php $renderValue((string) $key, $field); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php
            /* Canonical layout-group order within the existing report section.
             * Keep the surrounding section and Site-switching hooks intact.
             */
            $layoutGroups = llama_place_report_layout_groups();
            $fieldBuckets = [];
            foreach ($reportedFields as $fieldKey => $fieldDefinition) {
                $layoutSection = (string) ($fieldDefinition['layout_section'] ?? '');
                $layoutGroup = (string) ($fieldDefinition['layout_group'] ?? '');
                $bucketKey = $layoutSection . ':' . $layoutGroup;
                $fieldBuckets[$bucketKey][$fieldKey] = $fieldDefinition;
            }
            $orderedBuckets = [];
            foreach ($layoutGroups as $layoutSection => $groups) {
                foreach ($groups as $groupKey => $groupLabel) {
                    $bucketKey = $layoutSection . ':' . $groupKey;
                    if (isset($fieldBuckets[$bucketKey])) {
                        $orderedBuckets[$bucketKey] = [
                            'label' => $groupLabel,
                            'fields' => $fieldBuckets[$bucketKey],
                        ];
                        unset($fieldBuckets[$bucketKey]);
                    }
                }
            }
            foreach ($fieldBuckets as $bucketKey => $remainingFields) {
                $orderedBuckets[$bucketKey] = [
                    'label' => 'Legacy Review',
                    'fields' => $remainingFields,
                ];
            }
            ?>
            <?php foreach ($orderedBuckets as $bucket): ?>
                <div class="scout-report-subsection">
                    <h4><?= $e($bucket['label']) ?></h4>
                    <div class="scout-report-grid">
                        <?php foreach ($bucket['fields'] as $key => $field): ?>
                            <?php $renderValue((string) $key, $field); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (
                $placeReportReadMode === 'scout-report'
                && $missingApplicableCount > 0
                && $switchableApplicableCount < 1
            ): ?>
                <?php
                $renderContributionPrompt(
                    (string) $section['label'],
                    $missingApplicableCount,
                    false
                );
                ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($sectionKey === 'campground' && $officialFacility): ?>
            <?php
            $officialCards = [
                ['Official address', trim((string) ($officialFacility['address'] ?? '')), 'map-pin', ''],
                ['Phone', trim((string) ($officialFacility['phone'] ?? '')), 'phone', 'tel:'],
                ['Email', trim((string) ($officialFacility['email'] ?? '')), 'mail', 'mailto:'],
            ];
            foreach ((array) ($officialFacility['links'] ?? []) as $link) {
                $url = trim((string) ($link['url'] ?? ''));
                if (!preg_match('~^https?://~i', $url)) {
                    continue;
                }
                $officialCards[] = [
                    'Official link',
                    trim((string) ($link['title'] ?? '')) ?: 'Visit official website',
                    'external-link',
                    $url,
                ];
            }
            ?>
            <div class="scout-report-grid">
                <?php foreach ($officialCards as [$label, $answer, $iconName, $linkTarget]): ?>
                    <?php if ($answer === '') continue; ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span><?= $e($label) ?></span>
                            <strong>
                                <?php if ($linkTarget !== ''): ?>
                                    <?php
                                    $href = $linkTarget;
                                    if ($linkTarget === 'tel:') {
                                        $href .= preg_replace('/[^0-9+]/', '', $answer);
                                    } elseif ($linkTarget === 'mailto:') {
                                        $href .= $answer;
                                    }
                                    ?>
                                    <a href="<?= $e($href) ?>" <?= str_starts_with($href, 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= $e($answer) ?></a>
                                <?php else: ?>
                                    <?= $e($answer) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?= llama_icon($localIcon($iconName), ['class' => 'scout-report-value-icon']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (
            $placeReportReadMode === 'scout-report'
            && $switchableApplicableCount > 0
            && $missingApplicableCount > 0
        ): ?>
            <?php
            $targetPromptEmpty =
                count($applicableFields) > 0
                && $missingApplicableCount >= count($applicableFields);

            $targetPromptMessage =
                $targetPromptEmpty
                    ? (
                        'No one has reported '
                        . strtolower(
                            (string) $section['label']
                        )
                        . ' information here yet.'
                    )
                    : (
                        $missingApplicableCount
                        . ' applicable detail'
                        . (
                            $missingApplicableCount === 1
                                ? ''
                                : 's'
                        )
                        . ' in this section '
                        . (
                            $missingApplicableCount === 1
                                ? 'has'
                                : 'have'
                        )
                        . ' not been reported yet.'
                    );
            ?>

            <div
                class="scout-report-contribution-prompt<?= $targetPromptEmpty ? ' is-empty' : ' is-partial' ?>"
                data-target-report-prompt
            >
                <div class="scout-report-contribution-copy">
                    <strong>
                        <?= $targetPromptEmpty
                            ? 'Be the first to add this'
                            : 'Know something we are missing?' ?>
                    </strong>

                    <p><?= $e($targetPromptMessage) ?></p>
                </div>

                <?php if ($placeReportUpdateUrl !== ''): ?>
                    <a
                        href="<?= $e($placeReportUpdateUrl) ?>"
                        class="scout-report-contribution-action"
                    >
                        <?= llama_icon('edit') ?>
                        Update this Place
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
