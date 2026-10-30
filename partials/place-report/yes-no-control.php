<?php

declare(strict_types=1);

/* Shared answer control for Place and Site amenities, including legacy editors.
 * $yesNoField supplies key, label, and optional select options; $yesNoValue is
 * a canonical form value. Clear is unanswered, distinct from explicit Unknown.
 */
$yesNoKey = (string) $yesNoField['key'];
$yesNoLabel = (string) $yesNoField['label'];
$yesNoCurrent = is_bool($yesNoValue)
    ? ($yesNoValue ? '1' : '0')
    : (string) ($yesNoValue ?? '');
$yesNoEscape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$yesNoOptions = $yesNoField['options'] ?? ['1' => 'Yes', '0' => 'No'];
$yesNoLegacyValues =
    (array) (
        $yesNoField['legacy_values']
        ?? []
    );

if (
    $yesNoCurrent !== ''
    && !array_key_exists(
        $yesNoCurrent,
        $yesNoOptions
    )
    && array_key_exists(
        $yesNoCurrent,
        $yesNoLegacyValues
    )
) {
    $yesNoOptions[$yesNoCurrent] =
        (string) $yesNoLegacyValues[$yesNoCurrent];
}
?>
<input type="hidden" name="<?= $yesNoEscape($yesNoKey) ?>"
    value="<?= $yesNoEscape(llama_place_report_unanswered_token()) ?>"
    data-place-report-hidden="<?= $yesNoEscape($yesNoKey) ?>">
<div class="add-place-radio-control<?= count($yesNoOptions) > 2 ? ' has-extra-options' : '' ?>" data-radio-type="yes-no"
    role="group" aria-label="<?= $yesNoEscape($yesNoLabel) ?>">
    <div class="add-place-radio-row">
        <div class="add-place-radio-options">
            <label class="add-place-radio-option is-unknown">
                <input type="radio" name="<?= $yesNoEscape($yesNoKey) ?>"
                    value="<?= $yesNoEscape(llama_place_report_unknown_token()) ?>"
                    aria-label="Unknown"
                    <?= $yesNoCurrent === llama_place_report_unknown_token() ? 'checked' : '' ?>>
                <span>?</span>
            </label>
            <?php foreach ($yesNoOptions as $yesNoOption => $yesNoOptionLabel): ?>
                <label class="add-place-radio-option">
                    <input type="radio" name="<?= $yesNoEscape($yesNoKey) ?>"
                        value="<?= $yesNoEscape($yesNoOption) ?>"
                        <?= $yesNoCurrent === (string) $yesNoOption ? 'checked' : '' ?>>
                    <span><?= $yesNoEscape($yesNoOptionLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="add-place-radio-clear" type="button"
            data-place-report-clear="<?= $yesNoEscape($yesNoKey) ?>"
            aria-label="Clear <?= $yesNoEscape($yesNoLabel) ?>">Clear</button>
    </div>
    <div class="add-place-radio-help is-simple">
        ? = Unknown · Clear = Unanswered
    </div>
</div>
