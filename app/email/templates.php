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
    bool $html = false
): string {
    return (string) preg_replace_callback(
        '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
        static function (array $match) use ($context, $html): string {
            $key = (string) ($match[1] ?? '');
            $value = (string) ($context[$key] ?? '');

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

    $bodyHtml = llama_email_replace_variables(
        (string) $template['html_body'],
        $context,
        true
    );

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
    $base = [
        'display_name' => 'Trail Tester',
        'username' => 'trailtester',
        'verification_url' =>
            'https://account.llamascout.com/verify-email.php?token=TEST',
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
    ];

    return $base;
}
