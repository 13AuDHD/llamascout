<?php

declare(strict_types=1);

function places_public(): array
{
    $db = db();

    $stmt = $db->query(
        "
        SELECT
            id,
            slug,
            name,
            type,
            status,
            public_summary,
            city,
            county,
            state,
            region,
            land_manager,
            land_type,
            latitude,
            longitude

        FROM places

        WHERE status IN (
            'active',
            'featured'
        )

        ORDER BY
            CASE status
                WHEN 'featured' THEN 1
                ELSE 2
            END,
            name ASC
        "
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function place_public_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "
        SELECT
            id,
            slug,
            name,
            type,
            status,
            public_summary,
            city,
            county,
            state,
            region,
            land_manager,
            land_type,
            latitude,
            longitude

        FROM places

        WHERE slug = ?
          AND status IN (
              'active',
              'featured'
          )

        LIMIT 1
        "
    );

    $stmt->execute([$slug]);

    $place = $stmt->fetch(PDO::FETCH_ASSOC);

    return $place ?: null;
}
