<?php

declare(strict_types=1);

// The first Site is a child of this Place, but its GPS point is independent
// of the Place's latitude/longitude Scout Report questions.
$siteCoordinatesValue = trim((string) ($placeReportValues['site_coordinates'] ?? ''));
$adminSiteLocation = $placeReportMode === 'moderator';
?>
<details class="contribution-section" data-site-location-section>
    <summary><span>Location</span></summary>
    <div class="contribution-section-body">
        <div class="contribution-grid">
            <div class="contribution-field add-place-locate-panel">
                <span>At the Site right now?</span>
                <button type="button" class="add-place-locate-button" data-site-locate <?= $adminSiteLocation ? 'disabled' : '' ?>>
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
                        data-site-coordinate-input <?= $adminSiteLocation ? 'disabled' : '' ?>>
                    <button type="button" class="add-place-coordinate-search" data-site-coordinate-search <?= $adminSiteLocation ? 'disabled' : '' ?>>Search</button>
                </div>
            </div>
            <div class="add-place-location-status is-visible is-info" data-site-location-status role="status">
                At the Site? Tap Locate me. Otherwise, enter coordinates and tap Search.
                These identify the Site, not its Parent Place.
            </div>
            <?php if (!$adminSiteLocation && $placeReportOnlyScope === 'site'): ?>
                <section class="nearest-services-panel" data-site-nearest-preview aria-label="Nearest Services">
                    <h3>Nearest Services</h3>
                    <p data-nearest-status role="status" aria-live="polite">Enter Site coordinates to automatically find nearby mapped services.</p>
                    <div data-nearest-results></div>
                </section>
            <?php endif; ?>
            <?php if ($adminSiteLocation): ?>
                <input type="hidden" name="admin_site_coordinates_changed"
                       value="0" data-admin-site-coordinates-changed>
                <p class="admin-site-location-note" data-admin-site-location-note>
                    Select a Site above to view or edit its coordinates.
                </p>
            <?php endif; ?>
        </div>
    </div>
</details>
