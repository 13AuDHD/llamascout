<?php

declare(strict_types=1);

/* =========================================================
   BASECAMP PLACE FRESHNESS

   This admin page is intentionally about physical field
   freshness, not generic verification activity.

   Official-source checks do not reset field freshness.

   A new Place contributes an initial field observation only
   when its approved Place report contains a real visited_at
   date. Submission, approval, and publication dates never
   count as visits.

   Later geofenced check-ins and field verifications can refresh
   the observation date.
   ========================================================= */

function admin_place_freshness_base_rows(
    PDO $db,
    string $search = ''
): array {
    static $cache = [];

    $search = trim($search);

    $cacheKey =
        spl_object_id($db)
        . '|'
        . mb_strtolower(
            $search,
            'UTF-8'
        );

    if (
        array_key_exists(
            $cacheKey,
            $cache
        )
    ) {
        return $cache[$cacheKey];
    }

    $where = [
        'p.status IN ("active", "featured")',
    ];

    $params = [];

    if ($search !== '') {
        $where[] = '(
            p.name LIKE ?
            OR p.city LIKE ?
            OR p.county LIKE ?
            OR p.state LIKE ?
            OR CAST(p.id AS CHAR) = ?
        )';

        $needle =
            '%'
            . $search
            . '%';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle,
            $search
        );
    }

    $sql =
        'SELECT
            p.id,
            p.name,
            p.slug,
            p.status,
            p.source_type,
            p.city,
            p.county,
            p.state,
            p.last_field_checked_on,

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
                SELECT COUNT(*)
                FROM place_reports pr
                WHERE pr.place_id = p.id
                  AND pr.status IN ("open", "investigating")
            ) AS open_report_count

         FROM places p

         WHERE '
        . implode(
            ' AND ',
            $where
        );

    $stmt =
        $db->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    foreach (
        $rows
        as &$row
    ) {
        /*
         * The original Place report counts only when visited_at
         * contains a real field-visit date.
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

        if (
            $creationObservedAt
            !== null
        ) {
            /*
             * Use the shared contribution-level system whenever
             * an original contributor is known.
             *
             * This repairs the legacy case where some Owner
             * submissions were historically stored as member.
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
         * last_field_checked_on is allowed only as a stored
         * summary of actual field evidence. The repair SQL
         * recalculates it from visit evidence only.
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

        $freshnessState =
            llama_place_freshness_state(
                $overallLast
            );

        $row['overall_last_checked_at'] =
            $overallLast;

        $row['community_last_checked_at'] =
            $communityLast;

        $row['scout_last_checked_at'] =
            $scoutLast;

        $row['freshness_state'] =
            $freshnessState;

        $row['freshness_label'] =
            llama_place_freshness_state_label(
                $freshnessState
            );
    }

    unset(
        $row
    );

    /*
     * Put the most urgent Places first.
     */
    usort(
        $rows,
        static function (
            array $a,
            array $b
        ): int {
            $priority = [
                'never' => 1,
                'attention' => 2,
                'aging' => 3,
                'fresh' => 4,
            ];

            $stateCompare =
                (
                    $priority[
                        (string) (
                            $a['freshness_state']
                            ?? 'never'
                        )
                    ]
                    ?? 9
                )
                <=>
                (
                    $priority[
                        (string) (
                            $b['freshness_state']
                            ?? 'never'
                        )
                    ]
                    ?? 9
                );

            if (
                $stateCompare
                !== 0
            ) {
                return $stateCompare;
            }

            $aTimestamp =
                llama_place_freshness_timestamp(
                    isset(
                        $a['overall_last_checked_at']
                    )
                        ? (string) $a['overall_last_checked_at']
                        : null
                );

            $bTimestamp =
                llama_place_freshness_timestamp(
                    isset(
                        $b['overall_last_checked_at']
                    )
                        ? (string) $b['overall_last_checked_at']
                        : null
                );

            if (
                $aTimestamp
                !== $bTimestamp
            ) {
                if (
                    $aTimestamp
                    === null
                ) {
                    return -1;
                }

                if (
                    $bTimestamp
                    === null
                ) {
                    return 1;
                }

                return
                    $aTimestamp
                    <=>
                    $bTimestamp;
            }

            return
                strcasecmp(
                    (string) (
                        $a['name']
                        ?? ''
                    ),
                    (string) (
                        $b['name']
                        ?? ''
                    )
                );
        }
    );

    $cache[$cacheKey] =
        $rows;

    return $rows;
}

function admin_place_freshness_stats(
    PDO $db
): array {
    $rows =
        admin_place_freshness_base_rows(
            $db
        );

    $stats = [
        'total' =>
            count(
                $rows
            ),

        'fresh' =>
            0,

        'aging' =>
            0,

        'attention' =>
            0,

        'never_checked' =>
            0,
    ];

    foreach (
        $rows
        as $row
    ) {
        $state =
            (string) (
                $row['freshness_state']
                ?? 'never'
            );

        if (
            $state
            === 'fresh'
        ) {
            $stats['fresh']++;

        } elseif (
            $state
            === 'aging'
        ) {
            $stats['aging']++;

        } elseif (
            $state
            === 'attention'
        ) {
            $stats['attention']++;

        } else {
            $stats['never_checked']++;
        }
    }

    return $stats;
}

function admin_place_freshness_rows(
    PDO $db,
    string $search = '',
    string $state = '',
    int $limit = 500
): array {
    $search =
        trim(
            $search
        );

    $state =
        trim(
            $state
        );

    $limit =
        max(
            1,
            min(
                5000,
                $limit
            )
        );

    $rows =
        admin_place_freshness_base_rows(
            $db,
            $search
        );

    if (
        in_array(
            $state,
            [
                'fresh',
                'aging',
                'attention',
                'never',
            ],
            true
        )
    ) {
        $rows =
            array_values(
                array_filter(
                    $rows,
                    static fn (
                        array $row
                    ): bool =>
                        (string) (
                            $row['freshness_state']
                            ?? 'never'
                        )
                        === $state
                )
            );
    }

    return
        array_slice(
            $rows,
            0,
            $limit
        );
}

function admin_place_freshness_attention_queue(
    PDO $db,
    int $limit = 100
): array {
    $limit =
        max(
            1,
            min(
                1000,
                $limit
            )
        );

    $rows =
        admin_place_freshness_base_rows(
            $db
        );

    $rows =
        array_values(
            array_filter(
                $rows,
                static fn (
                    array $row
                ): bool =>
                    in_array(
                        (string) (
                            $row['freshness_state']
                            ?? ''
                        ),
                        [
                            'aging',
                            'attention',
                            'never',
                        ],
                        true
                    )
            )
        );

    return
        array_slice(
            $rows,
            0,
            $limit
        );
}

function admin_place_recent_field_checks(
    PDO $db,
    int $limit = 100
): array {
    $limit =
        max(
            1,
            min(
                250,
                $limit
            )
        );

    $sql =
        'SELECT *
         FROM (
            SELECT
                "checkin" AS record_kind,
                pc.id AS record_id,
                pc.place_id,
                pc.user_id,
                pc.contribution_level,
                pc.checked_in_at AS checked_at,
                pc.points_awarded,
                pc.distance_meters,
                pc.accuracy_meters,
                p.name AS place_name,
                p.slug AS place_slug,
                p.status AS place_status,
                p.city,
                p.county,
                p.state,

                COALESCE(
                    NULLIF(
                        u.display_name,
                        ""
                    ),
                    NULLIF(
                        u.username,
                        ""
                    ),
                    "Member"
                ) AS checker_name,

                u.username AS checker_username

            FROM place_checkins pc

            INNER JOIN places p
                ON p.id = pc.place_id

            LEFT JOIN users u
                ON u.id = pc.user_id

            UNION ALL

            SELECT
                "legacy-field" AS record_kind,
                pv.id AS record_id,
                pv.place_id,
                pv.verified_by AS user_id,
                "scout" AS contribution_level,

                COALESCE(
                    CONCAT(
                        pv.visited_at,
                        " 12:00:00"
                    ),
                    pv.verified_at
                ) AS checked_at,

                0 AS points_awarded,
                NULL AS distance_meters,
                NULL AS accuracy_meters,
                p.name AS place_name,
                p.slug AS place_slug,
                p.status AS place_status,
                p.city,
                p.county,
                p.state,

                COALESCE(
                    NULLIF(
                        u.display_name,
                        ""
                    ),
                    NULLIF(
                        u.username,
                        ""
                    ),
                    "Llama Scout"
                ) AS checker_name,

                u.username AS checker_username

            FROM place_verifications pv

            INNER JOIN places p
                ON p.id = pv.place_id

            LEFT JOIN users u
                ON u.id = pv.verified_by

            WHERE pv.verification_type = "field-verified"

              AND NOT EXISTS (
                    SELECT 1
                    FROM place_checkins linked
                    WHERE linked.verification_id = pv.id
              )

         ) field_checks

         ORDER BY
            checked_at DESC,
            record_id DESC

         LIMIT '
        . $limit;

    return
        $db
            ->query(
                $sql
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
        ?: [];
}
