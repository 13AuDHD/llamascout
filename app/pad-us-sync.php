<?php

declare(strict_types=1);


/*
 * =========================================================
 * PAD-US REFERENCE DATABASE SYNCHRONIZATION
 *
 * PAD-US is external reference data. It is stored in the
 * dedicated reference database and is not inserted into the
 * primary Llama Scout places/taxonomy tables.
 *
 * Geometry is intentionally not requested.
 * =========================================================
 */


function llama_pad_us_states(): array
{
    return [
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
    ];
}


function llama_pad_us_source(PDO $db): array
{
    $stmt =
        $db->prepare(
            'SELECT *
             FROM reference_sources
             WHERE source_key = ?
             LIMIT 1'
        );

    $stmt->execute([
        'pad-us',
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$row) {
        throw new RuntimeException(
            'PAD-US is not registered in reference_sources.'
        );
    }

    if (
        trim(
            (string) (
                $row['base_url']
                ?? ''
            )
        ) === ''
    ) {
        throw new RuntimeException(
            'The PAD-US source URL is missing.'
        );
    }

    return $row;
}


function llama_pad_us_http_json(
    string $url,
    array $params = []
): array {
    if ($params) {
        $query =
            http_build_query(
                $params,
                '',
                '&',
                PHP_QUERY_RFC3986
            );

        $url .=
            str_contains(
                $url,
                '?'
            )
                ? '&' . $query
                : '?' . $query;
    }

    $body = null;
    $status = 0;

    if (function_exists('curl_init')) {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'PAD-US HTTP initialization failed.'
            );
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER =>
                    true,
                CURLOPT_FOLLOWLOCATION =>
                    true,
                CURLOPT_CONNECTTIMEOUT =>
                    15,
                CURLOPT_TIMEOUT =>
                    60,
                CURLOPT_USERAGENT =>
                    'LlamaScout/1.0 PAD-US Reference Sync',
                CURLOPT_HTTPHEADER =>
                    [
                        'Accept: application/json',
                    ],
            ]
        );

        $response = curl_exec($curl);

        if ($response === false) {
            $message =
                curl_error($curl);

            curl_close($curl);

            throw new RuntimeException(
                'PAD-US request failed: '
                . $message
            );
        }

        $status =
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            );

        curl_close($curl);

        $body =
            (string) $response;
    } else {
        $context =
            stream_context_create(
                [
                    'http' => [
                        'method' =>
                            'GET',
                        'timeout' =>
                            60,
                        'ignore_errors' =>
                            true,
                        'header' =>
                            "Accept: application/json\r\n"
                            . "User-Agent: LlamaScout/1.0 PAD-US Reference Sync\r\n",
                    ],
                ]
            );

        $response =
            @file_get_contents(
                $url,
                false,
                $context
            );

        if ($response === false) {
            throw new RuntimeException(
                'PAD-US request failed and cURL is not available.'
            );
        }

        $body =
            (string) $response;

        foreach (
            $http_response_header
                ?? []
            as $header
        ) {
            if (
                preg_match(
                    '/^HTTP\/\S+\s+(\d+)/i',
                    (string) $header,
                    $match
                )
            ) {
                $status =
                    (int) $match[1];
            }
        }
    }

    if (
        $status < 200
        || $status >= 300
    ) {
        throw new RuntimeException(
            'PAD-US returned HTTP '
            . $status
            . '.'
        );
    }

    $decoded =
        json_decode(
            $body,
            true
        );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'PAD-US returned invalid JSON.'
        );
    }

    if (
        isset($decoded['error'])
        && is_array($decoded['error'])
    ) {
        $message =
            trim(
                (string) (
                    $decoded['error']['message']
                    ?? ''
                )
            );

        throw new RuntimeException(
            'PAD-US ArcGIS error: '
            . (
                $message !== ''
                    ? $message
                    : 'Unknown ArcGIS error.'
            )
        );
    }

    return $decoded;
}


function llama_pad_us_domain_maps(
    PDO $db
): array {
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $source =
        llama_pad_us_source($db);

    $metadata =
        llama_pad_us_http_json(
            rtrim(
                (string) $source['base_url'],
                '/'
            ),
            [
                'f' => 'json',
            ]
        );

    $maps = [];

    foreach (
        is_array(
            $metadata['fields']
            ?? null
        )
            ? $metadata['fields']
            : []
        as $field
    ) {
        if (!is_array($field)) {
            continue;
        }

        $name =
            trim(
                (string) (
                    $field['name']
                    ?? ''
                )
            );

        $coded =
            $field['domain']['codedValues']
            ?? null;

        if (
            $name === ''
            || !is_array($coded)
        ) {
            continue;
        }

        $maps[$name] = [];

        foreach ($coded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $code =
                trim(
                    (string) (
                        $entry['code']
                        ?? ''
                    )
                );

            $label =
                trim(
                    (string) (
                        $entry['name']
                        ?? ''
                    )
                );

            if (
                $code !== ''
                && $label !== ''
            ) {
                $maps[$name][$code] =
                    $label;
            }
        }
    }

    $cache = $maps;

    return $cache;
}


function llama_pad_us_decode(
    array $domains,
    string $field,
    mixed $value
): string {
    $raw =
        trim(
            (string) $value
        );

    if ($raw === '') {
        return '';
    }

    return
        (string) (
            $domains[$field][$raw]
            ?? $raw
        );
}


function llama_pad_us_state_code(
    string $value
): string {
    $value =
        strtoupper(
            trim($value)
        );

    $states =
        llama_pad_us_states();

    if (isset($states[$value])) {
        return $value;
    }

    foreach (
        $states
        as $code => $name
    ) {
        if (
            strcasecmp(
                $name,
                $value
            ) === 0
        ) {
            return $code;
        }
    }

    return '';
}


function llama_pad_us_canonical_text(
    string $value
): string {
    $value =
        mb_strtolower(
            trim($value)
        );

    return
        preg_replace(
            '/\s+/u',
            ' ',
            $value
        )
        ?? $value;
}


function llama_pad_us_location_name(
    array $attributes
): string {
    foreach (
        [
            'Unit_Nm',
            'Loc_Nm',
            'Loc_Ds',
        ]
        as $field
    ) {
        $value =
            trim(
                (string) (
                    $attributes[$field]
                    ?? ''
                )
            );

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}


function llama_pad_us_logical_unit_id(
    array $attributes,
    string $stateCode,
    string $manager,
    string $designation
): string {
    return
        'unit-'
        . sha1(
            implode(
                '|',
                [
                    llama_pad_us_canonical_text(
                        $stateCode
                    ),
                    llama_pad_us_canonical_text(
                        $manager
                    ),
                    llama_pad_us_canonical_text(
                        llama_pad_us_location_name(
                            $attributes
                        )
                    ),
                    llama_pad_us_canonical_text(
                        $designation
                    ),
                ]
            )
        );
}


function llama_pad_us_source_value(
    PDO $db,
    int $sourceId,
    string $fieldName,
    string $sourceCode,
    string $sourceValue
): void {
    $sourceValue =
        trim($sourceValue);

    if ($sourceValue === '') {
        return;
    }

    $stmt =
        $db->prepare(
            'INSERT INTO reference_source_values (
                source_id,
                field_name,
                source_code,
                source_value,
                first_seen_at,
                last_seen_at,
                occurrence_count
             ) VALUES (
                ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1
             )
             ON DUPLICATE KEY UPDATE
                source_code =
                    CASE
                        WHEN VALUES(source_code) <> ""
                        THEN VALUES(source_code)
                        ELSE source_code
                    END,
                last_seen_at =
                    UTC_TIMESTAMP(),
                occurrence_count =
                    occurrence_count + 1'
        );

    $stmt->execute([
        $sourceId,
        $fieldName,
        $sourceCode !== ''
            ? $sourceCode
            : null,
        $sourceValue,
    ]);
}


function llama_pad_us_mapping_slug(
    PDO $db,
    int $sourceId,
    string $sourceDesignation
): ?string {
    $sourceDesignation =
        trim(
            $sourceDesignation
        );

    if ($sourceDesignation === '') {
        return null;
    }

    $stmt =
        $db->prepare(
            'SELECT property_type_slug
             FROM reference_property_type_mappings
             WHERE source_id = ?
               AND source_designation = ?
               AND active = 1
               AND reviewed = 1
             LIMIT 1'
        );

    $stmt->execute([
        $sourceId,
        $sourceDesignation,
    ]);

    $value =
        $stmt->fetchColumn();

    if ($value === false) {
        return null;
    }

    $slug =
        trim(
            (string) $value
        );

    return
        $slug !== ''
            ? $slug
            : null;
}


function llama_pad_us_upsert_organization(
    PDO $db,
    int $sourceId,
    string $stateCode,
    string $managerCode,
    string $managerName,
    string $managerType
): ?int {
    $managerName =
        trim($managerName);

    if ($managerName === '') {
        return null;
    }

    $externalId =
        'manager-'
        . sha1(
            llama_pad_us_canonical_text(
                $managerName
            )
        );

    $find =
        $db->prepare(
            'SELECT id
             FROM reference_organizations
             WHERE source_id = ?
               AND external_id = ?
             LIMIT 1'
        );

    $find->execute([
        $sourceId,
        $externalId,
    ]);

    $existing =
        $find->fetchColumn();

    if ($existing !== false) {
        $stmt =
            $db->prepare(
                'UPDATE reference_organizations
                 SET
                    name = ?,
                    organization_type = ?,
                    state_code =
                        COALESCE(
                            state_code,
                            ?
                        ),
                    active = 1,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );

        $stmt->execute([
            $managerName,
            $managerType !== ''
                ? $managerType
                : null,
            $stateCode !== ''
                ? $stateCode
                : null,
            (int) $existing,
        ]);

        return
            (int) $existing;
    }

    $stmt =
        $db->prepare(
            'INSERT INTO reference_organizations (
                source_id,
                external_id,
                name,
                organization_type,
                state_code,
                nationwide,
                active,
                metadata_json
             ) VALUES (
                ?, ?, ?, ?, ?, 0, 1, ?
             )'
        );

    $stmt->execute([
        $sourceId,
        $externalId,
        $managerName,
        $managerType !== ''
            ? $managerType
            : null,
        $stateCode !== ''
            ? $stateCode
            : null,
        json_encode(
            [
                'pad_us' => [
                    'manager_code' =>
                        $managerCode,
                ],
            ],
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ),
    ]);

    return
        (int) $db->lastInsertId();
}


function llama_pad_us_manager_type(
    string $managerCode,
    string $managerName
): string {
    $code =
        strtoupper(
            trim($managerCode)
        );

    $name =
        llama_pad_us_canonical_text(
            $managerName
        );

    if (
        $code === 'FED'
        || str_contains(
            $name,
            'federal'
        )
    ) {
        return 'federal_agency';
    }

    if (
        $code === 'STAT'
        || str_contains(
            $name,
            'state'
        )
    ) {
        return 'state_agency';
    }

    if (
        $code === 'TRIB'
        || str_contains(
            $name,
            'tribal'
        )
    ) {
        return 'tribal_government';
    }

    if (
        $code === 'CNTY'
        || str_contains(
            $name,
            'county'
        )
    ) {
        return 'county_government';
    }

    if (
        $code === 'CITY'
        || str_contains(
            $name,
            'city'
        )
        || str_contains(
            $name,
            'municipal'
        )
    ) {
        return 'municipal_government';
    }

    if (
        $code === 'NGO'
        || str_contains(
            $name,
            'nonprofit'
        )
    ) {
        return 'nonprofit';
    }

    if ($code === 'PVT') {
        return 'private_owner';
    }

    return 'other';
}


function llama_pad_us_start_run(
    PDO $db,
    int $sourceId,
    int $userId,
    string $stateCode,
    int $sourceRows
): int {
    $stmt =
        $db->prepare(
            'INSERT INTO reference_sync_runs (
                source_id,
                state_code,
                status,
                cursor_value,
                source_rows,
                rows_processed,
                started_by_user_id,
                started_at,
                last_message
             ) VALUES (
                ?, ?, "running", "0", ?, 0, ?, UTC_TIMESTAMP(), ?
             )'
        );

    $stmt->execute([
        $sourceId,
        $stateCode,
        $sourceRows,
        $userId > 0
            ? $userId
            : null,
        'PAD-US synchronization started.',
    ]);

    return
        (int) $db->lastInsertId();
}


function llama_pad_us_recent_runs(
    PDO $db,
    int $limit = 40
): array {
    $source =
        llama_pad_us_source($db);

    $limit =
        max(
            1,
            min(
                100,
                $limit
            )
        );

    $stmt =
        $db->prepare(
            'SELECT *
             FROM reference_sync_runs
             WHERE source_id = ?
             ORDER BY id DESC
             LIMIT '
             . $limit
        );

    $stmt->execute([
        (int) $source['id'],
    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


function llama_pad_us_issue_count(
    PDO $db
): int {
    $source =
        llama_pad_us_source($db);

    $stmt =
        $db->prepare(
            'SELECT COUNT(*)
             FROM reference_sync_issues i
             INNER JOIN reference_sync_runs r
                ON r.id = i.sync_run_id
             WHERE r.source_id = ?
               AND i.resolved = 0'
        );

    $stmt->execute([
        (int) $source['id'],
    ]);

    return
        (int) $stmt->fetchColumn();
}


function llama_pad_us_source_count(
    PDO $db,
    array $source,
    string $stateCode
): int {
    $response =
        llama_pad_us_http_json(
            rtrim(
                (string) $source['base_url'],
                '/'
            )
            . '/query',
            [
                'f' =>
                    'json',
                'where' =>
                    "State_Nm='"
                    . str_replace(
                        "'",
                        "''",
                        $stateCode
                    )
                    . "'",
                'returnCountOnly' =>
                    'true',
            ]
        );

    return
        max(
            0,
            (int) (
                $response['count']
                ?? 0
            )
        );
}


function llama_pad_us_import_batch(
    PDO $db,
    int $userId,
    string $stateCode,
    int $runId = 0,
    ?int $offset = null,
    int $batchSize = 500
): array {
    $stateCode =
        strtoupper(
            trim($stateCode)
        );

    if (
        !isset(
            llama_pad_us_states()[
                $stateCode
            ]
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid state.'
        );
    }

    $batchSize =
        max(
            100,
            min(
                1000,
                $batchSize
            )
        );

    $source =
        llama_pad_us_source($db);

    $sourceId =
        (int) $source['id'];

    $domains =
        llama_pad_us_domain_maps($db);

    if ($runId > 0) {
        $runStmt =
            $db->prepare(
                'SELECT *
                 FROM reference_sync_runs
                 WHERE id = ?
                   AND source_id = ?
                 LIMIT 1'
            );

        $runStmt->execute([
            $runId,
            $sourceId,
        ]);

        $run =
            $runStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$run) {
            throw new RuntimeException(
                'The PAD-US synchronization run was not found.'
            );
        }

        if (
            (string) (
                $run['state_code']
                ?? ''
            )
            !== $stateCode
        ) {
            throw new RuntimeException(
                'The PAD-US synchronization state does not match this run.'
            );
        }

        if ($offset === null) {
            $offset =
                max(
                    0,
                    (int) (
                        $run['cursor_value']
                        ?? 0
                    )
                );
        }

        $sourceRows =
            (int) (
                $run['source_rows']
                ?? 0
            );
    } else {
        $sourceRows =
            llama_pad_us_source_count(
                $db,
                $source,
                $stateCode
            );

        $runId =
            llama_pad_us_start_run(
                $db,
                $sourceId,
                $userId,
                $stateCode,
                $sourceRows
            );

        $offset = 0;
    }

    $offset =
        max(
            0,
            (int) $offset
        );

    $response =
        llama_pad_us_http_json(
            rtrim(
                (string) $source['base_url'],
                '/'
            )
            . '/query',
            [
                'f' =>
                    'json',
                'where' =>
                    "State_Nm='"
                    . str_replace(
                        "'",
                        "''",
                        $stateCode
                    )
                    . "'",
                'outFields' =>
                    implode(
                        ',',
                        [
                            'OBJECTID',
                            'Unit_Nm',
                            'Loc_Nm',
                            'Loc_Ds',
                            'Loc_Mang',
                            'Mang_Name',
                            'Mang_Type',
                            'Des_Tp',
                            'Category',
                            'State_Nm',
                            'Source_PAID',
                            'Pub_Access',
                            'GIS_Src',
                            'Src_Date',
                            'GIS_Acres',
                        ]
                    ),
                'returnGeometry' =>
                    'false',
                'resultOffset' =>
                    $offset,
                'resultRecordCount' =>
                    $batchSize,
                'orderByFields' =>
                    'OBJECTID ASC',
            ]
        );

    $features =
        is_array(
            $response['features']
            ?? null
        )
            ? $response['features']
            : [];

    $rowsRead = 0;
    $unitsCreated = 0;
    $unitsUpdated = 0;
    $organizationsCreated = 0;
    $organizationsUpdated = 0;
    $skipped = 0;

    foreach ($features as $feature) {
        if (!is_array($feature)) {
            $skipped++;
            continue;
        }

        $attributes =
            is_array(
                $feature['attributes']
                ?? null
            )
                ? $feature['attributes']
                : [];

        $rowsRead++;

        $recordState =
            llama_pad_us_state_code(
                llama_pad_us_decode(
                    $domains,
                    'State_Nm',
                    $attributes['State_Nm']
                        ?? $stateCode
                )
            );

        if ($recordState === '') {
            $recordState =
                $stateCode;
        }

        $unitName =
            llama_pad_us_location_name(
                $attributes
            );

        if ($unitName === '') {
            $skipped++;
            continue;
        }

        $managerCode =
            trim(
                (string) (
                    $attributes['Mang_Type']
                    ?? ''
                )
            );

        $decodedManager =
            llama_pad_us_decode(
                $domains,
                'Mang_Name',
                $attributes['Mang_Name']
                    ?? ''
            );

        $localManager =
            trim(
                (string) (
                    $attributes['Loc_Mang']
                    ?? ''
                )
            );

        $managerName =
            $localManager !== ''
                ? $localManager
                : $decodedManager;

        $managerType =
            llama_pad_us_manager_type(
                $managerCode,
                $managerName
            );

        $organizationId =
            llama_pad_us_upsert_organization(
                $db,
                $sourceId,
                $recordState,
                $managerCode,
                $managerName,
                $managerType
            );

        $rawDesignation =
            trim(
                (string) (
                    $attributes['Des_Tp']
                    ?? ''
                )
            );

        $decodedDesignation =
            llama_pad_us_decode(
                $domains,
                'Des_Tp',
                $rawDesignation
            );

        $localDesignation =
            trim(
                (string) (
                    $attributes['Loc_Ds']
                    ?? ''
                )
            );

        $categoryRaw =
            trim(
                (string) (
                    $attributes['Category']
                    ?? ''
                )
            );

        $decodedCategory =
            llama_pad_us_decode(
                $domains,
                'Category',
                $categoryRaw
            );

        $publicAccess =
            llama_pad_us_decode(
                $domains,
                'Pub_Access',
                $attributes['Pub_Access']
                    ?? ''
            );

        foreach (
            [
                [
                    'designation',
                    $rawDesignation,
                    $decodedDesignation,
                ],
                [
                    'local_designation',
                    '',
                    $localDesignation,
                ],
                [
                    'category',
                    $categoryRaw,
                    $decodedCategory,
                ],
                [
                    'manager',
                    $managerCode,
                    $managerName,
                ],
                [
                    'public_access',
                    trim(
                        (string) (
                            $attributes['Pub_Access']
                            ?? ''
                        )
                    ),
                    $publicAccess,
                ],
            ]
            as $sourceValue
        ) {
            llama_pad_us_source_value(
                $db,
                $sourceId,
                $sourceValue[0],
                $sourceValue[1],
                $sourceValue[2]
            );
        }

        $mappingDesignation =
            $decodedDesignation !== ''
                ? $decodedDesignation
                : $localDesignation;

        $propertyTypeSlug =
            llama_pad_us_mapping_slug(
                $db,
                $sourceId,
                $mappingDesignation
            );

        $externalId =
            llama_pad_us_logical_unit_id(
                $attributes,
                $recordState,
                $managerName,
                $decodedDesignation !== ''
                    ? $decodedDesignation
                    : $localDesignation
            );

        $metadata = [
            'pad_us' => [
                'object_id' =>
                    $attributes['OBJECTID']
                    ?? null,
                'source_paid' =>
                    $attributes['Source_PAID']
                    ?? null,
                'gis_source' =>
                    $attributes['GIS_Src']
                    ?? null,
                'source_date' =>
                    $attributes['Src_Date']
                    ?? null,
                'gis_acres' =>
                    $attributes['GIS_Acres']
                    ?? null,
                'public_access' =>
                    $publicAccess,
                'raw_attributes' =>
                    $attributes,
            ],
        ];

        $contentHash =
            hash(
                'sha256',
                json_encode(
                    [
                        'organization_id' =>
                            $organizationId,
                        'name' =>
                            llama_pad_us_canonical_text(
                                $unitName
                            ),
                        'property_type_slug' =>
                            $propertyTypeSlug,
                        'designation' =>
                            $decodedDesignation,
                        'local_designation' =>
                            $localDesignation,
                        'category' =>
                            $decodedCategory,
                        'manager' =>
                            $managerName,
                        'access' =>
                            $publicAccess,
                    ],
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
                ?: ''
            );

        $find =
            $db->prepare(
                'SELECT
                    id,
                    content_hash
                 FROM reference_units
                 WHERE source_id = ?
                   AND external_id = ?
                 LIMIT 1'
            );

        $find->execute([
            $sourceId,
            $externalId,
        ]);

        $existing =
            $find->fetch(
                PDO::FETCH_ASSOC
            );

        $sourceDate =
            trim(
                (string) (
                    $attributes['Src_Date']
                    ?? ''
                )
            );

        $sourceUpdatedAt = null;

        if (
            $sourceDate !== ''
            && preg_match(
                '/^\d{4}-\d{2}-\d{2}/',
                $sourceDate,
                $match
            )
        ) {
            $sourceUpdatedAt =
                $match[0]
                . ' 00:00:00';
        }

        if ($existing) {
            if (
                (string) (
                    $existing['content_hash']
                    ?? ''
                )
                !== $contentHash
            ) {
                $stmt =
                    $db->prepare(
                        'UPDATE reference_units
                         SET
                            organization_id = ?,
                            name = ?,
                            source_unit_name = ?,
                            source_local_name = ?,
                            source_designation = ?,
                            source_designation_code = ?,
                            source_local_designation = ?,
                            source_category = ?,
                            source_manager_name = ?,
                            source_manager_code = ?,
                            source_local_manager = ?,
                            property_type_slug = ?,
                            state_code = ?,
                            public_access = ?,
                            acreage = ?,
                            content_hash = ?,
                            source_updated_at = ?,
                            source_last_seen_at = UTC_TIMESTAMP(),
                            active = 1,
                            metadata_json = ?
                         WHERE id = ?'
                    );

                $stmt->execute([
                    $organizationId,
                    $unitName,
                    trim(
                        (string) (
                            $attributes['Unit_Nm']
                            ?? ''
                        )
                    ),
                    trim(
                        (string) (
                            $attributes['Loc_Nm']
                            ?? ''
                        )
                    ),
                    $decodedDesignation !== ''
                        ? $decodedDesignation
                        : null,
                    $rawDesignation !== ''
                        ? $rawDesignation
                        : null,
                    $localDesignation !== ''
                        ? $localDesignation
                        : null,
                    $decodedCategory !== ''
                        ? $decodedCategory
                        : null,
                    $decodedManager !== ''
                        ? $decodedManager
                        : null,
                    $managerCode !== ''
                        ? $managerCode
                        : null,
                    $localManager !== ''
                        ? $localManager
                        : null,
                    $propertyTypeSlug,
                    $recordState,
                    $publicAccess !== ''
                        ? $publicAccess
                        : null,
                    is_numeric(
                        $attributes['GIS_Acres']
                        ?? null
                    )
                        ? (float) $attributes['GIS_Acres']
                        : null,
                    $contentHash,
                    $sourceUpdatedAt,
                    json_encode(
                        $metadata,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ),
                    (int) $existing['id'],
                ]);

                $unitsUpdated++;
            } else {
                $touch =
                    $db->prepare(
                        'UPDATE reference_units
                         SET
                            source_last_seen_at = UTC_TIMESTAMP(),
                            active = 1
                         WHERE id = ?'
                    );

                $touch->execute([
                    (int) $existing['id'],
                ]);
            }
        } else {
            $stmt =
                $db->prepare(
                    'INSERT INTO reference_units (
                        source_id,
                        organization_id,
                        external_id,
                        name,
                        source_unit_name,
                        source_local_name,
                        source_designation,
                        source_designation_code,
                        source_local_designation,
                        source_category,
                        source_manager_name,
                        source_manager_code,
                        source_local_manager,
                        property_type_slug,
                        state_code,
                        public_access,
                        acreage,
                        content_hash,
                        source_updated_at,
                        source_last_seen_at,
                        active,
                        metadata_json
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), 1, ?
                     )'
                );

            $stmt->execute([
                $sourceId,
                $organizationId,
                $externalId,
                $unitName,
                trim(
                    (string) (
                        $attributes['Unit_Nm']
                        ?? ''
                    )
                ),
                trim(
                    (string) (
                        $attributes['Loc_Nm']
                        ?? ''
                    )
                ),
                $decodedDesignation !== ''
                    ? $decodedDesignation
                    : null,
                $rawDesignation !== ''
                    ? $rawDesignation
                    : null,
                $localDesignation !== ''
                    ? $localDesignation
                    : null,
                $decodedCategory !== ''
                    ? $decodedCategory
                    : null,
                $decodedManager !== ''
                    ? $decodedManager
                    : null,
                $managerCode !== ''
                    ? $managerCode
                    : null,
                $localManager !== ''
                    ? $localManager
                    : null,
                $propertyTypeSlug,
                $recordState,
                $publicAccess !== ''
                    ? $publicAccess
                    : null,
                is_numeric(
                    $attributes['GIS_Acres']
                    ?? null
                )
                    ? (float) $attributes['GIS_Acres']
                    : null,
                $contentHash,
                $sourceUpdatedAt,
                json_encode(
                    $metadata,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                ),
            ]);

            $unitsCreated++;
        }
    }

    $nextOffset =
        $offset
        + $rowsRead;

    $done =
        $rowsRead === 0
        || $nextOffset >= $sourceRows;

    $status =
        $done
            ? 'completed'
            : 'running';

    $message =
        $done
            ? 'PAD-US synchronization completed.'
            : 'PAD-US synchronization batch completed.';

    $update =
        $db->prepare(
            'UPDATE reference_sync_runs
             SET
                status = ?,
                cursor_value = ?,
                rows_processed =
                    LEAST(
                        source_rows,
                        rows_processed + ?
                    ),
                organizations_created =
                    organizations_created + ?,
                organizations_updated =
                    organizations_updated + ?,
                units_created =
                    units_created + ?,
                units_updated =
                    units_updated + ?,
                rows_skipped =
                    rows_skipped + ?,
                last_message = ?,
                completed_at =
                    CASE
                        WHEN ? = "completed"
                        THEN UTC_TIMESTAMP()
                        ELSE completed_at
                    END
             WHERE id = ?'
        );

    $update->execute([
        $status,
        (string) $nextOffset,
        $rowsRead,
        $organizationsCreated,
        $organizationsUpdated,
        $unitsCreated,
        $unitsUpdated,
        $skipped,
        $message,
        $status,
        $runId,
    ]);

    if ($done) {
        $sourceUpdate =
            $db->prepare(
                'UPDATE reference_sources
                 SET last_successful_sync_at = UTC_TIMESTAMP()
                 WHERE id = ?'
            );

        $sourceUpdate->execute([
            $sourceId,
        ]);
    }

    $runStmt =
        $db->prepare(
            'SELECT *
             FROM reference_sync_runs
             WHERE id = ?
             LIMIT 1'
        );

    $runStmt->execute([
        $runId,
    ]);

    $run =
        $runStmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: [];

    return [
        'run_id' =>
            $runId,
        'state_code' =>
            $stateCode,
        'source_rows' =>
            (int) (
                $run['source_rows']
                ?? $sourceRows
            ),
        'rows_processed' =>
            (int) (
                $run['rows_processed']
                ?? $nextOffset
            ),
        'units_created' =>
            (int) (
                $run['units_created']
                ?? 0
            ),
        'units_updated' =>
            (int) (
                $run['units_updated']
                ?? 0
            ),
        'rows_skipped' =>
            (int) (
                $run['rows_skipped']
                ?? 0
            ),
        'warning_count' =>
            (int) (
                $run['warning_count']
                ?? 0
            ),
        'next_offset' =>
            $nextOffset,
        'done' =>
            $done,
    ];
}
