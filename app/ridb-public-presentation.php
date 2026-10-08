<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';

function llama_ridb_public_repair_text(
    mixed $value
): ?string {
    $text = trim((string) $value);

    if ($text === '') {
        return null;
    }

    $replacements = [
        'Â·' => ' - ',
        'Â×' => ' x ',
        'â€™' => "'",
        'â€œ' => '"',
        'â€' => '"',
        'â€“' => '-',
        'â€”' => '-',
        'â€¦' => '...',
        "\u{00A0}" => ' ',
        "\u{200B}" => '',
    ];

    $text = strtr(
        $text,
        $replacements
    );

    $text =
        preg_replace(
            '/[ \t]+/u',
            ' ',
            $text
        )
        ?? $text;

    return trim($text);
}

function llama_ridb_public_smart_name(
    mixed $value
): ?string {
    $text =
        llama_ridb_public_repair_text(
            $value
        );

    if ($text === null) {
        return null;
    }

    $lettersOnly =
        preg_replace(
            '/[^\p{L}]+/u',
            '',
            $text
        )
        ?? '';

    if (
        $lettersOnly !== ''
        && mb_strtoupper(
            $lettersOnly,
            'UTF-8'
        ) === $lettersOnly
    ) {
        $text =
            mb_convert_case(
                mb_strtolower(
                    $text,
                    'UTF-8'
                ),
                MB_CASE_TITLE,
                'UTF-8'
            );

        $text =
            preg_replace_callback(
                '/\b(?:Rv|Usa|Us|Blm|Nps|Usfs|Usda|Fws|Atv|Ohv|Ada)\b/u',
                static fn (
                    array $match
                ): string =>
                    strtoupper(
                        $match[0]
                    ),
                $text
            )
            ?? $text;
    }

    return $text;
}

function llama_ridb_public_decode_json(
    mixed $value
): array {
    if (
        !is_string($value)
        || trim($value) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $value,
            true
        );

    return is_array($decoded)
        ? $decoded
        : [];
}

function llama_ridb_public_cached_facility(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT source_json
             FROM ridb_facilities
             WHERE ridb_facility_id = ?
             LIMIT 1'
        );

    $stmt->execute([
        $facilityId,
    ]);

    return
        llama_ridb_public_decode_json(
            $stmt->fetchColumn()
        );
}

function llama_ridb_public_cached_address(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT source_json
             FROM ridb_facility_addresses
             WHERE ridb_facility_id = ?
             ORDER BY id ASC
             LIMIT 1'
        );

    $stmt->execute([
        $facilityId,
    ]);

    return
        llama_ridb_public_decode_json(
            $stmt->fetchColumn()
        );
}

function llama_ridb_public_number(
    mixed $value
): ?float {
    if (
        is_int($value)
        || is_float($value)
    ) {
        return (float) $value;
    }

    $text =
        str_replace(
            ',',
            '',
            trim(
                (string) $value
            )
        );

    if (
        $text === ''
        || preg_match(
            '/-?\d+(?:\.\d+)?/',
            $text,
            $match
        ) !== 1
    ) {
        return null;
    }

    return (float) $match[0];
}

function llama_ridb_public_state_name(
    mixed $value
): ?string {
    $raw =
        strtoupper(
            trim(
                (string) $value
            )
        );

    if ($raw === '') {
        return null;
    }

    $states = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
        'PR' => 'Puerto Rico',
    ];

    return
        $states[$raw]
        ?? llama_ridb_public_smart_name(
            $raw
        );
}

function llama_ridb_public_sync_core_place_fields(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    string $facilityId
): int {
    $facility =
        llama_ridb_public_cached_facility(
            $ridbDb,
            $facilityId
        );

    if (!$facility) {
        return 0;
    }

    $address =
        llama_ridb_public_cached_address(
            $ridbDb,
            $facilityId
        );

    $latitude =
        llama_ridb_public_number(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityLatitude',
                    'facilityLatitude',
                    'Latitude',
                ]
            )
        );

    $longitude =
        llama_ridb_public_number(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityLongitude',
                    'facilityLongitude',
                    'Longitude',
                ]
            )
        );

    $elevation =
        llama_ridb_public_number(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityElevationFeet',
                    'facilityElevationFeet',
                    'ElevationFeet',
                ]
            )
        );

    $road =
        llama_ridb_public_smart_name(
            llama_ridb_record_value(
                $address,
                [
                    'FacilityStreetAddress1',
                    'StreetAddress1',
                    'AddressLine1',
                ],
                llama_ridb_record_value(
                    $facility,
                    [
                        'FacilityStreetAddress1',
                        'StreetAddress1',
                        'RoadName',
                    ],
                    ''
                )
            )
        );

    $city =
        llama_ridb_public_smart_name(
            llama_ridb_record_value(
                $address,
                [
                    'City',
                    'city',
                ],
                ''
            )
        );

    $state =
        llama_ridb_public_state_name(
            llama_ridb_record_value(
                $address,
                [
                    'AddressStateCode',
                    'State',
                    'state',
                ],
                ''
            )
        );

    $name =
        llama_ridb_public_smart_name(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityName',
                    'facilityName',
                ],
                ''
            )
        );

    $description =
        llama_ridb_public_repair_text(
            llama_ridb_record_value(
                $facility,
                [
                    'FacilityDescription',
                    'facilityDescription',
                ],
                ''
            )
        );

    $stmt =
        $mainDb->prepare(
            'UPDATE places
             SET
                name =
                    COALESCE(
                        NULLIF(?, ""),
                        name
                    ),
                description =
                    COALESCE(
                        NULLIF(?, ""),
                        description
                    ),
                latitude =
                    COALESCE(
                        ?,
                        latitude
                    ),
                longitude =
                    COALESCE(
                        ?,
                        longitude
                    ),
                public_latitude =
                    CASE
                        WHEN ? IS NOT NULL
                        THEN ROUND(?, 1)
                        ELSE public_latitude
                    END,
                public_longitude =
                    CASE
                        WHEN ? IS NOT NULL
                        THEN ROUND(?, 1)
                        ELSE public_longitude
                    END,
                road =
                    COALESCE(
                        NULLIF(?, ""),
                        road
                    ),
                elevation_feet =
                    COALESCE(
                        ?,
                        elevation_feet
                    ),
                city =
                    COALESCE(
                        NULLIF(?, ""),
                        city
                    ),
                state =
                    COALESCE(
                        NULLIF(?, ""),
                        state
                    )
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
        $elevation !== null
            ? (int) round(
                $elevation
            )
            : null,
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
            $elevation,
            $city,
            $state,
        ]
        as $value
    ) {
        if (
            $value !== null
            && $value !== ''
        ) {
            $count++;
        }
    }

    return $count;
}

function llama_ridb_public_media_url(
    array $media
): ?string {
    foreach (
        [
            'URL',
            'MediaURL',
            'OriginalURL',
            'ImageURL',
            'url',
        ]
        as $key
    ) {
        $url =
            trim(
                (string) (
                    $media[$key]
                    ?? ''
                )
            );

        if (
            $url !== ''
            && filter_var(
                $url,
                FILTER_VALIDATE_URL
            )
        ) {
            return $url;
        }
    }

    return null;
}

function llama_ridb_public_media_is_image(
    array $media,
    string $url
): bool {
    $type =
        strtolower(
            trim(
                (string) llama_ridb_record_value(
                    $media,
                    [
                        'MediaType',
                        'Type',
                        'mediaType',
                    ],
                    ''
                )
            )
        );

    if (
        str_contains(
            $type,
            'image'
        )
        || str_contains(
            $type,
            'photo'
        )
    ) {
        return true;
    }

    $path =
        strtolower(
            (string) parse_url(
                $url,
                PHP_URL_PATH
            )
        );

    return
        preg_match(
            '/\.(?:jpe?g|png|webp|gif)$/',
            $path
        ) === 1;
}

function llama_ridb_public_import_media(
    PDO $mainDb,
    PDO $ridbDb,
    int $placeId,
    int $userId,
    string $facilityId,
    string $placeName
): int {
    $response =
        llama_ridb_facility_media(
            $facilityId
        );

    $media =
        $response['records']
        ?? [];

    llama_ridb_replace_related_records(
        $ridbDb,
        'ridb_media',
        $facilityId,
        $media,
        'MediaID'
    );

    if (!$media) {
        return 0;
    }

    $existingStmt =
        $mainDb->prepare(
            'SELECT src
             FROM place_images
             WHERE place_id = ?'
        );

    $existingStmt->execute([
        $placeId,
    ]);

    $existing =
        array_fill_keys(
            array_map(
                'strval',
                $existingStmt->fetchAll(
                    PDO::FETCH_COLUMN
                )
                ?: []
            ),
            true
        );

    $featuredStmt =
        $mainDb->prepare(
            'SELECT COUNT(*)
             FROM place_images
             WHERE place_id = ?
               AND is_featured = 1'
        );

    $featuredStmt->execute([
        $placeId,
    ]);

    $hasFeatured =
        (int) $featuredStmt
            ->fetchColumn()
        > 0;

    $sortStmt =
        $mainDb->prepare(
            'SELECT COALESCE(
                MAX(sort_order),
                -1
             )
             FROM place_images
             WHERE place_id = ?'
        );

    $sortStmt->execute([
        $placeId,
    ]);

    $sortOrder =
        (int) $sortStmt
            ->fetchColumn()
        + 1;

    $insert =
        $mainDb->prepare(
            'INSERT INTO place_images
            (
                place_id,
                src,
                alt_text,
                is_featured,
                sort_order,
                uploaded_by
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )'
        );

    $added = 0;

    foreach ($media as $item) {
        if (!is_array($item)) {
            continue;
        }

        $url =
            llama_ridb_public_media_url(
                $item
            );

        if (
            $url === null
            || isset(
                $existing[$url]
            )
            || !llama_ridb_public_media_is_image(
                $item,
                $url
            )
        ) {
            continue;
        }

        $title =
            llama_ridb_public_smart_name(
                llama_ridb_record_value(
                    $item,
                    [
                        'Title',
                        'MediaTitle',
                        'Subtitle',
                    ],
                    ''
                )
            );

        $alt =
            $title
            ?: $placeName;

        $isFeatured =
            !$hasFeatured
            && $added === 0
                ? 1
                : 0;

        $insert->execute([
            $placeId,
            $url,
            $alt,
            $isFeatured,
            $sortOrder,
            $userId > 0
                ? $userId
                : null,
        ]);

        $existing[$url] = true;

        if ($isFeatured === 1) {
            $hasFeatured = true;
        }

        $sortOrder++;
        $added++;
    }

    return $added;
}

function llama_ridb_public_clean_site_names(
    PDO $mainDb,
    int $placeId
): void {
    $stmt =
        $mainDb->prepare(
            'SELECT
                id,
                site_name,
                raw_site_type
             FROM place_campsites
             WHERE place_id = ?
               AND source_provider = ?'
        );

    $stmt->execute([
        $placeId,
        'ridb',
    ]);

    $update =
        $mainDb->prepare(
            'UPDATE place_campsites
             SET
                site_name = ?,
                raw_site_type = ?
             WHERE id = ?
             LIMIT 1'
        );

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $site
    ) {
        $update->execute([
            llama_ridb_public_smart_name(
                $site['site_name']
                ?? null
            ),
            llama_ridb_public_smart_name(
                $site['raw_site_type']
                ?? null
            ),
            (int) $site['id'],
        ]);
    }
}

function llama_ridb_public_finalize_import(
    PDO $mainDb,
    PDO $ridbDb,
    int $userId,
    string $facilityId,
    array $result
): array {
    $placeId =
        (int) (
            $result['place_id']
            ?? 0
        );

    if ($placeId < 1) {
        return $result;
    }

    $mainDb
        ->prepare(
            'UPDATE places
             SET source_type = ?
             WHERE id = ?
             LIMIT 1'
        )
        ->execute([
            'external',
            $placeId,
        ]);

    $result['core_fields_imported'] =
        llama_ridb_public_sync_core_place_fields(
            $mainDb,
            $ridbDb,
            $placeId,
            $facilityId
        );

    llama_ridb_public_clean_site_names(
        $mainDb,
        $placeId
    );

    $nameStmt =
        $mainDb->prepare(
            'SELECT name
             FROM places
             WHERE id = ?
             LIMIT 1'
        );

    $nameStmt->execute([
        $placeId,
    ]);

    $placeName =
        (string) (
            $nameStmt->fetchColumn()
            ?: 'Llama Scout Place'
        );

    $result['media_imported'] =
        llama_ridb_public_import_media(
            $mainDb,
            $ridbDb,
            $placeId,
            $userId,
            $facilityId,
            $placeName
        );

    return $result;
}
