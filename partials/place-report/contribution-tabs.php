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
<?php if ($isNewContribution): ?>
<div class="contribution-type-navigation">
<?php endif; ?>
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
<?php if ($isNewContribution): ?>
    <?php
        // All four contributions share one explanatory card directly
        // under the switcher. Only the active report type is displayed.
        $contributionIntros = [
            'place' => [
                'title' => 'Add a Place',
                'description' => 'Describe a Place (Dispersed Camping Area, Campground, Parking Lot, RV Park, etc).',
            ],
            'site' => [
                'title' => 'Add a Site',
                'description' => 'Describe a Site (Campsite, Parking Spot, etc) that’s inside of a Place.',
            ],
            'area' => [
                'title' => 'Add a Facility',
                'description' => 'Describe an on-site Facility (Laundry, Shower, Dump Station, etc) that’s inside of a Place.',
            ],
            'resource' => [
                'title' => 'Add a Resource',
                'description' => 'Describe an off-site Resource (Water, Shower, Dump Station, etc) not inside of a Place.',
            ],
        ];
        $introMode = $isResourcePage
            ? 'resource'
            : (string) ($areaInitialTab ?? 'place');
    ?>
    <?php foreach ($contributionIntros as $introKey => $intro): ?>
        <?php if ($isResourcePage ? $introKey === 'resource' : $introKey !== 'resource'): ?>
            <section class="contribution-type-intro" data-contribution-intro-mode="<?= $introKey ?>"
                <?= $introKey !== $introMode ? 'hidden' : '' ?>>
                <h2><?= htmlspecialchars($intro['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($intro['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!$isResourcePage): ?>
<script defer src="https://llamascout.com/js/contribution-tabs.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/js/contribution-tabs.js') ?>"></script>
<?php endif; ?>
