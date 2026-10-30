<?php
declare(strict_types=1);

/** Site-specific approved Scout Report history inside the existing compare page. */
$escapeSiteHistory = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$siteHistoryFields = llama_place_report_fields();
$siteHistoryQuestionKeys = [];
foreach ($siteHistoryReports as $report) {
    foreach (array_keys((array) ($report['answers'] ?? [])) as $key) {
        if (isset($siteHistoryFields[$key])) {
            $siteHistoryQuestionKeys[$key] = true;
        }
    }
}
?>
<section class="compare-empty" aria-labelledby="site-history-heading">
    <h2 id="site-history-heading">Site report history</h2>
    <p>Approved observations for Site #<?= (int) $reportSiteId ?>.
        Each column is an original report, not a replacement for the current Site summary.</p>
    <?php if (!$siteHistoryReports): ?>
        <p>No approved V3 Scout Reports have been recorded for this Site yet.</p>
    <?php else: ?>
        <div class="compare-site-history-scroll">
            <table class="compare-site-history-table">
                <thead>
                    <tr>
                        <th scope="col">Question</th>
                        <?php foreach ($siteHistoryReports as $report): ?>
                            <th scope="col">
                                <?= $escapeSiteHistory($report['username']) ?>
                                <small><?= $escapeSiteHistory($report['visitDate'] ?: $report['submittedAt']) ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_keys($siteHistoryQuestionKeys) as $questionKey): ?>
                        <tr>
                            <th scope="row"><?= $escapeSiteHistory(
                                $siteHistoryFields[$questionKey]['label'] ?? $questionKey
                            ) ?></th>
                            <?php foreach ($siteHistoryReports as $report): ?>
                                <?php $answer = $report['answers'][$questionKey] ?? null; ?>
                                <td>
                                    <?php if ($answer === null): ?>
                                        Not answered
                                    <?php elseif (($answer['state'] ?? '') === 'unknown'): ?>
                                        Unknown
                                    <?php else: ?>
                                        <?php
                                        // Reuse the same formatting rules as published Scout cards.
                                        $field = $siteHistoryFields[$questionKey];
                                        $value = $answer['value'] ?? null;
                                        $displayData = [];
                                        llama_place_report_set_path(
                                            $displayData,
                                            (string) $field['storage'],
                                            $value
                                        );
                                        $text = llama_place_report_display_value(
                                            $displayData,
                                            $questionKey
                                        );
                                        if ($text === null || $text === '') {
                                            $text = 'No value recorded';
                                        }
                                        ?>
                                        <?= $escapeSiteHistory($text) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
