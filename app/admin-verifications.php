<?php

declare(strict_types=1);

function admin_verifications_list(
    PDO $db,
    string $search = '',
    string $type = '',
    string $age = ''
): array {
    $where = ['1 = 1'];
    $params = [];

    $search = trim($search);
    $type = trim($type);
    $age = trim($age);

    if ($search !== '') {
        $where[] = '(
            p.name LIKE ?
            OR p.city LIKE ?
            OR p.county LIKE ?
            OR p.state LIKE ?
            OR pv.source LIKE ?
            OR pv.notes LIKE ?
            OR CAST(p.id AS CHAR) = ?
        )';

        $needle = '%' . $search . '%';

        array_push(
            $params,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $needle,
            $search
        );
    }

    if ($type !== '') {
        $where[] = 'pv.verification_type = ?';
        $params[] = $type;
    }

    if ($age === '30') {
        $where[] = 'pv.verified_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    } elseif ($age === '90') {
        $where[] = 'pv.verified_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
    } elseif ($age === '365') {
        $where[] = 'pv.verified_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
    } elseif ($age === 'older-365') {
        $where[] = 'pv.verified_at < DATE_SUB(NOW(), INTERVAL 365 DAY)';
    }

    $sql =
        'SELECT
            pv.*,
            p.name AS place_name,
            p.slug AS place_slug,
            p.status AS place_status,
            p.city,
            p.county,
            p.state,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "System"
            ) AS verifier_name,
            u.username AS verifier_username,
            (
                SELECT cpi.image_src
                FROM community_profile_images cpi
                WHERE cpi.user_id = u.id
                ORDER BY
                    cpi.sort_order ASC,
                    cpi.id ASC
                LIMIT 1
            ) AS verifier_profile_image
         FROM place_verifications pv
         INNER JOIN places p
            ON p.id = pv.place_id
         LEFT JOIN users u
            ON u.id = pv.verified_by
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY
            pv.verified_at DESC,
            pv.id DESC
         LIMIT 500';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_verification_types(
    PDO $db
): array {
    $stmt = $db->query(
        'SELECT DISTINCT verification_type
         FROM place_verifications
         WHERE verification_type IS NOT NULL
           AND verification_type <> ""
         ORDER BY verification_type ASC'
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

function admin_verification_stats(
    PDO $db
): array {
    $sql = '
        SELECT
            COUNT(*) AS total,
            SUM(
                CASE
                    WHEN verified_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        THEN 1
                    ELSE 0
                END
            ) AS last_30,
            SUM(
                CASE
                    WHEN verification_type = "llama-scouted"
                        THEN 1
                    ELSE 0
                END
            ) AS llama_scouted,
            SUM(
                CASE
                    WHEN public_data_verified = 1
                        THEN 1
                    ELSE 0
                END
            ) AS public_checked
        FROM place_verifications
    ';

    $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'last_30' => (int) ($row['last_30'] ?? 0),
        'llama_scouted' => (int) ($row['llama_scouted'] ?? 0),
        'public_checked' => (int) ($row['public_checked'] ?? 0),
    ];
}


function admin_verification_attention_queue(
    PDO $db,
    int $limit = 100
): array {
    $limit = max(1, min(250, $limit));

    $sql =
        'SELECT
            p.id,
            p.name,
            p.slug,
            p.status,
            p.city,
            p.county,
            p.state,
            p.last_verified_at,
            p.updated_at,
            p.source_type,
            (
                SELECT COUNT(*)
                FROM place_verifications pv
                WHERE pv.place_id = p.id
            ) AS verification_count,
            (
                SELECT COUNT(*)
                FROM place_reports pr
                WHERE pr.place_id = p.id
                  AND pr.status IN ("open","investigating")
            ) AS open_report_count,
            CASE
                WHEN p.last_verified_at IS NULL
                    THEN "never"
                WHEN p.last_verified_at < DATE_SUB(NOW(), INTERVAL 730 DAY)
                    THEN "overdue"
                WHEN p.last_verified_at < DATE_SUB(NOW(), INTERVAL 365 DAY)
                    THEN "attention"
                ELSE "current"
            END AS freshness_state,
            DATEDIFF(
                NOW(),
                p.last_verified_at
            ) AS days_since_verified
         FROM places p
         WHERE p.status IN (
            "active",
            "featured",
            "draft",
            "unlisted"
         )
         ORDER BY
            CASE
                WHEN p.last_verified_at IS NULL THEN 1
                WHEN p.last_verified_at < DATE_SUB(NOW(), INTERVAL 730 DAY) THEN 2
                WHEN p.last_verified_at < DATE_SUB(NOW(), INTERVAL 365 DAY) THEN 3
                ELSE 4
            END,
            CASE
                WHEN p.status IN ("featured","active") THEN 1
                ELSE 2
            END,
            p.last_verified_at ASC,
            p.updated_at DESC,
            p.name ASC
         LIMIT ' . $limit;

    return
        $db->query($sql)->fetchAll(PDO::FETCH_ASSOC)
        ?: [];
}


function admin_verification_attention_stats(
    PDO $db
): array {
    $sql =
        'SELECT
            COUNT(*) AS total_places,
            SUM(
                CASE
                    WHEN status IN ("active","featured")
                     AND last_verified_at IS NULL
                        THEN 1
                    ELSE 0
                END
            ) AS published_never_verified,
            SUM(
                CASE
                    WHEN status IN ("active","featured")
                     AND last_verified_at < DATE_SUB(NOW(), INTERVAL 365 DAY)
                        THEN 1
                    ELSE 0
                END
            ) AS published_stale,
            SUM(
                CASE
                    WHEN status IN ("active","featured")
                     AND last_verified_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
                        THEN 1
                    ELSE 0
                END
            ) AS published_current
         FROM places
         WHERE status IN (
            "active",
            "featured",
            "draft",
            "unlisted"
         )';

    $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'total_places' =>
            (int) ($row['total_places'] ?? 0),
        'published_never_verified' =>
            (int) ($row['published_never_verified'] ?? 0),
        'published_stale' =>
            (int) ($row['published_stale'] ?? 0),
        'published_current' =>
            (int) ($row['published_current'] ?? 0),
    ];
}


function admin_verification_freshness_label(
    string $state
): string {
    return match ($state) {
        'never' => 'Never verified',
        'overdue' => 'Over 2 years old',
        'attention' => 'Over 1 year old',
        default => 'Current',
    };
}
