<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT PLACE FRESHNESS

   Field freshness is about physical observation at a Place.
   It is deliberately separate from official-source checks.

   A new Place starts freshness only when its approved Place
   report contains a real visited_at date. Submission, approval,
   publication, and other processing dates never count as field
   observations.

   Later geofenced check-ins and field verifications can refresh
   the observation date.

   Public states:
   - fresh: observed within 180 days
   - aging: observed 181 to 365 days ago
   - attention: observed more than 365 days ago
   - never: no physical field observation is recorded

   Community freshness includes Community + Member activity.
   Scout freshness includes Scout + Master Scout + Admin activity,
   owner-created Place reports with a real visit date, and legacy
   Llama Scout field-verified records.
   ========================================================= */

function llama_place_freshness_thresholds(): array
{
    return [
        'fresh_days' => 180,
        'attention_days' => 365,
    ];
}

function llama_place_freshness_timestamp(?string $value): ?int
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $timestamp = strtotime(
        $value
        . (
            strlen($value) === 10
                ? ' 00:00:00 UTC'
                : ' UTC'
        )
    );

    return $timestamp === false
        ? null
        : $timestamp;
}

function llama_place_freshness_latest_value(?string ...$values): ?string
{
    $latestValue = null;
    $latestTimestamp = null;

    foreach ($values as $value) {
        $timestamp =
            llama_place_freshness_timestamp(
                $value
            );

        if ($timestamp === null) {
            continue;
        }

        if (
            $latestTimestamp === null
            || $timestamp > $latestTimestamp
        ) {
            $latestTimestamp = $timestamp;
            $latestValue = trim((string) $value);
        }
    }

    return $latestValue;
}

function llama_place_freshness_days_since(?string $value): ?int
{
    $timestamp =
        llama_place_freshness_timestamp(
            $value
        );

    if ($timestamp === null) {
        return null;
    }

    $today =
        new DateTimeImmutable(
            'today',
            new DateTimeZone('UTC')
        );

    $checked =
        (new DateTimeImmutable(
            '@' . $timestamp
        ))
            ->setTimezone(
                new DateTimeZone('UTC')
            )
            ->setTime(0, 0);

    if ($checked > $today) {
        return 0;
    }

    return
        (int) $checked
            ->diff($today)
            ->days;
}

function llama_place_freshness_state(?string $value): string
{
    $days =
        llama_place_freshness_days_since(
            $value
        );

    if ($days === null) {
        return 'never';
    }

    $thresholds =
        llama_place_freshness_thresholds();

    if (
        $days
        <= (int) $thresholds['fresh_days']
    ) {
        return 'fresh';
    }

    if (
        $days
        <= (int) $thresholds['attention_days']
    ) {
        return 'aging';
    }

    return 'attention';
}

function llama_place_freshness_state_label(string $state): string
{
    return match ($state) {
        'fresh' => 'Fresh',
        'aging' => 'Aging',
        'attention' => 'Needs attention',
        default => 'Never checked',
    };
}

function llama_place_freshness_state_description(string $state): string
{
    return match ($state) {
        'fresh' =>
            'This Place has a field observation within the last six months.',

        'aging' =>
            'The last field observation was between six months and one year ago.',

        'attention' =>
            'It has been more than one year since the last field observation.',

        default =>
            'No physical field observation is recorded yet.',
    };
}

function llama_place_freshness_relative_label(?string $value): string
{
    $days =
        llama_place_freshness_days_since(
            $value
        );

    if ($days === null) {
        return 'Never checked';
    }

    if ($days === 0) {
        return 'Today';
    }

    if ($days === 1) {
        return '1 day ago';
    }

    if ($days < 60) {
        return
            $days
            . ' days ago';
    }

    if ($days < 365) {
        $months =
            max(
                2,
                (int) floor(
                    $days / 30
                )
            );

        return
            $months
            . ' months ago';
    }

    $years =
        max(
            1,
            (int) floor(
                $days / 365
            )
        );

    $remainingDays =
        $days
        - (
            $years * 365
        );

    if ($remainingDays >= 60) {
        $months =
            (int) floor(
                $remainingDays / 30
            );

        return
            $years
            . ' '
            . (
                $years === 1
                    ? 'year'
                    : 'years'
            )
            . ', '
            . $months
            . ' '
            . (
                $months === 1
                    ? 'month'
                    : 'months'
            )
            . ' ago';
    }

    return
        $years
        . ' '
        . (
            $years === 1
                ? 'year ago'
                : 'years ago'
        );
}

function llama_place_freshness_date_label(?string $value): string
{
    $timestamp =
        llama_place_freshness_timestamp(
            $value
        );

    if ($timestamp === null) {
        return 'No field check recorded';
    }

    return
        gmdate(
            'M j, Y',
            $timestamp
        );
}


/*
 * Convert the role snapshot stored with a new Place contribution
 * into the two public freshness lanes.
 *
 * This helper determines only the lane. It never supplies a date.
 * A new Place contributes to freshness only when visited_at exists.
 */
function llama_place_freshness_creation_lane(
    ?string $roleAtTime,
    ?string $sourceType
): string {
    $role =
        strtolower(
            str_replace(
                '_',
                '-',
                trim(
                    (string) $roleAtTime
                )
            )
        );

    if (
        in_array(
            $role,
            [
                'scout',
                'master-scout',
                'master scout',
                'admin',
                'owner',
            ],
            true
        )
    ) {
        return 'scout';
    }

    if ($role !== '') {
        return 'community';
    }

    $source =
        strtolower(
            trim(
                (string) $sourceType
            )
        );

    if (
        in_array(
            $source,
            [
                'community',
                'community-scouted',
                'member',
                'user',
            ],
            true
        )
    ) {
        return 'community';
    }

    return 'scout';
}


function llama_place_freshness_summary(
    PDO $db,
    int $placeId
): array {
    $empty = [
        'state' =>
            'never',

        'state_label' =>
            llama_place_freshness_state_label(
                'never'
            ),

        'state_description' =>
            llama_place_freshness_state_description(
                'never'
            ),

        'overall_last_checked_at' =>
            null,

        'overall_relative' =>
            'Never checked',

        'overall_date_label' =>
            'No field check recorded',

        'overall_level' =>
            null,

        'overall_level_label' =>
            null,

        'community_last_checked_at' =>
            null,

        'community_relative' =>
            'No check yet',

        'community_date_label' =>
            'No check recorded',

        'scout_last_checked_at' =>
            null,

        'scout_relative' =>
            'No check yet',

        'scout_date_label' =>
            'No check recorded',
    ];

    if ($placeId < 1) {
        return $empty;
    }

    try {
        $stmt =
            $db->prepare(
                'SELECT
                    p.last_field_checked_on,
                    p.source_type,

                    (
                        SELECT MAX(pc.checked_in_at)
                        FROM place_checkins pc
                        WHERE pc.place_id = p.id
                          AND pc.contribution_level IN ("community", "member")
                    ) AS community_last_checked_at,

                    (
                        SELECT MAX(pc.checked_in_at)
                        FROM place_checkins pc
                        WHERE pc.place_id = p.id
                          AND pc.contribution_level IN ("scout", "master-scout", "admin")
                    ) AS scout_checkin_last_checked_at,

                    (
                        SELECT MAX(
                            COALESCE(
                                CONCAT(
                                    pv.visited_at,
                                    " 12:00:00"
                                ),
                                pv.verified_at
                            )
                        )
                        FROM place_verifications pv
                        WHERE pv.place_id = p.id
                          AND pv.verification_type = "field-verified"
                    ) AS legacy_scout_last_checked_at,

                    (
                        SELECT pc.visited_at
                        FROM place_contributions pc
                        WHERE pc.place_id = p.id
                          AND pc.contribution_type = "new_place"
                          AND pc.status = "approved"
                        ORDER BY pc.id ASC
                        LIMIT 1
                    ) AS creation_observed_at,

                    (
                        SELECT pc.role_at_time
                        FROM place_contributions pc
                        WHERE pc.place_id = p.id
                          AND pc.contribution_type = "new_place"
                          AND pc.status = "approved"
                        ORDER BY pc.id ASC
                        LIMIT 1
                    ) AS creation_role_at_time,

                    (
                        SELECT pc.user_id
                        FROM place_contributions pc
                        WHERE pc.place_id = p.id
                          AND pc.contribution_type = "new_place"
                          AND pc.status = "approved"
                        ORDER BY pc.id ASC
                        LIMIT 1
                    ) AS creation_user_id,

                    (
                        SELECT pc.contribution_level
                        FROM place_checkins pc
                        WHERE pc.place_id = p.id
                        ORDER BY pc.checked_in_at DESC, pc.id DESC
                        LIMIT 1
                    ) AS latest_checkin_level,

                    (
                        SELECT pc.checked_in_at
                        FROM place_checkins pc
                        WHERE pc.place_id = p.id
                        ORDER BY pc.checked_in_at DESC, pc.id DESC
                        LIMIT 1
                    ) AS latest_checkin_at

                 FROM places p
                 WHERE p.id = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $placeId,
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            return $empty;
        }


        /*
         * The original Place report is a field observation only
         * when the report contains visited_at.
         *
         * approved_at, submitted_at, and published_at are processing
         * dates and are intentionally excluded.
         */
        $creationObservedAt =
            trim(
                (string) (
                    $row['creation_observed_at']
                    ?? ''
                )
            )
            ?: null;

        $creationRoleAtTime =
            trim(
                (string) (
                    $row['creation_role_at_time']
                    ?? ''
                )
            );

        $creationUserId =
            (int) (
                $row['creation_user_id']
                ?? 0
            );

        $creationLane =
            null;

        if ($creationObservedAt !== null) {
            /*
             * Prefer the centralized contribution-level logic.
             * It also repairs the known legacy Owner record case
             * where some Owner submissions were stored as member.
             */
            if (
                $creationRoleAtTime !== ''
                && $creationUserId > 0
            ) {
                $creationLevel =
                    llama_contribution_level_for_record(
                        $db,
                        [
                            'role_at_time' =>
                                $creationRoleAtTime,

                            'user_id' =>
                                $creationUserId,
                        ]
                    );

                $creationLane =
                    llama_contribution_level_rank(
                        $creationLevel
                    )
                    >=
                    llama_contribution_level_rank(
                        LLAMA_CONTRIBUTION_LEVEL_SCOUT
                    )
                        ? 'scout'
                        : 'community';
            } else {
                $creationLane =
                    llama_place_freshness_creation_lane(
                        $creationRoleAtTime !== ''
                            ? $creationRoleAtTime
                            : null,

                        trim(
                            (string) (
                                $row['source_type']
                                ?? ''
                            )
                        )
                            ?: null
                    );
            }
        }


        $communityCheckinLast =
            trim(
                (string) (
                    $row['community_last_checked_at']
                    ?? ''
                )
            )
            ?: null;

        $communityCreationLast =
            $creationLane === 'community'
                ? $creationObservedAt
                : null;

        $communityLast =
            llama_place_freshness_latest_value(
                $communityCheckinLast,
                $communityCreationLast
            );


        $scoutCheckinLast =
            trim(
                (string) (
                    $row['scout_checkin_last_checked_at']
                    ?? ''
                )
            )
            ?: null;

        $legacyScoutLast =
            trim(
                (string) (
                    $row['legacy_scout_last_checked_at']
                    ?? ''
                )
            )
            ?: null;

        $scoutCreationLast =
            $creationLane === 'scout'
                ? $creationObservedAt
                : null;

        $scoutLast =
            llama_place_freshness_latest_value(
                $scoutCheckinLast,
                $legacyScoutLast,
                $scoutCreationLast
            );


        /*
         * last_field_checked_on is a stored summary of physical
         * field evidence. It is never populated here from workflow
         * or publication dates.
         */
        $storedOverall =
            trim(
                (string) (
                    $row['last_field_checked_on']
                    ?? ''
                )
            )
            ?: null;

        $overallLast =
            llama_place_freshness_latest_value(
                $communityLast,
                $scoutLast,
                $storedOverall
            );


        $latestCheckinAt =
            trim(
                (string) (
                    $row['latest_checkin_at']
                    ?? ''
                )
            )
            ?: null;

        $overallLevel =
            null;

        if (
            $latestCheckinAt !== null
            && llama_place_freshness_timestamp(
                $latestCheckinAt
            )
                === llama_place_freshness_timestamp(
                    $overallLast
                )
        ) {
            $overallLevel =
                llama_contribution_level_normalize(
                    (string) (
                        $row['latest_checkin_level']
                        ?? ''
                    )
                );

        } elseif (
            $overallLast !== null
            && $scoutLast !== null
            && llama_place_freshness_timestamp(
                $overallLast
            )
                === llama_place_freshness_timestamp(
                    $scoutLast
                )
        ) {
            $overallLevel =
                llama_contribution_level_normalize(
                    'scout'
                );

        } elseif (
            $overallLast !== null
            && $communityLast !== null
            && llama_place_freshness_timestamp(
                $overallLast
            )
                === llama_place_freshness_timestamp(
                    $communityLast
                )
        ) {
            $overallLevel =
                llama_contribution_level_normalize(
                    'community'
                );
        }


        $state =
            llama_place_freshness_state(
                $overallLast
            );

        return [
            'state' =>
                $state,

            'state_label' =>
                llama_place_freshness_state_label(
                    $state
                ),

            'state_description' =>
                llama_place_freshness_state_description(
                    $state
                ),

            'overall_last_checked_at' =>
                $overallLast,

            'overall_relative' =>
                llama_place_freshness_relative_label(
                    $overallLast
                ),

            'overall_date_label' =>
                llama_place_freshness_date_label(
                    $overallLast
                ),

            'overall_level' =>
                $overallLevel,

            'overall_level_label' =>
                $overallLevel !== null
                    ? llama_contribution_level_short_label(
                        $overallLevel
                    )
                    : null,

            'community_last_checked_at' =>
                $communityLast,

            'community_relative' =>
                $communityLast !== null
                    ? llama_place_freshness_relative_label(
                        $communityLast
                    )
                    : 'No check yet',

            'community_date_label' =>
                $communityLast !== null
                    ? llama_place_freshness_date_label(
                        $communityLast
                    )
                    : 'No check recorded',

            'scout_last_checked_at' =>
                $scoutLast,

            'scout_relative' =>
                $scoutLast !== null
                    ? llama_place_freshness_relative_label(
                        $scoutLast
                    )
                    : 'No check yet',

            'scout_date_label' =>
                $scoutLast !== null
                    ? llama_place_freshness_date_label(
                        $scoutLast
                    )
                    : 'No check recorded',
        ];

    } catch (Throwable $exception) {
        error_log(
            'Llama Scout Place freshness lookup failed for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );

        return $empty;
    }
}
