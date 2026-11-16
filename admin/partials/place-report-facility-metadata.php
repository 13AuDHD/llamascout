<?php

declare(strict_types=1);

// The mapped Facility's canonical Experience answers are still loaded from
// the existing Area-scope registry. This panel edits its mapped identity and
// location, preserving its original feature and Scoped Report IDs.
?>
<section class="admin-place-facility-metadata contribution-area-panel"
         data-admin-facility-metadata hidden>
    <details class="contribution-section" open>
        <summary><span>Basic</span></summary>
        <div class="contribution-section-body">
            <div class="contribution-grid">
                <label class="contribution-field">
                    <span>Facility name</span>
                    <input type="text" name="facility_name" maxlength="160"
                           data-admin-facility-name>
                </label>
                <label class="contribution-field" data-admin-facility-kind-wrap>
                    <span>Facility type</span>
                    <select name="facility_kind" data-admin-facility-kind>
                        <option value="">Unspecified</option>
                        <?php foreach (llama_place_facility_kind_options() as $kind => $label): ?>
                        <option value="<?= $e($kind) ?>"><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="contribution-field contribution-field-wide">
                    <span>Description</span>
                    <textarea name="facility_notes" rows="3" maxlength="4000"
                              data-admin-facility-notes></textarea>
                </label>
            </div>
        </div>
    </details>

    <details class="contribution-section" open>
        <summary><span>Location</span></summary>
        <div class="contribution-section-body">
            <div class="contribution-grid">
                <label class="contribution-field contribution-field-wide">
                    <span>Coordinates (latitude, longitude)</span>
                    <input type="text" name="facility_coordinates" inputmode="decimal"
                           placeholder="37.2522200, -107.2192000"
                           data-admin-facility-coordinates>
                </label>
                <input type="hidden" name="facility_coordinates_changed" value="0"
                       data-admin-facility-coordinates-changed>
                <p class="admin-place-muted contribution-field-wide" data-admin-facility-map-hint>
                    Select a Facility to see its coordinates.
                </p>
                <a class="admin-button is-muted" data-admin-facility-map-link
                   href="https://llamascout.com/place-map-editor.php?slug=<?= rawurlencode((string) ($place['slug'] ?? '')) ?>&amp;from=admin"
                   target="_blank" rel="noopener">
                    <?= llama_icon('map') ?> Edit Map
                </a>
            </div>
        </div>
    </details>
    <script type="application/json" data-admin-facility-map-catalog><?= json_encode(
        $adminFacilityMapInfo,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    ) ?></script>
</section>
