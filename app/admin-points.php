<?php

declare(strict_types=1);

function admin_points_policy_definitions(): array
{
    return [
        'new_place_points_per_percent' => [
            'group' => 'Place Report Points',
            'label' => 'New Place points per 1% completion',
            'description' =>
                'Points awarded for each percentage point of approved New Place completion.',
        ],

        'new_place_max_points' => [
            'group' => 'Place Report Points',
            'label' => 'New Place maximum points',
            'description' =>
                'Maximum points available from one approved New Place.',
        ],

        'new_place_minimum_approval_percent' => [
            'group' => 'Place Report Points',
            'label' => 'Minimum approval completion',
            'description' =>
                'Minimum completion percentage required before a New Place can be approved.',
        ],

        'place_update_percent_per_point' => [
            'group' => 'Place Report Points',
            'label' => 'Update percent per point',
            'description' =>
                'Percentage of applicable report questions changed for each update point.',
        ],

        'place_update_max_points' => [
            'group' => 'Place Report Points',
            'label' => 'Update maximum points',
            'description' =>
                'Maximum points available from one approved update or correction.',
        ],

        'points_global_multiplier' => [
            'group' => 'Place Report Points',
            'label' => 'Global points multiplier',
            'description' =>
                'Stored as a percentage. 100 = 1.00x, 150 = 1.50x, 200 = 2.00x.',
        ],

        'place_checkin' => [
            'group' => 'Other Contributions',
            'label' => 'Place Check In',
            'description' =>
                'Points awarded for a successful geofenced on-site Place check-in.',
        ],
    ];
}
function admin_points_policy_rows(PDO $db): array
{
    $definitions =
        admin_points_policy_definitions();

    $stmt =
        $db->query(
            'SELECT
                policy_key,
                points_value,
                description,
                updated_by
             FROM points_policy'
        );

    $stored = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: []
        as $row
    ) {
        $stored[
            (string) $row['policy_key']
        ] = $row;
    }

    $rows = [];

    foreach (
        $definitions
        as $key => $definition
    ) {
        if (!isset($stored[$key])) {
            throw new RuntimeException(
                'Points policy setting "' .
                $key .
                '" is not configured. Run the Points policy migration.'
            );
        }

        $row =
            $stored[$key];

        $row['label'] =
            (string) $definition['label'];

        $row['group'] =
            (string) $definition['group'];

        $row['description'] =
            (string) $definition['description'];

        $rows[] = $row;
    }

    return $rows;
}

function admin_points_policy_groups(
    PDO $db
): array {
    $groups = [];

    foreach (
        admin_points_policy_rows($db)
        as $row
    ) {
        $groups[
            (string) $row['group']
        ][] = $row;
    }

    return $groups;
}

function admin_points_manual_adjustment_users(
    PDO $db
): array {
    $sql =
        'SELECT
            u.id,
            u.email,
            u.username,
            u.display_name,
            u.status
         FROM users u
         WHERE u.anonymized_at IS NULL
         ORDER BY
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                u.email
            ) ASC,
            u.id ASC';

    return
        $db->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function admin_points_recent(
    PDO $db,
    int $limit = 100
): array {
    $limit =
        max(
            1,
            min(250, $limit)
        );

    $sql =
        'SELECT
            pl.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS member_name,
            u.username,
            ' .
            admin_user_profile_image_sql('u') .
            ' AS profile_image_src,
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
         ORDER BY
            pl.created_at DESC,
            pl.id DESC
         LIMIT ' .
         $limit;

    return
        $db->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}

function admin_points_save_policy(
    PDO $db,
    int $actorUserId,
    array $values
): void {
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can change the points policy.'
        );
    }

    $definitions =
        admin_points_policy_definitions();

    $stmt =
        $db->prepare(
            'UPDATE points_policy
             SET
                points_value = ?,
                updated_by = ?
             WHERE policy_key = ?'
        );

    $saved = [];

    foreach (
        $definitions
        as $key => $definition
    ) {
        if (
            !array_key_exists(
                $key,
                $values
            )
        ) {
            continue;
        }

        $value =
            max(
                0,
                (int) $values[$key]
            );

        $stmt->execute([
            $value,
            $actorUserId,
            $key,
        ]);

        if ($stmt->rowCount() < 1) {
            $check =
                $db->prepare(
                    'SELECT 1
                     FROM points_policy
                     WHERE policy_key = ?
                     LIMIT 1'
                );

            $check->execute([$key]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException(
                    'Points policy setting "' .
                    $key .
                    '" is missing.'
                );
            }
        }

        $saved[$key] = $value;
    }

    admin_users_audit(
        $db,
        $actorUserId,
        null,
        'points.policy_updated',
        'Updated sitewide points policy.',
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
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
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

    $user =
        admin_users_get(
            $db,
            $userId
        );

    if (!$user) {
        throw new RuntimeException(
            'User account not found.'
        );
    }

    $ledgerId =
        llama_points_record(
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
