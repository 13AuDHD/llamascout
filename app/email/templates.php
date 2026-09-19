<?php

declare(strict_types=1);

require_once __DIR__ . '/defaults.php';


function llama_email_table_exists(
    PDO $db,
    string $table
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}


function llama_email_schema_ready(PDO $db): bool
{
    return
        llama_email_table_exists($db, 'email_templates')
        && llama_email_table_exists($db, 'email_send_log')
        && llama_email_table_exists($db, 'email_event_deliveries');
}


function llama_email_seed_defaults(PDO $db): void
{
    if (!llama_email_table_exists($db, 'email_templates')) {
        return;
    }

    $defaults = llama_email_default_templates();

    $stmt = $db->prepare(
        'INSERT INTO email_templates (
            template_key,
            category,
            name,
            description,
            subject,
            preheader,
            text_body,
            html_body,
            is_enabled,
            created_at,
            updated_at
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            template_key = VALUES(template_key)'
    );

    foreach ($defaults as $template) {
        $stmt->execute([
            $template['template_key'],
            $template['category'],
            $template['name'],
            $template['description'],
            $template['subject'],
            $template['preheader'],
            $template['text_body'],
            $template['html_body'],
            (int) $template['enabled'],
        ]);
    }
}


function llama_email_template(
    PDO $db,
    string $templateKey
): ?array {
    $defaults = llama_email_default_templates();

    if (!isset($defaults[$templateKey])) {
        return null;
    }

    $template = $defaults[$templateKey];

    if (!llama_email_table_exists($db, 'email_templates')) {
        return $template;
    }

    $stmt = $db->prepare(
        'SELECT
            template_key,
            category,
            name,
            description,
            subject,
            preheader,
            text_body,
            html_body,
            is_enabled,
            updated_at,
            updated_by
         FROM email_templates
         WHERE template_key = ?
         LIMIT 1'
    );

    $stmt->execute([$templateKey]);

    $stored = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stored) {
        return $template;
    }

    return array_merge(
        $template,
        [
            'category' => (string) $stored['category'],
            'name' => (string) $stored['name'],
            'description' => (string) $stored['description'],
            'subject' => (string) $stored['subject'],
            'preheader' => (string) $stored['preheader'],
            'text_body' => (string) $stored['text_body'],
            'html_body' => (string) $stored['html_body'],
            'enabled' => (int) $stored['is_enabled'],
            'updated_at' => $stored['updated_at'],
            'updated_by' => $stored['updated_by'],
        ]
    );
}


function llama_email_templates(PDO $db): array
{
    $defaults = llama_email_default_templates();
    $rows = [];

    foreach (array_keys($defaults) as $key) {
        $template = llama_email_template($db, $key);

        if ($template) {
            $rows[] = $template;
        }
    }

    usort(
        $rows,
        static function (array $a, array $b): int {
            $categoryCompare =
                strcasecmp(
                    (string) $a['category'],
                    (string) $b['category']
                );

            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }

            return strcasecmp(
                (string) $a['name'],
                (string) $b['name']
            );
        }
    );

    return $rows;
}


function llama_email_save_template(
    PDO $db,
    int $actorUserId,
    string $templateKey,
    array $input
): void {
    $defaults = llama_email_default_templates();

    if (!isset($defaults[$templateKey])) {
        throw new InvalidArgumentException(
            'Unknown email template.'
        );
    }

    if (!llama_email_table_exists($db, 'email_templates')) {
        throw new RuntimeException(
            'Run the Email Center database migration first.'
        );
    }

    $subject = trim((string) ($input['subject'] ?? ''));
    $preheader = trim((string) ($input['preheader'] ?? ''));
    $textBody = trim((string) ($input['text_body'] ?? ''));
    $htmlBody = trim((string) ($input['html_body'] ?? ''));
    $enabled = !empty($input['is_enabled']) ? 1 : 0;

    if ($subject === '') {
        throw new InvalidArgumentException(
            'Email subject cannot be blank.'
        );
    }

    if ($textBody === '') {
        throw new InvalidArgumentException(
            'Plain-text email body cannot be blank.'
        );
    }

    if ($htmlBody === '') {
        throw new InvalidArgumentException(
            'HTML email body cannot be blank.'
        );
    }

    if (mb_strlen($subject) > 190) {
        throw new InvalidArgumentException(
            'Email subject is too long.'
        );
    }

    $default = $defaults[$templateKey];

    $stmt = $db->prepare(
        'INSERT INTO email_templates (
            template_key,
            category,
            name,
            description,
            subject,
            preheader,
            text_body,
            html_body,
            is_enabled,
            updated_by,
            created_at,
            updated_at
         ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            subject = VALUES(subject),
            preheader = VALUES(preheader),
            text_body = VALUES(text_body),
            html_body = VALUES(html_body),
            is_enabled = VALUES(is_enabled),
            updated_by = VALUES(updated_by),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $templateKey,
        $default['category'],
        $default['name'],
        $default['description'],
        $subject,
        $preheader,
        $textBody,
        $htmlBody,
        $enabled,
        $actorUserId > 0 ? $actorUserId : null,
    ]);
}


function llama_email_replace_variables(
    string $content,
    array $context,
    bool $html = false,
    array $rawHtmlVariables = []
): string {
    return (string) preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
        static function (array $match) use (
            $context,
            $html,
            $rawHtmlVariables
        ): string {
            $key = (string) ($match[1] ?? '');

            if (
                $html
                && array_key_exists(
                    $key,
                    $rawHtmlVariables
                )
            ) {
                return (string) $rawHtmlVariables[$key];
            }

            $value =
                (string) (
                    $context[$key]
                    ?? ''
                );

            if (!$html) {
                return $value;
            }

            return htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        },
        $content
    );
}

function llama_email_html_shell(
    string $preheader,
    string $bodyHtml
): string {
    $safePreheader = htmlspecialchars(
        $preheader,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width">
  <title>Llama Scout</title>
</head>

<body style="margin:0;padding:0;background:#f2eee6;font-family:Arial,Helvetica,sans-serif;color:#172822;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    {$safePreheader}
  </div>

  <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
    <div style="padding:0 0 18px;text-align:center;">
      <img
        src="https://llamascout.com/images/logo.png"
        alt="Llama Scout"
        width="220"
        style="display:inline-block;max-width:220px;height:auto;border:0;"
      >
    </div>

    <div style="background:#ffffff;border:1px solid #dedbd3;border-radius:16px;padding:32px;">
      {$bodyHtml}

      <hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 20px;">

      <p style="margin:0;color:#667069;font-size:13px;line-height:1.6;">
        Llama Scout<br>
        Know the place before you go.
      </p>
    </div>
  </div>
</body>
</html>
HTML;
}


function llama_email_render_record(
    array $template,
    array $context
): array {
    $subject = llama_email_replace_variables(
        (string) $template['subject'],
        $context,
        false
    );

    $subject = trim(
        preg_replace('/[\r\n]+/', ' ', $subject) ?? $subject
    );

    $preheader = llama_email_replace_variables(
        (string) ($template['preheader'] ?? ''),
        $context,
        false
    );

    $text = llama_email_replace_variables(
        (string) $template['text_body'],
        $context,
        false
    );

    $rawHtmlVariables =
        isset($context['_raw_html'])
        && is_array($context['_raw_html'])
            ? $context['_raw_html']
            : [];

    $bodyHtml = llama_email_replace_variables(
        (string) $template['html_body'],
        $context,
        true,
        $rawHtmlVariables
    );

    $templateKey =
        (string) (
            $template['template_key']
            ?? ''
        );

    if (
        in_array(
            $templateKey,
            [
                'promotion_campaign_announcement',
                'promotion_campaign_reminder',
            ],
            true
        )
    ) {
        $unsubscribeUrl =
            (string) (
                $context['unsubscribe_url']
                ?? ''
            );

        $safeUnsubscribe = htmlspecialchars(
            $unsubscribeUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $bodyHtml .= <<<HTML
<hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 20px;">
<p style="margin:0;color:#667069;font-size:12px;line-height:1.6;">
You received this promotional email because your Llama Scout account is eligible for membership offers.
<a href="{$safeUnsubscribe}" style="color:#445c52;">Unsubscribe from promotional email</a>.
</p>
HTML;

        $text = rtrim($text)
            . "\n\nUnsubscribe from Llama Scout promotional email:\n"
            . $unsubscribeUrl
            . "\n";
    }

    return [
        'subject' => $subject,
        'preheader' => $preheader,
        'text' => $text,
        'html' => llama_email_html_shell(
            $preheader,
            $bodyHtml
        ),
    ];
}


function llama_email_log_send(
    PDO $db,
    string $templateKey,
    string $recipient,
    bool $success,
    bool $isTest,
    ?int $userId = null
): void {
    if (!llama_email_table_exists($db, 'email_send_log')) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO email_send_log (
            template_key,
            recipient_email,
            user_id,
            is_test,
            send_status,
            sent_at
         ) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );

    $stmt->execute([
        $templateKey,
        strtolower(trim($recipient)),
        $userId,
        $isTest ? 1 : 0,
        $success ? 'sent' : 'failed',
    ]);
}


function llama_email_recipient_account(
    PDO $db,
    string $recipient,
    ?int $userId = null
): ?array {
    if ($userId !== null && $userId > 0) {
        $stmt =
            $db->prepare(
                'SELECT
                    id,
                    email,
                    email_verified_at,
                    status,
                    anonymized_at
                 FROM users
                 WHERE id = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $userId,
        ]);
    } else {
        $recipient =
            strtolower(
                trim($recipient)
            );

        if (
            $recipient === ''
            || !filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        $stmt =
            $db->prepare(
                'SELECT
                    id,
                    email,
                    email_verified_at,
                    status,
                    anonymized_at
                 FROM users
                 WHERE LOWER(email) = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $recipient,
        ]);
    }

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}


function llama_email_account_can_receive(
    PDO $db,
    string $templateKey,
    string $recipient,
    bool $isTest,
    ?int $userId = null
): bool {
    /*
     * Admin tests must remain testable even if the dev mailbox is attached
     * to an account whose verification state is being changed.
     */
    if ($isTest) {
        return true;
    }

    /*
     * Verification messages must be able to reach an address before that
     * address itself has been verified. This covers both new-account
     * verification and the pending-email-change verification workflow.
     */
    if (
        in_array(
            $templateKey,
            [
                'verify_email',
                'email_change_verification',
            ],
            true
        )
    ) {
        return true;
    }

    /*
     * Invitation messages may go to someone who does not have an account
     * yet. If no account exists for the recipient, verification state does
     * not apply.
     */
    $account =
        llama_email_recipient_account(
            $db,
            $recipient,
            $userId
        );

    if (!$account) {
        return true;
    }

    if (!empty($account['anonymized_at'])) {
        return false;
    }

    if (
        in_array(
            strtolower(
                trim(
                    (string) (
                        $account['status']
                        ?? ''
                    )
                )
            ),
            [
                'suspended',
                'disabled',
            ],
            true
        )
    ) {
        return false;
    }

    return
        !empty(
            $account['email_verified_at']
        );
}


function llama_email_send_template(
    PDO $db,
    string $templateKey,
    string $recipient,
    array $context,
    bool $isTest = false,
    ?int $userId = null,
    ?array $templateOverride = null
): bool {
    $template =
        $templateOverride
        ?? llama_email_template($db, $templateKey);

    if (!$template) {
        throw new RuntimeException(
            'Email template not found.'
        );
    }

    if (!$isTest && empty($template['enabled'])) {
        return false;
    }

    if (
        !llama_email_account_can_receive(
            $db,
            $templateKey,
            $recipient,
            $isTest,
            $userId
        )
    ) {
        /*
         * This is an intentional suppression, not a failed SMTP delivery.
         * Do not add a failed email_send_log row, because nothing was sent.
         */
        return false;
    }

    $rendered =
        llama_email_render_record(
            $template,
            $context
        );

    $subject =
        $isTest
            ? '[TEST] ' . $rendered['subject']
            : $rendered['subject'];

    $success = send_llama_mail(
        $recipient,
        $subject,
        $rendered['text'],
        $rendered['html']
    );

    llama_email_log_send(
        $db,
        $templateKey,
        $recipient,
        $success,
        $isTest,
        $userId
    );

    return $success;
}


function llama_email_sample_context(
    string $templateKey
): array {
    return [
        'display_name' => 'Trail Tester',
        'username' => 'trailtester',
        'new_email' => 'new-address@example.com',
        'years_with_us' => '3 years',
        'anniversary_number' => '3rd',
        'member_since' => 'September 19, 2023',
        'account_url' =>
            'https://account.llamascout.com/',
        'verification_url' =>
            $templateKey === 'email_change_verification'
                ? 'https://account.llamascout.com/verify-email-change.php?token=TEST'
                : 'https://account.llamascout.com/verify-email.php?token=TEST',
        'reset_url' =>
            'https://account.llamascout.com/reset-password.php?token=TEST',
        'membership_url' =>
            'https://llamascout.com/membership.php',
        'monthly_url' =>
            'https://account.llamascout.com/membership.php?plan=monthly',
        'annual_url' =>
            'https://account.llamascout.com/membership.php?plan=annual',
        'monthly_price' => '$6.99 / month',
        'annual_price' => '$59.99 / year',
        'demo_report_url' =>
            'https://llamascout.com/scout-report-demo.php',
        'map_url' =>
            'https://llamascout.com/map.php',
        'site_url' =>
            'https://llamascout.com',
        'support_url' =>
            'https://llamascout.com/contact.php',
        'scout_invite_url' =>
            'https://account.llamascout.com/scout-invite.php',

        'ticket_number' => 'LS-260919-001',
        'support_category' => 'Technical problem or site error',
        'requester_name' => 'Trail Tester',
        'requester_email' => 'trailtester@example.com',
        'preferred_contact' => 'Email',
        'request_details' =>
            "Phone: (970) 555-0123\nError: LS-TEST-123",
        'ticket_subject' => 'Map page will not load',
        'ticket_message' =>
            'The map stopped loading after I signed in.',
        'admin_ticket_url' =>
            'https://admin.llamascout.com/support.php?id=123',
        'ticket_extra_details' =>
            'Error reference: LS-TEST-123',

        'contribution_type' => 'new Place submission',
        'contribution_id' => '123',
        'place_name' => 'Alpine Hollow',
        'review_notes' =>
            'Everything looks good. Thanks for the detailed access notes.',
        'points_awarded' => '100',
        'contribution_url' =>
            'https://account.llamascout.com/submissions.php',
        'place_url' =>
            'https://llamascout.com/place.php?place=alpine-hollow',

        'customer_name' => 'Trail Tester',
        'order_number' => 'LS-12345',
        'order_items' =>
            "1 x Llama Scout Vintage Tee - Faded Black  $29.00\n"
            . "2 x Sticker Pack  $12.00",
        'subtotal' => '$41.00',
        'shipping' => '$5.00',
        'tax' => '$3.68',
        'discount_line' => 'Discount: -$5.00',
        'total' => '$44.68',
        'order_action_url' =>
            'https://account.llamascout.com/order.php?id=12345',
        'order_action_label' =>
            'View your order',
        'tracking_carrier' => 'USPS',
        'tracking_number' => '9400111899560000000000',
        'tracking_url' =>
            'https://tools.usps.com/go/TrackConfirmAction?tLabels=9400111899560000000000',
        'refund_amount' => '$44.68',

        'recipient_name' => 'Trail Tester',
        'invite_email' => 'trailtester@example.com',
        'complimentary_days' => '90',
        'invite_expires' => 'September 23, 2026',
        'invite_reason' =>
            'Weâd like you to explore the complete Llama Scout experience.',
        'invite_url' =>
            'https://account.llamascout.com/complimentary-invite.php?token=TEST',

        'campaign_name' => 'Flash Sale',
        'campaign_label' => 'Flash Sale Today Only',
        'campaign_description' =>
            'Save on Llama Scout Complete Access for a limited time.',
        'promotion_url' =>
            'https://llamascout.com/membership.php?promotion=test-sale',
        'starts_at' => 'September 18, 2026 9:00 AM MDT',
        'ends_at' => 'September 18, 2026 11:59 PM MDT',
        'monthly_regular_price' => '$6.99 / month',
        'monthly_sale_price' => '$5.24 / month',
        'monthly_year_total' => '$62.88 for 12 months',
        'monthly_discount' => '25% off',
        'monthly_offer' =>
            '$5.24 / month ($62.88 for 12 months, 25% off)',
        'annual_regular_price' => '$59.99 / year',
        'annual_sale_price' => '$44.99 / year',
        'annual_month_equivalent' => '$3.75 / month equivalent',
        'annual_discount' => '25% off',
        'annual_offer' =>
            '$44.99 / year ($3.75 / month equivalent, 25% off)',
        'unsubscribe_url' =>
            'https://account.llamascout.com/email-preferences.php?token=TEST',
    ];
}
