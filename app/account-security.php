<?php

declare(strict_types=1);

function llama_account_security_csrf_token(): string
{
    if (empty($_SESSION['account_security_csrf'])) {
        $_SESSION['account_security_csrf'] =
            bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['account_security_csrf'];
}

function llama_account_security_verify_csrf(
    string $submitted
): bool {
    $expected =
        llama_account_security_csrf_token();

    return $submitted !== ''
        && hash_equals(
            $expected,
            $submitted
        );
}

function llama_account_security_user(
    PDO $db,
    int $userId
): ?array {
    $stmt =
        $db->prepare(
            '
            SELECT
                id,
                email,
                username,
                display_name,
                password_hash,
                status
            FROM users
            WHERE id = ?
            LIMIT 1
            '
        );

    $stmt->execute([$userId]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row)
        ? $row
        : null;
}

function llama_account_security_verify_password(
    PDO $db,
    int $userId,
    string $password
): void {
    $user =
        llama_account_security_user(
            $db,
            $userId
        );

    if (
        !$user
        || !password_verify(
            $password,
            (string) (
                $user['password_hash']
                ?? ''
            )
        )
    ) {
        throw new InvalidArgumentException(
            'Your current password is not correct.'
        );
    }
}

function llama_account_security_verify_totp(
    PDO $db,
    int $userId,
    string $code
): void {
    if (
        !llama_mfa_authenticate_totp(
            $userId,
            $code,
            $db
        )
    ) {
        throw new InvalidArgumentException(
            'That authentication code is not valid.'
        );
    }
}

function llama_account_security_regenerate_recovery_codes(
    PDO $db,
    int $userId,
    string $password,
    string $totpCode
): array {
    if (
        !llama_mfa_is_enabled(
            $userId,
            $db
        )
    ) {
        throw new RuntimeException(
            'Multi-factor authentication is not enabled.'
        );
    }

    llama_account_security_verify_password(
        $db,
        $userId,
        $password
    );

    llama_account_security_verify_totp(
        $db,
        $userId,
        $totpCode
    );

    $codes =
        llama_mfa_replace_recovery_codes(
            $userId,
            $db
        );

    llama_mfa_invalidate_remember_tokens(
        $userId,
        $db
    );

    return $codes;
}

function llama_account_security_disable_mfa(
    PDO $db,
    int $userId,
    string $password,
    string $totpCode
): void {
    if (
        llama_mfa_role_requires_mfa(
            $userId,
            $db
        )
    ) {
        throw new RuntimeException(
            'Multi-factor authentication is required for Owner and Admin accounts.'
        );
    }

    if (
        !llama_mfa_is_enabled(
            $userId,
            $db
        )
    ) {
        throw new RuntimeException(
            'Multi-factor authentication is not enabled.'
        );
    }

    llama_account_security_verify_password(
        $db,
        $userId,
        $password
    );

    llama_account_security_verify_totp(
        $db,
        $userId,
        $totpCode
    );

    llama_mfa_reset(
        $userId,
        $db
    );

    llama_mfa_invalidate_remember_tokens(
        $userId,
        $db
    );

    llama_mfa_clear_session_state();
}
