<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   AUTHENTICATED USER PRESENCE

   Presence is intentionally approximate.

   Current presence:
   - Online Now: seen within 5 minutes
   - Active Last Hour: seen within 60 minutes
   - Active Today: seen since the current day boundary

   Historical presence:
   - One row per authenticated user per UTC day
   - Records the first and last activity timestamps
   - Records which UTC hours contained activity
   - Allows Analytics to calculate unique active accounts
     across 24H, 7D, 30D, QTD, and YTD

   Authenticated requests normally touch presence at most
   once every two minutes per session.

   An hour change bypasses the two-minute session throttle so
   brief activity immediately after an hour boundary is still
   recorded in the historical hourly activity mask.
   ========================================================= */


const LLAMA_PRESENCE_WRITE_INTERVAL_SECONDS =
    120;


/**
 * Record authenticated account presence.
 *
 * The users.last_seen_at column remains the source for the
 * live dashboard counters.
 *
 * user_activity_daily supplies persistent historical activity
 * for Analytics.
 */
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


    $lastHourKey =
        (string) (
            $_SESSION[
                'llama_presence_hour_key'
            ]
            ?? ''
        );


    $now =
        time();


    /*
     * Historical activity is stored in UTC.
     *
     * Analytics can later translate the stored hour buckets
     * into the administrator's selected timezone without
     * changing how the raw activity is recorded.
     */
    $currentHourKey =
        gmdate(
            'YmdH',
            $now
        );


    /*
     * Normal requests are limited to one presence write every
     * two minutes.
     *
     * If the UTC hour changed, allow the request through even
     * when two minutes have not elapsed. This ensures the new
     * hour is represented in activity_hours_mask.
     */
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
        &&
        $lastHourKey ===
            $currentHourKey
    ) {

        return;
    }


    try {

        /*
         * Maintain the existing live-presence timestamp.
         *
         * The SQL condition also protects against unnecessary
         * writes when several tabs are open for the same user.
         */
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
         * Record persistent historical activity.
         *
         * Each account gets at most one row per UTC date.
         *
         * activity_hours_mask uses one bit for each hour:
         *
         * hour 00 = bit 0
         * hour 01 = bit 1
         * ...
         * hour 23 = bit 23
         *
         * Repeated activity during the same hour simply keeps
         * that hour's bit enabled.
         */
        $activityStmt =
            $db->prepare(
                '
                INSERT INTO user_activity_daily (
                    user_id,
                    activity_date,
                    activity_hours_mask,
                    first_seen_at,
                    last_seen_at
                )

                SELECT
                    u.id,
                    UTC_DATE(),
                    (
                        1 <<
                        HOUR(
                            UTC_TIMESTAMP()
                        )
                    ),
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()

                FROM users u

                WHERE u.id = ?
                  AND u.status = \'active\'

                ON DUPLICATE KEY UPDATE

                    activity_hours_mask =
                        activity_hours_mask
                        |
                        VALUES(
                            activity_hours_mask
                        ),

                    first_seen_at =
                        LEAST(
                            first_seen_at,
                            VALUES(
                                first_seen_at
                            )
                        ),

                    last_seen_at =
                        GREATEST(
                            last_seen_at,
                            VALUES(
                                last_seen_at
                            )
                        )
                '
            );


        $activityStmt->execute([
            $userId
        ]);


        /*
         * Record the attempted touch even if another browser
         * tab already updated users.last_seen_at inside the
         * two-minute database window.
         */
        $_SESSION[
            'llama_presence_user_id'
        ] =
            $userId;


        $_SESSION[
            'llama_presence_touch_at'
        ] =
            $now;


        $_SESSION[
            'llama_presence_hour_key'
        ] =
            $currentHourKey;


    } catch (Throwable $exception) {

        if (
            function_exists(
                'llama_log_caught_exception'
            )
        ) {

            llama_log_caught_exception(
                $exception,
                'presence.touch',
                [
                    'user_id' =>
                        $userId,
                ]
            );

        } else {

            error_log(
                'Llama Scout presence update error: '
                . $exception->getMessage()
            );
        }
    }
}
