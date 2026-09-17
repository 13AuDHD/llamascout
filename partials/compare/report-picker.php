<section
    class="compare-picker compare-report-picker"
    aria-labelledby="compare-report-picker-heading"
>
    <div class="compare-picker-heading">
        <div>
            <p class="compare-eyebrow">
                Report History
            </p>

            <h2 id="compare-report-picker-heading">
                Choose a Place and reports
            </h2>
        </div>

        <?php if ($reportPlaceSlug !== ''): ?>
            <span>
                <?= number_format(
                    count($reportVersions)
                ) ?>
                report<?= count($reportVersions) === 1 ? '' : 's' ?>
                available
            </span>
        <?php endif; ?>
    </div>

    <form
        class="compare-report-place-form"
        method="get"
        action="/compare.php"
    >
        <input
            type="hidden"
            name="mode"
            value="reports"
        >

        <label for="compare-report-place">
            Place
        </label>

        <div class="compare-report-place-row">
            <select
                id="compare-report-place"
                name="place"
                required
            >
                <option value="">
                    Choose a Place...
                </option>

                <?php foreach ($placeOptions as $option): ?>
                    <?php
                    $optionSlug =
                        (string) (
                            $option['slug']
                            ?? ''
                        );

                    $location =
                        implode(
                            ', ',
                            array_filter(
                                [
                                    (string) (
                                        $option['city']
                                        ?? ''
                                    ),
                                    (string) (
                                        $option['state']
                                        ?? ''
                                    ),
                                ]
                            )
                        );
                    ?>

                    <option
                        value="<?= llama_compare_h($optionSlug) ?>"
                        <?= $optionSlug === $reportPlaceSlug
                            ? 'selected'
                            : '' ?>
                    >
                        <?= llama_compare_h(
                            (string) (
                                $option['name']
                                ?? 'Unnamed Place'
                            )
                            . (
                                $location !== ''
                                    ? ' · ' . $location
                                    : ''
                            )
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button
                class="compare-button is-secondary"
                type="submit"
            >
                <i aria-hidden="true"><?= llama_icon('history') ?></i>
                Load History
            </button>
        </div>
    </form>

    <?php if (
        $reportPlaceSlug !== ''
        && $reportVersions
    ): ?>

        <form
            class="compare-report-selection"
            method="get"
            action="/compare.php"
            data-compare-report-picker
            data-min-reports="<?= LLAMA_COMPARE_MIN_REPORTS ?>"
            data-max-reports="<?= LLAMA_COMPARE_MAX_REPORTS ?>"
        >
            <input
                type="hidden"
                name="mode"
                value="reports"
            >

            <input
                type="hidden"
                name="place"
                value="<?= llama_compare_h($reportPlaceSlug) ?>"
            >

            <div class="compare-report-selection-heading">
                <div>
                    <strong>
                        <?= llama_compare_h(
                            (string) (
                                $reportPlace['name']
                                ?? 'Report history'
                            )
                        ) ?>
                    </strong>

                    <span>
                        Select two to four approved reports.
                    </span>
                </div>

                <span data-compare-report-count>
                    <?= number_format(
                        count($compareReportKeys)
                    ) ?>
                    selected
                </span>
            </div>

            <div class="compare-report-options">
                <?php foreach ($reportVersions as $report): ?>
                    <?php
                    $reportKey =
                        (string) (
                            $report['key']
                            ?? ''
                        );

                    $selected =
                        in_array(
                            $reportKey,
                            $compareReportKeys,
                            true
                        );

                    $dateLabel =
                        llama_compare_report_date_label(
                            $report['report_date']
                            ?? ''
                        );

                    $contributor =
                        (string) (
                            $report['contributor_name']
                            ?? 'Contributor'
                        );

                    $role =
                        llama_compare_report_role_label(
                            $report['role_at_time']
                            ?? ''
                        );
                    ?>

                    <label
                        class="compare-report-option <?= $selected ? 'is-selected' : '' ?>"
                    >
                        <input
                            type="checkbox"
                            name="reports[]"
                            value="<?= llama_compare_h($reportKey) ?>"
                            <?= $selected ? 'checked' : '' ?>
                        >

                        <span class="compare-report-option-check">
                            <i aria-hidden="true"><?= llama_icon('check') ?></i>
                        </span>

                        <span class="compare-report-option-copy">
                            <strong>
                                <?= llama_compare_h($dateLabel) ?>
                            </strong>

                            <small>
                                <?= llama_compare_h($contributor) ?>
                                ·
                                <?= llama_compare_h($role) ?>
                            </small>
                        </span>

                        <span class="compare-report-option-meta">
                            <?php if (!empty($report['is_latest'])): ?>
                                <b>Latest</b>
                            <?php elseif (
                                (string) (
                                    $report['kind']
                                    ?? ''
                                ) === 'initial'
                            ): ?>
                                <b>Initial</b>
                            <?php else: ?>
                                <b>Update</b>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="compare-picker-footer">
                <span>
                    Up to
                    <?= LLAMA_COMPARE_MAX_REPORTS ?>
                    reports
                </span>

                <div>
                    <a
                        class="compare-button is-quiet"
                        href="/compare.php?mode=reports&place=<?= rawurlencode(
                            $reportPlaceSlug
                        ) ?>"
                    >
                        Newest Two
                    </a>

                    <button
                        class="compare-button"
                        type="submit"
                        data-compare-report-submit
                        <?= count($compareReportKeys) < LLAMA_COMPARE_MIN_REPORTS
                            ? 'disabled'
                            : '' ?>
                    >
                        <i aria-hidden="true"><?= llama_icon('arrows-diff') ?></i>
                        Compare Reports
                    </button>
                </div>
            </div>
        </form>

    <?php elseif ($reportPlaceSlug !== ''): ?>

        <div class="compare-notice">
            No approved report history is available for this Place yet.
        </div>

    <?php endif; ?>
</section>
