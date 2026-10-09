<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

// Rule group layout uses a dedicated stylesheet, never inline CSS.



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
    is_array(
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

            // Legacy versions stored these fields as boolean tri-state.
            // Preserve an explicit old No (false) rather than treating it
            // as a blank when rendering the new three-option selects.
            if (
                in_array(
                    $key,
                    ['high_clearance_recommended', 'four_wheel_drive_recommended'],
                    true
                )
                && is_bool($value)
            ) {
                return $value ? '1' : '0';
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

                <option
                    value="<?= $e(
                        $current
                    ) ?>"
                    selected
                >
                    <?= $e(
                        $current
                    ) ?>
                    (previous entry)
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

            <label class="contribution-check">

                <?php if (
                    $placeReportMode === 'moderator'
                ): ?>

                    <input
                        type="hidden"
                        name="<?= $e($key) ?>"
                        value="0"
                    >

                <?php endif; ?>


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

        $fieldContainerTag =
            $type === 'multiselect'
                ? 'div'
                : 'label';
        ?>

        <<?= $fieldContainerTag ?> class="<?= $e(
            $class
        ) ?>">

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


            <?php if (
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

                $multiSummary =
                    (string) (
                        $field['summary']
                        ?? 'Choose options'
                    );
                ?>

                <details
                    class="place-report-multiselect"
                    data-place-report-multiselect
                >
                    <summary
                        data-place-report-multiselect-summary
                        data-default-summary="<?= $e($multiSummary) ?>"
                    >
                        <?= $selectedValues
                            ? number_format(count($selectedValues)) . ' selected'
                            : $e($multiSummary) ?>
                    </summary>

                    <div class="place-report-multiselect-panel">
                        <input
                            type="search"
                            class="place-report-multiselect-search"
                            placeholder="<?= $e(
                                $field['search_placeholder']
                                ?? 'Search options...'
                            ) ?>"
                            autocomplete="off"
                            data-place-report-multiselect-search
                        >

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
                </details>


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

foreach (
    $placeReportSections
    as $sectionKey => $section
):

    $sectionFields =
        array_filter(
            $placeReportFields,
            static fn (array $field): bool =>
                (string) (
                    $field['display_section']
                    ?? $field['section']
                    ?? ''
                ) === $sectionKey
        );

    $combinedCoordinates = '';
    $combinedLatitude = '';
    $combinedLongitude = '';

    if (
        $sectionKey === 'location'
        && $placeReportShowLocate
    ) {
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
            trim(
                (string) (
                    $placeReportValues['coordinates']
                    ?? ''
                )
            );

        if (
            $combinedCoordinates === ''
            && is_numeric($combinedLatitude)
            && is_numeric($combinedLongitude)
        ) {
            $combinedCoordinates =
                number_format(
                    (float) $combinedLatitude,
                    7,
                    '.',
                    ''
                )
                . ', '
                . number_format(
                    (float) $combinedLongitude,
                    7,
                    '.',
                    ''
                );
        }

        $sectionFields =
            array_filter(
                $sectionFields,
                static fn (array $field): bool =>
                    !in_array(
                        (string) ($field['key'] ?? ''),
                        ['latitude', 'longitude'],
                        true
                    )
            );
    }

    /*
     * Keep the established section UI and all existing field names, while
     * displaying related questions together. These lists affect presentation
     * only: no values, validation, applicability or points are changed.
     * Unlisted and future questions retain their original registry order.
     */
    $placeReportFlowOrder = [
        'rules' => [
            'overnight_camping_allowed', 'dispersed_camping_allowed',
            'car_truck_camping', 'designated_sites_only', 'existing_sites_encouraged',
            'stay_limit_days', 'residential_use_prohibited',
            'camping_conditional_details',
            'fee', 'reservation_required', 'reservation_fee',
            'reservation_url', 'membership_required', 'membership_fee',
            'membership_url', 'check_in_required', 'check_in_begins',
            'check_out_required', 'checkout_ends',
            'entrance_facility_fee', 'parking_fee',
            'season_begins', 'season_ends', 'seasonal_closure',
            'seasonal_access_note', 'best_months', 'winter_access',
            'snow_risk', 'mud_season_risk', 'monsoon_risk',
            'hurricane_risk', 'heat_season_risk',
            'generator_restrictions', 'generator_prohibited',
            'generator_quiet_hours', 'generator_quiet_hours_begin',
            'generator_quiet_hours_end', 'generator_run_restrictions',
            'generator_max_run_hours', 'generator_free_area',
            'generator_restriction_details',
            'campfire_allowed', 'collecting_firewood',
            'current_fire_restrictions_url',
            'fire_activities_conditional_details',
            'pets_allowed', 'dogs_required_to_be_leashed',
            'food_storage_required', 'pack_it_in_pack_it_out',
            'drone_use_legal', 'target_shooting_allowed',
            'nearest_town', 'nearest_fuel', 'nearest_ev_charging',
            'nearest_alcohol_sales', 'nearest_propane',
            'nearest_grocery', 'nearest_water', 'nearest_toilet',
            'nearest_hospital',
        ],
        'site_vehicle' => [
            'site_number', 'site_accessible', 'max_people',
            'vehicle_capacity', 'capacity_size_rating',
            'site_length_feet', 'site_width_feet',
            'parking_surface', 'ground_condition', 'parking_grade',
            'parking_length_feet', 'overhead_clearance_feet',
            'max_vehicle_length_feet', 'double_driveway',
            'turnaround_space', 'pull_through', 'back_in',
            'trailer_suitable', 'max_trailer_length_feet',
            'rv_suitable', 'max_rv_length_feet',
            'tent_camping_suitable', 'tent_pad',
            'tent_pad_length_feet', 'tent_pad_width_feet',
            'hike_in_distance_feet', 'levelness',
            'site_open_sky', 'tree_cover', 'site_shade',
            'site_hookups_available', 'hookup_electric',
            'hookup_electric_service', 'hookup_water', 'hookup_sewer',
        ],
        'road_access' => [
            'road_surface', 'road_width', 'sedan_accessible',
            'high_clearance_recommended', 'four_wheel_drive_recommended',
            'water_crossings', 'downed_tree_risk',
            'road_overall_difficulty', 'road_stress',
            'rocks', 'washboards', 'potholes', 'mud_risk',
            'steep_grades', 'drop_off_exposure',
            'site_access_difficulty', 'access_summary',
        ],
    ];
    if (isset($placeReportFlowOrder[$sectionKey])) {
        $positions = array_flip($placeReportFlowOrder[$sectionKey]);
        $originalOrder = array_flip(array_keys($sectionFields));
        uksort(
            $sectionFields,
            static function (string $a, string $b) use ($positions, $originalOrder): int {
                $left = $positions[$a] ?? (1000 + $originalOrder[$a]);
                $right = $positions[$b] ?? (1000 + $originalOrder[$b]);
                return $left <=> $right;
            }
        );
    }

    if (!$sectionFields) {
        continue;
    }
    ?>

    <details
        class="contribution-section"
        <?= !empty(
            $section['open']
        )
            ? 'open'
            : '' ?>
    >

        <summary>

            <span>

                <?= llama_icon(
                    (string) (
                        $section['icon']
                        ?? 'info-circle'
                    )
                ) ?>

                <?= $e(
                    $section['label']
                    ?? 'Place information'
                ) ?>

            </span>

            <small>
                <?= $e(
                    $section['description']
                    ?? ''
                ) ?>
            </small>

        </summary>


        <div class="contribution-section-body">


            <?php if (
                $sectionKey === 'location'
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


            <?php if (
                $sectionKey === 'amenities'
            ): ?>


                <p class="contribution-section-help">
                    Unchecked means the amenity was not present when observed.
                    Choose No amenities only when none of the listed amenities
                    are present.
                </p>


                <div class="contribution-checkbox-grid">

                    <?php foreach (
                        $sectionFields
                        as $field
                    ): ?>

                        <?php
                        $renderField(
                            $field
                        );
                        ?>

                    <?php endforeach; ?>

                </div>


            <?php elseif (
                $sectionKey === 'sensory'
            ): ?>


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
                            $sectionFields,
                            static fn (array $field): bool =>
                                (string) (
                                    $field['subsection']
                                    ?? ''
                                ) === $subsection
                        );

                    if (!$subsectionFields) {
                        continue;
                    }
                    ?>


                    <h3 class="contribution-subheading">
                        <?= $e(
                            $subsection
                        ) ?>
                    </h3>


                    <div class="contribution-grid">

                        <?php foreach (
                            $subsectionFields
                            as $field
                        ): ?>

                            <?php
                            $renderField(
                                $field
                            );
                            ?>

                        <?php endforeach; ?>

                    </div>


                <?php endforeach; ?>


                <?php
                $sensorySummaryFields =
                    array_filter(
                        $sectionFields,
                        static fn (array $field): bool =>
                            trim(
                                (string) (
                                    $field['subsection']
                                    ?? ''
                                )
                            ) === ''
                    );
                ?>

                <?php if ($sensorySummaryFields): ?>
                    <div class="contribution-grid">
                        <?php foreach (
                            $sensorySummaryFields
                            as $field
                        ): ?>
                            <?php $renderField($field); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>


            <?php elseif ($sectionKey === 'rules'): ?>

                <link rel="stylesheet" href="/css/site/features/place-report-flow-groups.css">

                <?php
                $ruleGroups = [
                    'Camping and stays' => [
                        'overnight_camping_allowed', 'dispersed_camping_allowed',
                        'car_truck_camping', 'designated_sites_only', 'existing_sites_encouraged',
                        'stay_limit_days', 'residential_use_prohibited',
            'camping_conditional_details',
                    ],
                    'Camping costs and reservations' => [
                        'fee', 'reservation_required', 'reservation_fee',
                        'reservation_url', 'membership_required',
                        'membership_fee', 'membership_url',
                        'check_in_required', 'check_in_begins',
                        'check_out_required', 'checkout_ends',
                        'entrance_facility_fee', 'parking_fee',
                    ],
                    'Seasons and access' => [
                        'season_begins', 'season_ends', 'seasonal_closure',
                        'seasonal_access_note', 'best_months', 'winter_access',
                        'snow_risk', 'mud_season_risk', 'monsoon_risk',
                        'hurricane_risk', 'heat_season_risk',
                    ],
                    'Generators' => [
                        'generator_restrictions', 'generator_prohibited',
                        'generator_quiet_hours', 'generator_quiet_hours_begin',
                        'generator_quiet_hours_end', 'generator_run_restrictions',
                        'generator_max_run_hours', 'generator_free_area',
                        'generator_restriction_details',
                    ],
                    'Fire and outdoor activities' => [
                        'campfire_allowed', 'collecting_firewood',
                        'current_fire_restrictions_url',
                        'drone_use_legal', 'target_shooting_allowed',
                        'fire_activities_conditional_details',
                    ],
                    'Pets and campsite rules' => [
                        'pets_allowed', 'dogs_required_to_be_leashed',
                        'food_storage_required', 'pack_it_in_pack_it_out',
                    ],
                    'Nearby services' => [
                        'nearest_town', 'nearest_fuel', 'nearest_ev_charging',
                        'nearest_alcohol_sales', 'nearest_propane',
                        'nearest_grocery', 'nearest_water', 'nearest_toilet',
                        'nearest_hospital',
                    ],
                ];
                $renderedRules = [];
                ?>

                <?php foreach ($ruleGroups as $groupTitle => $fieldKeys): ?>
                    <?php
                    $groupFields = [];
                    foreach ($fieldKeys as $fieldKey) {
                        if (isset($sectionFields[$fieldKey])) {
                            $groupFields[$fieldKey] = $sectionFields[$fieldKey];
                            $renderedRules[$fieldKey] = true;
                        }
                    }
                    if (!$groupFields) {
                        continue;
                    }
                    ?>
                    <section class="place-report-flow-group" data-place-report-flow-group>
                        <h3 class="place-report-flow-heading"><?= $e($groupTitle) ?></h3>
                        <div class="contribution-grid">
                            <?php foreach ($groupFields as $field): ?>
                                <?php $renderField($field); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

                <?php
                $unassignedRules = array_diff_key($sectionFields, $renderedRules);
                ?>
                <?php if ($unassignedRules): ?>
                    <section class="place-report-flow-group" data-place-report-flow-group>
                        <h3 class="place-report-flow-heading">Additional rules</h3>
                        <div class="contribution-grid">
                            <?php foreach ($unassignedRules as $field): ?>
                                <?php $renderField($field); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

            <?php else: ?>


                <div class="contribution-grid">

                    <?php foreach (
                        $sectionFields
                        as $field
                    ): ?>

                        <?php
                        $renderField(
                            $field
                        );
                        ?>

                    <?php endforeach; ?>

                </div>


            <?php endif; ?>


        </div>

    </details>

<?php endforeach; ?>


<?php
/*
 * =========================================================
 * SHARED PHOTO SECTION
 * =========================================================
 *
 * Add Place and moderation continue using this normally.
 * Suggest an Update turns it off and supplies its own
 * update-specific evidence section.
 */
?>

<?php if ($placeReportShowPhotos): ?>

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
