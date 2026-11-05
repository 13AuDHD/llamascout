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

/*
 * M4: select one of this Place's approved named seasons. These are a view
 * over canonical answers, not additional reports. Any database migration
 * error should leave the normal Scout Report working.
 */
$publicReportSeasons = [];
$publicReportSeason = null;
$publicSeasonAttribution = [];
if (empty($isDemoScoutReport) && !empty($placeReportSource['id'])) {
    try {
        require_once dirname(__DIR__, 3) . '/app/place-report/season-public.php';
        $seasonState = llama_public_season_selection(
            db(),
            (int) $placeReportSource['id'],
            $_GET['season'] ?? null
        );
        $publicReportSeasons = (array) ($seasonState['seasons'] ?? []);
        $publicReportSeason = $seasonState['selected'] ?? null;
        if (is_array($publicReportSeason)) {
            $seasonAnswers = llama_season_answers_for_target(
                db(),
                (int) $placeReportSource['id'],
                (int) $publicReportSeason['id'],
                [
                    'scope' => 'place',
                    'source' => 'place',
                    'id' => (int) $placeReportSource['id'],
                ]
            );
            $placeReportData = llama_public_season_project_place(
                $placeReportData, $seasonAnswers
            );
            foreach ($seasonAnswers as $key => $answer) {
                $publicSeasonAttribution[(string) $key] =
                    llama_public_season_source($answer);
            }
        }
    } catch (Throwable $exception) {
        llama_log_caught_exception($exception, 'scout-report.public-season');
        $publicReportSeason = null;
        $publicReportSeasons = [];
        $publicSeasonAttribution = [];
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

    <?php if ($publicReportSeasons): ?>
        <form class="scout-report-season-picker" method="get"
              action="/place.php" data-scout-season-form>
            <input type="hidden" name="slug"
                   value="<?= htmlspecialchars((string) $placeReportSource['slug'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="site" data-season-selected-site
                   value="<?= (int) ($_GET['site'] ?? 0) ?: '' ?>">
            <label for="scout-report-season">
                <strong>Viewing season</strong>
                <span>See which services and rules change through the year.</span>
            </label>
            <div class="scout-report-season-inputs">
                <select id="scout-report-season" name="season">
                    <option value="0" <?= !is_array($publicReportSeason) ? 'selected' : '' ?>>
                        Normal report
                    </option>
                    <?php foreach ($publicReportSeasons as $season): ?>
                        <?php
                        $from = (string) ($season['start_mmdd'] ?? '');
                        $to = (string) ($season['end_mmdd'] ?? '');
                        $dates = $from !== '' && $to !== ''
                            ? ' (' . $from . ' to ' . $to . ')' : '';
                        ?>
                        <option value="<?= (int) $season['id'] ?>"
                            <?= (int) ($publicReportSeason['id'] ?? 0) === (int) $season['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $season['name'] . $dates, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">View season</button>
            </div>
            <small>
                Seasonal details describe normal operations, not real-time availability.
                Verified closures and current fire restrictions still apply.
                Staying across seasons? Check both.
            </small>
        </form>
        <script defer src="/js/place-report-seasons.js"></script>
    <?php endif; ?>

    <?php
    require dirname(__DIR__, 2)
        . '/place-report/read-only.php';
    ?>

</section>
