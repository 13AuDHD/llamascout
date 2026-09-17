<?php
$compareSections =
    llama_compare_sections();

$columnCount =
    count(
        $compareReports
    );

$columnClass =
    'has-'
    . max(
        LLAMA_COMPARE_MIN_REPORTS,
        min(
            LLAMA_COMPARE_MAX_REPORTS,
            $columnCount
        )
    )
    . '-places';

$reportDataSets =
    array_values(
        array_map(
            static fn (
                array $report
            ): array =>
                is_array(
                    $report['data']
                    ?? null
                )
                    ? $report['data']
                    : [],
            $compareReports
        )
    );
?>

<section
    class="compare-results compare-report-results"
    aria-labelledby="compare-report-results-heading"
>
    <div class="compare-results-heading">
        <div>
            <p class="compare-eyebrow">
                Same Place, Different Reports
            </p>

            <h2 id="compare-report-results-heading">
                <?= number_format($columnCount) ?>
                Reports
            </h2>
        </div>

        <p>
            Each column is the published Scout Report after that approved
            contribution. A subtle left marker shows values changed by that
            specific report; the other values were carried forward from
            earlier approved history.
        </p>
    </div>

    <div class="compare-table-scroll">
        <div class="compare-table <?= llama_compare_h($columnClass) ?>">
            <div class="compare-corner">
                <span>Report</span>
            </div>

            <?php foreach ($compareReports as $report): ?>
                <?php
                $reportDate =
                    llama_compare_report_date_label(
                        $report['report_date']
                        ?? ''
                    );

                $contributor =
                    (string) (
                        $report['contributor_name']
                        ?? 'Contributor'
                    );

                $username =
                    trim(
                        (string) (
                            $report['contributor_username']
                            ?? ''
                        )
                    );

                $role =
                    llama_compare_report_role_label(
                        $report['role_at_time']
                        ?? ''
                    );
                ?>

                <article class="compare-place-header compare-report-header">
                    <div class="compare-report-header-icon">
                        <i aria-hidden="true"><?= llama_icon('history') ?></i>
                    </div>

                    <div class="compare-place-header-copy">
                        <small>
                            <?= !empty($report['is_latest'])
                                ? 'Latest approved report'
                                : llama_compare_h(
                                    (string) (
                                        $report['label']
                                        ?? 'Approved report'
                                    )
                                ) ?>
                        </small>

                        <h3>
                            <?= llama_compare_h($reportDate) ?>
                        </h3>

                        <span>
                            <?= llama_compare_h($contributor) ?>
                            ·
                            <?= llama_compare_h($role) ?>
                        </span>

                        <?php if ($username !== ''): ?>
                            <a
                                href="/<?= rawurlencode($username) ?>"
                            >
                                Contributor Profile
                                <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php foreach ($compareSections as $section): ?>
                <div class="compare-section-heading">
                    <i aria-hidden="true"><?= llama_icon(
                        (string) $section['icon']
                    ) ?></i>

                    <?= llama_compare_h(
                        (string) $section['title']
                    ) ?>
                </div>

                <?php foreach ((array) $section['rows'] as $row): ?>
                    <?php if (
                        !llama_compare_row_has_data(
                            $reportDataSets,
                            $row
                        )
                    ): ?>
                        <?php continue; ?>
                    <?php endif; ?>

                    <div class="compare-row-label">
                        <?= llama_compare_h(
                            (string) $row['label']
                        ) ?>
                    </div>

                    <?php foreach ($compareReports as $report): ?>
                        <?php
                        $reportData =
                            is_array(
                                $report['data']
                                ?? null
                            )
                                ? $report['data']
                                : [];

                        $value =
                            llama_compare_format_row_value(
                                $reportData,
                                $row
                            );

                        $isMissing =
                            $value === 'Not reported';

                        $isYes =
                            $value === 'Yes';

                        $isNo =
                            $value === 'No';

                        $isRating =
                            preg_match(
                                '/^[1-5] \/ 5$/',
                                $value
                            ) === 1;

                        $wasUpdated =
                            llama_compare_report_row_was_updated(
                                $report,
                                $row
                            );
                        ?>

                        <div
                            class="compare-value
                                <?= $isMissing ? 'is-missing' : '' ?>
                                <?= $isYes ? 'is-yes' : '' ?>
                                <?= $isNo ? 'is-no' : '' ?>
                                <?= $isRating ? 'is-rating' : '' ?>
                                <?= $wasUpdated ? 'is-report-updated' : '' ?>"
                        >
                            <?php if ($isYes): ?>
                                <i aria-hidden="true"><?= llama_icon('circle-check') ?></i>
                            <?php elseif ($isNo): ?>
                                <i aria-hidden="true"><?= llama_icon('xbox-x') ?></i>
                            <?php endif; ?>

                            <?php if ($isRating): ?>
                                <?php
                                $rating =
                                    (int) $value;
                                ?>

                                <span
                                    class="compare-rating-dots"
                                    aria-hidden="true"
                                >
                                    <?php for (
                                        $dot = 1;
                                        $dot <= 5;
                                        $dot++
                                    ): ?>
                                        <i
                                            class="<?= $dot <= $rating ? 'is-filled' : '' ?>"
                                        ></i>
                                    <?php endfor; ?>
                                </span>
                            <?php endif; ?>

                            <span>
                                <?= llama_compare_h(
                                    $value
                                ) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
