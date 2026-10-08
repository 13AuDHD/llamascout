<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';

function llama_ridb_catalog_embedded_addresses(
    array $facility
): array {
    foreach (
        [
            'FACILITYADDRESS',
            'FacilityAddress',
            'facilityAddress',
            'facilityAddresses',
        ]
        as $key
    ) {
        $value =
            $facility[$key]
            ?? null;

        if (!is_array($value)) {
            continue;
        }

        if (
            isset($value[0])
            && is_array($value[0])
        ) {
            return
                array_values(
                    array_filter(
                        $value,
                        'is_array'
                    )
                );
        }

        if ($value) {
            return [
                $value,
            ];
        }
    }

    return [];
}

function llama_ridb_catalog_address_value(
    array $address,
    array $keys
): string {
    foreach ($keys as $key) {
        if (
            array_key_exists(
                $key,
                $address
            )
        ) {
            $value =
                trim(
                    (string) $address[$key]
                );

            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function llama_ridb_catalog_update_facility_index(
    PDO $ridbDb,
    array $facility
): void {
    $facilityId =
        trim(
            (string) llama_ridb_record_value(
                $facility,
                [
                    'FacilityID',
                    'facilityID',
                    'facility_id',
                ],
                ''
            )
        );

    if ($facilityId === '') {
        return;
    }

    $addresses =
        llama_ridb_catalog_embedded_addresses(
            $facility
        );

    $city = '';
    $state = '';

    if ($addresses) {
        $address =
            $addresses[0];

        $city =
            llama_ridb_catalog_address_value(
                $address,
                [
                    'City',
                    'city',
                ]
            );

        $state =
            strtoupper(
                llama_ridb_catalog_address_value(
                    $address,
                    [
                        'AddressStateCode',
                        'State',
                        'stateCode',
                    ]
                )
            );
    }

    $stmt =
        $ridbDb->prepare(
            'UPDATE ridb_facilities
             SET
                city = COALESCE(
                    NULLIF(?, ""),
                    city
                ),
                state_code = COALESCE(
                    NULLIF(?, ""),
                    state_code
                )
             WHERE ridb_facility_id = ?'
        );

    $stmt->execute([
        $city,
        $state,
        $facilityId,
    ]);

    if ($addresses) {
        llama_ridb_replace_related_records(
            $ridbDb,
            'ridb_facility_addresses',
            $facilityId,
            $addresses,
            'FacilityAddressID'
        );
    }
}

function llama_ridb_catalog_sync_state(
    PDO $ridbDb
): array {
    $row =
        $ridbDb
            ->query(
                'SELECT
                    id,
                    next_offset,
                    complete,
                    total_seen,
                    total_stored,
                    last_batch_count,
                    started_at,
                    updated_at
                 FROM ridb_catalog_sync_state
                 WHERE id = 1
                 LIMIT 1'
            )
            ->fetch(
                PDO::FETCH_ASSOC
            );

    if ($row) {
        return $row;
    }

    $ridbDb
        ->exec(
            'INSERT INTO ridb_catalog_sync_state
            (
                id,
                next_offset,
                complete,
                total_seen,
                total_stored,
                last_batch_count,
                started_at,
                updated_at
            )
            VALUES
            (
                1,
                0,
                0,
                0,
                0,
                0,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );

    return [
        'id' => 1,
        'next_offset' => 0,
        'complete' => 0,
        'total_seen' => 0,
        'total_stored' => 0,
        'last_batch_count' => 0,
        'started_at' => null,
        'updated_at' => null,
    ];
}

function llama_ridb_catalog_reset_sync(
    PDO $ridbDb
): void {
    $ridbDb
        ->exec(
            'INSERT INTO ridb_catalog_sync_state
            (
                id,
                next_offset,
                complete,
                total_seen,
                total_stored,
                last_batch_count,
                started_at,
                updated_at
            )
            VALUES
            (
                1,
                0,
                0,
                0,
                0,
                0,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                next_offset = 0,
                complete = 0,
                total_seen = 0,
                total_stored = 0,
                last_batch_count = 0,
                started_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()'
        );
}

function llama_ridb_catalog_sync_batch(
    PDO $ridbDb,
    int $maxRecords = 1000
): array {
    $maxRecords =
        max(
            50,
            min(
                5000,
                $maxRecords
            )
        );

    $state =
        llama_ridb_catalog_sync_state(
            $ridbDb
        );

    $offset =
        (int) (
            $state['next_offset']
            ?? 0
        );

    if (
        (int) (
            $state['complete']
            ?? 0
        ) === 1
    ) {
        return [
            'seen' => 0,
            'stored' => 0,
            'next_offset' =>
                $offset,
            'complete' => true,
        ];
    }

    $limit = 50;
    $seen = 0;
    $stored = 0;
    $complete = false;

    while ($seen < $maxRecords) {
        $response =
            llama_ridb_request(
                '/facilities',
                [
                    'limit' =>
                        $limit,

                    'offset' =>
                        $offset,
                ]
            );

        $records =
            llama_ridb_response_records(
                $response
            );

        $count =
            count($records);

        if ($count === 0) {
            $complete = true;
            break;
        }

        $stored +=
            llama_ridb_store_facilities(
                $ridbDb,
                $records
            );

        foreach ($records as $facility) {
            if (!is_array($facility)) {
                continue;
            }

            llama_ridb_catalog_update_facility_index(
                $ridbDb,
                $facility
            );
        }

        $seen +=
            $count;

        $offset +=
            $count;

        if ($count < $limit) {
            $complete = true;
            break;
        }

        if ($seen >= $maxRecords) {
            break;
        }
    }

    $stmt =
        $ridbDb->prepare(
            'UPDATE ridb_catalog_sync_state
             SET
                next_offset = ?,
                complete = ?,
                total_seen =
                    total_seen + ?,
                total_stored =
                    total_stored + ?,
                last_batch_count = ?,
                updated_at =
                    UTC_TIMESTAMP()
             WHERE id = 1'
        );

    $stmt->execute([
        $offset,
        $complete
            ? 1
            : 0,
        $seen,
        $stored,
        $seen,
    ]);

    llama_ridb_log_sync_run(
        $ridbDb,
        'facility_catalog_sync',
        $complete
            ? 'success'
            : 'partial',
        $seen,
        $stored,
        'Catalog offset now '
            . $offset
            . (
                $complete
                    ? '; synchronization complete'
                    : '; more facilities remain'
            )
    );

    return [
        'seen' =>
            $seen,

        'stored' =>
            $stored,

        'next_offset' =>
            $offset,

        'complete' =>
            $complete,
    ];
}

function llama_ridb_catalog_counts(
    PDO $ridbDb
): array {
    $tables = [
        'facilities' =>
            'ridb_facilities',

        'campsites' =>
            'ridb_campsites',

        'attributes' =>
            'ridb_campsite_attributes',
    ];

    $counts = [];

    foreach ($tables as $key => $table) {
        $counts[$key] =
            (int) $ridbDb
                ->query(
                    'SELECT COUNT(*)
                     FROM '
                    . $table
                )
                ->fetchColumn();
    }

    return $counts;
}

function llama_ridb_catalog_filter(
    array $input
): array {
    $reservable =
        trim(
            (string) (
                $input['reservable']
                ?? ''
            )
        );

    if (
        !in_array(
            $reservable,
            [
                '',
                'yes',
                'no',
            ],
            true
        )
    ) {
        $reservable = '';
    }

    return [
        'q' =>
            trim(
                (string) (
                    $input['q']
                    ?? ''
                )
            ),

        'state' =>
            strtoupper(
                trim(
                    (string) (
                        $input['state']
                        ?? ''
                    )
                )
            ),

        'type' =>
            trim(
                (string) (
                    $input['type']
                    ?? ''
                )
            ),

        'reservable' =>
            $reservable,

        'page' =>
            max(
                1,
                (int) (
                    $input['page']
                    ?? 1
                )
            ),
    ];
}

function llama_ridb_catalog_facilities(
    PDO $ridbDb,
    array $filters,
    int $pageSize = 50
): array {
    $pageSize =
        max(
            10,
            min(
                100,
                $pageSize
            )
        );

    $where = [
        '1 = 1',
    ];

    $params = [];

    if (
        trim(
            (string) (
                $filters['q']
                ?? ''
            )
        ) !== ''
    ) {
        $where[] =
            '(
                f.facility_name LIKE ?
                OR f.ridb_facility_id LIKE ?
                OR f.description LIKE ?
            )';

        $search =
            '%'
            . trim(
                (string) $filters['q']
            )
            . '%';

        array_push(
            $params,
            $search,
            $search,
            $search
        );
    }

    if (
        trim(
            (string) (
                $filters['state']
                ?? ''
            )
        ) !== ''
    ) {
        $where[] =
            'f.state_code = ?';

        $params[] =
            trim(
                (string) $filters['state']
            );
    }

    if (
        trim(
            (string) (
                $filters['type']
                ?? ''
            )
        ) !== ''
    ) {
        $where[] =
            'f.facility_type_description = ?';

        $params[] =
            trim(
                (string) $filters['type']
            );
    }

    if (
        ($filters['reservable'] ?? '')
        === 'yes'
    ) {
        $where[] =
            'f.reservable = 1';
    } elseif (
        ($filters['reservable'] ?? '')
        === 'no'
    ) {
        $where[] =
            '(f.reservable = 0
              OR f.reservable IS NULL)';
    }

    $whereSql =
        implode(
            ' AND ',
            $where
        );

    $countStmt =
        $ridbDb->prepare(
            'SELECT COUNT(*)
             FROM ridb_facilities f
             WHERE '
            . $whereSql
        );

    $countStmt->execute(
        $params
    );

    $total =
        (int) $countStmt
            ->fetchColumn();

    $pages =
        max(
            1,
            (int) ceil(
                $total
                / $pageSize
            )
        );

    $page =
        min(
            max(
                1,
                (int) (
                    $filters['page']
                    ?? 1
                )
            ),
            $pages
        );

    $offset =
        ($page - 1)
        * $pageSize;

    $stmt =
        $ridbDb->prepare(
            'SELECT
                f.ridb_facility_id,
                f.facility_name,
                f.facility_type_description,
                f.city,
                f.state_code,
                f.reservable,
                f.enabled,
                f.last_updated_date,
                f.last_seen_at,

                (
                    SELECT COUNT(*)
                    FROM ridb_campsites c
                    WHERE c.ridb_facility_id =
                        f.ridb_facility_id
                ) AS cached_campsite_count

             FROM ridb_facilities f

             WHERE '
            . $whereSql
            . '

             ORDER BY
                f.facility_name ASC,
                f.ridb_facility_id ASC

             LIMIT '
            . $pageSize
            . '

             OFFSET '
            . $offset
        );

    $stmt->execute(
        $params
    );

    return [
        'rows' =>
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [],

        'total' =>
            $total,

        'page' =>
            $page,

        'pages' =>
            $pages,

        'page_size' =>
            $pageSize,
    ];
}

function llama_ridb_catalog_distinct_types(
    PDO $ridbDb
): array {
    return
        $ridbDb
            ->query(
                'SELECT DISTINCT
                    facility_type_description
                 FROM ridb_facilities
                 WHERE facility_type_description
                    IS NOT NULL
                   AND facility_type_description <> ""
                 ORDER BY
                    facility_type_description ASC'
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            )
        ?: [];
}

function llama_ridb_catalog_distinct_states(
    PDO $ridbDb
): array {
    return
        $ridbDb
            ->query(
                'SELECT DISTINCT
                    state_code
                 FROM ridb_facilities
                 WHERE state_code IS NOT NULL
                   AND state_code <> ""
                 ORDER BY state_code ASC'
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            )
        ?: [];
}

function llama_ridb_catalog_imported_lookup(
    PDO $mainDb,
    array $facilityIds
): array {
    $facilityIds =
        array_values(
            array_filter(
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string) $value
                        ),
                    $facilityIds
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        );

    if (!$facilityIds) {
        return [];
    }

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($facilityIds),
                '?'
            )
        );

    $params = [
        'ridb',
        ...$facilityIds,
    ];

    $stmt =
        $mainDb->prepare(
            'SELECT
                pes.source_external_id,
                pes.place_id,
                pes.synced_at,
                p.slug,
                p.name,
                p.status
             FROM place_external_sources pes
             INNER JOIN places p
                ON p.id = pes.place_id
             WHERE pes.source_provider = ?
               AND pes.source_external_id IN ('
            . $placeholders
            . ')'
        );

    $stmt->execute(
        $params
    );

    $lookup = [];

    foreach (
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: []
        as $row
    ) {
        $lookup[
            (string) $row[
                'source_external_id'
            ]
        ] =
            $row;
    }

    return $lookup;
}

function llama_ridb_catalog_url(
    array $filters
): string {
    $query =
        array_filter(
            [
                'q' =>
                    $filters['q']
                    ?? '',

                'state' =>
                    $filters['state']
                    ?? '',

                'type' =>
                    $filters['type']
                    ?? '',

                'reservable' =>
                    $filters['reservable']
                    ?? '',

                'page' =>
                    (int) (
                        $filters['page']
                        ?? 1
                    ),
            ],
            static fn (
                mixed $value
            ): bool =>
                $value !== ''
                && $value !== null
        );

    return
        '/ridb.php'
        . (
            $query
                ? '?'
                    . http_build_query(
                        $query
                    )
                : ''
        );
}
