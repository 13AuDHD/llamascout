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

/*
 * Public Scout Report groups follow the approved questionnaire.
 * The shared field registry remains authoritative for labels, answer values,
 * storage and site switching. Unlisted legacy fields are not displayed.
 */
$publicReportGroups = llama_place_report_approved_question_groups();
/* Official contact data is sourced from the existing RIDB facility resolver. */
$publicFacility = [];
if ($placeReportReadMode === 'scout-report' && !empty($place['id'])) {
    require_once dirname(__DIR__, 2) . '/app/ridb-facility-details.php';
    try {
        $publicFacility = llama_place_facility_public_data(db(), (int) $place['id']);
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'scout-report.facility');
    }
}

/* Render only a limited set of formatting tags, never source HTML attributes. */
$renderSafeDescription = static function (string $raw): string {
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $raw = preg_replace('~<(script|style|iframe|object|svg|form)\b[^>]*>.*?</\1\s*>~is', '', $raw) ?? $raw;
    $raw = strip_tags($raw, '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><h5><blockquote>');
    $raw = preg_replace_callback('~<(/?)([a-z][a-z0-9]*)\b[^>]*>~i', static function (array $m): string {
        $tag = strtolower($m[2]);
        return '<' . $m[1] . $tag . ($tag === 'br' ? ' /' : '') . '>';
    }, $raw) ?? $raw;
    return $raw;
};

/* Place/area groups and site/space groups use the same visual identity as map features. */
$publicPlaceGroups = [
    'basic', 'location', 'amenities', 'operating', 'road_conditions',
    'vehicle_access', 'seasonal_access', 'gate_access', 'nearest_services',
];
$publicPlaceType = strtolower(trim((string) ($place['type'] ?? $placeReportData['type'] ?? '')));
$publicPlaceIsArea = preg_match('/campground|camping area|recreation area|dispersed area|area/', $publicPlaceType) === 1;
$publicSiteType = strtolower(trim((string) ($placeReportData['site_type'] ?? $placeReportData['space_type'] ?? '')));
$publicSiteIsParking = preg_match('/parking|space/', $publicSiteType) === 1;

$publicFieldGroups = [];
if ($placeReportReadMode === 'scout-report') {
    $placeReportSections = [];
    foreach ($publicReportGroups as $groupKey => [$groupLabel, $keys]) {
        $placeReportSections[$groupKey] = ['label' => $groupLabel, 'icon' => 'info-circle'];
        foreach ($keys as $key) {
            // Keep the first occurrence of a shared field to avoid duplicate cards.
            $publicFieldGroups[$key] ??= $groupKey;
        }
    }
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
        $renderSafeDescription
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
                            <?= $renderSafeDescription((string) $value) ?>
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
                $placeReportApplicabilityInput,
                $publicFieldGroups
            ): bool {
                if ($placeReportReadMode === 'scout-report') {
                    return ($publicFieldGroups[(string) $fieldKey] ?? null) === $sectionKey
                        && empty($field['hide_public']);
                }

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

    <?php
    $reportScopeClass = in_array((string) $sectionKey, $publicPlaceGroups, true)
        ? ($publicPlaceIsArea ? 'scout-report-scope-place' : 'scout-report-scope-other-place')
        : ($publicSiteIsParking ? 'scout-report-scope-space' : 'scout-report-scope-site');
    ?>
    <section
        class="scout-report-section<?= $placeReportReadMode === 'scout-report' ? ' ' . $reportScopeClass : '' ?>"
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

        <?php if ($placeReportReadMode === 'scout-report' && $sectionKey === 'basic'): ?>
            <?php
            $officialContact = [
                'Official Address' => ['value' => trim((string) ($publicFacility['address'] ?? '')), 'icon' => 'map-pin'],
                'Phone Number' => ['value' => trim((string) ($publicFacility['phone'] ?? '')), 'icon' => 'phone'],
                'Email Address' => ['value' => trim((string) ($publicFacility['email'] ?? '')), 'icon' => 'mail'],
            ];
            ?>
            <div class="scout-report-grid">
                <?php foreach ($officialContact as $contactLabel => $contact): ?>
                    <?php if ($contact['value'] === '') continue; ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span><?= $e($contactLabel) ?></span>
                            <strong>
                                <?php if ($contactLabel === 'Phone Number'): ?>
                                    <a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $contact['value'])) ?>"><?= $e($contact['value']) ?></a>
                                <?php elseif ($contactLabel === 'Email Address'): ?>
                                    <a href="mailto:<?= $e($contact['value']) ?>"><?= $e($contact['value']) ?></a>
                                <?php else: ?>
                                    <?= $e($contact['value']) ?>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?= llama_icon($contact['icon'], ['class' => 'scout-report-value-icon']) ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ((array) ($publicFacility['links'] ?? []) as $officialLink): ?>
                    <?php
                    $linkUrl = trim((string) ($officialLink['url'] ?? ''));
                    if (!preg_match('~^https?://~i', $linkUrl)) continue;
                    $linkTitle = trim((string) ($officialLink['title'] ?? ''));
                    ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span>Official Links</span>
                            <strong><a href="<?= $e($linkUrl) ?>" target="_blank" rel="noopener noreferrer"><?= $e($linkTitle !== '' ? $linkTitle : $linkUrl) ?></a></strong>
                        </div>
                        <?= llama_icon('external-link', ['class' => 'scout-report-value-icon']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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

        <?php elseif ($placeReportReadMode === 'scout-report'): ?>
            <div class="scout-report-grid">
                <?php foreach ($publicReportGroups[$sectionKey][1] as $key): ?>
                    <?php if (isset($reportedFields[$key])): ?>
                        <?php $renderValue($key, $reportedFields[$key]); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php
            /* Canonical layout-group order within the existing report section.
             * Keep the surrounding section and Site-switching hooks intact.
             */
            // Use the same canonical grouping and order as the editable form.
            $orderedBuckets = array_filter(
                llama_place_report_group_eligible_questions($reportedFields),
                static fn (array $bucket): bool => !empty($bucket['fields'])
            );
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
