<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';


function llama_marketing_user_token(PDO $db, int $userId): string
{
    if ($userId < 1) {
        throw new InvalidArgumentException('A valid user is required.');
    }

    $stmt = $db->prepare(
        'SELECT marketing_unsubscribe_token
         FROM users
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);

    $token = trim((string) $stmt->fetchColumn());

    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        return $token;
    }

    $token = bin2hex(random_bytes(32));

    $update = $db->prepare(
        'UPDATE users
         SET marketing_unsubscribe_token = ?
         WHERE id = ?'
    );
    $update->execute([$token, $userId]);

    return $token;
}


function llama_marketing_unsubscribe_url(PDO $db, int $userId): string
{
    return 'https://account.llamascout.com/email-preferences.php?token='
        . rawurlencode(llama_marketing_user_token($db, $userId));
}


function llama_promotion_email_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}


function llama_promotion_email_html(
    string $name,
    string $body,
    string $promotionUrl,
    string $unsubscribeUrl
): string {
    $safeName = llama_promotion_email_escape($name);
    $safeUrl = llama_promotion_email_escape($promotionUrl);
    $safeUnsubscribe = llama_promotion_email_escape($unsubscribeUrl);

    $paragraphs = preg_split('/\R{2,}/', trim($body)) ?: [];
    $bodyHtml = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);

        if ($paragraph === '') {
            continue;
        }

        $bodyHtml .= '<p style="margin:0 0 18px;line-height:1.65;">'
            . nl2br(llama_promotion_email_escape($paragraph))
            . '</p>';
    }

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f4efe6;font-family:Arial,Helvetica,sans-serif;color:#172822;">
<div style="max-width:620px;margin:0 auto;padding:32px 18px;">
<div style="background:#ffffff;border-radius:16px;padding:32px;">
<p style="margin:0 0 10px;font-size:13px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#667069;">Llama Scout</p>
<h1 style="margin:0 0 22px;font-size:28px;line-height:1.2;">{$safeName}</h1>

{$bodyHtml}

<p style="margin:28px 0;">
<a href="{$safeUrl}" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:bold;">
View membership offer
</a>
</p>

<hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 22px;">

<p style="margin:0;color:#667069;font-size:12px;line-height:1.6;">
You received this promotional email because your Llama Scout account is eligible for membership offers.
<a href="{$safeUnsubscribe}" style="color:#445c52;">Unsubscribe from promotional email</a>.
</p>
</div>
</div>
</body>
</html>
HTML;
}


function llama_promotion_email_text(
    string $body,
    string $promotionUrl,
    string $unsubscribeUrl
): string {
    return trim($body)
        . "\n\nView membership offer:\n"
        . $promotionUrl
        . "\n\nUnsubscribe from Llama Scout promotional email:\n"
        . $unsubscribeUrl
        . "\n";
}


function llama_promotion_email_recipients(
    PDO $db,
    int $promotionId,
    string $deliveryType,
    int $limit = 25
): array {
    $limit = max(1, min(100, $limit));

    /*
     * Free members only:
     * - verified email
     * - marketing email still enabled
     * - no active/paid/complimentary membership
     * - no delivery already recorded for this campaign/type
     */
    $sql =
        'SELECT
            u.id,
            u.email,
            u.username,
            u.display_name,
            u.marketing_unsubscribe_token
         FROM users u
         LEFT JOIN membership_promotion_deliveries d
           ON d.promotion_id = ?
          AND d.user_id = u.id
          AND d.delivery_type = ?
         WHERE u.email_verified_at IS NOT NULL
           AND u.marketing_email_enabled = 1
           AND COALESCE(u.membership_status, \'none\')
               NOT IN (\'active\', \'trialing\', \'past_due\', \'complimentary\')
           AND d.id IS NULL
         ORDER BY u.id ASC
         LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute([$promotionId, $deliveryType]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function llama_promotion_record_delivery(
    PDO $db,
    int $promotionId,
    array $user,
    string $deliveryType,
    string $status,
    ?string $failureMessage = null
): void {
    $sentAt = $status === 'sent' ? gmdate('Y-m-d H:i:s') : null;
    $failedAt = $status === 'failed' ? gmdate('Y-m-d H:i:s') : null;

    $stmt = $db->prepare(
        'INSERT INTO membership_promotion_deliveries
         (
            promotion_id,
            user_id,
            email,
            delivery_type,
            status,
            sent_at,
            failed_at,
            failure_message
         )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            sent_at = VALUES(sent_at),
            failed_at = VALUES(failed_at),
            failure_message = VALUES(failure_message),
            updated_at = CURRENT_TIMESTAMP'
    );

    $stmt->execute([
        $promotionId,
        (int) $user['id'],
        (string) $user['email'],
        $deliveryType,
        $status,
        $sentAt,
        $failedAt,
        $failureMessage !== null ? mb_substr($failureMessage, 0, 500) : null,
    ]);
}


function llama_promotion_send_batch(
    PDO $db,
    array $promotion,
    string $deliveryType,
    int $limit = 25
): array {
    $promotionId = (int) ($promotion['id'] ?? 0);

    if ($promotionId < 1) {
        throw new InvalidArgumentException('Promotion is missing an ID.');
    }

    if ($deliveryType === 'reminder') {
        $subject = trim((string) ($promotion['reminder_subject'] ?? ''));
        $body = trim((string) ($promotion['reminder_body_text'] ?? ''));
    } else {
        $subject = trim((string) ($promotion['email_subject'] ?? ''));
        $body = trim((string) ($promotion['email_body_text'] ?? ''));
        $deliveryType = 'announcement';
    }

    if ($subject === '' || $body === '') {
        return [
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'remaining' => 0,
        ];
    }

    $promotionUrl = trim((string) ($promotion['landing_url'] ?? ''));

    if ($promotionUrl === '') {
        $promotionUrl = 'https://llamascout.com/membership';
    } elseif (str_starts_with($promotionUrl, '/')) {
        $promotionUrl = 'https://llamascout.com' . $promotionUrl;
    }

    $recipients = llama_promotion_email_recipients(
        $db,
        $promotionId,
        $deliveryType,
        $limit
    );

    $stats = [
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
        'remaining' => count($recipients),
    ];

    foreach ($recipients as $user) {
        $stats['attempted']++;

        try {
            $unsubscribeUrl = llama_marketing_unsubscribe_url(
                $db,
                (int) $user['id']
            );

            $name = trim((string) ($user['display_name'] ?? ''));

            if ($name === '') {
                $name = trim((string) ($user['username'] ?? ''));
            }

            if ($name === '') {
                $name = 'there';
            }

            $text = llama_promotion_email_text(
                $body,
                $promotionUrl,
                $unsubscribeUrl
            );

            $html = llama_promotion_email_html(
                $name,
                $body,
                $promotionUrl,
                $unsubscribeUrl
            );

            $sent = send_llama_mail(
                (string) $user['email'],
                $subject,
                $text,
                $html
            );

            if (!$sent) {
                throw new RuntimeException('Mail server rejected the message.');
            }

            llama_promotion_record_delivery(
                $db,
                $promotionId,
                $user,
                $deliveryType,
                'sent'
            );

            $stats['sent']++;
        } catch (Throwable $exception) {
            llama_promotion_record_delivery(
                $db,
                $promotionId,
                $user,
                $deliveryType,
                'failed',
                $exception->getMessage()
            );

            $stats['failed']++;
        }
    }

    $remainingStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM users u
         LEFT JOIN membership_promotion_deliveries d
           ON d.promotion_id = ?
          AND d.user_id = u.id
          AND d.delivery_type = ?
         WHERE u.email_verified_at IS NOT NULL
           AND u.marketing_email_enabled = 1
           AND COALESCE(u.membership_status, \'none\')
               NOT IN (\'active\', \'trialing\', \'past_due\', \'complimentary\')
           AND d.id IS NULL'
    );
    $remainingStmt->execute([$promotionId, $deliveryType]);

    $stats['remaining'] = (int) $remainingStmt->fetchColumn();

    return $stats;
}


function llama_due_promotion_email_jobs(PDO $db): array
{
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'SELECT *
         FROM membership_promotions
         WHERE is_enabled = 1
           AND
           (
                (
                    email_enabled = 1
                    AND email_send_at IS NOT NULL
                    AND email_send_at <= ?
                    AND email_sent_at IS NULL
                )
                OR
                (
                    reminder_enabled = 1
                    AND reminder_send_at IS NOT NULL
                    AND reminder_send_at <= ?
                    AND reminder_sent_at IS NULL
                )
           )
         ORDER BY starts_at ASC, id ASC'
    );
    $stmt->execute([$now, $now]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function llama_promotion_finish_delivery_if_complete(
    PDO $db,
    int $promotionId,
    string $deliveryType
): void {
    $recipients = llama_promotion_email_recipients(
        $db,
        $promotionId,
        $deliveryType,
        1
    );

    if ($recipients) {
        return;
    }

    $sentCountStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM membership_promotion_deliveries
         WHERE promotion_id = ?
           AND delivery_type = ?
           AND status = \'sent\''
    );
    $sentCountStmt->execute([$promotionId, $deliveryType]);
    $sentCount = (int) $sentCountStmt->fetchColumn();

    if ($deliveryType === 'reminder') {
        $stmt = $db->prepare(
            'UPDATE membership_promotions
             SET reminder_sent_at = UTC_TIMESTAMP(),
                 reminder_sent_count = ?
             WHERE id = ?'
        );
    } else {
        $stmt = $db->prepare(
            'UPDATE membership_promotions
             SET email_sent_at = UTC_TIMESTAMP(),
                 email_sent_count = ?
             WHERE id = ?'
        );
    }

    $stmt->execute([$sentCount, $promotionId]);
}
