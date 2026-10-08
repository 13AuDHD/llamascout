<?php

declare(strict_types=1);

function llama_ridb_config(): array
{
    $config =
        (array) (
            llama_config()['ridb']
            ?? []
        );

    $apiKey =
        trim(
            (string) (
                $config['api_key']
                ?? ''
            )
        );

    $baseUrl =
        rtrim(
            trim(
                (string) (
                    $config['base_url']
                    ?? 'https://ridb.recreation.gov/api/v1'
                )
            ),
            '/'
        );

    if ($apiKey === '') {
        throw new RuntimeException(
            'RIDB API key is missing from private configuration.'
        );
    }

    if ($baseUrl === '') {
        throw new RuntimeException(
            'RIDB API base URL is missing from private configuration.'
        );
    }

    return [
        'api_key' => $apiKey,
        'base_url' => $baseUrl,
    ];
}

function llama_ridb_request(
    string $path,
    array $query = []
): array {
    $config =
        llama_ridb_config();

    $path =
        '/' . ltrim(
            $path,
            '/'
        );

    $queryString =
        http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986
        );

    $url =
        $config['base_url']
        . $path
        . (
            $queryString !== ''
                ? '?' . $queryString
                : ''
        );

    $curl =
        curl_init($url);

    if ($curl === false) {
        throw new RuntimeException(
            'Could not initialize the RIDB request.'
        );
    }

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'apikey: ' . $config['api_key'],
                'User-Agent: LlamaScout/1.0 (+https://llamascout.com)',
            ],
        ]
    );

    $body =
        curl_exec($curl);

    $curlError =
        curl_error($curl);

    $status =
        (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

    curl_close($curl);

    if ($body === false) {
        throw new RuntimeException(
            'RIDB request failed: '
            . (
                $curlError !== ''
                    ? $curlError
                    : 'Unknown cURL error.'
            )
        );
    }

    $decoded =
        json_decode(
            (string) $body,
            true
        );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'RIDB returned an invalid JSON response.'
        );
    }

    if (
        $status < 200
        || $status >= 300
    ) {
        $message =
            trim(
                (string) (
                    $decoded['message']
                    ?? $decoded['Message']
                    ?? $decoded['error']
                    ?? ''
                )
            );

        throw new RuntimeException(
            'RIDB returned HTTP '
            . $status
            . (
                $message !== ''
                    ? ': ' . $message
                    : '.'
            )
        );
    }

    return [
        'status' => $status,
        'url' => $url,
        'data' => $decoded,
    ];
}

function llama_ridb_response_records(
    array $response
): array {
    $data =
        (array) (
            $response['data']
            ?? []
        );

    foreach (
        [
            'RECDATA',
            'RecData',
            'recdata',
            'data',
            'DATA',
        ]
        as $key
    ) {
        if (
            isset($data[$key])
            && is_array(
                $data[$key]
            )
        ) {
            return
                array_values(
                    $data[$key]
                );
        }
    }

    /*
     * Some specific-record endpoints return the record directly.
     * Treat an associative payload as one record while avoiding
     * metadata-only responses.
     */
    if (
        $data
        && array_keys($data)
            !== range(
                0,
                count($data) - 1
            )
        && !isset(
            $data['METADATA'],
            $data['Metadata'],
            $data['metadata']
        )
    ) {
        return [$data];
    }

    return [];
}

function llama_ridb_response_record(
    array $response
): array {
    $records =
        llama_ridb_response_records(
            $response
        );

    $record =
        $records[0]
        ?? [];

    return
        is_array($record)
            ? $record
            : [];
}

function llama_ridb_response_metadata(
    array $response
): array {
    $data =
        (array) (
            $response['data']
            ?? []
        );

    foreach (
        [
            'METADATA',
            'Metadata',
            'metadata',
        ]
        as $key
    ) {
        if (
            isset($data[$key])
            && is_array(
                $data[$key]
            )
        ) {
            return
                $data[$key];
        }
    }

    return [];
}

function llama_ridb_record_value(
    array $record,
    array $keys,
    mixed $default = null
): mixed {
    foreach ($keys as $key) {
        if (
            array_key_exists(
                $key,
                $record
            )
        ) {
            $value =
                $record[$key];

            if (
                $value !== null
                && $value !== ''
            ) {
                return $value;
            }
        }
    }

    return $default;
}

function llama_ridb_fetch_all(
    string $path,
    array $query = [],
    int $maxRecords = 500
): array {
    $maxRecords =
        max(
            1,
            min(
                5000,
                $maxRecords
            )
        );

    $limit = 50;
    $offset = 0;
    $records = [];
    $lastMetadata = [];

    do {
        $response =
            llama_ridb_request(
                $path,
                array_merge(
                    $query,
                    [
                        'limit' => $limit,
                        'offset' => $offset,
                    ]
                )
            );

        $pageRecords =
            llama_ridb_response_records(
                $response
            );

        $lastMetadata =
            llama_ridb_response_metadata(
                $response
            );

        foreach ($pageRecords as $record) {
            if (
                is_array($record)
                && count($records)
                    < $maxRecords
            ) {
                $records[] =
                    $record;
            }
        }

        $count =
            count($pageRecords);

        $offset +=
            $count;

        if (
            $count < $limit
            || count($records)
                >= $maxRecords
        ) {
            break;
        }
    } while ($offset < $maxRecords);

    return [
        'records' =>
            $records,

        'metadata' =>
            $lastMetadata,

        'truncated' =>
            count($records)
                >= $maxRecords,

        'count' =>
            count($records),
    ];
}

function llama_ridb_facility_search(
    string $query,
    int $limit = 25,
    int $offset = 0
): array {
    return
        llama_ridb_request(
            '/facilities',
            [
                'query' =>
                    trim($query),

                'limit' =>
                    max(
                        1,
                        min(
                            50,
                            $limit
                        )
                    ),

                'offset' =>
                    max(
                        0,
                        $offset
                    ),
            ]
        );
}

function llama_ridb_facility(
    int|string $facilityId
): array {
    $facilityId =
        trim(
            (string) $facilityId
        );

    if ($facilityId === '') {
        throw new InvalidArgumentException(
            'A RIDB facility ID is required.'
        );
    }

    return
        llama_ridb_request(
            '/facilities/'
            . rawurlencode(
                $facilityId
            )
        );
}

function llama_ridb_facility_campsites(
    int|string $facilityId,
    int $maxRecords = 500
): array {
    $facilityId =
        trim(
            (string) $facilityId
        );

    if ($facilityId === '') {
        throw new InvalidArgumentException(
            'A RIDB facility ID is required.'
        );
    }

    return
        llama_ridb_fetch_all(
            '/facilities/'
            . rawurlencode(
                $facilityId
            )
            . '/campsites',
            [],
            $maxRecords
        );
}

function llama_ridb_facility_addresses(
    int|string $facilityId
): array {
    return
        llama_ridb_fetch_all(
            '/facilities/'
            . rawurlencode(
                trim(
                    (string) $facilityId
                )
            )
            . '/facilityaddresses',
            [],
            100
        );
}

function llama_ridb_facility_media(
    int|string $facilityId
): array {
    return
        llama_ridb_fetch_all(
            '/facilities/'
            . rawurlencode(
                trim(
                    (string) $facilityId
                )
            )
            . '/media',
            [],
            250
        );
}

function llama_ridb_facility_links(
    int|string $facilityId
): array {
    return
        llama_ridb_fetch_all(
            '/facilities/'
            . rawurlencode(
                trim(
                    (string) $facilityId
                )
            )
            . '/links',
            [],
            250
        );
}

function llama_ridb_facility_activities(
    int|string $facilityId
): array {
    return
        llama_ridb_fetch_all(
            '/facilities/'
            . rawurlencode(
                trim(
                    (string) $facilityId
                )
            )
            . '/activities',
            [],
            250
        );
}

function llama_ridb_campsite(
    int|string $campsiteId
): array {
    $campsiteId =
        trim(
            (string) $campsiteId
        );

    if ($campsiteId === '') {
        throw new InvalidArgumentException(
            'A RIDB campsite ID is required.'
        );
    }

    return
        llama_ridb_request(
            '/campsites/'
            . rawurlencode(
                $campsiteId
            )
        );
}

function llama_ridb_campsite_attributes(
    int|string $campsiteId
): array {
    $campsiteId =
        trim(
            (string) $campsiteId
        );

    if ($campsiteId === '') {
        throw new InvalidArgumentException(
            'A RIDB campsite ID is required.'
        );
    }

    return
        llama_ridb_fetch_all(
            '/campsites/'
            . rawurlencode(
                $campsiteId
            )
            . '/attributes',
            [],
            250
        );
}

function llama_ridb_log_sync_run(
    PDO $ridbDb,
    string $runType,
    string $status,
    int $recordsSeen = 0,
    int $recordsStored = 0,
    ?string $message = null
): int {
    $stmt =
        $ridbDb->prepare(
            'INSERT INTO ridb_sync_runs
            (
                run_type,
                status,
                records_seen,
                records_stored,
                message,
                started_at,
                completed_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );

    $stmt->execute([
        $runType,
        $status,
        $recordsSeen,
        $recordsStored,
        $message,
    ]);

    return
        (int) $ridbDb
            ->lastInsertId();
}

function llama_ridb_store_facilities(
    PDO $ridbDb,
    array $records
): int {
    if (!$records) {
        return 0;
    }

    $stmt =
        $ridbDb->prepare(
            'INSERT INTO ridb_facilities
            (
                ridb_facility_id,
                facility_name,
                facility_type_description,
                description,
                latitude,
                longitude,
                reservable,
                enabled,
                parent_rec_area_id,
                last_updated_date,
                source_json,
                first_seen_at,
                last_seen_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                facility_name =
                    VALUES(facility_name),
                facility_type_description =
                    VALUES(facility_type_description),
                description =
                    VALUES(description),
                latitude =
                    VALUES(latitude),
                longitude =
                    VALUES(longitude),
                reservable =
                    VALUES(reservable),
                enabled =
                    VALUES(enabled),
                parent_rec_area_id =
                    VALUES(parent_rec_area_id),
                last_updated_date =
                    VALUES(last_updated_date),
                source_json =
                    VALUES(source_json),
                last_seen_at =
                    UTC_TIMESTAMP()'
        );

    $stored = 0;

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $facilityId =
            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'FacilityID',
                        'facilityID',
                        'facility_id',
                    ],
                    ''
                )
            );

        if ($facilityId === '') {
            continue;
        }

        $stmt->execute([
            $facilityId,

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'FacilityName',
                        'facilityName',
                    ],
                    ''
                )
            ),

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'FacilityTypeDescription',
                        'facilityTypeDescription',
                    ],
                    ''
                )
            ),

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'FacilityDescription',
                        'facilityDescription',
                    ],
                    ''
                )
            ),

            is_numeric(
                llama_ridb_record_value(
                    $record,
                    [
                        'FacilityLatitude',
                        'facilityLatitude',
                    ]
                )
            )
                ? (float) llama_ridb_record_value(
                    $record,
                    [
                        'FacilityLatitude',
                        'facilityLatitude',
                    ]
                )
                : null,

            is_numeric(
                llama_ridb_record_value(
                    $record,
                    [
                        'FacilityLongitude',
                        'facilityLongitude',
                    ]
                )
            )
                ? (float) llama_ridb_record_value(
                    $record,
                    [
                        'FacilityLongitude',
                        'facilityLongitude',
                    ]
                )
                : null,

            array_key_exists(
                'Reservable',
                $record
            )
                ? (
                    !empty(
                        $record['Reservable']
                    )
                        ? 1
                        : 0
                )
                : null,

            array_key_exists(
                'Enabled',
                $record
            )
                ? (
                    !empty(
                        $record['Enabled']
                    )
                        ? 1
                        : 0
                )
                : null,

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'ParentRecAreaID',
                        'parentRecAreaID',
                    ],
                    ''
                )
            ) !== ''
                ? trim(
                    (string) llama_ridb_record_value(
                        $record,
                        [
                            'ParentRecAreaID',
                            'parentRecAreaID',
                        ]
                    )
                )
                : null,

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'LastUpdatedDate',
                        'lastUpdatedDate',
                    ],
                    ''
                )
            ) !== ''
                ? trim(
                    (string) llama_ridb_record_value(
                        $record,
                        [
                            'LastUpdatedDate',
                            'lastUpdatedDate',
                        ]
                    )
                )
                : null,

            json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $stored++;
    }

    return $stored;
}

function llama_ridb_store_campsites(
    PDO $ridbDb,
    string $facilityId,
    array $records
): int {
    if (!$records) {
        return 0;
    }

    $stmt =
        $ridbDb->prepare(
            'INSERT INTO ridb_campsites
            (
                ridb_campsite_id,
                ridb_facility_id,
                campsite_name,
                campsite_type,
                campsite_accessible,
                latitude,
                longitude,
                source_json,
                first_seen_at,
                last_seen_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                ridb_facility_id =
                    VALUES(ridb_facility_id),
                campsite_name =
                    VALUES(campsite_name),
                campsite_type =
                    VALUES(campsite_type),
                campsite_accessible =
                    VALUES(campsite_accessible),
                latitude =
                    VALUES(latitude),
                longitude =
                    VALUES(longitude),
                source_json =
                    VALUES(source_json),
                last_seen_at =
                    UTC_TIMESTAMP()'
        );

    $stored = 0;

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $campsiteId =
            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteID',
                        'campsiteID',
                        'campsite_id',
                    ],
                    ''
                )
            );

        if ($campsiteId === '') {
            continue;
        }

        $accessible =
            llama_ridb_record_value(
                $record,
                [
                    'CampsiteAccessible',
                    'campsiteAccessible',
                ]
            );

        $stmt->execute([
            $campsiteId,
            $facilityId,

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteName',
                        'campsiteName',
                    ],
                    ''
                )
            ),

            trim(
                (string) llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteType',
                        'CampsiteTypeDescription',
                        'campsiteType',
                    ],
                    ''
                )
            ),

            $accessible === null
                ? null
                : (
                    !empty($accessible)
                        ? 1
                        : 0
                ),

            is_numeric(
                llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteLatitude',
                        'Latitude',
                        'latitude',
                    ]
                )
            )
                ? (float) llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteLatitude',
                        'Latitude',
                        'latitude',
                    ]
                )
                : null,

            is_numeric(
                llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteLongitude',
                        'Longitude',
                        'longitude',
                    ]
                )
            )
                ? (float) llama_ridb_record_value(
                    $record,
                    [
                        'CampsiteLongitude',
                        'Longitude',
                        'longitude',
                    ]
                )
                : null,

            json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $stored++;
    }

    return $stored;
}

function llama_ridb_replace_related_records(
    PDO $ridbDb,
    string $table,
    string $facilityId,
    array $records,
    string $sourceIdKey
): int {
    $allowed = [
        'ridb_facility_addresses',
        'ridb_media',
        'ridb_links',
        'ridb_activities',
    ];

    if (
        !in_array(
            $table,
            $allowed,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Unsupported RIDB related-record table.'
        );
    }

    $ridbDb->beginTransaction();

    try {
        $delete =
            $ridbDb->prepare(
                'DELETE FROM '
                . $table
                . ' WHERE ridb_facility_id = ?'
            );

        $delete->execute([
            $facilityId,
        ]);

        $insert =
            $ridbDb->prepare(
                'INSERT INTO '
                . $table
                . '
                (
                    ridb_facility_id,
                    source_record_id,
                    source_json,
                    synced_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )'
            );

        $stored = 0;

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $sourceId =
                trim(
                    (string) (
                        $record[$sourceIdKey]
                        ?? ''
                    )
                );

            if ($sourceId === '') {
                $sourceId =
                    'row-'
                    . ($index + 1);
            }

            $insert->execute([
                $facilityId,
                $sourceId,

                json_encode(
                    $record,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
            ]);

            $stored++;
        }

        $ridbDb->commit();

        return $stored;
    } catch (Throwable $exception) {
        if ($ridbDb->inTransaction()) {
            $ridbDb->rollBack();
        }

        throw $exception;
    }
}

function llama_ridb_replace_campsite_attributes(
    PDO $ridbDb,
    string $campsiteId,
    array $records
): int {
    $ridbDb->beginTransaction();

    try {
        $delete =
            $ridbDb->prepare(
                'DELETE FROM ridb_campsite_attributes
                 WHERE ridb_campsite_id = ?'
            );

        $delete->execute([
            $campsiteId,
        ]);

        $insert =
            $ridbDb->prepare(
                'INSERT INTO ridb_campsite_attributes
                (
                    ridb_campsite_id,
                    source_record_id,
                    attribute_name,
                    attribute_value,
                    source_json,
                    synced_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )'
            );

        $stored = 0;

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $sourceId =
                trim(
                    (string) llama_ridb_record_value(
                        $record,
                        [
                            'AttributeID',
                            'EntityAttributeID',
                            'attributeID',
                        ],
                        ''
                    )
                );

            if ($sourceId === '') {
                $sourceId =
                    'row-'
                    . ($index + 1);
            }

            $insert->execute([
                $campsiteId,
                $sourceId,

                trim(
                    (string) llama_ridb_record_value(
                        $record,
                        [
                            'AttributeName',
                            'AttributeKey',
                            'attributeName',
                        ],
                        ''
                    )
                ),

                trim(
                    (string) llama_ridb_record_value(
                        $record,
                        [
                            'AttributeValue',
                            'AttributeText',
                            'attributeValue',
                        ],
                        ''
                    )
                ),

                json_encode(
                    $record,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR
                ),
            ]);

            $stored++;
        }

        $ridbDb->commit();

        return $stored;
    } catch (Throwable $exception) {
        if ($ridbDb->inTransaction()) {
            $ridbDb->rollBack();
        }

        throw $exception;
    }
}

function llama_ridb_place_mapping_preview(
    array $facility,
    array $addresses,
    array $media,
    array $links,
    array $activities,
    array $campsites
): array {
    $latitude =
        llama_ridb_record_value(
            $facility,
            [
                'FacilityLatitude',
                'facilityLatitude',
            ]
        );

    $longitude =
        llama_ridb_record_value(
            $facility,
            [
                'FacilityLongitude',
                'facilityLongitude',
            ]
        );

    $rows = [
        [
            'field' => 'Place name',
            'status' => 'import',
            'value' =>
                (string) llama_ridb_record_value(
                    $facility,
                    [
                        'FacilityName',
                        'facilityName',
                    ],
                    ''
                ),
        ],
        [
            'field' => 'Description',
            'status' => 'import',
            'value' =>
                trim(
                    strip_tags(
                        (string) llama_ridb_record_value(
                            $facility,
                            [
                                'FacilityDescription',
                                'facilityDescription',
                            ],
                            ''
                        )
                    )
                ),
        ],
        [
            'field' => 'Coordinates',
            'status' =>
                is_numeric($latitude)
                && is_numeric($longitude)
                    ? 'import'
                    : 'missing',
            'value' =>
                is_numeric($latitude)
                && is_numeric($longitude)
                    ? number_format(
                        (float) $latitude,
                        7,
                        '.',
                        ''
                    )
                    . ', '
                    . number_format(
                        (float) $longitude,
                        7,
                        '.',
                        ''
                    )
                    : '',
        ],
        [
            'field' => 'Address',
            'status' =>
                $addresses
                    ? 'import'
                    : 'missing',
            'value' =>
                $addresses
                    ? count($addresses)
                        . ' address record'
                        . (
                            count($addresses) === 1
                                ? ''
                                : 's'
                        )
                    : '',
        ],
        [
            'field' => 'Official links',
            'status' =>
                $links
                    ? 'import'
                    : 'missing',
            'value' =>
                $links
                    ? count($links)
                        . ' link'
                        . (
                            count($links) === 1
                                ? ''
                                : 's'
                        )
                    : '',
        ],
        [
            'field' => 'Official media',
            'status' =>
                $media
                    ? 'import'
                    : 'missing',
            'value' =>
                $media
                    ? count($media)
                        . ' media item'
                        . (
                            count($media) === 1
                                ? ''
                                : 's'
                        )
                    : '',
        ],
        [
            'field' => 'Activities',
            'status' =>
                $activities
                    ? 'import'
                    : 'missing',
            'value' =>
                $activities
                    ? count($activities)
                        . ' activit'
                        . (
                            count($activities) === 1
                                ? 'y'
                                : 'ies'
                        )
                    : '',
        ],
        [
            'field' => 'Individual campsites',
            'status' =>
                $campsites
                    ? 'import'
                    : 'missing',
            'value' =>
                $campsites
                    ? count($campsites)
                        . ' campsite'
                        . (
                            count($campsites) === 1
                                ? ''
                                : 's'
                        )
                    : '',
        ],
    ];

    $needsScout = [
        'Sensory conditions',
        'Daytime and nighttime noise',
        'Road and access condition',
        'Real-world vehicle fit',
        'Cell service experience',
        'Current hazards and warnings',
        'Site-specific observations',
        'Anything RIDB leaves blank or appears stale',
    ];

    return [
        'rows' => $rows,
        'needs_scout' => $needsScout,
    ];
}
