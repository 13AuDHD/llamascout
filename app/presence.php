<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   AUTHENTICATED USER PRESENCE

   Presence is intentionally approximate.

   - Online Now: seen within 5 minutes
   - Active Last Hour: seen within 60 minutes
   - Active Today: seen since midnight Mountain Time

   Authenticated requests touch presence at most once every
   two minutes per session. The SQL condition also prevents
   unnecessary writes from multiple open tabs.
   ========================================================= */


const LLAMA_PRESENCE_WRITE_INTERVAL_SECONDS =
    120;


function llama_presence_touch(
    PDO $db,
    int $userId
): void {

    if ($userId < 1) {
        return;
    }


    start_llama_session();


    $sessionUserId =
        (int) (
            $_SESSION[
                'llama_presence_user_id'
            ]
            ?? 0
        );


    $lastTouch =
        (int) (
            $_SESSION[
                'llama_presence_touch_at'
            ]
            ?? 0
        );


    $now =
        time();


    if (
        $sessionUserId ===
            $userId
        &&
        $lastTouch > 0
        &&
        (
            $now -
            $lastTouch
        ) <
            LLAMA_PRESENCE_WRITE_INTERVAL_SECONDS
    ) {

        return;
    }


    $stmt =
        $db->prepare(
            '
            UPDATE users

            SET last_seen_at =
                UTC_TIMESTAMP()

            WHERE id = ?
              AND status = \'active\'
              AND (
                    last_seen_at IS NULL
                    OR last_seen_at <
                        DATE_SUB(
                            UTC_TIMESTAMP(),
                            INTERVAL 2 MINUTE
                        )
                  )
            '
        );


    $stmt->execute([
        $userId
    ]);


    /*
     * Record the attempted touch even if another browser tab
     * already updated the DB inside the two-minute window.
     */
    $_SESSION[
        'llama_presence_user_id'
    ] =
        $userId;


    $_SESSION[
        'llama_presence_touch_at'
    ] =
        $now;
}
