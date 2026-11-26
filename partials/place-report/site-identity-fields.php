<?php
// Only the standalone Add Site flow supplies these values. Site revisions
// cannot reparent an existing pending contribution.
?>
<?php if ($isNeedsChanges): ?>
    <input type="hidden" name="site_place_id" value="<?= $selectedPlaceId ?>">
    <div class="contribution-field">
        <span class="place-report-field-label">Parent Place</span>
        <strong><?= $e((string) ($revision['data']['place_name']
            ?? $selectedParentPlace['name'] ?? ('Place #' . $selectedPlaceId))) ?></strong>
    </div>
<?php else: ?>
    <div class="contribution-field site-parent-place-picker" data-site-parent-picker>
        <label class="place-report-field-label" for="site-parent-place">Parent Place *</label>
        <input type="hidden" name="site_place_id" data-site-parent-id
            value="<?= (int) ($selectedParentPlace['id'] ?? 0) ?: '' ?>">
        <input type="text" id="site-parent-place" data-site-parent-input
            autocomplete="off" spellcheck="false" required
            role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
            aria-controls="site-parent-place-results" aria-expanded="false"
            value="<?= $e((string) ($selectedParentPlace['name'] ?? '')) ?>">
        <div class="site-parent-place-results" id="site-parent-place-results"
            data-site-parent-results role="listbox" hidden></div>
    </div>
<?php endif; ?>
<div class="contribution-field">
    <label class="place-report-field-label" for="contribution-site-name">Site name *</label>
    <div class="add-place-name-control">
        <input type="text" id="contribution-site-name" name="site_name" maxlength="120" required
            data-site-name value="<?= $e($siteNameValue) ?>"
            placeholder="Site 12, North Parking Spot, etc.">
        <button type="button" class="add-place-name-refresh" data-refresh-site-name
            aria-label="Suggest a Site name" title="Suggest a Site name"><?= llama_icon('refresh') ?></button>
    </div>
    <small>Use the assigned Site name or number when known. Request a suggestion for an unnamed Site.</small>
    <small data-site-name-status role="status" aria-live="polite"></small>
</div>
