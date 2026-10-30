<?php
declare(strict_types=1);
?>
<?php if ($siteHistoryAvailable): ?>
<section class="compare-picker" aria-labelledby="site-report-picker-title">
    <div class="compare-picker-heading">
        <h2 id="site-report-picker-title">Choose Site reports</h2>
        <span><?= count($siteHistoryAvailable) ?> recent reports available</span>
    </div>
    <p>Select up to four approved reports to compare. By default, the four newest are selected.</p>
    <form method="get" action="/compare.php" class="compare-site-report-picker" data-compare-site-report-picker>
        <input type="hidden" name="mode" value="reports">
        <input type="hidden" name="place" value="<?= htmlspecialchars($reportPlaceSlug, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="site" value="<?= (int) $reportSiteId ?>">
        <p data-compare-site-report-count aria-live="polite"><?= count($siteHistorySelectedIds) ?> selected (2 to 4)</p>
        <div class="compare-site-report-options">
            <?php foreach ($siteHistoryAvailable as $report): ?>
                <?php $reportId = (int) $report['reportId']; ?>
                <label>
                    <input type="checkbox" name="siteReports[]" value="<?= $reportId ?>"
                        <?= in_array($reportId, $siteHistorySelectedIds, true) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars((string) $report['username'], ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars((string) ($report['visitDate'] ?: $report['submittedAt']), ENT_QUOTES, 'UTF-8') ?>
                        <small>Report #<?= $reportId ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="compare-button" type="submit" data-compare-site-report-submit <?= count($siteHistorySelectedIds) < 2 ? "disabled" : "" ?>>Compare selected reports</button>
    </form>
</section>
<?php endif; ?>
