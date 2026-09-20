<section class="place-history-section" id="place-history">
    <div class="place-detail-container">

        <div class="place-history-heading">
            <p class="place-detail-eyebrow">Place history</p>
            <h2>Who helped document this Place</h2>
            <p>
                See who has added or updated information here, and open
                past reports to see how this Place has changed over time.
            </p>
        </div>

        <?php if ($historyProvenance): ?>
            <?php
            $originName = trim((string) (
                $historyProvenance['contributor_display_name']
                ?: $historyProvenance['contributor_username']
                ?: ''
            ));

            $originRecord = [
                'user_id' => (int) (
                    $historyProvenance['original_activity_user_id']
                    ?? $historyProvenance['original_contributor_id']
                    ?? 0
                ),
                'role_at_time' => (string) (
                    $historyProvenance['original_role_at_time']
                    ?? ''
                ),
            ];

            if ($originRecord['role_at_time'] !== '') {
                $originLevel = llama_contribution_level_for_record(
                    $db,
                    $originRecord
                );
            } else {
                $originType = strtolower(trim((string) (
                    $historyProvenance['origin_type']
                    ?? ''
                )));

                $originLevel = in_array(
                    $originType,
                    ['llama-scouted', 'scout'],
                    true
                )
                    ? LLAMA_CONTRIBUTION_LEVEL_SCOUT
                    : (
                        $originType === 'admin'
                            ? LLAMA_CONTRIBUTION_LEVEL_ADMIN
                            : LLAMA_CONTRIBUTION_LEVEL_COMMUNITY
                    );
            }

            $originReportUrl = '';
            $originSubmissionId = (int) (
                $historyProvenance['original_submission_id']
                ?? 0
            );

            if ($originSubmissionId > 0) {
                $originReportUrl = llama_historical_report_url(
                    (string) $place['slug'],
                    'submission-' . $originSubmissionId
                );
            }
            ?>

            <div class="place-history-origin">
                <div class="place-history-badge">
                    <i aria-hidden="true"><?= llama_icon(llama_contribution_level_icon($originLevel)) ?></i>
                    <div>
                        <span><?= place_h(llama_contribution_level_label($originLevel)) ?></span>
                        <strong>This Place began with this contribution.</strong>
                    </div>
                </div>

                <?php if ($originName !== ''): ?>
                    <p>
                        Original contributor:
                        <?php if (!empty($historyProvenance['contributor_username'])): ?>
                            <a href="/<?= rawurlencode((string) $historyProvenance['contributor_username']) ?>">
                                <?= place_h($originName) ?>
                            </a>
                        <?php else: ?>
                            <?= place_h($originName) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ($originReportUrl !== ''): ?>
                    <a class="place-history-report-link" href="<?= place_h($originReportUrl) ?>">
                        <i aria-hidden="true"><?= llama_icon('history') ?></i>
                        View original report
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($recentPlaceActivity): ?>
            <div class="place-activity-list">
                <?php foreach ($recentPlaceActivity as $activity): ?>
                    <?php
                    $activityName = trim((string) (
                        $activity['display_name']
                        ?: $activity['username']
                        ?: 'Llama Scout member'
                    ));
                    $activityType = ucwords(str_replace(
                        ['_', '-'],
                        ' ',
                        (string) $activity['contribution_type']
                    ));
                    $activityLevel = llama_contribution_level_for_record(
                        $db,
                        $activity
                    );

                    $activityReportKey = '';
                    $activitySubmissionId = (int) (
                        $activity['submission_id']
                        ?? 0
                    );
                    $activityUpdateId = (int) (
                        $activity['update_submission_id']
                        ?? 0
                    );

                    if (
                        (string) $activity['contribution_type'] === 'new_place'
                        && $activitySubmissionId > 0
                    ) {
                        $activityReportKey =
                            'submission-' . $activitySubmissionId;
                    } elseif ($activityUpdateId > 0) {
                        $activityReportKey =
                            'update-' . $activityUpdateId;
                    }

                    $activityReportUrl =
                        $activityReportKey !== ''
                            ? llama_historical_report_url(
                                (string) $place['slug'],
                                $activityReportKey
                            )
                            : '';
                    ?>
                    <article class="place-activity-item">
                        <div class="place-activity-icon">
                            <i aria-hidden="true"><?= llama_icon('check') ?></i>
                        </div>

                        <div class="place-activity-copy">
                            <div class="place-activity-title-row">
                                <strong>
                                    <?php if (!empty($activity['username'])): ?>
                                        <a href="/<?= rawurlencode((string) $activity['username']) ?>">
                                            <?= place_h($activityName) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= place_h($activityName) ?>
                                    <?php endif; ?>
                                </strong>

                                <span class="place-activity-level">
                                    <?= place_h(llama_contribution_level_short_label($activityLevel)) ?>
                                </span>
                            </div>

                            <span>
                                <?= place_h($activityType) ?>
                                <?php if (!empty($activity['visited_at'])): ?>
                                    / visited <?= place_h(
                                        llama_format_viewer_date(
                                            (string) $activity['visited_at'],
                                            'M j, Y'
                                        )
                                    ) ?>
                                <?php elseif (!empty($activity['approved_at'])): ?>
                                    / <?= place_h(
                                        llama_format_viewer_date(
                                            (string) $activity['approved_at'],
                                            'M j, Y'
                                        )
                                    ) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="place-activity-actions">
                            <?php if ((int) ($activity['points_awarded'] ?? 0) > 0): ?>
                                <span class="place-activity-points">+<?= (int) $activity['points_awarded'] ?></span>
                            <?php endif; ?>

                            <?php if ($activityReportUrl !== ''): ?>
                                <a class="place-history-report-link is-compact" href="<?= place_h($activityReportUrl) ?>">
                                    View report
                                    <i aria-hidden="true"><?= llama_icon('arrow-right') ?></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$historyProvenance): ?>
            <p class="place-history-empty">No approved Place history is available yet.</p>
        <?php endif; ?>

    </div>
</section>
