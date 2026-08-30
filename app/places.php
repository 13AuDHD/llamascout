<?php

declare(strict_types=1);

function places_public(): array
{
    $stmt = db()->query(
        "
        SELECT
            p.id,
            p.slug,
            p.name,
            p.type,
            p.status,
            p.source_type,

            p.public_summary,
            p.public_location_label,
            p.public_latitude,
            p.public_longitude,

            p.city,
            p.county,
            p.state,
            p.region,
            p.land_manager,
            p.land_type,

            p.elevation_feet,
            p.sensory_summary,
            p.access_summary,
            p.last_verified_at,
            p.published_at,

            pi.src AS featured_image,
            pi.alt_text AS featured_image_alt

        FROM places p

        LEFT JOIN place_images pi
            ON pi.place_id = p.id
           AND pi.is_featured = 1

        WHERE p.status IN ('active', 'featured')

        ORDER BY
            CASE
                WHEN p.status = 'featured' THEN 0
                ELSE 1
            END,
            p.name ASC
        "
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function place_public_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "
        SELECT
            p.id,
            p.slug,
            p.name,
            p.type,
            p.status,
            p.source_type,

            p.public_summary,
            p.public_location_label,
            p.public_latitude,
            p.public_longitude,

            p.city,
            p.county,
            p.state,
            p.region,
            p.land_manager,
            p.land_type,

            p.elevation_feet,
            p.sensory_summary,
            p.access_summary,
            p.last_verified_at,
            p.published_at,

            pi.src AS featured_image,
            pi.alt_text AS featured_image_alt

        FROM places p

        LEFT JOIN place_images pi
            ON pi.place_id = p.id
           AND pi.is_featured = 1

        WHERE p.slug = ?
          AND p.status IN ('active', 'featured')

        LIMIT 1
        "
    );

    $stmt->execute([$slug]);

    $place = $stmt->fetch(PDO::FETCH_ASSOC);

    return $place ?: null;
}
