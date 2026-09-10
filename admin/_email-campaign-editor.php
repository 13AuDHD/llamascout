<?php if (!$selectedCampaign): ?>

    <section class="admin-panel email-campaign-empty">
        <i
            class="fa-solid fa-bullhorn"
            aria-hidden="true"
        ></i>

        <h2>Select a promotion</h2>

        <p>
            Choose a membership promotion to manage its announcement
            and final reminder emails.
        </p>
    </section>

<?php else: ?>

    <?php
    $announcementSent =
        !empty(
            $selectedCampaign['email_sent_at']
        );

    $reminderSent =
        !empty(
            $selectedCampaign['reminder_sent_at']
        );

    $emailEnabledValue =
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            ? !empty($_POST['email_enabled'])
            : !empty($selectedCampaign['email_enabled']);

    $reminderEnabledValue =
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            ? !empty($_POST['reminder_enabled'])
            : !empty($selectedCampaign['reminder_enabled']);

    $emailSubjectValue =
        (string) (
            $_POST['email_subject']
            ?? $selectedCampaign['email_subject']
            ?? ''
        );

    $emailPreheaderValue =
        (string) (
            $_POST['email_preheader']
            ?? $selectedCampaign['email_preheader']
            ?? ''
        );

    $emailBodyValue =
        (string) (
            $_POST['email_body_text']
            ?? $selectedCampaign['email_body_text']
            ?? ''
        );

    $emailSendAtValue =
        isset($_POST['email_send_at'])
            ? (string) $_POST['email_send_at']
            : email_campaign_utc_to_input(
                $selectedCampaign['email_send_at']
                ?? null
            );

    $reminderSubjectValue =
        (string) (
            $_POST['reminder_subject']
            ?? $selectedCampaign['reminder_subject']
            ?? ''
        );

    $reminderBodyValue =
        (string) (
            $_POST['reminder_body_text']
            ?? $selectedCampaign['reminder_body_text']
            ?? ''
        );

    $reminderSendAtValue =
        isset($_POST['reminder_send_at'])
            ? (string) $_POST['reminder_send_at']
            : email_campaign_utc_to_input(
                $selectedCampaign['reminder_send_at']
                ?? null
            );

    $landingUrlValue =
        (string) (
            $_POST['landing_url']
            ?? $selectedCampaign['landing_url']
            ?? '/membership.php'
        );
    ?>

    <section class="admin-panel email-campaign-overview">

        <header class="admin-panel-header">
            <div>
                <p>Campaign</p>
                <h2>
                    <?= moderation_e(
                        (string) $selectedCampaign['name']
                    ) ?>
                </h2>
            </div>

            <a
                class="admin-button is-secondary"
                href="/memberships.php"
            >
                <i
                    class="fa-solid fa-tags"
                    aria-hidden="true"
                ></i>

                Pricing + Promotion Rules
            </a>
        </header>

        <div class="email-campaign-overview-grid">

            <div>
                <span>Promotion starts</span>
                <strong>
                    <?= !empty($selectedCampaign['starts_at'])
                        ? moderation_e(
                            llama_format_viewer_datetime(
                                (string) $selectedCampaign['starts_at']
                            )
                        )
                        : 'Not set' ?>
                </strong>
            </div>

            <div>
                <span>Promotion ends</span>
                <strong>
                    <?= !empty($selectedCampaign['ends_at'])
                        ? moderation_e(
                            llama_format_viewer_datetime(
                                (string) $selectedCampaign['ends_at']
                            )
                        )
                        : 'Not set' ?>
                </strong>
            </div>

            <div>
                <span>Audience</span>
                <strong>Eligible free members</strong>
            </div>

            <div>
                <span>Promotion status</span>
                <strong>
                    <?= !empty($selectedCampaign['is_enabled'])
                        ? 'Enabled'
                        : 'Disabled' ?>
                </strong>
            </div>

        </div>

    </section>


    <form
        method="post"
        class="email-campaign-form"
    >

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
                    <p>First Message</p>
                    <h2>Announcement Email</h2>
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
                            (int) (
                                $selectedCampaign['email_sent_count']
                                ?? 0
                            )
                        ) ?>
                        recipients
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
                        <strong>Schedule announcement email</strong>
                        <small>
                            Sends only to verified free members who still allow
                            promotional email and do not already have member access.
                        </small>
                    </span>
                </label>

                <div class="email-campaign-field-grid">

                    <label>
                        <span>Send date + time</span>

                        <input
                            type="datetime-local"
                            name="email_send_at"
                            value="<?= moderation_e($emailSendAtValue) ?>"
                        >
                    </label>

                    <label>
                        <span>Landing page</span>

                        <input
                            type="text"
                            name="landing_url"
                            value="<?= moderation_e($landingUrlValue) ?>"
                            placeholder="/membership.php"
                        >
                    </label>

                </div>

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
                    <span>Message</span>

                    <textarea
                        name="email_body_text"
                        rows="12"
                    ><?= moderation_e($emailBodyValue) ?></textarea>
                </label>

                <div class="email-campaign-message-actions">

                    <button
                        class="admin-button"
                        type="submit"
                        name="campaign_email_action"
                        value="test-announcement"
                    >
                        <i
                            class="fa-solid fa-paper-plane"
                            aria-hidden="true"
                        ></i>

                        Test Announcement
                    </button>

                    <span>
                        Sends only to dev@llamascout.com
                    </span>

                </div>

                <?php if ($announcementSent): ?>
                    <p class="email-campaign-sent-note">
                        This announcement has already been sent. Editing its content
                        does not automatically send it again.
                    </p>
                <?php endif; ?>

            </div>

        </section>


        <section class="admin-panel">

            <header class="admin-panel-header">
                <div>
                    <p>Second Message</p>
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
                            (int) (
                                $selectedCampaign['reminder_sent_count']
                                ?? 0
                            )
                        ) ?>
                        recipients
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
                        <strong>Schedule final reminder</strong>
                        <small>
                            Uses the same eligible free-member audience, but tracks
                            reminder delivery separately from the announcement.
                        </small>
                    </span>
                </label>

                <label class="email-campaign-date-field">
                    <span>Send date + time</span>

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
                    <span>Message</span>

                    <textarea
                        name="reminder_body_text"
                        rows="10"
                    ><?= moderation_e($reminderBodyValue) ?></textarea>
                </label>

                <div class="email-campaign-message-actions">

                    <button
                        class="admin-button"
                        type="submit"
                        name="campaign_email_action"
                        value="test-reminder"
                    >
                        <i
                            class="fa-solid fa-paper-plane"
                            aria-hidden="true"
                        ></i>

                        Test Reminder
                    </button>

                    <span>
                        Sends only to dev@llamascout.com
                    </span>

                </div>

                <?php if ($reminderSent): ?>
                    <p class="email-campaign-sent-note">
                        This reminder has already been sent. Editing its content
                        does not automatically send it again.
                    </p>
                <?php endif; ?>

            </div>

        </section>


        <div class="email-campaign-save-bar">

            <div>
                <strong>Campaign email settings</strong>
                <span>
                    Scheduling is handled by Llama Scout’s existing promotion
                    delivery system.
                </span>
            </div>

            <button
                class="admin-button"
                type="submit"
                name="campaign_email_action"
                value="save"
            >
                <i
                    class="fa-solid fa-floppy-disk"
                    aria-hidden="true"
                ></i>

                Save Campaign Emails
            </button>

        </div>

    </form>

<?php endif; ?>
