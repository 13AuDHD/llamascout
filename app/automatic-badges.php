<?php

declare(strict_types=1);

require_once __DIR__ . '/points.php';
require_once __DIR__ . '/badge-eligibility.php';


function llama_badge_threshold_metric_labels(): array
{
    return [
        'approved_contributions' =>
            'Approved contributions',
        'new_places' =>
            'New Places',
        'place_updates' =>
            'Place updates / corrections',
        'llamaversaries' =>
            'Llamaversaries',
        'total_points' =>
            'Total points',
    ];
}


function llama_badge_threshold_metric_descriptions(): array
{
    return [
        'approved_contributions' =>
            'Counts every approved Place contribution, including new Places, updates, and corrections.',
        'new_places' =>
            'Counts unique approved new Places submitted by the member.',
        'place_updates' =>
            'Counts approved Place updates and factual corrections.',
        'llamaversaries' =>
            'Counts completed years since the member joined Llama Scout.',
        'total_points' =>
            "Uses the member's total contribution points.",
    ];
}


function llama_badge_threshold_metric_is_valid(
    string $metric
): bool {
    return isset(
        llama_badge_threshold_metric_labels()[
            $metric
        ]
    );
}


function llama_badge_threshold_column_ready(
    PDO $db
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'badge_definitions',
        'threshold_metric',
    ]);

    return (bool) $stmt->fetchColumn();
}


function llama_badge_automatic_revocation_storage_ready(
    PDO $db
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'badge_automatic_revocations',
    ]);

    return (bool) $stmt->fetchColumn();
}


function llama_badge_automatic_is_suppressed(
    PDO $db,
    int $userId,
    int $badgeId,
    bool $forUpdate = false
): bool {
    if (
        $userId < 1
        || $badgeId < 1
        || !llama_badge_automatic_revocation_storage_ready($db)
    ) {
        return false;
    }

    $sql =
        'SELECT id
         FROM badge_automatic_revocations
         WHERE user_id = ?
           AND badge_id = ?
           AND cleared_at IS NULL
         LIMIT 1';

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $userId,
        $badgeId,
    ]);

    return (bool) $stmt->fetchColumn();
}


function llama_badge_suppress_automatic_award(
    PDO $db,
    int $userId,
    int $badgeId,
    ?int $revokedUserBadgeId,
    int $revokedBy,
    string $reason
): void {
    if ($userId < 1 || $badgeId < 1) {
        throw new RuntimeException(
            'A valid member and badge are required for automatic badge revocation.'
        );
    }

    if (!llama_badge_automatic_revocation_storage_ready($db)) {
        throw new RuntimeException(
            'Automatic badge revocation storage is not installed yet.'
        );
    }

    $reason = mb_substr(trim($reason), 0, 500);
    if ($reason === '') {
        throw new RuntimeException(
            'A reason is required when suppressing an automatic badge.'
        );
    }

    $stmt = $db->prepare(
        'INSERT INTO badge_automatic_revocations (
            user_id,
            badge_id,
            revoked_user_badge_id,
            revoked_by,
            reason,
            revoked_at,
            cleared_at,
            cleared_by,
            clear_reason
         ) VALUES (
            ?, ?, ?, ?, ?, UTC_TIMESTAMP(), NULL, NULL, NULL
         )
         ON DUPLICATE KEY UPDATE
            revoked_user_badge_id = VALUES(revoked_user_badge_id),
            revoked_by = VALUES(revoked_by),
            reason = VALUES(reason),
            revoked_at = UTC_TIMESTAMP(),
            cleared_at = NULL,
            cleared_by = NULL,
            clear_reason = NULL'
    );

    $stmt->execute([
        $userId,
        $badgeId,
        $revokedUserBadgeId && $revokedUserBadgeId > 0
            ? $revokedUserBadgeId
            : null,
        $revokedBy > 0 ? $revokedBy : null,
        $reason,
    ]);
}


function llama_badge_clear_automatic_suppression(
    PDO $db,
    int $userId,
    int $badgeId,
    int $clearedBy,
    string $reason = 'Explicit manual award from Basecamp.'
): bool {
    if (
        $userId < 1
        || $badgeId < 1
        || !llama_badge_automatic_revocation_storage_ready($db)
    ) {
        return false;
    }

    $stmt = $db->prepare(
        'UPDATE badge_automatic_revocations
         SET
            cleared_at = UTC_TIMESTAMP(),
            cleared_by = ?,
            clear_reason = ?
         WHERE user_id = ?
           AND badge_id = ?
           AND cleared_at IS NULL'
    );

    $stmt->execute([
        $clearedBy > 0 ? $clearedBy : null,
        mb_substr(trim($reason), 0, 500),
        $userId,
        $badgeId,
    ]);

    return $stmt->rowCount() > 0;
}


function llama_badge_maintenance_storage_ready(
    PDO $db
): bool {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([
        'app_maintenance',
    ]);

    return (bool) $stmt->fetchColumn();
}


function llama_badge_automatic_definitions(
    PDO $db
): array {
    if (
        !llama_badge_threshold_column_ready(
            $db
        )
    ) {
        return [];
    }

    $stmt = $db->query(
        'SELECT
            id,
            slug,
            name,
            threshold_metric,
            threshold_value,
            eligibility_scope,
            recognition_mode,
            how_to_earn,
            sort_order
         FROM badge_definitions
         WHERE is_active = 1
           AND award_type = "automatic"
           AND threshold_metric IS NOT NULL
           AND threshold_metric <> ""
           AND threshold_value IS NOT NULL
           AND threshold_value > 0
         ORDER BY
            sort_order ASC,
            id ASC'
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


function llama_badge_user_metric_value(
    PDO $db,
    int $userId,
    string $metric,
    string $eligibilityScope = LLAMA_BADGE_SCOPE_ALL_MEMBERS
): int {
    if (
        $userId < 1
        || !llama_badge_threshold_metric_is_valid(
            $metric
        )
    ) {
        return 0;
    }

    $eligibilityScope =
        llama_badge_scope_normalize(
            $eligibilityScope
        );

    if (
        $metric === 'total_points'
        && $eligibilityScope === LLAMA_BADGE_SCOPE_ALL_MEMBERS
    ) {
        return max(
            0,
            llama_points_total(
                $db,
                $userId
            )
        );
    }

    if ($metric === 'llamaversaries') {
        if (
            $eligibilityScope !== LLAMA_BADGE_SCOPE_ALL_MEMBERS
            && !llama_badge_user_matches_current_scope(
                $db,
                $userId,
                $eligibilityScope
            )
        ) {
            return 0;
        }

        $stmt = $db->prepare(
            'SELECT
                GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        YEAR,
                        DATE(created_at),
                        UTC_DATE()
                    )
                )
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $userId,
        ]);

        return max(
            0,
            (int) (
                $stmt->fetchColumn()
                ?: 0
            )
        );
    }

    if ($metric === 'total_points') {
        $contributionFilter =
            llama_badge_scope_activity_sql(
                $eligibilityScope,
                'role_at_time'
            );

        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points_awarded), 0)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"'
            . $contributionFilter['sql']
        );
        $stmt->execute(
            array_merge(
                [$userId],
                $contributionFilter['params']
            )
        );

        $points = max(0, (int) ($stmt->fetchColumn() ?: 0));

        try {
            $checkinFilter =
                llama_badge_scope_level_sql(
                    $eligibilityScope,
                    'contribution_level'
                );

            $checkinStmt = $db->prepare(
                'SELECT COALESCE(SUM(points_awarded), 0)
                 FROM place_checkins
                 WHERE user_id = ?'
                . $checkinFilter['sql']
            );
            $checkinStmt->execute(
                array_merge(
                    [$userId],
                    $checkinFilter['params']
                )
            );
            $points += max(0, (int) ($checkinStmt->fetchColumn() ?: 0));
        } catch (Throwable) {
            // Check-in storage may not exist during a staged migration.
        }

        return $points;
    }

    $metricSql = match ($metric) {
        'approved_contributions' =>
            'COUNT(*)',

        'new_places' =>
            'COUNT(
                DISTINCT CASE
                    WHEN contribution_type = "new_place"
                        THEN place_id
                    ELSE NULL
                END
            )',

        'place_updates' =>
            'SUM(
                CASE
                    WHEN contribution_type IN (
                        "update",
                        "correction"
                    )
                        THEN 1
                    ELSE 0
                END
            )',

        default =>
            '0',
    };

    $scopeFilter =
        llama_badge_scope_activity_sql(
            $eligibilityScope,
            'role_at_time'
        );

    $stmt = $db->prepare(
        'SELECT COALESCE('
        . $metricSql
        . ', 0)
         FROM place_contributions
         WHERE user_id = ?
           AND status = "approved"'
        . $scopeFilter['sql']
    );

    $stmt->execute(
        array_merge(
            [$userId],
            $scopeFilter['params']
        )
    );

    return max(
        0,
        (int) (
            $stmt->fetchColumn()
            ?: 0
        )
    );
}

function llama_badge_award_automatic(
    PDO $db,
    int $userId,
    int $badgeId,
    string $metric,
    int $currentValue,
    int $threshold,
    string $eligibilityScope = LLAMA_BADGE_SCOPE_ALL_MEMBERS,
    string $recognitionMode = LLAMA_BADGE_RECOGNITION_PERMANENT
): bool {
    if (
        $userId < 1
        || $badgeId < 1
        || $threshold < 1
    ) {
        return false;
    }

    $eligibilityScope = llama_badge_scope_normalize($eligibilityScope);
    $recognitionMode = llama_badge_recognition_normalize($recognitionMode);

    if (
        $recognitionMode === LLAMA_BADGE_RECOGNITION_CURRENT_ROLE
        && !llama_badge_user_matches_current_scope(
            $db,
            $userId,
            $eligibilityScope
        )
    ) {
        return false;
    }

    $revocationStorageReady =
        llama_badge_automatic_revocation_storage_ready($db);

    $startedTransaction =
        $revocationStorageReady
        && !$db->inTransaction();

    if ($startedTransaction) {
        $db->beginTransaction();
    }

    try {
        /*
         * Lock the user/badge suppression key before awarding. The unique
         * (user_id, badge_id) index makes this safe against a concurrent
         * Basecamp revocation: whichever action commits last leaves the
         * correct final state instead of silently re-awarding the badge.
         */
        if (
            $revocationStorageReady
            && llama_badge_automatic_is_suppressed(
                $db,
                $userId,
                $badgeId,
                true
            )
        ) {
            if ($startedTransaction) {
                $db->commit();
            }

            return false;
        }

        $userStmt = $db->prepare(
            'SELECT id
             FROM users
             WHERE id = ?
               AND status = "active"
               AND anonymized_at IS NULL
             LIMIT 1'
        );

        $userStmt->execute([
            $userId,
        ]);

        if (!$userStmt->fetchColumn()) {
            if ($startedTransaction) {
                $db->commit();
            }

            return false;
        }

        $existingSql =
            'SELECT
                id,
                review_status
             FROM user_badges
             WHERE user_id = ?
               AND badge_id = ?
             LIMIT 1';

        if ($startedTransaction) {
            $existingSql .= ' FOR UPDATE';
        }

        $existingStmt = $db->prepare($existingSql);
        $existingStmt->execute([
            $userId,
            $badgeId,
        ]);

        $existing =
            $existingStmt->fetch(
                PDO::FETCH_ASSOC
            );

        $note =
            'Automatically earned: '
            . (
                llama_badge_threshold_metric_labels()[
                    $metric
                ]
                ?? $metric
            )
            . ' '
            . number_format($currentValue)
            . ' / '
            . number_format($threshold)
            . '. Badge track: '
            . llama_badge_scope_label($eligibilityScope)
            . '.';

        if ($existing) {
            if (
                (string) (
                    $existing['review_status']
                    ?? ''
                ) === 'earned'
            ) {
                if ($startedTransaction) {
                    $db->commit();
                }

                return false;
            }

            $stmt = $db->prepare(
                'UPDATE user_badges
                 SET
                    review_status = "earned",
                    awarded_by = NULL,
                    awarded_at = UTC_TIMESTAMP(),
                    note = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                $note,
                (int) $existing['id'],
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO user_badges (
                    user_id,
                    badge_id,
                    awarded_by,
                    awarded_at,
                    review_status,
                    note
                 ) VALUES (
                    ?,
                    ?,
                    NULL,
                    UTC_TIMESTAMP(),
                    "earned",
                    ?
                 )'
            );

            $stmt->execute([
                $userId,
                $badgeId,
                $note,
            ]);
        }

        if ($startedTransaction) {
            $db->commit();
        }

        return true;
    } catch (Throwable $exception) {
        if (
            $startedTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        throw $exception;
    }
}


function llama_badges_sync_user(
    PDO $db,
    int $userId
): array {
    $summary = [
        'checked' => 0,
        'awarded' => 0,
        'role_recognition_removed' => 0,
    ];

    if ($userId < 1) {
        return $summary;
    }

    $summary['role_recognition_removed'] =
        llama_badge_sync_current_role_recognition(
            $db,
            $userId
        );

    foreach (
        llama_badge_automatic_definitions(
            $db
        )
        as $badge
    ) {
        $metric =
            trim(
                (string) (
                    $badge['threshold_metric']
                    ?? ''
                )
            );

        $threshold =
            max(
                0,
                (int) (
                    $badge['threshold_value']
                    ?? 0
                )
            );

        $eligibilityScope =
            llama_badge_definition_scope($badge);

        $recognitionMode =
            llama_badge_definition_recognition($badge);

        if (
            !llama_badge_threshold_metric_is_valid(
                $metric
            )
            || $threshold < 1
        ) {
            continue;
        }

        $summary['checked']++;

        $currentValue =
            llama_badge_user_metric_value(
                $db,
                $userId,
                $metric,
                $eligibilityScope
            );

        if ($currentValue < $threshold) {
            continue;
        }

        if (
            llama_badge_award_automatic(
                $db,
                $userId,
                (int) $badge['id'],
                $metric,
                $currentValue,
                $threshold,
                $eligibilityScope,
                $recognitionMode
            )
        ) {
            $summary['awarded']++;
        }
    }

    return $summary;
}


function llama_badge_maintenance_is_due(
    PDO $db,
    int $intervalSeconds = 300
): bool {
    if (
        !llama_badge_maintenance_storage_ready(
            $db
        )
    ) {
        return false;
    }

    $intervalSeconds =
        max(
            60,
            $intervalSeconds
        );

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = ?
         LIMIT 1'
    );

    $stmt->execute([
        'automatic_badges',
    ]);

    $lastRun =
        $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $timestamp =
        strtotime(
            (string) $lastRun
            . ' UTC'
        );

    return
        $timestamp === false
        || (
            time() - $timestamp
        ) >= $intervalSeconds;
}


function llama_badge_mark_maintenance_run(
    PDO $db
): void {
    $stmt = $db->prepare(
        'INSERT INTO app_maintenance (
            maintenance_key,
            last_run_at
         )
         VALUES (
            ?,
            UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            last_run_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        'automatic_badges',
    ]);
}


function llama_run_automatic_badge_maintenance(
    PDO $db,
    int $userLimit = 50,
    int $intervalSeconds = 300
): array {
    $summary = [
        'ran' => false,
        'users_checked' => 0,
        'awarded' => 0,
        'role_recognition_removed' => 0,
    ];

    if (
        !llama_badge_threshold_column_ready(
            $db
        )
        || !llama_badge_maintenance_storage_ready(
            $db
        )
        || !llama_badge_maintenance_is_due(
            $db,
            $intervalSeconds
        )
    ) {
        return $summary;
    }

    $lockStmt = $db->query(
        "SELECT GET_LOCK(
            'llamascout_automatic_badges',
            0
        )"
    );

    if (
        !$lockStmt
        || (int) $lockStmt->fetchColumn() !== 1
    ) {
        return $summary;
    }

    try {
        if (
            !llama_badge_maintenance_is_due(
                $db,
                $intervalSeconds
            )
        ) {
            return $summary;
        }

        $summary['ran'] = true;

        $userLimit =
            max(
                1,
                min(
                    250,
                    $userLimit
                )
            );

        /*
         * Rotate through active users instead of permanently starting at
         * user #1. Individual user sync is idempotent, so revisiting a
         * member is safe.
         */
        $offsetSeed =
            (int) floor(
                time()
                / max(
                    60,
                    $intervalSeconds
                )
            );

        $countStmt = $db->query(
            'SELECT COUNT(*)
             FROM users
             WHERE status = "active"
               AND anonymized_at IS NULL'
        );

        $activeCount =
            max(
                0,
                (int) (
                    $countStmt
                        ? $countStmt->fetchColumn()
                        : 0
                )
            );

        if ($activeCount > 0) {
            $offset =
                ($offsetSeed * $userLimit)
                % $activeCount;

            $userStmt = $db->query(
                'SELECT id
                 FROM users
                 WHERE status = "active"
                   AND anonymized_at IS NULL
                 ORDER BY id ASC
                 LIMIT '
                . $userLimit
                . ' OFFSET '
                . $offset
            );

            $userIds =
                $userStmt
                    ? (
                        $userStmt->fetchAll(
                            PDO::FETCH_COLUMN
                        )
                        ?: []
                    )
                    : [];

            if (
                count($userIds) < $userLimit
                && $activeCount > count($userIds)
            ) {
                $remaining =
                    $userLimit
                    - count($userIds);

                $wrapStmt = $db->query(
                    'SELECT id
                     FROM users
                     WHERE status = "active"
                       AND anonymized_at IS NULL
                     ORDER BY id ASC
                     LIMIT '
                    . $remaining
                );

                $wrapped =
                    $wrapStmt
                        ? (
                            $wrapStmt->fetchAll(
                                PDO::FETCH_COLUMN
                            )
                            ?: []
                        )
                        : [];

                $userIds =
                    array_values(
                        array_unique(
                            array_merge(
                                $userIds,
                                $wrapped
                            )
                        )
                    );
            }

            foreach ($userIds as $userId) {
                $userSummary =
                    llama_badges_sync_user(
                        $db,
                        (int) $userId
                    );

                $summary['users_checked']++;
                $summary['awarded'] +=
                    (int) (
                        $userSummary['awarded']
                        ?? 0
                    );

                $summary['role_recognition_removed'] +=
                    (int) (
                        $userSummary['role_recognition_removed']
                        ?? 0
                    );
            }
        }

        llama_badge_mark_maintenance_run(
            $db
        );

        return $summary;

    } finally {
        try {
            $db->query(
                "SELECT RELEASE_LOCK(
                    'llamascout_automatic_badges'
                )"
            );
        } catch (Throwable) {
            // Connection cleanup releases the lock.
        }
    }
}
