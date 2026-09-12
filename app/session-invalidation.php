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


    $stmt =
        $db->prepare(
            '
            SELECT session_invalidated_at
            FROM users
            WHERE id = ?
            LIMIT 1
            '
        );

    $stmt->execute([
        $userId
    ]);

    $invalidatedAt =
        trim(
            (string) (
                $stmt->fetchColumn()
                ?: ''
            )
        );

    if ($invalidatedAt === '') {
        return;
    }


    try {
        $invalidatedTimestamp =
            (
                new DateTimeImmutable(
                    $invalidatedAt,
                    new DateTimeZone('UTC')
                )
            )->getTimestamp();
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout session invalidation timestamp error: '
            . $exception->getMessage()
        );

        return;
    }


    if (
        $loggedInAt > 0
        && $loggedInAt > $invalidatedTimestamp
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
        $stmt =
            $db->prepare(
                '
                UPDATE users
                SET
                    session_invalidated_at = UTC_TIMESTAMP(),
                    last_seen_at = NULL
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
