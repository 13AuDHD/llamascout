<div class="contribution-scope-picker" role="group" aria-label="Contribution type" data-contribution-picker hidden>
    <button type="button" data-contribution-mode="place" aria-pressed="true">Place</button>
    <button type="button" data-contribution-mode="site" aria-pressed="false">Site</button>
    <button type="button" data-contribution-mode="area" aria-pressed="false"><?= isset($areaInitialTab) ? 'Facility' : 'Area' ?></button>
    <?php if (isset($areaInitialTab)): ?>
        <a href="/resource-contribution.php" data-contribution-resource-link
           title="Create a separate, searchable Resource such as water, propane or dump station">Resource</a>
    <?php endif; ?>
</div>
<script defer src="https://llamascout.com/js/contribution-tabs.js?v=<?= (int) filemtime(dirname(__DIR__, 2) . '/js/contribution-tabs.js') ?>"></script>
