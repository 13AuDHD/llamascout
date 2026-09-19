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
        'email_sent_at',
        'email_sent_count',
        'reminder_enabled',
        'reminder_send_at',
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

            if ($action !== 'save') {
                throw new InvalidArgumentException(
                    'Unknown campaign scheduling action.'
                );
            }

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

            $reminderEnabled = !empty($_POST['reminder_enabled']) ? 1 : 0;
            $reminderSendAt = email_campaign_local_to_utc(
                (string) ($_POST['reminder_send_at'] ?? '')
            );

            if ($emailEnabled && !$emailSendAt) {
                throw new InvalidArgumentException(
                    'Scheduled Campaign Email requires a send date and time.'
                );
            }

            if ($reminderEnabled && !$reminderSendAt) {
                throw new InvalidArgumentException(
                    'Scheduled Final Reminder requires a send date and time.'
                );
            }

            $campaignTemplate = llama_email_template(
                $db,
                'promotion_campaign_announcement'
            );
            $reminderTemplate = llama_email_template(
                $db,
                'promotion_campaign_reminder'
            );

            if (
                $emailEnabled
                && (
                    !$campaignTemplate
                    || empty($campaignTemplate['enabled'])
                )
            ) {
                throw new InvalidArgumentException(
                    'Campaign Email is disabled under Communications > Emails. Enable it before scheduling.'
                );
            }

            if (
                $reminderEnabled
                && (
                    !$reminderTemplate
                    || empty($reminderTemplate['enabled'])
                )
            ) {
                throw new InvalidArgumentException(
                    'Final Reminder is disabled under Communications > Emails. Enable it before scheduling.'
                );
            }

            $update = $db->prepare(
                'UPDATE membership_promotions
                 SET
                    email_enabled = ?,
                    email_audience = "free_members",
                    email_send_at = ?,
                    reminder_enabled = ?,
                    reminder_send_at = ?
                 WHERE id = ?'
            );

            $update->execute([
                $emailEnabled,
                $emailSendAt,
                $reminderEnabled,
                $reminderSendAt,
                $campaignId,
            ]);

            if (function_exists('llama_membership_audit')) {
                llama_membership_audit(
                    $db,
                    $actorUserId,
                    'membership_promotion_email_schedule_updated',
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

            $notice = 'Campaign schedule saved.';
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
                    'The campaign schedule could not be updated.',
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
            'Campaign schedules could not be loaded.',
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
        Campaign scheduling storage is not available yet.
        Run the Pricing &amp; Promotions email database upgrade first.
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
