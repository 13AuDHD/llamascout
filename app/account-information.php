<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/username-policy.php';


function llama_account_info_schema_ready(PDO $db): bool
{
    $requiredColumns = [
        'phone_number',
        'pending_email',
    ];

    $stmt =
        $db->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = "users"
               AND column_name = ?
             LIMIT 1'
        );

    foreach ($requiredColumns as $column) {
        $stmt->execute([
            $column,
        ]);

        if (!$stmt->fetchColumn()) {
            return false;
        }
    }

    $tableStmt =
        $db->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = "email_change_verifications"
             LIMIT 1'
        );

    $tableStmt->execute();

    return
        (bool) $tableStmt->fetchColumn();
}


function llama_account_info_user(
    PDO $db,
    int $userId
): ?array {
    $stmt =
        $db->prepare(
            'SELECT
                id,
                email,
                pending_email,
                username,
                display_name,
                phone_number,
                timezone,
                status,
                email_verified_at,
                password_hash,
                anonymized_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $userId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $row ?: null;
}


function llama_account_info_normalize_email(
    string $email
): string {
    $email =
        strtolower(
            trim($email)
        );

    if (
        $email === ''
        || strlen($email) > 254
        || !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid email address.'
        );
    }

    return $email;
}


function llama_account_info_assert_email_available(
    PDO $db,
    string $email,
    int $userId
): void {
    $stmt =
        $db->prepare(
            'SELECT id
             FROM users
             WHERE id <> ?
               AND (
                    LOWER(email) = ?
                    OR LOWER(pending_email) = ?
               )
             LIMIT 1'
        );

    $stmt->execute([
        $userId,
        $email,
        $email,
    ]);

    if ($stmt->fetchColumn()) {
        throw new InvalidArgumentException(
            'That email address is already in use.'
        );
    }
}


function llama_account_info_normalize_phone(
    string $phone
): ?string {
    $phone =
        trim($phone);

    if ($phone === '') {
        return null;
    }

    if (mb_strlen($phone) > 32) {
        throw new InvalidArgumentException(
            'Phone number is too long.'
        );
    }

    if (
        !preg_match(
            '/^[0-9+().\-\sxeXtT]+$/',
            $phone
        )
    ) {
        throw new InvalidArgumentException(
            'Enter a valid phone number.'
        );
    }

    return $phone;
}


function llama_account_info_save_identity(
    PDO $db,
    int $userId,
    string $displayName,
    string $username,
    string $phone
): void {
    $displayName =
        trim($displayName);

    $username =
        strtolower(
            trim($username)
        );

    if (
        mb_strlen($displayName) < 2
        || mb_strlen($displayName) > 100
    ) {
        throw new InvalidArgumentException(
            'Display name must be 2 to 100 characters.'
        );
    }

    $policy =
        username_policy_check(
            $username
        );

    if (empty($policy['allowed'])) {
        throw new InvalidArgumentException(
            (string) (
                $policy['reason']
                ?? 'Choose another username.'
            )
        );
    }

    $duplicate =
        $db->prepare(
            'SELECT id
             FROM users
             WHERE LOWER(username) = ?
               AND id <> ?
             LIMIT 1'
        );

    $duplicate->execute([
        $username,
        $userId,
    ]);

    if ($duplicate->fetchColumn()) {
        throw new InvalidArgumentException(
            'That username is already taken.'
        );
    }

    $phoneNumber =
        llama_account_info_normalize_phone(
            $phone
        );

    $stmt =
        $db->prepare(
            'UPDATE users
             SET
                display_name = ?,
                username = ?,
                phone_number = ?
             WHERE id = ?
               AND anonymized_at IS NULL'
        );

    $stmt->execute([
        $displayName,
        $username,
        $phoneNumber,
        $userId,
    ]);
}


function llama_account_info_request_email_change(
    PDO $db,
    int $userId,
    string $newEmail,
    string $password
): array {
    $newEmail =
        llama_account_info_normalize_email(
            $newEmail
        );

    $user =
        llama_account_info_user(
            $db,
            $userId
        );

    if (!$user) {
        throw new RuntimeException(
            'Account not found.'
        );
    }

    if (
        !password_verify(
            $password,
            (string) (
                $user['password_hash']
                ?? ''
            )
        )
    ) {
        throw new InvalidArgumentException(
            'Your current password is incorrect.'
        );
    }

    if (
        hash_equals(
            strtolower(
                (string) $user['email']
            ),
            $newEmail
        )
    ) {
        throw new InvalidArgumentException(
            'That is already your verified email address.'
        );
    }

    llama_account_info_assert_email_available(
        $db,
        $newEmail,
        $userId
    );

    $token =
        bin2hex(
            random_bytes(32)
        );

    $tokenHash =
        hash(
            'sha256',
            $token
        );

    $db->beginTransaction();

    try {
        $expire =
            $db->prepare(
                'UPDATE email_change_verifications
                 SET used_at = UTC_TIMESTAMP()
                 WHERE user_id = ?
                   AND used_at IS NULL'
            );

        $expire->execute([
            $userId,
        ]);

        $update =
            $db->prepare(
                'UPDATE users
                 SET pending_email = ?
                 WHERE id = ?'
            );

        $update->execute([
            $newEmail,
            $userId,
        ]);

        $insert =
            $db->prepare(
                'INSERT INTO email_change_verifications (
                    user_id,
                    new_email,
                    token_hash,
                    expires_at,
                    created_at
                 ) VALUES (
                    ?,
                    ?,
                    ?,
                    DATE_ADD(
                        UTC_TIMESTAMP(),
                        INTERVAL 24 HOUR
                    ),
                    UTC_TIMESTAMP()
                 )'
            );

        $insert->execute([
            $userId,
            $newEmail,
            $tokenHash,
        ]);

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    $mailUser = [
        'email' =>
            $newEmail,

        'username' =>
            (string) (
                $user['username']
                ?? ''
            ),

        'display_name' =>
            (string) (
                $user['display_name']
                ?? ''
            ),
    ];

    $verificationUrl =
        'https://account.llamascout.com/verify-email-change.php?token='
        . rawurlencode($token);

    $context = [
        'display_name' =>
            trim(
                (string) (
                    $mailUser['display_name']
                    ?: $mailUser['username']
                    ?: 'Scout'
                )
            ),

        'username' =>
            (string) $mailUser['username'],

        'verification_url' =>
            $verificationUrl,
    ];

    $template =
        llama_email_template(
            $db,
            'verify_email'
        );

    $sent = false;

    if ($template) {
        $sent =
            llama_email_send_template(
                $db,
                'verify_email',
                $newEmail,
                $context,
                false,
                $userId
            );
    }

    return [
        'email' =>
            $newEmail,

        'sent' =>
            $sent,
    ];
}


function llama_account_info_cancel_email_change(
    PDO $db,
    int $userId
): void {
    $db->beginTransaction();

    try {
        $stmt =
            $db->prepare(
                'UPDATE email_change_verifications
                 SET used_at = UTC_TIMESTAMP()
                 WHERE user_id = ?
                   AND used_at IS NULL'
            );

        $stmt->execute([
            $userId,
        ]);

        $stmt =
            $db->prepare(
                'UPDATE users
                 SET pending_email = NULL
                 WHERE id = ?'
            );

        $stmt->execute([
            $userId,
        ]);

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function llama_admin_issue_reverification(
    PDO $db,
    int $targetUserId
): array {
    $user =
        llama_account_info_user(
            $db,
            $targetUserId
        );

    if (!$user) {
        throw new RuntimeException(
            'Account not found.'
        );
    }

    if (!empty($user['anonymized_at'])) {
        throw new RuntimeException(
            'An anonymized account cannot be reverified.'
        );
    }

    $email =
        llama_account_info_normalize_email(
            (string) $user['email']
        );

    $token =
        bin2hex(
            random_bytes(32)
        );

    $tokenHash =
        hash(
            'sha256',
            $token
        );

    $db->beginTransaction();

    try {
        $expire =
            $db->prepare(
                'UPDATE email_verifications
                 SET used_at = UTC_TIMESTAMP()
                 WHERE user_id = ?
                   AND used_at IS NULL'
            );

        $expire->execute([
            $targetUserId,
        ]);

        $pendingChange =
            $db->prepare(
                'UPDATE email_change_verifications
                 SET used_at = UTC_TIMESTAMP()
                 WHERE user_id = ?
                   AND used_at IS NULL'
            );

        $pendingChange->execute([
            $targetUserId,
        ]);

        $update =
            $db->prepare(
                'UPDATE users
                 SET
                    email_verified_at = NULL,
                    pending_email = NULL,
                    status = CASE
                        WHEN status = "active"
                        THEN "pending"
                        ELSE status
                    END
                 WHERE id = ?'
            );

        $update->execute([
            $targetUserId,
        ]);

        $insert =
            $db->prepare(
                'INSERT INTO email_verifications (
                    user_id,
                    token_hash,
                    expires_at
                 ) VALUES (
                    ?,
                    ?,
                    DATE_ADD(
                        UTC_TIMESTAMP(),
                        INTERVAL 24 HOUR
                    )
                 )'
            );

        $insert->execute([
            $targetUserId,
            $tokenHash,
        ]);

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    $sent =
        send_verification_email(
            [
                'email' =>
                    $email,

                'username' =>
                    (string) (
                        $user['username']
                        ?? ''
                    ),

                'display_name' =>
                    (string) (
                        $user['display_name']
                        ?? ''
                    ),
            ],
            $token
        );

    return [
        'email' =>
            $email,

        'sent' =>
            $sent,
    ];
}
