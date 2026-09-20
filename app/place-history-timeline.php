<?php

declare(strict_types=1);

/* =========================================================
   LLAMA SCOUT
   PUBLIC PLACE HISTORY TIMELINE

   Builds one chronological history from the systems that
   already preserve Place activity:

   - original contribution / approved contribution history
   - geofenced check-ins
   - resolved problem reports
   - legacy/manual Llama Scout field visits

   The public timeline is newest first. Moderation by itself is
   not treated as a Place contribution and does not raise the
   public documentation level.
   ========================================================= */

function llama_place_history_table_exists(
    PDO $db,
    string $table
): bool {
    if (!preg_match('/^[a-z0-9_]+$/', $table)) {
        return false;
    }

    $stmt = $db->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1'
    );

    $stmt->execute([$table]);

    return $stmt->fetchColumn() !== false;
}

function llama_place_history_normalize_datetime(
    mixed $value
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value . ' 12:00:00';
    }

    return $value;
}

function llama_place_history_level_for_user(
    PDO $db,
    int $userId
): string {
    if ($userId < 1) {
        return LLAMA_CONTRIBUTION_LEVEL_COMMUNITY;
    }

    try {
        return llama_user_contribution_level(
            $db,
            $userId
        );
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout Place history contributor-level lookup failed for user #'
            . $userId
            . ': '
            . $exception->getMessage()
        );

        return LLAMA_CONTRIBUTION_LEVEL_COMMUNITY;
    }
}

function llama_place_history_contribution_title(
    string $type
): string {
    return match ($type) {
        'new_place' => 'New Place contributed',
        'update' => 'Place updated',
        'correction' => 'Factual correction approved',
        'field_report' => 'Field report approved',
        default => 'Place contribution approved',
    };
}

function llama_place_history_contribution_summary(
    string $type
): string {
    return match ($type) {
        'new_place' =>
            'This Place was added to Llama Scout.',
        'update' =>
            'Published Place information was updated.',
        'correction' =>
            'Published Place information was corrected.',
        'field_report' =>
            'A field report added or refreshed Place information.',
        default =>
            'Published information for this Place was improved.',
    };
}

function llama_place_history_origin_level(
    PDO $db,
    array $origin
): string {
    $userId = (int) (
        $origin['original_contributor_id']
        ?? 0
    );

    $originType = strtolower(
        trim(
            (string) (
                $origin['origin_type']
                ?? ''
            )
        )
    );

    if (
        in_array(
            $originType,
            [
                'llama-scouted',
                'scout',
            ],
            true
        )
    ) {
        return LLAMA_CONTRIBUTION_LEVEL_SCOUT;
    }

    if ($originType === 'admin') {
        return LLAMA_CONTRIBUTION_LEVEL_ADMIN;
    }

    if ($userId > 0) {
        return llama_place_history_level_for_user(
            $db,
            $userId
        );
    }

    return LLAMA_CONTRIBUTION_LEVEL_COMMUNITY;
}

function llama_place_history_timeline(
    PDO $db,
    int $placeId,
    string $placeSlug
): array {
    if ($placeId < 1) {
        return [];
    }

    $events = [];
    $hasOriginalContribution = false;

    /* =====================================================
       APPROVED CONTRIBUTIONS
       ===================================================== */

    try {
        $stmt = $db->prepare(
            'SELECT
                pc.id,
                pc.user_id,
                pc.submission_id,
                pc.contribution_type,
                pc.role_at_time,
                pc.points_awarded,
                pc.visited_at,
                pc.submitted_at,
                pc.approved_at,
                pc.created_at,
                u.username,
                u.display_name,
                pus.id AS update_submission_id
             FROM place_contributions pc
             LEFT JOIN users u
                ON u.id = pc.user_id
             LEFT JOIN place_update_submissions pus
                ON pus.contribution_id = pc.id
               AND pus.status = "approved"
             WHERE pc.place_id = ?
               AND pc.status = "approved"
               AND pc.contribution_type <> "moderation"
             ORDER BY
                COALESCE(pc.approved_at, pc.created_at) DESC,
                pc.id DESC'
        );

        $stmt->execute([$placeId]);

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
            as $row
        ) {
            $type = strtolower(
                trim(
                    (string) (
                        $row['contribution_type']
                        ?? 'other'
                    )
                )
            );

            if ($type === 'new_place') {
                $hasOriginalContribution = true;
            }

            $reportKey = '';
            $submissionId = (int) (
                $row['submission_id']
                ?? 0
            );
            $updateSubmissionId = (int) (
                $row['update_submission_id']
                ?? 0
            );

            if (
                $type === 'new_place'
                && $submissionId > 0
            ) {
                $reportKey =
                    'submission-' . $submissionId;
            } elseif ($updateSubmissionId > 0) {
                $reportKey =
                    'update-' . $updateSubmissionId;
            }

            $reportUrl =
                $reportKey !== ''
                    ? llama_historical_report_url(
                        $placeSlug,
                        $reportKey
                    )
                    : '';

            $occurredAt =
                llama_place_history_normalize_datetime(
                    $row['approved_at']
                    ?? $row['created_at']
                    ?? $row['visited_at']
                    ?? ''
                );

            if ($occurredAt === '') {
                continue;
            }

            $events[] = [
                'key' =>
                    'contribution-' . (int) $row['id'],
                'type' =>
                    $type,
                'occurred_at' =>
                    $occurredAt,
                'date_label_source' =>
                    $row['approved_at']
                    ?? $row['created_at']
                    ?? $row['visited_at']
                    ?? '',
                'title' =>
                    llama_place_history_contribution_title($type),
                'summary' =>
                    llama_place_history_contribution_summary($type),
                'icon' => match ($type) {
                    'new_place' => 'map-pin',
                    'correction' => 'refresh',
                    'field_report' => 'binoculars',
                    default => 'edit',
                },
                'user_id' =>
                    (int) ($row['user_id'] ?? 0),
                'username' =>
                    trim((string) ($row['username'] ?? '')),
                'display_name' =>
                    trim((string) ($row['display_name'] ?? '')),
                'actor_label' =>
                    $type === 'new_place'
                        ? 'Original contributor'
                        : 'Contributed by',
                'contribution_level' =>
                    llama_contribution_level_for_record(
                        $db,
                        $row
                    ),
                'show_level' =>
                    true,
                'points_awarded' =>
                    max(
                        0,
                        (int) (
                            $row['points_awarded']
                            ?? 0
                        )
                    ),
                'visited_at' =>
                    trim((string) ($row['visited_at'] ?? '')),
                'report_url' =>
                    $reportUrl,
                'report_label' =>
                    $type === 'new_place'
                        ? 'View original report'
                        : 'View report',
                'status_label' =>
                    '',
                'sort_id' =>
                    5000000 + (int) $row['id'],
            ];
        }
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout contribution history timeline error for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );
    }

    /* =====================================================
       LEGACY ORIGINAL PROVENANCE FALLBACK

       If the Place predates place_contributions, retain its
       original provenance as the oldest timeline event.
       ===================================================== */

    if (!$hasOriginalContribution) {
        try {
            $originStmt = $db->prepare(
                'SELECT
                    pp.origin_type,
                    pp.established_at,
                    pp.original_contributor_id,
                    pp.original_submission_id,
                    u.username,
                    u.display_name
                 FROM place_provenance pp
                 LEFT JOIN users u
                    ON u.id = pp.original_contributor_id
                 WHERE pp.place_id = ?
                 LIMIT 1'
            );

            $originStmt->execute([$placeId]);
            $origin =
                $originStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if ($origin) {
                $occurredAt =
                    llama_place_history_normalize_datetime(
                        $origin['established_at']
                        ?? ''
                    );

                if ($occurredAt !== '') {
                    $submissionId = (int) (
                        $origin['original_submission_id']
                        ?? 0
                    );

                    $events[] = [
                        'key' =>
                            'origin-' . $placeId,
                        'type' =>
                            'new_place',
                        'occurred_at' =>
                            $occurredAt,
                        'date_label_source' =>
                            $origin['established_at']
                            ?? '',
                        'title' =>
                            'New Place contributed',
                        'summary' =>
                            'This Place was added to Llama Scout.',
                        'icon' =>
                            'map-pin',
                        'user_id' =>
                            (int) (
                                $origin['original_contributor_id']
                                ?? 0
                            ),
                        'username' =>
                            trim((string) ($origin['username'] ?? '')),
                        'display_name' =>
                            trim((string) ($origin['display_name'] ?? '')),
                        'actor_label' =>
                            'Original contributor',
                        'contribution_level' =>
                            llama_place_history_origin_level(
                                $db,
                                $origin
                            ),
                        'show_level' =>
                            true,
                        'points_awarded' =>
                            0,
                        'visited_at' =>
                            '',
                        'report_url' =>
                            $submissionId > 0
                                ? llama_historical_report_url(
                                    $placeSlug,
                                    'submission-' . $submissionId
                                )
                                : '',
                        'report_label' =>
                            'View original report',
                        'status_label' =>
                            '',
                        'sort_id' =>
                            1000000 + $placeId,
                    ];
                }
            }
        } catch (Throwable $exception) {
            error_log(
                'Llama Scout legacy provenance timeline error for Place #'
                . $placeId
                . ': '
                . $exception->getMessage()
            );
        }
    }

    /* =====================================================
       GEOFENCED CHECK-INS
       ===================================================== */

    try {
        if (
            llama_place_history_table_exists(
                $db,
                'place_checkins'
            )
        ) {
            $checkinStmt = $db->prepare(
                'SELECT
                    pci.id,
                    pci.user_id,
                    pci.role_at_time,
                    pci.contribution_level,
                    pci.complete_report_visible,
                    pci.distance_meters,
                    pci.accuracy_meters,
                    pci.points_awarded,
                    pci.checked_in_at,
                    u.username,
                    u.display_name
                 FROM place_checkins pci
                 LEFT JOIN users u
                    ON u.id = pci.user_id
                 WHERE pci.place_id = ?
                 ORDER BY pci.checked_in_at DESC, pci.id DESC'
            );

            $checkinStmt->execute([$placeId]);

            foreach (
                $checkinStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                as $row
            ) {
                $occurredAt =
                    llama_place_history_normalize_datetime(
                        $row['checked_in_at']
                        ?? ''
                    );

                if ($occurredAt === '') {
                    continue;
                }

                $level =
                    llama_contribution_level_normalize(
                        (string) (
                            $row['contribution_level']
                            ?? ''
                        )
                    );

                $events[] = [
                    'key' =>
                        'checkin-' . (int) $row['id'],
                    'type' =>
                        'checkin',
                    'occurred_at' =>
                        $occurredAt,
                    'date_label_source' =>
                        $row['checked_in_at']
                        ?? '',
                    'title' =>
                        llama_contribution_level_short_label($level)
                        . ' Check-In',
                    'summary' =>
                        !empty($row['complete_report_visible'])
                            ? 'This Place was confirmed in person, including the visible road/access and site information.'
                            : 'This Place was confirmed in person with a geofenced check-in.',
                    'icon' =>
                        'check',
                    'user_id' =>
                        (int) ($row['user_id'] ?? 0),
                    'username' =>
                        trim((string) ($row['username'] ?? '')),
                    'display_name' =>
                        trim((string) ($row['display_name'] ?? '')),
                    'actor_label' =>
                        'Checked in by',
                    'contribution_level' =>
                        $level,
                    'show_level' =>
                        true,
                    'points_awarded' =>
                        max(
                            0,
                            (int) (
                                $row['points_awarded']
                                ?? 0
                            )
                        ),
                    'visited_at' =>
                        trim((string) ($row['checked_in_at'] ?? '')),
                    'report_url' =>
                        '',
                    'report_label' =>
                        '',
                    'status_label' =>
                        'Geofenced',
                    'sort_id' =>
                        6000000 + (int) $row['id'],
                ];
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout check-in timeline error for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );
    }

    /* =====================================================
       RESOLVED PROBLEM REPORTS

       Open/investigating reports remain private to moderation.
       A resolved report contributes two public history points:
       when the issue was reported and when it was resolved.
       Internal report details and resolution notes are never
       exposed here.
       ===================================================== */

    try {
        if (
            llama_place_history_table_exists(
                $db,
                'place_reports'
            )
        ) {
            $reportStmt = $db->prepare(
                'SELECT
                    pr.id,
                    pr.user_id,
                    pr.problem_type,
                    pr.created_at,
                    pr.reviewed_at,
                    u.username,
                    u.display_name
                 FROM place_reports pr
                 LEFT JOIN users u
                    ON u.id = pr.user_id
                 WHERE pr.place_id = ?
                   AND pr.status = "resolved"
                 ORDER BY
                    COALESCE(pr.reviewed_at, pr.created_at) DESC,
                    pr.id DESC'
            );

            $reportStmt->execute([$placeId]);
            $problemTypes = place_report_problem_types();

            foreach (
                $reportStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                as $row
            ) {
                $problemType = trim(
                    (string) (
                        $row['problem_type']
                        ?? 'other'
                    )
                );

                $problemLabel =
                    (string) (
                        $problemTypes[$problemType]
                        ?? ucwords(
                            str_replace(
                                ['-', '_'],
                                ' ',
                                $problemType
                            )
                        )
                    );

                $userId = (int) ($row['user_id'] ?? 0);
                $level =
                    llama_place_history_level_for_user(
                        $db,
                        $userId
                    );

                $createdAt =
                    llama_place_history_normalize_datetime(
                        $row['created_at']
                        ?? ''
                    );

                if ($createdAt !== '') {
                    $events[] = [
                        'key' =>
                            'problem-reported-' . (int) $row['id'],
                        'type' =>
                            'problem_reported',
                        'occurred_at' =>
                            $createdAt,
                        'date_label_source' =>
                            $row['created_at']
                            ?? '',
                        'title' =>
                            'Problem reported',
                        'summary' =>
                            $problemLabel
                            . ' was reported for this Place.',
                        'icon' =>
                            'alert-triangle',
                        'user_id' =>
                            $userId,
                        'username' =>
                            trim((string) ($row['username'] ?? '')),
                        'display_name' =>
                            trim((string) ($row['display_name'] ?? '')),
                        'actor_label' =>
                            'Reported by',
                        'contribution_level' =>
                            $level,
                        'show_level' =>
                            true,
                        'points_awarded' =>
                            0,
                        'visited_at' =>
                            '',
                        'report_url' =>
                            '',
                        'report_label' =>
                            '',
                        'status_label' =>
                            'Resolved',
                        'sort_id' =>
                            3000000 + (int) $row['id'],
                    ];
                }

                $reviewedAt =
                    llama_place_history_normalize_datetime(
                        $row['reviewed_at']
                        ?? ''
                    );

                if ($reviewedAt !== '') {
                    $events[] = [
                        'key' =>
                            'problem-resolved-' . (int) $row['id'],
                        'type' =>
                            'problem_resolved',
                        'occurred_at' =>
                            $reviewedAt,
                        'date_label_source' =>
                            $row['reviewed_at']
                            ?? '',
                        'title' =>
                            'Problem resolved',
                        'summary' =>
                            $problemLabel
                            . ' was resolved after review.',
                        'icon' =>
                            'circle-check',
                        'user_id' =>
                            $userId,
                        'username' =>
                            trim((string) ($row['username'] ?? '')),
                        'display_name' =>
                            trim((string) ($row['display_name'] ?? '')),
                        'actor_label' =>
                            'Originally reported by',
                        'contribution_level' =>
                            $level,
                        'show_level' =>
                            false,
                        'points_awarded' =>
                            0,
                        'visited_at' =>
                            '',
                        'report_url' =>
                            '',
                        'report_label' =>
                            '',
                        'status_label' =>
                            'Resolved',
                        'sort_id' =>
                            4000000 + (int) $row['id'],
                    ];
                }
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout problem-report timeline error for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );
    }

    /* =====================================================
       LEGACY / MANUAL FIELD SCOUT ACTIVITY

       Modern geofenced check-ins create place_verifications too,
       so verification rows already linked to a check-in are
       excluded here to prevent duplicate timeline events.
       ===================================================== */

    try {
        if (
            llama_place_history_table_exists(
                $db,
                'place_verifications'
            )
        ) {
            $hasCheckins =
                llama_place_history_table_exists(
                    $db,
                    'place_checkins'
                );

            $verificationSql =
                'SELECT
                    pv.id,
                    pv.visited_at,
                    pv.verified_at,
                    pv.source
                 FROM place_verifications pv ';

            if ($hasCheckins) {
                $verificationSql .=
                    'LEFT JOIN place_checkins pci
                        ON pci.verification_id = pv.id ';
            }

            $verificationSql .=
                'WHERE pv.place_id = ?
                   AND (
                        pv.verification_type = "field-verified"
                        OR LOWER(COALESCE(pv.source, "")) = "llama scouted"
                   ) ';

            if ($hasCheckins) {
                $verificationSql .=
                    'AND pci.id IS NULL ';
            }

            $verificationSql .=
                'ORDER BY
                    COALESCE(pv.visited_at, DATE(pv.verified_at)) DESC,
                    pv.id DESC';

            $verificationStmt =
                $db->prepare($verificationSql);

            $verificationStmt->execute([$placeId]);

            foreach (
                $verificationStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
                as $row
            ) {
                $eventDate =
                    trim(
                        (string) (
                            $row['visited_at']
                            ?? ''
                        )
                    );

                if ($eventDate === '') {
                    $eventDate =
                        trim(
                            (string) (
                                $row['verified_at']
                                ?? ''
                            )
                        );
                }

                $occurredAt =
                    llama_place_history_normalize_datetime(
                        $eventDate
                    );

                if ($occurredAt === '') {
                    continue;
                }

                $events[] = [
                    'key' =>
                        'field-verification-' . (int) $row['id'],
                    'type' =>
                        'field_verification',
                    'occurred_at' =>
                        $occurredAt,
                    'date_label_source' =>
                        $eventDate,
                    'title' =>
                        'Llama Scout field visit',
                    'summary' =>
                        'A trained Llama Scout field visit was recorded for this Place.',
                    'icon' =>
                        'binoculars',
                    'user_id' =>
                        0,
                    'username' =>
                        '',
                    'display_name' =>
                        '',
                    'actor_label' =>
                        '',
                    'contribution_level' =>
                        LLAMA_CONTRIBUTION_LEVEL_SCOUT,
                    'show_level' =>
                        true,
                    'points_awarded' =>
                        0,
                    'visited_at' =>
                        $eventDate,
                    'report_url' =>
                        '',
                    'report_label' =>
                        '',
                    'status_label' =>
                        'Field visit',
                    'sort_id' =>
                        2000000 + (int) $row['id'],
                ];
            }
        }
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout legacy field-visit timeline error for Place #'
            . $placeId
            . ': '
            . $exception->getMessage()
        );
    }

    /* =====================================================
       NEWEST FIRST
       ===================================================== */

    usort(
        $events,
        static function (array $a, array $b): int {
            $aTime = strtotime(
                (string) ($a['occurred_at'] ?? '')
            );
            $bTime = strtotime(
                (string) ($b['occurred_at'] ?? '')
            );

            $aTime = $aTime !== false ? $aTime : 0;
            $bTime = $bTime !== false ? $bTime : 0;

            if ($aTime !== $bTime) {
                return $bTime <=> $aTime;
            }

            return
                (int) ($b['sort_id'] ?? 0)
                <=>
                (int) ($a['sort_id'] ?? 0);
        }
    );

    return $events;
}
