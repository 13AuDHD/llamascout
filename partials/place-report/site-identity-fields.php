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
<label class="contribution-field">
    <span class="place-report-field-label">Site name *</span>
    <input type="text" name="site_name" maxlength="120" required
        value="<?= $e($siteNameValue) ?>"
        placeholder="Site 12, North Parking Spot, etc.">
</label>
