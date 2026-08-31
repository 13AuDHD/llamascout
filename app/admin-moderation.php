<?php

declare(strict_types=1);

function admin_moderation_avatar_sql(
    string $userAlias = 'u'
): string {
    return '(SELECT cpi.image_src
             FROM community_profile_images cpi
             WHERE cpi.user_id = ' . $userAlias . '.id
             ORDER BY
                cpi.sort_order ASC,
                cpi.id ASC
             LIMIT 1)';
}

function admin_moderation_new_places(
    PDO $db,
    int $limit = 40
): array {
    $limit = max(1, min(100, $limit));

    $sql =
        'SELECT
            ps.id,
            ps.user_id,
            ps.place_name,
            ps.status,
            ps.role_at_submission,
            ps.submitted_at,
            ps.updated_at,
            ps.reviewed_at,
            ps.review_notes,
            ps.place_id,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS contributor_name,
            u.username,
            ' . admin_moderation_avatar_sql('u') . ' AS profile_image_src
         FROM place_submissions ps
         INNER JOIN users u
            ON u.id = ps.user_id
         WHERE ps.status IN (
            "pending",
            "needs-changes"
         )
         ORDER BY
            ps.submitted_at ASC,
            ps.id ASC
         LIMIT ' . $limit;

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_moderation_updates(
    PDO $db,
    int $limit = 40
): array {
    $limit = max(1, min(100, $limit));

    $sql =
        'SELECT
            pus.id,
            pus.place_id,
            pus.user_id,
            pus.update_type,
            pus.status,
            pus.role_at_submission,
            pus.visited_at,
            pus.proposed_changes,
            pus.submitted_at,
            pus.updated_at,
            pus.reviewed_at,
            pus.review_notes,
            p.name AS place_name,
            p.slug AS place_slug,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS contributor_name,
            u.username,
            ' . admin_moderation_avatar_sql('u') . ' AS profile_image_src
         FROM place_update_submissions pus
         INNER JOIN places p
            ON p.id = pus.place_id
         INNER JOIN users u
            ON u.id = pus.user_id
         WHERE pus.status IN (
            "pending",
            "needs-changes"
         )
         ORDER BY
            pus.submitted_at ASC,
            pus.id ASC
         LIMIT ' . $limit;

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_moderation_reports(
    PDO $db,
    int $limit = 40
): array {
    $limit = max(1, min(100, $limit));

    $sql =
        'SELECT
            pr.id,
            pr.place_id,
            pr.user_id,
            pr.problem_type,
            pr.details,
            pr.status,
            pr.created_at,
            pr.reviewed_at,
            pr.resolution_notes,
            p.name AS place_name,
            p.slug AS place_slug,
            COALESCE(
                NULLIF(u.display_name, ""),
                NULLIF(u.username, ""),
                "Former Llama Scout Member"
            ) AS contributor_name,
            u.username,
            ' . admin_moderation_avatar_sql('u') . ' AS profile_image_src,
            (
                SELECT COUNT(*)
                FROM place_report_images pri
                WHERE pri.report_id = pr.id
            ) AS image_count
         FROM place_reports pr
         INNER JOIN places p
            ON p.id = pr.place_id
         INNER JOIN users u
            ON u.id = pr.user_id
         WHERE pr.status IN (
            "open",
            "investigating"
         )
         ORDER BY
            CASE
                WHEN pr.status = "open" THEN 0
                ELSE 1
            END,
            pr.created_at ASC,
            pr.id ASC
         LIMIT ' . $limit;

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function admin_moderation_stats(
    PDO $db
): array {
    $queries = [
        'new_places' =>
            'SELECT COUNT(*)
             FROM place_submissions
             WHERE status IN ("pending","needs-changes")',

        'updates' =>
            'SELECT COUNT(*)
             FROM place_update_submissions
             WHERE status IN ("pending","needs-changes")',

        'reports' =>
            'SELECT COUNT(*)
             FROM place_reports
             WHERE status IN ("open","investigating")',

        'oldest_submission' =>
            'SELECT MIN(submitted_at)
             FROM place_submissions
             WHERE status IN ("pending","needs-changes")',

        'oldest_update' =>
            'SELECT MIN(submitted_at)
             FROM place_update_submissions
             WHERE status IN ("pending","needs-changes")',

        'oldest_report' =>
            'SELECT MIN(created_at)
             FROM place_reports
             WHERE status IN ("open","investigating")',
    ];

    $result = [];

    foreach ($queries as $key => $sql) {
        try {
            $value = $db->query($sql)->fetchColumn();

            if (str_starts_with($key, 'oldest_')) {
                $result[$key] =
                    $value !== false
                    && $value !== null
                    && $value !== ''
                        ? (string) $value
                        : null;
            } else {
                $result[$key] = (int) $value;
            }
        } catch (Throwable $exception) {
            $result[$key] =
                str_starts_with($key, 'oldest_')
                    ? null
                    : 0;
        }
    }

    return $result;
}

function admin_moderation_age_label(
    ?string $dateTime
): string {
    if (!$dateTime) {
        return 'Unknown age';
    }

    try {
        $then = new DateTimeImmutable($dateTime);
        $now = new DateTimeImmutable('now');

        $seconds = max(
            0,
            $now->getTimestamp() - $then->getTimestamp()
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
        return 'Unknown age';
    }
}
