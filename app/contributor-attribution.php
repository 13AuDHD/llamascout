<?php

declare(strict_types=1);

/**
 * Loads approved contributor attribution for a single Place.
 *
 * Privacy rules:
 * - Signed-in members may see the basic identity of active members.
 * - Logged-out visitors only see identity when that contributor has
 *   explicitly enabled a public profile.
 * - Disabled, suspended, deleted, or otherwise unavailable accounts
 *   are represented without exposing their former identity.
 */
function llama_place_contributors(
    PDO $db,
    int $placeId,
    ?int $viewerUserId = null
): array {
    if ($placeId < 1) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT
            pc.user_id,
            COUNT(*) AS contribution_count,
            COALESCE(SUM(pc.points_awarded), 0) AS place_points,
            MIN(pc.approved_at) AS first_approved_at,
            MAX(pc.approved_at) AS latest_approved_at,
            SUM(CASE WHEN pc.contribution_type = \'new_place\' THEN 1 ELSE 0 END) AS new_place_count,
            SUM(CASE WHEN pc.contribution_type <> \'new_place\' THEN 1 ELSE 0 END) AS improvement_count,
            u.username,
            u.display_name,
            u.status,
            COALESCE(cp.is_public, 0) AS is_public
         FROM place_contributions pc
         LEFT JOIN users u ON u.id = pc.user_id
         LEFT JOIN community_profiles cp ON cp.user_id = pc.user_id
         WHERE pc.place_id = ?
           AND pc.status = \'approved\'
         GROUP BY
            pc.user_id,
            u.username,
            u.display_name,
            u.status,
            cp.is_public
         ORDER BY
            new_place_count DESC,
            first_approved_at ASC,
            pc.user_id ASC'
    );

    $stmt->execute([$placeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $signedIn = ($viewerUserId ?? 0) > 0;
    $contributors = [];

    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $username = trim((string) ($row['username'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        $isPublic = (int) ($row['is_public'] ?? 0) === 1;
        $activeIdentity = $userId > 0 && $status === 'active' && $username !== '';
        $identityVisible = $activeIdentity && ($signedIn || $isPublic);

        $contributionCount = (int) ($row['contribution_count'] ?? 0);
        $newPlaceCount = (int) ($row['new_place_count'] ?? 0);
        $improvementCount = (int) ($row['improvement_count'] ?? 0);

        $role = $newPlaceCount > 0
            ? ($improvementCount > 0 ? 'Original contributor and editor' : 'Original contributor')
            : 'Contributor';

        $item = [
            'user_id' => $userId,
            'identity_visible' => $identityVisible,
            'is_public' => $isPublic,
            'username' => $identityVisible ? $username : '',
            'display_name' => $identityVisible
                ? ($displayName !== '' ? $displayName : $username)
                : ($activeIdentity ? 'Llama Scout member' : 'Former member'),
            'profile_url' => $identityVisible ? llama_profile_url($username) : null,
            'image_url' => llama_profile_image_url(
                $identityVisible
                    ? llama_primary_profile_image($db, $userId)
                    : LLAMA_DEFAULT_PROFILE_IMAGE
            ),
            'badges' => $identityVisible ? llama_user_badges($db, $userId) : [],
            'role' => $role,
            'contribution_count' => $contributionCount,
            'place_points' => (int) ($row['place_points'] ?? 0),
            'new_place_count' => $newPlaceCount,
            'improvement_count' => $improvementCount,
            'first_approved_at' => $row['first_approved_at'] ?? null,
            'latest_approved_at' => $row['latest_approved_at'] ?? null,
        ];

        $contributors[] = $item;
    }

    return $contributors;
}

function llama_contributor_badge_icon(array $badge): string
{
    $icon = trim((string) ($badge['icon'] ?? ''));

    if ($icon === '') {
        return 'fa-award';
    }

    if (str_starts_with($icon, 'fa-')) {
        return $icon;
    }

    return 'fa-' . $icon;
}
