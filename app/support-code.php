<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   SUPPORT VERIFICATION CODE

   A member-created support PIN can be verified repeatedly by
   authorized support staff during a phone call. The same PIN
   may be used for one MFA reset only. Replacing the PIN while
   signed in and strongly authenticated re-arms that one reset.
   ========================================================= */


const LLAMA_SUPPORT_PIN_DIGITS = 8;
const LLAMA_SUPPORT_PIN_MAX_FAILURES = 5;
const LLAMA_SUPPORT_PIN_LOCK_MINUTES = 15;


function llama_support_pin_normalize(
    string $pin
): string {
    return preg_replace('/\D+/', '', $pin) ?? '';
}


function llama_support_pin_validate_format(
    string $pin
): string {
    $pin = llama_support_pin_normalize($pin);

    if (strlen($pin) !== LLAMA_SUPPORT_PIN_DIGITS) {
        throw new InvalidArgumentException(
            'Support PIN must be exactly 8 digits.'
        );
    }

    if (preg_match('/^(\d)\1{7}$/', $pin)) {
        throw new InvalidArgumentException(
            'Choose a less predictable support PIN.'
        );
    }

    return $pin;
}


function llama_support_pin_record(
    PDO $db,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            user_id,
            pin_hash,
            created_at,
            updated_at,
            last_verified_at,
            mfa_reset_used_at,
            failed_attempts,
            locked_until
         FROM user_support_codes
         WHERE user_id = ?
         LIMIT 1'
    );

    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}


function llama_support_pin_is_set(
    PDO $db,
    int $userId
): bool {
    $record = llama_support_pin_record($db, $userId);

    return is_array($record)
        && !empty($record['pin_hash']);
}


function llama_support_pin_can_reset_mfa(
    PDO $db,
    int $userId
): bool {
    $record = llama_support_pin_record($db, $userId);

    return is_array($record)
        && !empty($record['pin_hash'])
        && empty($record['mfa_reset_used_at']);
}


function llama_support_phone_number(
    PDO $db,
    int $userId
): ?string {
    $stmt = $db->prepare(
        'SELECT phone_number
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$userId]);
    $phone = trim((string) ($stmt->fetchColumn() ?: ''));

    return $phone !== '' ? $phone : null;
}


function llama_support_pin_set(
    PDO $db,
    int $userId,
    string $pin
): void {
    if ($userId < 1) {
        throw new InvalidArgumentException('A valid user ID is required.');
    }

    if (llama_support_phone_number($db, $userId) === null) {
        throw new RuntimeException(
            'Add a phone number to Account Information before setting a support PIN.'
        );
    }

    $pin = llama_support_pin_validate_format($pin);
    $hash = password_hash($pin, PASSWORD_DEFAULT);

    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Support PIN could not be secured.');
    }

    $stmt = $db->prepare(
        'INSERT INTO user_support_codes
        (
            user_id,
            pin_hash,
            failed_attempts,
            locked_until,
            last_verified_at,
            mfa_reset_used_at
        )
        VALUES
        (
            ?,
            ?,
            0,
            NULL,
            NULL,
            NULL
        )
        ON DUPLICATE KEY UPDATE
            pin_hash = VALUES(pin_hash),
            failed_attempts = 0,
            locked_until = NULL,
            last_verified_at = NULL,
            mfa_reset_used_at = NULL,
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([$userId, $hash]);
}


function llama_support_pin_remove(
    PDO $db,
    int $userId
): void {
    $stmt = $db->prepare(
        'DELETE FROM user_support_codes
         WHERE user_id = ?'
    );

    $stmt->execute([$userId]);
}


function llama_support_pin_verify(
    PDO $db,
    int $userId,
    string $pin
): bool {
    $record = llama_support_pin_record($db, $userId);

    if (!is_array($record) || empty($record['pin_hash'])) {
        return false;
    }

    $lockedUntil = trim((string) ($record['locked_until'] ?? ''));

    if ($lockedUntil !== '') {
        try {
            $lock = new DateTimeImmutable($lockedUntil, new DateTimeZone('UTC'));

            if ($lock->getTimestamp() > time()) {
                throw new RuntimeException(
                    'Support verification is temporarily locked after repeated failed attempts.'
                );
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Ignore malformed historical lock values and continue to verification.
        }
    }

    $pin = llama_support_pin_normalize($pin);
    $verified = $pin !== ''
        && password_verify($pin, (string) $record['pin_hash']);

    if ($verified) {
        $stmt = $db->prepare(
            'UPDATE user_support_codes
             SET
                failed_attempts = 0,
                locked_until = NULL,
                last_verified_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        return true;
    }

    $failures = (int) ($record['failed_attempts'] ?? 0) + 1;

    if ($failures >= LLAMA_SUPPORT_PIN_MAX_FAILURES) {
        $stmt = $db->prepare(
            'UPDATE user_support_codes
             SET
                failed_attempts = 0,
                locked_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE),
                updated_at = UTC_TIMESTAMP()
             WHERE user_id = ?'
        );
        $stmt->execute([
            LLAMA_SUPPORT_PIN_LOCK_MINUTES,
            $userId,
        ]);
    } else {
        $stmt = $db->prepare(
            'UPDATE user_support_codes
             SET
                failed_attempts = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE user_id = ?'
        );
        $stmt->execute([$failures, $userId]);
    }

    return false;
}


function llama_support_pin_reset_mfa(
    PDO $db,
    int $userId,
    string $pin
): void {
    if (!llama_support_pin_verify($db, $userId, $pin)) {
        throw new InvalidArgumentException('Support PIN is not valid.');
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT mfa_reset_used_at
             FROM user_support_codes
             WHERE user_id = ?
             FOR UPDATE'
        );
        $stmt->execute([$userId]);
        $usedAt = $stmt->fetchColumn();

        if ($usedAt !== false && $usedAt !== null && trim((string) $usedAt) !== '') {
            throw new RuntimeException(
                'This support PIN has already been used for its one allowed MFA reset. The member must replace the PIN while signed in to re-arm recovery.'
            );
        }

        $stmt = $db->prepare(
            'DELETE FROM user_mfa_recovery_codes
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        $stmt = $db->prepare(
            'DELETE FROM user_mfa
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        $stmt = $db->prepare(
            'DELETE FROM user_remember_tokens
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);

        try {
            $stmt = $db->prepare(
                'DELETE FROM sessions
                 WHERE user_id = ?'
            );
            $stmt->execute([$userId]);
        } catch (Throwable $exception) {
            error_log(
                'Llama Scout support reset legacy session cleanup error: '
                . $exception->getMessage()
            );
        }

        $stmt = $db->prepare(
            'UPDATE user_support_codes
             SET
                mfa_reset_used_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE user_id = ?
               AND mfa_reset_used_at IS NULL'
        );
        $stmt->execute([$userId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The MFA reset allowance is no longer available.'
            );
        }

        /*
         * Invalidate existing authenticated browsers without removing
         * the member's last-seen history.
         */
        $stmt = $db->prepare(
            'UPDATE users
             SET session_invalidated_at = UTC_TIMESTAMP()
             WHERE id = ?'
        );
        $stmt->execute([$userId]);

        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
