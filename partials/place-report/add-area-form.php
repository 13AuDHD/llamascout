<?php
declare(strict_types=1);

/**
 * Add Area contribution UI. Every question comes from the canonical catalog.
 * This form never creates map coordinates or changes the parent Place.
 */
$escArea = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$types = [
    'camping_area' => 'Camping area / loop',
    'parking_area' => 'Parking area',
    'facility_area' => 'Facility / amenity',
];
$facilityKinds = llama_place_facility_kind_options();
$selectedAreaType = (string) ($_POST['area_feature_type'] ?? 'facility_area');
$selectedFacilityKind = (string) ($_POST['area_facility_kind'] ?? 'restroom');
$selectedPlaceId = (int) ($_POST['area_place_id'] ?? $_GET['area_place_id'] ?? $_GET['place_id'] ?? 0);
$areaAnswers = is_array($_POST['area_answers'] ?? null) ? $_POST['area_answers'] : [];
$areaUnknown = is_array($_POST['area_unknown'] ?? null) ? $_POST['area_unknown'] : [];
$areaQuestions = llama_report_questions_for_scope('area', true);
?>
<section class="contribution-area-panel" data-contribution-area-panel hidden>
    <h3>Add an Area</h3>
    <p>Give this Area a name, select what it is and answer what you observed. A map point or boundary is optional and can be added later.</p>

    <form method="get" action="/add-place.php" class="area-place-search">
        <input type="hidden" name="tab" value="area">
        <label for="area-place-query">Find the parent Place</label>
        <div class="area-place-search-row">
            <input id="area-place-query" name="area_q" type="search"
                value="<?= $escArea($_GET['area_q'] ?? '') ?>"
                placeholder="Search campground or Place name">
            <button type="submit">Find Places</button>
        </div>
    </form>

    <form method="post" action="/add-place.php?tab=area" class="area-create-form"
          data-area-create-form>
        <input type="hidden" name="csrf_token" value="<?= $escArea(community_csrf_token()) ?>">
        <label class="contribution-field">
            <span>Parent Place</span>
            <select name="area_place_id" required>
                <option value="">Select the Place this Area belongs to</option>
                <?php foreach ($areaPlaceChoices as $placeChoice): ?>
                    <option value="<?= (int) $placeChoice['id'] ?>"
                        <?= (int) $placeChoice['id'] === $selectedPlaceId ? 'selected' : '' ?>>
                        <?= $escArea((string) $placeChoice['name']) ?>
                        <?= $escArea((string) ($placeChoice['state'] ?? '')) ?>
                        (#<?= (int) $placeChoice['id'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="area-create-identity">
            <label class="contribution-field">
                <span>Area name</span>
                <input type="text" name="area_name" maxlength="120" required
                    placeholder="e.g. West Vault Toilets"
                    value="<?= $escArea($_POST['area_name'] ?? '') ?>">
            </label>
            <label class="contribution-field">
                <span>Area type</span>
                <select name="area_feature_type" required data-area-type>
                    <?php foreach ($types as $typeCode => $typeLabel): ?>
                        <option value="<?= $escArea($typeCode) ?>"
                            <?= $selectedAreaType === $typeCode ? 'selected' : '' ?>>
                            <?= $escArea($typeLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="contribution-field" data-area-facility-selector>
                <span>Facility type</span>
                <select name="area_facility_kind" data-area-facility-kind>
                    <option value="">Select facility</option>
                    <?php foreach ($facilityKinds as $kindCode => $kindLabel): ?>
                        <option value="<?= $escArea($kindCode) ?>"
                            <?= $selectedFacilityKind === $kindCode ? 'selected' : '' ?>>
                            <?= $escArea($kindLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <p class="area-map-optional">Map location: Not mapped yet. You can add a purple marker or boundary using Edit Map after approval.</p>
        <h4 class="area-report-heading">Area Scout Report</h4>
        <p>Leave questions unanswered when you did not assess them. Choose ? only when you checked but could not determine an answer.</p>
        <?php
        $lastGroup = null;
        foreach ($areaQuestions as $key => $field):
            $group = (string) ($field['approved_question_group_label'] ?? $field['section'] ?? 'Area details');
            $kinds = array_values(array_map('strval', (array) ($field['facility_kinds'] ?? [])));
            $areaTypes = array_values(array_map('strval', (array) ($field['area_feature_types'] ?? [])));
            if ($lastGroup !== $group):
                if ($lastGroup !== null) echo '</div></details>';
                $lastGroup = $group;
                ?>
                <details class="contribution-section area-question-group" open>
                    <summary><?= $escArea($group) ?></summary>
                    <div class="area-question-grid">
            <?php endif; ?>
            <?php
                $type = (string) ($field['type'] ?? 'text');
                $value = $areaAnswers[$key] ?? null;
                $options = (array) ($field['options'] ?? []);
                $hasUnknown = !empty($field['allow_unknown']);
                $unknownSelected = !empty($areaUnknown[$key])
                    || $value === llama_place_report_unknown_token();
            ?>
            <fieldset class="area-question contribution-field"
                data-area-question
                data-area-types="<?= $escArea(implode(',', $areaTypes)) ?>"
                data-facility-kinds="<?= $escArea(implode(',', $kinds)) ?>">
                <legend><?= $escArea((string) $field['label']) ?></legend>
                <?php if (in_array($type, ['tri','checkbox','rating','permission'], true)): ?>
                    <div class="area-choice-row">
                    <?php if ($hasUnknown): ?>
                        <label><input type="radio" name="area_answers[<?= $escArea($key) ?>]"
                            value="<?= $escArea(llama_place_report_unknown_token()) ?>"
                            <?= $unknownSelected ? 'checked' : '' ?>>?</label>
                    <?php endif; ?>
                    <?php
                        $radioOptions = $type === 'rating'
                            ? ['1'=>'1','2'=>'2','3'=>'3','4'=>'4','5'=>'5']
                            : ($type === 'permission'
                                ? ['1'=>'Yes','0'=>'No','2'=>'Permit']
                                : ['1'=>'Yes','0'=>'No']);
                        foreach ($radioOptions as $optionValue => $optionLabel):
                    ?>
                        <label><input type="radio" name="area_answers[<?= $escArea($key) ?>]"
                            value="<?= $escArea($optionValue) ?>"
                            <?= (string) $value === (string) $optionValue ? 'checked' : '' ?>>
                            <?= $escArea($optionLabel) ?></label>
                    <?php endforeach; ?>
                    <button type="button" data-clear-area-answer>Clear</button>
                    </div>
                <?php elseif ($type === 'multiselect'): ?>
                    <div class="area-multi-options">
                    <?php if ($hasUnknown): ?>
                        <label><input type="checkbox" name="area_unknown[<?= $escArea($key) ?>]"
                            value="1" <?= $unknownSelected ? 'checked' : '' ?>>? Unknown</label>
                    <?php endif; ?>
                    <?php foreach ($options as $optionValue => $optionLabel): ?>
                        <label><input type="checkbox"
                            name="area_answers[<?= $escArea($key) ?>][]"
                            value="<?= $escArea($optionValue) ?>"
                            <?= is_array($value) && in_array((string) $optionValue, array_map('strval', $value), true) ? 'checked' : '' ?>>
                            <?= $escArea($optionLabel) ?></label>
                    <?php endforeach; ?>
                    </div>
                <?php elseif ($type === 'select'): ?>
                    <select name="area_answers[<?= $escArea($key) ?>]">
                        <option value="">Unanswered</option>
                        <?php if ($hasUnknown): ?>
                            <option value="<?= $escArea(llama_place_report_unknown_token()) ?>"
                                <?= $unknownSelected ? 'selected' : '' ?>>? Unknown</option>
                        <?php endif; ?>
                        <?php foreach ($options as $optionValue => $optionLabel): ?>
                            <option value="<?= $escArea($optionValue) ?>"
                                <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>>
                                <?= $escArea($optionLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'textarea'): ?>
                    <textarea name="area_answers[<?= $escArea($key) ?>]" rows="3"><?= $escArea($value ?? '') ?></textarea>
                    <?php if ($hasUnknown): ?>
                        <label><input type="checkbox" name="area_unknown[<?= $escArea($key) ?>]"
                            value="1" <?= $unknownSelected ? 'checked' : '' ?>>? Unknown</label>
                    <?php endif; ?>
                <?php else: ?>
                    <input name="area_answers[<?= $escArea($key) ?>]"
                        type="<?= $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text') ?>"
                        <?= $type === 'number' ? 'step="' . $escArea($field['step'] ?? 'any') . '"' : '' ?>
                        value="<?= $escArea($value ?? '') ?>">
                    <?php if ($hasUnknown): ?>
                        <label><input type="checkbox" name="area_unknown[<?= $escArea($key) ?>]"
                            value="1" <?= $unknownSelected ? 'checked' : '' ?>>? Unknown</label>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($field['help'])): ?>
                    <small><?= $escArea($field['help']) ?></small>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>
        <?php if ($lastGroup !== null) echo '</div></details>'; ?>
        <div class="contribution-actions">
            <button type="submit" name="submit_area" value="1" class="contribution-submit">
                <?= llama_icon('send') ?> Submit Area for Review
            </button>
            <a href="/map.php">Cancel</a>
        </div>
    </form>
</section>
