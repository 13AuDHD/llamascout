<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/support.php';
require_once dirname(__DIR__) . '/app/support-conversations.php';

moderation_require_admin();

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attachmentId || $attachmentId < 1 || !llama_support_conversations_ready(db())) {
    http_response_code(404);
    exit('Attachment not found.');
}

$stmt = db()->prepare(
    'SELECT a.original_name, a.storage_name, a.mime_type, a.byte_size
     FROM support_ticket_attachments a
     JOIN support_ticket_messages m
       ON m.id = a.support_ticket_message_id
     JOIN support_requests r
       ON r.id = m.support_request_id
     WHERE a.id = ?
     LIMIT 1'
);
$stmt->execute([$attachmentId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp|pdf|txt)$/', (string) $file['storage_name'])) {
    http_response_code(404);
    exit('Attachment not found.');
}

$directory = realpath(llama_support_attachment_directory());
$path = $directory
    ? realpath($directory . '/' . $file['storage_name'])
    : false;
if (
    $directory === false
    || $path === false
    || dirname($path) !== $directory
    || !is_file($path)
) {
    http_response_code(404);
    exit('Attachment not found.');
}

$name = (string) $file['original_name'];
$name = trim(str_replace(["\r", "\n", '"', '\\'], '_', $name));
if ($name === '') {
    $name = 'attachment';
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="download"; filename*=UTF-8' . "''" . rawurlencode($name));
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Content-Security-Policy: sandbox');
readfile($path);
exit;
