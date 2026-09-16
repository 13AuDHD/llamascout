<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mail.php';
require_once dirname(__DIR__) . '/app/memberships.php';
require_once dirname(__DIR__) . '/app/promotion-campaigns.php';
require_once dirname(__DIR__) . '/app/timezone.php';
require_once __DIR__ . '/_dashboard.php';

$adminUser = moderation_require_admin();
$db = db();
$actorUserId = (int) ($adminUser['id'] ?? 0);

$stats = admin_dashboard_stats($db);

$adminNavCounts = [
    'new_places' => $stats['new_places'],
    'updates' => $stats['updates'],
    'reports' => $stats['reports'],
    'orders' => $stats['orders'],
    'scout_reviews' => $stats['scout_reviews'],
];

$adminPageTitle = 'Email Campaigns';
$adminPageEyebrow = 'Communications';
$adminActiveNav = 'email-campaigns';

$notice = '';
$error = '';

$viewerTimezone = llama_viewer_timezone();
$timezoneLabels = llama_timezones();
$viewerTimezoneLabel = (string) (
    $timezoneLabels[$viewerTimezone]
    ?? $viewerTimezone
);

function email_campaign_local_to_utc(
    string $value,
    bool $allowBlank = true
): ?string {
    $value = trim($value);

    if ($value === '') {
        if ($allowBlank) {
            return null;
        }

        throw new InvalidArgumentException(
            'A send date and time is required.'
        );
    }

    $local = DateTimeImmutable::createFromFormat(
        'Y-m-d\TH:i',
        $value,
        new DateTimeZone(llama_viewer_timezone())
    );

    if (!$local) {
        throw new InvalidArgumentException(
            'Enter a valid date and time.'
        );
    }

    return $local
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

function email_campaign_utc_to_input(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC')
        ))
            ->setTimezone(
                new DateTimeZone(llama_viewer_timezone())
            )
            ->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function email_campaign_table_ready(PDO $db): bool
{
    $tableStmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = "membership_promotions"
         LIMIT 1'
    );
    $tableStmt->execute();

    if (!$tableStmt->fetchColumn()) {
        return false;
    }

    $required = [
        'email_enabled',
        'email_audience',
        'email_send_at',
        'email_subject',
        'email_preheader',
        'email_body_text',
        'email_body_html',
        'email_sent_at',
        'email_sent_count',
        'reminder_enabled',
        'reminder_send_at',
        'reminder_subject',
        'reminder_preheader',
        'reminder_body_text',
        'reminder_body_html',
        'reminder_sent_at',
        'reminder_sent_count',
        'landing_url',
    ];

    $columnStmt = $db->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = "membership_promotions"
           AND column_name = ?
         LIMIT 1'
    );

    foreach ($required as $column) {
        $columnStmt->execute([$column]);

        if (!$columnStmt->fetchColumn()) {
            return false;
        }
    }

    return true;
}

function email_campaign_default_html(string $type): string
{
    if ($type === 'reminder') {
        return <<<'HTML'
<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;">Last chance: {{campaign_label}}</h1>
<p style="margin:0 0 18px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 18px;line-height:1.65;">{{campaign_description}}</p>
<p style="margin:0 0 10px;line-height:1.65;"><strong>Annual:</strong> {{annual_offer}}</p>
<p style="margin:0 0 22px;line-height:1.65;"><strong>Monthly:</strong> {{monthly_offer}}</p>
<p style="margin:0;">
  <a href="{{promotion_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:bold;">View membership offer</a>
</p>
HTML;
    }

    return <<<'HTML'
<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;">{{campaign_label}}</h1>
<p style="margin:0 0 18px;line-height:1.65;">Hi {{display_name}},</p>
<p style="margin:0 0 18px;line-height:1.65;">{{campaign_description}}</p>
<p style="margin:0 0 10px;line-height:1.65;"><strong>Annual:</strong> {{annual_offer}}</p>
<p style="margin:0 0 22px;line-height:1.65;"><strong>Monthly:</strong> {{monthly_offer}}</p>
<p style="margin:0;">
  <a href="{{promotion_url}}" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:bold;">View membership offer</a>
</p>
HTML;
}

function email_campaign_default_text(string $type): string
{
    if ($type === 'reminder') {
        return "Hi {{display_name}},\n\n"
            . "This is your final reminder for {{campaign_label}}.\n\n"
            . "{{campaign_description}}\n\n"
            . "Annual: {{annual_offer}}\n"
            . "Monthly: {{monthly_offer}}\n\n"
            . "View the membership offer: {{promotion_url}}";
    }

    return "Hi {{display_name}},\n\n"
        . "{{campaign_description}}\n\n"
        . "Annual: {{annual_offer}}\n"
        . "Monthly: {{monthly_offer}}\n\n"
        . "View the membership offer: {{promotion_url}}";
}

$schemaReady = email_campaign_table_ready($db);

$campaignId = (int) (
    $_GET['id']
    ?? $_POST['promotion_id']
    ?? 0
);

if (
    $schemaReady
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Reload and try again.';
    } else {
        try {
            $action = trim((string) ($_POST['campaign_email_action'] ?? ''));
            $campaignId = (int) ($_POST['promotion_id'] ?? 0);

            if ($campaignId < 1) {
                throw new InvalidArgumentException(
                    'Choose a campaign first.'
                );
            }

            $campaignStmt = $db->prepare(
                'SELECT *
                 FROM membership_promotions
                 WHERE id = ?
                 LIMIT 1'
            );
            $campaignStmt->execute([$campaignId]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);

            if (!$campaign) {
                throw new InvalidArgumentException(
                    'Membership promotion not found.'
                );
            }

            $emailEnabled = !empty($_POST['email_enabled']) ? 1 : 0;
            $emailSendAt = email_campaign_local_to_utc(
                (string) ($_POST['email_send_at'] ?? '')
            );
            $emailSubject = trim((string) ($_POST['email_subject'] ?? ''));
            $emailPreheader = trim((string) ($_POST['email_preheader'] ?? ''));
            $emailHtml = trim((string) ($_POST['email_body_html'] ?? ''));
            $emailText = trim((string) ($_POST['email_body_text'] ?? ''));

            $reminderEnabled = !empty($_POST['reminder_enabled']) ? 1 : 0;
            $reminderSendAt = email_campaign_local_to_utc(
                (string) ($_POST['reminder_send_at'] ?? '')
            );
            $reminderSubject = trim((string) ($_POST['reminder_subject'] ?? ''));
            $reminderPreheader = trim((string) ($_POST['reminder_preheader'] ?? ''));
            $reminderHtml = trim((string) ($_POST['reminder_body_html'] ?? ''));
            $reminderText = trim((string) ($_POST['reminder_body_text'] ?? ''));

            if (
                $emailEnabled
                && (
                    !$emailSendAt
                    || $emailSubject === ''
                    || $emailHtml === ''
                    || $emailText === ''
                )
            ) {
                throw new InvalidArgumentException(
                    'Scheduled Campaign Email requires a send time, subject, HTML body, and plain-text fallback.'
                );
            }

            if (
                $reminderEnabled
                && (
                    !$reminderSendAt
                    || $reminderSubject === ''
                    || $reminderHtml === ''
                    || $reminderText === ''
                )
            ) {
                throw new InvalidArgumentException(
                    'Scheduled Final Reminder requires a send time, subject, HTML body, and plain-text fallback.'
                );
            }

            $override = [
                'email_enabled' => $emailEnabled,
                'email_send_at' => $emailSendAt,
                'email_subject' => $emailSubject,
                'email_preheader' => $emailPreheader,
                'email_body_html' => $emailHtml,
                'email_body_text' => $emailText,
                'reminder_enabled' => $reminderEnabled,
                'reminder_send_at' => $reminderSendAt,
                'reminder_subject' => $reminderSubject,
                'reminder_preheader' => $reminderPreheader,
                'reminder_body_html' => $reminderHtml,
                'reminder_body_text' => $reminderText,
            ];

            if ($action === 'save') {
                $update = $db->prepare(
                    'UPDATE membership_promotions
                     SET
                        email_enabled = ?,
                        email_audience = "free_members",
                        email_send_at = ?,
                        email_subject = ?,
                        email_preheader = ?,
                        email_body_html = ?,
                        email_body_text = ?,
                        reminder_enabled = ?,
                        reminder_send_at = ?,
                        reminder_subject = ?,
                        reminder_preheader = ?,
                        reminder_body_html = ?,
                        reminder_body_text = ?
                     WHERE id = ?'
                );

                $update->execute([
                    $emailEnabled,
                    $emailSendAt,
                    $emailSubject !== '' ? $emailSubject : null,
                    $emailPreheader !== '' ? $emailPreheader : null,
                    $emailHtml !== '' ? $emailHtml : null,
                    $emailText !== '' ? $emailText : null,
                    $reminderEnabled,
                    $reminderSendAt,
                    $reminderSubject !== '' ? $reminderSubject : null,
                    $reminderPreheader !== '' ? $reminderPreheader : null,
                    $reminderHtml !== '' ? $reminderHtml : null,
                    $reminderText !== '' ? $reminderText : null,
                    $campaignId,
                ]);

                if (function_exists('llama_membership_audit')) {
                    llama_membership_audit(
                        $db,
                        $actorUserId,
                        'membership_promotion_email_updated',
                        'membership_promotion',
                        $campaignId,
                        [
                            'email_enabled' => $emailEnabled,
                            'email_send_at' => $emailSendAt,
                            'reminder_enabled' => $reminderEnabled,
                            'reminder_send_at' => $reminderSendAt,
                        ]
                    );
                }

                $notice = 'Campaign emails saved.';
            } elseif (in_array(
                $action,
                ['test-campaign', 'test-reminder'],
                true
            )) {
                $deliveryType = $action === 'test-reminder'
                    ? 'reminder'
                    : 'announcement';

                $workingCampaign = array_merge($campaign, $override);
                $context = llama_promotion_email_sample_context(
                    $db,
                    $workingCampaign
                );

                $rendered = llama_promotion_render_email(
                    $db,
                    $workingCampaign,
                    $deliveryType,
                    $context
                );

                $sent = send_llama_mail(
                    'dev@llamascout.com',
                    '[TEST] ' . $rendered['subject'],
                    $rendered['text'],
                    $rendered['html']
                );

                llama_email_log_send(
                    $db,
                    $deliveryType === 'reminder'
                        ? 'promotion_campaign_reminder'
                        : 'promotion_campaign_announcement',
                    'dev@llamascout.com',
                    $sent,
                    true,
                    null
                );

                if (!$sent) {
                    throw new RuntimeException(
                        'The test campaign email could not be sent.'
                    );
                }

                $notice = $deliveryType === 'reminder'
                    ? 'Final Reminder test sent to dev@llamascout.com.'
                    : 'Campaign Email test sent to dev@llamascout.com.';
            }
        } catch (Throwable $exception) {
            $reference = llama_log_caught_exception(
                $exception,
                'admin.email_campaigns',
                [
                    'promotion_id' => $campaignId,
                ],
                [
                    InvalidArgumentException::class,
                ]
            );

            $error = $reference === null
                ? $exception->getMessage()
                : llama_error_message_with_reference(
                    'The campaign email could not be updated.',
                    $reference
                );
        }
    }
}

$campaigns = [];

if ($schemaReady) {
    try {
        $campaigns = $db->query(
            'SELECT *
             FROM membership_promotions
             ORDER BY starts_at DESC, id DESC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        $reference = llama_log_caught_exception(
            $exception,
            'admin.email_campaigns_load'
        );

        $error = llama_error_message_with_reference(
            'Campaign emails could not be loaded.',
            $reference
        );
    }
}

if ($campaignId < 1 && $campaigns) {
    $campaignId = (int) $campaigns[0]['id'];
}

$selectedCampaign = null;

foreach ($campaigns as $campaign) {
    if ((int) $campaign['id'] === $campaignId) {
        $selectedCampaign = $campaign;
        break;
    }
}

require __DIR__ . '/_header.php';
?>

<link
    rel="stylesheet"
    href="https://llamascout.com/css/admin/pages/email-campaigns.css"
>

<?php if ($notice !== ''): ?>
    <div class="admin-user-notice is-success">
        <?= moderation_e($notice) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="admin-user-notice is-error">
        <?= moderation_e($error) ?>
    </div>
<?php endif; ?>

<?php if (!$schemaReady): ?>
    <div class="admin-user-notice is-error">
        Campaign rich-email storage is not available yet.
        Run the Pricing &amp; Promotions rich-email database upgrade first.
    </div>
<?php else: ?>

    <div class="email-campaign-layout">

        <?php require __DIR__ . '/_email-campaign-list.php'; ?>

        <div class="email-campaign-editor-column">
            <?php require __DIR__ . '/_email-campaign-editor.php'; ?>
        </div>

    </div>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
