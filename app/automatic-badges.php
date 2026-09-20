<?php

declare(strict_types=1);

require_once __DIR__ . '/points.php';


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
    string $metric
): int {
    if (
        $userId < 1
        || !llama_badge_threshold_metric_is_valid(
            $metric
        )
    ) {
        return 0;
    }

    if ($metric === 'total_points') {
        return max(
            0,
            llama_points_total(
                $db,
                $userId
            )
        );
    }

    if ($metric === 'llamaversaries') {
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

    $stmt = $db->prepare(
        'SELECT COALESCE('
        . $metricSql
        . ', 0)
         FROM place_contributions
         WHERE user_id = ?
           AND status = "approved"'
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


function llama_badge_award_automatic(
    PDO $db,
    int $userId,
    int $badgeId,
    string $metric,
    int $currentValue,
    int $threshold
): bool {
    if (
        $userId < 1
        || $badgeId < 1
        || $threshold < 1
    ) {
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
        return false;
    }

    $existingStmt = $db->prepare(
        'SELECT
            id,
            review_status
         FROM user_badges
         WHERE user_id = ?
           AND badge_id = ?
         LIMIT 1'
    );

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
        . '.';

    if ($existing) {
        if (
            (string) (
                $existing['review_status']
                ?? ''
            ) === 'earned'
        ) {
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

        return true;
    }

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

    return true;
}


function llama_badges_sync_user(
    PDO $db,
    int $userId
): array {
    $summary = [
        'checked' => 0,
        'awarded' => 0,
    ];

    if ($userId < 1) {
        return $summary;
    }

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
                $metric
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
                $threshold
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
