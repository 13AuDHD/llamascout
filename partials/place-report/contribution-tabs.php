<?php
declare(strict_types=1);

// The same four-option contribution navigation is shared by Add a Place
// (three local panels plus the standalone Resource page) and Add a Resource.
// The Resource editor is a separate form, never embedded in the Place form.
$isResourcePage = !empty($contributionPickerResourcePage);
$isNewContribution = $isResourcePage || isset($areaInitialTab);
$placeDraftSuffix = $isResourcePage && !empty($contributionReturnDraft)
    ? '&draft=' . (int) $contributionReturnDraft
    : '';
?>
<div class="contribution-scope-picker"
    role="group" aria-label="Contribution type"
    data-contribution-picker
    <?= $isResourcePage ? 'data-contribution-resource-page' : 'hidden' ?>>
    <?php if ($isResourcePage): ?>
        <a href="/add-place.php?tab=place<?= $placeDraftSuffix ?>">Place</a>
        <a href="/add-place.php?tab=site<?= $placeDraftSuffix ?>">Site</a>
        <a href="/add-place.php?tab=area<?= $placeDraftSuffix ?>">Facility</a>
        <span class="contribution-current-resource" aria-current="page">Resource</span>
    <?php else: ?>
        <button type="button" data-contribution-mode="place" aria-pressed="true">Place</button>
        <button type="button" data-contribution-mode="site" aria-pressed="false">Site</button>
        <button type="button" data-contribution-mode="area" aria-pressed="false"><?= $isNewContribution ? 'Facility' : 'Area' ?></button>
        <?php if ($isNewContribution): ?>
            <a href="/resource-contribution.php<?= !empty($draftId) ? '?return_draft=' . (int) $draftId : '' ?>"
               data-contribution-resource-link
               title="Create a separate, searchable Resource">Resource</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php if (!$isResourcePage): ?>
<script defer src="https://llamascout.com/js/contribution-tabs.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/js/contribution-tabs.js') ?>"></script>
<?php endif; ?>
