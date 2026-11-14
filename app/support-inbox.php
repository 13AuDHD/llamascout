<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once __DIR__ . '/support-conversations.php';
require_once __DIR__ . '/support-imap-reader.php';

/*
 * One support inbox, one canonical support_requests ticket, one conversation.
 * The UID ledger and cursor prevent repeated imports. Unmatched mail is
 * retained in Purelymail and appears in Basecamp for human review.
 */

function llama_support_inbox_schema_ready(PDO $db): bool
{
    if (!llama_support_conversations_ready($db)) {
        return false;
    }
    $stmt = $db->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('support_inbox_state', 'support_inbound_messages')"
    );
    return (int) $stmt->fetchColumn() === 2;
}

function llama_support_inbox_state(PDO $db): array
{
    $stmt = $db->query('SELECT * FROM support_inbox_state WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Support inbox cursor is missing. Apply the inbox SQL migration.');
    }
    return $row;
}

function llama_support_inbox_reviews(PDO $db, int $limit = 40): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $db->query(
        'SELECT * FROM support_inbound_messages
         WHERE status = "review"
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function llama_support_inbox_review_count(PDO $db): int
{
    return (int) $db->query(
        'SELECT COUNT(*) FROM support_inbound_messages WHERE status = "review"'
    )->fetchColumn();
}

function llama_support_inbox_result(): array
{
    return [
        'ran' => false,
        'checked' => 0,
        'imported' => 0,
        'review' => 0,
        'ignored' => 0,
        'error' => '',
    ];
}

function llama_support_inbox_update_state(
    PDO $db,
    int $validity,
    int $lastUid,
    string $error = ''
): void {
    $stmt = $db->prepare(
        'UPDATE support_inbox_state
         SET uid_validity = ?, last_uid = ?,
             last_checked_at = UTC_TIMESTAMP(), last_error = ?
         WHERE id = 1'
    );
    $stmt->execute([
        $validity, $lastUid, $error !== '' ? mb_substr($error, 0, 500) : null,
    ]);
}

function llama_support_inbox_due(array $state, int $seconds = 300): bool
{
    $last = (string) ($state['last_checked_at'] ?? '');
    if ($last === '') {
        return true;
    }
    try {
        $stamp = (new DateTimeImmutable(
            $last, new DateTimeZone('UTC')
        ))->getTimestamp();
    } catch (Throwable) {
        return true;
    }
    return (time() - $stamp) >= $seconds;
}

function llama_support_inbox_ticket_for_mail(PDO $db, array $mail): array
{
    $subjectTicket = null;
    if (preg_match('/\bticket\s*#\s*(\d{6}-\d{6}(?:-\d{2})?)\b/i', $mail['subject'], $m)) {
        $stmt = $db->prepare(
            'SELECT id FROM support_requests WHERE ticket_number = ? LIMIT 1'
        );
        $stmt->execute([$m[1]]);
        $subjectTicket = (int) ($stmt->fetchColumn() ?: 0);
        if ($subjectTicket < 1) {
            return [null, 'ticket_not_found'];
        }
    }

    $referenceTickets = [];
    if (!empty($mail['references'])) {
        $stmt = $db->prepare(
            'SELECT support_request_id FROM support_ticket_messages
             WHERE external_message_id = ? LIMIT 1'
        );
        foreach (array_slice($mail['references'], -15) as $ref) {
            $stmt->execute([$ref]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                $referenceTickets[$id] = true;
            }
        }
    }

    if (count($referenceTickets) > 1) {
        return [null, 'conflicting_references'];
    }
    $referenceTicket = $referenceTickets
        ? (int) array_key_first($referenceTickets)
        : null;

    if ($subjectTicket && $referenceTicket && $subjectTicket !== $referenceTicket) {
        return [null, 'conflicting_ticket_identifiers'];
    }
    $ticketId = $referenceTicket ?? $subjectTicket;
    if (!$ticketId) {
        return [null, 'no_ticket_reference'];
    }

    $ticket = llama_support_request($db, (int) $ticketId);
    if (!$ticket) {
        return [null, 'ticket_not_found'];
    }
    if (strcasecmp((string) $ticket['email'], (string) $mail['sender']) !== 0) {
        return [null, 'sender_does_not_match'];
    }

    return [$ticket, ''];
}

function llama_support_inbox_save_attachment(
    PDO $db,
    int $messageId,
    string $name,
    string $bytes,
    array &$written
): bool {
    if ($bytes === '' || strlen($bytes) > 10 * 1024 * 1024) {
        return false;
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!is_string($mime) || !isset($allowed[$mime])) {
        return false;
    }
    $dir = llama_support_attachment_directory();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Private Support attachment storage is unavailable.');
    }
    $name = basename(str_replace('\\', '/', $name));
    $name = mb_substr(trim(str_replace(["\r", "\n", "\0"], '', $name)), 0, 240);
    if ($name === '') {
        $name = 'attachment.' . $allowed[$mime];
    }
    $storage = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
    $path = $dir . '/' . $storage;
    if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
        @unlink($path);
        throw new RuntimeException('Could not save the incoming attachment.');
    }
    @chmod($path, 0600);
    $written[] = $path;
    $stmt = $db->prepare(
        'INSERT INTO support_ticket_attachments
         (support_ticket_message_id, original_name, storage_name, mime_type, byte_size)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$messageId, $name, $storage, $mime, strlen($bytes)]);
    return true;
}

function llama_support_inbox_persist(
    PDO $db,
    int $validity,
    int $uid,
    array $mail,
    ?int $forcedTicketId = null
): string {
    $written = [];
    $messageId = (string) $mail['message_id'];
    if ($messageId === '') {
        $messageId = '<imap-' . $validity . '-' . $uid . '@llamascout.local>';
    }
    $reason = '';
    $ticket = null;
    $status = 'review';

    if (!empty($mail['oversized'])) {
        $reason = 'oversized_message';
    } elseif (!empty($mail['auto'])) {
        $status = 'ignored';
        $reason = 'automatic_or_internal_message';
    } elseif ((string) $mail['sender'] === '') {
        $reason = 'invalid_sender';
    } else {
        $duplicate = $db->prepare(
            'SELECT id FROM support_ticket_messages WHERE external_message_id = ? LIMIT 1'
        );
        $duplicate->execute([$messageId]);
        if ($duplicate->fetchColumn()) {
            $status = 'ignored';
            $reason = 'duplicate_message_id';
        } elseif ($forcedTicketId !== null) {
            $ticket = llama_support_request($db, $forcedTicketId);
            if (!$ticket) {
                throw new InvalidArgumentException('Selected ticket does not exist.');
            }
            if (strcasecmp((string) $ticket['email'], (string) $mail['sender']) !== 0) {
                throw new InvalidArgumentException(
                    'Sender does not match the ticket email. Verify identity before associating this message.'
                );
            }
        } else {
            [$ticket, $reason] = llama_support_inbox_ticket_for_mail($db, $mail);
        }
    }
    if ($ticket) {
        $status = 'imported';
    }

    $db->beginTransaction();
    try {
        $row = $db->prepare(
            'SELECT id, status FROM support_inbound_messages
             WHERE uid_validity = ? AND mailbox_uid = ?
             LIMIT 1 FOR UPDATE'
        );
        $row->execute([$validity, $uid]);
        $saved = $row->fetch(PDO::FETCH_ASSOC);
        if ($saved && (string) $saved['status'] !== 'review') {
            $db->commit();
            return (string) $saved['status'];
        }

        $preview = mb_substr((string) ($mail['body'] ?? ''), 0, 1800);
        if (!$saved) {
            $insert = $db->prepare(
                'INSERT INTO support_inbound_messages
                 (uid_validity, mailbox_uid, message_id, sender_email,
                  subject, body_preview, status, reason, support_request_id,
                  received_at)
                 VALUES (?, ?, ?, ?, ?, ?, "review", ?, NULL, ?)'
            );
            $insert->execute([
                $validity, $uid, $messageId,
                (string) $mail['sender'], (string) $mail['subject'],
                $preview, $reason, $mail['received_at'],
            ]);
            $inboundId = (int) $db->lastInsertId();
        } else {
            $inboundId = (int) $saved['id'];
        }

        $ticketMessageId = null;
        $attached = 0;
        if ($status === 'imported' && $ticket) {
            $files = $mail['files'] ?? [];
            $skipped = 0;
            $total = 0;
            $ready = [];
            foreach ($files as $file) {
                $bytes = $file['bytes'] ?? null;
                if (
                    !is_string($bytes)
                    || strlen($bytes) > 10 * 1024 * 1024
                    || count($ready) >= 3
                    || $total + strlen($bytes) > 20 * 1024 * 1024
                ) {
                    $skipped++;
                    continue;
                }
                $ready[] = $file;
                $total += strlen($bytes);
            }
            $body = (string) $mail['body'];
            $message = $db->prepare(
                'INSERT INTO support_ticket_messages
                 (support_request_id, author_role, author_user_id,
                  sender_email, visibility, channel, body_text, send_status,
                  external_message_id, created_at)
                 VALUES (?, "customer", NULL, ?, "public", "email", ?, "none", ?, UTC_TIMESTAMP())'
            );
            $message->execute([
                (int) $ticket['id'], (string) $mail['sender'],
                $body, $messageId,
            ]);
            $ticketMessageId = (int) $db->lastInsertId();
            foreach ($ready as $file) {
                if (llama_support_inbox_save_attachment(
                    $db, $ticketMessageId, (string) $file['name'],
                    (string) $file['bytes'], $written
                )) {
                    $attached++;
                } else {
                    $skipped++;
                }
            }
            if ($skipped > 0) {
                $append = $db->prepare(
                    'UPDATE support_ticket_messages
                     SET body_text = CONCAT(body_text, ?)
                     WHERE id = ?'
                );
                $append->execute([
                    "\n\n[$skipped incoming attachment(s) were unsupported or omitted.]",
                    $ticketMessageId,
                ]);
            }
            $updateTicket = $db->prepare(
                'UPDATE support_requests SET
                    status = "open", resolved_at = NULL,
                    updated_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );
            $updateTicket->execute([(int) $ticket['id']]);
        }

        $record = $db->prepare(
            'UPDATE support_inbound_messages
             SET message_id = ?, sender_email = ?, subject = ?,
                 body_preview = ?, status = ?, reason = ?,
                 support_request_id = ?, support_ticket_message_id = ?,
                 attachment_count = ?, received_at = ?,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $record->execute([
            $messageId,
            (string) $mail['sender'],
            (string) $mail['subject'],
            $preview, $status, $reason,
            $ticket ? (int) $ticket['id'] : null,
            $ticketMessageId, $attached, $mail['received_at'], $inboundId,
        ]);

        $db->commit();
        return $status;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($written as $path) {
            @unlink($path);
        }
        throw $error;
    }
}

function llama_support_inbox_sync(
    PDO $db,
    int $limit = 5,
    bool $force = false
): array {
    $summary = llama_support_inbox_result();
    if (!llama_support_inbox_schema_ready($db)) {
        $summary['error'] = 'Support inbox SQL migration has not been installed.';
        return $summary;
    }
    $config = llama_mail_config();
    if (!llama_support_imap_ready($config)) {
        $summary['error'] = 'Configure a read-only Purelymail IMAP app password in private/mail.php.';
        return $summary;
    }
    $state = llama_support_inbox_state($db);
    if (!$force && !llama_support_inbox_due($state, 300)) {
        return $summary;
    }

    $locked = (int) $db->query(
        "SELECT GET_LOCK('llamascout_support_inbox', 0)"
    )->fetchColumn() === 1;
    if (!$locked) {
        return $summary;
    }
    $imap = null;
    $validity = (int) $state['uid_validity'];
    $lastUid = (int) $state['last_uid'];
    try {
        $state = llama_support_inbox_state($db);
        if (!$force && !llama_support_inbox_due($state, 300)) {
            return $summary;
        }
        $summary['ran'] = true;
        $imap = llama_support_imap_connect($config);
        $folder = llama_support_imap_mailbox();
        $status = @imap_status($imap, $folder, SA_UIDVALIDITY);
        if (!$status || empty($status->uidvalidity)) {
            throw new RuntimeException('Unable to verify IMAP mailbox UID validity.');
        }
        $currentValidity = (int) $status->uidvalidity;
        if ($currentValidity !== $validity) {
            $validity = $currentValidity;
            $lastUid = 0;
        }
        $uids = @imap_search($imap, 'ALL', SE_UID);
        if ($uids === false) {
            $uids = [];
        }
        $uids = array_values(array_unique(array_map('intval', $uids)));
        sort($uids, SORT_NUMERIC);

        foreach ($uids as $uid) {
            if ($uid <= $lastUid) {
                continue;
            }
            if ($summary['checked'] >= max(1, min(15, $limit))) {
                break;
            }
            $mail = llama_support_imap_read($imap, $uid);
            $result = llama_support_inbox_persist($db, $validity, $uid, $mail);
            $lastUid = $uid;
            $summary['checked']++;
            $summary[$result]++;
            llama_support_inbox_update_state($db, $validity, $lastUid);
        }
        llama_support_inbox_update_state($db, $validity, $lastUid);
    } catch (Throwable $error) {
        $summary['error'] = $error->getMessage();
        try {
            llama_support_inbox_update_state(
                $db, $validity, $lastUid, $summary['error']
            );
        } catch (Throwable) {
            // Never replace the original importer error with an error-handler failure.
        }
    } finally {
        if ($imap !== null) {
            @imap_close($imap);
            imap_errors();
        }
        try {
            $db->query("SELECT RELEASE_LOCK('llamascout_support_inbox')");
        } catch (Throwable) {
            // Database disconnect will release the advisory lock.
        }
    }
    return $summary;
}

function llama_support_inbox_match_review(
    PDO $db,
    int $inboundId,
    string $ticketNumber
): void {
    if (!llama_support_inbox_schema_ready($db)) {
        throw new RuntimeException('Support inbox migration is missing.');
    }
    $stmt = $db->prepare(
        'SELECT * FROM support_inbound_messages
         WHERE id = ? AND status = "review" LIMIT 1'
    );
    $stmt->execute([$inboundId]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry) {
        throw new InvalidArgumentException('Review message is no longer available.');
    }
    $ticketStmt = $db->prepare(
        'SELECT id FROM support_requests WHERE ticket_number = ? LIMIT 1'
    );
    $ticketStmt->execute([trim($ticketNumber)]);
    $ticketId = (int) ($ticketStmt->fetchColumn() ?: 0);
    if ($ticketId < 1) {
        throw new InvalidArgumentException('Enter an existing ticket number.');
    }
    $config = llama_mail_config();
    $imap = llama_support_imap_connect($config);
    try {
        $status = @imap_status($imap, llama_support_imap_mailbox(), SA_UIDVALIDITY);
        if (!$status || (int) $status->uidvalidity !== (int) $entry['uid_validity']) {
            throw new RuntimeException('Mailbox changed. This message must be reviewed in Purelymail.');
        }
        $mail = llama_support_imap_read($imap, (int) $entry['mailbox_uid']);
        if ($mail['oversized'] || $mail['auto']) {
            throw new InvalidArgumentException('This message cannot be attached to a ticket.');
        }
        $result = llama_support_inbox_persist(
            $db, (int) $entry['uid_validity'], (int) $entry['mailbox_uid'],
            $mail, $ticketId
        );
        if ($result !== 'imported') {
            throw new RuntimeException('The reviewed email could not be imported.');
        }
    } finally {
        @imap_close($imap);
        imap_errors();
    }
}

function llama_support_inbox_ignore_review(PDO $db, int $inboundId): void
{
    $stmt = $db->prepare(
        'UPDATE support_inbound_messages
         SET status = "ignored", reason = "dismissed_by_staff",
             updated_at = UTC_TIMESTAMP()
         WHERE id = ? AND status = "review"'
    );
    $stmt->execute([$inboundId]);
    if ($stmt->rowCount() < 1) {
        throw new InvalidArgumentException('Review message is no longer available.');
    }
}
