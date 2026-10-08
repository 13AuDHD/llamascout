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
            CURLOPT_TIMEOUT => 30,
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

function llama_ridb_facility_campsites(
    int|string $facilityId,
    int $limit = 50,
    int $offset = 0
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
            . '/campsites',
            [
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

    return [];
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
                (string) (
                    $record['FacilityID']
                    ?? ''
                )
            );

        if ($facilityId === '') {
            continue;
        }

        $stmt->execute([
            $facilityId,

            trim(
                (string) (
                    $record['FacilityName']
                    ?? ''
                )
            ),

            trim(
                (string) (
                    $record['FacilityTypeDescription']
                    ?? ''
                )
            ),

            trim(
                (string) (
                    $record['FacilityDescription']
                    ?? ''
                )
            ),

            is_numeric(
                $record['FacilityLatitude']
                ?? null
            )
                ? (float) $record[
                    'FacilityLatitude'
                ]
                : null,

            is_numeric(
                $record['FacilityLongitude']
                ?? null
            )
                ? (float) $record[
                    'FacilityLongitude'
                ]
                : null,

            isset($record['Reservable'])
                ? (
                    !empty(
                        $record['Reservable']
                    )
                        ? 1
                        : 0
                )
                : null,

            isset($record['Enabled'])
                ? (
                    !empty(
                        $record['Enabled']
                    )
                        ? 1
                        : 0
                )
                : null,

            trim(
                (string) (
                    $record['ParentRecAreaID']
                    ?? ''
                )
            ) !== ''
                ? trim(
                    (string) $record[
                        'ParentRecAreaID'
                    ]
                )
                : null,

            trim(
                (string) (
                    $record['LastUpdatedDate']
                    ?? ''
                )
            ) !== ''
                ? trim(
                    (string) $record[
                        'LastUpdatedDate'
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
