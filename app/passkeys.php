<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   PASSKEY STORAGE FOUNDATION

   This file intentionally contains storage and account-policy
   helpers only. WebAuthn ceremony verification will be added
   through a dedicated audited library before passkey login is
   enabled. No cryptographic WebAuthn verification is performed
   here.
   ========================================================= */


function llama_passkey_user_handle(
    PDO $db,
    int $userId
): string {
    if ($userId < 1) {
        throw new InvalidArgumentException('A valid user ID is required.');
    }

    $stmt = $db->prepare(
        'SELECT user_handle
         FROM user_passkey_identities
         WHERE user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $handle = $stmt->fetchColumn();

    if (is_string($handle) && $handle !== '') {
        return $handle;
    }

    $handle = random_bytes(32);

    $stmt = $db->prepare(
        'INSERT INTO user_passkey_identities
        (
            user_id,
            user_handle
        )
        VALUES (?, ?)'
    );

    try {
        $stmt->execute([$userId, $handle]);
        return $handle;
    } catch (Throwable $exception) {
        /* Another request may have created it first. */
        $stmt = $db->prepare(
            'SELECT user_handle
             FROM user_passkey_identities
             WHERE user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $existing = $stmt->fetchColumn();

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        throw $exception;
    }
}


function llama_passkey_credentials(
    PDO $db,
    int $userId
): array {
    $stmt = $db->prepare(
        'SELECT
            id,
            label,
            transports,
            backup_eligible,
            backup_state,
            created_at,
            last_used_at
         FROM user_passkeys
         WHERE user_id = ?
           AND revoked_at IS NULL
         ORDER BY created_at ASC, id ASC'
    );

    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


function llama_passkey_count(
    PDO $db,
    int $userId
): int {
    $stmt = $db->prepare(
        'SELECT COUNT(*)
         FROM user_passkeys
         WHERE user_id = ?
           AND revoked_at IS NULL'
    );

    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}


function llama_passkey_account_is_owner(
    PDO $db,
    int $userId
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM user_roles ur
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = ?
           AND r.slug = "owner"
         LIMIT 1'
    );

    $stmt->execute([$userId]);

    return (bool) $stmt->fetchColumn();
}
