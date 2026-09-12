<?php

declare(strict_types=1);

function admin_points_policy_definitions(): array
{
    return [
        'new_place_site_vehicle' => [
            'group' => 'New Place Categories',
            'label' => 'Site + Vehicle',
            'description' => 'Maximum points available from Site + Vehicle information.',
        ],
        'new_place_road_access' => [
            'group' => 'New Place Categories',
            'label' => 'Road Access',
            'description' => 'Maximum points available from Road Access information.',
        ],
        'new_place_amenities' => [
            'group' => 'New Place Categories',
            'label' => 'Amenities',
            'description' => 'Awarded when the contributor explicitly reports any amenity information, including No amenities.',
        ],
        'new_place_connectivity' => [
            'group' => 'New Place Categories',
            'label' => 'Connectivity',
            'description' => 'Awarded when the contributor supplies at least one cellular, Starlink, or connectivity observation.',
        ],
        'new_place_sensory' => [
            'group' => 'New Place Categories',
            'label' => 'Sensory',
            'description' => 'Maximum points available from daytime, nighttime, and detailed sensory observations.',
        ],
        'new_place_environment' => [
            'group' => 'New Place Categories',
            'label' => 'Environment',
            'description' => 'Maximum points available from environment observations.',
        ],
        'new_place_accessibility' => [
            'group' => 'New Place Categories',
            'label' => 'Accessibility',
            'description' => 'Maximum points available from accessibility observations.',
        ],
        'new_place_safety_warnings' => [
            'group' => 'New Place Categories',
            'label' => 'Safety + Warnings',
            'description' => 'Maximum points available from safety, hazard, and quick-warning observations.',
        ],
        'new_place_seasons_rules_services' => [
            'group' => 'New Place Categories',
            'label' => 'Seasons + Rules + Services',
            'description' => 'Maximum points available from seasonal access, rules, fees, fire, and nearby-service information.',
        ],
        'new_place_experience_recommendations' => [
            'group' => 'New Place Categories',
            'label' => 'Experience + Recommendations',
            'description' => 'Maximum points available from experience ratings and recommendations.',
        ],

        'place_update_site_vehicle' => [
            'group' => 'Place Update Categories',
            'label' => 'Site + Vehicle',
            'description' => 'Maximum points available from approved Site + Vehicle changes.',
        ],
        'place_update_road_access' => [
            'group' => 'Place Update Categories',
            'label' => 'Road Access',
            'description' => 'Maximum points available from approved Road Access changes.',
        ],
        'place_update_amenities' => [
            'group' => 'Place Update Categories',
            'label' => 'Amenities',
            'description' => 'Maximum points available from approved Amenities changes.',
        ],
        'place_update_connectivity' => [
            'group' => 'Place Update Categories',
            'label' => 'Connectivity',
            'description' => 'Maximum points available from approved cellular, Starlink, or connectivity changes.',
        ],
        'place_update_sensory' => [
            'group' => 'Place Update Categories',
            'label' => 'Sensory',
            'description' => 'Maximum points available from approved Sensory changes.',
        ],
        'place_update_environment' => [
            'group' => 'Place Update Categories',
            'label' => 'Environment',
            'description' => 'Maximum points available from approved Environment changes.',
        ],
        'place_update_accessibility' => [
            'group' => 'Place Update Categories',
            'label' => 'Accessibility',
            'description' => 'Maximum points available from approved Accessibility changes.',
        ],
        'place_update_safety_warnings' => [
            'group' => 'Place Update Categories',
            'label' => 'Safety + Warnings',
            'description' => 'Maximum points available from approved Safety + Warnings changes.',
        ],
        'place_update_seasons_rules_services' => [
            'group' => 'Place Update Categories',
            'label' => 'Seasons + Rules + Services',
            'description' => 'Maximum points available from approved seasonal, rules, fee, fire, or nearby-service changes.',
        ],
        'place_update_experience_recommendations' => [
            'group' => 'Place Update Categories',
            'label' => 'Experience + Recommendations',
            'description' => 'Maximum points available from approved Experience + Recommendation changes.',
        ],

        'approved_correction' => [
            'group' => 'Other Contributions',
            'label' => 'Approved Correction',
            'description' => 'Points awarded for an approved correction.',
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

    /*
     * These two legacy totals remain available for reporting and
     * compatibility, but are derived from their ten category policies.
     * They are not separately editable anymore.
     */
    $newPlaceMax =
        llama_points_new_place_max_points(
            $db
        );

    $placeUpdateMax =
        llama_points_place_update_max_points(
            $db
        );

    $derived =
        $db->prepare(
            'INSERT INTO points_policy
                (
                    policy_key,
                    points_value,
                    description,
                    updated_by
                )
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                points_value = VALUES(points_value),
                description = VALUES(description),
                updated_by = VALUES(updated_by)'
        );

    $derived->execute([
        'approved_new_place',
        $newPlaceMax,
        'Derived maximum for the weighted New Place category policy.',
        $actorUserId,
    ]);

    $saved['approved_new_place'] =
        $newPlaceMax;

    $derived->execute([
        'approved_place_update',
        $placeUpdateMax,
        'Derived maximum for the weighted Place Update category policy.',
        $actorUserId,
    ]);

    $saved['approved_place_update'] =
        $placeUpdateMax;

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
