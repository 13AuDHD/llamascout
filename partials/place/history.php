<section class="place-history-section" id="place-history">
    <div class="place-detail-container">

        <div class="place-history-heading">
            <p class="place-detail-eyebrow">Place history</p>
            <h2>Who helped document this Place</h2>
            <p>
                Follow check-ins, updates, resolved problems, and past reports
                to see how this Place has changed over time. The newest activity
                appears first.
            </p>
        </div>

        <?php if ($placeHistoryTimeline): ?>
            <div class="place-history-timeline">
                <?php foreach ($placeHistoryTimeline as $event): ?>
                    <?php
                    $eventLevel = llama_contribution_level_normalize(
                        (string) (
                            $event['contribution_level']
                            ?? LLAMA_CONTRIBUTION_LEVEL_COMMUNITY
                        )
                    );

                    $eventName = trim((string) (
                        $event['display_name']
                        ?: $event['username']
                        ?: ''
                    ));

                    $eventDateSource = trim((string) (
                        $event['date_label_source']
                        ?? $event['occurred_at']
                        ?? ''
                    ));

                    $eventDateLabel = $eventDateSource !== ''
                        ? llama_format_viewer_date(
                            $eventDateSource,
                            'M j, Y'
                        )
                        : '';

                    $visitedAt = trim((string) (
                        $event['visited_at']
                        ?? ''
                    ));

                    $visitedLabel = $visitedAt !== ''
                        ? llama_format_viewer_date(
                            $visitedAt,
                            'M j, Y'
                        )
                        : '';

                    $showVisitedDate =
                        $visitedLabel !== ''
                        && $visitedLabel !== $eventDateLabel
                        && !in_array(
                            (string) ($event['type'] ?? ''),
                            [
                                'checkin',
                                'field_verification',
                            ],
                            true
                        );
                    ?>

                    <article class="place-history-event">
                        <div class="place-history-marker" aria-hidden="true">
                            <i><?= llama_icon((string) ($event['icon'] ?? 'history')) ?></i>
                        </div>

                        <div class="place-history-event-card">
                            <div class="place-history-event-header">
                                <div class="place-history-event-title-block">
                                    <p class="place-history-event-date">
                                        <?= place_h($eventDateLabel) ?>
                                    </p>

                                    <h3>
                                        <?= place_h((string) ($event['title'] ?? 'Place activity')) ?>
                                    </h3>
                                </div>

                                <div class="place-history-event-badges">
                                    <?php if (!empty($event['show_level'])): ?>
                                        <span class="place-history-level is-<?= place_h($eventLevel) ?>">
                                            <i aria-hidden="true"><?= llama_icon(llama_contribution_level_icon($eventLevel)) ?></i>
                                            <?= place_h(llama_contribution_level_short_label($eventLevel)) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($event['status_label'])): ?>
                                        <span class="place-history-status">
                                            <?= place_h((string) $event['status_label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="place-history-event-summary">
                                <?= place_h((string) ($event['summary'] ?? '')) ?>
                            </p>

                            <?php if ($eventName !== '' || $showVisitedDate): ?>
                                <div class="place-history-event-meta">
                                    <?php if ($eventName !== ''): ?>
                                        <span>
                                            <?= place_h((string) ($event['actor_label'] ?? 'By')) ?>:

                                            <?php if (!empty($event['username'])): ?>
                                                <a href="/<?= rawurlencode((string) $event['username']) ?>">
                                                    <?= place_h($eventName) ?>
                                                </a>
                                            <?php else: ?>
                                                <strong><?= place_h($eventName) ?></strong>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($showVisitedDate): ?>
                                        <span>
                                            Visited <?= place_h($visitedLabel) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            $points = max(
                                0,
                                (int) (
                                    $event['points_awarded']
                                    ?? 0
                                )
                            );

                            $reportUrl = trim((string) (
                                $event['report_url']
                                ?? ''
                            ));
                            ?>

                            <?php if ($points > 0 || $reportUrl !== ''): ?>
                                <div class="place-history-event-actions">
                                    <?php if ($points > 0): ?>
                                        <span class="place-history-points">
                                            +<?= $points ?> points
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($reportUrl !== ''): ?>
                                        <a class="place-history-report-link" href="<?= place_h($reportUrl) ?>">
                                            <i aria-hidden="true"><?= llama_icon('history') ?></i>
                                            <?= place_h((string) ($event['report_label'] ?? 'View report')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="place-history-empty">
                No approved Place history is available yet.
            </p>
        <?php endif; ?>

    </div>
</section>
