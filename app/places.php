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
            p.description,
            p.public_summary,
            p.public_location_label,
            p.public_latitude,
            p.public_longitude,
            p.sensory_summary,
            p.access_summary,
            p.elevation_feet,
            p.city,
            p.county,
            p.state,
            p.region,
            p.land_manager,
            p.land_type,
            p.last_verified_at,
            p.published_at

        FROM places p

        WHERE p.slug = ?
          AND p.status IN ('active', 'featured')

        LIMIT 1
        "
    );

    $stmt->execute([$slug]);

    $place = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$place) {
        return null;
    }

    $place['images'] = place_public_images((int) $place['id']);
    $place['amenities'] = place_public_amenities((int) $place['id']);

    return $place;
}


function place_public_images(int $placeId): array
{
    $stmt = db()->prepare(
        "
        SELECT
            src,
            alt_text,
            is_featured,
            sort_order

        FROM place_images

        WHERE place_id = ?

        ORDER BY
            is_featured DESC,
            sort_order ASC,
            id ASC
        "
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function place_public_amenities(int $placeId): array
{
    $stmt = db()->prepare(
        "
        SELECT
            toilets,
            potable_water,
            trash,
            fire_ring,
            picnic_table,
            bear_box,
            showers,
            electricity,
            dump_station,
            food_storage_required

        FROM place_amenities

        WHERE place_id = ?

        LIMIT 1
        "
    );

    $stmt->execute([$placeId]);

    $amenities = $stmt->fetch(PDO::FETCH_ASSOC);

    return $amenities ?: [];
}
