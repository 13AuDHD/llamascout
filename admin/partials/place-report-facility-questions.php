<?php

declare(strict_types=1);

/**
 * Area-owned questions use the canonical registry and existing Admin save.
 * No second field schema or Area answer store is defined here.
 */
$areaQuestionFields = array_filter(
    admin_place_area_fields(),
    static fn (array $field): bool =>
        ($field['report_scope_group'] ?? '') === 'area'
        && (
            !empty($field['facility_kinds'])
            || !empty($field['area_feature_types'])
        )
);

if (!$areaQuestionFields || empty($adminPlaceAreas)) {
    return;
}

$esc = static fn (string $value): string =>
    htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$areaFacilityFields = array_filter(
    $areaQuestionFields,
    static fn (array $field): bool => !empty($field['facility_kinds'])
);
$areaCampingFields = array_filter(
    $areaQuestionFields,
    static fn (array $field): bool => !empty($field['area_feature_types'])
);
?>

<section class="contribution-area-panel admin-facility-questions"
         data-admin-facility-questions hidden>
    <?php foreach ([
        ['fields' => $areaCampingFields, 'heading' => 'Area Scout Report',
         'description' => 'Answers describe this camping or parking Area, not the entire Place.'],
        ['fields' => $areaFacilityFields, 'heading' => 'Facility Scout Report',
         'description' => 'Answers describe the selected facility, not the entire Place.'],
    ] as $group): ?>
        <?php if (empty($group['fields'])) continue; ?>
        <div data-admin-area-question-group hidden>
            <h3><?= $esc($group['heading']) ?></h3>
            <p><?= $esc($group['description']) ?></p>
            <div class="admin-facility-question-grid">
                <?php foreach ($group['fields'] as $field):
                    $key = (string) $field['key'];
                    $type = (string) ($field['type'] ?? 'text');
                    $options = (array) ($field['options'] ?? []);
                    if ($type === 'tri') {
                        $options = ['1' => 'Yes', '0' => 'No'];
                    } elseif ($type === 'rating') {
                        $options = [
                            '1' => '1 / 5', '2' => '2 / 5',
                            '3' => '3 / 5', '4' => '4 / 5',
                            '5' => '5 / 5',
                        ];
                    }
                    $inputId = 'admin-area-question-' . $key;
                    $isMultiple = $type === 'multiselect';
                ?>
                    <div class="contribution-field admin-facility-question"
                         data-report-field="<?= $esc($key) ?>"
                         data-area-question
                         <?php if (!empty($field['facility_kinds'])): ?>
                             data-facility-kinds="<?= $esc(implode(',', (array) $field['facility_kinds'])) ?>"
                         <?php endif; ?>
                         <?php if (!empty($field['area_feature_types'])): ?>
                             data-area-feature-types="<?= $esc(implode(',', (array) $field['area_feature_types'])) ?>"
                         <?php endif; ?>
                         hidden>
                        <label class="place-report-field-label admin-facility-question-heading"
                               for="<?= $esc($inputId) ?>">
                            <?= $esc((string) $field['label']) ?>
                        </label>
                        <?php if ($type === 'textarea'): ?>
                            <textarea id="<?= $esc($inputId) ?>"
                                      name="<?= $esc($key) ?>" rows="3"></textarea>
                        <?php elseif ($type === 'number'): ?>
                            <input id="<?= $esc($inputId) ?>"
                                   type="number" name="<?= $esc($key) ?>"
                                   min="<?= $esc((string) ($field['min'] ?? '0')) ?>"
                                   step="<?= $esc((string) ($field['step'] ?? 'any')) ?>">
                        <?php elseif ($type === 'text'): ?>
                            <input id="<?= $esc($inputId) ?>"
                                   type="text" name="<?= $esc($key) ?>">
                        <?php else: ?>
                            <select id="<?= $esc($inputId) ?>"
                                    name="<?= $esc($key) ?>"
                                    <?= $isMultiple ? 'multiple size="5"' : '' ?>>
                                <?php if (!$isMultiple): ?>
                                    <option value="">Unanswered</option>
                                    <?php if (!empty($field['allow_unknown'])): ?>
                                        <option value="<?= $esc(llama_place_report_unknown_token()) ?>">Unknown</option>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php foreach ($options as $value => $label): ?>
                                    <option value="<?= $esc((string) $value) ?>">
                                        <?= $esc((string) $label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if (!empty($field['help'])): ?>
                            <small><?= $esc((string) $field['help']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>
