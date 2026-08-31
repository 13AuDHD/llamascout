<?php

declare(strict_types=1);

function admin_points_policy_rows(PDO $db): array
{
    $stmt = $db->query(
        'SELECT *
         FROM points_policy
         ORDER BY policy_key'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_points_recent(
    PDO $db,
    int $limit = 100
): array {
    $limit = max(1, min(250, $limit));

    $sql =
        'SELECT
            pl.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS member_name,
            u.username,
            ' . admin_user_profile_image_sql('u') . ' AS profile_image_src,
            COALESCE(
                NULLIF(actor.display_name, ""),
                NULLIF(actor.username, ""),
                "System"
            ) AS awarded_by_name
         FROM points_ledger pl
         LEFT JOIN users u
            ON u.id = pl.user_id
         LEFT JOIN users actor
            ON actor.id = pl.awarded_by
         ORDER BY pl.created_at DESC, pl.id DESC
         LIMIT ' . $limit;

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_points_save_policy(
    PDO $db,
    int $actorUserId,
    array $values
): void {
    if (!admin_users_current_is_owner($db, $actorUserId)) {
        throw new RuntimeException(
            'Only an Owner can change the points policy.'
        );
    }

    $allowed = [
        'approved_new_place',
        'approved_place_update',
        'approved_correction',
    ];

    $stmt = $db->prepare(
        'UPDATE points_policy
         SET
            points_value = ?,
            updated_by = ?
         WHERE policy_key = ?'
    );

    $scoutMap = [
        'approved_new_place' => 'new_place_max_points',
        'approved_place_update' => 'place_update_max_points',
        'approved_correction' => 'place_correction_points',
    ];

    $scoutStmt = $db->prepare(
        'UPDATE scout_policy
         SET policy_value = ?
         WHERE policy_key = ?'
    );

    $saved = [];

    foreach ($allowed as $key) {
        if (!array_key_exists($key, $values)) {
            continue;
        }

        $value = max(
            0,
            (int) $values[$key]
        );

        $stmt->execute([
            $value,
            $actorUserId,
            $key,
        ]);

        $scoutStmt->execute([
            (string) $value,
            $scoutMap[$key],
        ]);

        $saved[$key] = $value;
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'points.policy_updated',
        'Updated contribution points policy.',
        $saved
    );
}

function admin_points_manual_adjustment(
    PDO $db,
    int $actorUserId,
    int $userId,
    int $points,
    string $reason
): int {
    if (!admin_users_current_is_owner($db, $actorUserId)) {
        throw new RuntimeException(
            'Only an Owner can make manual point adjustments.'
        );
    }

    if ($points === 0) {
        throw new RuntimeException(
            'Adjustment cannot be zero points.'
        );
    }

    $reason = trim($reason);

    if (mb_strlen($reason) < 8) {
        throw new RuntimeException(
            'Enter a clear reason for the adjustment.'
        );
    }

    $user = admin_users_get(
        $db,
        $userId
    );

    if (!$user) {
        throw new RuntimeException(
            'User account not found.'
        );
    }

    $ledgerId = llama_points_record(
        $db,
        $userId,
        $points,
        'manual_adjustment',
        null,
        $reason,
        $actorUserId,
        null
    );

    admin_users_audit(
        $db,
        $actorUserId,
        $userId,
        'points.manual_adjustment',
        ($points > 0 ? 'Added ' : 'Removed ') .
            number_format(abs($points)) .
            ' points.',
        [
            'points' => $points,
            'reason' => $reason,
            'ledger_id' => $ledgerId,
        ]
    );

    return $ledgerId;
}
