<?php

declare(strict_types=1);

function admin_scouts_list(PDO $db): array
{
    $stmt = $db->query(
        "SELECT
            sp.*,
            u.username,
            u.display_name,
            u.email,
            u.membership_status,
            ' . admin_user_profile_image_sql('u') . ' AS profile_image_src,
            GROUP_CONCAT(
                DISTINCT r.slug
                ORDER BY r.id
                SEPARATOR ','
            ) AS role_slugs,
            (
                SELECT COUNT(*)
                FROM scout_activity sa
                WHERE sa.user_id = sp.user_id
            ) AS activity_count,
            (
                SELECT COALESCE(SUM(sa.points),0)
                FROM scout_activity sa
                WHERE sa.user_id = sp.user_id
            ) AS scout_points
         FROM scout_profiles sp
         INNER JOIN users u
            ON u.id = sp.user_id
         LEFT JOIN user_roles ur
            ON ur.user_id = u.id
         LEFT JOIN roles r
            ON r.id = ur.role_id
         GROUP BY sp.id
         ORDER BY
            FIELD(
                sp.status,
                'application_submitted',
                'pending_approval',
                'training',
                'active',
                'inactive',
                'invited',
                'application_started',
                'declined',
                'removed'
            ),
            sp.updated_at DESC"
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_scout_get(PDO $db, int $scoutProfileId): ?array
{
    $stmt = $db->prepare(
        "SELECT
            sp.*,
            u.username,
            u.display_name,
            u.email,
            u.membership_status,
            u.membership_ends_at,
            ' . admin_user_profile_image_sql('u') . ' AS profile_image_src,
            GROUP_CONCAT(
                DISTINCT r.slug
                ORDER BY r.id
                SEPARATOR ','
            ) AS role_slugs
         FROM scout_profiles sp
         INNER JOIN users u
            ON u.id = sp.user_id
         LEFT JOIN user_roles ur
            ON ur.user_id = u.id
         LEFT JOIN roles r
            ON r.id = ur.role_id
         WHERE sp.id = ?
         GROUP BY sp.id
         LIMIT 1"
    );
    $stmt->execute([$scoutProfileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_scout_application(
    PDO $db,
    int $scoutProfileId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM scout_applications
         WHERE scout_profile_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$scoutProfileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_scout_training(
    PDO $db,
    int $scoutProfileId
): ?array {
    $stmt = $db->prepare(
        'SELECT *
         FROM scout_training
         WHERE scout_profile_id = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([$scoutProfileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function admin_scout_activity(
    PDO $db,
    int $userId
): array {
    $stmt = $db->prepare(
        'SELECT
            sa.*,
            p.name AS place_name
         FROM scout_activity sa
         LEFT JOIN places p
            ON p.id = sa.place_id
         WHERE sa.user_id = ?
         ORDER BY sa.occurred_at DESC
         LIMIT 50'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_scout_rank_history(
    PDO $db,
    int $userId
): array {
    $stmt = $db->prepare(
        'SELECT *
         FROM scout_rank_history
         WHERE user_id = ?
         ORDER BY occurred_at DESC
         LIMIT 30'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_scout_policy_rows(PDO $db): array
{
    $stmt = $db->query(
        'SELECT *
         FROM scout_policy
         ORDER BY policy_key'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_scout_set_status(
    PDO $db,
    int $actorUserId,
    int $scoutProfileId,
    string $status,
    string $notes = ''
): void {
    $allowed = [
        'invited',
        'application_started',
        'application_submitted',
        'training',
        'pending_approval',
        'active',
        'inactive',
        'declined',
        'removed',
    ];

    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('Invalid Scout status.');
    }

    $scout = admin_scout_get($db, $scoutProfileId);

    if (!$scout) {
        throw new RuntimeException('Scout profile not found.');
    }

    $before = (string) $scout['status'];

    $sql =
        'UPDATE scout_profiles
         SET
            status = ?,
            approved_at = CASE
                WHEN ? = "active" AND approved_at IS NULL
                    THEN NOW()
                ELSE approved_at
            END,
            approved_by = CASE
                WHEN ? = "active" AND approved_by IS NULL
                    THEN ?
                ELSE approved_by
            END,
            scout_started_at = CASE
                WHEN ? = "active" AND scout_started_at IS NULL
                    THEN NOW()
                ELSE scout_started_at
            END,
            inactive_at = CASE
                WHEN ? = "inactive"
                    THEN NOW()
                ELSE inactive_at
            END,
            removed_at = CASE
                WHEN ? = "removed"
                    THEN NOW()
                ELSE removed_at
            END,
            removed_by = CASE
                WHEN ? = "removed"
                    THEN ?
                ELSE removed_by
            END,
            removal_reason = CASE
                WHEN ? = "removed"
                    THEN ?
                ELSE removal_reason
            END
         WHERE id = ?';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $status,
        $status,
        $status,
        $actorUserId,
        $status,
        $status,
        $status,
        $status,
        $actorUserId,
        $status,
        trim($notes) !== '' ? trim($notes) : null,
        $scoutProfileId,
    ]);

    if ($status === 'active') {
        $roleStmt = $db->prepare(
            'SELECT id FROM roles WHERE slug = "scout" LIMIT 1'
        );
        $roleStmt->execute();
        $roleId = (int) $roleStmt->fetchColumn();

        if ($roleId > 0) {
            $insert = $db->prepare(
                'INSERT IGNORE INTO user_roles (user_id, role_id)
                 VALUES (?, ?)'
            );
            $insert->execute([
                (int) $scout['user_id'],
                $roleId,
            ]);
        }
    }

    if (in_array($status, ['inactive','declined','removed'], true)) {
        $stmt = $db->prepare(
            'DELETE ur
             FROM user_roles ur
             INNER JOIN roles r
                ON r.id = ur.role_id
             WHERE ur.user_id = ?
               AND r.slug IN ("scout","master_scout")'
        );
        $stmt->execute([(int) $scout['user_id']]);
    }

    admin_users_audit(
        $db,
        $actorUserId,
        (int) $scout['user_id'],
        'scout.status_updated',
        'Changed Scout status from ' .
            $before .
            ' to ' .
            $status . '.',
        [
            'scout_profile_id' => $scoutProfileId,
            'before' => $before,
            'after' => $status,
            'notes' => trim($notes),
        ]
    );
}

function admin_scout_set_master(
    PDO $db,
    int $actorUserId,
    int $scoutProfileId,
    bool $makeMaster,
    string $notes = ''
): void {
    if (!admin_users_current_is_owner($db, $actorUserId)) {
        throw new RuntimeException(
            'Only an Owner can change Master Scout status.'
        );
    }

    $scout = admin_scout_get($db, $scoutProfileId);

    if (!$scout) {
        throw new RuntimeException('Scout profile not found.');
    }

    $userId = (int) $scout['user_id'];

    $roleStmt = $db->prepare(
        'SELECT id
         FROM roles
         WHERE slug = ?
         LIMIT 1'
    );

    if ($makeMaster) {
        foreach (['scout','master_scout'] as $slug) {
            $roleStmt->execute([$slug]);
            $roleId = (int) $roleStmt->fetchColumn();

            if ($roleId > 0) {
                $insert = $db->prepare(
                    'INSERT IGNORE INTO user_roles (
                        user_id,
                        role_id
                    ) VALUES (?, ?)'
                );
                $insert->execute([$userId, $roleId]);
            }
        }

        $fromRank = 'scout';
        $toRank = 'master_scout';
        $action = 'scout.master_granted';
        $summary = 'Granted Master Scout status.';
    } else {
        $stmt = $db->prepare(
            'DELETE ur
             FROM user_roles ur
             INNER JOIN roles r
                ON r.id = ur.role_id
             WHERE ur.user_id = ?
               AND r.slug = "master_scout"'
        );
        $stmt->execute([$userId]);

        $fromRank = 'master_scout';
        $toRank = 'scout';
        $action = 'scout.master_removed';
        $summary = 'Removed Master Scout status.';
    }

    $rank = $db->prepare(
        'INSERT INTO scout_rank_history (
            scout_profile_id,
            user_id,
            from_rank,
            to_rank,
            reason,
            changed_by,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $rank->execute([
        $scoutProfileId,
        $userId,
        $fromRank,
        $toRank,
        'admin_change',
        $actorUserId,
        trim($notes) !== '' ? trim($notes) : null,
    ]);

    admin_users_audit(
        $db,
        $actorUserId,
        $userId,
        $action,
        $summary,
        [
            'scout_profile_id' => $scoutProfileId,
            'notes' => trim($notes),
        ]
    );
}

function admin_scout_update_policy(
    PDO $db,
    int $actorUserId,
    array $values
): void {
    $current = admin_scout_policy_rows($db);

    $lookup = [];
    foreach ($current as $row) {
        $lookup[(string) $row['policy_key']] = $row;
    }

    $stmt = $db->prepare(
        'UPDATE scout_policy
         SET policy_value = ?
         WHERE policy_key = ?'
    );

    foreach ($values as $key => $value) {
        if (!isset($lookup[$key])) {
            continue;
        }

        $type = (string) $lookup[$key]['value_type'];
        $clean = trim((string) $value);

        if ($type === 'int') {
            $clean = (string) max(0, (int) $clean);
        } elseif ($type === 'float') {
            $clean = (string) (float) $clean;
        } elseif ($type === 'bool') {
            $clean = in_array(
                strtolower($clean),
                ['1','true','yes','on'],
                true
            ) ? '1' : '0';
        }

        $stmt->execute([$clean, $key]);
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'scout.policy_updated',
        'Updated Scout and Master Scout qualification policy.',
        ['keys' => array_keys($values)]
    );
}
