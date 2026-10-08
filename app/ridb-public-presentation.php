<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';

function llama_ridb_public_repair_text(
    mixed $value
): ?string {
    $text =
        trim(
            (string) $value
        );

    if ($text === '') {
        return null;
    }

    if (
        preg_match(
            '/(?:Â|Ã|â€|â€™|â€œ|â€|â€“|â€”|â€¦)/u',
            $text
        )
    ) {
        $candidate =
            @mb_convert_encoding(
                $text,
                'ISO-8859-1',
                'UTF-8'
            );

        if (
            is_string($candidate)
            && $candidate !== ''
            && mb_check_encoding(
                $candidate,
                'UTF-8'
            )
        ) {
            $text = $candidate;
        }
    }

    $text =
        str_replace(
            [
                "\u{00A0}",
                "\u{200B}",
            ],
            [
                ' ',
                '',
            ],
            $text
        );

    return
        trim(
            preg_replace(
                '/[ \t]+/u',
                ' ',
                $text
            )
            ?? $text
        );
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
                static function (
                    array $match
                ): string {
                    return
                        strtoupper(
                            $match[0]
                        );
                },
                $text
            )
            ?? $text;
    }

    return $text;
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

    $hasFeaturedStmt =
        $mainDb->prepare(
            'SELECT COUNT(*)
             FROM place_images
             WHERE place_id = ?
               AND is_featured = 1'
        );

    $hasFeaturedStmt->execute([
        $placeId,
    ]);

    $hasFeatured =
        (int) $hasFeaturedStmt
            ->fetchColumn()
        > 0;

    $orderStmt =
        $mainDb->prepare(
            'SELECT COALESCE(
                MAX(sort_order),
                -1
             )
             FROM place_images
             WHERE place_id = ?'
        );

    $orderStmt->execute([
        $placeId,
    ]);

    $sortOrder =
        (int) $orderStmt
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

        $existing[$url] =
            true;

        $sortOrder++;
        $added++;
    }

    return $added;
}

function llama_ridb_public_clean_imported_records(
    PDO $db,
    int $placeId
): void {
    $placeStmt =
        $db->prepare(
            'SELECT
                name,
                description,
                city,
                region,
                land_manager
             FROM places
             WHERE id = ?
             LIMIT 1'
        );

    $placeStmt->execute([
        $placeId,
    ]);

    $place =
        $placeStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($place) {
        $update =
            $db->prepare(
                'UPDATE places
                 SET
                    name = ?,
                    description = ?,
                    city = ?,
                    region = ?,
                    land_manager = ?
                 WHERE id = ?
                 LIMIT 1'
            );

        $update->execute([
            llama_ridb_public_smart_name(
                $place['name']
                ?? null
            ),
            llama_ridb_public_repair_text(
                $place['description']
                ?? null
            ),
            llama_ridb_public_smart_name(
                $place['city']
                ?? null
            ),
            llama_ridb_public_smart_name(
                $place['region']
                ?? null
            ),
            llama_ridb_public_smart_name(
                $place['land_manager']
                ?? null
            ),
            $placeId,
        ]);
    }

    $siteStmt =
        $db->prepare(
            'SELECT
                id,
                site_name,
                raw_site_type
             FROM place_campsites
             WHERE place_id = ?
               AND source_provider = ?'
        );

    $siteStmt->execute([
        $placeId,
        'ridb',
    ]);

    $siteUpdate =
        $db->prepare(
            'UPDATE place_campsites
             SET
                site_name = ?,
                raw_site_type = ?
             WHERE id = ?
             LIMIT 1'
        );

    foreach (
        $siteStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $site
    ) {
        $siteUpdate->execute([
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

    $featureStmt =
        $db->prepare(
            'SELECT
                id,
                feature_value,
                qualifier
             FROM place_campsite_features
             WHERE campsite_id IN (
                SELECT id
                FROM place_campsites
                WHERE place_id = ?
                  AND source_provider = ?
             )'
        );

    $featureStmt->execute([
        $placeId,
        'ridb',
    ]);

    $featureUpdate =
        $db->prepare(
            'UPDATE place_campsite_features
             SET
                feature_value = ?,
                qualifier = ?
             WHERE id = ?
             LIMIT 1'
        );

    foreach (
        $featureStmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $feature
    ) {
        $featureUpdate->execute([
            llama_ridb_public_repair_text(
                $feature['feature_value']
                ?? null
            ),
            llama_ridb_public_smart_name(
                $feature['qualifier']
                ?? null
            ),
            (int) $feature['id'],
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

    llama_ridb_public_clean_imported_records(
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
