<?php

declare(strict_types=1);

/*
 * Membership lifecycle email worker.
 *
 * Stripe remains responsible only for billing state. This worker
 * reads the resulting local state and sends lifecycle email after
 * the state has safely landed in the database.
 */

require_once __DIR__ . '/mail.php';


const LLAMA_MEMBERSHIP_EMAIL_INTERVAL_SECONDS = 60;
const LLAMA_LLAMAVERSARY_GRACE_DAYS = 7;


function llama_membership_email_storage_ready(
    PDO $db
): bool {
    $requiredTables = [
        'email_templates',
        'email_send_log',
        'email_event_deliveries',
        'users',
    ];

    foreach ($requiredTables as $table) {
        if (
            !llama_email_table_exists(
                $db,
                $table
            )
        ) {
            return false;
        }
    }

    return true;
}


function llama_membership_email_event_key(
    string $prefix,
    string $identity
): string {
    return
        $prefix
        . ':'
        . substr(
            hash(
                'sha256',
                $identity
            ),
            0,
            24
        );
}


function llama_membership_email_event_sent(
    PDO $db,
    int $userId,
    string $eventKey
): bool {
    $stmt =
        $db->prepare(
            'SELECT 1
             FROM email_event_deliveries
             WHERE user_id = ?
               AND event_key = ?
             LIMIT 1'
        );

    $stmt->execute([
        $userId,
        $eventKey,
    ]);

    return
        (bool) $stmt->fetchColumn();
}


function llama_membership_email_mark_event(
    PDO $db,
    int $userId,
    string $eventKey
): void {
    $stmt =
        $db->prepare(
            'INSERT INTO email_event_deliveries
             (
                user_id,
                event_key,
                sent_at
             )
             VALUES
             (
                ?,
                ?,
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                sent_at =
                    COALESCE(
                        sent_at,
                        VALUES(sent_at)
                    )'
        );

    $stmt->execute([
        $userId,
        $eventKey,
    ]);
}


function llama_membership_email_date(
    ?string $value,
    ?string $timezone = null
): string {
    $value =
        trim(
            (string) $value
        );

    if ($value === '') {
        return 'the end of your current access period';
    }

    $timezone =
        trim(
            (string) $timezone
        );

    try {
        $viewerZone =
            new DateTimeZone(
                $timezone !== ''
                    ? $timezone
                    : 'UTC'
            );
    } catch (Throwable) {
        $viewerZone =
            new DateTimeZone('UTC');
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )
            ->setTimezone($viewerZone)
            ->format('F j, Y');
    } catch (Throwable) {
        return $value;
    }
}


function llama_membership_email_plan(
    ?string $interval
): string {
    return match (
        strtolower(
            trim(
                (string) $interval
            )
        )
    ) {
        'annual' =>
            'Annual',

        'monthly' =>
            'Monthly',

        default =>
            'Complete Access',
    };
}


function llama_membership_email_ordinal(
    int $number
): string {
    $number = max(1, $number);
    $mod100 = $number % 100;

    if ($mod100 >= 11 && $mod100 <= 13) {
        $suffix = 'th';
    } else {
        $suffix = match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    return $number . $suffix;
}


function llama_membership_email_anniversary_date(
    string $value,
    ?string $timezone = null
): string {
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $timezone = trim((string) $timezone);

    try {
        $viewerZone = new DateTimeZone(
            $timezone !== ''
                ? $timezone
                : 'UTC'
        );
    } catch (Throwable) {
        $viewerZone = new DateTimeZone('UTC');
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )
            ->setTimezone($viewerZone)
            ->format('F j, Y');
    } catch (Throwable) {
        return $value;
    }
}


function llama_membership_email_anniversary_window(): array
{
    $utc = new DateTimeZone('UTC');
    $today = new DateTimeImmutable('today', $utc);
    $window = [];

    for (
        $offset = 0;
        $offset <= LLAMA_LLAMAVERSARY_GRACE_DAYS;
        $offset++
    ) {
        $date = $today->modify('-' . $offset . ' days');
        $monthDay = $date->format('m-d');

        $window[$monthDay] = $date;

        /*
         * A Feb 29 signup celebrates on Feb 28 in non-leap years.
         */
        if (
            $monthDay === '02-28'
            && $date->format('L') !== '1'
        ) {
            $window['02-29'] = $date;
        }
    }

    return $window;
}


function llama_membership_email_anniversary_match(
    string $createdAt,
    array $window
): ?array {
    $createdAt = trim($createdAt);

    if ($createdAt === '') {
        return null;
    }

    try {
        $created = new DateTimeImmutable(
            $createdAt,
            new DateTimeZone('UTC')
        );
    } catch (Throwable) {
        return null;
    }

    $monthDay = $created->format('m-d');

    if (!isset($window[$monthDay])) {
        return null;
    }

    $anniversaryDate = $window[$monthDay];
    $years =
        (int) $anniversaryDate->format('Y')
        - (int) $created->format('Y');

    if ($years < 1) {
        return null;
    }

    return [
        'years' => $years,
        'date' => $anniversaryDate->format('Y-m-d'),
    ];
}


function llama_membership_email_user_context(
    array $user
): array {
    $displayName =
        trim(
            (string) (
                $user['display_name']
                ?? ''
            )
        );

    if ($displayName === '') {
        $displayName =
            trim(
                (string) (
                    $user['username']
                    ?? ''
                )
            );
    }

    if ($displayName === '') {
        $displayName = 'Scout';
    }

    $endsAt =
        llama_membership_email_date(
            $user['membership_ends_at']
            ?? null,
            $user['timezone']
            ?? null
        );
    $anniversaryYears =
        max(
            0,
            (int) (
                $user['anniversary_years']
                ?? 0
            )
        );

    $yearsWithUs =
        $anniversaryYears === 1
            ? '1 year'
            : $anniversaryYears . ' years';

    $memberSince =
        llama_membership_email_anniversary_date(
            (string) (
                $user['created_at']
                ?? ''
            ),
            $user['timezone']
            ?? null
        );

    return [
        'display_name' =>
            $displayName,

        'username' =>
            (string) (
                $user['username']
                ?? ''
            ),

        'years_with_us' =>
            $yearsWithUs,

        'anniversary_number' =>
            $anniversaryYears > 0
                ? llama_membership_email_ordinal(
                    $anniversaryYears
                )
                : '',

        'member_since' =>
            $memberSince,

        'membership_plan' =>
            llama_membership_email_plan(
                $user['membership_interval']
                ?? null
            ),

        'membership_renews_at' =>
            $endsAt,

        'membership_ends_at' =>
            $endsAt,

        'account_url' =>
            'https://account.llamascout.com/membership.php',

        'membership_url' =>
            'https://llamascout.com/membership.php',

        'map_url' =>
            'https://llamascout.com/map.php',

        'demo_report_url' =>
            'https://llamascout.com/scout-report-demo.php',
    ];
}


function llama_membership_email_send_once(
    PDO $db,
    array $user,
    string $templateKey,
    string $eventKey
): bool {
    $userId =
        (int) (
            $user['id']
            ?? 0
        );

    if ($userId < 1) {
        return false;
    }

    if (
        !llama_membership_lifecycle_email_enabled(
            $db,
            $templateKey
        )
    ) {
        return false;
    }

    if (
        llama_membership_email_event_sent(
            $db,
            $userId,
            $eventKey
        )
    ) {
        return false;
    }

    $sent =
        send_membership_lifecycle_email(
            $db,
            $user,
            $templateKey,
            llama_membership_email_user_context(
                $user
            )
        );

    if (!$sent) {
        return false;
    }

    llama_membership_email_mark_event(
        $db,
        $userId,
        $eventKey
    );

    return true;
}


function llama_membership_email_maintenance_storage_available(
    PDO $db
): bool {
    return
        llama_email_table_exists(
            $db,
            'app_maintenance'
        );
}


function llama_membership_email_maintenance_due(
    PDO $db
): bool {
    if (
        !llama_membership_email_maintenance_storage_available(
            $db
        )
    ) {
        return true;
    }

    $stmt =
        $db->prepare(
            'SELECT last_run_at
             FROM app_maintenance
             WHERE maintenance_key = ?
             LIMIT 1'
        );

    $stmt->execute([
        'membership_lifecycle_email',
    ]);

    $lastRun =
        trim(
            (string) (
                $stmt->fetchColumn()
                ?: ''
            )
        );

    if ($lastRun === '') {
        return true;
    }

    try {
        $timestamp =
            (
                new DateTimeImmutable(
                    $lastRun,
                    new DateTimeZone('UTC')
                )
            )->getTimestamp();
    } catch (Throwable) {
        return true;
    }

    return
        (
            time()
            - $timestamp
        )
        >= LLAMA_MEMBERSHIP_EMAIL_INTERVAL_SECONDS;
}


function llama_membership_email_mark_maintenance_run(
    PDO $db
): void {
    if (
        !llama_membership_email_maintenance_storage_available(
            $db
        )
    ) {
        return;
    }

    $stmt =
        $db->prepare(
            'INSERT INTO app_maintenance
             (
                maintenance_key,
                last_run_at
             )
             VALUES
             (
                ?,
                UTC_TIMESTAMP()
             )
             ON DUPLICATE KEY UPDATE
                last_run_at =
                    UTC_TIMESTAMP()'
        );

    $stmt->execute([
        'membership_lifecycle_email',
    ]);
}


function llama_membership_email_anniversary_candidates(
    PDO $db
): array {
    $window = llama_membership_email_anniversary_window();
    $monthDays = array_keys($window);

    if (!$monthDays) {
        return [];
    }

    $placeholders =
        implode(
            ', ',
            array_fill(
                0,
                count($monthDays),
                '?'
            )
        );

    $stmt = $db->prepare(
        'SELECT
            id,
            email,
            username,
            display_name,
            timezone,
            membership_interval,
            membership_ends_at,
            created_at
         FROM users
         WHERE email_verified_at IS NOT NULL
           AND anonymized_at IS NULL
           AND (
                status IS NULL
                OR status NOT IN
                (
                    "suspended",
                    "disabled"
                )
           )
           AND created_at IS NOT NULL
           AND created_at <= DATE_SUB(
                UTC_TIMESTAMP(),
                INTERVAL 1 YEAR
           )
           AND DATE_FORMAT(
                created_at,
                "%m-%d"
           ) IN ('
           . $placeholders
           . ')
         ORDER BY id ASC'
    );

    $stmt->execute($monthDays);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    $candidates = [];

    foreach ($rows as $row) {
        $match =
            llama_membership_email_anniversary_match(
                (string) (
                    $row['created_at']
                    ?? ''
                ),
                $window
            );

        if (!$match) {
            continue;
        }

        $row['anniversary_years'] =
            (int) $match['years'];
        $row['anniversary_date'] =
            (string) $match['date'];

        $candidates[] = $row;
    }

    return $candidates;
}


function llama_membership_email_paid_candidates(
    PDO $db,
    int $limit
): array {
    $limit =
        max(
            1,
            min(
                50,
                $limit
            )
        );

    $stmt =
        $db->query(
            'SELECT
                id,
                email,
                username,
                display_name,
                timezone,
                membership_status,
                membership_interval,
                membership_started_at,
                membership_ends_at,
                stripe_subscription_id,
                stripe_cancel_at_period_end
             FROM users
             WHERE email_verified_at IS NOT NULL
               AND stripe_subscription_id IS NOT NULL
               AND stripe_subscription_id <> ""
               AND membership_status IN
               (
                    "active",
                    "trialing",
                    "past_due",
                    "canceled"
               )
             ORDER BY id ASC
             LIMIT '
             . $limit
        );

    return
        $stmt
            ? (
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
            )
            : [];
}


function llama_membership_email_complimentary_candidates(
    PDO $db,
    int $limit
): array {
    if (
        !llama_email_table_exists(
            $db,
            'membership_grants'
        )
    ) {
        return [];
    }

    $limit =
        max(
            1,
            min(
                50,
                $limit
            )
        );

    $stmt =
        $db->query(
            'SELECT
                g.id AS grant_id,
                g.starts_at AS grant_starts_at,
                g.ends_at AS membership_ends_at,
                u.id,
                u.email,
                u.username,
                u.display_name,
                u.timezone,
                u.membership_interval
             FROM membership_grants g
             INNER JOIN users u
                ON u.id = g.user_id
             WHERE g.grant_type = "complimentary"
               AND g.revoked_at IS NULL
               AND g.starts_at <= UTC_TIMESTAMP()
               AND g.ends_at > UTC_TIMESTAMP()
               AND u.email_verified_at IS NOT NULL
             ORDER BY g.ends_at ASC, g.id ASC
             LIMIT '
             . $limit
        );

    return
        $stmt
            ? (
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                )
                ?: []
            )
            : [];
}


function llama_run_membership_email_maintenance(
    PDO $db,
    int $limit = 10
): array {
    $summary = [
        'ran' => false,
        'sent' => 0,
        'failed' => 0,
    ];

    if (!llama_mail_delivery_enabled()) {
        return $summary;
    }

    if (
        !llama_membership_email_storage_ready(
            $db
        )
        || !llama_membership_email_maintenance_due(
            $db
        )
    ) {
        return $summary;
    }

    $lockStmt =
        $db->prepare(
            'SELECT GET_LOCK(?, 0)'
        );

    $lockStmt->execute([
        'llamascout_membership_lifecycle_email',
    ]);

    if (
        (int) $lockStmt->fetchColumn()
        !== 1
    ) {
        return $summary;
    }

    try {
        if (
            !llama_membership_email_maintenance_due(
                $db
            )
        ) {
            return $summary;
        }

        $summary['ran'] = true;

        $limit =
            max(
                1,
                min(
                    25,
                    $limit
                )
            );

        $anniversaryProcessed = 0;

        foreach (
            llama_membership_email_anniversary_candidates(
                $db
            )
            as $user
        ) {
            try {
                if ($anniversaryProcessed >= $limit) {
                    break;
                }

                $years =
                    (int) (
                        $user['anniversary_years']
                        ?? 0
                    );

                if ($years < 1) {
                    continue;
                }

                $eventKey =
                    'llamaversary:'
                    . $years;

                if (
                    llama_membership_email_event_sent(
                        $db,
                        (int) (
                            $user['id']
                            ?? 0
                        ),
                        $eventKey
                    )
                ) {
                    continue;
                }

                $anniversaryProcessed++;

                if (
                    llama_membership_email_send_once(
                        $db,
                        $user,
                        'llamaversary',
                        $eventKey
                    )
                ) {
                    $summary['sent']++;
                }
            } catch (Throwable $exception) {
                $summary['failed']++;

                if (
                    function_exists(
                        'llama_log_caught_exception'
                    )
                ) {
                    llama_log_caught_exception(
                        $exception,
                        'membership.llamaversary_email',
                        [
                            'user_id' =>
                                (int) (
                                    $user['id']
                                    ?? 0
                                ),

                            'anniversary_years' =>
                                (int) (
                                    $user['anniversary_years']
                                    ?? 0
                                ),
                        ]
                    );
                }
            }
        }

        foreach (
            llama_membership_email_paid_candidates(
                $db,
                $limit
            )
            as $user
        ) {
            try {
                $status =
                    strtolower(
                        trim(
                            (string) (
                                $user['membership_status']
                                ?? ''
                            )
                        )
                    );

                $subscriptionId =
                    trim(
                        (string) (
                            $user['stripe_subscription_id']
                            ?? ''
                        )
                    );

                $endsAt =
                    trim(
                        (string) (
                            $user['membership_ends_at']
                            ?? ''
                        )
                    );

                $startedAt =
                    trim(
                        (string) (
                            $user['membership_started_at']
                            ?? ''
                        )
                    );

                if (
                    in_array(
                        $status,
                        [
                            'active',
                            'trialing',
                        ],
                        true
                    )
                    && $startedAt !== ''
                ) {
                    $key =
                        llama_membership_email_event_key(
                            'membership_started',
                            $subscriptionId
                        );

                    if (
                        llama_membership_email_send_once(
                            $db,
                            $user,
                            'membership_started',
                            $key
                        )
                    ) {
                        $summary['sent']++;
                    }
                }

                if (
                    !empty(
                        $user['stripe_cancel_at_period_end']
                    )
                    && in_array(
                        $status,
                        [
                            'active',
                            'trialing',
                            'past_due',
                        ],
                        true
                    )
                    && $endsAt !== ''
                ) {
                    $key =
                        llama_membership_email_event_key(
                            'membership_cancel',
                            $subscriptionId
                            . '|'
                            . $endsAt
                        );

                    if (
                        llama_membership_email_send_once(
                            $db,
                            $user,
                            'membership_cancel_scheduled',
                            $key
                        )
                    ) {
                        $summary['sent']++;
                    }
                }

                if (
                    $status === 'past_due'
                ) {
                    $key =
                        llama_membership_email_event_key(
                            'membership_payment_failed',
                            $subscriptionId
                            . '|'
                            . $endsAt
                        );

                    if (
                        llama_membership_email_send_once(
                            $db,
                            $user,
                            'membership_payment_failed',
                            $key
                        )
                    ) {
                        $summary['sent']++;
                    }
                }

                if (
                    $status === 'canceled'
                ) {
                    $key =
                        llama_membership_email_event_key(
                            'membership_ended',
                            $subscriptionId
                            . '|'
                            . $startedAt
                        );

                    if (
                        llama_membership_email_send_once(
                            $db,
                            $user,
                            'membership_ended',
                            $key
                        )
                    ) {
                        $summary['sent']++;
                    }
                }

            } catch (Throwable $exception) {
                $summary['failed']++;

                if (
                    function_exists(
                        'llama_log_caught_exception'
                    )
                ) {
                    llama_log_caught_exception(
                        $exception,
                        'membership.lifecycle_email',
                        [
                            'user_id' =>
                                (int) (
                                    $user['id']
                                    ?? 0
                                ),
                        ]
                    );
                }
            }
        }

        foreach (
            llama_membership_email_complimentary_candidates(
                $db,
                $limit
            )
            as $user
        ) {
            try {
                $grantId =
                    (int) (
                        $user['grant_id']
                        ?? 0
                    );

                if ($grantId < 1) {
                    continue;
                }

                $startedKey =
                    'complimentary_started:'
                    . $grantId;

                if (
                    llama_membership_email_send_once(
                        $db,
                        $user,
                        'complimentary_started',
                        $startedKey
                    )
                ) {
                    $summary['sent']++;
                }

                $endsAt =
                    trim(
                        (string) (
                            $user['membership_ends_at']
                            ?? ''
                        )
                    );

                if ($endsAt !== '') {
                    try {
                        $endsTimestamp =
                            (
                                new DateTimeImmutable(
                                    $endsAt,
                                    new DateTimeZone('UTC')
                                )
                            )->getTimestamp();
                    } catch (Throwable) {
                        $endsTimestamp = 0;
                    }

                    $secondsRemaining =
                        $endsTimestamp > 0
                            ? (
                                $endsTimestamp
                                - time()
                            )
                            : PHP_INT_MAX;

                    if (
                        $secondsRemaining > 0
                        && $secondsRemaining
                            <= (7 * 86400)
                    ) {
                        $endingKey =
                            'complimentary_ending:'
                            . $grantId;

                        if (
                            llama_membership_email_send_once(
                                $db,
                                $user,
                                'complimentary_ending',
                                $endingKey
                            )
                        ) {
                            $summary['sent']++;
                        }
                    }
                }
            } catch (Throwable $exception) {
                $summary['failed']++;

                if (
                    function_exists(
                        'llama_log_caught_exception'
                    )
                ) {
                    llama_log_caught_exception(
                        $exception,
                        'membership.complimentary_email',
                        [
                            'user_id' =>
                                (int) (
                                    $user['id']
                                    ?? 0
                                ),

                            'grant_id' =>
                                (int) (
                                    $user['grant_id']
                                    ?? 0
                                ),
                        ]
                    );
                }
            }
        }

        llama_membership_email_mark_maintenance_run(
            $db
        );

        return $summary;

    } finally {
        try {
            $release =
                $db->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );

            $release->execute([
                'llamascout_membership_lifecycle_email',
            ]);
        } catch (Throwable) {
            /*
             * MySQL connection cleanup releases the advisory lock.
             */
        }
    }
}
