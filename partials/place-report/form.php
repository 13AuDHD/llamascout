<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';


/*
 * =========================================================
 * SHARED PLACE REPORT FORM CONFIGURATION
 * =========================================================
 */

$placeReportValues =
    is_array($placeReportValues ?? null)
        ? $placeReportValues
        : [];

$placeReportExistingPhotos =
    is_array($placeReportExistingPhotos ?? null)
        ? $placeReportExistingPhotos
        : [];

$placeReportMode =
    (string) (
        $placeReportMode
        ?? 'contributor'
    );

$placeReportShowLocate =
    (bool) (
        $placeReportShowLocate
        ?? false
    );

$placeReportShowNameSuggestion =
    (bool) (
        $placeReportShowNameSuggestion
        ?? false
    );

$placeReportShowFieldHelp =
    (bool) (
        $placeReportShowFieldHelp
        ?? false
    );

$placeReportShowPhotos =
    (bool) (
        $placeReportShowPhotos
        ?? true
    );

$placeReportPhotoContext =
    trim(
        (string) (
            $placeReportPhotoContext
            ?? 'add-place'
        )
    );

if ($placeReportPhotoContext === '') {
    $placeReportPhotoContext =
        'add-place';
}

$placeReportPhotoEndpoint =
    (string) (
        $placeReportPhotoEndpoint
        ?? ''
    );

$placeReportPhotoCsrf =
    (string) (
        $placeReportPhotoCsrf
        ?? ''
    );

$placeReportPhotoMax =
    max(
        1,
        (int) (
            $placeReportPhotoMax
            ?? 10
        )
    );

$placeReportPhotoTitle =
    (string) (
        $placeReportPhotoTitle
        ?? 'Photos of this Place'
    );

$placeReportPhotoHelp =
    (string) (
        $placeReportPhotoHelp
        ?? 'Add up to 10 current photos. Signs, gates, washouts, road conditions, parking areas, and obstructions are especially useful. Location metadata is removed before permanent storage.'
    );


/*
 * Optional storage-path allow-list.
 *
 * Normal Add Place / moderation pages leave this undefined and
 * therefore receive the complete shared Place Report.
 *
 * Suggest an Update supplies the storage paths currently supported
 * by its update persistence layer so unsupported fields are never
 * shown as though they could be updated.
 */
$placeReportAllowedStoragePaths =
    is_array(
        $placeReportAllowedStoragePaths
        ?? null
    )
        ? array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (mixed $value): string =>
                            trim(
                                (string) $value
                            ),
                        $placeReportAllowedStoragePaths
                    ),
                    static fn (string $value): bool =>
                        $value !== ''
                )
            )
        )
        : null;

/*
 * Issue #5 reconciliation: show the entire canonical question set.
 * Visibility is independent of the current workflow's save capabilities.
 * Set false only when restoring workflow storage filtering intentionally.
 */
$placeReportReconciliationMode =
    (bool) ($placeReportReconciliationMode ?? true);

$placeReportAllowedStorageLookup =
    $placeReportAllowedStoragePaths !== null
        ? array_fill_keys(
            $placeReportAllowedStoragePaths,
            true
        )
        : null;


/*
 * =========================================================
 * SHARED FIELD DEFINITIONS
 * =========================================================
 */

$placeReportFields =
    llama_place_report_fields();

$placeReportSections =
    llama_place_report_sections();

/*
 * A parent workflow may render submission metadata in its own section.
 * Keep those fields in the canonical registry and browser schema, but do
 * not render a second control with the same name in this question area.
 */
$placeReportExternalFieldLookup =
    array_fill_keys(
        (array) ($placeReportExternalFieldKeys ?? []),
        true
    );

$placeReportRequiredFields =
    is_array(
        $placeReportRequiredFields
        ?? null
    )
        ? array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (mixed $value): string =>
                            trim((string) $value),
                        $placeReportRequiredFields
                    ),
                    static fn (string $value): bool =>
                        $value !== ''
                )
            )
        )
        : [];

foreach ($placeReportRequiredFields as $requiredFieldKey) {
    if (!isset($placeReportFields[$requiredFieldKey])) {
        continue;
    }

    $placeReportFields[$requiredFieldKey]['required'] = true;

    $requiredLabel = rtrim(
        (string) ($placeReportFields[$requiredFieldKey]['label'] ?? ''),
        " *"
    );

    $placeReportFields[$requiredFieldKey]['label'] =
        $requiredLabel . ' *';
}


/*
 * If this consumer supplied an allow-list, remove fields whose
 * storage path is not available to that workflow.
 */
if (
    !$placeReportReconciliationMode
    && is_array(
        $placeReportAllowedStorageLookup
    )
) {
    $placeReportFields =
        array_filter(
            $placeReportFields,
            static function (
                array $field
            ) use (
                $placeReportAllowedStorageLookup
            ): bool {
                $storage =
                    trim(
                        (string) (
                            $field['storage']
                            ?? ''
                        )
                    );

                return
                    $storage !== ''
                    && isset(
                        $placeReportAllowedStorageLookup[
                            $storage
                        ]
                    );
            }
        );
}


$placeReportBrowserFields = [];

foreach ($placeReportFields as $fieldKey => $field) {
    $placeReportBrowserFields[] = [
        'key' => (string) $fieldKey,
        'layout_section' => (string) ($field['layout_section'] ?? ''),
        'layout_group' => (string) ($field['layout_group'] ?? ''),
        'label' => (string) ($field['label'] ?? $fieldKey),
        'type' => (string) ($field['type'] ?? ''),
        'completion_group' => isset($field['completion_group'])
            && $field['completion_group'] !== null
                ? (string) $field['completion_group']
                : null,
        'min_characters' => max(0, (int) ($field['min_characters'] ?? 0)),
        'counts_toward_completion' => !array_key_exists(
            'counts_toward_completion',
            $field
        ) || (bool) $field['counts_toward_completion'],
        'applicable_if' => array_values((array) ($field['applicable_if'] ?? [])),
        'derived' => !empty($field['derived']),
    ];
}

$placeReportBrowserConfig = [
    'unknown_token' => llama_place_report_unknown_token(),
    'unanswered_token' => llama_place_report_unanswered_token(),
    'fields' => $placeReportBrowserFields,
];

$e =
    static fn (mixed $value): string =>
        htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );


/*
 * =========================================================
 * FIELD VALUE HELPERS
 * =========================================================
 */

$valueFor =
    static function (
        string $key,
        array $field
    ) use (
        $placeReportValues
    ): mixed {
        if (
            array_key_exists(
                $key,
                $placeReportValues
            )
        ) {
            $value =
                $placeReportValues[$key];

            if (is_array($value)) {
                return array_values($value);
            }

            return is_scalar($value)
                ? (string) $value
                : '';
        }

        return isset(
            $field['default']
        )
            ? (string) $field['default']
            : '';
    };


/*
 * The combined coordinate control replaces the individual latitude /
 * longitude inputs whenever locating is enabled. Seed it from the same
 * canonical form values as every other field so editing an existing Place
 * never opens with blank hidden coordinates and then saves them as NULL.
 */
$combinedLatitude =
    trim(
        (string) (
            $placeReportValues['latitude']
            ?? ''
        )
    );

$combinedLongitude =
    trim(
        (string) (
            $placeReportValues['longitude']
            ?? ''
        )
    );

$combinedCoordinates =
    (
        $combinedLatitude !== ''
     && $combinedLongitude !== ''
    )
        ? $combinedLatitude
            . ', '
            . $combinedLongitude
        : '';


$renderSelect =
    static function (
        array $field,
        string $current
    ) use (
        $e
    ): void {
        $unknown =
            llama_place_report_unknown_token();

        $options =
            (array) (
                $field['options']
                ?? []
            );

        $legacyValues =
            (array) (
                $field['legacy_values']
                ?? []
            );
        ?>

        <select
            name="<?= $e(
                $field['key']
            ) ?>"
            <?= !empty(
                $field['location_field']
            )
                ? 'data-location-field="'
                    . $e(
                        $field['key']
                    )
                    . '"'
                : '' ?>
        >

            <option
                value=""
                <?= $current === ''
                    ? 'selected'
                    : '' ?>
            >
                Select...
            </option>


            <?php if (
                !empty(
                    $field['allow_unknown']
                )
            ): ?>

                <option
                    value="<?= $e(
                        $unknown
                    ) ?>"
                    <?= $current === $unknown
                        ? 'selected'
                        : '' ?>
                >
                    Unknown / could not determine
                </option>

            <?php endif; ?>


            <?php
            $knownCurrent =
                $current === ''
                || $current === $unknown
                || array_key_exists(
                    $current,
                    $options
                );
            ?>


            <?php if (!$knownCurrent): ?>
                <?php
                $legacyCurrentLabel =
                    array_key_exists(
                        $current,
                        $legacyValues
                    )
                        ? (string) $legacyValues[$current]
                        : '';
                ?>

                <option
                    value="<?= $e(
                        $current
                    ) ?>"
                    selected
                >
                    <?= $e(
                        $legacyCurrentLabel !== ''
                            ? $legacyCurrentLabel
                            : $current . ' (previous entry)'
                    ) ?>
                </option>

            <?php endif; ?>


            <?php foreach (
                $options
                as $value => $label
            ): ?>

                <option
                    value="<?= $e(
                        $value
                    ) ?>"
                    <?= $current === (string) $value
                        ? 'selected'
                        : '' ?>
                >
                    <?= $e(
                        $label
                    ) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <?php
    };


/*
 * =========================================================
 * SHARED FIELD RENDERER
 * =========================================================
 */

$renderField =
    static function (
        array $field
    ) use (
        $e,
        $valueFor,
        $renderSelect,
        $placeReportMode,
        $placeReportShowNameSuggestion,
        $placeReportShowFieldHelp
    ): void {
        $key =
            (string) $field['key'];

        $type =
            (string) $field['type'];

        $current =
            $valueFor(
                $key,
                $field
            );

        $wide =
            !empty(
                $field['wide']
            );

        $unknown =
            llama_place_report_unknown_token();

        $unanswered =
            llama_place_report_unanswered_token();

        $helpText =
            $placeReportShowFieldHelp
                ? trim(
                    (string) (
                        $field['help']
                        ?? ''
                    )
                )
                : '';

        $helpId =
            'place-report-help-'
            . preg_replace(
                '/[^a-z0-9_-]+/i',
                '-',
                $key
            );


        /*
         * =====================================================
         * CHECKBOX
         * =====================================================
         */

        if ($type === 'checkbox') {
            $checked =
                $current === '1';
            ?>

            <label class="contribution-check" data-report-field="<?= $e($key) ?>">

                <input type="hidden" name="_report_present[<?= $e($key) ?>]" value="1">

                <input
                    type="checkbox"
                    name="<?= $e($key) ?>"
                    value="1"
                    <?= $checked
                        ? 'checked'
                        : '' ?>
                    <?= $key === 'amenity_none'
                        ? 'data-place-report-no-amenities'
                        : 'data-place-report-amenity' ?>
                >

                <span>
                    <?= $e(
                        $field['label']
                    ) ?>
                </span>

            </label>

            <?php
            return;
        }


        $class =
            'contribution-field'
            . (
                $wide
                    ? ' contribution-field-wide'
                    : ''
            );

        $usesYesNoControl = $type === 'tri'
            || ($field['control'] ?? '') === 'yes-no';

        $fieldContainerTag =
            $usesYesNoControl || in_array($type, ['multiselect', 'permission', 'rating'], true)
                ? 'div'
                : 'label';
        ?>

        <<?= $fieldContainerTag ?> class="<?= $e(
            $class
        ) ?>" data-report-field="<?= $e($key) ?>">

            <span class="place-report-field-label">
                <span>
                    <?= $e(
                        $field['label']
                    ) ?>
                </span>

                <?php if ($helpText !== ''): ?>
                    <span class="place-report-field-help">
                        <span
                            class="place-report-field-help-toggle"
                            role="button"
                            tabindex="0"
                            aria-label="Help for <?= $e($field['label']) ?>"
                            aria-controls="<?= $e($helpId) ?>"
                            aria-expanded="false"
                            data-place-report-help-toggle="<?= $e($helpId) ?>"
                            title="What does this mean?"
                        >
                            <?= llama_icon('info-circle') ?>
                        </span>

                        <span
                            id="<?= $e($helpId) ?>"
                            class="place-report-field-help-panel"
                            role="dialog"
                            aria-label="<?= $e($field['label']) ?> help"
                            data-place-report-help-panel
                        >
                            <?= $e($helpText) ?>
                        </span>
                    </span>
                <?php endif; ?>
            </span>


            <?php if ($usesYesNoControl): ?>
                <?php
                $yesNoField = $field;
                $yesNoValue = $current;
                require __DIR__ . '/yes-no-control.php';
                ?>
            <?php elseif (
                in_array(
                    $type,
                    [
                        'tri',
                        'permission',
                        'rating',
                    ],
                    true
                )
            ): ?>


                <input
                    type="hidden"
                    name="<?= $e($key) ?>"
                    value="<?= $e(
                        $unanswered
                    ) ?>"
                    data-place-report-hidden="<?= $e(
                        $key
                    ) ?>"
                >


                <div
                    class="add-place-radio-control"
                    data-radio-type="<?= $type === 'tri'
                        ? 'yes-no'
                        : (
                            $type === 'permission'
                                ? 'permission'
                                : 'rating'
                        ) ?>"
                >

                    <div class="add-place-radio-row">

                        <div class="add-place-radio-options">


                            <label
                                class="add-place-radio-option is-unknown"
                            >

                                <input
                                    type="radio"
                                    name="<?= $e(
                                        $key
                                    ) ?>"
                                    value="<?= $e(
                                        $unknown
                                    ) ?>"
                                    <?= $current === $unknown
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span>?</span>

                            </label>


                            <?php if (
                                in_array(
                                    $type,
                                    ['tri', 'permission'],
                                    true
                                )
                            ): ?>


                                <label
                                    class="add-place-radio-option"
                                >

                                    <input
                                        type="radio"
                                        name="<?= $e(
                                            $key
                                        ) ?>"
                                        value="1"
                                        <?= $current === '1'
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span>Yes</span>

                                </label>


                                <label
                                    class="add-place-radio-option"
                                >

                                    <input
                                        type="radio"
                                        name="<?= $e(
                                            $key
                                        ) ?>"
                                        value="0"
                                        <?= $current === '0'
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span>No</span>

                                </label>


                                <?php if ($type === 'permission'): ?>

                                    <label
                                        class="add-place-radio-option"
                                    >

                                        <input
                                            type="radio"
                                            name="<?= $e(
                                                $key
                                            ) ?>"
                                            value="2"
                                            <?= $current === '2'
                                                ? 'checked'
                                                : '' ?>
                                        >

                                        <span>Permit</span>

                                    </label>

                                <?php endif; ?>


                            <?php else: ?>


                                <?php for (
                                    $i = 1;
                                    $i <= 5;
                                    $i++
                                ): ?>

                                    <label
                                        class="add-place-radio-option"
                                    >

                                        <input
                                            type="radio"
                                            name="<?= $e(
                                                $key
                                            ) ?>"
                                            value="<?= $i ?>"
                                            <?= $current === (string) $i
                                                ? 'checked'
                                                : '' ?>
                                    >

                                        <span>
                                            <?= $i ?>
                                        </span>

                                    </label>

                                <?php endfor; ?>


                            <?php endif; ?>


                        </div>


                        <button
                            class="add-place-radio-clear"
                            type="button"
                            data-place-report-clear="<?= $e(
                                $key
                            ) ?>"
                        >
                            Clear
                        </button>

                    </div>


                    <div
                        class="add-place-radio-help<?= in_array(
                            $type,
                            ['tri', 'permission'],
                            true
                        )
                            ? ' is-simple'
                            : '' ?>"
                    >

                        <?php if (
                            in_array(
                                $type,
                                ['tri', 'permission'],
                                true
                            )
                        ): ?>

                            ? = Unknown / could not confidently determine

                        <?php else: ?>

                            <span>
                                1 =
                                <?= $e(
                                    $field['low']
                                    ?? 'Low'
                                ) ?>
                            </span>

                            <span>
                                5 =
                                <?= $e(
                                    $field['high']
                                    ?? 'High'
                                ) ?>
                            </span>

                            <span>
                                ? = Unknown
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


            <?php elseif (
                $type === 'multiselect'
            ): ?>


                <?php
                $selectedValues =
                    is_array($current)
                        ? array_map('strval', $current)
                        : [];

                $multiOptions =
                    (array) (
                        $field['options']
                        ?? []
                    );

                ?>

                <input type="hidden" name="_report_present[<?= $e($key) ?>]" value="1">
                <div class="place-report-multiselect" data-place-report-multiselect>
                    <?php if (!empty($field['allow_unknown'])): ?>
                        <label><input type="checkbox" name="_report_unknown[<?= $e($key) ?>]"
                            value="1" data-report-multiselect-unknown
                            <?= $current === $unknown ? 'checked' : '' ?>> Unknown</label>
                    <?php endif; ?>
                    <div class="place-report-multiselect-options">
                            <?php foreach (
                                $multiOptions
                                as $optionValue => $optionLabel
                            ): ?>
                                <label
                                    class="place-report-multiselect-option"
                                    data-place-report-multiselect-option
                                    data-search-text="<?= $e($optionLabel) ?>"
                                >
                                    <input
                                        type="checkbox"
                                        name="<?= $e($key) ?>[]"
                                        value="<?= $e($optionValue) ?>"
                                        <?= in_array(
                                            (string) $optionValue,
                                            $selectedValues,
                                            true
                                        )
                                            ? 'checked'
                                            : '' ?>
                                    >

                                    <span><?= $e($optionLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                    </div>
                </div>


            <?php elseif (in_array($key, ['season_begins', 'season_ends'], true)): ?>

                <?php
                $seasonParts = preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', (string) $current)
                    ? explode('-', (string) $current)
                    : ['', ''];
                $seasonMonths = [
                    '01' => 'January', '02' => 'February', '03' => 'March',
                    '04' => 'April', '05' => 'May', '06' => 'June',
                    '07' => 'July', '08' => 'August', '09' => 'September',
                    '10' => 'October', '11' => 'November', '12' => 'December',
                ];
                ?>

                <div class="place-report-season-picker" data-season-picker>
                    <select aria-label="<?= $e($field['label']) ?> month" data-season-month>
                        <option value="">Month...</option>
                        <?php foreach ($seasonMonths as $monthNumber => $monthName): ?>
                            <option value="<?= $e($monthNumber) ?>" <?= $seasonParts[0] === $monthNumber ? 'selected' : '' ?>><?= $e($monthName) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select aria-label="<?= $e($field['label']) ?> day" data-season-day>
                        <option value="">Day...</option>
                        <?php for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++):
                            $dayValue = sprintf('%02d', $dayNumber); ?>
                            <option value="<?= $e($dayValue) ?>" <?= $seasonParts[1] === $dayValue ? 'selected' : '' ?>><?= $dayNumber ?></option>
                        <?php endfor; ?>
                    </select>
                    <input type="hidden" name="<?= $e($key) ?>" value="<?= $e($current) ?>" data-season-value>
                </div>

            <?php elseif (
                $type === 'select'
            ): ?>


                <?php
                $renderSelect(
                    $field,
                    is_array($current)
                        ? ''
                        : (string) $current
                );
                ?>


            <?php elseif (
                $type === 'textarea'
            ): ?>


                <textarea
                    name="<?= $e($key) ?>"
                    rows="<?= (int) (
                        $field['rows']
                        ?? 4
                    ) ?>"
                    placeholder="<?= $e(
                        $field['placeholder']
                        ?? ''
                    ) ?>"
                    <?= !empty($field['maxlength'])
                        ? 'maxlength="' . (int) $field['maxlength'] . '"'
                        : '' ?>
                ><?= $e(
                    $current
                ) ?></textarea>


            <?php else: ?>


                <?php
                $htmlType =
                    $type === 'number'
                        ? 'number'
                        : (
                            $type === 'date'
                                ? 'date'
                                : (
                                    $type === 'url'
                                        ? 'url'
                                        : 'text'
                                )
                        );
                ?>


                <?php if (
                    !empty(
                        $field['name_suggestion']
                    )
                    && $placeReportShowNameSuggestion
                ): ?>

                    <div class="add-place-name-control">

                <?php endif; ?>


                <input
                    type="<?= $e(
                        $htmlType
                    ) ?>"
                    name="<?= $e(
                        $key
                    ) ?>"
                    value="<?= $e(
                        $current
                    ) ?>"
                    <?= !empty(
                        $field['required']
                    )
                        ? 'required'
                        : '' ?>
                    <?= !empty(
                        $field['maxlength']
                    )
                        ? 'maxlength="'
                            . (int) $field['maxlength']
                            . '"'
                        : '' ?>
                    <?= isset(
                        $field['step']
                    )
                        ? 'step="'
                            . $e(
                                $field['step']
                            )
                            . '"'
                        : '' ?>
                    <?= isset(
                        $field['min']
                    )
                        ? 'min="'
                            . $e(
                                $field['min']
                            )
                            . '"'
                        : '' ?>
                    <?= isset(
                        $field['max']
                    )
                        ? 'max="'
                            . $e(
                                $field['max']
                            )
                            . '"'
                        : '' ?>
                    placeholder="<?= $e(
                        $field['placeholder']
                        ?? ''
                    ) ?>"
                    <?= !empty(
                        $field['location_field']
                    )
                        ? 'data-location-field="'
                            . $e(
                                $key
                            )
                            . '"'
                        : '' ?>
                    <?= $key === 'name'
                        ? 'data-place-name'
                        : '' ?>
                >


                <?php if (
                    !empty(
                        $field['name_suggestion']
                    )
                    && $placeReportShowNameSuggestion
                ): ?>

                        <button
                            class="add-place-name-refresh"
                            type="button"
                            data-refresh-place-name
                            aria-label="Suggest another Place name"
                            title="Suggest another name"
                        >
                            <?= llama_icon('refresh') ?>

                            <span>
                                Another name
                            </span>
                        </button>

                    </div>


                    <small>
                        We suggest simple location-neutral names so the title
                        does not accidentally reveal a road, landmark, or exact
                        location. You can still edit the suggestion.
                    </small>

                <?php endif; ?>


            <?php endif; ?>


        </<?= $fieldContainerTag ?>>

        <?php
    };


$renderCompoundField =
    static function (
        string $compoundKey,
        array $componentFields
    ) use (
        $e,
        $valueFor,
        $renderSelect
    ): void {
        $definitions =
            llama_place_report_approved_compound_questions();

        $definition =
            $definitions[$compoundKey]
            ?? null;

        if (!is_array($definition)) {
            return;
        }

        $label =
            (string) (
                $definition['label']
                ?? $compoundKey
            );
        ?>
        <div
            class="contribution-field contribution-field-compound"
            data-report-compound-question="<?= $e($compoundKey) ?>"
        >
            <span class="place-report-field-label">
                <span><?= $e($label) ?></span>
            </span>

            <div class="place-report-compound-controls">
                <?php foreach ((array) ($definition['components'] ?? []) as $componentKey => $component): ?>
                    <?php
                    if (!isset($componentFields[$componentKey])) {
                        continue;
                    }

                    $field =
                        $componentFields[$componentKey];

                    $current =
                        (string) $valueFor(
                            (string) $componentKey,
                            $field
                        );

                    $componentLabel =
                        (string) (
                            $component['label']
                            ?? $field['label']
                            ?? $componentKey
                        );
                    ?>
                    <label
                        class="place-report-compound-control"
                        data-report-field="<?= $e((string) $componentKey) ?>"
                    >
                        <span><?= $e($componentLabel) ?></span>
                        <?php $renderSelect($field, $current); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    };


?>

<script
    type="application/json"
    data-place-report-schema-config
><?= json_encode(
    $placeReportBrowserConfig,
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<?php
/*
 * =========================================================
 * PLACE REPORT SECTIONS
 * =========================================================
 */


/* Exactly three top-level sections; nested groups reuse canonical field renderers. */
$reportLayoutGroups = llama_place_report_layout_groups();

$reportLayoutGroups['legacy_review'] = [
    'review' => 'Questions for Review',
];

$reportMainLabels = [
    'getting_there' => ['Getting There', 'road'],
    'place' => ['Place', 'info-circle'],
    'site' => ['Site', 'camper'],
    'legacy_review' => ['Legacy Review', 'info-circle'],
];

$placeReportVisibleFields = [];

foreach ($placeReportFields as $fieldKey => $field) {
    if (isset($placeReportExternalFieldLookup[$fieldKey])) {
        continue;
    }

    $legacySection = (string) (
        $field['display_section']
        ?? $field['section']
        ?? ''
    );

    if (
        $legacySection === 'location'
        && $placeReportShowLocate
        && in_array(
            (string) $fieldKey,
            ['latitude', 'longitude'],
            true
        )
    ) {
        continue;
    }

    $section = (string) ($field['layout_section'] ?? '');
    $group = (string) ($field['layout_group'] ?? '');

    if (
        !isset($reportLayoutGroups[$section])
        || !array_key_exists(
            $group,
            $reportLayoutGroups[$section]
        )
    ) {
        $field['layout_section'] = 'legacy_review';
        $field['layout_group'] = 'review';
    }

    $placeReportVisibleFields[$fieldKey] = $field;
}


foreach ($reportMainLabels as $reportMainKey => [$reportMainLabel, $reportMainIcon]):
    $reportMainFields = array_filter(
        $placeReportVisibleFields,
        static fn (array $field): bool =>
            (string) ($field['layout_section'] ?? '') === $reportMainKey
    );
    if (!$reportMainFields) {
        continue;
    }
?>
<details class="contribution-section" <?= $reportMainKey === 'getting_there' ? 'open' : '' ?>
    data-report-main-section="<?= $e($reportMainKey) ?>">
    <summary>
        <span><?= llama_icon($reportMainIcon) ?> <?= $e($reportMainLabel) ?></span>
        <span class="place-report-section-progress" data-section-completion-percent aria-label="Section completion"></span>
    </summary>
    <div class="contribution-section-body">
        <?php
        /*
         * Render one collapsible section per approved questionnaire group.
         * Keep the canonical Place/Site ownership and field renderer intact.
         */
        $approvedSections = llama_place_report_group_eligible_questions(
            $reportMainFields
        );
        ?>
        <?php foreach ($approvedSections as $approvedKey => $approvedSection): ?>
            <?php
            $hasLocate = $reportMainKey === 'place'
                && $approvedKey === 'location'
                && $placeReportShowLocate;
            if (!$approvedSection['fields'] && !$hasLocate) {
                continue;
            }
            ?>
            <details class="contribution-section"
                data-report-subsection="<?= $e($approvedKey) ?>"
                <?= $hasLocate ? 'open' : '' ?>>
                <summary>
                    <span><?= $e($approvedSection['label']) ?></span>
                    <span class="place-report-section-progress" data-section-completion-percent
                        aria-label="Subsection completion"></span>
                </summary>
                <div class="contribution-section-body">
                    <?php if ($hasLocate): ?>
            <?php if (
                true
                && $placeReportShowLocate
            ): ?>

                <div class="add-place-coordinate-field">

                    <label for="add-place-coordinates">
                        Coordinates
                    </label>

                    <div class="add-place-coordinate-row">

                        <input
                            id="add-place-coordinates"
                            type="text"
                            name="coordinates"
                            value="<?= $e($combinedCoordinates) ?>"
                            placeholder="37.2522200, -107.2192000"
                            inputmode="decimal"
                            autocomplete="off"
                            spellcheck="false"
                            data-coordinate-input
                        >

                        <button
                            class="add-place-coordinate-search"
                            type="button"
                            data-search-coordinates
                        >
                            Search
                        </button>

                    </div>

                    <small>
                        Paste latitude, longitude in decimal degrees. Use at
                        least 5 decimal places for each coordinate. Llama Scout
                        standardizes both values to 7 decimal places.
                    </small>

                    <input
                        type="hidden"
                        name="latitude"
                        value="<?= $e($combinedLatitude) ?>"
                        data-location-field="latitude"
                    >

                    <input
                        type="hidden"
                        name="longitude"
                        value="<?= $e($combinedLongitude) ?>"
                        data-location-field="longitude"
                    >

                </div>


                <div class="add-place-locate-panel">

                    <div>

                        <strong>
                            At the Place right now?
                        </strong>

                        <span>
                            Use your device location to fill coordinates,
                            elevation, road, city, county, and state.
                        </span>

                    </div>


                    <button
                        class="add-place-locate-button"
                        type="button"
                        data-locate-place
                    >

                        <?= llama_icon('current-location') ?>

                        Locate me

                    </button>

                </div>


                <div
                    class="add-place-location-status"
                    data-location-status
                    aria-live="polite"
                ></div>

            <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($approvedSection['fields']): ?>
                        <div class="contribution-grid">
                            <?php
                            $renderedCompoundQuestions = [];
                            ?>
                            <?php foreach ($approvedSection['fields'] as $fieldKey => $field): ?>
                                <?php
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

                                    foreach ($approvedSection['fields'] as $candidateKey => $candidateField) {
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

                                    $renderCompoundField(
                                        $compoundKey,
                                        $componentFields
                                    );

                                    continue;
                                }

                                $renderField($field);
                                ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </details>
        <?php endforeach; ?>
    </div>
</details>
<?php endforeach; ?>

<?php
/*
 * Photos belong to the selected reporting target, not to the Site Notes
 * question group. Keep one uploader for Place and Site records. Admin
 * target switching is handled by the existing photo controller.
 */
if ($placeReportShowPhotos):
?>


    <details
        class="contribution-section"
        open
    >

        <summary>

            <span>

                <?= llama_icon('camera') ?>

                Photos

            </span>

            <small>
                Signs, gates, roads, obstructions, the site, and important context
            </small>

        </summary>


        <div class="contribution-section-body">


            <?php if (
                $placeReportExistingPhotos
            ): ?>

                <div class="add-place-existing-photos">

                    <strong>
                        Photos already attached
                    </strong>

                    <p>
                        Keep useful evidence, remove anything that should be
                        replaced, and add new photos below if needed.
                    </p>


                    <div class="add-place-existing-photo-grid">

                        <?php foreach (
                            $placeReportExistingPhotos
                            as $photo
                        ): ?>

                            <?php
                            $src =
                                llama_place_report_photo_path(
                                    $photo
                                );

                            $photoUrl =
                                llama_place_report_photo_url(
                                    $photo
                                );
                            ?>


                            <?php if (
                                $src !== ''
                            ): ?>

                                <label
                                    class="add-place-existing-photo"
                                >

                                    <img
                                        src="<?= $e(
                                            $photoUrl
                                        ) ?>"
                                        alt="<?= $e(
                                            is_array($photo)
                                                ? (
                                                    $photo['alt']
                                                    ?? ''
                                                )
                                                : ''
                                        ) ?>"
                                        loading="lazy"
                                    >

                                    <span>

                                        <input
                                            type="checkbox"
                                            name="remove_existing_photos[]"
                                            value="<?= $e(
                                                $src
                                            ) ?>"
                                        >

                                        Remove this photo

                                    </span>

                                </label>

                            <?php endif; ?>


                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <div
                data-photo-uploader
                data-photo-context="<?= $e(
                    $placeReportPhotoContext
                ) ?>"
                data-photo-max="<?= $placeReportPhotoMax ?>"
                data-photo-csrf="<?= $e(
                    $placeReportPhotoCsrf
                ) ?>"
                data-photo-title="<?= $e(
                    $placeReportPhotoTitle
                ) ?>"
                data-photo-help="<?= $e(
                    $placeReportPhotoHelp
                ) ?>"
                <?= $placeReportPhotoEndpoint !== ''
                    ? 'data-photo-endpoint="'
                        . $e(
                            $placeReportPhotoEndpoint
                        )
                        . '"'
                    : '' ?>
            ></div>


        </div>

    </details>

<?php endif; ?>

<script defer src="/js/place-report-season-picker.js"></script>
<script defer src="/js/place-report-section-completion.js"></script>
<script defer src="/js/place-report-road-hazards.js"></script>

<script
    src="https://llamascout.com/js/place-report-form/recovery.js"
></script>


