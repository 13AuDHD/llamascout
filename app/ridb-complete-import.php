<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';

/**
 * Import campsite-specific RIDB data that is embedded in the already-mirrored
 * campsite source JSON.
 *
 * This supplements the existing production importer. It does not overwrite
 * Scout-owned observations. All rows written here remain source-scoped.
 */

function llama_ridb_complete_embedded_list(
    array $record,
    array $keys
): array {
    foreach ($keys as $key) {
        $value =
            $record[$key]
            ?? null;

        if (!is_array($value)) {
            continue;
        }

        if (!$value) {
            return [];
        }

        if (
            isset($value[0])
            && is_array($value[0])
        ) {
            return array_values(
                array_filter(
                    $value,
                    'is_array'
                )
            );
        }

        return [$value];
    }

    return [];
}

function llama_ridb_complete_raw_campsites(
    PDO $ridbDb,
    string $facilityId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT
                c.ridb_campsite_id,
                r.source_json
             FROM ridb_campsites c
             INNER JOIN ridb_raw_records r
                ON r.feed = "campsites"
               AND r.source_record_id =
                    c.ridb_campsite_id
             WHERE c.ridb_facility_id = ?
             ORDER BY c.ridb_campsite_id ASC'
        );

    $stmt->execute([
        $facilityId,
    ]);

    $rows = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $decoded =
            json_decode(
                (string) (
                    $row['source_json']
                    ?? ''
                ),
                true
            );

        if (!is_array($decoded)) {
            continue;
        }

        $siteId =
            trim(
                (string) (
                    $row[
                        'ridb_campsite_id'
                    ]
                    ?? ''
                )
            );

        if ($siteId === '') {
            continue;
        }

        $rows[$siteId] =
            $decoded;
    }

    return $rows;
}

function llama_ridb_complete_place_campsites(
    PDO $mainDb,
    int $placeId
): array {
    $stmt =
        $mainDb->prepare(
            'SELECT
                id,
                source_external_id
             FROM place_campsites
             WHERE place_id = ?
               AND source_provider = ?
               AND is_active = 1'
        );

    $stmt->execute([
        $placeId,
        'ridb',
    ]);

    $lookup = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $externalId =
            trim(
                (string) (
                    $row[
                        'source_external_id'
                    ]
                    ?? ''
                )
            );

        if ($externalId === '') {
            continue;
        }

        $lookup[$externalId] =
            (int) (
                $row['id']
                ?? 0
            );
    }

    return $lookup;
}

function llama_ridb_complete_media_url(
    array $media
): ?string {
    foreach (
        [
            'URL',
            'MediaURL',
            'Url',
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
            && preg_match(
                '#^https?://#i',
                $url
            ) === 1
        ) {
            return $url;
        }
    }

    return null;
}

function llama_ridb_complete_media_is_image(
    array $media,
    string $url
): bool {
    $type =
        strtolower(
            trim(
                (string) (
                    $media['MediaType']
                    ?? $media['mediaType']
                    ?? ''
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

    return preg_match(
        '/\.(?:jpe?g|png|webp|gif)$/',
        $path
    ) === 1;
}

function llama_ridb_complete_site_metadata(
    PDO $mainDb,
    int $campsiteId,
    string|int $siteId,
    array $site
): int {
    $siteId = trim((string) $siteId);
    /*
     * The main RIDB importer already owns its source-scoped campsite features.
     * Only replace metadata keys introduced by this supplemental importer.
     */
    $delete =
        $mainDb->prepare(
            'DELETE FROM place_campsite_features
             WHERE campsite_id = ?
               AND source_provider = ?
               AND feature_key IN (
                    "ridb_loop",
                    "ridb_type_of_use",
                    "ridb_reservable",
                    "ridb_created_date",
                    "ridb_last_updated",
                    "permitted_equipment"
               )'
        );

    $delete->execute([
        $campsiteId,
        'Recreation.gov / RIDB',
    ]);

    $insert =
        $mainDb->prepare(
            'INSERT INTO place_campsite_features
            (
                campsite_id,
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

    $rows = [];

    $simple = [
        'ridb_loop' => [
            'Loop',
            'loop',
        ],
        'ridb_type_of_use' => [
            'TypeOfUse',
            'typeOfUse',
        ],
        'ridb_reservable' => [
            'CampsiteReservable',
            'campsiteReservable',
        ],
        'ridb_created_date' => [
            'CreatedDate',
            'createdDate',
        ],
        'ridb_last_updated' => [
            'LastUpdatedDate',
            'lastUpdatedDate',
        ],
    ];

    foreach (
        $simple
        as $featureKey => $keys
    ) {
        $value =
            llama_ridb_record_value(
                $site,
                $keys,
                null
            );

        if ($value === null) {
            continue;
        }

        if (is_bool($value)) {
            $value =
                $value
                    ? 'Yes'
                    : 'No';
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            continue;
        }

        $rows[] = [
            'feature_key' =>
                $featureKey,
            'feature_value' =>
                $value,
            'qualifier' =>
                null,
            'external_id' =>
                $siteId
                . ':'
                . $featureKey,
        ];
    }

    $equipment =
        llama_ridb_complete_embedded_list(
            $site,
            [
                'PERMITTEDEQUIPMENT',
                'PermittedEquipment',
                'permittedEquipment',
            ]
        );

    foreach ($equipment as $index => $item) {
        $name =
            trim(
                (string) (
                    $item['EquipmentName']
                    ?? $item['equipmentName']
                    ?? ''
                )
            );

        if ($name === '') {
            continue;
        }

        $maxLength =
            $item['MaxLength']
            ?? $item['maxLength']
            ?? null;

        $qualifier = null;

        if (
            $maxLength !== null
            && is_numeric($maxLength)
        ) {
            $qualifier =
                'Maximum length: '
                . rtrim(
                    rtrim(
                        number_format(
                            (float) $maxLength,
                            2,
                            '.',
                            ''
                        ),
                        '0'
                    ),
                    '.'
                )
                . ' ft';
        }

        $rows[] = [
            'feature_key' =>
                'permitted_equipment',
            'feature_value' =>
                $name,
            'qualifier' =>
                $qualifier,
            'external_id' =>
                $siteId
                . ':equipment:'
                . ($index + 1),
        ];
    }

    $sort = 5000;
    $count = 0;

    foreach ($rows as $row) {
        $insert->execute([
            $campsiteId,
            $row['feature_key'],
            $row['feature_value'],
            $row['qualifier'],
            'Recreation.gov / RIDB',
            $row['external_id'],
            $sort,
        ]);

        $sort += 10;
        $count++;
    }

    return $count;
}

function llama_ridb_complete_site_media(
    PDO $mainDb,
    int $placeId,
    int $campsiteId,
    string|int $siteId,
    array $site,
    int $userId
): int {
    $siteId = trim((string) $siteId);
    $media =
        llama_ridb_complete_embedded_list(
            $site,
            [
                'ENTITYMEDIA',
                'EntityMedia',
                'entityMedia',
            ]
        );

    /*
     * Delete only RIDB-owned campsite media for this site. Scout uploads
     * and other providers are untouched.
     */
    $delete =
        $mainDb->prepare(
            'DELETE FROM place_campsite_images
             WHERE place_id = ?
               AND campsite_id = ?
               AND source_provider = ?'
        );

    $delete->execute([
        $placeId,
        $campsiteId,
        'Recreation.gov / RIDB',
    ]);

    if (!$media) {
        return 0;
    }

    $records = [];

    foreach ($media as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $url =
            llama_ridb_complete_media_url(
                $item
            );

        if (
            $url === null
            || !llama_ridb_complete_media_is_image(
                $item,
                $url
            )
        ) {
            continue;
        }

        $externalId =
            trim(
                (string) (
                    $item['EntityMediaID']
                    ?? $item['MediaID']
                    ?? $item['entityMediaID']
                    ?? ''
                )
            );

        if ($externalId === '') {
            $externalId =
                $siteId
                . ':media:'
                . ($index + 1);
        }

        $title =
            trim(
                (string) (
                    $item['Title']
                    ?? $item['MediaTitle']
                    ?? ''
                )
            );

        $description =
            trim(
                (string) (
                    $item['Description']
                    ?? $item['description']
                    ?? ''
                )
            );

        $credit =
            trim(
                (string) (
                    $item['Credits']
                    ?? $item['Credit']
                    ?? ''
                )
            );

        $records[] = [
            'url' =>
                $url,
            'external_id' =>
                $externalId,
            'title' =>
                $title,
            'description' =>
                $description,
            'credit' =>
                $credit,
            'is_primary' =>
                !empty(
                    $item['IsPrimary']
                    ?? $item['isPrimary']
                    ?? false
                ),
            'is_preview' =>
                !empty(
                    $item['IsPreview']
                    ?? $item['isPreview']
                    ?? false
                ),
        ];
    }

    if (!$records) {
        return 0;
    }

    usort(
        $records,
        static function (
            array $a,
            array $b
        ): int {
            $aRank =
                !empty($a['is_primary'])
                    ? 0
                    : (
                        !empty($a['is_preview'])
                            ? 1
                            : 2
                    );

            $bRank =
                !empty($b['is_primary'])
                    ? 0
                    : (
                        !empty($b['is_preview'])
                            ? 1
                            : 2
                    );

            return $aRank <=> $bRank;
        }
    );

    $insert =
        $mainDb->prepare(
            'INSERT INTO place_campsite_images
            (
                place_id,
                campsite_id,
                src,
                alt_text,
                caption,
                is_featured,
                sort_order,
                uploaded_by,
                source_provider,
                source_external_id,
                source_url,
                source_credit,
                source_updated_at
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

    $sourceUpdated =
        trim(
            (string) llama_ridb_record_value(
                $site,
                [
                    'LastUpdatedDate',
                    'lastUpdatedDate',
                ],
                ''
            )
        );

    $count = 0;

    foreach ($records as $index => $record) {
        $alt =
            $record['title']
            !== ''
                ? $record['title']
                : (
                    'Campsite '
                    . $siteId
                );

        $caption =
            $record['description']
            !== ''
                ? $record['description']
                : null;

        $insert->execute([
            $placeId,
            $campsiteId,
            $record['url'],
            mb_substr(
                $alt,
                0,
                300
            ),
            $caption !== null
                ? mb_substr(
                    $caption,
                    0,
                    500
                )
                : null,
            $index === 0
                ? 1
                : 0,
            $index,
            $userId > 0
                ? $userId
                : null,
            'Recreation.gov / RIDB',
            $record['external_id'],
            $record['url'],
            $record['credit']
                !== ''
                    ? mb_substr(
                        $record['credit'],
                        0,
                        255
                    )
                    : null,
            $sourceUpdated !== ''
                ? $sourceUpdated
                : null,
        ]);

        $count++;
    }

    return $count;
}

function llama_ridb_complete_campsite_import(
    PDO $mainDb,
    PDO $ridbDb,
    int $userId,
    int $placeId,
    string $facilityId
): array {
    if ($placeId < 1) {
        throw new InvalidArgumentException(
            'A valid Llama Scout Place is required.'
        );
    }

    $rawSites =
        llama_ridb_complete_raw_campsites(
            $ridbDb,
            $facilityId
        );

    $productionSites =
        llama_ridb_complete_place_campsites(
            $mainDb,
            $placeId
        );

    $siteCount = 0;
    $featureCount = 0;
    $mediaCount = 0;

    $mainDb->beginTransaction();

    try {
        foreach (
            $productionSites
            as $siteId => $campsiteId
        ) {
            $site =
                $rawSites[$siteId]
                ?? null;

            if (!is_array($site)) {
                continue;
            }

            $featureCount +=
                llama_ridb_complete_site_metadata(
                    $mainDb,
                    $campsiteId,
                    $siteId,
                    $site
                );

            $mediaCount +=
                llama_ridb_complete_site_media(
                    $mainDb,
                    $placeId,
                    $campsiteId,
                    $siteId,
                    $site,
                    $userId
                );

            $siteCount++;
        }

        $mainDb->commit();

    } catch (Throwable $exception) {
        if ($mainDb->inTransaction()) {
            $mainDb->rollBack();
        }

        throw $exception;
    }

    return [
        'sites_enriched' =>
            $siteCount,
        'site_metadata_imported' =>
            $featureCount,
        'site_media_imported' =>
            $mediaCount,
    ];
}
