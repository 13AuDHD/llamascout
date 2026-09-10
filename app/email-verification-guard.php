<?php

declare(strict_types=1);

/*
 * Verified-email gate for authenticated sessions.
 *
 * Login itself may establish a session for an unverified account so the
 * existing Verify Email / Resend Verification workflow can still identify
 * that account. Normal application pages are blocked here until verification
 * is complete.
 */

function llama_enforce_verified_email_session(PDO $db): void
{
    $userId =
        (int) (
            $_SESSION['user_id']
            ?? 0
        );

    if ($userId < 1) {
        return;
    }

    $stmt =
        $db->prepare(
            'SELECT
                email_verified_at,
                status,
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

    if (!$row) {
        return;
    }

    if (
        !empty($row['anonymized_at'])
        || in_array(
            strtolower(
                (string) (
                    $row['status']
                    ?? ''
                )
            ),
            [
                'suspended',
                'disabled',
            ],
            true
        )
    ) {
        return;
    }

    if (!empty($row['email_verified_at'])) {
        return;
    }

    $host =
        strtolower(
            trim(
                (string) (
                    $_SERVER['HTTP_HOST']
                    ?? ''
                )
            )
        );

    $script =
        strtolower(
            trim(
                (string) (
                    $_SERVER['SCRIPT_NAME']
                    ?? ''
                )
            )
        );

    /*
     * Some auth pages intentionally bypass bootstrap.php already, but keep
     * an explicit allowlist here in case they are routed through it later.
     */
    $allowedScripts = [
        '/verify-email.php',
        '/resend-verification.php',
        '/logout.php',
        '/login.php',
        '/register.php',
        '/complimentary-invite.php',
        '/verify-email-change.php',
    ];

    if (
        str_contains(
            $host,
            'account.llamascout.com'
        )
        && in_array(
            $script,
            $allowedScripts,
            true
        )
    ) {
        return;
    }

    header(
        'Location: https://account.llamascout.com/verify-email.php',
        true,
        303
    );

    exit;
}
