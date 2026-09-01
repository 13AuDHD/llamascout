<?php

declare(strict_types=1);

require_once __DIR__ . '/scout-policy.php';
require_once __DIR__ . '/scout-onboarding.php';
require_once __DIR__ . '/scout-maintenance.php';

function admin_scouts_list(PDO $db): array
{
    $profileImageSql =
        admin_user_profile_image_sql('u');

    $sql = "
        SELECT
            sp.*,
            u.username,
            u.display_name,
            u.email,
            u.membership_status,
            {$profileImageSql} AS profile_image_src,
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
            ) AS scout_points,
            (
                SELECT COUNT(*)
                FROM place_contributions pc
                WHERE pc.user_id = sp.user_id
                  AND pc.status = 'approved'
                  AND pc.contribution_type = 'new_place'
            ) AS new_place_count,
            (
                SELECT COUNT(*)
                FROM place_contributions pc
                WHERE pc.user_id = sp.user_id
                  AND pc.status = 'approved'
                  AND pc.contribution_type IN ('update','correction')
            ) AS improvement_count
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
            sp.updated_at DESC
    ";

    $stmt = $db->query($sql);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_scout_get(
    PDO $db,
    int $scoutProfileId
): ?array {
    $profileImageSql =
        admin_user_profile_image_sql('u');

    $sql = "
        SELECT
            sp.*,
            u.username,
            u.display_name,
            u.email,
            u.membership_status,
            u.membership_ends_at,
            {$profileImageSql} AS profile_image_src,
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
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
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


function admin_scout_policy_int_value(
    PDO $db,
    string $key
): int {
    return llama_scout_policy_int(
        $db,
        $key
    );
}


function admin_scout_policy_bool_value(
    PDO $db,
    string $key
): bool {
    return llama_scout_policy_bool(
        $db,
        $key
    );
}


function admin_scout_current_period(
    PDO $db,
    array $scout
): array {
    $months =
        admin_scout_policy_int_value(
            $db,
            'scout_period_months'
        );

    $required =
        admin_scout_policy_int_value(
            $db,
            'annual_new_places_required'
        );

    $end = trim(
        (string) (
            $scout['active_through']
            ?? ''
        )
    );

    if ($end === '') {
        return [
            'start' => null,
            'end' => null,
            'required' => $required,
            'completed' => 0,
            'remaining' => $required,
            'met' => false,
            'days_remaining' => null,
        ];
    }

    try {
        $endDate = new DateTimeImmutable($end);
        $startDate = $endDate->modify('-' . $months . ' months');

        $startedAt = trim(
            (string) (
                $scout['scout_started_at']
                ?? ''
            )
        );

        if ($startedAt !== '') {
            $started = new DateTimeImmutable($startedAt);
            if ($started > $startDate) {
                $startDate = $started;
            }
        }

        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM scout_activity
             WHERE scout_profile_id = ?
               AND user_id = ?
               AND activity_type = "place_approved"
               AND occurred_at >= ?
               AND occurred_at < ?'
        );

        $stmt->execute([
            (int) $scout['id'],
            (int) $scout['user_id'],
            $startDate->format('Y-m-d H:i:s'),
            $endDate->format('Y-m-d H:i:s'),
        ]);

        $completed = (int) $stmt->fetchColumn();

        $daysRemaining = (int) floor(
            ($endDate->getTimestamp() - time()) / 86400
        );

        return [
            'start' => $startDate->format('Y-m-d H:i:s'),
            'end' => $endDate->format('Y-m-d H:i:s'),
            'required' => $required,
            'completed' => $completed,
            'remaining' => max(0, $required - $completed),
            'met' => $required > 0 && $completed >= $required,
            'days_remaining' => $daysRemaining,
        ];
    } catch (Throwable) {
        return [
            'start' => null,
            'end' => $end,
            'required' => $required,
            'completed' => 0,
            'remaining' => $required,
            'met' => false,
            'days_remaining' => null,
        ];
    }
}


function admin_scout_master_qualification(
    PDO $db,
    array $scout
): array {
    $enabled = admin_scout_policy_bool_value(
        $db,
        'master_scout_qualification_enabled'
    );

    $pointsRequired = admin_scout_policy_int_value(
        $db,
        'master_scout_points_required'
    );

    $newPlacesRequired = admin_scout_policy_int_value(
        $db,
        'master_scout_lifetime_new_places_required'
    );

    $updatesRequired = admin_scout_policy_int_value(
        $db,
        'master_scout_updates_required'
    );

    $correctionsRequired = admin_scout_policy_int_value(
        $db,
        'master_scout_corrections_required'
    );

    $updatedPlacesRequired = admin_scout_policy_int_value(
        $db,
        'master_scout_updated_places_required'
    );

    $requiresCurrent = admin_scout_policy_bool_value(
        $db,
        'master_scout_requires_current_period'
    );

    $stmt = $db->prepare(
        'SELECT
            COALESCE(SUM(points_awarded),0) AS points,
            SUM(CASE WHEN contribution_type = "new_place" THEN 1 ELSE 0 END) AS new_places,
            SUM(CASE WHEN contribution_type = "update" THEN 1 ELSE 0 END) AS updates,
            SUM(CASE WHEN contribution_type = "correction" THEN 1 ELSE 0 END) AS corrections,
            COUNT(DISTINCT CASE
                WHEN contribution_type IN ("update","correction") THEN place_id
                ELSE NULL
            END) AS updated_places
         FROM place_contributions
         WHERE user_id = ?
           AND status = "approved"'
    );

    $stmt->execute([
        (int) $scout['user_id'],
    ]);

    $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $period = admin_scout_current_period($db, $scout);
    $active = (string) $scout['status'] === 'active';

    $requirements = [
        [
            'key' => 'active',
            'label' => 'Active Llama Scout',
            'current' => $active ? 1 : 0,
            'required' => 1,
            'met' => $active,
        ],
        [
            'key' => 'current_period',
            'label' => 'Current period requirement',
            'current' => (int) $period['completed'],
            'required' => (int) $period['required'],
            'met' => !$requiresCurrent || (bool) $period['met'],
        ],
        [
            'key' => 'points',
            'label' => 'Lifetime points',
            'current' => (int) ($counts['points'] ?? 0),
            'required' => $pointsRequired,
            'met' => $pointsRequired > 0 && (int) ($counts['points'] ?? 0) >= $pointsRequired,
        ],
        [
            'key' => 'new_places',
            'label' => 'Lifetime new Places',
            'current' => (int) ($counts['new_places'] ?? 0),
            'required' => $newPlacesRequired,
            'met' => $newPlacesRequired > 0 && (int) ($counts['new_places'] ?? 0) >= $newPlacesRequired,
        ],
        [
            'key' => 'updates',
            'label' => 'Approved updates',
            'current' => (int) ($counts['updates'] ?? 0),
            'required' => $updatesRequired,
            'met' => $updatesRequired > 0 && (int) ($counts['updates'] ?? 0) >= $updatesRequired,
        ],
        [
            'key' => 'corrections',
            'label' => 'Approved corrections',
            'current' => (int) ($counts['corrections'] ?? 0),
            'required' => $correctionsRequired,
            'met' => $correctionsRequired > 0 && (int) ($counts['corrections'] ?? 0) >= $correctionsRequired,
        ],
        [
            'key' => 'updated_places',
            'label' => 'Different Places improved',
            'current' => (int) ($counts['updated_places'] ?? 0),
            'required' => $updatedPlacesRequired,
            'met' => $updatedPlacesRequired > 0 && (int) ($counts['updated_places'] ?? 0) >= $updatedPlacesRequired,
        ],
    ];

    $numericComplete =
        $pointsRequired > 0
        && $newPlacesRequired > 0
        && $updatesRequired > 0
        && $correctionsRequired > 0
        && $updatedPlacesRequired > 0;

    $allMet = true;
    foreach ($requirements as $requirement) {
        if (!$requirement['met']) {
            $allMet = false;
            break;
        }
    }

    return [
        'enabled' => $enabled,
        'policy_complete' => $numericComplete,
        'eligible' => $enabled && $numericComplete && $allMet,
        'requirements' => $requirements,
        'period' => $period,
    ];
}


function admin_scout_operational_stats(
    array $scouts
): array {
    $stats = [
        'total' => count($scouts),
        'active' => 0,
        'master' => 0,
        'onboarding' => 0,
        'attention' => 0,
    ];

    foreach ($scouts as $scout) {
        $roles = explode(',', (string) ($scout['role_slugs'] ?? ''));
        $status = (string) ($scout['status'] ?? '');

        if ($status === 'active') {
            $stats['active']++;
        }

        if (in_array('master_scout', $roles, true)) {
            $stats['master']++;
        }

        if (in_array($status, [
            'invited',
            'application_started',
            'application_submitted',
            'training',
            'pending_approval',
        ], true)) {
            $stats['onboarding']++;
        }

        if (in_array($status, [
            'application_submitted',
            'pending_approval',
            'inactive',
        ], true)) {
            $stats['attention']++;
        }
    }

    return $stats;
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

    if (
        $status === 'active'
        && in_array(
            $before,
            [
                'invited',
                'application_started',
                'application_submitted',
                'training',
                'pending_approval',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Scout onboarding must be approved through the onboarding review before activating Scout access.'
        );
    }

    $activeThrough =
        $scout['active_through']
        ?? null;

    if ($status === 'active') {
        $needsPeriod = true;

        if (!empty($activeThrough)) {
            try {
                $needsPeriod =
                    new DateTimeImmutable((string) $activeThrough)
                    <= new DateTimeImmutable('now');
            } catch (Throwable) {
                $needsPeriod = true;
            }
        }

        if ($needsPeriod) {
            $periodMonths =
                admin_scout_policy_int_value(
                    $db,
                    'scout_period_months'
                );

            $activeThrough =
                (new DateTimeImmutable('now'))
                ->modify('+' . $periodMonths . ' months')
                ->format('Y-m-d H:i:s');
        }
    }

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
            active_through = CASE
                WHEN ? = "active"
                    THEN ?
                ELSE active_through
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
        $activeThrough,
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
    if (
        !admin_users_current_is_owner(
            $db,
            $actorUserId
        )
    ) {
        throw new RuntimeException(
            'Only an Owner can change Master Scout status.'
        );
    }

    $scout =
        admin_scout_get(
            $db,
            $scoutProfileId
        );

    if (!$scout) {
        throw new RuntimeException(
            'Scout profile not found.'
        );
    }

    $userId =
        (int) $scout['user_id'];

    if (
        (string) $scout['status']
        !== 'active'
    ) {
        throw new RuntimeException(
            'Only an active Llama Scout can have Master Scout status changed.'
        );
    }

    $notes =
        trim($notes);

    $db->beginTransaction();

    try {
        if ($makeMaster) {
            /*
             * The rank engine is the sole authority for qualification,
             * current role assignment, and permanent rank history.
             */
            $result =
                llama_promote_to_master_scout(
                    $db,
                    $userId,
                    $actorUserId,
                    $notes !== ''
                        ? $notes
                        : null
                );

            $badgeStmt =
                $db->prepare(
                    'SELECT id
                     FROM badge_definitions
                     WHERE slug = "master-scout"
                       AND is_active = 1
                     LIMIT 1'
                );

            $badgeStmt->execute();

            $masterBadgeId =
                (int) $badgeStmt->fetchColumn();

            if ($masterBadgeId > 0) {
                $existingStmt =
                    $db->prepare(
                        'SELECT id
                         FROM user_badges
                         WHERE user_id = ?
                           AND badge_id = ?
                         LIMIT 1'
                    );

                $existingStmt->execute([
                    $userId,
                    $masterBadgeId,
                ]);

                $userBadgeId =
                    (int) $existingStmt->fetchColumn();

                if ($userBadgeId > 0) {
                    $db->prepare(
                        'UPDATE user_badges
                         SET
                            awarded_by = ?,
                            review_status = "earned",
                            note = ?
                         WHERE id = ?'
                    )->execute([
                        $actorUserId,
                        'Automatically awarded with Master Scout rank.',
                        $userBadgeId,
                    ]);
                } else {
                    $db->prepare(
                        'INSERT INTO user_badges (
                            user_id,
                            badge_id,
                            awarded_by,
                            review_status,
                            note
                         ) VALUES (?, ?, ?, "earned", ?)'
                    )->execute([
                        $userId,
                        $masterBadgeId,
                        $actorUserId,
                        'Automatically awarded with Master Scout rank.',
                    ]);
                }
            }

            $action =
                'scout.master_granted';

            $summary =
                !empty($result['changed'])
                    ? 'Granted Master Scout status.'
                    : 'Master Scout status was already active.';

        } else {
            $currentRank =
                llama_current_scout_rank(
                    $db,
                    $userId
                );

            if (
                $currentRank ===
                LLAMA_SCOUT_RANK_MASTER
            ) {
                llama_return_to_basic_scout(
                    $db,
                    $userId,
                    $actorUserId,
                    LLAMA_RANK_REASON_ADMIN_CHANGE,
                    $notes !== ''
                        ? $notes
                        : 'Master Scout status removed by Owner.'
                );
            }

            $db->prepare(
                'DELETE ub
                 FROM user_badges ub
                 INNER JOIN badge_definitions bd
                    ON bd.id = ub.badge_id
                 WHERE ub.user_id = ?
                   AND bd.slug = "master-scout"
                   AND ub.review_status = "earned"'
            )->execute([
                $userId,
            ]);

            $action =
                'scout.master_removed';

            $summary =
                'Returned Master Scout to basic Llama Scout.';
        }

        admin_users_audit(
            $db,
            $actorUserId,
            $userId,
            $action,
            $summary,
            [
                'scout_profile_id' =>
                    $scoutProfileId,

                'notes' =>
                    $notes,
            ]
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
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


/* =========================================================
   SCOUT REACTIVATION OPERATIONS
   ========================================================= */

function admin_scout_latest_extension(
    PDO $db,
    int $scoutProfileId,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            se.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                CASE
                    WHEN se.granted_by IS NULL THEN "System"
                    ELSE CONCAT("User #", se.granted_by)
                END
            ) AS granted_by_name
         FROM scout_extensions se
         LEFT JOIN users u
            ON u.id = se.granted_by
         WHERE se.scout_profile_id = ?
           AND se.user_id = ?
         ORDER BY se.id DESC
         LIMIT 1'
    );

    $stmt->execute([
        $scoutProfileId,
        $userId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


function admin_scout_grant_reactivation(
    PDO $db,
    int $actorUserId,
    int $scoutProfileId,
    string $notes = ''
): array {
    $scout = admin_scout_get(
        $db,
        $scoutProfileId
    );

    if (!$scout) {
        throw new RuntimeException(
            'Scout profile not found.'
        );
    }

    if (
        !in_array(
            (string) $scout['status'],
            [
                'inactive',
                'removed',
            ],
            true
        )
    ) {
        throw new RuntimeException(
            'Only an inactive or removed former Scout can receive a reactivation window.'
        );
    }

    $userId =
        (int) $scout['user_id'];

    llama_ensure_scout_extensions_table(
        $db
    );

    $windowDays =
        admin_scout_policy_int_value(
            $db,
            'reactivation_window_days'
        );

    $requiredPlaces =
        admin_scout_policy_int_value(
            $db,
            'reactivation_new_places_required'
        );

    $startedAt =
        (new DateTimeImmutable('now'))
        ->format('Y-m-d H:i:s');

    $endsAt =
        (new DateTimeImmutable($startedAt))
        ->modify('+' . $windowDays . ' days')
        ->format('Y-m-d H:i:s');

    $db->beginTransaction();

    try {
        $activeStmt = $db->prepare(
            'SELECT id
             FROM scout_extensions
             WHERE scout_profile_id = ?
               AND user_id = ?
               AND status = "active"
             LIMIT 1
             FOR UPDATE'
        );

        $activeStmt->execute([
            $scoutProfileId,
            $userId,
        ]);

        if (
            (int) $activeStmt->fetchColumn()
            > 0
        ) {
            throw new RuntimeException(
                'This Scout already has an active reactivation window.'
            );
        }

        $userStmt = $db->prepare(
            'SELECT
                membership_status,
                stripe_subscription_id
             FROM users
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $userStmt->execute([
            $userId,
        ]);

        $userRow =
            $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow) {
            throw new RuntimeException(
                'Scout account not found.'
            );
        }

        $insert = $db->prepare(
            'INSERT INTO scout_extensions (
                scout_profile_id,
                user_id,
                granted_by,
                started_at,
                ends_at,
                status,
                accepted_reports
             ) VALUES (?, ?, ?, ?, ?, "active", 0)'
        );

        $insert->execute([
            $scoutProfileId,
            $userId,
            $actorUserId,
            $startedAt,
            $endsAt,
        ]);

        $extensionId =
            (int) $db->lastInsertId();

        $profileUpdate = $db->prepare(
            'UPDATE scout_profiles
             SET
                status = "active",
                active_through = ?,
                inactive_at = NULL,
                removed_at = NULL,
                removed_by = NULL,
                removal_reason = NULL,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND user_id = ?
               AND status IN ("inactive", "removed")'
        );

        $profileUpdate->execute([
            $endsAt,
            $scoutProfileId,
            $userId,
        ]);

        if ($profileUpdate->rowCount() !== 1) {
            throw new RuntimeException(
                'The Scout profile changed before reactivation could be granted.'
            );
        }

        /*
         * Reactivation is probationary basic Scout access.
         * It does not restore Master Scout and creates no new
         * earned-rank history until the window is completed.
         */
        llama_grant_basic_scout_role(
            $db,
            $userId
        );

        $membershipStatus =
            strtolower(
                trim(
                    (string) (
                        $userRow['membership_status']
                        ?? ''
                    )
                )
            );

        $hasPaidMembership =
            in_array(
                $membershipStatus,
                [
                    'active',
                    'trialing',
                    'past_due',
                ],
                true
            )
            && !empty(
                $userRow['stripe_subscription_id']
            );

        if (!$hasPaidMembership) {
            $db->prepare(
                'UPDATE users
                 SET
                    membership_status = "complimentary",
                    membership_interval = NULL,
                    membership_started_at = ?,
                    membership_ends_at = ?
                 WHERE id = ?'
            )->execute([
                $startedAt,
                $endsAt,
                $userId,
            ]);
        }

        admin_users_audit(
            $db,
            $actorUserId,
            $userId,
            'scout.reactivation_granted',
            'Granted a Scout reactivation window.',
            [
                'scout_profile_id' =>
                    $scoutProfileId,
                'extension_id' =>
                    $extensionId,
                'window_days' =>
                    $windowDays,
                'required_new_places' =>
                    $requiredPlaces,
                'ends_at' =>
                    $endsAt,
                'notes' =>
                    trim($notes) !== ''
                        ? trim($notes)
                        : null,
            ]
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }

    return [
        'extension_id' =>
            $extensionId,
        'started_at' =>
            $startedAt,
        'ends_at' =>
            $endsAt,
        'window_days' =>
            $windowDays,
        'required_new_places' =>
            $requiredPlaces,
    ];
}


function admin_scout_cancel_reactivation(
    PDO $db,
    int $actorUserId,
    int $scoutProfileId,
    string $notes
): void {
    $notes = trim($notes);

    if ($notes === '') {
        throw new RuntimeException(
            'Add a reason before canceling a reactivation window.'
        );
    }

    $scout = admin_scout_get(
        $db,
        $scoutProfileId
    );

    if (!$scout) {
        throw new RuntimeException(
            'Scout profile not found.'
        );
    }

    $userId =
        (int) $scout['user_id'];

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT *
             FROM scout_extensions
             WHERE scout_profile_id = ?
               AND user_id = ?
               AND status = "active"
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE'
        );

        $stmt->execute([
            $scoutProfileId,
            $userId,
        ]);

        $extension =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$extension) {
            throw new RuntimeException(
                'This Scout does not have an active reactivation window.'
            );
        }

        $db->prepare(
            'UPDATE scout_extensions
             SET
                status = "canceled",
                resolved_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND status = "active"'
        )->execute([
            (int) $extension['id'],
        ]);

        /*
         * Temporary access is removed without creating a fake
         * Scout-rank expiration event.
         */
        llama_expire_scout_access(
            $db,
            $scoutProfileId,
            $userId,
            false
        );

        admin_users_audit(
            $db,
            $actorUserId,
            $userId,
            'scout.reactivation_canceled',
            'Canceled a Scout reactivation window.',
            [
                'scout_profile_id' =>
                    $scoutProfileId,
                'extension_id' =>
                    (int) $extension['id'],
                'notes' =>
                    $notes,
            ]
        );

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}
