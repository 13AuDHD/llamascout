<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/username-policy.php';
require_once __DIR__ . '/timezone.php';


function llama_account_info_schema_ready(PDO $db): bool
{
    $requiredColumns = [
        'phone_number',
        'pending_email',
        'full_name',
        'address_line_1',
        'address_line_2',
        'address_city',
        'address_state',
        'address_postal_code',
        'address_country',
        'address_latitude',
        'address_longitude',
        'timezone',
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
        $stmt->execute([$column]);

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

    return (bool) $tableStmt->fetchColumn();
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
                full_name,
                phone_number,
                address_line_1,
                address_line_2,
                address_city,
                address_state,
                address_postal_code,
                address_country,
                address_latitude,
                address_longitude,
                timezone,
                status,
                email_verified_at,
                password_hash,
                anonymized_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

    $stmt->execute([$userId]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

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


function llama_account_info_format_phone(
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


function llama_account_info_clean_text(
    mixed $value,
    int $maxLength,
    string $label
): ?string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return null;
    }

    if (
        mb_strlen($value)
        > $maxLength
    ) {
        throw new InvalidArgumentException(
            $label
            . ' is too long.'
        );
    }

    return $value;
}


function llama_account_info_coordinate(
    mixed $value,
    float $minimum,
    float $maximum
): ?float {
    if (
        $value === null
        || trim(
            (string) $value
        ) === ''
    ) {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $number = (float) $value;

    if (
        $number < $minimum
        || $number > $maximum
    ) {
        return null;
    }

    return $number;
}


function llama_account_info_save_public_identity(
    PDO $db,
    int $userId,
    string $displayName,
    string $username
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

    $stmt =
        $db->prepare(
            'UPDATE users
             SET
                display_name = ?,
                username = ?
             WHERE id = ?
               AND anonymized_at IS NULL'
        );

    $stmt->execute([
        $displayName,
        $username,
        $userId,
    ]);
}


function llama_account_info_save_identity(
    PDO $db,
    int $userId,
    string $displayName,
    string $username,
    string $phone
): void {
    llama_account_info_save_public_identity(
        $db,
        $userId,
        $displayName,
        $username
    );

    $phoneNumber =
        llama_account_info_normalize_phone(
            $phone
        );

    $stmt =
        $db->prepare(
            'UPDATE users
             SET phone_number = ?
             WHERE id = ?
               AND anonymized_at IS NULL'
        );

    $stmt->execute([
        $phoneNumber,
        $userId,
    ]);
}


function llama_account_info_save_private_details(
    PDO $db,
    int $userId,
    array $input
): void {
    $fullName =
        llama_account_info_clean_text(
            $input['full_name']
            ?? '',
            150,
            'Full name'
        );

    $phoneNumber =
        llama_account_info_normalize_phone(
            (string) (
                $input['phone_number']
                ?? ''
            )
        );

    $addressLine1 =
        llama_account_info_clean_text(
            $input['address_line_1']
            ?? '',
            190,
            'Street address'
        );

    $addressLine2 =
        llama_account_info_clean_text(
            $input['address_line_2']
            ?? '',
            190,
            'Apartment, suite, or unit'
        );

    $city =
        llama_account_info_clean_text(
            $input['address_city']
            ?? '',
            120,
            'City'
        );

    $state =
        llama_account_info_clean_text(
            $input['address_state']
            ?? '',
            120,
            'State or region'
        );

    $postalCode =
        llama_account_info_clean_text(
            $input['address_postal_code']
            ?? '',
            32,
            'ZIP or postal code'
        );

    $country =
        llama_account_info_clean_text(
            $input['address_country']
            ?? '',
            120,
            'Country'
        );

    $timezone =
        trim(
            (string) (
                $input['timezone']
                ?? ''
            )
        );

    if (
        !llama_timezone_is_valid(
            $timezone
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid timezone.'
        );
    }

    $hasAddress =
        $addressLine1 !== null
        || $addressLine2 !== null
        || $city !== null
        || $state !== null
        || $postalCode !== null
        || $country !== null;

    if (
        $hasAddress
        && (
            $addressLine1 === null
            || $city === null
            || $state === null
            || $postalCode === null
        )
    ) {
        throw new InvalidArgumentException(
            'Complete the street address, city, state or region, and ZIP or postal code, or clear all address fields.'
        );
    }

    if (
        $hasAddress
        && $country === null
    ) {
        $country =
            'United States';
    }

    $latitude =
        llama_account_info_coordinate(
            $input['address_latitude']
            ?? null,
            -90,
            90
        );

    $longitude =
        llama_account_info_coordinate(
            $input['address_longitude']
            ?? null,
            -180,
            180
        );

    if (!$hasAddress) {
        $latitude = null;
        $longitude = null;
        $country = null;
    }

    $stmt =
        $db->prepare(
            'UPDATE users
             SET
                full_name = ?,
                phone_number = ?,
                address_line_1 = ?,
                address_line_2 = ?,
                address_city = ?,
                address_state = ?,
                address_postal_code = ?,
                address_country = ?,
                address_latitude = ?,
                address_longitude = ?,
                timezone = ?
             WHERE id = ?
               AND anonymized_at IS NULL'
        );

    $stmt->execute([
        $fullName,
        $phoneNumber,
        $addressLine1,
        $addressLine2,
        $city,
        $state,
        $postalCode,
        $country,
        $latitude,
        $longitude,
        $timezone,
        $userId,
    ]);

    llama_reset_viewer_timezone_cache();
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
        'email' => $newEmail,
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
        'email' => $newEmail,
        'sent' => $sent,
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
                'email' => $email,
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
        'email' => $email,
        'sent' => $sent,
    ];
}
