<?php
declare(strict_types=1);

/** Canonical Area-only facility questions; no independent question schema. */
$facilityFields = array_filter(
    admin_place_area_fields(),
    static fn (array $field): bool =>
        ($field['report_scope_group'] ?? '') === 'area'
        && !empty($field['facility_kinds'])
);
if (!$facilityFields || empty($adminPlaceAreas)) return;
$esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<section class="contribution-area-panel admin-facility-questions"
         data-admin-facility-questions hidden>
    <h3>Facility Scout Report</h3>
    <p>Answers describe the selected facility, not the whole Place.</p>
    <div class="admin-facility-question-grid">
    <?php foreach ($facilityFields as $field):
        $key = (string) $field['key'];
        $type = (string) $field['type'];
        $options = (array) ($field['options'] ?? []);
        if ($type === 'tri') $options = ['1' => 'Yes', '0' => 'No'];
        if ($type === 'rating') $options = [
            '1' => '1 / 5', '2' => '2 / 5', '3' => '3 / 5',
            '4' => '4 / 5', '5' => '5 / 5',
        ];
        ?>
        <label class="contribution-field admin-facility-question"
               data-report-field="<?= $esc($key) ?>"
               data-facility-kinds="<?= $esc(implode(',', (array) $field['facility_kinds'])) ?>" hidden>
            <span class="place-report-field-label admin-facility-question-heading">
                <?= $esc((string) $field['label']) ?>
                <?php if (!empty($field['help'])): ?>
                    <details class="admin-facility-question-help">
                        <summary aria-label="Help for <?= $esc((string) $field['label']) ?>">
                            <?= llama_icon('info') ?>
                        </summary>
                        <span class="admin-facility-question-help-popup"><?= $esc((string) $field['help']) ?></span>
                    </details>
                <?php endif; ?>
            </span>
            <select name="<?= $esc($key) ?>">
                <option value="">Unanswered</option>
                <option value="<?= $esc(llama_place_report_unknown_token()) ?>">Unknown</option>
                <?php foreach ($options as $value => $label): ?>
                    <option value="<?= $esc((string) $value) ?>"><?= $esc((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endforeach; ?>
    </div>
</section>
