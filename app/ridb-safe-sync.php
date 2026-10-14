<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-import.php';
require_once __DIR__ . '/ridb-canonical.php';
require_once __DIR__ . '/ridb-public-presentation.php';
require_once __DIR__ . '/ridb-complete-import.php';
require_once __DIR__ . '/ridb-facility-details.php';

function llama_ridb_safe_decode(mixed $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded)
        ? $decoded
        : [];
}

function llama_ridb_safe_related_rows(
    PDO $ridbDb,
    string $table,
    string $facilityId
): array {
    $allowed = [
        'ridb_facility_addresses',
        'ridb_media',
        'ridb_links',
        'ridb_activities',
    ];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException(
            'Unsupported RIDB related-record table.'
        );
    }

    $stmt = $ridbDb->prepare(
        'SELECT source_json
         FROM ' . $table . '
         WHERE ridb_facility_id = ?
         ORDER BY source_record_id ASC'
    );

    $stmt->execute([$facilityId]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $json) {
        $record = llama_ridb_safe_decode($json);

        if ($record) {
            $rows[] = $record;
        }
    }

    return $rows;
}

function llama_ridb_safe_cached_facility(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt = $ridbDb->prepare(
        'SELECT source_json
         FROM ridb_facilities
         WHERE ridb_facility_id = ?
         LIMIT 1'
    );

    $stmt->execute([$facilityId]);

    return llama_ridb_safe_decode(
        $stmt->fetchColumn()
    );
}

function llama_ridb_safe_cached_campsites(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt = $ridbDb->prepare(
        'SELECT
            ridb_campsite_id,
            source_json
         FROM ridb_campsites
         WHERE ridb_facility_id = ?
         ORDER BY ridb_campsite_id ASC'
    );

    $stmt->execute([$facilityId]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $siteId = trim(
            (string) (
                $row['ridb_campsite_id']
                ?? ''
            )
        );

        $record = llama_ridb_safe_decode(
            $row['source_json']
            ?? ''
        );

        if ($siteId !== '' && $record) {
            /*
             * PHP converts numeric-string array keys to integers. Prefixing
             * prevents that coercion; the sync loop removes the prefix.
             */
            $rows['site:' . $siteId] = $record;
        }
    }

    return $rows;
}

function llama_ridb_safe_cached_attributes(
    PDO $ridbDb,
    string|int $siteId
): array {
    $siteId = trim((string) $siteId);

    if ($siteId === '') {
        return [];
    }

    $stmt = $ridbDb->prepare(
        'SELECT
            source_record_id,
            attribute_name,
            attribute_value,
            source_json
         FROM ridb_campsite_attributes
         WHERE ridb_campsite_id = ?
         ORDER BY id ASC'
    );

    $stmt->execute([$siteId]);

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $decoded = llama_ridb_safe_decode(
            $row['source_json']
            ?? ''
        );

        if ($decoded) {
            $rows[] = $decoded;
            continue;
        }

        $rows[] = [
            'AttributeID' =>
                (string) (
                    $row['source_record_id']
                    ?? ''
                ),
            'AttributeName' =>
                (string) (
                    $row['attribute_name']
                    ?? ''
                ),
            'AttributeValue' =>
                (string) (
                    $row['attribute_value']
                    ?? ''
                ),
        ];
    }

    return $rows;
}

function llama_ridb_safe_fill_place_core(
    PDO $mainDb,
    int $placeId,
    array $facility,
    array $address
): int {
    $name = llama_ridb_import_text(
        llama_ridb_record_value(
            $facility,
            ['FacilityName', 'facilityName']
        )
    );

    $description = llama_ridb_import_text(
        llama_ridb_record_value(
            $facility,
            ['FacilityDescription', 'facilityDescription']
        )
    );

    $latitude = llama_ridb_import_number(
        llama_ridb_record_value(
            $facility,
            ['FacilityLatitude', 'facilityLatitude']
        )
    );

    $longitude = llama_ridb_import_number(
        llama_ridb_record_value(
            $facility,
            ['FacilityLongitude', 'facilityLongitude']
        )
    );

    $road = trim((string) ($address['address_1'] ?? ''));
    $city = trim((string) ($address['city'] ?? ''));
    $state = trim((string) ($address['state'] ?? ''));

    $stmt = $mainDb->prepare(
        'UPDATE places
         SET
            name = CASE
                WHEN name IS NULL OR name = ""
                THEN COALESCE(NULLIF(?, ""), name)
                ELSE name
            END,
            description = CASE
                WHEN description IS NULL OR description = ""
                THEN COALESCE(NULLIF(?, ""), description)
                ELSE description
            END,
            latitude = COALESCE(latitude, ?),
            longitude = COALESCE(longitude, ?),
            public_latitude = CASE
                WHEN public_latitude IS NULL AND ? IS NOT NULL
                THEN ROUND(?, 1)
                ELSE public_latitude
            END,
            public_longitude = CASE
                WHEN public_longitude IS NULL AND ? IS NOT NULL
                THEN ROUND(?, 1)
                ELSE public_longitude
            END,
            road = CASE
                WHEN road IS NULL OR road = ""
                THEN COALESCE(NULLIF(?, ""), road)
                ELSE road
            END,
            city = CASE
                WHEN city IS NULL OR city = ""
                THEN COALESCE(NULLIF(?, ""), city)
                ELSE city
            END,
            state = CASE
                WHEN state IS NULL OR state = ""
                THEN COALESCE(NULLIF(?, ""), state)
                ELSE state
            END
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $name,
        $description,
        $latitude,
        $longitude,
        $latitude,
        $latitude,
        $longitude,
        $longitude,
        $road,
        $city,
        $state,
        $placeId,
    ]);

    $count = 0;

    foreach (
        [
            $name,
            $description,
            $latitude,
            $longitude,
            $road,
            $city,
            $state,
        ]
        as $value
    ) {
        if ($value !== null && $value !== '') {
            $count++;
        }
    }

    return $count;
}

function llama_ridb_safe_replace_activities(
    PDO $mainDb,
    int $placeId,
    string $facilityId,
    array $activities
): int {
    $delete = $mainDb->prepare(
        'DELETE FROM place_facility_features
         WHERE place_id = ?
           AND source_provider = ?
           AND feature_key = ?'
    );

    $delete->execute([
        $placeId,
        'Recreation.gov / RIDB',
        'activity',
    ]);

    if (!$activities) {
        return 0;
    }

    $insert = $mainDb->prepare(
        'INSERT INTO place_facility_features
        (
            place_id,
            feature_key,
            feature_value,
            qualifier,
            source_provider,
            source_external_id,
            sort_order,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    $count = 0;
    $sort = 10;

    foreach ($activities as $index => $activity) {
        $name = llama_ridb_import_text(
            llama_ridb_record_value(
                $activity,
                ['ActivityName', 'activityName']
            )
        );

        if ($name === null) {
            continue;
        }

        $externalId = trim(
            (string) llama_ridb_record_value(
                $activity,
                ['ActivityID', 'activityID'],
                ''
            )
        );

        if ($externalId === '') {
            $externalId =
                $facilityId
                . ':activity:'
                . ($index + 1);
        }

        $insert->execute([
            $placeId,
            'activity',
            $name,
            null,
            'Recreation.gov / RIDB',
            $externalId,
            $sort,
        ]);

        $sort += 10;
        $count++;
    }

    return $count;
}

function llama_ridb_safe_replace_links(
    PDO $mainDb,
    int $placeId,
    string $facilityId,
    array $links
): int {
    $delete = $mainDb->prepare(
        'DELETE FROM place_facility_features
         WHERE place_id = ?
           AND source_provider = ?
           AND feature_key = ?'
    );

    $delete->execute([
        $placeId,
        'Recreation.gov / RIDB',
        'source_link',
    ]);

    if (!$links) {
        return 0;
    }

    $insert = $mainDb->prepare(
        'INSERT INTO place_facility_features
        (
            place_id,
            feature_key,
            feature_value,
            qualifier,
            source_provider,
            source_external_id,
            sort_order,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    $count = 0;
    $sort = 1000;

    foreach ($links as $index => $link) {
        $url = trim(
            (string) llama_ridb_record_value(
                $link,
                ['URL', 'Url', 'LinkURL', 'linkURL', 'url'],
                ''
            )
        );

        if (
            $url === ''
            || preg_match('#^https?://#i', $url) !== 1
        ) {
            continue;
        }

        $title = trim(
            (string) llama_ridb_record_value(
                $link,
                ['Title', 'LinkTitle', 'LinkType', 'linkType', 'title'],
                ''
            )
        );

        $externalId = trim(
            (string) llama_ridb_record_value(
                $link,
                ['LinkID', 'linkID', 'id'],
                ''
            )
        );

        if ($externalId === '') {
            $externalId =
                $facilityId
                . ':link:'
                . ($index + 1);
        }

        $insert->execute([
            $placeId,
            'source_link',
            $url,
            $title !== ''
                ? $title
                : null,
            'Recreation.gov / RIDB',
            $externalId,
            $sort,
        ]);

        $sort += 10;
        $count++;
    }

    return $count;
}

function llama_ridb_safe_add_place_media(
    PDO $mainDb,
    int $placeId,
    int $userId,
    string $placeName,
    array $media
): int {
    if (!$media) {
        return 0;
    }

    $existingStmt = $mainDb->prepare(
        'SELECT src
         FROM place_images
         WHERE place_id = ?'
    );

    $existingStmt->execute([$placeId]);

    $existing = array_fill_keys(
        array_map(
            'strval',
            $existingStmt->fetchAll(PDO::FETCH_COLUMN)
            ?: []
        ),
        true
    );

    $featuredStmt = $mainDb->prepare(
        'SELECT COUNT(*)
         FROM place_images
         WHERE place_id = ?
           AND is_featured = 1'
    );

    $featuredStmt->execute([$placeId]);

    $hasFeatured =
        (int) $featuredStmt->fetchColumn()
        > 0;

    $sortStmt = $mainDb->prepare(
        'SELECT COALESCE(MAX(sort_order), -1)
         FROM place_images
         WHERE place_id = ?'
    );

    $sortStmt->execute([$placeId]);

    $sort =
        (int) $sortStmt->fetchColumn()
        + 1;

    $insert = $mainDb->prepare(
        'INSERT INTO place_images
        (
            place_id,
            src,
            alt_text,
            is_featured,
            sort_order,
            uploaded_by
        )
        VALUES (?, ?, ?, ?, ?, ?)'
    );

    $count = 0;

    foreach ($media as $item) {
        if (!is_array($item)) {
            continue;
        }

        $url = llama_ridb_public_media_url($item);

        if (
            $url === null
            || isset($existing[$url])
            || !llama_ridb_public_media_is_image(
                $item,
                $url
            )
        ) {
            continue;
        }

        $title = llama_ridb_public_smart_name(
            llama_ridb_record_value(
                $item,
                ['Title', 'MediaTitle', 'Subtitle'],
                ''
            )
        );

        $isFeatured =
            !$hasFeatured
            && $count === 0;

        $insert->execute([
            $placeId,
            $url,
            $title !== ''
                ? $title
                : $placeName,
            $isFeatured ? 1 : 0,
            $sort,
            $userId > 0
                ? $userId
                : null,
        ]);

        $existing[$url] = true;

        if ($isFeatured) {
            $hasFeatured = true;
        }

        $sort++;
        $count++;
    }

    return $count;
}

function llama_ridb_safe_canonical_sync(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    string $facilityId
): array {
    $amenityStmt = $mainDb->prepare(
        'SELECT *
         FROM place_amenities
         WHERE place_id = ?
         LIMIT 1'
    );

    $amenityStmt->execute([$placeId]);

    $amenitySnapshot = $amenityStmt->fetch(
        PDO::FETCH_ASSOC
    );

    $result = llama_ridb_canonical_sync_place(
        $mainDb,
        $ridbDb,
        $placeId,
        $facilityId
    );

    if (
        !is_array($amenitySnapshot)
        || !$amenitySnapshot
    ) {
        return $result;
    }

    unset($amenitySnapshot['place_id']);

    $columns = [];
    $values = [];

    foreach ($amenitySnapshot as $column => $value) {
        if (
            !preg_match(
                '/^[a-z0-9_]+$/',
                (string) $column
            )
        ) {
            continue;
        }

        $columns[] =
            '`'
            . $column
            . '` = ?';

        $values[] = $value;
    }

    if ($columns) {
        $values[] = $placeId;

        $restore = $mainDb->prepare(
            'UPDATE place_amenities
             SET '
            . implode(', ', $columns)
            . '
             WHERE place_id = ?'
        );

        $restore->execute($values);
    }

    return $result;
}

function llama_ridb_safe_sync_facility(
    PDO $mainDb,
    PDO $ridbDb,
    int $userId,
    string $facilityId
): array {
    $facilityId = trim($facilityId);

    if ($facilityId === '') {
        throw new InvalidArgumentException(
            'A RIDB facility ID is required.'
        );
    }

    $placeId = llama_ridb_import_find_place(
        $mainDb,
        $facilityId
    );

    if ($placeId === null) {
        throw new RuntimeException(
            'This RIDB facility has not been imported into Llama Scout yet.'
        );
    }

    $facility = llama_ridb_safe_cached_facility(
        $ridbDb,
        $facilityId
    );

    if (!$facility) {
        throw new RuntimeException(
            'The local RIDB mirror does not contain this facility.'
        );
    }

    $addresses = llama_ridb_safe_related_rows(
        $ridbDb,
        'ridb_facility_addresses',
        $facilityId
    );

    $activities = llama_ridb_safe_related_rows(
        $ridbDb,
        'ridb_activities',
        $facilityId
    );

    $links = llama_ridb_safe_related_rows(
        $ridbDb,
        'ridb_links',
        $facilityId
    );

    $media = llama_ridb_safe_related_rows(
        $ridbDb,
        'ridb_media',
        $facilityId
    );

    $sites = llama_ridb_safe_cached_campsites(
        $ridbDb,
        $facilityId
    );

    $address = llama_ridb_import_address(
        $addresses
    );

    $coreFillCount = llama_ridb_safe_fill_place_core(
        $mainDb,
        $placeId,
        $facility,
        $address
    );

    llama_ridb_import_link_source(
        $mainDb,
        $placeId,
        $facilityId,
        $facility
    );

    llama_ridb_import_facility_facts(
        $mainDb,
        $placeId,
        $facilityId,
        $facility,
        $address
    );

    $activityCount = llama_ridb_safe_replace_activities(
        $mainDb,
        $placeId,
        $facilityId,
        $activities
    );

    $linkCount = llama_ridb_safe_replace_links(
        $mainDb,
        $placeId,
        $facilityId,
        $links
    );

    $mainDb
        ->prepare(
            'UPDATE place_campsites
             SET is_active = 0
             WHERE place_id = ?
               AND source_provider = ?'
        )
        ->execute([
            $placeId,
            'ridb',
        ]);

    $siteCount = 0;
    $attributeCount = 0;

    foreach ($sites as $siteKey => $site) {
        $siteId = str_starts_with(
            (string) $siteKey,
            'site:'
        )
            ? substr(
                (string) $siteKey,
                5
            )
            : (string) $siteKey;

        $siteId = trim($siteId);

        if ($siteId === '') {
            continue;
        }

        $attributes =
            llama_ridb_safe_cached_attributes(
                $ridbDb,
                $siteId
            );

        $payload =
            llama_ridb_import_site_payload(
                $site,
                $attributes
            );

        $campsiteId =
            llama_ridb_import_upsert_site(
                $mainDb,
                $placeId,
                $siteId,
                $payload
            );

        llama_ridb_import_upsert_site_facts(
            $mainDb,
            $campsiteId,
            $siteId,
            $payload['facts']
            ?? []
        );

        llama_ridb_import_replace_site_features(
            $mainDb,
            $campsiteId,
            $siteId,
            $payload['features']
            ?? []
        );

        $siteCount++;
        $attributeCount +=
            count($attributes);
    }

    $canonical = llama_ridb_safe_canonical_sync(
        $mainDb,
        $ridbDb,
        $placeId,
        $facilityId
    );

    $complete = llama_ridb_complete_campsite_import(
        $mainDb,
        $ridbDb,
        $userId,
        $placeId,
        $facilityId
    );

    $nameStmt = $mainDb->prepare(
        'SELECT
            name,
            slug
         FROM places
         WHERE id = ?
         LIMIT 1'
    );

    $nameStmt->execute([$placeId]);

    $placeRow =
        $nameStmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $placeMediaCount = llama_ridb_safe_add_place_media(
        $mainDb,
        $placeId,
        $userId,
        (string) (
            $placeRow['name']
            ?? 'Llama Scout Place'
        ),
        $media
    );

    llama_ridb_public_clean_site_names(
        $mainDb,
        $placeId
    );

    llama_ridb_log_sync_run(
        $ridbDb,
        'production_safe_sync',
        'success',
        1 + $siteCount + $attributeCount,
        1 + $siteCount + $attributeCount,
        'Safely synchronized facility '
        . $facilityId
        . ' to Place #'
        . $placeId
        . ' from the local RIDB mirror.'
    );

    return [
        'place_id' => $placeId,
        'slug' =>
            (string) (
                $placeRow['slug']
                ?? ''
            ),
        'created' => false,
        'sync_mode' => 'safe',
        'campsites' => $siteCount,
        'attributes' => $attributeCount,
        'canonical_answers' =>
            (int) (
                $canonical['answer_count']
                ?? 0
            ),
        'core_fields_imported' => $coreFillCount,
        'media_imported' => $placeMediaCount,
        'facility_links_imported' => $linkCount,
        'facility_activities_imported' => $activityCount,
        'sites_enriched' =>
            (int) (
                $complete['sites_enriched']
                ?? 0
            ),
        'site_metadata_imported' =>
            (int) (
                $complete['site_metadata_imported']
                ?? 0
            ),
        'site_media_imported' =>
            (int) (
                $complete['site_media_imported']
                ?? 0
            ),
    ];
}
