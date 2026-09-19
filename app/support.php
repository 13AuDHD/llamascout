<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';


function llama_support_categories(): array
{
    return [
        'general' => 'General question',
        'account' => 'Account help',
        'membership' => 'Membership or billing',
        'shop' => 'Shop or order',
        'place' => 'Place information',
        'technical' => 'Technical problem or site error',
        'accessibility' => 'Accessibility',
        'privacy' => 'Privacy or legal',
    ];
}


function llama_support_contact_methods(): array
{
    return [
        'email' => 'Email',
        'text' => 'Text',
        'phone' => 'Phone Call',
    ];
}


function llama_support_normalize_phone(
    string $phone
): ?string {
    $phone = trim($phone);

    if ($phone === '') {
        return null;
    }

    $digits =
        preg_replace(
            '/\D+/',
            '',
            $phone
        )
        ?? '';

    if (strlen($digits) === 10) {
        return '+1' . $digits;
    }

    if (
        strlen($digits) === 11
        && str_starts_with(
            $digits,
            '1'
        )
    ) {
        return '+' . $digits;
    }

    if (
        str_starts_with(
            $phone,
            '+'
        )
        && strlen($digits) >= 7
        && strlen($digits) <= 15
    ) {
        return '+' . $digits;
    }

    throw new InvalidArgumentException(
        'Enter a complete phone number. US numbers may be entered with or without +1. International numbers must include the country code.'
    );
}


function llama_support_format_phone(
    ?string $phone
): string {
    $phone =
        trim(
            (string) $phone
        );

    if ($phone === '') {
        return '';
    }

    $digits =
        preg_replace(
            '/\D+/',
            '',
            $phone
        )
        ?? '';

    if (
        strlen($digits) === 11
        && str_starts_with(
            $digits,
            '1'
        )
    ) {
        return sprintf(
            '(%s) %s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 4)
        );
    }

    return $phone;
}


function llama_support_contact_method_label(
    ?string $method
): string {
    $method =
        trim(
            (string) $method
        );

    return llama_support_contact_methods()[
        $method
    ]
        ?? 'Email';
}


function llama_support_csrf_token(): string
{
    if (
        empty($_SESSION['support_csrf_token'])
        || !is_string($_SESSION['support_csrf_token'])
    ) {
        $_SESSION['support_csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['support_csrf_token'];
}


function llama_support_verify_csrf(string $token): bool
{
    $known = (string) (
        $_SESSION['support_csrf_token']
        ?? ''
    );

    return $known !== ''
        && $token !== ''
        && hash_equals($known, $token);
}


function llama_support_normalize_error_reference(
    ?string $reference
): ?string {
    $reference = strtoupper(
        trim((string) $reference)
    );

    if ($reference === '') {
        return null;
    }

    if (
        !preg_match(
            '/^LS-[A-Z0-9]{8,32}$/',
            $reference
        )
    ) {
        return null;
    }

    return $reference;
}


function llama_support_ticket_base(): string
{
    return gmdate('ymd-His');
}


function llama_support_ticket_candidate(
    string $base,
    int $attempt
): string {
    if ($attempt <= 1) {
        return $base;
    }

    return $base
        . '-'
        . str_pad(
            (string) $attempt,
            2,
            '0',
            STR_PAD_LEFT
        );
}


function llama_support_ip_hash(): ?string
{
    $ip = trim(
        (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    );

    if ($ip === '') {
        return null;
    }

    return hash(
        'sha256',
        'llamascout-support|' . $ip
    );
}


function llama_support_rate_limit_ok(
    PDO $db,
    string $email,
    ?string $ipHash
): bool {
    $email = strtolower(trim($email));

    $where = [
        'created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)',
    ];

    $params = [];

    if ($email !== '') {
        $where[] = 'LOWER(email) = ?';
        $params[] = $email;
    }

    if ($ipHash !== null && $ipHash !== '') {
        $where[] = 'requester_ip_hash = ?';
        $params[] = $ipHash;
    }

    if (count($where) === 1) {
        return true;
    }

    $sql =
        'SELECT COUNT(*)
         FROM support_requests
         WHERE '
         . $where[0]
         . ' AND ('
         . implode(
             ' OR ',
             array_slice($where, 1)
         )
         . ')';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() < 5;
}


function llama_support_create(
    PDO $db,
    array $data,
    ?array $user = null
): int {
    $categories = llama_support_categories();
    $contactMethods =
        llama_support_contact_methods();

    $name = trim(
        (string) ($data['name'] ?? '')
    );

    $email = strtolower(
        trim(
            (string) ($data['email'] ?? '')
        )
    );

    $phoneNumber =
        llama_support_normalize_phone(
            (string) (
                $data['phone_number']
                ?? ''
            )
        );

    $preferredContact =
        trim(
            (string) (
                $data['preferred_contact']
                ?? 'email'
            )
        );

    $category = trim(
        (string) ($data['category'] ?? 'general')
    );

    $subject = trim(
        (string) ($data['subject'] ?? '')
    );

    $message = trim(
        (string) ($data['message'] ?? '')
    );

    $orderNumber = trim(
        (string) ($data['order_number'] ?? '')
    );

    $errorReference =
        llama_support_normalize_error_reference(
            (string) (
                $data['error_reference']
                ?? ''
            )
        );

    if ($name === '') {
        throw new InvalidArgumentException(
            'Enter your name.'
        );
    }

    if (
        $email === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        throw new InvalidArgumentException(
            'Enter a valid email address.'
        );
    }

    if (
        !isset(
            $contactMethods[
                $preferredContact
            ]
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid preferred contact method.'
        );
    }

    if (
        in_array(
            $preferredContact,
            [
                'text',
                'phone',
            ],
            true
        )
        && $phoneNumber === null
    ) {
        throw new InvalidArgumentException(
            'Enter a phone number if you prefer a text or phone call.'
        );
    }

    if (!isset($categories[$category])) {
        throw new InvalidArgumentException(
            'Choose a valid support category.'
        );
    }

    if ($subject === '') {
        throw new InvalidArgumentException(
            'Enter a subject.'
        );
    }

    if (mb_strlen($subject) > 180) {
        throw new InvalidArgumentException(
            'Subject must be 180 characters or fewer.'
        );
    }

    if ($message === '') {
        throw new InvalidArgumentException(
            'Enter a message.'
        );
    }

    if (mb_strlen($message) > 10000) {
        throw new InvalidArgumentException(
            'Message must be 10,000 characters or fewer.'
        );
    }

    if (mb_strlen($name) > 150) {
        throw new InvalidArgumentException(
            'Name must be 150 characters or fewer.'
        );
    }

    if (mb_strlen($orderNumber) > 100) {
        throw new InvalidArgumentException(
            'Order number must be 100 characters or fewer.'
        );
    }

    $ipHash = llama_support_ip_hash();

    if (
        !llama_support_rate_limit_ok(
            $db,
            $email,
            $ipHash
        )
    ) {
        throw new InvalidArgumentException(
            'Too many support requests were submitted recently. Please wait before sending another.'
        );
    }

    $userId = $user && !empty($user['id'])
        ? (int) $user['id']
        : null;

    $ticketBase =
        llama_support_ticket_base();

    $requestId = 0;

    for ($attempt = 1; $attempt <= 99; $attempt++) {
        $ticketNumber =
            llama_support_ticket_candidate(
                $ticketBase,
                $attempt
            );

        try {
            $stmt = $db->prepare(
                'INSERT INTO support_requests
                 (
                    ticket_number,
                    user_id,
                    name,
                    email,
                    phone_number,
                    preferred_contact,
                    category,
                    subject,
                    message,
                    order_number,
                    error_reference,
                    status,
                    requester_ip_hash,
                    created_at,
                    updated_at
                 )
                 VALUES
                 (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    "open", ?,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                 )'
            );

            $stmt->execute([
                $ticketNumber,
                $userId,
                $name,
                $email,
                $phoneNumber,
                $preferredContact,
                $category,
                $subject,
                $message,
                $orderNumber !== ''
                    ? $orderNumber
                    : null,
                $errorReference,
                $ipHash,
            ]);

            $requestId =
                (int) $db->lastInsertId();

            break;

        } catch (PDOException $exception) {
            if (
                (string) $exception->getCode() !== '23000'
                || $attempt >= 99
            ) {
                throw $exception;
            }
        }
    }

    if ($requestId < 1) {
        throw new RuntimeException(
            'A unique support ticket number could not be generated.'
        );
    }

    llama_support_send_notifications(
        $db,
        $requestId
    );

    return $requestId;
}


function llama_support_request(
    PDO $db,
    int $requestId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            sr.*,
            u.username,
            u.display_name
         FROM support_requests sr
         LEFT JOIN users u
            ON u.id = sr.user_id
         WHERE sr.id = ?
         LIMIT 1'
    );

    $stmt->execute([$requestId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function llama_support_ticket_number(
    PDO $db,
    int $requestId
): string {
    $stmt = $db->prepare(
        'SELECT ticket_number
         FROM support_requests
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$requestId]);

    return trim(
        (string) ($stmt->fetchColumn() ?: '')
    );
}


function llama_support_admin_email(): string
{
    try {
        $config = llama_mail_config();

        $email = trim(
            (string) (
                $config['support_email']
                ?? $config['from_email']
                ?? ''
            )
        );

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
            ? $email
            : '';

    } catch (Throwable $exception) {
        return '';
    }
}


function llama_support_send_notifications(
    PDO $db,
    int $requestId
): void {
    $request = llama_support_request(
        $db,
        $requestId
    );

    if (!$request) {
        return;
    }

    $ticketNumber = trim(
        (string) (
            $request['ticket_number']
            ?? ''
        )
    );

    if ($ticketNumber === '') {
        $ticketNumber =
            (string) $requestId;
    }

    $categories = llama_support_categories();

    $categoryLabel =
        $categories[
            (string) $request['category']
        ]
        ?? 'Support';

    $preferredContactLabel =
        llama_support_contact_method_label(
            (string) (
                $request['preferred_contact']
                ?? 'email'
            )
        );

    $requestDetails = [];

    if (!empty($request['phone_number'])) {
        $requestDetails[] =
            'Phone: '
            . llama_support_format_phone(
                (string) $request['phone_number']
            );
    }

    if (!empty($request['order_number'])) {
        $requestDetails[] =
            'Order: '
            . (string) $request['order_number'];
    }

    if (!empty($request['error_reference'])) {
        $requestDetails[] =
            'Error: '
            . (string) $request['error_reference'];
    }

    $requestDetailsText =
        implode("\n", $requestDetails);

    $adminEmail =
        llama_support_admin_email();

    if (
        $adminEmail !== ''
        && empty($request['admin_notified_at'])
    ) {
        $adminContext = [
            'ticket_number' =>
                $ticketNumber,
            'support_category' =>
                $categoryLabel,
            'requester_name' =>
                (string) $request['name'],
            'requester_email' =>
                (string) $request['email'],
            'preferred_contact' =>
                $preferredContactLabel,
            'request_details' =>
                $requestDetailsText,
            'ticket_subject' =>
                (string) $request['subject'],
            'ticket_message' =>
                (string) $request['message'],
            'admin_ticket_url' =>
                'https://admin.llamascout.com/support.php?id='
                . $requestId,
        ];

        try {
            $sent = llama_email_send_template(
                $db,
                'support_admin_new_ticket',
                $adminEmail,
                $adminContext,
                false,
                null
            );

            if ($sent) {
                $db->prepare(
                    'UPDATE support_requests
                     SET admin_notified_at = UTC_TIMESTAMP()
                     WHERE id = ?'
                )->execute([$requestId]);
            }

        } catch (Throwable $exception) {
            if (
                function_exists(
                    'llama_log_caught_exception'
                )
            ) {
                llama_log_caught_exception(
                    $exception,
                    'support.admin_notification',
                    [
                        'support_request_id' =>
                            $requestId,
                        'ticket_number' =>
                            $ticketNumber,
                    ]
                );
            }
        }
    }

    $customerEmail = trim(
        (string) $request['email']
    );

    if (
        filter_var(
            $customerEmail,
            FILTER_VALIDATE_EMAIL
        )
        && empty($request['customer_confirmed_at'])
    ) {
        $customerExtraDetails = [];

        if (!empty($request['error_reference'])) {
            $customerExtraDetails[] =
                'Error reference: '
                . (string) $request['error_reference'];
        }

        $customerContext = [
            'requester_name' =>
                trim((string) $request['name']) !== ''
                    ? trim((string) $request['name'])
                    : 'there',
            'ticket_number' =>
                $ticketNumber,
            'ticket_subject' =>
                (string) $request['subject'],
            'preferred_contact' =>
                $preferredContactLabel,
            'ticket_extra_details' =>
                implode("\n", $customerExtraDetails),
            'support_url' =>
                'https://llamascout.com/contact.php',
        ];

        try {
            $sent = llama_email_send_template(
                $db,
                'support_ticket_received',
                $customerEmail,
                $customerContext,
                false,
                !empty($request['user_id'])
                    ? (int) $request['user_id']
                    : null
            );

            if ($sent) {
                $db->prepare(
                    'UPDATE support_requests
                     SET customer_confirmed_at = UTC_TIMESTAMP()
                     WHERE id = ?'
                )->execute([$requestId]);
            }

        } catch (Throwable $exception) {
            if (
                function_exists(
                    'llama_log_caught_exception'
                )
            ) {
                llama_log_caught_exception(
                    $exception,
                    'support.customer_confirmation',
                    [
                        'support_request_id' =>
                            $requestId,
                        'ticket_number' =>
                            $ticketNumber,
                    ]
                );
            }
        }
    }
}


function llama_support_requests(
    PDO $db,
    string $status = 'open'
): array {
    $allowed = [
        'open',
        'waiting',
        'resolved',
        'all',
    ];

    if (!in_array($status, $allowed, true)) {
        $status = 'open';
    }

    $sql =
        'SELECT
            sr.*,
            u.username,
            u.display_name
         FROM support_requests sr
         LEFT JOIN users u
            ON u.id = sr.user_id';

    $params = [];

    if ($status !== 'all') {
        $sql .= ' WHERE sr.status = ?';
        $params[] = $status;
    }

    $sql .=
        ' ORDER BY
            CASE sr.status
                WHEN "open" THEN 1
                WHEN "waiting" THEN 2
                ELSE 3
            END,
            sr.created_at DESC
          LIMIT 300';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function llama_support_send_status_notification(
    PDO $db,
    int $requestId,
    string $oldStatus,
    string $newStatus
): void {
    if ($oldStatus === $newStatus) {
        return;
    }

    $request = llama_support_request(
        $db,
        $requestId
    );

    if (!$request) {
        return;
    }

    $email = trim(
        (string) ($request['email'] ?? '')
    );

    if (
        $email === ''
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return;
    }

    $ticketNumber = trim(
        (string) (
            $request['ticket_number']
            ?? ''
        )
    );

    if ($ticketNumber === '') {
        $ticketNumber = (string) $requestId;
    }

    $templateKey = match ($newStatus) {
        'waiting' =>
            'support_ticket_waiting',
        'resolved' =>
            'support_ticket_resolved',
        default =>
            'support_ticket_reopened',
    };

    $context = [
        'requester_name' =>
            trim((string) ($request['name'] ?? '')) !== ''
                ? trim((string) $request['name'])
                : 'there',
        'ticket_number' =>
            $ticketNumber,
        'ticket_subject' =>
            (string) ($request['subject'] ?? ''),
        'support_url' =>
            'https://llamascout.com/contact.php',
    ];

    try {
        llama_email_send_template(
            $db,
            $templateKey,
            $email,
            $context,
            false,
            !empty($request['user_id'])
                ? (int) $request['user_id']
                : null
        );
    } catch (Throwable $exception) {
        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {
            llama_log_caught_exception(
                $exception,
                'support.status_notification',
                [
                    'support_request_id' =>
                        $requestId,
                    'ticket_number' =>
                        $ticketNumber,
                    'old_status' =>
                        $oldStatus,
                    'new_status' =>
                        $newStatus,
                ]
            );
        }
    }
}


function llama_support_update(
    PDO $db,
    int $requestId,
    string $status,
    string $internalNotes
): void {
    $allowed = [
        'open',
        'waiting',
        'resolved',
    ];

    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException(
            'Choose a valid support status.'
        );
    }

    if (mb_strlen($internalNotes) > 10000) {
        throw new InvalidArgumentException(
            'Internal notes must be 10,000 characters or fewer.'
        );
    }

    $currentStmt = $db->prepare(
        'SELECT status
         FROM support_requests
         WHERE id = ?
         LIMIT 1'
    );

    $currentStmt->execute([
        $requestId,
    ]);

    $oldStatus = trim(
        (string) (
            $currentStmt->fetchColumn()
            ?: ''
        )
    );

    if ($oldStatus === '') {
        throw new InvalidArgumentException(
            'Support ticket not found.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE support_requests
         SET
            status = ?,
            internal_notes = ?,
            resolved_at = CASE
                WHEN ? = "resolved"
                    THEN COALESCE(
                        resolved_at,
                        UTC_TIMESTAMP()
                    )
                ELSE NULL
            END,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $stmt->execute([
        $status,
        trim($internalNotes) !== ''
            ? trim($internalNotes)
            : null,
        $status,
        $requestId,
    ]);

    llama_support_send_status_notification(
        $db,
        $requestId,
        $oldStatus,
        $status
    );
}


/* =========================================================
   SUPPORT EMAIL MAINTENANCE

   Support tickets are stored before email is attempted. If the
   mail server is temporarily unavailable, the ticket remains
   valid and unsent initial notifications are retried later during
   authenticated site activity.
   ========================================================= */

function llama_support_email_maintenance_storage_available(
    PDO $db
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'app_maintenance',
    ]);

    return (bool) $stmt->fetchColumn();
}


function llama_support_email_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 600
): bool {
    $intervalSeconds = max(
        300,
        $intervalSeconds
    );

    if (
        !llama_support_email_maintenance_storage_available(
            $db
        )
    ) {
        throw new RuntimeException(
            'Support email maintenance storage is not initialized. Missing table: app_maintenance'
        );
    }

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'support_email_notifications',
    ]);

    $lastRun = $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    try {
        $timestamp =
            (
                new DateTimeImmutable(
                    (string) $lastRun,
                    new DateTimeZone('UTC')
                )
            )->getTimestamp();
    } catch (Throwable) {
        return true;
    }

    return
        (time() - $timestamp)
        >= $intervalSeconds;
}


function llama_support_mark_email_maintenance_run(
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
        'support_email_notifications',
    ]);
}


function llama_run_support_email_maintenance(
    PDO $db,
    int $limit = 10
): array {
    $summary = [
        'ran' => false,
        'tickets' => 0,
    ];

    if (!llama_mail_delivery_enabled()) {
        return $summary;
    }

    if (
        !llama_support_email_maintenance_is_due(
            $db
        )
    ) {
        return $summary;
    }

    $lockStmt = $db->query(
        "SELECT GET_LOCK('llamascout_support_email', 0)"
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
            !llama_support_email_maintenance_is_due(
                $db
            )
        ) {
            return $summary;
        }

        $summary['ran'] = true;
        $limit = max(
            1,
            min(50, $limit)
        );

        $stmt = $db->query(
            'SELECT id
             FROM support_requests
             WHERE
                (
                    admin_notified_at IS NULL
                    OR customer_confirmed_at IS NULL
                )
               AND created_at <= DATE_SUB(
                    UTC_TIMESTAMP(),
                    INTERVAL 2 MINUTE
               )
             ORDER BY created_at ASC, id ASC
             LIMIT ' . $limit
        );

        $requestIds = $stmt
            ? (
                $stmt->fetchAll(
                    PDO::FETCH_COLUMN
                )
                ?: []
            )
            : [];

        foreach ($requestIds as $requestId) {
            llama_support_send_notifications(
                $db,
                (int) $requestId
            );

            $summary['tickets']++;
        }

        llama_support_mark_email_maintenance_run(
            $db
        );

        return $summary;
    } finally {
        try {
            $db->query(
                "SELECT RELEASE_LOCK('llamascout_support_email')"
            );
        } catch (Throwable) {
            // Connection cleanup releases the lock.
        }
    }
}
