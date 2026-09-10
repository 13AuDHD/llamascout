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

$actorUserId =
    (int) ($adminUser['id'] ?? 0);

$stats =
    admin_dashboard_stats($db);

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

    $local =
        DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $value,
            new DateTimeZone(
                llama_viewer_timezone()
            )
        );

    if (!$local) {
        throw new InvalidArgumentException(
            'Enter a valid date and time.'
        );
    }

    return
        $local
            ->setTimezone(
                new DateTimeZone('UTC')
            )
            ->format(
                'Y-m-d H:i:s'
            );
}


function email_campaign_utc_to_input(
    ?string $value
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )
            ->setTimezone(
                new DateTimeZone(
                    llama_viewer_timezone()
                )
            )
            ->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}


function email_campaign_table_ready(
    PDO $db
): bool {
    $tableStmt =
        $db->prepare(
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
        'email_sent_at',
        'email_sent_count',
        'reminder_enabled',
        'reminder_send_at',
        'reminder_subject',
        'reminder_body_text',
        'reminder_sent_at',
        'reminder_sent_count',
        'landing_url',
    ];

    $columnStmt =
        $db->prepare(
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


function email_campaign_normalize_landing_url(
    string $value
): string {
    $value = trim($value);

    if ($value === '') {
        return '/membership.php';
    }

    if (
        str_starts_with(
            $value,
            '/'
        )
    ) {
        return $value;
    }

    if (
        !filter_var(
            $value,
            FILTER_VALIDATE_URL
        )
    ) {
        throw new InvalidArgumentException(
            'Landing URL must be a valid full URL or a site path beginning with /.'
        );
    }

    $scheme =
        strtolower(
            (string) parse_url(
                $value,
                PHP_URL_SCHEME
            )
        );

    if (
        !in_array(
            $scheme,
            [
                'http',
                'https',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Landing URL must use http or https.'
        );
    }

    return $value;
}


function email_campaign_test_url(
    string $landingUrl
): string {
    $landingUrl = trim($landingUrl);

    if ($landingUrl === '') {
        return
            'https://llamascout.com/membership.php';
    }

    if (
        str_starts_with(
            $landingUrl,
            '/'
        )
    ) {
        return
            'https://llamascout.com'
            . $landingUrl;
    }

    return $landingUrl;
}


$schemaReady =
    email_campaign_table_ready($db);

$campaignId =
    (int) (
        $_GET['id']
        ?? $_POST['promotion_id']
        ?? 0
    );


if (
    $schemaReady
    && ($_SERVER['REQUEST_METHOD'] ?? '')
        === 'POST'
) {
    if (
        !moderation_verify_csrf(
            (string) (
                $_POST['csrf_token']
                ?? ''
            )
        )
    ) {
        $error =
            'Your session token expired. Reload and try again.';
    } else {
        try {
            $action =
                trim(
                    (string) (
                        $_POST['campaign_email_action']
                        ?? ''
                    )
                );

            $campaignId =
                (int) (
                    $_POST['promotion_id']
                    ?? 0
                );

            if ($campaignId < 1) {
                throw new InvalidArgumentException(
                    'Choose a campaign first.'
                );
            }

            $campaignStmt =
                $db->prepare(
                    'SELECT *
                     FROM membership_promotions
                     WHERE id = ?
                     LIMIT 1'
                );

            $campaignStmt->execute([
                $campaignId,
            ]);

            $campaign =
                $campaignStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!$campaign) {
                throw new InvalidArgumentException(
                    'Membership promotion not found.'
                );
            }

            $emailSubject =
                trim(
                    (string) (
                        $_POST['email_subject']
                        ?? ''
                    )
                );

            $emailPreheader =
                trim(
                    (string) (
                        $_POST['email_preheader']
                        ?? ''
                    )
                );

            $emailBody =
                trim(
                    (string) (
                        $_POST['email_body_text']
                        ?? ''
                    )
                );

            $emailSendAt =
                email_campaign_local_to_utc(
                    (string) (
                        $_POST['email_send_at']
                        ?? ''
                    )
                );

            $emailEnabled =
                !empty(
                    $_POST['email_enabled']
                )
                    ? 1
                    : 0;

            $reminderSubject =
                trim(
                    (string) (
                        $_POST['reminder_subject']
                        ?? ''
                    )
                );

            $reminderBody =
                trim(
                    (string) (
                        $_POST['reminder_body_text']
                        ?? ''
                    )
                );

            $reminderSendAt =
                email_campaign_local_to_utc(
                    (string) (
                        $_POST['reminder_send_at']
                        ?? ''
                    )
                );

            $reminderEnabled =
                !empty(
                    $_POST['reminder_enabled']
                )
                    ? 1
                    : 0;

            $landingUrl =
                email_campaign_normalize_landing_url(
                    (string) (
                        $_POST['landing_url']
                        ?? ''
                    )
                );

            if (
                $emailEnabled
                && (
                    $emailSubject === ''
                    || $emailBody === ''
                    || $emailSendAt === null
                )
            ) {
                throw new InvalidArgumentException(
                    'Enabled announcement email requires a send time, subject, and message.'
                );
            }

            if (
                $reminderEnabled
                && (
                    $reminderSubject === ''
                    || $reminderBody === ''
                    || $reminderSendAt === null
                )
            ) {
                throw new InvalidArgumentException(
                    'Enabled reminder email requires a send time, subject, and message.'
                );
            }

            if (
                $action === 'save'
            ) {
                $update =
                    $db->prepare(
                        'UPDATE membership_promotions
                         SET
                            email_enabled = ?,
                            email_audience = "free_members",
                            email_send_at = ?,
                            email_subject = ?,
                            email_preheader = ?,
                            email_body_text = ?,
                            reminder_enabled = ?,
                            reminder_send_at = ?,
                            reminder_subject = ?,
                            reminder_body_text = ?,
                            landing_url = ?
                         WHERE id = ?'
                    );

                $update->execute([
                    $emailEnabled,
                    $emailSendAt,
                    $emailSubject !== ''
                        ? $emailSubject
                        : null,
                    $emailPreheader !== ''
                        ? $emailPreheader
                        : null,
                    $emailBody !== ''
                        ? $emailBody
                        : null,
                    $reminderEnabled,
                    $reminderSendAt,
                    $reminderSubject !== ''
                        ? $reminderSubject
                        : null,
                    $reminderBody !== ''
                        ? $reminderBody
                        : null,
                    $landingUrl,
                    $campaignId,
                ]);

                if (
                    function_exists(
                        'llama_membership_audit'
                    )
                ) {
                    llama_membership_audit(
                        $db,
                        $actorUserId,
                        'membership_promotion_email_updated',
                        'membership_promotion',
                        $campaignId,
                        [
                            'email_enabled' =>
                                $emailEnabled,
                            'email_send_at' =>
                                $emailSendAt,
                            'reminder_enabled' =>
                                $reminderEnabled,
                            'reminder_send_at' =>
                                $reminderSendAt,
                        ]
                    );
                }

                $notice =
                    'Campaign email settings saved.';
            } elseif (
                in_array(
                    $action,
                    [
                        'test-announcement',
                        'test-reminder',
                    ],
                    true
                )
            ) {
                $isReminder =
                    $action
                    === 'test-reminder';

                $subject =
                    $isReminder
                        ? $reminderSubject
                        : $emailSubject;

                $body =
                    $isReminder
                        ? $reminderBody
                        : $emailBody;

                if (
                    $subject === ''
                    || $body === ''
                ) {
                    throw new InvalidArgumentException(
                        'Add a subject and message before sending a test.'
                    );
                }

                $promotionUrl =
                    email_campaign_test_url(
                        $landingUrl
                    );

                $unsubscribeUrl =
                    'https://account.llamascout.com/email-preferences.php?token=TEST';

                $text =
                    llama_promotion_email_text(
                        $body,
                        $promotionUrl,
                        $unsubscribeUrl
                    );

                $html =
                    llama_promotion_email_html(
                        'Trail Tester',
                        $body,
                        $promotionUrl,
                        $unsubscribeUrl
                    );

                $sent =
                    send_llama_mail(
                        'dev@llamascout.com',
                        '[TEST] ' . $subject,
                        $text,
                        $html
                    );

                llama_email_log_send(
                    $db,
                    $isReminder
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

                $notice =
                    $isReminder
                        ? 'Reminder test sent to dev@llamascout.com.'
                        : 'Announcement test sent to dev@llamascout.com.';
            }
        } catch (Throwable $exception) {
            $reference =
                llama_log_caught_exception(
                    $exception,
                    'admin.email_campaigns',
                    [
                        'promotion_id' =>
                            $campaignId,
                    ],
                    [
                        InvalidArgumentException::class,
                    ]
                );

            $error =
                $reference === null
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
        $campaigns =
            $db
                ->query(
                    'SELECT
                        id,
                        name,
                        public_label,
                        public_description,
                        starts_at,
                        ends_at,
                        is_enabled,
                        landing_url,
                        email_enabled,
                        email_audience,
                        email_send_at,
                        email_subject,
                        email_preheader,
                        email_body_text,
                        email_sent_at,
                        email_sent_count,
                        reminder_enabled,
                        reminder_send_at,
                        reminder_subject,
                        reminder_body_text,
                        reminder_sent_at,
                        reminder_sent_count,
                        created_at
                     FROM membership_promotions
                     ORDER BY starts_at DESC, id DESC'
                )
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: [];
    } catch (Throwable $exception) {
        $reference =
            llama_log_caught_exception(
                $exception,
                'admin.email_campaigns_load'
            );

        $error =
            llama_error_message_with_reference(
                'Campaign emails could not be loaded.',
                $reference
            );
    }
}


if (
    $campaignId < 1
    && $campaigns
) {
    $campaignId =
        (int) $campaigns[0]['id'];
}


$selectedCampaign = null;

foreach ($campaigns as $campaign) {
    if (
        (int) $campaign['id']
        === $campaignId
    ) {
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
        Membership campaign email storage is not available yet.
        Install the existing Membership Campaign database upgrade first.
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
