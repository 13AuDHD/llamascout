<?php if (!$selectedCampaign): ?>

    <section class="admin-panel email-campaign-empty">
        <i aria-hidden="true"><?= llama_icon('speakerphone') ?></i>
        <h2>Select a promotion</h2>
        <p>
            Choose a membership promotion to edit its Campaign Email
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

    $emailSubjectValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['email_subject'] ?? '')
            : ($selectedCampaign['email_subject'] ?? '')
    ));

    if ($emailSubjectValue === '') {
        $emailSubjectValue =
            (string) (
                $selectedCampaign['public_label']
                ?? $selectedCampaign['name']
                ?? 'Membership sale'
            )
            . ' at Llama Scout';
    }

    $emailPreheaderValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['email_preheader'] ?? '')
            : ($selectedCampaign['email_preheader'] ?? '')
    ));

    if ($emailPreheaderValue === '') {
        $emailPreheaderValue =
            '{{annual_offer}} · {{monthly_offer}}';
    }

    $emailTextValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['email_body_text'] ?? '')
            : ($selectedCampaign['email_body_text'] ?? '')
    ));

    if ($emailTextValue === '') {
        $emailTextValue = email_campaign_default_text('campaign');
    }

    $emailHtmlValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['email_body_html'] ?? '')
            : ($selectedCampaign['email_body_html'] ?? '')
    ));

    if ($emailHtmlValue === '') {
        $storedEmailText = trim((string) (
            $selectedCampaign['email_body_text']
            ?? ''
        ));

        $emailHtmlValue = $storedEmailText !== ''
            ? llama_promotion_plain_text_to_html($emailTextValue)
            : email_campaign_default_html('campaign');
    }

    $reminderSubjectValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['reminder_subject'] ?? '')
            : ($selectedCampaign['reminder_subject'] ?? '')
    ));

    if ($reminderSubjectValue === '') {
        $reminderSubjectValue =
            'Last chance: '
            . (string) (
                $selectedCampaign['public_label']
                ?? $selectedCampaign['name']
                ?? 'Llama Scout membership sale'
            );
    }

    $reminderPreheaderValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['reminder_preheader'] ?? '')
            : ($selectedCampaign['reminder_preheader'] ?? '')
    ));

    if ($reminderPreheaderValue === '') {
        $reminderPreheaderValue =
            'The sale ends {{ends_at}}.';
    }

    $reminderTextValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['reminder_body_text'] ?? '')
            : ($selectedCampaign['reminder_body_text'] ?? '')
    ));

    if ($reminderTextValue === '') {
        $reminderTextValue = email_campaign_default_text('reminder');
    }

    $reminderHtmlValue = trim((string) (
        $isPostForCampaign
            ? ($_POST['reminder_body_html'] ?? '')
            : ($selectedCampaign['reminder_body_html'] ?? '')
    ));

    if ($reminderHtmlValue === '') {
        $storedReminderText = trim((string) (
            $selectedCampaign['reminder_body_text']
            ?? ''
        ));

        $reminderHtmlValue = $storedReminderText !== ''
            ? llama_promotion_plain_text_to_html($reminderTextValue)
            : email_campaign_default_html('reminder');
    }

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

    $previewCampaign = array_merge(
        $selectedCampaign,
        [
            'email_subject' => $emailSubjectValue,
            'email_preheader' => $emailPreheaderValue,
            'email_body_html' => $emailHtmlValue,
            'email_body_text' => $emailTextValue,
            'reminder_subject' => $reminderSubjectValue,
            'reminder_preheader' => $reminderPreheaderValue,
            'reminder_body_html' => $reminderHtmlValue,
            'reminder_body_text' => $reminderTextValue,
        ]
    );

    $campaignPreview = llama_promotion_render_email(
        $db,
        $previewCampaign,
        'announcement',
        $sampleContext
    );

    $reminderPreview = llama_promotion_render_email(
        $db,
        $previewCampaign,
        'reminder',
        $sampleContext
    );

    $promotionUrl = llama_promotion_email_url($selectedCampaign);
    $variables = llama_promotion_email_variable_names();
    ?>

    <section class="admin-panel email-campaign-context">
        <header class="admin-panel-header">
            <div>
                <p>Sale variables</p>
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

        <div class="email-campaign-variable-box">
            <strong>Available variables</strong>
            <p>
                Values come from this sale automatically, so the saved email
                stays connected to its current prices and dates.
            </p>
            <div>
                <?php foreach ($variables as $variable): ?>
                    <code>{{<?= moderation_e((string) $variable) ?>}}</code>
                <?php endforeach; ?>
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
                            Sends to eligible verified free members who allow
                            promotional email and do not already have member access.
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

                <label>
                    <span>Subject</span>
                    <input
                        type="text"
                        name="email_subject"
                        maxlength="190"
                        value="<?= moderation_e($emailSubjectValue) ?>"
                    >
                </label>

                <label>
                    <span>Preview text</span>
                    <input
                        type="text"
                        name="email_preheader"
                        maxlength="255"
                        value="<?= moderation_e($emailPreheaderValue) ?>"
                    >
                </label>

                <label>
                    <span>HTML body</span>
                    <textarea
                        name="email_body_html"
                        rows="18"
                        spellcheck="false"
                    ><?= moderation_e($emailHtmlValue) ?></textarea>
                </label>

                <label>
                    <span>Plain-text fallback</span>
                    <textarea
                        name="email_body_text"
                        rows="10"
                    ><?= moderation_e($emailTextValue) ?></textarea>
                </label>

                <div class="email-campaign-message-actions">
                    <button
                        class="admin-button"
                        type="submit"
                        name="campaign_email_action"
                        value="test-campaign"
                    >
                        <i aria-hidden="true"><?= llama_icon('send') ?></i>
                        Test Campaign Email
                    </button>
                    <span>Sends only to dev@llamascout.com</span>
                </div>

                <?php if ($announcementSent): ?>
                    <p class="email-campaign-sent-note">
                        This Campaign Email has already been sent. Editing it does
                        not automatically send it again.
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel email-campaign-preview-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Campaign Email preview</p>
                    <h2><?= moderation_e($campaignPreview['subject']) ?></h2>
                </div>
                <span>Sample member data</span>
            </header>

            <iframe
                class="email-campaign-preview-frame"
                title="Campaign Email preview"
                sandbox
                srcdoc="<?= moderation_e($campaignPreview['html']) ?>"
            ></iframe>
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
                            Uses the same promotional audience and tracks
                            reminder delivery separately.
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

                <label>
                    <span>Subject</span>
                    <input
                        type="text"
                        name="reminder_subject"
                        maxlength="190"
                        value="<?= moderation_e($reminderSubjectValue) ?>"
                    >
                </label>

                <label>
                    <span>Preview text</span>
                    <input
                        type="text"
                        name="reminder_preheader"
                        maxlength="255"
                        value="<?= moderation_e($reminderPreheaderValue) ?>"
                    >
                </label>

                <label>
                    <span>HTML body</span>
                    <textarea
                        name="reminder_body_html"
                        rows="18"
                        spellcheck="false"
                    ><?= moderation_e($reminderHtmlValue) ?></textarea>
                </label>

                <label>
                    <span>Plain-text fallback</span>
                    <textarea
                        name="reminder_body_text"
                        rows="10"
                    ><?= moderation_e($reminderTextValue) ?></textarea>
                </label>

                <div class="email-campaign-message-actions">
                    <button
                        class="admin-button"
                        type="submit"
                        name="campaign_email_action"
                        value="test-reminder"
                    >
                        <i aria-hidden="true"><?= llama_icon('send') ?></i>
                        Test Final Reminder
                    </button>
                    <span>Sends only to dev@llamascout.com</span>
                </div>

                <?php if ($reminderSent): ?>
                    <p class="email-campaign-sent-note">
                        This Final Reminder has already been sent. Editing it does
                        not automatically send it again.
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel email-campaign-preview-panel">
            <header class="admin-panel-header">
                <div>
                    <p>Final Reminder preview</p>
                    <h2><?= moderation_e($reminderPreview['subject']) ?></h2>
                </div>
                <span>Sample member data</span>
            </header>

            <iframe
                class="email-campaign-preview-frame"
                title="Final Reminder preview"
                sandbox
                srcdoc="<?= moderation_e($reminderPreview['html']) ?>"
            ></iframe>
        </section>

        <div class="email-campaign-save-bar">
            <div>
                <strong>Campaign messages</strong>
                <span>
                    Sale pricing and website promotion settings remain under
                    Pricing &amp; Promotions.
                </span>
            </div>

            <button
                class="admin-button"
                type="submit"
                name="campaign_email_action"
                value="save"
            >
                <i aria-hidden="true"><?= llama_icon('device-floppy') ?></i>
                Save Campaign Emails
            </button>
        </div>
    </form>

<?php endif; ?>
