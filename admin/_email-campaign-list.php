<section class="admin-panel email-campaign-library">

    <header class="admin-panel-header">
        <div>
            <p>Membership Promotions</p>
            <h2>Email Campaigns</h2>
        </div>

        <span>
            <?= count($campaigns) ?> campaigns
        </span>
    </header>

    <?php if (!$campaigns): ?>

        <div class="admin-empty-state">
            <p>
                No membership promotions exist yet.
            </p>

            <a
                class="admin-button"
                href="/memberships.php"
            >
                Create a Promotion
            </a>
        </div>

    <?php else: ?>

        <div class="email-campaign-list">

            <?php foreach ($campaigns as $campaign): ?>
                <?php
                $id =
                    (int) $campaign['id'];

                $isSelected =
                    $id === $campaignId;

                $announcementSent =
                    !empty(
                        $campaign['email_sent_at']
                    );

                $reminderSent =
                    !empty(
                        $campaign['reminder_sent_at']
                    );

                $scheduled =
                    !empty(
                        $campaign['email_enabled']
                    )
                    && !empty(
                        $campaign['email_send_at']
                    )
                    && !$announcementSent;

                $statusLabel =
                    $announcementSent
                        ? 'Sent'
                        : (
                            $scheduled
                                ? 'Scheduled'
                                : 'Draft'
                        );
                ?>

                <a
                    class="email-campaign-row<?= $isSelected ? ' is-active' : '' ?>"
                    href="/email-campaigns.php?id=<?= $id ?>"
                >
                    <span class="email-campaign-row-icon">
                        <i
                            class="fa-solid fa-bullhorn"
                            aria-hidden="true"
                        ></i>
                    </span>

                    <span class="email-campaign-row-copy">
                        <strong>
                            <?= moderation_e(
                                (string) $campaign['name']
                            ) ?>
                        </strong>

                        <small>
                            <?php if (!empty($campaign['starts_at'])): ?>
                                Starts
                                <?= moderation_e(
                                    llama_format_viewer_datetime(
                                        (string) $campaign['starts_at']
                                    )
                                ) ?>
                            <?php else: ?>
                                No start date
                            <?php endif; ?>
                        </small>

                        <?php if ($reminderSent): ?>
                            <small>
                                Reminder sent to
                                <?= number_format(
                                    (int) (
                                        $campaign['reminder_sent_count']
                                        ?? 0
                                    )
                                ) ?>
                            </small>
                        <?php endif; ?>
                    </span>

                    <span
                        class="email-campaign-row-status<?= $announcementSent ? ' is-sent' : ($scheduled ? ' is-scheduled' : '') ?>"
                    >
                        <?= moderation_e($statusLabel) ?>
                    </span>
                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>
