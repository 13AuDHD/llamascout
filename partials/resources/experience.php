<?php

declare(strict_types=1);

// Shared by Add Resource, Add Facility, Admin edits and moderation.
require_once dirname(__DIR__, 2) . '/app/resource-experience.php';
$experienceManifest = llama_resource_experience_manifest();
$experienceValues = is_array($form['experience'] ?? null)
    ? $form['experience']
    : (is_array($resource['experience'] ?? null) ? $resource['experience'] : []);
?>
<details class="contribution-section resource-experience-section">
    <summary><span>Experience</span></summary>
    <div class="contribution-section-body" data-resource-experience>
        <input type="hidden" name="experience_present" value="1">
        <p class="resource-experience-empty" data-resource-experience-empty
            <?= $enabledServices ? 'hidden' : '' ?>>
            Add a Service above to see its Experience questions.
        </p>
        <?php foreach ($experienceManifest as $serviceType => $groups): ?>
            <?php $serviceEnabled = isset($enabledServices[$serviceType]); ?>
            <fieldset class="resource-experience-service"
                data-resource-experience-service="<?= $e($serviceType) ?>"
                <?= $serviceEnabled ? '' : 'hidden disabled' ?>>
                <legend><?= $e(llama_resource_service_type_label($serviceType)) ?></legend>
                <?php foreach ($groups as $groupKey => $group): ?>
                    <section class="resource-experience-group">
                        <?php if ($serviceType === 'toilet'): ?>
                            <h3><?= $e($group['label']) ?></h3>
                        <?php endif; ?>
                        <div class="resource-experience-grid">
                            <?php foreach ($group['questions'] as $key => $question): ?>
                                <?php
                                $type = $question['type'];
                                $value = $experienceValues[$serviceType][$groupKey][$key] ?? null;
                                $name = 'experience[' . $serviceType . '][' . $groupKey . '][' . $key . ']';
                                ?>
                                <?php if ($type === 'multiselect'): ?>
                                    <fieldset class="resource-experience-question">
                                        <legend><?= $e($question['label']) ?></legend>
                                        <div class="resource-experience-choices">
                                            <?php foreach ($question['options'] as $option => $optionLabel): ?>
                                                <label>
                                                    <input type="checkbox" name="<?= $e($name) ?>[]"
                                                        value="<?= $e($option) ?>"
                                                        <?= is_array($value) && in_array((string) $option, $value, true) ? 'checked' : '' ?>>
                                                    <?= $e($optionLabel) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php else: ?>
                                    <label class="contribution-field resource-experience-question">
                                        <span><?= $e($question['label']) ?></span>
                                        <?php if (in_array($type, ['select', 'tri', 'rating'], true)): ?>
                                            <select name="<?= $e($name) ?>">
                                                <option value="">Unanswered</option>
                                                <?php
                                                $options = $type === 'tri'
                                                    ? ['yes' => 'Yes', 'no' => 'No', 'unknown' => 'Unknown']
                                                    : ($type === 'rating'
                                                        ? [1 => '1 · Poor', 2 => '2', 3 => '3', 4 => '4', 5 => '5 · Excellent']
                                                        : $question['options']);
                                                ?>
                                                <?php foreach ($options as $option => $optionLabel): ?>
                                                    <option value="<?= $e($option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>>
                                                        <?= $e($optionLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($type === 'textarea'): ?>
                                            <textarea name="<?= $e($name) ?>" rows="3" maxlength="1000"><?= $e($value ?? '') ?></textarea>
                                        <?php else: ?>
                                            <input type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                                name="<?= $e($name) ?>"
                                                value="<?= $e($value ?? '') ?>"
                                                <?= $type === 'number' ? 'min="0" step="' . $e(in_array($key, [
                                                    'facility_shower_cost', 'facility_wash_cost', 'facility_dry_cost',
                                                    'facility_dump_fee', 'service_air_cost',
                                                ], true) ? '0.01' : '1') . '"' : 'maxlength="255"' ?>>
                                        <?php endif; ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
    </div>
</details>
