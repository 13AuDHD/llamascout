<?php if (!$selectedCampaign): ?>

    <section class="admin-panel email-campaign-empty">
        <i aria-hidden="true"><?= llama_icon('speakerphone') ?></i>
        <h2>Select a promotion</h2>
        <p>
            Choose a membership promotion to schedule its Campaign Email
            and Final Reminder.
        </p>
    </section>

<?php else: ?>

    <?php
    $announcementSent = !empty($selectedCampaign['email_sent_at']);
    $reminderSent = !empty($selectedCampaign['reminder_sent_at']);

    $isPostForCampaign =
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && (int) ($_POST['promotion_id'] ?? 0)
            === (int) $selectedCampaign['id'];

    $emailEnabledValue = $isPostForCampaign
        ? !empty($_POST['email_enabled'])
        : !empty($selectedCampaign['email_enabled']);

    $reminderEnabledValue = $isPostForCampaign
        ? !empty($_POST['reminder_enabled'])
        : !empty($selectedCampaign['reminder_enabled']);

    $emailSendAtValue = $isPostForCampaign
        ? (string) ($_POST['email_send_at'] ?? '')
        : email_campaign_utc_to_input(
            $selectedCampaign['email_send_at'] ?? null
        );

    $reminderSendAtValue = $isPostForCampaign
        ? (string) ($_POST['reminder_send_at'] ?? '')
        : email_campaign_utc_to_input(
            $selectedCampaign['reminder_send_at'] ?? null
        );

    $sampleContext = llama_promotion_email_sample_context(
        $db,
        $selectedCampaign
    );

    $campaignTemplate = llama_email_template(
        $db,
        'promotion_campaign_announcement'
    );

    $reminderTemplate = llama_email_template(
        $db,
        'promotion_campaign_reminder'
    );

    $promotionUrl = llama_promotion_email_url($selectedCampaign);
    ?>

    <section class="admin-panel email-campaign-context">
        <header class="admin-panel-header">
            <div>
                <p>Campaign schedule</p>
                <h2><?= moderation_e((string) $selectedCampaign['name']) ?></h2>
            </div>

            <a
                class="admin-button"
                href="/memberships.php?edit=<?= (int) $selectedCampaign['id'] ?>"
            >
                Edit sale
            </a>
        </header>

        <div class="email-campaign-overview-grid">
            <div>
                <span>Starts</span>
                <strong><?= moderation_e((string) $sampleContext['starts_at']) ?></strong>
            </div>
            <div>
                <span>Ends</span>
                <strong><?= moderation_e((string) $sampleContext['ends_at']) ?></strong>
            </div>
            <div>
                <span>Annual offer</span>
                <strong><?= moderation_e((string) $sampleContext['annual_offer']) ?></strong>
            </div>
            <div>
                <span>Monthly offer</span>
                <strong><?= moderation_e((string) $sampleContext['monthly_offer']) ?></strong>
            </div>
        </div>

        <div class="email-campaign-link-row">
            <span>Membership link</span>
            <a href="<?= moderation_e($promotionUrl) ?>" target="_blank" rel="noopener">
                <?= moderation_e($promotionUrl) ?>
            </a>
        </div>

        <div class="email-campaign-template-note">
            <i aria-hidden="true"><?= llama_icon('mail') ?></i>
            <div>
                <strong>Email content is managed in Communications &gt; Emails.</strong>
                <span>
                    This page only controls whether each message is scheduled and when it sends.
                    Sale prices, dates, labels, and links are filled into the email variables automatically.
                </span>
            </div>
        </div>
    </section>

    <form method="post" class="email-campaign-form">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= moderation_e(moderation_csrf_token()) ?>"
        >
        <input
            type="hidden"
            name="promotion_id"
            value="<?= (int) $selectedCampaign['id'] ?>"
        >

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>First message</p>
                    <h2>Campaign Email</h2>
                </div>

                <?php if ($announcementSent): ?>
                    <span>
                        Sent
                        <?= moderation_e(
                            llama_format_viewer_datetime(
                                (string) $selectedCampaign['email_sent_at']
                            )
                        ) ?>
                        ·
                        <?= number_format(
                            (int) ($selectedCampaign['email_sent_count'] ?? 0)
                        ) ?> recipients
                    </span>
                <?php endif; ?>
            </header>

            <div class="email-campaign-form-body">
                <div class="email-campaign-template-summary">
                    <div>
                        <span>Email template</span>
                        <strong>
                            <?= moderation_e(
                                (string) (
                                    $campaignTemplate['subject']
                                    ?? 'Campaign Email'
                                )
                            ) ?>
                        </strong>
                        <small>
                            <?= !empty($campaignTemplate['enabled']) ? 'Enabled' : 'Disabled' ?>
                        </small>
                    </div>

                    <a
                        class="admin-button is-secondary"
                        href="/emails.php?template=promotion_campaign_announcement"
                    >
                        Edit Email
                    </a>
                </div>

                <?php if (empty($campaignTemplate['enabled'])): ?>
                    <p class="email-campaign-sent-note">
                        Campaign Email is currently disabled in the Email Center.
                        Enable it there before scheduling this message.
                    </p>
                <?php endif; ?>

                <label class="email-campaign-toggle">
                    <input
                        type="checkbox"
                        name="email_enabled"
                        value="1"
                        <?= $emailEnabledValue ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Schedule Campaign Email</strong>
                        <small>
                            Sends the Campaign Email template to eligible verified
                            free members who allow promotional email and do not already
                            have member access.
                        </small>
                    </span>
                </label>

                <label class="email-campaign-date-field">
                    <span>Send date + time, <?= moderation_e($viewerTimezoneLabel) ?></span>
                    <input
                        type="datetime-local"
                        name="email_send_at"
                        value="<?= moderation_e($emailSendAtValue) ?>"
                    >
                </label>

                <?php if ($announcementSent): ?>
                    <p class="email-campaign-sent-note">
                        This Campaign Email has already been sent. Changing its schedule
                        does not automatically send it again.
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Second message</p>
                    <h2>Final Reminder</h2>
                </div>

                <?php if ($reminderSent): ?>
                    <span>
                        Sent
                        <?= moderation_e(
                            llama_format_viewer_datetime(
                                (string) $selectedCampaign['reminder_sent_at']
                            )
                        ) ?>
                        ·
                        <?= number_format(
                            (int) ($selectedCampaign['reminder_sent_count'] ?? 0)
                        ) ?> recipients
                    </span>
                <?php endif; ?>
            </header>

            <div class="email-campaign-form-body">
                <div class="email-campaign-template-summary">
                    <div>
                        <span>Email template</span>
                        <strong>
                            <?= moderation_e(
                                (string) (
                                    $reminderTemplate['subject']
                                    ?? 'Final Reminder'
                                )
                            ) ?>
                        </strong>
                        <small>
                            <?= !empty($reminderTemplate['enabled']) ? 'Enabled' : 'Disabled' ?>
                        </small>
                    </div>

                    <a
                        class="admin-button is-secondary"
                        href="/emails.php?template=promotion_campaign_reminder"
                    >
                        Edit Email
                    </a>
                </div>

                <?php if (empty($reminderTemplate['enabled'])): ?>
                    <p class="email-campaign-sent-note">
                        Final Reminder is currently disabled in the Email Center.
                        Enable it there before scheduling this message.
                    </p>
                <?php endif; ?>

                <label class="email-campaign-toggle">
                    <input
                        type="checkbox"
                        name="reminder_enabled"
                        value="1"
                        <?= $reminderEnabledValue ? 'checked' : '' ?>
                    >
                    <span>
                        <strong>Schedule Final Reminder</strong>
                        <small>
                            Uses the same promotional audience and records reminder
                            delivery separately in campaign history and Email Activity.
                        </small>
                    </span>
                </label>

                <label class="email-campaign-date-field">
                    <span>Send date + time, <?= moderation_e($viewerTimezoneLabel) ?></span>
                    <input
                        type="datetime-local"
                        name="reminder_send_at"
                        value="<?= moderation_e($reminderSendAtValue) ?>"
                    >
                </label>

                <?php if ($reminderSent): ?>
                    <p class="email-campaign-sent-note">
                        This Final Reminder has already been sent. Changing its schedule
                        does not automatically send it again.
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <div class="email-campaign-save-bar">
            <div>
                <strong>Campaign schedule</strong>
                <span>
                    Message content lives under Communications &gt; Emails.
                </span>
            </div>

            <button
                class="admin-button"
                type="submit"
                name="campaign_email_action"
                value="save"
            >
                <i aria-hidden="true"><?= llama_icon('device-floppy') ?></i>
                Save Schedule
            </button>
        </div>
    </form>

<?php endif; ?>
