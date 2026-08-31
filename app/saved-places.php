<?php

declare(strict_types=1);

function saved_places_csrf_token(): string
{
    if (empty($_SESSION['saved_places_csrf'])) {
        $_SESSION['saved_places_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['saved_places_csrf'];
}

function saved_places_verify_csrf(string $token): bool
{
    $stored = (string) ($_SESSION['saved_places_csrf'] ?? '');

    return $stored !== ''
        && $token !== ''
        && hash_equals($stored, $token);
}

function user_has_saved_place(int $userId, int $placeId): bool
{
    $stmt = db()->prepare(
        "SELECT 1
         FROM user_saved_places
         WHERE user_id = ?
           AND place_id = ?
         LIMIT 1"
    );

    $stmt->execute([$userId, $placeId]);

    return (bool) $stmt->fetchColumn();
}

function save_place_for_user(
    int $userId,
    int $placeId,
    string $slug,
    string $name
): void {
    if (user_has_saved_place($userId, $placeId)) {
        return;
    }

    $stmt = db()->prepare(
        "INSERT INTO user_saved_places
            (user_id, place_id, place_slug_snapshot, place_name_snapshot)
         VALUES
            (?, ?, ?, ?)"
    );

    $stmt->execute([
        $userId,
        $placeId,
        $slug,
        $name,
    ]);
}

function remove_saved_place_for_user(int $userId, int $placeId): void
{
    $stmt = db()->prepare(
        "DELETE FROM user_saved_places
         WHERE user_id = ?
           AND place_id = ?"
    );

    $stmt->execute([$userId, $placeId]);
}

function saved_places_for_user(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT
            usp.id AS saved_id,
            usp.saved_at,
            usp.place_id,
            COALESCE(p.slug, usp.place_slug_snapshot) AS slug,
            COALESCE(p.name, usp.place_name_snapshot, usp.place_slug_snapshot, 'Saved place') AS name,
            p.status,
            p.city,
            p.county,
            p.state,
            p.elevation_feet,
            (
                SELECT pi.src
                FROM place_images pi
                WHERE pi.place_id = p.id
                ORDER BY pi.is_featured DESC, pi.sort_order ASC, pi.id ASC
                LIMIT 1
            ) AS featured_image
         FROM user_saved_places usp
         LEFT JOIN places p
            ON p.id = usp.place_id
         WHERE usp.user_id = ?
         ORDER BY usp.saved_at DESC, usp.id DESC"
    );

    $stmt->execute([$userId]);

    return $stmt->fetchAll() ?: [];
}
