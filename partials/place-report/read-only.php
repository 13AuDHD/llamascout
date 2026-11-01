<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$placeReportData =
    is_array($placeReportData ?? null)
        ? $placeReportData
        : [];

$placeReportReadMode =
    (string) ($placeReportReadMode ?? 'scout-report');

$placeReportCanViewCommunity =
    (bool) ($placeReportCanViewCommunity ?? true);

/*
 * Only public Place questions receive first-render report attribution here.
 * Site content is switched client-side and must receive its own provenance
 * alongside its Site-scoped answer, not a Place-level attribution.
 */
$placeReportFields =
    llama_report_questions();

$publicPlaceReportAttribution = [];
if (
    $placeReportReadMode === 'scout-report'
    && !empty($place['id'])
) {
    try {
        $placeId = (int) $place['id'];
        $observations = llama_scout_report_latest_approved_answers(
            db(), $placeId, 'place', 'place', $placeId
        );
        $visibility = llama_place_report_approved_question_visibility();
        $published = llama_place_report_current_place_answers(db(), $placeId);
        $matched = llama_scout_report_attach_matching_history($published, $observations);
        $sources = llama_report_answer_sources(db(), $matched);
        foreach ($sources as $key => $source) {
            $field = $placeReportFields[$key] ?? llama_report_question((string) $key);
            if (($visibility[$key] ?? 'community') !== 'public'
                || !in_array('place', (array) ($field['report_scopes'] ?? []), true)) {
                continue;
            }
            $publicPlaceReportAttribution[$key] = $source;
        }
    } catch (Throwable $exception) {
        // Attribution is optional: never break the existing public cards.
        if (function_exists('llama_log_caught_exception')) {
            llama_log_caught_exception($exception, 'scout-report.attribution');
        }
    }
}

$placeReportSections =
    llama_place_report_sections();

/*
 * Published Scout Report groups follow the canonical two-page definition.
 * The shared field registry remains authoritative for labels, answer values,
 * storage and site switching. Unlisted legacy fields are not displayed.
 */
$publicReportGroups =
    llama_report_public_question_groups();

$approvedGroupPages =
    llama_place_report_approved_group_page_lookup();
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
        $renderSafeDescription,
        $publicPlaceReportAttribution,
        $placeReportCanViewCommunity
    ): void {
        $visibility =
            (string) (
                $field['approved_visibility']
                ?? $field['report_visibility']
                ?? 'community'
            );

        $isAccessLocked =
            $placeReportReadMode === 'scout-report'
            && !$placeReportCanViewCommunity
            && $visibility !== 'public';

        if ($isAccessLocked) {
            $state = 'locked';
            $value = 'Full Member';
        } else {
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
        }

        if (
            !$isAccessLocked
            && $placeReportReadMode === 'scout-report'
            && !in_array('place', (array) ($field['report_scopes'] ?? []), true)
        ) {
            $state = 'unanswered';
            $value = null;
        }
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
            )
            . (
                $isAccessLocked
                    ? ' is-access-locked'
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

        $fieldReportScopes =
            array_values(
                array_intersect(
                    ['place', 'area', 'site'],
                    array_map(
                        'strval',
                        (array) (
                            $field['report_scopes']
                            ?? []
                        )
                    )
                )
            );
        ?>

        <div
            class="<?= $e($class) ?>"
            <?php if ($isCampsiteSwitchable): ?>
                data-campsite-report-field="<?= $e($key) ?>"
                data-campsite-report-type="<?= $e($type) ?>"
            <?php endif; ?>
            <?php if (
                $placeReportReadMode === 'scout-report'
                && $fieldReportScopes
            ): ?>
                data-report-scopes="<?= $e(
                    implode(
                        ' ',
                        $fieldReportScopes
                    )
                ) ?>"
            <?php endif; ?>
            <?php if ($isAccessLocked): ?>
                data-report-access-locked="1"
            <?php endif; ?>
            <?= $hideUntilCampsite ? 'hidden' : '' ?>
        >

            <?php if ($isAccessLocked): ?>
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
                        <?= llama_icon('lock') ?>
                        Full Member
                    </strong>
                </div>

                <?php
                $lockedFieldIcon =
                    $localIcon(
                        llama_place_report_field_icon(
                            $key,
                            $field
                        ),
                        $key
                    );
                ?>

                <?= llama_icon(
                    $lockedFieldIcon,
                    [
                        'class' => 'scout-report-value-icon',
                    ]
                ) ?>

            <?php elseif ($isRating): ?>
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

                <?php
                $publicAttribution =
                    !$isCampsiteSwitchable
                    ? ($publicPlaceReportAttribution[$key] ?? null)
                    : null;
                if (is_array($publicAttribution)):
                    $dateLabel = (string) ($publicAttribution['dateLabel'] ?? '');
                ?>
                    <small class="scout-report-answer-source">
                        <?php if ($dateLabel !== ''): ?>
                            <?= $e($dateLabel) ?> ·
                        <?php endif; ?>
                        <?= $e((string) $publicAttribution['label']) ?>
                    </small>
                <?php endif; ?>

        </div>
        <?php
    };

$renderCompoundValue =
    static function (
        string $compoundKey,
        array $componentFields
    ) use (
        $placeReportData,
        $placeReportReadMode,
        $placeReportCanViewCommunity,
        $e,
        $localIcon
    ): void {
        $definitions =
            llama_place_report_approved_compound_questions();

        $definition =
            $definitions[$compoundKey]
            ?? null;

        if (!is_array($definition)) {
            return;
        }

        $isAccessLocked =
            $placeReportReadMode === 'scout-report'
            && !$placeReportCanViewCommunity
            && array_reduce(
                $componentFields,
                static function (bool $locked, array $field): bool {
                    if ($locked) {
                        return true;
                    }

                    $visibility =
                        (string) (
                            $field['approved_visibility']
                            ?? $field['report_visibility']
                            ?? 'community'
                        );

                    return $visibility !== 'public';
                },
                false
            );

        $compoundReportScopes =
            array_values(
                array_unique(
                    array_merge(
                        ...array_map(
                            static fn (array $componentField): array =>
                                array_values(
                                    array_intersect(
                                        ['place', 'area', 'site'],
                                        array_map(
                                            'strval',
                                            (array) (
                                                $componentField['report_scopes']
                                                ?? []
                                            )
                                        )
                                    )
                                ),
                            array_values(
                                $componentFields
                            )
                        )
                    )
                )
            );

        if ($isAccessLocked) {
            ?>
            <div
                class="scout-report-item scout-report-value-item is-access-locked"
                data-report-compound-question="<?= $e($compoundKey) ?>"
                <?php if (
                    $placeReportReadMode === 'scout-report'
                    && $compoundReportScopes
                ): ?>
                    data-report-scopes="<?= $e(
                        implode(
                            ' ',
                            $compoundReportScopes
                        )
                    ) ?>"
                <?php endif; ?>
            >
                <div class="scout-report-value-content">
                    <span>
                        <?= $e(
                            (string) (
                                $definition['label']
                                ?? $compoundKey
                            )
                        ) ?>
                    </span>

                    <strong>
                        <?= llama_icon('lock') ?>
                        Full Member
                    </strong>
                </div>

                <?= llama_icon(
                    'lock',
                    [
                        'class' => 'scout-report-value-icon',
                    ]
                ) ?>
            </div>
            <?php
            return;
        }

        $parts = [];
        $overallState = 'answered';
        $firstField = null;

        foreach ((array) ($definition['components'] ?? []) as $componentKey => $component) {
            if (!isset($componentFields[$componentKey])) {
                continue;
            }

            $field =
                $componentFields[$componentKey];

            $firstField ??= $field;

            $state =
                llama_place_report_answer_state(
                    $placeReportData,
                    (string) $componentKey
                );

            $value =
                llama_place_report_display_value(
                    $placeReportData,
                    (string) $componentKey
                );

            $role =
                (string) (
                    $component['role']
                    ?? ''
                );

            if ($state === 'answered' && $value !== null && trim((string) $value) !== '') {
                $parts[$role] =
                    (string) $value;
                continue;
            }

            if ($state === 'unknown') {
                $parts[$role] =
                    $role === 'month'
                        ? 'Unknown month'
                        : 'Unknown day';
                $overallState = 'unknown';
                continue;
            }

            $parts[$role] =
                $role === 'month'
                    ? 'Month not provided'
                    : 'Day not provided';

            if ($overallState !== 'unknown') {
                $overallState = 'unanswered';
            }
        }

        $month =
            trim(
                (string) (
                    $parts['month']
                    ?? ''
                )
            );

        $day =
            trim(
                (string) (
                    $parts['day']
                    ?? ''
                )
            );

        if (
            $month === 'Month not provided'
            && $day === 'Day not provided'
        ) {
            $displayValue =
                'Not provided';
        } elseif (
            $month !== ''
            && !str_contains(
                strtolower($month),
                'unknown'
            )
            && !str_contains(
                strtolower($month),
                'not provided'
            )
            && $day !== ''
            && !str_contains(
                strtolower($day),
                'unknown'
            )
            && !str_contains(
                strtolower($day),
                'not provided'
            )
        ) {
            $displayValue =
                $month
                . ' '
                . $day;
            $overallState =
                'answered';
        } else {
            $displayValue =
                implode(
                    ' · ',
                    array_values(
                        array_filter(
                            [$month, $day],
                            static fn (string $part): bool =>
                                $part !== ''
                        )
                    )
                );
        }

        $class =
            'scout-report-item scout-report-value-item'
            . (
                $overallState === 'unknown'
                    ? ' is-explicit-unknown'
                    : ''
            )
            . (
                $overallState === 'unanswered'
                    ? ' is-unanswered'
                    : ''
            );

        $icon =
            $firstField !== null
                ? $localIcon(
                    llama_place_report_field_icon(
                        (string) ($firstField['key'] ?? ''),
                        $firstField
                    ),
                    (string) ($firstField['key'] ?? '')
                )
                : 'calendar';
        ?>
        <div
            class="<?= $e($class) ?>"
            data-report-compound-question="<?= $e($compoundKey) ?>"
        >
            <div class="scout-report-value-content">
                <span>
                    <?= $e(
                        (string) (
                            $definition['label']
                            ?? $compoundKey
                        )
                    ) ?>
                </span>

                <strong><?= $e($displayValue) ?></strong>
            </div>

            <?= llama_icon(
                $icon,
                [
                    'class' => 'scout-report-value-icon',
                ]
            ) ?>
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
    $placeReportCanViewCommunity
        ? llama_place_report_quick_warnings(
            $placeReportData
        )
        : [];
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
    $reportPageKey =
        (string) (
            $approvedGroupPages[(string) $sectionKey]['page']
            ?? ''
        );

    /*
     * V3 context identity is based on the active record level, not the Place
     * or Site type label. Parent Place content is green; Site content is
     * orange. Area/space accents are applied when those child contexts are
     * selected in the remaining Issue #41 Area work.
     */
    $reportScopeClass =
        $reportPageKey === 'place'
            ? 'scout-report-scope-place'
            : 'scout-report-scope-site';

    $sectionReportScopes =
        [];

    foreach (
        (array) (
            $publicReportGroups[$sectionKey][1]
            ?? []
        )
        as $sectionFieldKey
    ) {
        $sectionField =
            $placeReportFields[
                (string) $sectionFieldKey
            ]
            ?? null;

        if (!is_array($sectionField)) {
            continue;
        }

        foreach (
            (array) (
                $sectionField['report_scopes']
                ?? []
            )
            as $sectionScope
        ) {
            $sectionScope =
                (string) $sectionScope;

            if (in_array(
                $sectionScope,
                ['place', 'area', 'site'],
                true
            )) {
                $sectionReportScopes[
                    $sectionScope
                ] = true;
            }
        }
    }
    ?>
    <section
        class="scout-report-section<?= $placeReportReadMode === 'scout-report' ? ' ' . $reportScopeClass : '' ?>"
        <?php if (
            $placeReportReadMode === 'scout-report'
            && $reportPageKey !== ''
        ): ?>
            data-report-page="<?= $e($reportPageKey) ?>"
        <?php endif; ?>
        <?php if (
            $placeReportReadMode === 'scout-report'
            && $sectionReportScopes
        ): ?>
            data-report-scopes="<?= $e(
                implode(
                    ' ',
                    array_keys(
                        $sectionReportScopes
                    )
                )
            ) ?>"
        <?php endif; ?>
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
                'Official Address' => [
                    'value' => trim((string) ($publicFacility['address'] ?? '')),
                    'icon' => 'map-pin',
                    'kind' => 'text',
                ],
                'Phone Number' => [
                    'value' => trim((string) ($publicFacility['phone'] ?? '')),
                    'icon' => 'phone',
                    'kind' => 'phone',
                ],
                'Email Address' => [
                    'value' => trim((string) ($publicFacility['email'] ?? '')),
                    'icon' => 'mail',
                    'kind' => 'email',
                ],
                'Published Fees' => [
                    'value' => trim((string) ($publicFacility['fee_description'] ?? '')),
                    'icon' => 'receipt',
                    'kind' => 'text',
                ],
                'Reservations' => [
                    'value' => trim((string) ($publicFacility['reservation_url'] ?? '')),
                    'icon' => 'calendar',
                    'kind' => 'url',
                ],
            ];
            ?>
            <div class="scout-report-grid">
                <?php foreach ($officialContact as $contactLabel => $contact): ?>
                    <?php if ($contact['value'] === '') continue; ?>
                    <div class="scout-report-item scout-report-value-item">
                        <div class="scout-report-value-content">
                            <span><?= $e($contactLabel) ?></span>
                            <strong>
                                <?php if (($contact['kind'] ?? '') === 'phone'): ?>
                                    <a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $contact['value'])) ?>"><?= $e($contact['value']) ?></a>
                                <?php elseif (($contact['kind'] ?? '') === 'email'): ?>
                                    <a href="mailto:<?= $e($contact['value']) ?>"><?= $e($contact['value']) ?></a>
                                <?php elseif (
                                    ($contact['kind'] ?? '') === 'url'
                                    && preg_match('~^https?://~i', (string) $contact['value']) === 1
                                ): ?>
                                    <a
                                        href="<?= $e($contact['value']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    ><?= $e($contact['value']) ?></a>
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
                <?php
                $renderedCompoundQuestions = [];
                ?>
                <?php foreach ($publicReportGroups[$sectionKey][1] as $key): ?>
                    <?php if (!isset($reportedFields[$key])): ?>
                        <?php continue; ?>
                    <?php endif; ?>

                    <?php
                    $field =
                        $reportedFields[$key];

                    $compoundKey =
                        trim(
                            (string) (
                                $field['approved_compound_question']
                                ?? ''
                            )
                        );

                    if ($compoundKey !== '') {
                        if (isset($renderedCompoundQuestions[$compoundKey])) {
                            continue;
                        }

                        $renderedCompoundQuestions[$compoundKey] = true;
                        $componentFields = [];

                        foreach ($reportedFields as $candidateKey => $candidateField) {
                            if (
                                (string) (
                                    $candidateField['approved_compound_question']
                                    ?? ''
                                ) === $compoundKey
                            ) {
                                $componentFields[(string) $candidateKey] =
                                    $candidateField;
                            }
                        }

                        $renderCompoundValue(
                            $compoundKey,
                            $componentFields
                        );

                        continue;
                    }

                    $renderValue(
                        (string) $key,
                        $field
                    );
                    ?>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <?php
            /* Canonical layout-group order within the existing report section.
             * Keep the surrounding section and Site-switching hooks intact.
             */
            // Use the same canonical grouping and order as the editable form.
            $orderedBuckets = array_filter(
                llama_report_group_selected_questions($reportedFields),
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

