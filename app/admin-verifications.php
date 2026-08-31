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
