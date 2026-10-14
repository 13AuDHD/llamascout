<?php

declare(strict_types=1);

/**
 * Production/RIDB reconciliation.
 *
 * This table lives in the dedicated RIDB database. It is a disposable index
 * of how RIDB facilities relate to Llama Scout Places. RIDB remains the
 * source archive and place_external_sources remains the production authority.
 */

function llama_ridb_status_reconcile(
    PDO $ridbDb,
    PDO $mainDb
): array {
    $rows =
        $mainDb
            ->query(
                'SELECT
                    pes.source_external_id,
                    pes.place_id,
                    pes.source_updated_at,
                    pes.synced_at,
                    p.name,
                    p.slug,
                    p.status
                 FROM place_external_sources pes
                 INNER JOIN places p
                    ON p.id = pes.place_id
                 WHERE pes.source_provider = "ridb"
                   AND pes.source_type = "facility"
                 ORDER BY pes.source_external_id ASC'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

    $upsert =
        $ridbDb->prepare(
            'INSERT INTO ridb_llama_sync_status
            (
                ridb_facility_id,
                place_id,
                place_name,
                place_slug,
                place_status,
                production_source_updated_at,
                production_synced_at,
                ridb_source_updated_at,
                sync_status,
                checked_at
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                place_id = VALUES(place_id),
                place_name = VALUES(place_name),
                place_slug = VALUES(place_slug),
                place_status = VALUES(place_status),
                production_source_updated_at =
                    VALUES(production_source_updated_at),
                production_synced_at =
                    VALUES(production_synced_at),
                ridb_source_updated_at =
                    VALUES(ridb_source_updated_at),
                sync_status =
                    VALUES(sync_status),
                checked_at =
                    UTC_TIMESTAMP()'
        );

    $sourceStmt =
        $ridbDb->prepare(
            'SELECT
                last_updated_date
             FROM ridb_facilities
             WHERE ridb_facility_id = ?
             LIMIT 1'
        );

    $seen = [];
    $current = 0;
    $updated = 0;
    $missing = 0;

    foreach ($rows as $row) {
        $facilityId =
            trim(
                (string) (
                    $row[
                        'source_external_id'
                    ]
                    ?? ''
                )
            );

        if ($facilityId === '') {
            continue;
        }

        $seen[$facilityId] =
            true;

        $sourceStmt->execute([
            $facilityId,
        ]);

        $ridbUpdated =
            $sourceStmt->fetchColumn();

        $ridbUpdated =
            $ridbUpdated === false
                ? null
                : trim(
                    (string) $ridbUpdated
                );

        $productionUpdated =
            trim(
                (string) (
                    $row[
                        'source_updated_at'
                    ]
                    ?? ''
                )
            );

        if (
            $ridbUpdated === null
            || $ridbUpdated === ''
        ) {
            $status =
                'missing_source';

            $missing++;
        } elseif (
            $productionUpdated === ''
            || strtotime($ridbUpdated)
                > strtotime($productionUpdated)
        ) {
            $status =
                'source_updated';

            $updated++;
        } else {
            $status =
                'current';

            $current++;
        }

        $upsert->execute([
            $facilityId,
            (int) (
                $row['place_id']
                ?? 0
            ),
            (string) (
                $row['name']
                ?? ''
            ),
            (string) (
                $row['slug']
                ?? ''
            ),
            (string) (
                $row['status']
                ?? ''
            ),
            $productionUpdated !== ''
                ? $productionUpdated
                : null,
            trim(
                (string) (
                    $row['synced_at']
                    ?? ''
                )
            ) ?: null,
            $ridbUpdated,
            $status,
        ]);
    }

    /*
     * Remove stale index rows when a production source link no longer exists.
     * This changes only the disposable reconciliation index.
     */
    $indexed =
        $ridbDb
            ->query(
                'SELECT ridb_facility_id
                 FROM ridb_llama_sync_status'
            )
            ->fetchAll(
                PDO::FETCH_COLUMN
            )
            ?: [];

    if ($indexed) {
        $delete =
            $ridbDb->prepare(
                'DELETE FROM ridb_llama_sync_status
                 WHERE ridb_facility_id = ?'
            );

        foreach ($indexed as $facilityId) {
            $facilityId =
                trim(
                    (string) $facilityId
                );

            if (
                $facilityId !== ''
                && !isset(
                    $seen[$facilityId]
                )
            ) {
                $delete->execute([
                    $facilityId,
                ]);
            }
        }
    }

    return [
        'imported' =>
            count($seen),
        'current' =>
            $current,
        'source_updated' =>
            $updated,
        'missing_source' =>
            $missing,
    ];
}

function llama_ridb_status_counts(
    PDO $ridbDb
): array {
    $facilities =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_facilities'
            )
            ->fetchColumn();

    $imported =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_llama_sync_status'
            )
            ->fetchColumn();

    $sourceUpdated =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_llama_sync_status
                 WHERE sync_status = "source_updated"'
            )
            ->fetchColumn();

    $current =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_llama_sync_status
                 WHERE sync_status = "current"'
            )
            ->fetchColumn();

    $missingSource =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_llama_sync_status
                 WHERE sync_status = "missing_source"'
            )
            ->fetchColumn();

    return [
        'facilities' =>
            $facilities,
        'imported' =>
            $imported,
        'not_imported' =>
            max(
                0,
                $facilities - $imported
            ),
        'source_updated' =>
            $sourceUpdated,
        'current' =>
            $current,
        'missing_source' =>
            $missingSource,
    ];
}

function llama_ridb_status_filter(
    mixed $value
): string {
    $value =
        trim(
            (string) $value
        );

    return in_array(
        $value,
        [
            '',
            'not_imported',
            'imported',
            'source_updated',
            'current',
        ],
        true
    )
        ? $value
        : '';
}

function llama_ridb_status_catalog_facilities(
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

    $q =
        trim(
            (string) (
                $filters['q']
                ?? ''
            )
        );

    if ($q !== '') {
        $where[] =
            '(
                f.facility_name LIKE ?
                OR f.ridb_facility_id LIKE ?
                OR f.description LIKE ?
            )';

        $search =
            '%' . $q . '%';

        array_push(
            $params,
            $search,
            $search,
            $search
        );
    }

    $state =
        trim(
            (string) (
                $filters['state']
                ?? ''
            )
        );

    if ($state !== '') {
        $where[] =
            'f.state_code = ?';

        $params[] =
            $state;
    }

    $type =
        trim(
            (string) (
                $filters['type']
                ?? ''
            )
        );

    if ($type !== '') {
        $where[] =
            'f.facility_type_description = ?';

        $params[] =
            $type;
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

    $status =
        llama_ridb_status_filter(
            $filters['status']
            ?? ''
        );

    if ($status === 'not_imported') {
        $where[] =
            's.ridb_facility_id IS NULL';
    } elseif ($status === 'imported') {
        $where[] =
            's.ridb_facility_id IS NOT NULL';
    } elseif ($status === 'source_updated') {
        $where[] =
            's.sync_status = "source_updated"';
    } elseif ($status === 'current') {
        $where[] =
            's.sync_status = "current"';
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
             LEFT JOIN ridb_llama_sync_status s
               ON s.ridb_facility_id =
                    f.ridb_facility_id
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
                f.first_seen_at,
                f.last_seen_at,

                s.place_id,
                s.place_name,
                s.place_slug,
                s.place_status,
                s.production_source_updated_at,
                s.production_synced_at,
                s.ridb_source_updated_at,
                s.sync_status,
                s.checked_at

             FROM ridb_facilities f
             LEFT JOIN ridb_llama_sync_status s
               ON s.ridb_facility_id =
                    f.ridb_facility_id

             WHERE '
            . $whereSql
            . '

             ORDER BY
                CASE
                    WHEN s.sync_status =
                        "source_updated"
                        THEN 0
                    WHEN s.ridb_facility_id
                        IS NULL
                        THEN 1
                    ELSE 2
                END,
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

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    /*
     * Count campsites only for the facilities on the visible page. The former
     * correlated subquery could repeatedly probe ridb_campsites while sorting
     * the catalog.
     */
    $facilityIds =
        array_values(
            array_filter(
                array_map(
                    static fn (
                        array $row
                    ): string =>
                        trim(
                            (string) (
                                $row['ridb_facility_id']
                                ?? ''
                            )
                        ),
                    $rows
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        );

    $campsiteCounts = [];

    if ($facilityIds) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($facilityIds),
                    '?'
                )
            );

        $countVisible =
            $ridbDb->prepare(
                'SELECT
                    ridb_facility_id,
                    COUNT(*) AS campsite_count
                 FROM ridb_campsites
                 WHERE ridb_facility_id IN ('
                . $placeholders
                . ')
                 GROUP BY ridb_facility_id'
            );

        $countVisible->execute(
            $facilityIds
        );

        foreach (
            $countVisible->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: []
            as $countRow
        ) {
            $campsiteCounts[
                (string) (
                    $countRow['ridb_facility_id']
                    ?? ''
                )
            ] =
                (int) (
                    $countRow['campsite_count']
                    ?? 0
                );
        }
    }

    foreach ($rows as &$row) {
        $facilityId =
            (string) (
                $row['ridb_facility_id']
                ?? ''
            );

        $row['cached_campsite_count'] =
            $campsiteCounts[$facilityId]
            ?? 0;
    }
    unset($row);

    return [
        'rows' =>
            $rows,
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

function llama_ridb_status_catalog_url(
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
                'status' =>
                    $filters['status']
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
