<?php

declare(strict_types=1);

/*
 * Basecamp Support conversations. The original support_requests row remains
 * the canonical ticket; its initial message is rendered as the first item.
 * All subsequent messages belong to that same ticket ID.
 */

function llama_support_conversations_ready(PDO $db): bool
{
    $stmt = $db->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN (
             'support_ticket_messages',
             'support_ticket_attachments',
             'support_canned_responses'
           )"
    );

    return (int) $stmt->fetchColumn() === 3;
}

function llama_support_conversation(PDO $db, int $ticketId): array
{
    $stmt = $db->prepare(
        'SELECT * FROM support_ticket_messages
         WHERE support_request_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([$ticketId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_support_message_attachments(PDO $db, int $ticketId): array
{
    $stmt = $db->prepare(
        'SELECT a.*
         FROM support_ticket_attachments a
         JOIN support_ticket_messages m
           ON m.id = a.support_ticket_message_id
         WHERE m.support_request_id = ?
         ORDER BY a.id ASC'
    );
    $stmt->execute([$ticketId]);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[(int) $row['support_ticket_message_id']][] = $row;
    }

    return $grouped;
}

function llama_support_attachment_directory(): string
{
    return dirname(__DIR__, 2) . '/private/support-attachments';
}

function llama_support_canned_responses(PDO $db, bool $includeInactive = false): array
{
    $where = $includeInactive ? '' : 'WHERE is_active = 1';
    $stmt = $db->query(
        'SELECT * FROM support_canned_responses '
        . $where . ' ORDER BY category ASC, title ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_support_save_canned_response(
    PDO $db,
    int $actorId,
    int $responseId,
    string $title,
    string $category,
    string $body,
    bool $active
): int {
    $title = trim($title);
    $category = trim($category);
    $body = trim($body);
    if ($title === '' || mb_strlen($title) > 150) {
        throw new InvalidArgumentException('Canned response title must be 1 to 150 characters.');
    }
    if ($category === '' || mb_strlen($category) > 80) {
        throw new InvalidArgumentException('Canned response category must be 1 to 80 characters.');
    }
    if ($body === '' || mb_strlen($body) > 10000) {
        throw new InvalidArgumentException('Canned response text must be 1 to 10,000 characters.');
    }

    if ($responseId > 0) {
        $stmt = $db->prepare(
            'UPDATE support_canned_responses
             SET title = ?, category = ?, body_text = ?,
                 is_active = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $title, $category, $body, $active ? 1 : 0, $actorId, $responseId,
        ]);
        if ($stmt->rowCount() === 0) {
            $exists = $db->prepare('SELECT id FROM support_canned_responses WHERE id = ?');
            $exists->execute([$responseId]);
            if (!$exists->fetchColumn()) {
                throw new InvalidArgumentException('Canned response not found.');
            }
        }
        return $responseId;
    }

    $stmt = $db->prepare(
        'INSERT INTO support_canned_responses
         (title, category, body_text, is_active, created_by, updated_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $category, $body, $active ? 1 : 0, $actorId, $actorId]);

    return (int) $db->lastInsertId();
}

function llama_support_normalize_attachments(array $upload): array
{
    if (empty($upload['name']) || !is_array($upload['name'])) {
        return [];
    }

    $files = [];
    foreach ($upload['name'] as $i => $name) {
        $error = (int) ($upload['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('An attachment could not be uploaded. Check its size.');
        }
        $files[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($upload['tmp_name'][$i] ?? ''),
            'size' => (int) ($upload['size'][$i] ?? 0),
        ];
    }
    if (count($files) > 3) {
        throw new InvalidArgumentException('Attach up to three files per reply.');
    }

    $total = 0;
    foreach ($files as $file) {
        $total += $file['size'];
        if ($file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('Each attachment must be no larger than 10 MB.');
        }
    }
    if ($total > 20 * 1024 * 1024) {
        throw new InvalidArgumentException('The combined attachment size must not exceed 20 MB.');
    }

    return $files;
}

function llama_support_store_attachments(
    PDO $db,
    int $messageId,
    array $upload,
    array &$createdPaths
): array {
    $files = llama_support_normalize_attachments($upload);
    if (!$files) {
        return [];
    }

    $dir = llama_support_attachment_directory();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Private attachment storage is unavailable.');
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
    ];
    $ins = $db->prepare(
        'INSERT INTO support_ticket_attachments
         (support_ticket_message_id, original_name, storage_name, mime_type, byte_size)
         VALUES (?, ?, ?, ?, ?)'
    );
    $result = [];

    foreach ($files as $file) {
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The attachment upload could not be verified.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!is_string($mime) || !isset($allowed[$mime])) {
            throw new InvalidArgumentException(
                'Supported attachments are JPG, PNG, WebP, PDF and plain-text files.'
            );
        }
        $name = basename(str_replace('\\', '/', $file['name']));
        $name = trim(str_replace(["\r", "\n", "\0"], '', $name));
        if ($name === '') {
            $name = 'attachment.' . $allowed[$mime];
        }
        $name = mb_substr($name, 0, 240);
        $storageName = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $path = $dir . '/' . $storageName;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new RuntimeException('Could not save the attachment securely.');
        }
        @chmod($path, 0600);
        $createdPaths[] = $path;
        $ins->execute([$messageId, $name, $storageName, $mime, $file['size']]);
        $result[] = ['path' => $path, 'name' => $name];
    }

    return $result;
}

function llama_support_add_reply(
    PDO $db,
    int $ticketId,
    int $actorId,
    string $text,
    bool $internal,
    string $submissionKey,
    array $upload = [],
    int $cannedResponseId = 0
): bool {
    if (!llama_support_conversations_ready($db)) {
        throw new RuntimeException('Support conversation migration is not installed.');
    }
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 10000) {
        throw new InvalidArgumentException('Reply must be 1 to 10,000 characters.');
    }
    if (!preg_match('/^[a-f0-9]{32}$/', $submissionKey)) {
        throw new InvalidArgumentException('Reply form expired. Reload the ticket.');
    }
    if ($actorId < 1) {
        throw new RuntimeException('An authorized staff member is required.');
    }

    $existing = $db->prepare(
        'SELECT send_status FROM support_ticket_messages WHERE submission_key = ? LIMIT 1'
    );
    $existing->execute([$submissionKey]);
    $prior = $existing->fetchColumn();
    if ($prior !== false) {
        // Prevent duplicate emails from form resubmission.
        return in_array($prior, ['sent', 'none'], true);
    }

    $ticket = llama_support_request($db, $ticketId);
    if (!$ticket) {
        throw new InvalidArgumentException('Support ticket not found.');
    }
    if (!$internal && !filter_var((string) $ticket['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ticket has no valid recipient email.');
    }
    if (!$internal) {
        $config = llama_mail_config();
        if (
            empty($config['support_username'])
            || empty($config['support_password'])
            || strtolower((string) ($config['support_email'] ?? '')) !== 'support@llamascout.com'
        ) {
            throw new RuntimeException('Support SMTP credentials are not configured.');
        }
        if (!llama_mail_delivery_enabled($config)) {
            throw new RuntimeException('Email delivery is currently disabled.');
        }
    }

    // Validate the request before persisting any message.
    $files = llama_support_normalize_attachments($upload);
    if ($internal && $files) {
        throw new InvalidArgumentException('Attachments require a customer-visible reply.');
    }

    $createdPaths = [];
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO support_ticket_messages
             (support_request_id, author_role, author_user_id, sender_email,
              visibility, channel, body_text, send_status, submission_key)
             VALUES (?, "staff", ?, ?, ?, "web", ?, ?, ?)'
        );
        $stmt->execute([
            $ticketId, $actorId,
            $internal ? null : 'support@llamascout.com',
            $internal ? 'internal' : 'public',
            $text, $internal ? 'none' : 'pending', $submissionKey,
        ]);
        $messageId = (int) $db->lastInsertId();
        $threadMessageId = '<ls-ticket-' . $ticketId . '-msg-'
            . $messageId . '@llamascout.com>';
        if (!$internal) {
            $reference = $db->prepare(
                'UPDATE support_ticket_messages SET external_message_id = ? WHERE id = ?'
            );
            $reference->execute([$threadMessageId, $messageId]);
        }
        $attachments = llama_support_store_attachments(
            $db, $messageId, $upload, $createdPaths
        );
        if ($cannedResponseId > 0) {
            $count = $db->prepare(
                'UPDATE support_canned_responses SET use_count = use_count + 1
                 WHERE id = ? AND is_active = 1'
            );
            $count->execute([$cannedResponseId]);
        }
        $touch = $db->prepare(
            'UPDATE support_requests SET updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $touch->execute([$ticketId]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($createdPaths as $path) {
            @unlink($path);
        }
        throw $error;
    }

    if ($internal) {
        return true;
    }

    $ticketNumber = trim((string) ($ticket['ticket_number'] ?? $ticketId));
    $subject = 'Re: ' . (string) $ticket['subject']
        . ' [Ticket #' . $ticketNumber . ']';
    $body = $text . "\n\n"
        . 'Ticket #' . $ticketNumber . "\n"
        . "Llama Scout Support\n";
    $sent = send_llama_mail(
        (string) $ticket['email'], $subject, $body, null, 'support',
        $attachments, $threadMessageId
    );
    $status = $db->prepare(
        'UPDATE support_ticket_messages
         SET send_status = ?, sent_at = CASE WHEN ? = "sent" THEN UTC_TIMESTAMP() ELSE NULL END
         WHERE id = ?'
    );
    $status->execute([$sent ? 'sent' : 'failed', $sent ? 'sent' : 'failed', $messageId]);

    return $sent;
}
