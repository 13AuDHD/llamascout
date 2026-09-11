<?php

declare(strict_types=1);

$path = (string) ($placeUpdatePath ?? '');
$definition = is_array($placeUpdateDefinition ?? null)
    ? $placeUpdateDefinition
    : [];

$shared = is_array($definition['shared'] ?? null)
    ? $definition['shared']
    : null;

$current = $placeUpdateCurrent ?? null;
$renderValue = $placeUpdateValue ?? $current;
$checked = (bool) ($placeUpdateChecked ?? false);

$e = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = (string) ($definition['label'] ?? $path);
$icon = llama_place_update_schema_icon($path);
$currentDisplay = llama_place_update_display_value(
    $path,
    $current
);

$type = (string) (
    $shared['type']
    ?? $definition['type']
    ?? 'text'
);

$inputName = 'field_value[' . $path . ']';
$unknownValue = '__NULL__';
?>

<article
    class="place-update-field-card<?= $checked ? ' is-selected' : '' ?>"
    data-update-card="<?= $e($path) ?>"
>
    <label class="place-update-change-toggle">
        <input
            type="checkbox"
            name="change_fields[]"
            value="<?= $e($path) ?>"
            data-update-field-toggle="<?= $e($path) ?>"
            <?= $checked ? 'checked' : '' ?>
        >

        <span>Change</span>
    </label>

    <div class="place-update-field-main">
        <header class="place-update-field-heading">
            <div>
                <i
                    class="fa-solid <?= $e($icon) ?>"
                    aria-hidden="true"
                ></i>

                <strong><?= $e($label) ?></strong>
            </div>

            <small>
                Current:
                <b><?= $e($currentDisplay) ?></b>
            </small>
        </header>

        <div
            class="place-update-field-control"
            data-update-field-control="<?= $e($path) ?>"
        >

            <?php if ($type === 'tri'): ?>

                <div
                    class="add-place-radio-control"
                    data-radio-type="yes-no"
                >
                    <div class="add-place-radio-row">
                        <div class="add-place-radio-options">

                            <label class="add-place-radio-option is-unknown">
                                <input
                                    type="radio"
                                    name="<?= $e($inputName) ?>"
                                    value="<?= $e($unknownValue) ?>"
                                    <?= $renderValue === null ? 'checked' : '' ?>
                                >
                                <span>?</span>
                            </label>

                            <label class="add-place-radio-option">
                                <input
                                    type="radio"
                                    name="<?= $e($inputName) ?>"
                                    value="1"
                                    <?= $renderValue === true || (string) $renderValue === '1' ? 'checked' : '' ?>
                                >
                                <span>Yes</span>
                            </label>

                            <label class="add-place-radio-option">
                                <input
                                    type="radio"
                                    name="<?= $e($inputName) ?>"
                                    value="0"
                                    <?= $renderValue === false || (string) $renderValue === '0' ? 'checked' : '' ?>
                                >
                                <span>No</span>
                            </label>

                        </div>
                    </div>

                    <div class="add-place-radio-help is-simple">
                        ? = Unknown / could not confidently determine
                    </div>
                </div>

            <?php elseif ($type === 'rating'): ?>

                <div
                    class="add-place-radio-control"
                    data-radio-type="rating"
                >
                    <div class="add-place-radio-row">
                        <div class="add-place-radio-options">

                            <label class="add-place-radio-option is-unknown">
                                <input
                                    type="radio"
                                    name="<?= $e($inputName) ?>"
                                    value="<?= $e($unknownValue) ?>"
                                    <?= $renderValue === null ? 'checked' : '' ?>
                                >
                                <span>?</span>
                            </label>

                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="add-place-radio-option">
                                    <input
                                        type="radio"
                                        name="<?= $e($inputName) ?>"
                                        value="<?= $i ?>"
                                        <?= (string) $renderValue === (string) $i ? 'checked' : '' ?>
                                    >
                                    <span><?= $i ?></span>
                                </label>
                            <?php endfor; ?>

                        </div>
                    </div>

                    <div class="add-place-radio-help">
                        <span>
                            1 = <?= $e($shared['low'] ?? 'Low') ?>
                        </span>

                        <span>
                            5 = <?= $e($shared['high'] ?? 'High') ?>
                        </span>

                        <span>? = Unknown</span>
                    </div>
                </div>

            <?php elseif ($type === 'select'): ?>

                <select name="<?= $e($inputName) ?>">
                    <option value="">Select...</option>

                    <option
                        value="<?= $e($unknownValue) ?>"
                        <?= $renderValue === null ? 'selected' : '' ?>
                    >
                        Unknown / could not determine
                    </option>

                    <?php
                    $options = (array) ($shared['options'] ?? []);
                    $known = $renderValue === null
                        || array_key_exists((string) $renderValue, $options);
                    ?>

                    <?php if (!$known): ?>
                        <option
                            value="<?= $e($renderValue) ?>"
                            selected
                        >
                            <?= $e($renderValue) ?> (current value)
                        </option>
                    <?php endif; ?>

                    <?php foreach ($options as $value => $optionLabel): ?>
                        <option
                            value="<?= $e($value) ?>"
                            <?= (string) $renderValue === (string) $value ? 'selected' : '' ?>
                        >
                            <?= $e($optionLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($type === 'textarea'): ?>

                <textarea
                    name="<?= $e($inputName) ?>"
                    rows="<?= (int) ($shared['rows'] ?? 4) ?>"
                    placeholder="<?= $e($shared['placeholder'] ?? '') ?>"
                ><?= $e($renderValue ?? '') ?></textarea>

            <?php else: ?>

                <?php
                $htmlType =
                    $type === 'number'
                    || in_array(
                        (string) ($definition['type'] ?? ''),
                        ['int', 'float'],
                        true
                    )
                        ? 'number'
                        : (
                            $type === 'url'
                                ? 'url'
                                : 'text'
                        );

                $step = (string) (
                    $shared['step']
                    ?? (
                        ($definition['type'] ?? '') === 'float'
                            ? 'any'
                            : '1'
                    )
                );
                ?>

                <input
                    type="<?= $e($htmlType) ?>"
                    name="<?= $e($inputName) ?>"
                    value="<?= $e($renderValue ?? '') ?>"
                    <?= $htmlType === 'number'
                        ? 'step="' . $e($step) . '"'
                        : '' ?>
                    <?= !empty($shared['location_field'])
                        ? 'data-location-field="' . $e($shared['key']) . '"'
                        : '' ?>
                    placeholder="<?= $e($shared['placeholder'] ?? '') ?>"
                >

            <?php endif; ?>

        </div>
    </div>
</article>
