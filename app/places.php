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

    $place['featured_image'] = place_public_featured_image((int) $place['id']);
    $place['amenities'] = place_public_amenities((int) $place['id']);

    return $place;
}


function place_public_featured_image(int $placeId): ?array
{
    $stmt = db()->prepare(
        "
        SELECT
            src,
            alt_text

        FROM place_images

        WHERE place_id = ?
          AND is_featured = 1

        ORDER BY
            sort_order ASC,
            id ASC

        LIMIT 1
        "
    );

    $stmt->execute([$placeId]);

    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    return $image ?: null;
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

function place_member_by_slug(string $slug): ?array
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

            p.latitude,
            p.longitude,
            p.elevation_feet,
            p.road,

            p.city,
            p.county,
            p.state,
            p.region,
            p.land_manager,
            p.land_type,

            p.sensory_summary,
            p.access_summary,

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

    $placeId = (int) $place['id'];

    $place['images'] = place_member_images($placeId);
    $place['amenities'] = place_public_amenities($placeId);
    $place['details'] = place_member_row('place_details', $placeId);
    $place['connectivity'] = place_member_row('place_connectivity', $placeId);
    $place['sensory_details'] = place_member_row('place_sensory_details', $placeId);
    $place['rules'] = place_member_row('place_rules', $placeId);
    $place['experience'] = place_member_row('place_experience', $placeId);
    $place['notes'] = place_member_notes($placeId);

    $place['sensory'] = place_member_sensory($placeId);

    return $place;
}


function place_member_images(int $placeId): array
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


function place_member_row(string $table, int $placeId): array
{
    $allowedTables = [
        'place_details',
        'place_connectivity',
        'place_sensory_details',
        'place_rules',
        'place_experience',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Invalid place data table.');
    }

    $stmt = db()->prepare(
        "SELECT *
         FROM `$table`
         WHERE place_id = ?
         LIMIT 1"
    );

    $stmt->execute([$placeId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: [];
}


function place_member_sensory(int $placeId): array
{
    $stmt = db()->prepare(
        "
        SELECT
            period,
            noise,
            traffic,
            crowds,
            privacy,
            light_pollution,
            sensory_comfort,
            social_interaction_likelihood

        FROM place_sensory

        WHERE place_id = ?

        ORDER BY
            CASE period
                WHEN 'daytime' THEN 1
                WHEN 'nighttime' THEN 2
                ELSE 3
            END
        "
    );

    $stmt->execute([$placeId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sensory = [];

    foreach ($rows as $row) {
        $period = (string) $row['period'];
        unset($row['period']);

        $sensory[$period] = $row;
    }

    return $sensory;
}






function place_member_notes(int $placeId): array
{
    $stmt = db()->prepare(
        "
        SELECT
            note,
            sort_order

        FROM place_notes

        WHERE place_id = ?

        ORDER BY
            sort_order ASC,
            id ASC
        "
    );

    $stmt->execute([$placeId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
