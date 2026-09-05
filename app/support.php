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
        'accessibility' => 'Accessibility',
        'privacy' => 'Privacy or legal',
    ];
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

    $name = trim(
        (string) ($data['name'] ?? '')
    );

    $email = strtolower(
        trim(
            (string) ($data['email'] ?? '')
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

    $stmt = $db->prepare(
        'INSERT INTO support_requests
         (
            user_id,
            name,
            email,
            category,
            subject,
            message,
            order_number,
            status,
            requester_ip_hash,
            created_at,
            updated_at
         )
         VALUES
         (
            ?, ?, ?, ?, ?, ?, ?, "open", ?,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
         )'
    );

    $stmt->execute([
        $userId,
        $name,
        $email,
        $category,
        $subject,
        $message,
        $orderNumber !== ''
            ? $orderNumber
            : null,
        $ipHash,
    ]);

    $requestId = (int) $db->lastInsertId();

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

    $categories = llama_support_categories();

    $categoryLabel =
        $categories[
            (string) $request['category']
        ]
        ?? 'Support';

    $adminEmail =
        llama_support_admin_email();

    if ($adminEmail !== '') {
        $adminSubject =
            '[Support #' . $requestId . '] '
            . (string) $request['subject'];

        $adminText =
            "New Llama Scout support request\n\n"
            . "Request: #" . $requestId . "\n"
            . "Category: " . $categoryLabel . "\n"
            . "Name: " . (string) $request['name'] . "\n"
            . "Email: " . (string) $request['email'] . "\n";

        if (!empty($request['order_number'])) {
            $adminText .=
                "Order: "
                . (string) $request['order_number']
                . "\n";
        }

        $adminText .=
            "\n"
            . (string) $request['message']
            . "\n\n"
            . "Admin: https://admin.llamascout.com/support.php?id="
            . $requestId
            . "\n";

        try {
            send_llama_mail(
                $adminEmail,
                $adminSubject,
                $adminText
            );

            $db->prepare(
                'UPDATE support_requests
                 SET admin_notified_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            )->execute([$requestId]);

        } catch (Throwable $exception) {
            if (
                function_exists(
                    'llama_log_caught_exception'
                )
            ) {
                llama_log_caught_exception(
                    $exception,
                    'support.admin_notification',
                    ['support_request_id' => $requestId]
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
    ) {
        $customerSubject =
            'We received your Llama Scout support request';

        $customerText =
            'Hi '
            . (string) $request['name']
            . ",\n\n"
            . "We received your Llama Scout support request.\n\n"
            . "Reference: #"
            . $requestId
            . "\n"
            . "Subject: "
            . (string) $request['subject']
            . "\n\n"
            . "Keep the reference number above if you need to follow up.\n\n"
            . "Llama Scout\n"
            . "Know the place before you go.\n";

        try {
            send_llama_mail(
                $customerEmail,
                $customerSubject,
                $customerText
            );

            $db->prepare(
                'UPDATE support_requests
                 SET customer_confirmed_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            )->execute([$requestId]);

        } catch (Throwable $exception) {
            if (
                function_exists(
                    'llama_log_caught_exception'
                )
            ) {
                llama_log_caught_exception(
                    $exception,
                    'support.customer_confirmation',
                    ['support_request_id' => $requestId]
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
}
