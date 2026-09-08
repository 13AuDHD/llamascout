<?php

declare(strict_types=1);

require_once __DIR__ . '/scout-policy.php';
require_once __DIR__ . '/scout-ranks.php';

function llama_scout_maintenance_utc_timestamp(string $value): ?int {
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        return (
            new DateTimeImmutable(
                $value,
                new DateTimeZone('UTC')
            )
        )->getTimestamp();
    } catch (Throwable) {
        return null;
    }
}

function llama_ensure_maintenance_table(PDO $db): void {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute(['app_maintenance']);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Scout maintenance storage is not initialized. Missing table: app_maintenance'
        );
    }
}

function llama_ensure_scout_extensions_table(PDO $db): void {
    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );
    $stmt->execute(['scout_extensions']);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException(
            'Scout maintenance storage is not initialized. Missing table: scout_extensions'
        );
    }
}

function llama_scout_maintenance_is_due(PDO $db): bool {
    llama_ensure_maintenance_table($db);
    llama_ensure_scout_extensions_table($db);
    llama_ensure_scout_policy_table($db);

    $interval = llama_scout_policy_int(
        $db,
        'maintenance_interval_seconds'
    );

    $stmt = $db->prepare(
        'SELECT last_run_at
         FROM app_maintenance
         WHERE maintenance_key = "scout_renewals"
         LIMIT 1'
    );
    $stmt->execute();
    $lastRun = $stmt->fetchColumn();

    if (!$lastRun) {
        return true;
    }

    $lastRunTimestamp = llama_scout_maintenance_utc_timestamp(
        (string) $lastRun
    );

    if ($lastRunTimestamp === null) {
        return true;
    }

    return (time() - $lastRunTimestamp) >= $interval;
}

function llama_mark_scout_maintenance_run(PDO $db): void {
    $stmt = $db->prepare(
        'INSERT INTO app_maintenance (
            maintenance_key,
            last_run_at
         ) VALUES (
            "scout_renewals",
            UTC_TIMESTAMP()
         )
         ON DUPLICATE KEY UPDATE
            last_run_at = UTC_TIMESTAMP()'
    );
    $stmt->execute();
}

function llama_count_scout_reports(
    PDO $db,
    int $scoutProfileId,
    int $userId,
    string $periodStart,
    string $periodEnd
): int {
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
        $scoutProfileId,
        $userId,
        $periodStart,
        $periodEnd,
    ]);
    return (int) $stmt->fetchColumn();
}

function llama_remove_scout_roles(PDO $db, int $userId): void {
    $stmt = $db->prepare(
        'DELETE ur
         FROM user_roles ur
         INNER JOIN roles r
            ON r.id = ur.role_id
         WHERE ur.user_id = ?
           AND r.slug IN (
                "scout",
                "master-scout",
                "master_scout"
           )'
    );
    $stmt->execute([$userId]);
}

function llama_grant_basic_scout_role(PDO $db, int $userId): void {
    llama_remove_scout_roles($db, $userId);

    $stmt = $db->prepare(
        'INSERT IGNORE INTO user_roles (
            user_id,
            role_id
         )
         SELECT ?, r.id
         FROM roles r
         WHERE r.slug = "scout"
         LIMIT 1'
    );
    $stmt->execute([$userId]);

    $check = $db->prepare(
        'SELECT COUNT(*)
         FROM user_roles ur
         INNER JOIN roles r
            ON r.id = ur.role_id
         WHERE ur.user_id = ?
           AND r.slug = "scout"'
    );
    $check->execute([$userId]);

    if ((int) $check->fetchColumn() < 1) {
        throw new RuntimeException(
            'The Scout role could not be granted.'
        );
    }
}

function llama_end_scout_complimentary_membership(
    PDO $db,
    int $userId
): void {
    $stmt = $db->prepare(
        'UPDATE users
         SET
            membership_status = "canceled",
            membership_ends_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND membership_status = "complimentary"'
    );
    $stmt->execute([$userId]);
}

function llama_sync_scout_membership_end(
    PDO $db,
    int $userId,
    int $scoutProfileId
): void {
    $stmt = $db->prepare(
        'UPDATE users u
         INNER JOIN scout_profiles sp
            ON sp.user_id = u.id
         SET u.membership_ends_at = sp.active_through
         WHERE u.id = ?
           AND sp.id = ?
           AND u.membership_status = "complimentary"'
    );
    $stmt->execute([$userId, $scoutProfileId]);
}

function llama_expire_scout_access(
    PDO $db,
    int $scoutProfileId,
    int $userId,
    bool $recordRankHistory = true
): void {
    $stmt = $db->prepare(
        'UPDATE scout_profiles
         SET
            status = "inactive",
            inactive_at = UTC_TIMESTAMP(),
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND user_id = ?
           AND status = "active"'
    );
    $stmt->execute([$scoutProfileId, $userId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Scout deactivation failed.');
    }

    if ($recordRankHistory) {
        llama_end_current_scout_rank(
            $db,
            $userId,
            LLAMA_RANK_REASON_SCOUT_EXPIRED,
            null,
            'Scout period ended without the required number of approved new Places.'
        );
    } else {
        llama_clear_current_scout_rank($db, $userId);
    }

    llama_remove_scout_roles($db, $userId);

    $masterBadgeStmt = $db->prepare(
        'DELETE ub
         FROM user_badges ub
         INNER JOIN badge_definitions bd
            ON bd.id = ub.badge_id
         WHERE ub.user_id = ?
           AND bd.slug = "master-scout"
           AND ub.review_status = "earned"'
    );
    $masterBadgeStmt->execute([$userId]);

    llama_end_scout_complimentary_membership($db, $userId);
}

function llama_active_scout_extension(
    PDO $db,
    int $scoutProfileId,
    int $userId
): ?array {
    $stmt = $db->prepare(
        'SELECT
            id,
            scout_profile_id,
            user_id,
            granted_by,
            started_at,
            ends_at,
            status,
            accepted_reports
         FROM scout_extensions
         WHERE scout_profile_id = ?
           AND user_id = ?
           AND status = "active"
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$scoutProfileId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function llama_complete_scout_extension(
    PDO $db,
    array $extension,
    int $acceptedReports
): void {
    $extensionId = (int) $extension['id'];
    $scoutProfileId = (int) $extension['scout_profile_id'];
    $userId = (int) $extension['user_id'];
    $extensionEnd = trim((string) $extension['ends_at']);

    if (
        $extensionEnd === ''
        || llama_scout_maintenance_utc_timestamp($extensionEnd) === null
    ) {
        throw new RuntimeException(
            'The Scout extension end date is invalid.'
        );
    }

    $scoutPeriodMonths = llama_scout_policy_int(
        $db,
        'scout_period_months'
    );
    $newActiveThrough = llama_policy_add_months(
        $extensionEnd,
        $scoutPeriodMonths
    );

    $profileStmt = $db->prepare(
        'UPDATE scout_profiles
         SET
            status = "active",
            active_through = ?,
            inactive_at = NULL,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND user_id = ?
           AND status = "active"'
    );
    $profileStmt->execute([
        $newActiveThrough,
        $scoutProfileId,
        $userId,
    ]);

    if ($profileStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The Scout extension could not be converted into normal Scout access.'
        );
    }

    $extensionStmt = $db->prepare(
        'UPDATE scout_extensions
         SET
            status = "completed",
            accepted_reports = ?,
            resolved_at = UTC_TIMESTAMP(),
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND status = "active"'
    );
    $extensionStmt->execute([$acceptedReports, $extensionId]);

    if ($extensionStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The Scout extension could not be marked complete.'
        );
    }

    llama_record_scout_rank_change(
        $db,
        $userId,
        LLAMA_SCOUT_RANK_NONE,
        LLAMA_SCOUT_RANK_SCOUT,
        LLAMA_RANK_REASON_REACTIVATED,
        null,
        null,
        'Successfully completed the Scout reactivation requirement.'
    );

    llama_sync_scout_membership_end(
        $db,
        $userId,
        $scoutProfileId
    );
}

function llama_fail_scout_extension(
    PDO $db,
    array $extension,
    int $acceptedReports
): void {
    $extensionId = (int) $extension['id'];
    $scoutProfileId = (int) $extension['scout_profile_id'];
    $userId = (int) $extension['user_id'];

    $extensionStmt = $db->prepare(
        'UPDATE scout_extensions
         SET
            status = "failed",
            accepted_reports = ?,
            resolved_at = UTC_TIMESTAMP(),
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND status = "active"'
    );
    $extensionStmt->execute([$acceptedReports, $extensionId]);

    if ($extensionStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The expired Scout extension could not be closed.'
        );
    }

    llama_expire_scout_access(
        $db,
        $scoutProfileId,
        $userId,
        false
    );
}

function llama_run_scout_renewal_maintenance(PDO $db): array {
    $summary = [
        'processed' => 0,
        'renewed' => 0,
        'inactive' => 0,
        'extensions_completed' => 0,
        'extensions_failed' => 0,
        'errors' => 0,
    ];

    if (!llama_scout_maintenance_is_due($db)) {
        return $summary;
    }

    $annualReportsRequired = llama_scout_policy_int(
        $db,
        'annual_new_places_required'
    );
    $reactivationReportsRequired = llama_scout_policy_int(
        $db,
        'reactivation_new_places_required'
    );
    $scoutPeriodMonths = llama_scout_policy_int(
        $db,
        'scout_period_months'
    );

    $stmt = $db->query(
        'SELECT
            id,
            user_id,
            scout_started_at,
            active_through
         FROM scout_profiles
         WHERE status = "active"
           AND active_through IS NOT NULL
           AND active_through <= UTC_TIMESTAMP()
         ORDER BY
            active_through ASC,
            id ASC'
    );
    $expiredScouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredScouts as $scout) {
        $summary['processed']++;
        $scoutProfileId = (int) $scout['id'];
        $userId = (int) $scout['user_id'];

        try {
            $db->beginTransaction();

            $lockStmt = $db->prepare(
                'SELECT
                    id,
                    user_id,
                    status,
                    scout_started_at,
                    active_through
                 FROM scout_profiles
                 WHERE id = ?
                   AND user_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $lockStmt->execute([$scoutProfileId, $userId]);
            $currentScout = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentScout) {
                throw new RuntimeException('Scout profile was not found.');
            }

            if (($currentScout['status'] ?? '') !== 'active') {
                $db->rollBack();
                continue;
            }

            $activeThrough = trim(
                (string) ($currentScout['active_through'] ?? '')
            );
            if ($activeThrough === '') {
                $db->rollBack();
                continue;
            }

            $periodEndTimestamp = llama_scout_maintenance_utc_timestamp(
                $activeThrough
            );
            if ($periodEndTimestamp === null) {
                throw new RuntimeException(
                    'Scout active_through date was invalid.'
                );
            }

            if ($periodEndTimestamp > time()) {
                $db->rollBack();
                continue;
            }

            $extension = llama_active_scout_extension(
                $db,
                $scoutProfileId,
                $userId
            );

            if ($extension) {
                $extensionStart = trim(
                    (string) ($extension['started_at'] ?? '')
                );
                $extensionEnd = trim(
                    (string) ($extension['ends_at'] ?? '')
                );

                $extensionStartTimestamp =
                    llama_scout_maintenance_utc_timestamp($extensionStart);
                $extensionEndTimestamp =
                    llama_scout_maintenance_utc_timestamp($extensionEnd);

                if (
                    $extensionStartTimestamp === null
                    || $extensionEndTimestamp === null
                ) {
                    throw new RuntimeException(
                        'Scout extension dates were invalid.'
                    );
                }

                if ($extensionEndTimestamp > time()) {
                    $repairStmt = $db->prepare(
                        'UPDATE scout_profiles
                         SET
                            active_through = ?,
                            updated_at = UTC_TIMESTAMP()
                         WHERE id = ?
                           AND user_id = ?
                           AND status = "active"'
                    );
                    $repairStmt->execute([
                        $extensionEnd,
                        $scoutProfileId,
                        $userId,
                    ]);
                    $db->commit();
                    continue;
                }

                $acceptedReports = llama_count_scout_reports(
                    $db,
                    $scoutProfileId,
                    $userId,
                    $extensionStart,
                    $extensionEnd
                );

                if ($acceptedReports >= $reactivationReportsRequired) {
                    llama_complete_scout_extension(
                        $db,
                        $extension,
                        $acceptedReports
                    );
                    $db->commit();
                    $summary['extensions_completed']++;
                    continue;
                }

                llama_fail_scout_extension(
                    $db,
                    $extension,
                    $acceptedReports
                );
                $db->commit();
                $summary['extensions_failed']++;
                $summary['inactive']++;
                continue;
            }

            $yearStart = llama_policy_subtract_months(
                $activeThrough,
                $scoutPeriodMonths
            );

            $yearStartTimestamp = llama_scout_maintenance_utc_timestamp(
                $yearStart
            );
            if ($yearStartTimestamp === null) {
                throw new RuntimeException(
                    'Scout period start date could not be determined.'
                );
            }

            $scoutStartedAt = trim(
                (string) ($currentScout['scout_started_at'] ?? '')
            );

            if ($scoutStartedAt !== '') {
                $scoutStartedTimestamp =
                    llama_scout_maintenance_utc_timestamp($scoutStartedAt);

                if (
                    $scoutStartedTimestamp !== null
                    && $scoutStartedTimestamp > $yearStartTimestamp
                ) {
                    $yearStart = gmdate(
                        'Y-m-d H:i:s',
                        $scoutStartedTimestamp
                    );
                }
            }

            $acceptedReports = llama_count_scout_reports(
                $db,
                $scoutProfileId,
                $userId,
                $yearStart,
                $activeThrough
            );

            if ($acceptedReports >= $annualReportsRequired) {
                $newActiveThrough = llama_policy_add_months(
                    $activeThrough,
                    $scoutPeriodMonths
                );

                $renewStmt = $db->prepare(
                    'UPDATE scout_profiles
                     SET
                        active_through = ?,
                        inactive_at = NULL,
                        updated_at = UTC_TIMESTAMP()
                     WHERE id = ?
                       AND user_id = ?
                       AND status = "active"'
                );
                $renewStmt->execute([
                    $newActiveThrough,
                    $scoutProfileId,
                    $userId,
                ]);

                if ($renewStmt->rowCount() !== 1) {
                    throw new RuntimeException('Scout renewal failed.');
                }

                llama_sync_scout_membership_end(
                    $db,
                    $userId,
                    $scoutProfileId
                );

                $db->commit();
                $summary['renewed']++;
                continue;
            }

            llama_expire_scout_access(
                $db,
                $scoutProfileId,
                $userId
            );
            $db->commit();
            $summary['inactive']++;

        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $summary['errors']++;
            error_log(
                'Llama Scout maintenance error for Scout profile ' .
                $scoutProfileId . ': ' . $exception->getMessage()
            );
        }
    }

    llama_mark_scout_maintenance_run($db);
    return $summary;
}
