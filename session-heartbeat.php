<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';


start_llama_session();


header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);


if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'OPTIONS'
) {
    http_response_code(204);
    exit;
}


$revoked =
    false;

$revokeReason =
    null;

$sessionUserId =
    (int) (
        $_SESSION[
            'user_id'
        ]
        ?? 0
    );

$loggedInAt =
    (int) (
        $_SESSION[
            'logged_in_at'
        ]
        ?? 0
    );


/*
 * The heartbeat is allowed to identify explicit revocation, but it
 * must not treat a missing or temporarily unavailable PHP session as
 * proof that the user intentionally signed out.
 */
if ($sessionUserId > 0) {
    try {
        $stmt =
            db()->prepare(
                '
                SELECT
                    status,
                    UNIX_TIMESTAMP(
                        session_invalidated_at
                    ) AS session_invalidated_epoch

                FROM users

                WHERE id = ?

                LIMIT 1
                '
            );

        $stmt->execute([
            $sessionUserId
        ]);

        $record =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$record) {
            $revoked =
                true;

            $revokeReason =
                'account_missing';

        } elseif (
            in_array(
                (string) (
                    $record['status']
                    ?? ''
                ),
                [
                    'suspended',
                    'disabled',
                ],
                true
            )
        ) {
            $revoked =
                true;

            $revokeReason =
                'account_disabled';

        } else {
            $invalidatedAt =
                (int) (
                    $record[
                        'session_invalidated_epoch'
                    ]
                    ?? 0
                );

            if (
                $invalidatedAt > 0
                &&
                (
                    $loggedInAt < 1
                    ||
                    $loggedInAt <= $invalidatedAt
                )
            ) {
                $revoked =
                    true;

                $revokeReason =
                    'session_revoked';
            }
        }

        if ($revoked) {
            logout_user();
        }

    } catch (Throwable $exception) {
        error_log(
            'Llama Scout session heartbeat error: '
            .
            $exception->getMessage()
        );

        http_response_code(503);

        echo json_encode(
            [
                'authenticated' =>
                    false,
                'revoked' =>
                    false,
                'revoke_reason' =>
                    null,
                'user_id' =>
                    0,
                'checked_at' =>
                    gmdate('c'),
            ],
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}


$authenticated =
    !$revoked
    &&
    $sessionUserId > 0;


/*
 * Presence is best-effort only. The heartbeat deliberately does not call
 * current_user(), attempt Remember Me recovery, or run the full bootstrap.
 * A passive tab check must never become the authority that destroys or
 * reconstructs authentication state.
 */
if ($authenticated) {
    try {
        llama_presence_touch(
            db(),
            $sessionUserId
        );
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout heartbeat presence error: '
            .
            $exception->getMessage()
        );
    }
}


echo json_encode(
    [
        'authenticated' =>
            $authenticated,
        'revoked' =>
            $revoked,
        'revoke_reason' =>
            $revokeReason,
        'user_id' =>
            $authenticated
                ? $sessionUserId
                : 0,
        'checked_at' =>
            gmdate('c'),
    ],
    JSON_UNESCAPED_SLASHES
);
