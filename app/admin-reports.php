<?php

declare(strict_types=1);

function admin_report_problem_meta(
    string $problemType
): array {
    return match ($problemType) {
        'safety' => [
            'label' => 'Safety concern',
            'priority' => 1,
            'priority_label' => 'Urgent',
            'icon' => 'fa-triangle-exclamation',
            'place_anchor' => 'road-access',
            'place_action' => 'Review safety + access',
        ],

        'closure-status' => [
            'label' => 'Closure or status changed',
            'priority' => 1,
            'priority_label' => 'Urgent',
            'icon' => 'fa-road-barrier',
            'place_anchor' => 'rules',
            'place_action' => 'Review rules + closure',
        ],

        'location-access' => [
            'label' => 'Location or access problem',
            'priority' => 2,
            'priority_label' => 'High',
            'icon' => 'fa-location-crosshairs',
            'place_anchor' => 'road-access',
            'place_action' => 'Review location + access',
        ],

        'amenities' => [
            'label' => 'Amenities are incorrect',
            'priority' => 3,
            'priority_label' => 'Normal',
            'icon' => 'fa-circle-info',
            'place_anchor' => 'amenities',
            'place_action' => 'Review amenities',
        ],

        'sensory-information' => [
            'label' => 'Sensory information is incorrect',
            'priority' => 3,
            'priority_label' => 'Normal',
            'icon' => 'fa-brain',
            'place_anchor' => 'sensory-report',
            'place_action' => 'Review sensory report',
        ],

        'photo' => [
            'label' => 'Photo problem',
            'priority' => 3,
            'priority_label' => 'Normal',
            'icon' => 'fa-image',
            'place_anchor' => 'photos',
            'place_action' => 'Review Place photos',
        ],

        'duplicate-place' => [
            'label' => 'Possible duplicate Place',
            'priority' => 3,
            'priority_label' => 'Normal',
            'icon' => 'fa-clone',
            'place_anchor' => 'identity',
            'place_action' => 'Review Place',
        ],

        'incorrect-information' => [
            'label' => 'Incorrect information',
            'priority' => 3,
            'priority_label' => 'Normal',
            'icon' => 'fa-pen-to-square',
            'place_anchor' => 'identity',
            'place_action' => 'Review Place details',
        ],

        default => [
            'label' => 'Other problem',
            'priority' => 4,
            'priority_label' => 'Normal',
            'icon' => 'fa-flag',
            'place_anchor' => 'identity',
            'place_action' => 'Review Place',
        ],
    };
}

function admin_report_age_label(
    ?string $createdAt
): string {
    if (!$createdAt) {
        return 'Unknown';
    }

    try {
        $created = new DateTimeImmutable($createdAt);
        $now = new DateTimeImmutable('now');
        $seconds = max(
            0,
            $now->getTimestamp()
            - $created->getTimestamp()
        );

        if ($seconds < 3600) {
            $minutes = max(
                1,
                (int) floor($seconds / 60)
            );

            return $minutes . ' min';
        }

        if ($seconds < 86400) {
            $hours =
                (int) floor($seconds / 3600);

            return $hours . ' hr';
        }

        $days =
            (int) floor($seconds / 86400);

        return $days . ' day' .
            ($days === 1 ? '' : 's');
    } catch (Throwable) {
        return 'Unknown';
    }
}

function admin_reports_queue(
    PDO $db,
    string $status = '',
    string $problemType = '',
    string $search = ''
): array {
    $where = ['1 = 1'];
    $params = [];

    $allowedStatuses = [
        'open',
        'investigating',
        'resolved',
        'dismissed',
    ];

    if (
        $status !== ''
        && in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {
        $where[] = 'pr.status = ?';
        $params[] = $status;
    } elseif ($status === '') {
        $where[] =
            'pr.status IN ("open","investigating")';
    }

    if ($problemType !== '') {
        $where[] = 'pr.problem_type = ?';
        $params[] = $problemType;
    }

    $search = trim($search);

    if ($search !== '') {
        $needle =
            '%' . $search . '%';

        $where[] = '(
            p.name LIKE ?
            OR p.city LIKE ?
            OR p.county LIKE ?
            OR p.state LIKE ?
            OR pr.details LIKE ?
            OR u.username LIKE ?
            OR u.display_name LIKE ?
            OR CAST(pr.id AS CHAR) = ?
        )';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $search
        );
    }

    $stmt = $db->prepare(
        'SELECT
            pr.*,
            p.name AS place_name,
            p.slug AS place_slug,
            p.status AS place_status,
            p.city,
            p.county,
            p.state,
            u.username,
            u.display_name,
            (
                SELECT cpi.image_src
                FROM community_profile_images cpi
                WHERE cpi.user_id = u.id
                ORDER BY
                    cpi.sort_order ASC,
                    cpi.id ASC
                LIMIT 1
            ) AS reporter_profile_image,
            (
                SELECT COUNT(*)
                FROM place_report_images pri
                WHERE pri.report_id = pr.id
            ) AS image_count,
            (
                SELECT COUNT(*)
                FROM place_reports related
                WHERE related.place_id = pr.place_id
                  AND related.problem_type = pr.problem_type
            ) AS matching_report_count,
            (
                SELECT COUNT(*)
                FROM place_reports related_open
                WHERE related_open.place_id = pr.place_id
                  AND related_open.problem_type = pr.problem_type
                  AND related_open.status IN (
                    "open",
                    "investigating"
                  )
            ) AS matching_open_count,
            (
                SELECT COUNT(*)
                FROM place_reports place_open
                WHERE place_open.place_id = pr.place_id
                  AND place_open.status IN (
                    "open",
                    "investigating"
                  )
            ) AS place_open_report_count,
            (
                SELECT COUNT(*)
                FROM place_update_submissions pus
                WHERE pus.place_id = pr.place_id
                  AND pus.status IN (
                    "pending",
                    "needs-changes"
                  )
            ) AS pending_update_count,
            (
                SELECT MAX(pv.verified_at)
                FROM place_verifications pv
                WHERE pv.place_id = pr.place_id
            ) AS latest_verification_at,
            (
                SELECT COUNT(*)
                FROM place_verifications pv
                WHERE pv.place_id = pr.place_id
                  AND (
                    pv.verification_type = "field-verified"
                    OR LOWER(
                        COALESCE(
                            pv.source,
                            ""
                        )
                    ) = "llama scouted"
                  )
            ) AS llama_scouted_count
         FROM place_reports pr
         INNER JOIN places p
            ON p.id = pr.place_id
         INNER JOIN users u
            ON u.id = pr.user_id
         WHERE ' .
            implode(' AND ', $where) .
        ' ORDER BY
            CASE pr.problem_type
                WHEN "safety" THEN 1
                WHEN "closure-status" THEN 1
                WHEN "location-access" THEN 2
                WHEN "amenities" THEN 3
                WHEN "sensory-information" THEN 3
                WHEN "photo" THEN 3
                WHEN "duplicate-place" THEN 3
                WHEN "incorrect-information" THEN 3
                ELSE 4
            END ASC,
            CASE pr.status
                WHEN "open" THEN 1
                WHEN "investigating" THEN 2
                WHEN "resolved" THEN 3
                ELSE 4
            END ASC,
            pr.created_at ASC,
            pr.id ASC
         LIMIT 500'
    );

    $stmt->execute($params);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

function admin_report_problem_types(
    PDO $db
): array {
    $stmt = $db->query(
        'SELECT DISTINCT problem_type
         FROM place_reports
         WHERE problem_type IS NOT NULL
           AND problem_type <> ""
         ORDER BY problem_type ASC'
    );

    return array_values(
        array_filter(
            array_map(
                'strval',
                $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            )
        )
    );
}

function admin_report_stats(
    PDO $db
): array {
    $row =
        $db->query(
            'SELECT
                SUM(
                    CASE
                        WHEN status = "open"
                            THEN 1
                        ELSE 0
                    END
                ) AS open_count,
                SUM(
                    CASE
                        WHEN status = "investigating"
                            THEN 1
                        ELSE 0
                    END
                ) AS investigating_count,
                SUM(
                    CASE
                        WHEN status IN (
                            "open",
                            "investigating"
                        )
                        AND problem_type IN (
                            "safety",
                            "closure-status"
                        )
                            THEN 1
                        ELSE 0
                    END
                ) AS urgent_count,
                MIN(
                    CASE
                        WHEN status IN (
                            "open",
                            "investigating"
                        )
                            THEN created_at
                        ELSE NULL
                    END
                ) AS oldest_open
             FROM place_reports'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'open' =>
            (int) ($row['open_count'] ?? 0),
        'investigating' =>
            (int) ($row['investigating_count'] ?? 0),
        'urgent' =>
            (int) ($row['urgent_count'] ?? 0),
        'oldest' =>
            !empty($row['oldest_open'])
                ? (string) $row['oldest_open']
                : null,
    ];
}

function admin_report_related(
    PDO $db,
    int $reportId,
    int $placeId,
    string $problemType
): array {
    $stmt = $db->prepare(
        'SELECT
            pr.id,
            pr.status,
            pr.details,
            pr.created_at,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former member"
            ) AS reporter_name
         FROM place_reports pr
         INNER JOIN users u
            ON u.id = pr.user_id
         WHERE pr.place_id = ?
           AND pr.problem_type = ?
           AND pr.id <> ?
         ORDER BY
            CASE
                WHEN pr.status IN (
                    "open",
                    "investigating"
                ) THEN 0
                ELSE 1
            END,
            pr.created_at DESC
         LIMIT 20'
    );

    $stmt->execute([
        $placeId,
        $problemType,
        $reportId,
    ]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

function admin_report_history(
    PDO $db,
    int $reportId
): array {
    $stmt = $db->prepare(
        'SELECT
            h.*,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "System"
            ) AS actor_name
         FROM place_report_history h
         LEFT JOIN users u
            ON u.id = h.changed_by
         WHERE h.report_id = ?
         ORDER BY
            h.created_at DESC,
            h.id DESC'
    );

    $stmt->execute([$reportId]);

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) ?: [];
}

function admin_report_place_snapshot(
    PDO $db,
    int $placeId,
    string $problemType
): array {
    $coreStmt = $db->prepare(
        'SELECT *
         FROM places
         WHERE id = ?
         LIMIT 1'
    );

    $coreStmt->execute([$placeId]);

    $place =
        $coreStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $snapshot = [];

    $push = static function (
        array &$target,
        string $label,
        mixed $value
    ): void {
        if (
            $value === null
            || $value === ''
        ) {
            return;
        }

        $target[$label] = $value;
    };

    if (
        in_array(
            $problemType,
            [
                'location-access',
                'closure-status',
                'safety',
                'incorrect-information',
                'duplicate-place',
                'other',
            ],
            true
        )
    ) {
        $push(
            $snapshot,
            'Road',
            $place['road'] ?? null
        );

        $push(
            $snapshot,
            'Location',
            implode(
                ', ',
                array_filter(
                    [
                        $place['city'] ?? null,
                        $place['county'] ?? null,
                        $place['state'] ?? null,
                    ]
                )
            )
        );

        $push(
            $snapshot,
            'Elevation',
            isset($place['elevation_feet'])
                ? number_format(
                    (int) $place['elevation_feet']
                ) . ' ft'
                : null
        );

        $push(
            $snapshot,
            'Land manager',
            $place['land_manager'] ?? null
        );

        $push(
            $snapshot,
            'Land type',
            $place['land_type'] ?? null
        );
    }

    if (
        in_array(
            $problemType,
            [
                'location-access',
                'safety',
            ],
            true
        )
    ) {
        $stmt = $db->prepare(
            'SELECT *
             FROM place_details
             WHERE place_id = ?
             LIMIT 1'
        );

        $stmt->execute([$placeId]);

        $details =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        $rating = static function (
            mixed $value
        ): ?string {
            return (
                $value === null
                || $value === ''
            )
                ? null
                : ((int) $value) . '/5';
        };

        $yesNo = static function (
            mixed $value
        ): ?string {
            if (
                $value === null
                || $value === ''
            ) {
                return null;
            }

            return (int) $value === 1
                ? 'Yes'
                : 'No';
        };

        $push(
            $snapshot,
            'Road surface',
            $details['road_surface'] ?? null
        );

        $push(
            $snapshot,
            'Road width',
            $details['road_width'] ?? null
        );

        $push(
            $snapshot,
            'Road difficulty',
            $rating(
                $details[
                    'road_overall_difficulty'
                ] ?? null
            )
        );

        $push(
            $snapshot,
            'Mud risk',
            $rating(
                $details['mud_risk']
                ?? null
            )
        );

        $push(
            $snapshot,
            'Sedan accessible',
            $yesNo(
                $details['sedan_accessible']
                ?? null
            )
        );

        $push(
            $snapshot,
            'High clearance recommended',
            $yesNo(
                $details[
                    'high_clearance_recommended'
                ] ?? null
            )
        );

        $push(
            $snapshot,
            '4WD recommended',
            $yesNo(
                $details[
                    'four_wheel_drive_recommended'
                ] ?? null
            )
        );

        $push(
            $snapshot,
            'Traffic hazard',
            $yesNo(
                $details['traffic_hazard']
                ?? null
            )
        );
    }

    if ($problemType === 'amenities') {
        $stmt = $db->prepare(
            'SELECT *
             FROM place_amenities
             WHERE place_id = ?
             LIMIT 1'
        );

        $stmt->execute([$placeId]);

        $amenities =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        $labels = [
            'toilets' => 'Toilets',
            'potable_water' => 'Potable water',
            'trash' => 'Trash',
            'fire_ring' => 'Fire ring',
            'picnic_table' => 'Picnic table',
            'bear_box' => 'Bear box',
            'showers' => 'Showers',
            'electricity' => 'Electricity',
            'dump_station' => 'Dump station',
        ];

        foreach ($labels as $key => $label) {
            if (
                !array_key_exists(
                    $key,
                    $amenities
                )
                || $amenities[$key] === null
            ) {
                continue;
            }

            $snapshot[$label] =
                (int) $amenities[$key] === 1
                    ? 'Yes'
                    : 'No';
        }
    }

    if ($problemType === 'sensory-information') {
        foreach (
            [
                'daytime' => 'Day',
                'nighttime' => 'Night',
            ] as $period => $prefix
        ) {
            $stmt = $db->prepare(
                'SELECT *
                 FROM place_sensory
                 WHERE place_id = ?
                   AND period = ?
                 LIMIT 1'
            );

            $stmt->execute([
                $placeId,
                $period,
            ]);

            $row =
                $stmt->fetch(PDO::FETCH_ASSOC)
                ?: [];

            foreach (
                [
                    'noise' => 'noise',
                    'traffic' => 'traffic',
                    'crowds' => 'crowds',
                    'privacy' => 'privacy',
                    'sensory_comfort' =>
                        'sensory comfort',
                ] as $key => $label
            ) {
                if (
                    isset($row[$key])
                    && $row[$key] !== null
                    && $row[$key] !== ''
                ) {
                    $snapshot[
                        $prefix .
                        ' ' .
                        $label
                    ] =
                        ((int) $row[$key]) .
                        '/5';
                }
            }
        }
    }

    if ($problemType === 'closure-status') {
        $stmt = $db->prepare(
            'SELECT *
             FROM place_rules
             WHERE place_id = ?
             LIMIT 1'
        );

        $stmt->execute([$placeId]);

        $rules =
            $stmt->fetch(PDO::FETCH_ASSOC)
            ?: [];

        $push(
            $snapshot,
            'Place status',
            $place['status'] ?? null
        );

        if (
            array_key_exists(
                'winter_access',
                $rules
            )
            && $rules['winter_access'] !== null
        ) {
            $snapshot['Winter access'] =
                (int) $rules['winter_access'] === 1
                    ? 'Yes'
                    : 'No';
        }

        $push(
            $snapshot,
            'Seasonal access note',
            $rules[
                'seasonal_access_note'
            ] ?? null
        );
    }

    if ($problemType === 'photo') {
        $countStmt = $db->prepare(
            'SELECT COUNT(*)
             FROM place_images
             WHERE place_id = ?'
        );

        $countStmt->execute([$placeId]);

        $snapshot['Current Place photos'] =
            (int) $countStmt->fetchColumn();
    }

    return $snapshot;
}


function admin_report_place_context(
    PDO $db,
    int $placeId
): array {
    $stmt = $db->prepare(
        'SELECT
            p.id,
            p.name,
            p.slug,
            p.status,
            p.source_type,
            p.city,
            p.county,
            p.state,
            p.last_verified_at,
            p.updated_at,
            (
                SELECT MAX(pv.verified_at)
                FROM place_verifications pv
                WHERE pv.place_id = p.id
            ) AS latest_verification_at,
            (
                SELECT COUNT(*)
                FROM place_verifications pv
                WHERE pv.place_id = p.id
                  AND (
                    pv.verification_type = "field-verified"
                    OR LOWER(
                        COALESCE(
                            pv.source,
                            ""
                        )
                    ) = "llama scouted"
                  )
            ) AS llama_scouted_count,
            (
                SELECT MIN(
                    COALESCE(
                        pv.visited_at,
                        DATE(pv.verified_at)
                    )
                )
                FROM place_verifications pv
                WHERE pv.place_id = p.id
                  AND (
                    pv.verification_type = "field-verified"
                    OR LOWER(
                        COALESCE(
                            pv.source,
                            ""
                        )
                    ) = "llama scouted"
                  )
            ) AS first_llama_scouted_at,
            (
                SELECT COUNT(*)
                FROM place_reports pr
                WHERE pr.place_id = p.id
                  AND pr.status IN (
                    "open",
                    "investigating"
                  )
            ) AS unresolved_report_count,
            (
                SELECT COUNT(*)
                FROM place_update_submissions pus
                WHERE pus.place_id = p.id
                  AND pus.status IN (
                    "pending",
                    "needs-changes"
                  )
            ) AS pending_update_count,
            (
                SELECT COUNT(*)
                FROM place_verifications pv
                WHERE pv.place_id = p.id
            ) AS verification_count
         FROM places p
         WHERE p.id = ?
         LIMIT 1'
    );

    $stmt->execute([$placeId]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    if (!$row) {
        return [];
    }

    $latest =
        trim(
            (string) (
                $row['latest_verification_at']
                ?? $row['last_verified_at']
                ?? ''
            )
        );

    $freshness = 'never';

    if ($latest !== '') {
        try {
            $verified =
                new DateTimeImmutable(
                    $latest
                );

            $days =
                max(
                    0,
                    (int)
                    floor(
                        (
                            time()
                            - $verified->getTimestamp()
                        ) / 86400
                    )
                );

            $freshness =
                $days > 730
                    ? 'overdue'
                    : (
                        $days > 365
                            ? 'attention'
                            : 'current'
                    );

            $row['verification_age_days'] =
                $days;
        } catch (Throwable) {
            $freshness = 'attention';
        }
    }

    $row['verification_freshness'] =
        $freshness;

    $row['is_published'] =
        in_array(
            (string) (
                $row['status']
                ?? ''
            ),
            [
                'active',
                'featured',
            ],
            true
        );

    $row['ever_llama_scouted'] =
        (int) (
            $row['llama_scouted_count']
            ?? 0
        ) > 0;

    return $row;
}


function admin_report_recent_updates(
    PDO $db,
    int $placeId,
    int $limit = 8
): array {
    $limit =
        max(
            1,
            min(
                20,
                $limit
            )
        );

    $stmt =
        $db->prepare(
            'SELECT
                pus.id,
                pus.update_type,
                pus.status,
                pus.role_at_submission,
                pus.visited_at,
                pus.proposed_changes,
                pus.contributor_notes,
                pus.review_notes,
                pus.points_awarded,
                pus.submitted_at,
                pus.updated_at,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "Former member"
                ) AS contributor_name
             FROM place_update_submissions pus
             LEFT JOIN users u
                ON u.id = pus.user_id
             WHERE pus.place_id = ?
             ORDER BY
                CASE
                    WHEN pus.status IN (
                        "pending",
                        "needs-changes"
                    )
                        THEN 0
                    ELSE 1
                END,
                pus.submitted_at DESC,
                pus.id DESC
             LIMIT ' .
             $limit
        );

    $stmt->execute([
        $placeId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}


function admin_report_place_unresolved(
    PDO $db,
    int $placeId,
    int $excludeReportId = 0
): array {
    $where =
        'pr.place_id = ?
         AND pr.status IN (
            "open",
            "investigating"
         )';

    $params = [
        $placeId,
    ];

    if ($excludeReportId > 0) {
        $where .=
            ' AND pr.id <> ?';

        $params[] =
            $excludeReportId;
    }

    $stmt =
        $db->prepare(
            'SELECT
                pr.id,
                pr.problem_type,
                pr.status,
                pr.created_at,
                pr.details,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "Former member"
                ) AS reporter_name
             FROM place_reports pr
             LEFT JOIN users u
                ON u.id = pr.user_id
             WHERE ' .
             $where .
             ' ORDER BY
                CASE pr.problem_type
                    WHEN "safety" THEN 1
                    WHEN "closure-status" THEN 1
                    WHEN "location-access" THEN 2
                    ELSE 3
                END,
                pr.created_at ASC,
                pr.id ASC
             LIMIT 30'
        );

    $stmt->execute(
        $params
    );

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];
}


function admin_report_freshness_label(
    string $state,
    ?int $days = null
): string {
    return match ($state) {
        'never' =>
            'Never verified',

        'overdue' =>
            $days !== null
                ? number_format($days) .
                    ' days since verification'
                : 'Verification is over 2 years old',

        'attention' =>
            $days !== null
                ? number_format($days) .
                    ' days since verification'
                : 'Verification is over 1 year old',

        default =>
            $days !== null
                ? number_format($days) .
                    ' days since verification'
                : 'Verification is current',
    };
}


function admin_report_set_status(
    PDO $db,
    int $reportId,
    int $actorUserId,
    string $newStatus,
    string $notes
): void {
    $allowed = [
        'open',
        'investigating',
        'resolved',
        'dismissed',
    ];

    if (
        !in_array(
            $newStatus,
            $allowed,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid report status.'
        );
    }

    $notes = trim($notes);

    if (
        in_array(
            $newStatus,
            [
                'resolved',
                'dismissed',
            ],
            true
        )
        && $notes === ''
    ) {
        throw new InvalidArgumentException(
            'Resolution notes are required before closing a report.'
        );
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'SELECT *
             FROM place_reports
             WHERE id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $stmt->execute([$reportId]);

        $report =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            throw new RuntimeException(
                'The Place report could not be found.'
            );
        }

        $oldStatus =
            (string) $report['status'];

        $reviewedAt =
            in_array(
                $newStatus,
                [
                    'resolved',
                    'dismissed',
                ],
                true
            )
                ? date('Y-m-d H:i:s')
                : null;

        $update = $db->prepare(
            'UPDATE place_reports
             SET
                status = ?,
                reviewed_by = ?,
                reviewed_at = ?,
                resolution_notes = ?
             WHERE id = ?'
        );

        $update->execute([
            $newStatus,
            $actorUserId,
            $reviewedAt,
            $notes !== ''
                ? $notes
                : null,
            $reportId,
        ]);

        if (
            $oldStatus !== $newStatus
            || $notes !==
                trim(
                    (string) (
                        $report['resolution_notes']
                        ?? ''
                    )
                )
        ) {
            $history = $db->prepare(
                'INSERT INTO place_report_history (
                    report_id,
                    old_status,
                    new_status,
                    notes,
                    changed_by
                 ) VALUES (?, ?, ?, ?, ?)'
            );

            $history->execute([
                $reportId,
                $oldStatus,
                $newStatus,
                $notes !== ''
                    ? $notes
                    : null,
                $actorUserId,
            ]);
        }

        admin_users_audit(
            $db,
            $actorUserId,
            null,
            'place.report_status_updated',
            'Updated Place report #' .
                $reportId .
                ' from ' .
                $oldStatus .
                ' to ' .
                $newStatus .
                '.',
            [
                'report_id' =>
                    $reportId,
                'place_id' =>
                    (int) $report['place_id'],
                'before' =>
                    $oldStatus,
                'after' =>
                    $newStatus,
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
