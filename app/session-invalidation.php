<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   AUTHENTICATED SESSION INVALIDATION

   PHP sessions are stored independently from the legacy
   sessions table. This layer gives the application a reliable
   per-user "sign out everywhere" mechanism.

   An admin invalidation stamps users.session_invalidated_at.
   Every authenticated request compares that timestamp with the
   session's logged_in_at value. Older sessions are destroyed
   before the request can continue.
   ========================================================= */


function llama_enforce_session_invalidation(
    PDO $db
): void {

    $userId =
        (int) (
            $_SESSION['user_id']
            ?? 0
        );

    if ($userId < 1) {
        return;
    }


    $loggedInAt =
        (int) (
            $_SESSION['logged_in_at']
            ?? 0
        );


    /*
     * Ask MySQL for an epoch value directly. This avoids any
     * PHP / SQL timezone interpretation differences.
     */
    $stmt =
        $db->prepare(
            '
            SELECT
                UNIX_TIMESTAMP(
                    session_invalidated_at
                )
            FROM users
            WHERE id = ?
            LIMIT 1
            '
        );

    $stmt->execute([
        $userId
    ]);


    $invalidatedAt =
        (int) (
            $stmt->fetchColumn()
            ?: 0
        );


    if ($invalidatedAt < 1) {
        return;
    }


    /*
     * A session with no login timestamp is not trusted once an
     * invalidation exists. Sessions created at or before the
     * invalidation moment are also rejected.
     */
    if (
        $loggedInAt > 0
        && $loggedInAt > $invalidatedAt
    ) {
        return;
    }


    logout_user();
}


function llama_invalidate_user_authentication(
    PDO $db,
    int $userId
): void {

    if ($userId < 1) {
        throw new InvalidArgumentException(
            'A valid user ID is required.'
        );
    }


    $db->beginTransaction();

    try {
        /*
         * Do not erase last_seen_at here. It is useful historical
         * information and lets Basecamp show when the account was
         * last active before it was revoked.
         */
        $stmt =
            $db->prepare(
                '
                UPDATE users
                SET session_invalidated_at =
                    UTC_TIMESTAMP()
                WHERE id = ?
                '
            );

        $stmt->execute([
            $userId
        ]);


        /*
         * Remember-me tokens can otherwise restore a signed-out
         * member after their PHP session has been invalidated.
         */
        $stmt =
            $db->prepare(
                '
                DELETE FROM user_remember_tokens
                WHERE user_id = ?
                '
            );

        $stmt->execute([
            $userId
        ]);


        /*
         * Keep cleaning the legacy sessions table while it still
         * exists, but do not rely on it for authentication state.
         */
        try {
            $stmt =
                $db->prepare(
                    '
                    DELETE FROM sessions
                    WHERE user_id = ?
                    '
                );

            $stmt->execute([
                $userId
            ]);
        } catch (Throwable $exception) {
            error_log(
                'Llama Scout legacy session cleanup error: '
                . $exception->getMessage()
            );
        }


        $db->commit();

    } catch (Throwable $exception) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
