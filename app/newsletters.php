<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/promotion-campaigns.php';


function llama_newsletter_types(): array
{
    return [
        'monthly' => 'Llama Scout Monthly',
        'member_dispatch' => 'Member Dispatch',
    ];
}


function llama_newsletter_type_label(string $type): string
{
    return llama_newsletter_types()[$type]
        ?? 'Newsletter';
}


function llama_newsletter_escape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function llama_newsletter_issue(
    PDO $db,
    int $issueId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM newsletter_issues
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$issueId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function llama_newsletter_issues(
    PDO $db,
    int $limit = 100
): array {
    $limit = max(1, min(300, $limit));

    $stmt = $db->query(
        'SELECT
            ni.*,
            (
                SELECT COUNT(*)
                FROM newsletter_deliveries nd
                WHERE nd.newsletter_id = ni.id
                  AND nd.status = "sent"
            ) AS sent_delivery_count,
            (
                SELECT COUNT(*)
                FROM newsletter_deliveries nd
                WHERE nd.newsletter_id = ni.id
                  AND nd.status = "failed"
            ) AS failed_delivery_count
         FROM newsletter_issues ni
         ORDER BY ni.created_at DESC, ni.id DESC
         LIMIT ' . $limit
    );

    return $stmt
        ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
}


function llama_newsletter_validate_type(string $type): string
{
    $type = strtolower(trim($type));

    if (!array_key_exists($type, llama_newsletter_types())) {
        throw new InvalidArgumentException(
            'Choose a valid newsletter type.'
        );
    }

    return $type;
}


function llama_newsletter_save_issue(
    PDO $db,
    int $actorUserId,
    array $data,
    int $issueId = 0
): int {
    $type = llama_newsletter_validate_type(
        (string) ($data['newsletter_type'] ?? '')
    );

    $title = trim(
        (string) ($data['title'] ?? '')
    );

    $subject = trim(
        (string) ($data['subject'] ?? '')
    );

    $body = trim(
        (string) ($data['body_text'] ?? '')
    );

    if ($title === '') {
        throw new InvalidArgumentException(
            'Newsletter title is required.'
        );
    }

    if (mb_strlen($title) > 180) {
        throw new InvalidArgumentException(
            'Newsletter title must be 180 characters or fewer.'
        );
    }

    if ($subject === '') {
        throw new InvalidArgumentException(
            'Email subject is required.'
        );
    }

    if (mb_strlen($subject) > 180) {
        throw new InvalidArgumentException(
            'Email subject must be 180 characters or fewer.'
        );
    }

    if ($body === '') {
        throw new InvalidArgumentException(
            'Newsletter content is required.'
        );
    }

    if (mb_strlen($body) > 30000) {
        throw new InvalidArgumentException(
            'Newsletter content must be 30,000 characters or fewer.'
        );
    }

    if ($issueId > 0) {
        $existing = llama_newsletter_issue(
            $db,
            $issueId
        );

        if (!$existing) {
            throw new InvalidArgumentException(
                'Newsletter not found.'
            );
        }

        if (
            in_array(
                strtolower(
                    (string) ($existing['status'] ?? '')
                ),
                ['sending', 'sent'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'A newsletter cannot be edited after sending has started.'
            );
        }

        $stmt = $db->prepare(
            'UPDATE newsletter_issues
             SET
                newsletter_type = ?,
                title = ?,
                subject = ?,
                body_text = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );

        $stmt->execute([
            $type,
            $title,
            $subject,
            $body,
            $issueId,
        ]);

        return $issueId;
    }

    $stmt = $db->prepare(
        'INSERT INTO newsletter_issues
         (
            newsletter_type,
            title,
            subject,
            body_text,
            status,
            created_by,
            created_at,
            updated_at
         )
         VALUES
         (?, ?, ?, ?, "draft", ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    $stmt->execute([
        $type,
        $title,
        $subject,
        $body,
        $actorUserId > 0
            ? $actorUserId
            : null,
    ]);

    return (int) $db->lastInsertId();
}


function llama_newsletter_schedule(
    PDO $db,
    int $issueId,
    string $sendAtUtc
): void {
    $issue = llama_newsletter_issue(
        $db,
        $issueId
    );

    if (!$issue) {
        throw new InvalidArgumentException(
            'Newsletter not found.'
        );
    }

    $status = strtolower(
        trim((string) ($issue['status'] ?? ''))
    );

    if (
        in_array(
            $status,
            ['sending', 'sent'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'This newsletter has already started sending.'
        );
    }

    $sendAtUtc = trim($sendAtUtc);

    if ($sendAtUtc === '') {
        throw new InvalidArgumentException(
            'Choose when this newsletter should send.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE newsletter_issues
         SET
            status = "scheduled",
            send_at = ?,
            sent_at = NULL,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $stmt->execute([
        $sendAtUtc,
        $issueId,
    ]);
}


function llama_newsletter_unschedule(
    PDO $db,
    int $issueId
): void {
    $issue = llama_newsletter_issue(
        $db,
        $issueId
    );

    if (!$issue) {
        throw new InvalidArgumentException(
            'Newsletter not found.'
        );
    }

    if (
        !in_array(
            strtolower(
                (string) ($issue['status'] ?? '')
            ),
            ['draft', 'scheduled'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'This newsletter can no longer be returned to draft.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE newsletter_issues
         SET
            status = "draft",
            send_at = NULL,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $stmt->execute([$issueId]);
}


function llama_newsletter_member_access_sql(
    string $userAlias = 'u'
): string {
    return '(
        (
            LOWER(COALESCE(' . $userAlias . '.membership_status, "none"))
                IN ("active", "trialing", "past_due", "complimentary")
            AND (
                ' . $userAlias . '.membership_ends_at IS NULL
                OR ' . $userAlias . '.membership_ends_at >= UTC_TIMESTAMP()
            )
        )
        OR EXISTS (
            SELECT 1
            FROM scout_profiles sp
            INNER JOIN user_roles ur
                ON ur.user_id = sp.user_id
            INNER JOIN roles r
                ON r.id = ur.role_id
            WHERE sp.user_id = ' . $userAlias . '.id
              AND sp.status = "active"
              AND (
                    sp.active_through IS NULL
                    OR sp.active_through >= UTC_TIMESTAMP()
              )
              AND r.slug IN (
                    "scout",
                    "master-scout",
                    "master_scout"
              )
        )
        OR EXISTS (
            SELECT 1
            FROM membership_grants mg
            WHERE mg.user_id = ' . $userAlias . '.id
              AND mg.grant_type = "complimentary"
              AND mg.revoked_at IS NULL
              AND mg.starts_at <= UTC_TIMESTAMP()
              AND mg.ends_at >= UTC_TIMESTAMP()
        )
    )';
}


function llama_newsletter_recipient_filter_sql(
    string $type
): string {
    $type = llama_newsletter_validate_type(
        $type
    );

    if ($type === 'member_dispatch') {
        return 'u.member_dispatch_email_enabled = 1
            AND '
            . llama_newsletter_member_access_sql('u');
    }

    return 'u.newsletter_email_enabled = 1';
}


function llama_newsletter_recipients(
    PDO $db,
    int $issueId,
    string $type,
    int $limit = 25
): array {
    $limit = max(1, min(100, $limit));

    $preferenceFilter =
        llama_newsletter_recipient_filter_sql(
            $type
        );

    $sql =
        'SELECT
            u.id,
            u.email,
            u.username,
            u.display_name
         FROM users u
         LEFT JOIN newsletter_deliveries nd
           ON nd.newsletter_id = ?
          AND nd.user_id = u.id
         WHERE u.email_verified_at IS NOT NULL
           AND u.email IS NOT NULL
           AND u.email <> ""
           AND '
           . $preferenceFilter .
        ' AND (
                nd.id IS NULL
                OR (
                    nd.status = "failed"
                    AND nd.failed_at IS NOT NULL
                    AND nd.failed_at <= DATE_SUB(
                        UTC_TIMESTAMP(),
                        INTERVAL 1 HOUR
                    )
                )
           )
         ORDER BY u.id ASC
         LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute([$issueId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function llama_newsletter_remaining_count(
    PDO $db,
    int $issueId,
    string $type
): int {
    $preferenceFilter =
        llama_newsletter_recipient_filter_sql(
            $type
        );

    $sql =
        'SELECT COUNT(*)
         FROM users u
         LEFT JOIN newsletter_deliveries nd
           ON nd.newsletter_id = ?
          AND nd.user_id = u.id
         WHERE u.email_verified_at IS NOT NULL
           AND u.email IS NOT NULL
           AND u.email <> ""
           AND '
           . $preferenceFilter .
        ' AND (
                nd.id IS NULL
                OR nd.status <> "sent"
           )';

    $stmt = $db->prepare($sql);
    $stmt->execute([$issueId]);

    return (int) $stmt->fetchColumn();
}


function llama_newsletter_audience_count(
    PDO $db,
    string $type
): int {
    $preferenceFilter =
        llama_newsletter_recipient_filter_sql(
            $type
        );

    $stmt = $db->query(
        'SELECT COUNT(*)
         FROM users u
         WHERE u.email_verified_at IS NOT NULL
           AND u.email IS NOT NULL
           AND u.email <> ""
           AND '
        . $preferenceFilter
    );

    return $stmt
        ? (int) $stmt->fetchColumn()
        : 0;
}


function llama_newsletter_record_delivery(
    PDO $db,
    int $issueId,
    array $user,
    string $status,
    ?string $failureMessage = null
): void {
    $sentAt =
        $status === 'sent'
            ? gmdate('Y-m-d H:i:s')
            : null;

    $failedAt =
        $status === 'failed'
            ? gmdate('Y-m-d H:i:s')
            : null;

    $stmt = $db->prepare(
        'INSERT INTO newsletter_deliveries
         (
            newsletter_id,
            user_id,
            email,
            status,
            sent_at,
            failed_at,
            failure_message,
            created_at,
            updated_at
         )
         VALUES
         (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            email = VALUES(email),
            status = VALUES(status),
            sent_at = VALUES(sent_at),
            failed_at = VALUES(failed_at),
            failure_message = VALUES(failure_message),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $issueId,
        (int) $user['id'],
        (string) $user['email'],
        $status,
        $sentAt,
        $failedAt,
        $failureMessage !== null
            ? mb_substr(
                $failureMessage,
                0,
                500
            )
            : null,
    ]);
}


function llama_newsletter_html(
    string $title,
    string $body,
    string $typeLabel,
    string $unsubscribeUrl
): string {
    $safeTitle =
        llama_newsletter_escape($title);

    $safeType =
        llama_newsletter_escape($typeLabel);

    $safeUnsubscribe =
        llama_newsletter_escape(
            $unsubscribeUrl
        );

    $paragraphs =
        preg_split(
            '/\R{2,}/',
            trim($body)
        )
        ?: [];

    $bodyHtml = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);

        if ($paragraph === '') {
            continue;
        }

        $bodyHtml .=
            '<p style="margin:0 0 18px;line-height:1.65;">'
            . nl2br(
                llama_newsletter_escape(
                    $paragraph
                )
            )
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
<div style="max-width:640px;margin:0 auto;padding:32px 18px;">
<div style="background:#ffffff;border-radius:16px;padding:32px;">
<p style="margin:0 0 8px;font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#667069;">{$safeType}</p>
<h1 style="margin:0 0 24px;font-size:28px;line-height:1.2;">{$safeTitle}</h1>

{$bodyHtml}

<p style="margin:30px 0;">
<a href="https://llamascout.com/" style="display:inline-block;background:#172822;color:#ffffff;padding:14px 22px;border-radius:9px;text-decoration:none;font-weight:bold;">
Open Llama Scout
</a>
</p>

<hr style="border:0;border-top:1px solid #e4e4e0;margin:30px 0 22px;">

<p style="margin:0;color:#667069;font-size:12px;line-height:1.6;">
Manage which optional Llama Scout emails you receive in
<a href="{$safeUnsubscribe}" style="color:#445c52;">Email Preferences</a>.
Account and service messages are not affected.
</p>
</div>
</div>
</body>
</html>
HTML;
}


function llama_newsletter_text(
    string $title,
    string $body,
    string $unsubscribeUrl
): string {
    return $title
        . "\n\n"
        . trim($body)
        . "\n\nOpen Llama Scout:\n"
        . "https://llamascout.com/\n\n"
        . "Manage optional email preferences:\n"
        . $unsubscribeUrl
        . "\n";
}


function llama_newsletter_send_batch(
    PDO $db,
    array $issue,
    int $limit = 25
): array {
    $issueId =
        (int) ($issue['id'] ?? 0);

    if ($issueId < 1) {
        throw new InvalidArgumentException(
            'Newsletter is missing an ID.'
        );
    }

    $type =
        llama_newsletter_validate_type(
            (string) (
                $issue['newsletter_type']
                ?? ''
            )
        );

    $title = trim(
        (string) ($issue['title'] ?? '')
    );

    $subject = trim(
        (string) ($issue['subject'] ?? '')
    );

    $body = trim(
        (string) ($issue['body_text'] ?? '')
    );

    if (
        $title === ''
        || $subject === ''
        || $body === ''
    ) {
        throw new RuntimeException(
            'Newsletter content is incomplete.'
        );
    }

    $db->prepare(
        'UPDATE newsletter_issues
         SET
            status = "sending",
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND status IN ("scheduled","sending")'
    )->execute([$issueId]);

    $recipients =
        llama_newsletter_recipients(
            $db,
            $issueId,
            $type,
            $limit
        );

    $stats = [
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
    ];

    foreach ($recipients as $user) {
        $stats['attempted']++;

        try {
            $unsubscribeUrl =
                llama_marketing_unsubscribe_url(
                    $db,
                    (int) $user['id']
                );

            $html =
                llama_newsletter_html(
                    $title,
                    $body,
                    llama_newsletter_type_label(
                        $type
                    ),
                    $unsubscribeUrl
                );

            $text =
                llama_newsletter_text(
                    $title,
                    $body,
                    $unsubscribeUrl
                );

            $sent =
                send_llama_mail(
                    (string) $user['email'],
                    $subject,
                    $text,
                    $html
                );

            if (!$sent) {
                throw new RuntimeException(
                    'Mail server rejected the message.'
                );
            }

            llama_newsletter_record_delivery(
                $db,
                $issueId,
                $user,
                'sent'
            );

            $stats['sent']++;
        } catch (Throwable $exception) {
            llama_newsletter_record_delivery(
                $db,
                $issueId,
                $user,
                'failed',
                $exception->getMessage()
            );

            $stats['failed']++;
        }
    }

    $remaining =
        llama_newsletter_remaining_count(
            $db,
            $issueId,
            $type
        );

    if ($remaining === 0) {
        $sentCountStmt =
            $db->prepare(
                'SELECT COUNT(*)
                 FROM newsletter_deliveries
                 WHERE newsletter_id = ?
                   AND status = "sent"'
            );

        $sentCountStmt->execute([
            $issueId,
        ]);

        $sentCount =
            (int) $sentCountStmt
                ->fetchColumn();

        $db->prepare(
            'UPDATE newsletter_issues
             SET
                status = "sent",
                sent_at = UTC_TIMESTAMP(),
                sent_count = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        )->execute([
            $sentCount,
            $issueId,
        ]);
    }

    return $stats;
}


function llama_newsletter_due_issues(
    PDO $db
): array {
    $stmt = $db->query(
        'SELECT *
         FROM newsletter_issues
         WHERE status IN ("scheduled","sending")
           AND send_at IS NOT NULL
           AND send_at <= UTC_TIMESTAMP()
           AND sent_at IS NULL
         ORDER BY send_at ASC, id ASC'
    );

    return $stmt
        ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [])
        : [];
}


function llama_newsletter_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 60
): bool {
    $intervalSeconds =
        max(30, $intervalSeconds);

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'newsletter_delivery',
    ]);

    $lastRun =
        $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $timestamp =
        strtotime((string) $lastRun);

    if ($timestamp === false) {
        return true;
    }

    return
        (time() - $timestamp)
        >= $intervalSeconds;
}


function llama_newsletter_mark_maintenance_run(
    PDO $db
): void {
    $stmt = $db->prepare(
        'INSERT INTO app_maintenance
         (
            maintenance_key,
            last_run_at
         )
         VALUES (?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            last_run_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        'newsletter_delivery',
    ]);
}


function llama_run_newsletter_maintenance(
    PDO $db,
    int $batchSize = 2
): array {
    $summary = [
        'ran' => false,
        'issues' => 0,
        'attempted' => 0,
        'sent' => 0,
        'failed' => 0,
    ];

    if (
        !llama_newsletter_maintenance_is_due(
            $db
        )
    ) {
        return $summary;
    }

    $lockStmt =
        $db->query(
            "SELECT GET_LOCK('llamascout_newsletters', 0)"
        );

    if (
        !$lockStmt
        || (int) $lockStmt->fetchColumn()
            !== 1
    ) {
        return $summary;
    }

    try {
        if (
            !llama_newsletter_maintenance_is_due(
                $db
            )
        ) {
            return $summary;
        }

        $summary['ran'] = true;

        $batchSize =
            max(
                1,
                min(5, $batchSize)
            );

        foreach (
            llama_newsletter_due_issues($db)
            as $issue
        ) {
            $stats =
                llama_newsletter_send_batch(
                    $db,
                    $issue,
                    $batchSize
                );

            $summary['issues']++;
            $summary['attempted'] +=
                (int) $stats['attempted'];
            $summary['sent'] +=
                (int) $stats['sent'];
            $summary['failed'] +=
                (int) $stats['failed'];
        }

        llama_newsletter_mark_maintenance_run(
            $db
        );

        return $summary;
    } finally {
        try {
            $db->query(
                "SELECT RELEASE_LOCK('llamascout_newsletters')"
            );
        } catch (Throwable) {
            // Connection cleanup releases the lock.
        }
    }
}
