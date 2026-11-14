<?php

declare(strict_types=1);

/*
 * Read-only Purelymail IMAP reader for Basecamp Support.
 * The server extension "imap" is optional until the inbox worker is enabled.
 * Never marks messages read, moves them, or deletes them on Purelymail.
 */

function llama_support_imap_ready(array $config): bool
{
    return !empty($config['support_imap_enabled'])
        && !empty($config['support_imap_password'])
        && strtolower((string) ($config['support_imap_username'] ?? ''))
            === 'support@llamascout.com';
}

function llama_support_imap_mailbox(): string
{
    // Fixed, TLS-verified host: no user-supplied network destinations.
    return '{imap.purelymail.com:993/imap/ssl/validate-cert}INBOX';
}

function llama_support_imap_connect(array $config)
{
    if (!function_exists('imap_open')) {
        throw new RuntimeException(
            'The PHP IMAP extension is not enabled. Enable imap in cPanel PHP Selector.'
        );
    }
    if (!llama_support_imap_ready($config)) {
        throw new RuntimeException('Support inbox IMAP is not configured in private/mail.php.');
    }

    imap_timeout(IMAP_OPENTIMEOUT, 6);
    imap_timeout(IMAP_READTIMEOUT, 10);
    imap_timeout(IMAP_WRITETIMEOUT, 10);
    $imap = @imap_open(
        llama_support_imap_mailbox(),
        (string) $config['support_imap_username'],
        (string) $config['support_imap_password'],
        OP_READONLY,
        1,
        ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
    );
    if ($imap === false) {
        imap_errors();
        throw new RuntimeException(
            'Purelymail IMAP connection failed. Check the IMAP-only app password and PHP extension.'
        );
    }

    return $imap;
}

function llama_support_imap_header(string $raw, string $key): string
{
    $unfolded = preg_replace("/\r?\n[ \t]+/", ' ', $raw) ?? $raw;
    if (preg_match('/^' . preg_quote($key, '/') . ':[ \t]*(.*?)\r?$/mi', $unfolded, $m)) {
        return trim($m[1]);
    }
    return '';
}

function llama_support_imap_utf8(string $text, string $charset = 'UTF-8'): string
{
    $charset = strtoupper(trim($charset));
    if ($charset === '' || $charset === 'DEFAULT' || $charset === 'UNKNOWN-8BIT') {
        $charset = 'UTF-8';
    }
    try {
        return mb_convert_encoding($text, 'UTF-8', $charset);
    } catch (Throwable) {
        return mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}

function llama_support_imap_decode_header(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    try {
        return llama_support_imap_utf8(mb_decode_mimeheader($raw));
    } catch (Throwable) {
        return llama_support_imap_utf8($raw);
    }
}

function llama_support_imap_sender(string $raw): string
{
    if ($raw === '') {
        return '';
    }
    $list = @imap_rfc822_parse_adrlist($raw, 'invalid.invalid');
    if (!is_array($list) || count($list) !== 1) {
        return '';
    }
    $item = $list[0];
    $mailbox = strtolower((string) ($item->mailbox ?? ''));
    $host = strtolower((string) ($item->host ?? ''));
    $email = $mailbox . '@' . $host;

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function llama_support_imap_decode_content(string $encoded, int $encoding): string
{
    if ($encoding === 3) {
        $decoded = base64_decode($encoded, true);
        return $decoded === false ? '' : $decoded;
    }
    if ($encoding === 4) {
        return quoted_printable_decode($encoded);
    }
    return $encoded;
}

function llama_support_imap_part_parameter(object $part, string $name): string
{
    foreach (['dparameters', 'parameters'] as $key) {
        foreach (($part->$key ?? []) as $param) {
            if (
                strcasecmp((string) ($param->attribute ?? ''), $name) === 0
            ) {
                return (string) ($param->value ?? '');
            }
        }
    }
    return '';
}

function llama_support_imap_collect_parts(
    $imap,
    int $uid,
    object $part,
    string $partNo,
    array &$bodyCandidates,
    array &$files,
    int $depth = 0
): void {
    if ($depth > 6) {
        return;
    }
    if ((int) ($part->type ?? -1) === 1 && isset($part->parts)) {
        foreach ($part->parts as $index => $child) {
            if (!is_object($child)) {
                continue;
            }
            $childNo = $partNo === ''
                ? (string) ($index + 1)
                : $partNo . '.' . ($index + 1);
            llama_support_imap_collect_parts(
                $imap, $uid, $child, $childNo, $bodyCandidates, $files, $depth + 1
            );
        }
        return;
    }

    $partType = (int) ($part->type ?? -1);
    $subtype = strtoupper((string) ($part->subtype ?? ''));
    $filename = llama_support_imap_part_parameter($part, 'filename');
    if ($filename === '') {
        $filename = llama_support_imap_part_parameter($part, 'name');
    }
    $filename = llama_support_imap_decode_header($filename);
    $disposition = strtoupper((string) ($part->disposition ?? ''));
    $isAttachment = $filename !== '' || $disposition === 'ATTACHMENT';

    // Never fetch arbitrary nested messages or executable parts.
    if (
        !$isAttachment
        && !($partType === 0 && in_array($subtype, ['PLAIN', 'HTML'], true))
    ) {
        return;
    }
    if (count($files) >= 8 && $isAttachment) {
        return;
    }

    $encoded = $partNo === ''
        ? @imap_body($imap, $uid, FT_UID | FT_PEEK)
        : @imap_fetchbody($imap, $uid, $partNo, FT_UID | FT_PEEK);

    if (!is_string($encoded)) {
        throw new RuntimeException('An IMAP message part could not be retrieved.');
    }
    $decoded = llama_support_imap_decode_content(
        $encoded, (int) ($part->encoding ?? 0)
    );

    if ($isAttachment) {
        // Cap allocation for each decoded candidate.
        if (strlen($decoded) <= 10 * 1024 * 1024) {
            $files[] = [
                'name' => $filename === '' ? 'attachment' : $filename,
                'bytes' => $decoded,
            ];
        } else {
            $files[] = ['name' => $filename, 'bytes' => null];
        }
        return;
    }

    $charset = llama_support_imap_part_parameter($part, 'charset');
    $text = llama_support_imap_utf8($decoded, $charset);
    if ($subtype === 'HTML') {
        $text = html_entity_decode(
            preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?? $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $text = strip_tags($text);
    }
    $bodyCandidates[] = [
        'priority' => $subtype === 'PLAIN' ? 0 : 1,
        'text' => trim($text),
    ];
}

function llama_support_imap_read($imap, int $uid): array
{
    $overview = @imap_fetch_overview($imap, (string) $uid, FT_UID);
    if (!is_array($overview) || !$overview) {
        throw new RuntimeException('Unable to fetch incoming email metadata.');
    }
    $size = (int) ($overview[0]->size ?? 0);
    if ($size > 24 * 1024 * 1024) {
        // Avoid loading oversized messages into a shared PHP worker.
        return [
            'oversized' => true,
            'sender' => '',
            'subject' => '',
            'message_id' => '',
            'references' => [],
            'body' => '',
            'files' => [],
            'received_at' => null,
            'auto' => false,
        ];
    }

    $rawHeader = @imap_fetchheader($imap, $uid, FT_UID);
    $structure = @imap_fetchstructure($imap, $uid, FT_UID);
    if (!is_string($rawHeader) || !is_object($structure)) {
        throw new RuntimeException('Unable to read the IMAP message.');
    }

    $sender = llama_support_imap_sender(llama_support_imap_header($rawHeader, 'From'));
    $subject = llama_support_imap_decode_header(
        llama_support_imap_header($rawHeader, 'Subject')
    );
    $messageId = trim(llama_support_imap_header($rawHeader, 'Message-ID'));
    $refs = llama_support_imap_header($rawHeader, 'References') . ' '
        . llama_support_imap_header($rawHeader, 'In-Reply-To');
    preg_match_all('/<[^<>\r\n]{4,248}>/', $refs, $found);
    $references = array_values(array_unique($found[0] ?? []));

    $auto = strtolower(llama_support_imap_header($rawHeader, 'Auto-Submitted'));
    $precedence = strtolower(llama_support_imap_header($rawHeader, 'Precedence'));
    $isAuto = ($auto !== '' && $auto !== 'no')
        || in_array($precedence, ['bulk', 'list', 'junk'], true)
        || in_array($sender, ['support@llamascout.com', 'no-reply@llamascout.com'], true);

    $candidates = [];
    $files = [];
    llama_support_imap_collect_parts(
        $imap, $uid, $structure, '', $candidates, $files
    );
    usort(
        $candidates,
        static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']
    );
    $body = trim((string) ($candidates[0]['text'] ?? ''));
    if ($body === '') {
        $body = '(No readable text in incoming email.)';
    }
    $body = mb_substr($body, 0, 10000);

    $time = (string) ($overview[0]->date ?? '');
    $timestamp = $time !== '' ? strtotime($time) : false;
    $receivedAt = $timestamp !== false
        ? gmdate('Y-m-d H:i:s', $timestamp)
        : null;

    return [
        'oversized' => false,
        'sender' => $sender,
        'subject' => mb_substr($subject, 0, 500),
        'message_id' => mb_substr($messageId, 0, 255),
        'references' => $references,
        'body' => $body,
        'files' => $files,
        'received_at' => $receivedAt,
        'auto' => $isAuto,
    ];
}
