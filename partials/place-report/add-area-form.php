<?php
declare(strict_types=1);

/**
 * The same canonical Area question registry, displayed in calm sections.
 * Area coordinates are optional, but are saved with the approved Area
 * when supplied. No new Area question names or storage keys are introduced.
 */
$escArea = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// Only actual Facilities can be added publicly. Keep legacy area
// feature types in storage for administrative Site/loop management.
$types = [
    'facility_area' => 'Facility',
];
$facilityKinds = llama_place_facility_kind_options();
$selectedAreaType = (string) ($_POST['area_feature_type'] ?? 'facility_area');
$selectedFacilityKind = (string) ($_POST['area_facility_kind'] ?? 'restroom');
$selectedPlaceId = (int) ($_POST['area_place_id'] ?? $_GET['area_place_id'] ?? $_GET['place_id'] ?? 0);
$areaAnswers = is_array($_POST['area_answers'] ?? null) ? $_POST['area_answers'] : [];
$areaUnknown = is_array($_POST['area_unknown'] ?? null) ? $_POST['area_unknown'] : [];
$areaQuestions = llama_report_questions_for_scope('area', true);
$areaSectionDefinitions = llama_place_report_sections();
$areaGroupedQuestions = [];
foreach ($areaQuestions as $key => $field) {
    $sectionKey = (string) ($field['section'] ?? 'summaries');
    if (!isset($areaSectionDefinitions[$sectionKey])) {
        $sectionKey = 'summaries';
    }
    // Basic identity and GPS controls have their own first-class sections.
    // Other canonical questions stay in their original question groups.
    $groupLabel = (string) (
        $field['approved_question_group_label']
        ?? $areaSectionDefinitions[$sectionKey]['label']
    );
    $areaGroupedQuestions[$sectionKey]['label'] = (string) $areaSectionDefinitions[$sectionKey]['label'];
    $areaGroupedQuestions[$sectionKey]['icon'] = (string) ($areaSectionDefinitions[$sectionKey]['icon'] ?? 'clipboard-list');
    $areaGroupedQuestions[$sectionKey]['groups'][$groupLabel][$key] = $field;
}
$areaCoordinates = (string) ($_POST['area_coordinates'] ?? '');
?>
<section class="contribution-area-panel" data-contribution-area-panel hidden>
    <form method="post" action="/add-place.php?tab=area"
          id="area-create-form" class="area-create-form" data-area-create-form>
        <input type="hidden" name="csrf_token" value="<?= $escArea(community_csrf_token()) ?>">

        <details class="contribution-section area-form-section" open data-area-basic-section>
            <summary>
                <span><?= llama_icon('info-circle') ?> Basic</span>
            </summary>
            <div class="contribution-section-body area-form-section-body">
                <label class="area-bundle-toggle">
                    <input type="checkbox" data-area-bundle-new>
                    Include this Facility in the new Place I'm adding
                </label>
                <p data-area-bundle-status role="status" aria-live="polite" hidden></p>

                <div class="area-place-search" data-area-search-existing>
                    <label for="area-place-query">Find the parent Place</label>
                    <div class="area-place-search-row">
                        <input id="area-place-query" data-area-place-query type="search"
                            value="<?= $escArea($_GET['area_q'] ?? '') ?>"
                            placeholder="Search campground or Place name">
                        <button type="button" data-area-find-place>Find Places</button>
                    </div>
                </div>
                <label class="contribution-field" data-area-existing-parent>
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
                        <span>Facility name</span>
                        <input type="text" name="area_name" maxlength="120" required
                            placeholder="e.g. West Vault Toilets"
                            value="<?= $escArea($_POST['area_name'] ?? '') ?>">
                    </label>
                    <label class="contribution-field" hidden>
                        <span>Facility record type</span>
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
                        <span>What kind of Facility?</span>
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
            </div>
        </details>

        <details class="contribution-section area-form-section" open data-area-location-section>
            <summary>
                <span><?= llama_icon('current-location') ?> Location</span>
            </summary>
            <div class="contribution-section-body area-form-section-body">
                <div class="area-location-fields">
                    <div class="contribution-field">
                        <span>At the Facility right now?</span>
                        <button type="button" class="area-location-button" data-area-locate>
                            <?= llama_icon('current-location') ?> Locate me
                        </button>
                    </div>
                    <div class="contribution-field">
                        <label for="area-coordinates">Coordinates <small>(optional)</small></label>
                        <div class="area-coordinate-row">
                            <input id="area-coordinates" name="area_coordinates"
                                type="text" inputmode="text" autocomplete="off"
                                data-area-coordinate-input
                                placeholder="37.2522200, -107.2192000"
                                value="<?= $escArea($areaCoordinates) ?>">
                            <button type="button" data-area-search-coordinates>Search</button>
                        </div>
                    </div>
                </div>
                <p class="area-location-status" role="status" aria-live="polite"
                   data-area-location-status>
                    At the Facility? Tap Locate me. Otherwise enter latitude, longitude and tap Search.
                    You can leave this blank and map the Facility later.
                </p>
            </div>
        </details>

        <?php foreach ($areaGroupedQuestions as $sectionKey => $section): ?>
            <details class="contribution-section area-form-section area-report-section"
                data-area-report-section="<?= $escArea($sectionKey) ?>">
                <summary>
                    <span><?= llama_icon((string) $section['icon']) ?>
                        <?= $escArea((string) $section['label']) ?></span>
                </summary>
                <div class="contribution-section-body area-form-section-body">
                    <?php foreach ($section['groups'] as $group => $groupFields): ?>
                        <details class="area-question-group" data-area-question-group>
                            <summary><?= $escArea((string) $group) ?></summary>
                            <div class="area-question-grid">
                                <?php foreach ($groupFields as $key => $field): ?>
                                    <?php
                                        $kinds = array_values(array_map('strval', (array) ($field['facility_kinds'] ?? [])));
                                        $areaTypes = array_values(array_map('strval', (array) ($field['area_feature_types'] ?? [])));
                                    ?>
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
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="contribution-actions">
            <button type="submit" name="submit_area" value="1" class="contribution-submit"
                data-area-submit-existing>
                <?= llama_icon('send') ?> Submit Facility for Review
            </button>
            <button type="button" class="contribution-submit" data-area-attach-new hidden>
                Add Facility to New Place Submission
            </button>
            <button type="submit" form="add-place-main-form" class="contribution-submit"
                data-area-submit-new hidden>
                <?= llama_icon('send') ?> Submit Place + Facility for Review
            </button>
            <a href="/map.php">Cancel</a>
        </div>
    </form>
</section>
