<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-report.php';

$placeReportValues =
    is_array($placeReportValues ?? null)
        ? $placeReportValues
        : [];

$placeReportExistingPhotos =
    is_array($placeReportExistingPhotos ?? null)
        ? $placeReportExistingPhotos
        : [];

$placeReportMode =
    (string) ($placeReportMode ?? 'contributor');

$placeReportShowLocate =
    (bool) ($placeReportShowLocate ?? false);

$placeReportShowNameSuggestion =
    (bool) ($placeReportShowNameSuggestion ?? false);

$placeReportPhotoEndpoint =
    (string) ($placeReportPhotoEndpoint ?? '');

$placeReportPhotoCsrf =
    (string) ($placeReportPhotoCsrf ?? '');

$placeReportPhotoMax =
    max(1, (int) ($placeReportPhotoMax ?? 10));

$placeReportPhotoTitle =
    (string) ($placeReportPhotoTitle ?? 'Photos of this Place');

$placeReportPhotoHelp =
    (string) (
        $placeReportPhotoHelp
        ?? 'Add up to 10 current photos. Signs, gates, washouts, road conditions, parking areas, and obstructions are especially useful. Location metadata is removed before permanent storage.'
    );

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

$valueFor =
    static function (
        string $key,
        array $field
    ) use ($placeReportValues): string {
        if (array_key_exists($key, $placeReportValues)) {
            $value = $placeReportValues[$key];

            return is_scalar($value)
                ? (string) $value
                : '';
        }

        return isset($field['default'])
            ? (string) $field['default']
            : '';
    };

$renderSelect =
    static function (
        array $field,
        string $current
    ) use ($e): void {
        $unknown =
            llama_place_report_unknown_token();

        $options =
            (array) ($field['options'] ?? []);
        ?>
        <select
            name="<?= $e($field['key']) ?>"
            <?= !empty($field['location_field'])
                ? 'data-location-field="' . $e($field['key']) . '"'
                : '' ?>
        >
            <option
                value=""
                <?= $current === '' ? 'selected' : '' ?>
            >
                Select...
            </option>

            <?php if (!empty($field['allow_unknown'])): ?>
                <option
                    value="<?= $e($unknown) ?>"
                    <?= $current === $unknown ? 'selected' : '' ?>
                >
                    Unknown / could not determine
                </option>
            <?php endif; ?>

            <?php
            $knownCurrent =
                $current === ''
                || $current === $unknown
                || array_key_exists($current, $options);
            ?>

            <?php if (!$knownCurrent): ?>
                <option
                    value="<?= $e($current) ?>"
                    selected
                >
                    <?= $e($current) ?> (previous entry)
                </option>
            <?php endif; ?>

            <?php foreach ($options as $value => $label): ?>
                <option
                    value="<?= $e($value) ?>"
                    <?= $current === (string) $value ? 'selected' : '' ?>
                >
                    <?= $e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    };

$renderField =
    static function (
        array $field
    ) use (
        $e,
        $valueFor,
        $renderSelect,
        $placeReportMode,
        $placeReportShowNameSuggestion
    ): void {
        $key = (string) $field['key'];
        $type = (string) $field['type'];
        $current = $valueFor($key, $field);
        $wide = !empty($field['wide']);
        $unknown = llama_place_report_unknown_token();
        $unanswered = llama_place_report_unanswered_token();

        if ($type === 'checkbox') {
            $checked = $current === '1';
            ?>
            <label class="contribution-check">
                <?php if ($placeReportMode === 'moderator'): ?>
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
                    <?= $checked ? 'checked' : '' ?>
                    <?= $key === 'amenity_none'
                        ? 'data-place-report-no-amenities'
                        : 'data-place-report-amenity' ?>
                >

                <span><?= $e($field['label']) ?></span>
            </label>
            <?php
            return;
        }

        $class =
            'contribution-field'
            . ($wide ? ' contribution-field-wide' : '');
        ?>
        <label class="<?= $e($class) ?>">
            <span><?= $e($field['label']) ?></span>

            <?php if (in_array($type, ['tri', 'rating'], true)): ?>
                <input
                    type="hidden"
                    name="<?= $e($key) ?>"
                    value="<?= $e($unanswered) ?>"
                    data-place-report-hidden="<?= $e($key) ?>"
                >

                <div
                    class="add-place-radio-control"
                    data-radio-type="<?= $type === 'tri' ? 'yes-no' : 'rating' ?>"
                >
                    <div class="add-place-radio-row">
                        <div class="add-place-radio-options">

                            <label class="add-place-radio-option is-unknown">
                                <input
                                    type="radio"
                                    name="<?= $e($key) ?>"
                                    value="<?= $e($unknown) ?>"
                                    <?= $current === $unknown ? 'checked' : '' ?>
                                >
                                <span>?</span>
                            </label>

                            <?php if ($type === 'tri'): ?>
                                <label class="add-place-radio-option">
                                    <input
                                        type="radio"
                                        name="<?= $e($key) ?>"
                                        value="1"
                                        <?= $current === '1' ? 'checked' : '' ?>
                                    >
                                    <span>Yes</span>
                                </label>

                                <label class="add-place-radio-option">
                                    <input
                                        type="radio"
                                        name="<?= $e($key) ?>"
                                        value="0"
                                        <?= $current === '0' ? 'checked' : '' ?>
                                    >
                                    <span>No</span>
                                </label>
                            <?php else: ?>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="add-place-radio-option">
                                        <input
                                            type="radio"
                                            name="<?= $e($key) ?>"
                                            value="<?= $i ?>"
                                            <?= $current === (string) $i ? 'checked' : '' ?>
                                        >
                                        <span><?= $i ?></span>
                                    </label>
                                <?php endfor; ?>
                            <?php endif; ?>

                        </div>

                        <button
                            class="add-place-radio-clear"
                            type="button"
                            data-place-report-clear="<?= $e($key) ?>"
                        >
                            Clear
                        </button>
                    </div>

                    <div
                        class="add-place-radio-help<?= $type === 'tri' ? ' is-simple' : '' ?>"
                    >
                        <?php if ($type === 'tri'): ?>
                            ? = Unknown / could not confidently determine
                        <?php else: ?>
                            <span>1 = <?= $e($field['low'] ?? 'Low') ?></span>
                            <span>5 = <?= $e($field['high'] ?? 'High') ?></span>
                            <span>? = Unknown</span>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($type === 'select'): ?>
                <?php $renderSelect($field, $current); ?>

            <?php elseif ($type === 'textarea'): ?>
                <textarea
                    name="<?= $e($key) ?>"
                    rows="<?= (int) ($field['rows'] ?? 4) ?>"
                    placeholder="<?= $e($field['placeholder'] ?? '') ?>"
                ><?= $e($current) ?></textarea>

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
                    !empty($field['name_suggestion'])
                    && $placeReportShowNameSuggestion
                ): ?>
                    <div class="add-place-name-control">
                <?php endif; ?>

                <input
                    type="<?= $e($htmlType) ?>"
                    name="<?= $e($key) ?>"
                    value="<?= $e($current) ?>"
                    <?= !empty($field['required']) ? 'required' : '' ?>
                    <?= !empty($field['maxlength'])
                        ? 'maxlength="' . (int) $field['maxlength'] . '"'
                        : '' ?>
                    <?= isset($field['step'])
                        ? 'step="' . $e($field['step']) . '"'
                        : '' ?>
                    <?= isset($field['min'])
                        ? 'min="' . $e($field['min']) . '"'
                        : '' ?>
                    <?= isset($field['max'])
                        ? 'max="' . $e($field['max']) . '"'
                        : '' ?>
                    placeholder="<?= $e($field['placeholder'] ?? '') ?>"
                    <?= !empty($field['location_field'])
                        ? 'data-location-field="' . $e($key) . '"'
                        : '' ?>
                    <?= $key === 'name' ? 'data-place-name' : '' ?>
                >

                <?php if (
                    !empty($field['name_suggestion'])
                    && $placeReportShowNameSuggestion
                ): ?>
                        <button
                            class="add-place-name-refresh"
                            type="button"
                            data-refresh-place-name
                            aria-label="Suggest another Place name"
                            title="Suggest another name"
                        >
                            <i
                                class="fa-solid fa-arrows-rotate"
                                aria-hidden="true"
                            ></i>
                            <span>Another name</span>
                        </button>
                    </div>

                    <small>
                        We suggest simple location-neutral names so the title
                        does not accidentally reveal a road, landmark, or exact
                        location. You can still edit the suggestion.
                    </small>
                <?php endif; ?>
            <?php endif; ?>
        </label>
        <?php
    };

foreach ($placeReportSections as $sectionKey => $section):
    $sectionFields =
        array_filter(
            $placeReportFields,
            static fn (array $field): bool =>
                (string) $field['section'] === $sectionKey
        );

    if (!$sectionFields) {
        continue;
    }
    ?>
    <details
        class="contribution-section"
        <?= !empty($section['open']) ? 'open' : '' ?>
    >
        <summary>
            <span>
                <i
                    class="fa-solid <?= $e($section['icon']) ?>"
                    aria-hidden="true"
                ></i>
                <?= $e($section['label']) ?>
            </span>

            <small><?= $e($section['description']) ?></small>
        </summary>

        <div class="contribution-section-body">

            <?php if (
                $sectionKey === 'location'
                && $placeReportShowLocate
            ): ?>
                <div class="add-place-locate-panel">
                    <div>
                        <strong>At the Place right now?</strong>
                        <span>
                            Use your device location to fill GPS coordinates,
                            elevation, road, city, county, and state.
                        </span>
                    </div>

                    <button
                        class="add-place-locate-button"
                        type="button"
                        data-locate-place
                    >
                        <i
                            class="fa-solid fa-crosshairs"
                            aria-hidden="true"
                        ></i>
                        Locate me
                    </button>
                </div>

                <div
                    class="add-place-location-status"
                    data-location-status
                    aria-live="polite"
                ></div>
            <?php endif; ?>

            <?php if ($sectionKey === 'amenities'): ?>
                <p class="contribution-section-help">
                    Unchecked means the amenity was not present when observed.
                    Choose No amenities only when none of the listed amenities
                    are present.
                </p>

                <div class="contribution-checkbox-grid">
                    <?php foreach ($sectionFields as $field): ?>
                        <?php $renderField($field); ?>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($sectionKey === 'sensory'): ?>
                <?php foreach (
                    [
                        'Daytime',
                        'Nighttime',
                        'Specific sensory conditions',
                    ]
                    as $subsection
                ): ?>
                    <h3 class="contribution-subheading">
                        <?= $e($subsection) ?>
                    </h3>

                    <div class="contribution-grid">
                        <?php foreach ($sectionFields as $field): ?>
                            <?php if (
                                ($field['subsection'] ?? '')
                                === $subsection
                            ): ?>
                                <?php $renderField($field); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="contribution-grid">
                    <?php foreach ($sectionFields as $field): ?>
                        <?php $renderField($field); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </details>
<?php endforeach; ?>


<details class="contribution-section" open>
    <summary>
        <span>
            <i
                class="fa-solid fa-camera"
                aria-hidden="true"
            ></i>
            Photos
        </span>

        <small>
            Signs, gates, roads, obstructions, the site, and important context
        </small>
    </summary>

    <div class="contribution-section-body">

        <?php if ($placeReportExistingPhotos): ?>
            <div class="add-place-existing-photos">
                <strong>Photos already attached</strong>

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
                        ?>

                        <?php if ($src !== ''): ?>
                            <label class="add-place-existing-photo">
                                <img
                                    src="<?= $e($src) ?>"
                                    alt="<?= $e(
                                        is_array($photo)
                                            ? ($photo['alt'] ?? '')
                                            : ''
                                    ) ?>"
                                >

                                <span>
                                    <input
                                        type="checkbox"
                                        name="remove_existing_photos[]"
                                        value="<?= $e($src) ?>"
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
            data-photo-context="add-place"
            data-photo-max="<?= $placeReportPhotoMax ?>"
            data-photo-csrf="<?= $e($placeReportPhotoCsrf) ?>"
            data-photo-title="<?= $e($placeReportPhotoTitle) ?>"
            data-photo-help="<?= $e($placeReportPhotoHelp) ?>"
            <?= $placeReportPhotoEndpoint !== ''
                ? 'data-photo-endpoint="'
                    . $e($placeReportPhotoEndpoint)
                    . '"'
                : '' ?>
        ></div>

    </div>
</details>
