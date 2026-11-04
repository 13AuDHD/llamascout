<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3)
    . '/app/place-report.php';
require_once dirname(__DIR__, 3)
    . '/app/usfs-place-report.php';

 /*
 * Issue #41: every authorized viewer uses the same canonical Scout Report
 * renderer. The presentation source may contain the complete published Place
 * record, while the renderer independently decides which answers this viewer
 * may see. Never use a stripped Place object to invent a second report shape.
 */
$placeReportSource =
    is_array($placeReportSource ?? null)
        ? $placeReportSource
        : $place;

$placeReportCanViewCommunity =
    (bool) ($placeReportCanViewCommunity ?? true);

$placeReportData =
    llama_place_report_data_from_published_place(
        $placeReportSource,
        []
    );

if (empty($isDemoScoutReport) && !empty($placeReportSource['id'])) {
    try {
        $placeReportData =
            llama_place_report_data_from_current_place(
                db(),
                $placeReportSource
            );
    } catch (Throwable $exception) {
        // Keep the public Place readable if the current-answer store fails.
        // Only approved questions can render in the shared read-only view.
        llama_log_caught_exception($exception, 'scout-report.current-place');
    }
}

$placeReportReadMode =
    'scout-report';

$compareReportHistoryUrl =
    !empty($isDemoScoutReport)
    || empty($place['slug'])
        ? ''
        : '/compare.php?mode=reports&place='
            . rawurlencode(
                (string) $place['slug']
            );
?>

<link
    rel="stylesheet"
    href="/css/site/features/place-report-form.css"
>

<section
    class="scout-report"
    aria-labelledby="scout-report-heading"
    data-question-inventory-review="<?= llama_place_report_full_inventory_review_enabled() ? '1' : '0' ?>"
>
    <header class="scout-report-header">
        <div>
            <p class="eyebrow">Member details</p>
            <h2 id="scout-report-heading">Scout Report</h2>
            <div
                class="place-report-completeness scout-report-completeness"
                title="How many Scout Report questions have been addressed. Known and Unknown responses count as addressed. Unanswered questions do not."
            >
                <i aria-hidden="true"><?= llama_icon('list-check') ?></i>
                <span>
                    Report completeness
                    <?= (int) ($reportCompleteness['percent'] ?? 0) ?>%
                    <?php if (
                        isset(
                            $reportCompleteness['answered'],
                            $reportCompleteness['total']
                        )
                        && (int) $reportCompleteness['total'] > 0
                    ): ?>
                        ·
                        <?= (int) $reportCompleteness['answered'] ?>
                        /
                        <?= (int) $reportCompleteness['total'] ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if ($compareReportHistoryUrl !== ''): ?>
            <a
                class="scout-report-history-link"
                href="<?= htmlspecialchars(
                    $compareReportHistoryUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                <i aria-hidden="true"><?= llama_icon('history') ?></i>
                Compare Reports
            </a>
        <?php endif; ?>
    </header>

    <?php
    require dirname(__DIR__, 2)
        . '/place-report/read-only.php';
    ?>

</section>
