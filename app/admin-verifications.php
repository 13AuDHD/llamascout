<?php

declare(strict_types=1);

/* =========================================================
   BASECAMP PLACE FRESHNESS

   This admin page is intentionally about physical field
   freshness, not generic verification activity.

   Official-source checks remain available on individual Place
   records, but they do not make a Place look freshly visited.
   ========================================================= */

function admin_place_freshness_stats(PDO $db): array
{
    $sql = '
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN last_field_checked_on IS NOT NULL
                     AND last_field_checked_on >= DATE_SUB(UTC_DATE(), INTERVAL 180 DAY)
                        THEN 1
                    ELSE 0
                END
            ) AS fresh,
            SUM(
                CASE
                    WHEN last_field_checked_on < DATE_SUB(UTC_DATE(), INTERVAL 180 DAY)
                     AND last_field_checked_on >= DATE_SUB(UTC_DATE(), INTERVAL 365 DAY)
                        THEN 1
                    ELSE 0
                END
            ) AS aging,
            SUM(
                CASE
                    WHEN last_field_checked_on < DATE_SUB(UTC_DATE(), INTERVAL 365 DAY)
                        THEN 1
                    ELSE 0
                END
            ) AS attention,
            SUM(
                CASE
                    WHEN last_field_checked_on IS NULL
                        THEN 1
                    ELSE 0
                END
            ) AS never_checked
        FROM places
        WHERE status IN ("active", "featured")
    ';

    $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'fresh' => (int) ($row['fresh'] ?? 0),
        'aging' => (int) ($row['aging'] ?? 0),
        'attention' => (int) ($row['attention'] ?? 0),
        'never_checked' => (int) ($row['never_checked'] ?? 0),
    ];
}

function admin_place_freshness_rows(
    PDO $db,
    string $search = '',
    string $state = '',
    int $limit = 500
): array {
    $limit = max(1, min(1000, $limit));
    $search = trim($search);
    $state = trim($state);

    $where = [
        'p.status IN ("active", "featured")',
    ];
    $params = [];

    if ($search !== '') {
        $where[] = '(
            p.name LIKE ?
            OR p.city LIKE ?
            OR p.county LIKE ?
            OR p.state LIKE ?
            OR CAST(p.id AS CHAR) = ?
        )';

        $needle = '%' . $search . '%';
        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle,
            $search
        );
    }

    if ($state === 'fresh') {
        $where[] = 'p.last_field_checked_on IS NOT NULL
            AND p.last_field_checked_on >= DATE_SUB(UTC_DATE(), INTERVAL 180 DAY)';
    } elseif ($state === 'aging') {
        $where[] = 'p.last_field_checked_on < DATE_SUB(UTC_DATE(), INTERVAL 180 DAY)
            AND p.last_field_checked_on >= DATE_SUB(UTC_DATE(), INTERVAL 365 DAY)';
    } elseif ($state === 'attention') {
        $where[] = 'p.last_field_checked_on < DATE_SUB(UTC_DATE(), INTERVAL 365 DAY)';
    } elseif ($state === 'never') {
        $where[] = 'p.last_field_checked_on IS NULL';
    }

    $sql =
        'SELECT
            p.id,
            p.name,
            p.slug,
            p.status,
            p.city,
            p.county,
            p.state,
            p.last_field_checked_on,
            DATEDIFF(UTC_DATE(), p.last_field_checked_on) AS days_since_field_check,
            (
                SELECT MAX(pc.checked_in_at)
                FROM place_checkins pc
                WHERE pc.place_id = p.id
                  AND pc.contribution_level IN ("community", "member")
            ) AS community_last_checked_at,
            (
                SELECT MAX(pc.checked_in_at)
                FROM place_checkins pc
                WHERE pc.place_id = p.id
                  AND pc.contribution_level IN ("scout", "master-scout", "admin")
            ) AS scout_checkin_last_checked_at,
            (
                SELECT MAX(
                    COALESCE(
                        CONCAT(pv.visited_at, " 12:00:00"),
                        pv.verified_at
                    )
                )
                FROM place_verifications pv
                WHERE pv.place_id = p.id
                  AND pv.verification_type = "field-verified"
            ) AS legacy_scout_last_checked_at,
            (
                SELECT pc.contribution_level
                FROM place_checkins pc
                WHERE pc.place_id = p.id
                ORDER BY pc.checked_in_at DESC, pc.id DESC
                LIMIT 1
            ) AS latest_checkin_level,
            (
                SELECT COUNT(*)
                FROM place_reports pr
                WHERE pr.place_id = p.id
                  AND pr.status IN ("open", "investigating")
            ) AS open_report_count
         FROM places p
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY
            CASE
                WHEN p.last_field_checked_on IS NULL THEN 1
                WHEN p.last_field_checked_on < DATE_SUB(UTC_DATE(), INTERVAL 365 DAY) THEN 2
                WHEN p.last_field_checked_on < DATE_SUB(UTC_DATE(), INTERVAL 180 DAY) THEN 3
                ELSE 4
            END,
            p.last_field_checked_on ASC,
            p.name ASC
         LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $communityLast =
            trim((string) ($row['community_last_checked_at'] ?? '')) ?: null;

        $scoutLast = llama_place_freshness_latest_value(
            trim((string) ($row['scout_checkin_last_checked_at'] ?? '')) ?: null,
            trim((string) ($row['legacy_scout_last_checked_at'] ?? '')) ?: null
        );

        $overallLast = llama_place_freshness_latest_value(
            $communityLast,
            $scoutLast,
            trim((string) ($row['last_field_checked_on'] ?? '')) ?: null
        );

        $row['overall_last_checked_at'] = $overallLast;
        $row['community_last_checked_at'] = $communityLast;
        $row['scout_last_checked_at'] = $scoutLast;
        $row['freshness_state'] = llama_place_freshness_state($overallLast);
        $row['freshness_label'] = llama_place_freshness_state_label(
            (string) $row['freshness_state']
        );
    }
    unset($row);

    return $rows;
}

function admin_place_freshness_attention_queue(
    PDO $db,
    int $limit = 100
): array {
    $rows = admin_place_freshness_rows($db, '', '', max($limit * 2, 100));

    $rows = array_values(
        array_filter(
            $rows,
            static fn(array $row): bool =>
                in_array(
                    (string) ($row['freshness_state'] ?? ''),
                    ['aging', 'attention', 'never'],
                    true
                )
        )
    );

    return array_slice($rows, 0, $limit);
}

function admin_place_recent_field_checks(
    PDO $db,
    int $limit = 100
): array {
    $limit = max(1, min(250, $limit));

    $sql =
        'SELECT *
         FROM (
            SELECT
                "checkin" AS record_kind,
                pc.id AS record_id,
                pc.place_id,
                pc.user_id,
                pc.contribution_level,
                pc.checked_in_at AS checked_at,
                pc.points_awarded,
                pc.distance_meters,
                pc.accuracy_meters,
                p.name AS place_name,
                p.slug AS place_slug,
                p.status AS place_status,
                p.city,
                p.county,
                p.state,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "Member"
                ) AS checker_name,
                u.username AS checker_username
            FROM place_checkins pc
            INNER JOIN places p
                ON p.id = pc.place_id
            LEFT JOIN users u
                ON u.id = pc.user_id

            UNION ALL

            SELECT
                "legacy-field" AS record_kind,
                pv.id AS record_id,
                pv.place_id,
                pv.verified_by AS user_id,
                "scout" AS contribution_level,
                COALESCE(
                    CONCAT(pv.visited_at, " 12:00:00"),
                    pv.verified_at
                ) AS checked_at,
                0 AS points_awarded,
                NULL AS distance_meters,
                NULL AS accuracy_meters,
                p.name AS place_name,
                p.slug AS place_slug,
                p.status AS place_status,
                p.city,
                p.county,
                p.state,
                COALESCE(
                    NULLIF(u.display_name, ""),
                    NULLIF(u.username, ""),
                    "Llama Scout"
                ) AS checker_name,
                u.username AS checker_username
            FROM place_verifications pv
            INNER JOIN places p
                ON p.id = pv.place_id
            LEFT JOIN users u
                ON u.id = pv.verified_by
            WHERE pv.verification_type = "field-verified"
              AND NOT EXISTS (
                    SELECT 1
                    FROM place_checkins linked
                    WHERE linked.verification_id = pv.id
              )
         ) field_checks
         ORDER BY checked_at DESC, record_id DESC
         LIMIT ' . $limit;

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
