<?php

declare(strict_types=1);

// The first Site is a child of this Place, but its GPS point is independent
// of the Place's latitude/longitude Scout Report questions.
$siteCoordinatesValue = trim((string) ($placeReportValues['site_coordinates'] ?? ''));
?>
<details class="contribution-section" data-site-location-section>
    <summary><span>Location</span></summary>
    <div class="contribution-section-body">
        <div class="contribution-grid">
            <div class="contribution-field add-place-locate-panel">
                <span>At the Site right now?</span>
                <button type="button" class="add-place-locate-button" data-site-locate>
                    <?= llama_icon('current-location') ?>
                    Locate me
                </button>
            </div>
            <div class="contribution-field add-place-coordinate-field">
                <label class="place-report-field-label" for="add-site-coordinates">Coordinates</label>
                <div class="add-place-coordinate-row">
                    <input id="add-site-coordinates" type="text" name="site_coordinates"
                        value="<?= $e($siteCoordinatesValue) ?>"
                        placeholder="37.2522200, -107.2192000"
                        inputmode="decimal" autocomplete="off" spellcheck="false"
                        data-site-coordinate-input>
                    <button type="button" class="add-place-coordinate-search" data-site-coordinate-search>Search</button>
                </div>
            </div>
            <div class="add-place-location-status is-visible is-info" data-site-location-status role="status">
                At the Site? Tap Locate me. Otherwise, enter coordinates and tap Search.
                These identify the Site, not its Parent Place.
            </div>
        </div>
    </div>
</details>
