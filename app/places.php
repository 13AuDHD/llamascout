<?php

declare(strict_types=1);

require_once __DIR__ . '/geography.php';


/* =========================================================
   COUNTY DISPLAY NORMALIZATION
   ========================================================= */

function places_preferred_county_label(
    array $labels
): ?string {
    $cleaned = [];

    foreach ($labels as $label) {
        $value =
            llama_county_text_cleanup(
                is_string($label)
                    ? $label
                    : null
            );

        if ($value === null) {
            continue;
        }

        $cleaned[$value] = $value;
    }

    if (!$cleaned) {
        return null;
    }

    $values =
        array_values($cleaned);

    $officialSuffixPattern =
        '/\s+(County|Parish|Borough|Census Area|Municipio|Municipality)$/iu';

    foreach ($values as $value) {
        if (
            preg_match(
                $officialSuffixPattern,
                $value
            )
        ) {
            return $value;
        }
    }

    usort(
        $values,
        static fn (
            string $a,
            string $b
        ): int =>
            mb_strlen($b, 'UTF-8')
            <=>
            mb_strlen($a, 'UTF-8')
    );

    return
        $values[0]
        ?? null;
}


function places_normalize_county_rows(
    array $rows
): array {
    if (!$rows) {
        return $rows;
    }

    $groups = [];

    foreach ($rows as $index => $row) {
        $county =
            llama_county_text_cleanup(
                isset($row['county'])
                    ? (string) $row['county']
                    : null
            );

        if ($county === null) {
            continue;
        }

        $state =
            mb_strtolower(
                trim(
                    (string) (
                        $row['state']
                        ?? ''
                    )
                ),
                'UTF-8'
            );

        $countyKey =
            llama_county_comparison_key(
                $county
            );

        if ($countyKey === '') {
            continue;
        }

        $groupKey =
            $state
            . '|'
            . $countyKey;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'indexes' => [],
                'labels' => [],
            ];
        }

        $groups[$groupKey]['indexes'][] =
            $index;

        $groups[$groupKey]['labels'][] =
            $county;
    }

    foreach ($groups as $group) {
        $preferred =
            places_preferred_county_label(
                $group['labels']
            );

        if ($preferred === null) {
            continue;
        }

        foreach (
            $group['indexes']
            as $index
        ) {
            $rows[$index]['county'] =
                $preferred;
        }
    }

    return $rows;
}


function places_normalize_single_county(
    array $place
): array {
    if (
        array_key_exists(
            'county',
            $place
        )
    ) {
        $place['county'] =
            llama_county_text_cleanup(
                is_string($place['county'])
                    ? $place['county']
                    : null
            );
    }

    return $place;
}


/* =========================================================
   PARTNER DEMO VISIBILITY

   Demo Places are represented by a place_partners relationship_type
   of "demo". They remain Draft publicly, but Owners and Admins can
   view them on the normal map and Place page.

   This helper is intentionally centralized so partner preview grants
   can be added later without rewriting every Place query again.
   ========================================================= */

function llama_partner_demo_viewer_can_access(
    ?int $userId = null
): bool {
    if ($userId === null) {
        $user =
            current_user();

        $userId =
            is_array($user)
                ? (int) (
                    $user['id']
                    ?? 0
                )
                : 0;
    }

    if ($userId < 1) {
        return false;
    }

    return
        user_has_role(
            'owner',
            $userId
        )
        || user_has_role(
            'admin',
            $userId
        );
}


function llama_partner_demo_exists_sql(
    string $placeAlias = 'p'
): string {
    $placeAlias =
        preg_replace(
            '/[^a-zA-Z0-9_]/',
            '',
            $placeAlias
        )
        ?: 'p';

    return
        'EXISTS (
            SELECT 1
            FROM place_partners pp_demo
            WHERE pp_demo.place_id = '
        . $placeAlias
        . '.id
              AND pp_demo.relationship_type = "demo"
        )';
}


function llama_partner_demo_visibility_sql(
    bool $canViewDemo,
    string $placeAlias = 'p'
): string {
    $demoExists =
        llama_partner_demo_exists_sql(
            $placeAlias
        );

    if ($canViewDemo) {
        return
            '(
                (
                    '
            . $placeAlias
            . '.status IN ("active", "featured")
                    AND NOT '
            . $demoExists
            . '
                )
                OR
                (
                    '
            . $placeAlias
            . '.status NOT IN ("removed", "archived")
                    AND '
            . $demoExists
            . '
                )
            )';
    }

    return
        '(
            '
        . $placeAlias
        . '.status IN ("active", "featured")
            AND NOT '
        . $demoExists
        . '
        )';
}


function places_public(
    ?int $viewerUserId = null
): array {
    $canViewDemo =
        llama_partner_demo_viewer_can_access(
            $viewerUserId
        );

    $visibilitySql =
        llama_partner_demo_visibility_sql(
            $canViewDemo,
            'p'
        );

    $demoExistsSql =
        llama_partner_demo_exists_sql(
            'p'
        );

    $stmt = db()->query(
        "
        SELECT
            p.id,
            p.slug,
            p.name,
            p.type,
            p.status,
            p.source_type,
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
            p.last_field_checked_on,
            p.published_at,

            CASE
                WHEN $demoExistsSql
                THEN 1
                ELSE 0
            END AS is_demo,

            pi.src AS featured_image,
            pi.alt_text AS featured_image_alt,

            pa.toilets,
            pa.potable_water,
            pa.trash,
            pa.fire_ring,
            pa.picnic_table,
            pa.bear_box,
            pa.showers,
            pa.electricity,
            pa.dump_station,
            pa.wifi,
            pa.laundry

        FROM places p

        LEFT JOIN place_images pi
            ON pi.place_id = p.id
           AND pi.is_featured = 1

        LEFT JOIN place_amenities pa
            ON pa.place_id = p.id

        WHERE $visibilitySql

        ORDER BY
            CASE
                WHEN p.status = 'featured' THEN 0
                ELSE 1
            END,
            p.name ASC
        "
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $rows =
        places_normalize_county_rows(
            $rows
        );

    foreach ($rows as &$row) {
        $row['is_demo'] =
            (int) (
                $row['is_demo']
                ?? 0
            );

        $row['amenities'] = [
            'toilets' => isset($row['toilets']) ? (int) $row['toilets'] : 0,
            'potable_water' => isset($row['potable_water']) ? (int) $row['potable_water'] : 0,
            'trash' => isset($row['trash']) ? (int) $row['trash'] : 0,
            'fire_ring' => isset($row['fire_ring']) ? (int) $row['fire_ring'] : 0,
            'picnic_table' => isset($row['picnic_table']) ? (int) $row['picnic_table'] : 0,
            'bear_box' => isset($row['bear_box']) ? (int) $row['bear_box'] : 0,
            'showers' => isset($row['showers']) ? (int) $row['showers'] : 0,
            'electricity' => isset($row['electricity']) ? (int) $row['electricity'] : 0,
            'dump_station' => isset($row['dump_station']) ? (int) $row['dump_station'] : 0,
            'wifi' => isset($row['wifi']) ? (int) $row['wifi'] : 0,
            'laundry' => isset($row['laundry']) ? (int) $row['laundry'] : 0,
        ];

        unset(
            $row['toilets'],
            $row['potable_water'],
            $row['trash'],
            $row['fire_ring'],
            $row['picnic_table'],
            $row['bear_box'],
            $row['showers'],
            $row['electricity'],
            $row['dump_station'],
            $row['wifi'],
            $row['laundry']
        );
    }
    unset($row);

    return $rows;
}


/*
 * Map-safe Places response.
 *
 * Free/logged-out visitors receive only the same approximate public
 * coordinates returned by places_public().
 *
 * Member coordinates are merged only after server-side access has already
 * been confirmed by api/places.php. This keeps exact coordinates completely
 * out of free-user responses instead of merely hiding them in JavaScript.
 *
 * Owners/Admins also receive exact coordinates for Demo Places so they can
 * review the demo exactly where it will appear when a future partner-preview
 * grant is active.
 */
function places_map(
    bool $includeExactCoordinates = false,
    ?int $viewerUserId = null
): array {
    $canViewDemo =
        llama_partner_demo_viewer_can_access(
            $viewerUserId
        );

    $places =
        places_public(
            $viewerUserId
        );

    if (!$places) {
        return $places;
    }

    $contributedPlaceLookup = [];

    if (
        !$includeExactCoordinates
        && ($viewerUserId ?? 0) > 0
    ) {
        $contributedPlaceLookup =
            array_fill_keys(
                user_original_contributed_place_ids(
                    (int) $viewerUserId
                ),
                true
            );
    }

    if (
        !$includeExactCoordinates
        && !$contributedPlaceLookup
        && !$canViewDemo
    ) {
        return $places;
    }

    $stmt = db()->query(
        "
        SELECT
            id,
            latitude,
            longitude

        FROM places

        WHERE status NOT IN (
            'removed',
            'archived'
        )
        "
    );

    $exactById = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        as $row
    ) {
        $exactById[(int) $row['id']] = [
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
        ];
    }

    foreach ($places as &$place) {
        $placeId =
            (int) (
                $place['id']
                ?? 0
            );

        $isDemo =
            (int) (
                $place['is_demo']
                ?? 0
            ) === 1;

        if (
            !$includeExactCoordinates
            && !isset(
                $contributedPlaceLookup[
                    $placeId
                ]
            )
            && !(
                $canViewDemo
                && $isDemo
            )
        ) {
            continue;
        }

        $exact =
            $exactById[
                $placeId
            ]
            ?? null;

        if (!$exact) {
            continue;
        }

        $latitude =
            $exact['latitude'];

        $longitude =
            $exact['longitude'];

        if (
            $latitude !== null
            && $longitude !== null
            && is_numeric($latitude)
            && is_numeric($longitude)
        ) {
            $place['latitude'] =
                (float) $latitude;

            $place['longitude'] =
                (float) $longitude;
        }
    }
    unset($place);

    return $places;
}


function place_public_by_slug(
    string $slug
): ?array {
    $canViewDemo =
        llama_partner_demo_viewer_can_access();

    $visibilitySql =
        llama_partner_demo_visibility_sql(
            $canViewDemo,
            'p'
        );

    $demoExistsSql =
        llama_partner_demo_exists_sql(
            'p'
        );

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
            p.last_field_checked_on,
            p.published_at,

            CASE
                WHEN $demoExistsSql
                THEN 1
                ELSE 0
            END AS is_demo

        FROM places p

        WHERE p.slug = ?
          AND $visibilitySql

        LIMIT 1
        "
    );

    $stmt->execute([
        $slug,
    ]);

    $place =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$place) {
        return null;
    }

    $place =
        places_normalize_single_county(
            $place
        );

    $place['is_demo'] =
        (int) (
            $place['is_demo']
            ?? 0
        );

    $place['featured_image'] =
        place_public_featured_image(
            (int) $place['id']
        );

    $place['amenities'] =
        place_public_amenities(
            (int) $place['id']
        );

    return $place;
}


function place_public_featured_image(
    int $placeId
): ?array {
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

    $stmt->execute([
        $placeId,
    ]);

    $image =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $image
        ?: null;
}


function place_public_amenities(
    int $placeId
): array {
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
            wifi,
            laundry

        FROM place_amenities

        WHERE place_id = ?

        LIMIT 1
        "
    );

    $stmt->execute([
        $placeId,
    ]);

    $amenities =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $amenities
        ?: [];
}


function place_member_by_slug(
    string $slug
): ?array {
    $canViewDemo =
        llama_partner_demo_viewer_can_access();

    $visibilitySql =
        llama_partner_demo_visibility_sql(
            $canViewDemo,
            'p'
        );

    $demoExistsSql =
        llama_partner_demo_exists_sql(
            'p'
        );

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
            p.last_field_checked_on,
            p.published_at,

            CASE
                WHEN $demoExistsSql
                THEN 1
                ELSE 0
            END AS is_demo

        FROM places p

        WHERE p.slug = ?
          AND $visibilitySql

        LIMIT 1
        "
    );

    $stmt->execute([
        $slug,
    ]);

    $place =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$place) {
        return null;
    }

    $place =
        places_normalize_single_county(
            $place
        );

    $place['is_demo'] =
        (int) (
            $place['is_demo']
            ?? 0
        );

    $placeId =
        (int) $place['id'];

    $place['images'] =
        place_member_images(
            $placeId
        );

    $place['amenities'] =
        place_public_amenities(
            $placeId
        );

    $place['details'] =
        place_member_row(
            'place_details',
            $placeId
        );

    $place['connectivity'] =
        place_member_row(
            'place_connectivity',
            $placeId
        );

    $place['sensory_details'] =
        place_member_row(
            'place_sensory_details',
            $placeId
        );

    $place['rules'] =
        place_member_row(
            'place_rules',
            $placeId
        );

    $place['experience'] =
        place_member_row(
            'place_experience',
            $placeId
        );

    $place['sensory'] =
        place_member_sensory(
            $placeId
        );

    return $place;
}


function place_member_images(
    int $placeId
): array {
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

    $stmt->execute([
        $placeId,
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function place_member_row(
    string $table,
    int $placeId
): array {
    $allowedTables = [
        'place_details',
        'place_connectivity',
        'place_sensory_details',
        'place_rules',
        'place_experience',
    ];

    if (
        !in_array(
            $table,
            $allowedTables,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid place data table.'
        );
    }

    $stmt = db()->prepare(
        "SELECT *
         FROM `$table`
         WHERE place_id = ?
         LIMIT 1"
    );

    $stmt->execute([
        $placeId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
        ?: [];
}


function place_member_sensory(
    int $placeId
): array {
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

    $stmt->execute([
        $placeId,
    ]);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $sensory = [];

    foreach ($rows as $row) {
        $period =
            (string) $row['period'];

        unset(
            $row['period']
        );

        $sensory[$period] =
            $row;
    }

    return $sensory;
}
