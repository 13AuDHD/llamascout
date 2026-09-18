<section class="place-history-section">
    <div class="place-detail-container">

        <div class="place-history-heading">
            <p class="place-detail-eyebrow">Place history</p>
            <h2>Who helped document this Place</h2>
        </div>

        <?php if ($historyProvenance): ?>
            <?php
            $originType = (string) ($historyProvenance['origin_type'] ?? '');
            $originIsScout = in_array($originType, ['llama-scouted', 'scout', 'admin'], true);
            $originName = trim((string) (
                $historyProvenance['contributor_display_name']
                ?: $historyProvenance['contributor_username']
                ?: ''
            ));
            ?>
            <div class="place-history-origin">
                <div class="place-history-badge<?= $originIsScout ? ' is-scouted' : '' ?>">
                    <i aria-hidden="true"><?= llama_icon($originIsScout ? 'binoculars' : 'users') ?></i>
                    <div>
                        <span><?= $originIsScout ? 'Llama Scouted' : 'Member contributed' ?></span>
                        <strong>
                            <?= $originIsScout
                                ? 'This Place has been documented in the field.'
                                : 'This Place began with a member contribution.'
                            ?>
                        </strong>
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
                    ?>
                    <article class="place-activity-item">
                        <div class="place-activity-icon">
                            <i aria-hidden="true"><?= llama_icon('check') ?></i>
                        </div>

                        <div>
                            <strong>
                                <?php if (!empty($activity['username'])): ?>
                                    <a href="/<?= rawurlencode((string) $activity['username']) ?>">
                                        <?= place_h($activityName) ?>
                                    </a>
                                <?php else: ?>
                                    <?= place_h($activityName) ?>
                                <?php endif; ?>
                            </strong>

                            <span>
                                <?= place_h($activityType) ?>
                                <?php if (!empty($activity['approved_at'])): ?>
                                    / <?= place_h(
                                        llama_format_viewer_date(
                                            (string) $activity['approved_at'],
                                            'M j, Y'
                                        )
                                    ) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ((int) ($activity['points_awarded'] ?? 0) > 0): ?>
                            <span class="place-activity-points">+<?= (int) $activity['points_awarded'] ?></span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$historyProvenance): ?>
            <p class="place-history-empty">No approved Place history is available yet.</p>
        <?php endif; ?>

    </div>
</section>
