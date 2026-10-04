<?php

declare(strict_types=1);


/*
 * =========================================================
 * PAD-US TAXONOMY SYNCHRONIZATION
 *
 * Imports attribute-only PAD-US records into the normalized
 * Llama Scout taxonomy tables.
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
             FROM place_taxonomy_sources
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
            'PAD-US is not registered in place_taxonomy_sources.'
        );
    }

    $baseUrl =
        trim(
            (string) (
                $row['base_url']
                ?? ''
            )
        );

    if ($baseUrl === '') {
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
        $curl =
            curl_init(
                $url
            );

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
                    'LlamaScout/1.0 PAD-US Taxonomy Sync',
                CURLOPT_HTTPHEADER =>
                    [
                        'Accept: application/json',
                    ],
            ]
        );

        $response =
            curl_exec(
                $curl
            );

        if ($response === false) {
            $message =
                curl_error(
                    $curl
                );

            curl_close(
                $curl
            );

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

        curl_close(
            $curl
        );

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
                            . "User-Agent: LlamaScout/1.0 PAD-US Taxonomy Sync\r\n",
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
        isset(
            $decoded['error']
        )
        && is_array(
            $decoded['error']
        )
    ) {
        $message =
            trim(
                (string) (
                    $decoded['error']['message']
                    ?? ''
                )
            );

        if ($message === '') {
            $message =
                'Unknown ArcGIS error.';
        }

        throw new RuntimeException(
            'PAD-US ArcGIS error: '
            . $message
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
        llama_pad_us_source(
            $db
        );

    $metadata =
        llama_pad_us_http_json(
            rtrim(
                (string) $source['base_url'],
                '/'
            ),
            [
                'f' =>
                    'json',
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

        if ($name === '') {
            continue;
        }

        $codedValues =
            $field['domain']['codedValues']
            ?? null;

        if (!is_array($codedValues)) {
            continue;
        }

        $maps[$name] = [];

        foreach (
            $codedValues
            as $entry
        ) {
            if (!is_array($entry)) {
                continue;
            }

            $code =
                (string) (
                    $entry['code']
                    ?? ''
                );

            $label =
                trim(
                    (string) (
                        $entry['name']
                        ?? ''
                    )
                );

            if (
                $code === ''
                || $label === ''
            ) {
                continue;
            }

            $maps[$name][
                $code
            ] = $label;
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
            trim(
                $value
            )
        );

    $states =
        llama_pad_us_states();

    if (
        isset(
            $states[$value]
        )
    ) {
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


function llama_pad_us_slug(
    string $value
): string {
    $value =
        strtolower(
            trim(
                $value
            )
        );

    $value =
        preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        )
        ?? '';

    return trim(
        $value,
        '-'
    );
}


function llama_pad_us_manager_type(
    string $raw,
    string $decoded
): string {
    $code =
        strtoupper(
            trim(
                $raw
            )
        );

    $label =
        strtolower(
            trim(
                $decoded
            )
        );

    if (
        $code === 'FED'
        || str_contains(
            $label,
            'federal'
        )
    ) {
        return 'federal_agency';
    }

    if (
        $code === 'TRIB'
        || str_contains(
            $label,
            'american indian'
        )
        || str_contains(
            $label,
            'tribal'
        )
    ) {
        return 'tribal_government';
    }

    if (
        $code === 'STAT'
        || str_contains(
            $label,
            'state'
        )
    ) {
        return 'state_agency';
    }

    if (
        $code === 'NGO'
        || str_contains(
            $label,
            'non-government'
        )
    ) {
        return 'nonprofit';
    }

    if (
        $code === 'PVT'
        || str_contains(
            $label,
            'private'
        )
    ) {
        return 'private_owner';
    }

    if (
        $code === 'CNTY'
        || str_contains(
            $label,
            'county'
        )
    ) {
        return 'county_government';
    }

    if (
        $code === 'CITY'
        || str_contains(
            $label,
            'city'
        )
        || str_contains(
            $label,
            'municipal'
        )
    ) {
        return 'municipal_government';
    }

    return 'other';
}


function llama_pad_us_seeded_manager_slug(
    string $rawCode,
    string $decodedName,
    string $localManager
): ?string {
    $code =
        strtoupper(
            trim(
                $rawCode
            )
        );

    $map = [
        'USFS' =>
            'us-forest-service',
        'BLM' =>
            'bureau-land-management',
        'NPS' =>
            'national-park-service',
        'FWS' =>
            'us-fish-wildlife-service',
        'USFWS' =>
            'us-fish-wildlife-service',
        'USACE' =>
            'us-army-corps-engineers',
        'USBR' =>
            'bureau-reclamation',
        'BOR' =>
            'bureau-reclamation',
        'TVA' =>
            'tennessee-valley-authority',
    ];

    if (isset($map[$code])) {
        return $map[$code];
    }

    $names = [
        'u.s. forest service' =>
            'us-forest-service',
        'forest service' =>
            'us-forest-service',
        'bureau of land management' =>
            'bureau-land-management',
        'national park service' =>
            'national-park-service',
        'u.s. fish and wildlife service' =>
            'us-fish-wildlife-service',
        'us fish and wildlife service' =>
            'us-fish-wildlife-service',
        'u.s. army corps of engineers' =>
            'us-army-corps-engineers',
        'bureau of reclamation' =>
            'bureau-reclamation',
        'tennessee valley authority' =>
            'tennessee-valley-authority',
    ];

    foreach (
        [
            $decodedName,
            $localManager,
        ]
        as $candidate
    ) {
        $normalized =
            strtolower(
                trim(
                    $candidate
                )
            );

        if (
            isset(
                $names[$normalized]
            )
        ) {
            return
                $names[$normalized];
        }
    }

    return null;
}


function llama_pad_us_property_type_slug(
    string $rawCode,
    string $decoded,
    string $localDesignation
): ?string {
    $code =
        strtoupper(
            trim(
                $rawCode
            )
        );

    $map = [
        'NF' =>
            'national-forest',
        'NG' =>
            'national-grassland',
        'NP' =>
            'national-park',
        'NM' =>
            'national-monument',
        'NCA' =>
            'national-conservation-area',
        'NRA' =>
            'national-recreation-area',
        'NWR' =>
            'national-wildlife-refuge',
        'SP' =>
            'state-park',
        'SRA' =>
            'state-recreation-area',
        'WMA' =>
            'wildlife-management-game-land',
    ];

    if (
        isset(
            $map[$code]
        )
    ) {
        return $map[$code];
    }

    $text =
        strtolower(
            trim(
                $decoded
                . ' '
                . $localDesignation
            )
        );

    $patterns = [
        'national forest' =>
            'national-forest',
        'national grassland' =>
            'national-grassland',
        'national conservation area' =>
            'national-conservation-area',
        'national park' =>
            'national-park',
        'national preserve' =>
            'national-preserve-reserve',
        'national reserve' =>
            'national-preserve-reserve',
        'national monument' =>
            'national-monument',
        'national recreation area' =>
            'national-recreation-area',
        'national seashore' =>
            'national-seashore-lakeshore',
        'national lakeshore' =>
            'national-seashore-lakeshore',
        'national river' =>
            'national-river-scenic-riverway',
        'scenic riverway' =>
            'national-river-scenic-riverway',
        'national wild and scenic river' =>
            'national-river-scenic-riverway',
        'federal recreation area' =>
            'federal-water-project',
        'water project' =>
            'federal-water-project',
        'reservoir recreation area' =>
            'federal-water-project',
        'wildlife refuge' =>
            'national-wildlife-refuge',
        'state forest' =>
            'state-forest',
        'state park' =>
            'state-park',
        'state recreation area' =>
            'state-recreation-area',
        'state natural area' =>
            'state-natural-area',
        'state preserve' =>
            'state-natural-area',
        'natural area' =>
            'state-natural-area',
        'nature preserve' =>
            'state-natural-area',
        'state trust land' =>
            'state-trust-land',
        'wildlife management area' =>
            'wildlife-management-game-land',
        'game land' =>
            'wildlife-management-game-land',
        'game management area' =>
            'wildlife-management-game-land',
        'wildlife area' =>
            'wildlife-management-game-land',
        'county park' =>
            'county-regional-park',
        'regional park' =>
            'county-regional-park',
        'municipal park' =>
            'city-municipal-land',
        'city park' =>
            'city-municipal-land',
        'land trust' =>
            'land-trust-preserve',
        'conservation preserve' =>
            'land-trust-preserve',
    ];

    foreach (
        $patterns
        as $needle => $slug
    ) {
        if (
            str_contains(
                $text,
                $needle
            )
        ) {
            return $slug;
        }
    }

    return null;
}


function llama_pad_us_designation_is_nonprimary(
    string $decoded,
    string $localDesignation,
    string $category
): bool {
    $text =
        mb_strtolower(
            trim(
                $decoded
                . ' '
                . $localDesignation
                . ' '
                . $category
            )
        );

    foreach (
        [
            'easement',
            'wilderness study area',
            'wilderness area',
            'area of critical environmental concern',
            'research natural area',
            'national scenic trail',
            'national historic trail',
            'wild and scenic river',
            'critical habitat',
            'roadless area',
            'conservation easement',
            'proclamation boundary',
            'planning boundary',
        ]
        as $needle
    ) {
        if (
            str_contains(
                $text,
                $needle
            )
        ) {
            return true;
        }
    }

    return false;
}


function llama_pad_us_property_type_id(
    PDO $db,
    string $slug
): ?int {
    $stmt =
        $db->prepare(
            'SELECT id
             FROM place_property_types
             WHERE slug = ?
               AND active = 1
             LIMIT 1'
        );

    $stmt->execute([
        $slug,
    ]);

    $value =
        $stmt->fetchColumn();

    return
        $value === false
            ? null
            : (int) $value;
}


function llama_pad_us_issue(
    PDO $db,
    int $runId,
    ?string $sourceRecordId,
    string $stateCode,
    string $severity,
    string $issueType,
    string $message,
    array $sourceData
): void {
    $stmt =
        $db->prepare(
            'INSERT INTO place_taxonomy_import_issues (
                import_run_id,
                source_record_id,
                state_code,
                severity,
                issue_type,
                message,
                source_data_json
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?
             )'
        );

    $stmt->execute([
        $runId,
        $sourceRecordId,
        $stateCode !== ''
            ? $stateCode
            : null,
        $severity,
        $issueType,
        mb_substr(
            $message,
            0,
            5000
        ),
        json_encode(
            $sourceData,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ),
    ]);
}


function llama_pad_us_issue_once(
    PDO $db,
    int $runId,
    ?string $sourceRecordId,
    string $stateCode,
    string $severity,
    string $issueType,
    string $message,
    array $sourceData
): bool {
    $check =
        $db->prepare(
            'SELECT id
             FROM place_taxonomy_import_issues
             WHERE import_run_id = ?
               AND issue_type = ?
               AND message = ?
             LIMIT 1'
        );

    $check->execute([
        $runId,
        $issueType,
        $message,
    ]);

    if (
        $check->fetchColumn()
        !== false
    ) {
        return false;
    }

    llama_pad_us_issue(
        $db,
        $runId,
        $sourceRecordId,
        $stateCode,
        $severity,
        $issueType,
        $message,
        $sourceData
    );

    return true;
}


function llama_pad_us_organization(
    PDO $db,
    string $rawManagerCode,
    string $decodedManager,
    string $localManager,
    string $rawManagerType,
    string $decodedManagerType,
    string $stateCode
): array {
    $seededSlug =
        llama_pad_us_seeded_manager_slug(
            $rawManagerCode,
            $decodedManager,
            $localManager
        );

    if ($seededSlug !== null) {
        $stmt =
            $db->prepare(
                'SELECT id, name
                 FROM place_organizations
                 WHERE slug = ?
                 LIMIT 1'
            );

        $stmt->execute([
            $seededSlug,
        ]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($row) {
            return [
                'id' =>
                    (int) $row['id'],
                'created' =>
                    false,
                'name' =>
                    (string) $row['name'],
            ];
        }
    }

    $name =
        trim(
            $localManager
        );

    if ($name === '') {
        $name =
            trim(
                $decodedManager
            );
    }

    if ($name === '') {
        return [
            'id' =>
                null,
            'created' =>
                false,
            'name' =>
                '',
        ];
    }

    $lookup =
        $db->prepare(
            'SELECT id, name
             FROM place_organizations
             WHERE name = ?
             LIMIT 1'
        );

    $lookup->execute([
        $name,
    ]);

    $existing =
        $lookup->fetch(
            PDO::FETCH_ASSOC
        );

    if ($existing) {
        $organizationId =
            (int) $existing['id'];

        if ($stateCode !== '') {
            $stateStmt =
                $db->prepare(
                    'INSERT IGNORE INTO place_organization_states (
                        organization_id,
                        state_code
                     ) VALUES (?, ?)'
                );

            $stateStmt->execute([
                $organizationId,
                $stateCode,
            ]);
        }

        return [
            'id' =>
                $organizationId,
            'created' =>
                false,
            'name' =>
                (string) $existing['name'],
        ];
    }

    $slugBase =
        llama_pad_us_slug(
            $name
        );

    if ($slugBase === '') {
        $slugBase =
            'organization';
    }

    $slug =
        'padus-'
        . (
            $stateCode !== ''
                ? strtolower(
                    $stateCode
                )
                . '-'
                : ''
        )
        . $slugBase;

    $candidate =
        $slug;

    $suffix = 2;

    $slugCheck =
        $db->prepare(
            'SELECT id
             FROM place_organizations
             WHERE slug = ?
             LIMIT 1'
        );

    while (true) {
        $slugCheck->execute([
            $candidate,
        ]);

        if (
            $slugCheck->fetchColumn()
            === false
        ) {
            break;
        }

        $candidate =
            $slug
            . '-'
            . $suffix;

        $suffix++;
    }

    $insert =
        $db->prepare(
            'INSERT INTO place_organizations (
                slug,
                name,
                organization_type,
                nationwide,
                active,
                sort_order
             ) VALUES (
                ?, ?, ?, 0, 1, 500
             )'
        );

    $insert->execute([
        $candidate,
        $name,
        llama_pad_us_manager_type(
            $rawManagerType,
            $decodedManagerType
        ),
    ]);

    $organizationId =
        (int) $db->lastInsertId();

    if ($stateCode !== '') {
        $stateStmt =
            $db->prepare(
                'INSERT IGNORE INTO place_organization_states (
                    organization_id,
                    state_code
                 ) VALUES (?, ?)'
            );

        $stateStmt->execute([
            $organizationId,
            $stateCode,
        ]);
    }

    return [
        'id' =>
            $organizationId,
        'created' =>
            true,
        'name' =>
            $name,
    ];
}


function llama_pad_us_canonical_key_part(
    string $value
): string {
    $value =
        mb_strtolower(
            trim(
                $value
            )
        );

    $value =
        preg_replace(
            '/\s+/u',
            ' ',
            $value
        )
        ?? $value;

    return $value;
}


function llama_pad_us_logical_unit_id(
    array $attributes,
    string $stateCode,
    string $decodedManager,
    string $decodedDesignation
): string {
    $name =
        llama_pad_us_location_name(
            $attributes
        );

    $manager =
        trim(
            (string) (
                $attributes['Loc_Mang']
                ?? ''
            )
        );

    if ($manager === '') {
        $manager =
            trim(
                $decodedManager
            );
    }

    $designation =
        trim(
            (string) (
                $attributes['Loc_Ds']
                ?? ''
            )
        );

    if ($designation === '') {
        $designation =
            trim(
                $decodedDesignation
            );
    }

    return
        'unit-'
        . sha1(
            implode(
                '|',
                [
                    llama_pad_us_canonical_key_part(
                        $stateCode
                    ),
                    llama_pad_us_canonical_key_part(
                        $manager
                    ),
                    llama_pad_us_canonical_key_part(
                        $name
                    ),
                    llama_pad_us_canonical_key_part(
                        $designation
                    ),
                ]
            )
        );
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


function llama_pad_us_run_start(
    PDO $db,
    int $actorUserId,
    string $stateCode
): array {
    $states =
        llama_pad_us_states();

    if (
        !isset(
            $states[$stateCode]
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid U.S. state.'
        );
    }

    $source =
        llama_pad_us_source(
            $db
        );

    $baseUrl =
        rtrim(
            (string) $source['base_url'],
            '/'
        );

    $count =
        llama_pad_us_http_json(
            $baseUrl
            . '/query',
            [
                'where' =>
                    "State_Nm='"
                    . $stateCode
                    . "'",
                'returnCountOnly' =>
                    'true',
                'f' =>
                    'json',
            ]
        );

    $totalRows =
        max(
            0,
            (int) (
                $count['count']
                ?? 0
            )
        );

    $stmt =
        $db->prepare(
            'INSERT INTO place_taxonomy_import_runs (
                source_key,
                source_name,
                source_version,
                state_code,
                status,
                cursor_value,
                source_rows,
                started_by,
                started_at
             ) VALUES (
                ?, ?, ?, ?, "running", "0", ?, ?, UTC_TIMESTAMP()
             )'
        );

    $stmt->execute([
        'pad-us',
        (string) (
            $source['name']
            ?? 'PAD-US'
        ),
        (string) (
            $source['version']
            ?? ''
        ),
        $stateCode,
        $totalRows,
        $actorUserId > 0
            ? $actorUserId
            : null,
    ]);

    return [
        'id' =>
            (int) $db->lastInsertId(),
        'state_code' =>
            $stateCode,
        'source_rows' =>
            $totalRows,
        'offset' =>
            0,
    ];
}


function llama_pad_us_run(
    PDO $db,
    int $runId
): ?array {
    $stmt =
        $db->prepare(
            'SELECT *
             FROM place_taxonomy_import_runs
             WHERE id = ?
               AND source_key = "pad-us"
             LIMIT 1'
        );

    $stmt->execute([
        $runId,
    ]);

    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $row
            ?: null;
}


function llama_pad_us_recent_runs(
    PDO $db,
    int $limit = 25
): array {
    $limit =
        max(
            1,
            min(
                100,
                $limit
            )
        );

    $stmt =
        $db->query(
            'SELECT *
             FROM place_taxonomy_import_runs
             WHERE source_key = "pad-us"
             ORDER BY id DESC
             LIMIT '
            . $limit
        );

    return
        $stmt
            ? $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            : [];
}


function llama_pad_us_issue_count(
    PDO $db
): int {
    return
        (int) $db
            ->query(
                'SELECT COUNT(*)
                 FROM place_taxonomy_import_issues
                 WHERE resolved = 0'
            )
            ->fetchColumn();
}


function llama_pad_us_import_batch(
    PDO $db,
    int $actorUserId,
    string $stateCode,
    int $runId = 0,
    ?int $offset = null,
    int $batchSize = 500
): array {
    $states =
        llama_pad_us_states();

    if (
        !isset(
            $states[$stateCode]
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid U.S. state.'
        );
    }

    $batchSize =
        max(
            50,
            min(
                1000,
                $batchSize
            )
        );

    if ($runId < 1) {
        $run =
            llama_pad_us_run_start(
                $db,
                $actorUserId,
                $stateCode
            );

        $runId =
            (int) $run['id'];

        $offset = 0;
    } else {
        $run =
            llama_pad_us_run(
                $db,
                $runId
            );

        if (!$run) {
            throw new RuntimeException(
                'The PAD-US sync run could not be found.'
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
                'The PAD-US sync run belongs to another state.'
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
    }

    $offset =
        max(
            0,
            (int) $offset
        );

    $source =
        llama_pad_us_source(
            $db
        );

    $domains =
        llama_pad_us_domain_maps(
            $db
        );

    $query =
        llama_pad_us_http_json(
            rtrim(
                (string) $source['base_url'],
                '/'
            )
            . '/query',
            [
                'where' =>
                    "State_Nm='"
                    . $stateCode
                    . "'",
                'outFields' =>
                    implode(
                        ',',
                        [
                            'OBJECTID',
                            'FeatClass',
                            'Category',
                            'Own_Type',
                            'Own_Name',
                            'Loc_Own',
                            'Mang_Type',
                            'Mang_Name',
                            'Loc_Mang',
                            'Des_Tp',
                            'Loc_Ds',
                            'Unit_Nm',
                            'Loc_Nm',
                            'State_Nm',
                            'Agg_Src',
                            'GIS_Src',
                            'Src_Date',
                            'GIS_Acres',
                            'Source_PAID',
                            'Pub_Access',
                            'Comments',
                        ]
                    ),
                'returnGeometry' =>
                    'false',
                'orderByFields' =>
                    'OBJECTID ASC',
                'resultOffset' =>
                    $offset,
                'resultRecordCount' =>
                    $batchSize,
                'f' =>
                    'json',
            ]
        );

    $features =
        is_array(
            $query['features']
            ?? null
        )
            ? $query['features']
            : [];

    $createdOrganizations = 0;
    $updatedOrganizations = 0;
    $createdLocations = 0;
    $updatedLocations = 0;
    $skipped = 0;
    $warnings = 0;
    $processed = 0;

    $sourceVersion =
        trim(
            (string) (
                $source['version']
                ?? ''
            )
        );

    $db->beginTransaction();

    try {
        foreach (
            $features
            as $feature
        ) {
            if (
                !is_array(
                    $feature
                )
            ) {
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

            if (!$attributes) {
                $skipped++;
                continue;
            }

            $processed++;

            $decodedState =
                llama_pad_us_decode(
                    $domains,
                    'State_Nm',
                    $attributes['State_Nm']
                        ?? ''
                );

            $recordState =
                llama_pad_us_state_code(
                    (string) (
                        $attributes['State_Nm']
                        ?? $decodedState
                    )
                );

            if ($recordState === '') {
                $recordState =
                    llama_pad_us_state_code(
                        $decodedState
                    );
            }

            if ($recordState === '') {
                $recordState =
                    $stateCode;
            }

            $decodedManager =
                llama_pad_us_decode(
                    $domains,
                    'Mang_Name',
                    $attributes['Mang_Name']
                        ?? ''
                );

            $decodedManagerType =
                llama_pad_us_decode(
                    $domains,
                    'Mang_Type',
                    $attributes['Mang_Type']
                        ?? ''
                );

            $decodedDesignation =
                llama_pad_us_decode(
                    $domains,
                    'Des_Tp',
                    $attributes['Des_Tp']
                        ?? ''
                );

            $sourceRecordId =
                llama_pad_us_logical_unit_id(
                    $attributes,
                    $recordState,
                    $decodedManager,
                    $decodedDesignation
                );

            $decodedAccess =
                llama_pad_us_decode(
                    $domains,
                    'Pub_Access',
                    $attributes['Pub_Access']
                        ?? ''
                );

            $localManager =
                trim(
                    (string) (
                        $attributes['Loc_Mang']
                        ?? ''
                    )
                );

            $organization =
                llama_pad_us_organization(
                    $db,
                    (string) (
                        $attributes['Mang_Name']
                        ?? ''
                    ),
                    $decodedManager,
                    $localManager,
                    (string) (
                        $attributes['Mang_Type']
                        ?? ''
                    ),
                    $decodedManagerType,
                    $recordState
                );

            $organizationId =
                isset(
                    $organization['id']
                )
                    ? (int) $organization['id']
                    : null;

            if (
                !empty(
                    $organization['created']
                )
            ) {
                $createdOrganizations++;
            }

            $propertySlug =
                llama_pad_us_property_type_slug(
                    (string) (
                        $attributes['Des_Tp']
                        ?? ''
                    ),
                    $decodedDesignation,
                    (string) (
                        $attributes['Loc_Ds']
                        ?? ''
                    )
                );

            $propertyTypeId = null;

            if ($propertySlug !== null) {
                $propertyTypeId =
                    llama_pad_us_property_type_id(
                        $db,
                        $propertySlug
                    );
            }

            if ($propertyTypeId === null) {
                $category =
                    llama_pad_us_decode(
                        $domains,
                        'Category',
                        $attributes['Category']
                            ?? ''
                    );

                if (
                    llama_pad_us_designation_is_nonprimary(
                        $decodedDesignation,
                        (string) (
                            $attributes['Loc_Ds']
                            ?? ''
                        ),
                        $category
                    )
                ) {
                    $skipped++;
                    continue;
                }

                $designationLabel =
                    trim(
                        $decodedDesignation
                    );

                if ($designationLabel === '') {
                    $designationLabel =
                        trim(
                            (string) (
                                $attributes['Loc_Ds']
                                ?? $attributes['Des_Tp']
                                ?? 'Unknown'
                            )
                        );
                }

                $message =
                    'PAD-US designation "'
                    . (
                        $designationLabel !== ''
                            ? $designationLabel
                            : 'Unknown'
                    )
                    . '" is not mapped to a Llama Scout property type.';

                if (
                    llama_pad_us_issue_once(
                        $db,
                        $runId,
                        $sourceRecordId,
                        $recordState,
                        'warning',
                        'unmapped_designation',
                        $message,
                        $attributes
                    )
                ) {
                    $warnings++;
                }

                $skipped++;
                continue;
            }

            if (
                $organizationId !== null
                && $propertyTypeId !== null
            ) {
                $relation =
                    $db->prepare(
                        'INSERT IGNORE INTO place_organization_property_types (
                            organization_id,
                            property_type_id
                         ) VALUES (?, ?)'
                    );

                $relation->execute([
                    $organizationId,
                    $propertyTypeId,
                ]);
            }

            $locationName =
                llama_pad_us_location_name(
                    $attributes
                );

            if ($locationName === '') {
                if (
                    llama_pad_us_issue_once(
                        $db,
                        $runId,
                        $sourceRecordId,
                        $recordState,
                        'warning',
                        'missing_location_name',
                        'PAD-US contains records with no usable unit or local name.',
                        $attributes
                    )
                ) {
                    $warnings++;
                }

                $skipped++;
                continue;
            }

            $metadata = [
                'pad_us' => [
                    'object_id' =>
                        $attributes['OBJECTID']
                        ?? null,
                    'feature_class' =>
                        $attributes['FeatClass']
                        ?? null,
                    'category' =>
                        $attributes['Category']
                        ?? null,
                    'owner_type' =>
                        llama_pad_us_decode(
                            $domains,
                            'Own_Type',
                            $attributes['Own_Type']
                                ?? ''
                        ),
                    'owner_name' =>
                        llama_pad_us_decode(
                            $domains,
                            'Own_Name',
                            $attributes['Own_Name']
                                ?? ''
                        ),
                    'local_owner' =>
                        $attributes['Loc_Own']
                        ?? null,
                    'manager_type' =>
                        $decodedManagerType,
                    'manager_name' =>
                        $decodedManager,
                    'local_manager' =>
                        $localManager,
                    'designation_type' =>
                        $decodedDesignation,
                    'local_designation' =>
                        $attributes['Loc_Ds']
                        ?? null,
                    'unit_name' =>
                        $attributes['Unit_Nm']
                        ?? null,
                    'local_name' =>
                        $attributes['Loc_Nm']
                        ?? null,
                    'public_access' =>
                        $decodedAccess,
                    'aggregator_source' =>
                        $attributes['Agg_Src']
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
                    'source_paid' =>
                        $attributes['Source_PAID']
                        ?? null,
                    'comments' =>
                        $attributes['Comments']
                        ?? null,
                ],
            ];

            $contentHash =
                hash(
                    'sha256',
                    json_encode(
                        [
                            'organization_id' =>
                                $organizationId,
                            'property_type_id' =>
                                $propertyTypeId,
                            'state_code' =>
                                $recordState,
                            'name' =>
                                llama_pad_us_canonical_key_part(
                                    $locationName
                                ),
                            'manager' =>
                                llama_pad_us_canonical_key_part(
                                    $localManager !== ''
                                        ? $localManager
                                        : $decodedManager
                                ),
                            'designation' =>
                                llama_pad_us_canonical_key_part(
                                    trim(
                                        (string) (
                                            $attributes['Loc_Ds']
                                            ?? ''
                                        )
                                    ) !== ''
                                        ? (string) $attributes['Loc_Ds']
                                        : $decodedDesignation
                                ),
                            'public_access' =>
                                llama_pad_us_canonical_key_part(
                                    $decodedAccess
                                ),
                        ],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    ?: ''
                );

            $ledgerStmt =
                $db->prepare(
                    'SELECT *
                     FROM place_taxonomy_source_records
                     WHERE source_key = "pad-us"
                       AND source_record_id = ?
                     LIMIT 1'
                );

            $ledgerStmt->execute([
                $sourceRecordId,
            ]);

            $ledger =
                $ledgerStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if (
                $ledger
                && hash_equals(
                    (string) (
                        $ledger['content_hash']
                        ?? ''
                    ),
                    $contentHash
                )
            ) {
                $touch =
                    $db->prepare(
                        'UPDATE place_taxonomy_source_records
                         SET
                            source_version = ?,
                            state_code = ?,
                            last_seen_at = UTC_TIMESTAMP()
                         WHERE id = ?'
                    );

                $touch->execute([
                    $sourceVersion,
                    $recordState,
                    (int) $ledger['id'],
                ]);

                $skipped++;
                continue;
            }

            $locationId =
                $ledger
                    ? (int) (
                        $ledger[
                            'organization_location_id'
                        ]
                        ?? 0
                    )
                    : 0;

            if ($locationId > 0) {
                $update =
                    $db->prepare(
                        'UPDATE place_organization_locations
                         SET
                            organization_id = ?,
                            property_type_id = ?,
                            name = ?,
                            state_code = ?,
                            source_type = "public_dataset",
                            source_name = "PAD-US",
                            source_external_id = ?,
                            source_last_verified_at = UTC_TIMESTAMP(),
                            source_last_synced_at = UTC_TIMESTAMP(),
                            active = 1,
                            metadata_json = ?
                         WHERE id = ?'
                    );

                $update->execute([
                    $organizationId,
                    $propertyTypeId,
                    $locationName,
                    $recordState,
                    $sourceRecordId,
                    json_encode(
                        $metadata,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ),
                    $locationId,
                ]);

                $updatedLocations++;
            } else {
                $insert =
                    $db->prepare(
                        'INSERT INTO place_organization_locations (
                            organization_id,
                            property_type_id,
                            name,
                            external_id,
                            state_code,
                            source_type,
                            source_name,
                            source_external_id,
                            source_last_verified_at,
                            source_last_synced_at,
                            active,
                            metadata_json
                         ) VALUES (
                            ?, ?, ?, ?, ?, "public_dataset",
                            "PAD-US", ?, UTC_TIMESTAMP(),
                            UTC_TIMESTAMP(), 1, ?
                         )'
                    );

                $insert->execute([
                    $organizationId,
                    $propertyTypeId,
                    $locationName,
                    'pad-us:' . $sourceRecordId,
                    $recordState,
                    $sourceRecordId,
                    json_encode(
                        $metadata,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ),
                ]);

                $locationId =
                    (int) $db->lastInsertId();

                $createdLocations++;
            }

            if ($ledger) {
                $ledgerUpdate =
                    $db->prepare(
                        'UPDATE place_taxonomy_source_records
                         SET
                            source_version = ?,
                            state_code = ?,
                            organization_id = ?,
                            organization_location_id = ?,
                            property_type_id = ?,
                            content_hash = ?,
                            last_seen_at = UTC_TIMESTAMP()
                         WHERE id = ?'
                    );

                $ledgerUpdate->execute([
                    $sourceVersion,
                    $recordState,
                    $organizationId,
                    $locationId,
                    $propertyTypeId,
                    $contentHash,
                    (int) $ledger['id'],
                ]);
            } else {
                $ledgerInsert =
                    $db->prepare(
                        'INSERT INTO place_taxonomy_source_records (
                            source_key,
                            source_record_id,
                            source_version,
                            state_code,
                            organization_id,
                            organization_location_id,
                            property_type_id,
                            content_hash,
                            last_seen_at
                         ) VALUES (
                            "pad-us", ?, ?, ?, ?, ?, ?, ?,
                            UTC_TIMESTAMP()
                         )'
                    );

                $ledgerInsert->execute([
                    $sourceRecordId,
                    $sourceVersion,
                    $recordState,
                    $organizationId,
                    $locationId,
                    $propertyTypeId,
                    $contentHash,
                ]);
            }
        }

        $read =
            count(
                $features
            );

        $nextOffset =
            $offset
            + $read;

        $runNow =
            llama_pad_us_run(
                $db,
                $runId
            );

        $sourceRows =
            max(
                0,
                (int) (
                    $runNow['source_rows']
                    ?? 0
                )
            );

        $done =
            $read === 0
            || $nextOffset
                >= $sourceRows;

        $runUpdate =
            $db->prepare(
                'UPDATE place_taxonomy_import_runs
                 SET
                    status = ?,
                    cursor_value = ?,
                    rows_processed =
                        rows_processed + ?,
                    organizations_created =
                        organizations_created + ?,
                    organizations_updated =
                        organizations_updated + ?,
                    locations_created =
                        locations_created + ?,
                    locations_updated =
                        locations_updated + ?,
                    rows_skipped =
                        rows_skipped + ?,
                    warning_count =
                        warning_count + ?,
                    last_message = ?,
                    completed_at =
                        CASE
                            WHEN ? = 1
                                THEN UTC_TIMESTAMP()
                            ELSE NULL
                        END
                 WHERE id = ?'
            );

        $message =
            $done
                ? 'PAD-US '
                    . $stateCode
                    . ' synchronization completed.'
                : 'Processed PAD-US '
                    . $stateCode
                    . ' rows '
                    . number_format(
                        $offset + 1
                    )
                    . ' through '
                    . number_format(
                        $nextOffset
                    )
                    . '.';

        $runUpdate->execute([
            $done
                ? 'completed'
                : 'running',
            (string) $nextOffset,
            $processed,
            $createdOrganizations,
            $updatedOrganizations,
            $createdLocations,
            $updatedLocations,
            $skipped,
            $warnings,
            $message,
            $done
                ? 1
                : 0,
            $runId,
        ]);

        if ($done) {
            $sourceTouch =
                $db->prepare(
                    'UPDATE place_taxonomy_sources
                     SET last_successful_sync_at =
                            UTC_TIMESTAMP()
                     WHERE source_key = "pad-us"'
                );

            $sourceTouch->execute();
        }

        $db->commit();

    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        try {
            $currentRun =
                llama_pad_us_run(
                    $db,
                    $runId
                );

            $partial =
                (int) (
                    $currentRun[
                        'rows_processed'
                    ]
                    ?? 0
                ) > 0;

            $fail =
                $db->prepare(
                    'UPDATE place_taxonomy_import_runs
                     SET
                        status = ?,
                        last_message = ?,
                        error_count =
                            error_count + 1
                     WHERE id = ?'
                );

            $fail->execute([
                $partial
                    ? 'partial'
                    : 'failed',
                mb_substr(
                    $exception->getMessage(),
                    0,
                    5000
                ),
                $runId,
            ]);
        } catch (Throwable) {
        }

        throw $exception;
    }

    $finishedRun =
        llama_pad_us_run(
            $db,
            $runId
        )
        ?? [];

    return [
        'run_id' =>
            $runId,
        'state_code' =>
            $stateCode,
        'offset' =>
            $offset,
        'next_offset' =>
            (int) (
                $finishedRun[
                    'cursor_value'
                ]
                ?? (
                    $offset
                    + count(
                        $features
                    )
                )
            ),
        'source_rows' =>
            (int) (
                $finishedRun[
                    'source_rows'
                ]
                ?? 0
            ),
        'rows_processed' =>
            (int) (
                $finishedRun[
                    'rows_processed'
                ]
                ?? 0
            ),
        'locations_created' =>
            (int) (
                $finishedRun[
                    'locations_created'
                ]
                ?? 0
            ),
        'locations_updated' =>
            (int) (
                $finishedRun[
                    'locations_updated'
                ]
                ?? 0
            ),
        'organizations_created' =>
            (int) (
                $finishedRun[
                    'organizations_created'
                ]
                ?? 0
            ),
        'rows_skipped' =>
            (int) (
                $finishedRun[
                    'rows_skipped'
                ]
                ?? 0
            ),
        'warning_count' =>
            (int) (
                $finishedRun[
                    'warning_count'
                ]
                ?? 0
            ),
        'status' =>
            (string) (
                $finishedRun[
                    'status'
                ]
                ?? ''
            ),
        'done' =>
            (
                (string) (
                    $finishedRun[
                        'status'
                    ]
                    ?? ''
                )
            )
            === 'completed',
        'message' =>
            (string) (
                $finishedRun[
                    'last_message'
                ]
                ?? ''
            ),
    ];
}
