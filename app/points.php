<?php

declare(strict_types=1);

function llama_points_policy(
    PDO $db,
    string $key,
    int $default = 0
): int {
    try {
        $stmt = $db->prepare(
            'SELECT points_value
             FROM points_policy
             WHERE policy_key = ?
             LIMIT 1'
        );

        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false
            ? $default
            : (int) $value;
    } catch (Throwable $exception) {
        return $default;
    }
}

function llama_points_total(
    PDO $db,
    int $userId
): int {
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points), 0)
             FROM points_ledger
             WHERE user_id = ?'
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(points_awarded), 0)
             FROM place_contributions
             WHERE user_id = ?
               AND status = "approved"'
        );

        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}

function llama_points_record(
    PDO $db,
    int $userId,
    int $points,
    string $sourceType,
    ?int $sourceId,
    string $reason,
    ?int $awardedBy = null,
    ?int $contributionId = null
): int {
    if ($points === 0) {
        return 0;
    }

    if ($contributionId) {
        $exists = $db->prepare(
            'SELECT id
             FROM points_ledger
             WHERE contribution_id = ?
             LIMIT 1'
        );

        $exists->execute([$contributionId]);

        $existingId = (int) ($exists->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO points_ledger (
            user_id,
            points,
            source_type,
            source_id,
            contribution_id,
            reason,
            awarded_by
         ) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $points,
        $sourceType,
        $sourceId,
        $contributionId,
        $reason,
        $awardedBy,
    ]);

    return (int) $db->lastInsertId();
}
