<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb.php';

/**
 * Lossless RIDB mirror.
 *
 * Existing normalized RIDB tables remain the fast/indexed working cache.
 * This mirror keeps the complete JSON payload for the rest of the useful
 * RIDB API so Llama Scout can normalize additional fields later without
 * having to rediscover or re-download the source record.
 */

function llama_ridb_mirror_feeds(): array
{
    return [
        'organizations' => [
            'label' => 'Organizations',
            'endpoint' => '/organizations',
            'id_keys' => ['OrgID', 'OrganizationID', 'organizationID'],
        ],
        'recreation_areas' => [
            'label' => 'Recreation areas',
            'endpoint' => '/recareas',
            'id_keys' => ['RecAreaID', 'recAreaID', 'recarea_id'],
        ],
        'recreation_area_addresses' => [
            'label' => 'Recreation area addresses',
            'endpoint' => '/recareaaddresses',
            'id_keys' => ['RecAreaAddressID', 'recAreaAddressID'],
        ],
        'facility_addresses' => [
            'label' => 'Facility addresses',
            'endpoint' => '/facilityaddresses',
            'id_keys' => ['FacilityAddressID', 'facilityAddressID'],
        ],
        'activities' => [
            'label' => 'Activities',
            'endpoint' => '/activities',
            'id_keys' => ['ActivityID', 'activityID'],
        ],
        'links' => [
            'label' => 'Links',
            'endpoint' => '/links',
            'id_keys' => ['LinkID', 'linkID'],
        ],
        'media' => [
            'label' => 'Media',
            'endpoint' => '/media',
            'id_keys' => ['EntityMediaID', 'MediaID', 'entityMediaID'],
        ],
        'campsites' => [
            'label' => 'Campsites',
            'endpoint' => '/campsites',
            'id_keys' => ['CampsiteID', 'campsiteID', 'campsite_id'],
        ],
        'events' => [
            'label' => 'Events',
            'endpoint' => '/events',
            'id_keys' => ['EventID', 'eventID'],
        ],
        'permit_entrances' => [
            'label' => 'Permit entrances',
            'endpoint' => '/permitentrances',
            'id_keys' => ['PermitEntranceID', 'permitEntranceID'],
        ],
        'tours' => [
            'label' => 'Tours',
            'endpoint' => '/tours',
            'id_keys' => ['TourID', 'tourID'],
        ],
    ];
}

function llama_ridb_mirror_child_feeds(): array
{
    return [
        'campsite_attributes' => [
            'label' => 'Campsite attributes',
            'parent_feed' => 'campsites',
            'path' => '/campsites/%s/attributes',
            'id_keys' => [
                'AttributeID',
                'EntityAttributeID',
                'attributeID',
            ],
        ],
        'permit_entrance_attributes' => [
            'label' => 'Permit entrance attributes',
            'parent_feed' => 'permit_entrances',
            'path' => '/permitentrances/%s/attributes',
            'id_keys' => [
                'AttributeID',
                'EntityAttributeID',
                'attributeID',
            ],
        ],
        'permit_entrance_zones' => [
            'label' => 'Permit entrance zones',
            'parent_feed' => 'permit_entrances',
            'path' => '/permitentrances/%s/zones',
            'id_keys' => ['ZoneID', 'zoneID'],
        ],
        'tour_attributes' => [
            'label' => 'Tour attributes',
            'parent_feed' => 'tours',
            'path' => '/tours/%s/attributes',
            'id_keys' => [
                'AttributeID',
                'EntityAttributeID',
                'attributeID',
            ],
        ],
    ];
}

function llama_ridb_mirror_record_value(
    array $record,
    array $keys
): string {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $record)) {
            continue;
        }

        $value = trim((string) $record[$key]);

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function llama_ridb_mirror_record_id(
    array $record,
    array $idKeys
): string {
    $sourceId =
        llama_ridb_mirror_record_value(
            $record,
            $idKeys
        );

    if ($sourceId !== '') {
        return $sourceId;
    }

    return
        'sha256:'
        . hash(
            'sha256',
            json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
        );
}

function llama_ridb_mirror_relationship(
    array $record,
    string $feed
): array {
    if ($feed === 'media') {
        $entityId =
            llama_ridb_mirror_record_value(
                $record,
                ['EntityID', 'entityID', 'entity_id']
            );

        $entityType =
            llama_ridb_mirror_record_value(
                $record,
                ['EntityType', 'entityType', 'entity_type']
            );

        return [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'parent_id' => $entityId,
            'parent_type' => $entityType,
        ];
    }

    $parentCandidates = [
        ['FacilityID', 'facilityID', 'facility_id', 'Facility'],
        ['RecAreaID', 'recAreaID', 'recarea_id', 'RecreationArea'],
        ['OrgID', 'OrganizationID', 'organizationID', 'Organization'],
        ['PermitEntranceID', 'permitEntranceID', 'PermitEntrance'],
        ['TourID', 'tourID', 'Tour'],
    ];

    foreach ($parentCandidates as $candidate) {
        [$a, $b, $c, $type] = $candidate;

        $value =
            llama_ridb_mirror_record_value(
                $record,
                [$a, $b, $c]
            );

        if ($value !== '') {
            return [
                'entity_id' => '',
                'entity_type' => '',
                'parent_id' => $value,
                'parent_type' => $type,
            ];
        }
    }

    return [
        'entity_id' => '',
        'entity_type' => '',
        'parent_id' => '',
        'parent_type' => '',
    ];
}

function llama_ridb_mirror_store_records(
    PDO $ridbDb,
    string $feed,
    string $endpoint,
    array $records,
    array $idKeys,
    string $forcedParentId = '',
    string $forcedParentType = ''
): int {
    if (!$records) {
        return 0;
    }

    $stmt =
        $ridbDb->prepare(
            'INSERT INTO ridb_raw_records
            (
                feed,
                source_record_id,
                source_endpoint,
                entity_id,
                entity_type,
                parent_id,
                parent_type,
                source_json,
                record_hash,
                first_seen_at,
                last_seen_at
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                source_endpoint = VALUES(source_endpoint),
                entity_id = VALUES(entity_id),
                entity_type = VALUES(entity_type),
                parent_type = VALUES(parent_type),
                source_json = VALUES(source_json),
                record_hash = VALUES(record_hash),
                last_seen_at = UTC_TIMESTAMP()'
        );

    $stored = 0;

    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }

        $sourceId =
            llama_ridb_mirror_record_id(
                $record,
                $idKeys
            );

        $relationship =
            llama_ridb_mirror_relationship(
                $record,
                $feed
            );

        if ($forcedParentId !== '') {
            $relationship['parent_id'] =
                $forcedParentId;

            $relationship['parent_type'] =
                $forcedParentType;
        }

        $json =
            json_encode(
                $record,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

        $stmt->execute([
            $feed,
            $sourceId,
            $endpoint,
            (string) $relationship['entity_id'],
            (string) $relationship['entity_type'],
            (string) $relationship['parent_id'],
            (string) $relationship['parent_type'],
            $json,
            hash('sha256', $json),
        ]);

        $stored++;
    }

    return $stored;
}

function llama_ridb_mirror_sync_state(
    PDO $ridbDb,
    string $feed,
    string $endpoint
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT
                feed,
                endpoint,
                next_offset,
                complete,
                total_seen,
                total_stored,
                last_batch_count,
                started_at,
                updated_at
             FROM ridb_mirror_sync_state
             WHERE feed = ?
             LIMIT 1'
        );

    $stmt->execute([$feed]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return $row;
    }

    $insert =
        $ridbDb->prepare(
            'INSERT INTO ridb_mirror_sync_state
            (
                feed,
                endpoint,
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
                ?, ?, 0, 0, 0, 0, 0,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );

    $insert->execute([
        $feed,
        $endpoint,
    ]);

    return [
        'feed' => $feed,
        'endpoint' => $endpoint,
        'next_offset' => 0,
        'complete' => 0,
        'total_seen' => 0,
        'total_stored' => 0,
        'last_batch_count' => 0,
        'started_at' => null,
        'updated_at' => null,
    ];
}

function llama_ridb_mirror_reset_feed(
    PDO $ridbDb,
    string $feed
): void {
    $feeds =
        llama_ridb_mirror_feeds();

    if (!isset($feeds[$feed])) {
        throw new InvalidArgumentException(
            'Unknown RIDB mirror feed.'
        );
    }

    $stmt =
        $ridbDb->prepare(
            'INSERT INTO ridb_mirror_sync_state
            (
                feed,
                endpoint,
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
                ?, ?, 0, 0, 0, 0, 0,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )
            ON DUPLICATE KEY UPDATE
                endpoint = VALUES(endpoint),
                next_offset = 0,
                complete = 0,
                total_seen = 0,
                total_stored = 0,
                last_batch_count = 0,
                started_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()'
        );

    $stmt->execute([
        $feed,
        $feeds[$feed]['endpoint'],
    ]);
}

function llama_ridb_mirror_sync_feed_batch(
    PDO $ridbDb,
    string $feed,
    int $maxRecords = 500
): array {
    $feeds =
        llama_ridb_mirror_feeds();

    if (!isset($feeds[$feed])) {
        throw new InvalidArgumentException(
            'Unknown RIDB mirror feed.'
        );
    }

    $definition =
        $feeds[$feed];

    $state =
        llama_ridb_mirror_sync_state(
            $ridbDb,
            $feed,
            $definition['endpoint']
        );

    if ((int) ($state['complete'] ?? 0) === 1) {
        return [
            'feed' => $feed,
            'seen' => 0,
            'stored' => 0,
            'next_offset' =>
                (int) ($state['next_offset'] ?? 0),
            'complete' => true,
        ];
    }

    $maxRecords =
        max(
            50,
            min(5000, $maxRecords)
        );

    $limit = 50;
    $offset =
        (int) ($state['next_offset'] ?? 0);

    $seen = 0;
    $stored = 0;
    $complete = false;

    while ($seen < $maxRecords) {
        $response =
            llama_ridb_request(
                $definition['endpoint'],
                [
                    'limit' => $limit,
                    'offset' => $offset,
                ]
            );

        $records =
            llama_ridb_response_records(
                $response
            );

        $count = count($records);

        if ($count === 0) {
            $complete = true;
            break;
        }

        $stored +=
            llama_ridb_mirror_store_records(
                $ridbDb,
                $feed,
                $definition['endpoint'],
                $records,
                $definition['id_keys']
            );

        /*
         * Keep the existing normalized working tables useful while the
         * lossless mirror grows.
         */
        if ($feed === 'campsites') {
            $byFacility = [];

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $facilityId =
                    llama_ridb_mirror_record_value(
                        $record,
                        [
                            'FacilityID',
                            'facilityID',
                            'facility_id',
                        ]
                    );

                if ($facilityId === '') {
                    continue;
                }

                $byFacility[$facilityId][] =
                    $record;
            }

            foreach ($byFacility as $facilityId => $sites) {
                llama_ridb_store_campsites(
                    $ridbDb,
                    (string) $facilityId,
                    $sites
                );
            }
        }

        $seen += $count;
        $offset += $count;

        if ($count < $limit) {
            $complete = true;
            break;
        }
    }

    $update =
        $ridbDb->prepare(
            'UPDATE ridb_mirror_sync_state
             SET
                endpoint = ?,
                next_offset = ?,
                complete = ?,
                total_seen = total_seen + ?,
                total_stored = total_stored + ?,
                last_batch_count = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE feed = ?'
        );

    $update->execute([
        $definition['endpoint'],
        $offset,
        $complete ? 1 : 0,
        $seen,
        $stored,
        $seen,
        $feed,
    ]);

    llama_ridb_log_sync_run(
        $ridbDb,
        'mirror_' . $feed,
        $complete ? 'success' : 'partial',
        $seen,
        $stored,
        $definition['label']
            . ' mirror offset now '
            . $offset
            . (
                $complete
                    ? '; synchronization complete'
                    : '; more records remain'
            )
    );

    return [
        'feed' => $feed,
        'seen' => $seen,
        'stored' => $stored,
        'next_offset' => $offset,
        'complete' => $complete,
    ];
}

function llama_ridb_mirror_parent_ids(
    PDO $ridbDb,
    string $parentFeed
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT source_record_id
             FROM ridb_raw_records
             WHERE feed = ?
             ORDER BY source_record_id ASC'
        );

    $stmt->execute([$parentFeed]);

    return
        array_values(
            array_filter(
                array_map(
                    'strval',
                    $stmt->fetchAll(
                        PDO::FETCH_COLUMN
                    )
                    ?: []
                )
            )
        );
}

function llama_ridb_mirror_child_state(
    PDO $ridbDb,
    string $feed,
    string $endpointPattern
): array {
    return
        llama_ridb_mirror_sync_state(
            $ridbDb,
            $feed,
            $endpointPattern
        );
}

function llama_ridb_mirror_sync_child_batch(
    PDO $ridbDb,
    string $feed,
    int $maxParents = 50
): array {
    $feeds =
        llama_ridb_mirror_child_feeds();

    if (!isset($feeds[$feed])) {
        throw new InvalidArgumentException(
            'Unknown RIDB child mirror feed.'
        );
    }

    $definition =
        $feeds[$feed];

    $parentIds =
        llama_ridb_mirror_parent_ids(
            $ridbDb,
            $definition['parent_feed']
        );

    $state =
        llama_ridb_mirror_child_state(
            $ridbDb,
            $feed,
            $definition['path']
        );

    $offset =
        (int) ($state['next_offset'] ?? 0);

    if ((int) ($state['complete'] ?? 0) === 1) {
        return [
            'feed' => $feed,
            'parents_seen' => 0,
            'stored' => 0,
            'next_offset' => $offset,
            'complete' => true,
        ];
    }

    $maxParents =
        max(
            1,
            min(250, $maxParents)
        );

    $slice =
        array_slice(
            $parentIds,
            $offset,
            $maxParents
        );

    $parentsSeen = 0;
    $stored = 0;

    foreach ($slice as $parentId) {
        $endpoint =
            sprintf(
                $definition['path'],
                rawurlencode($parentId)
            );

        $response =
            llama_ridb_fetch_all(
                $endpoint,
                [],
                1000
            );

        $records =
            $response['records']
            ?? [];

        $stored +=
            llama_ridb_mirror_store_records(
                $ridbDb,
                $feed,
                $endpoint,
                $records,
                $definition['id_keys'],
                $parentId,
                $definition['parent_feed']
            );

        $parentsSeen++;
    }

    $offset += $parentsSeen;

    $complete =
        $offset >= count($parentIds);

    $update =
        $ridbDb->prepare(
            'UPDATE ridb_mirror_sync_state
             SET
                endpoint = ?,
                next_offset = ?,
                complete = ?,
                total_seen = total_seen + ?,
                total_stored = total_stored + ?,
                last_batch_count = ?,
                updated_at = UTC_TIMESTAMP()
             WHERE feed = ?'
        );

    $update->execute([
        $definition['path'],
        $offset,
        $complete ? 1 : 0,
        $parentsSeen,
        $stored,
        $parentsSeen,
        $feed,
    ]);

    llama_ridb_log_sync_run(
        $ridbDb,
        'mirror_' . $feed,
        $complete ? 'success' : 'partial',
        $parentsSeen,
        $stored,
        $definition['label']
            . ': processed '
            . $offset
            . ' of '
            . count($parentIds)
            . ' parent records'
    );

    return [
        'feed' => $feed,
        'parents_seen' => $parentsSeen,
        'stored' => $stored,
        'next_offset' => $offset,
        'complete' => $complete,
        'parent_total' => count($parentIds),
    ];
}

function llama_ridb_mirror_counts(
    PDO $ridbDb
): array {
    $rows =
        $ridbDb
            ->query(
                'SELECT
                    feed,
                    COUNT(*) AS record_count
                 FROM ridb_raw_records
                 GROUP BY feed
                 ORDER BY feed ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC)
        ?: [];

    $counts = [];

    foreach ($rows as $row) {
        $counts[(string) $row['feed']] =
            (int) $row['record_count'];
    }

    return $counts;
}

function llama_ridb_mirror_media_for_entity(
    PDO $ridbDb,
    string $entityType,
    string $entityId
): array {
    $stmt =
        $ridbDb->prepare(
            'SELECT
                source_record_id,
                source_json,
                last_seen_at
             FROM ridb_raw_records
             WHERE feed = ?
               AND LOWER(entity_type) = LOWER(?)
               AND entity_id = ?
             ORDER BY source_record_id ASC'
        );

    $stmt->execute([
        'media',
        trim($entityType),
        trim($entityId),
    ]);

    $records = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: []
        as $row
    ) {
        $decoded =
            json_decode(
                (string) $row['source_json'],
                true
            );

        if (is_array($decoded)) {
            $records[] = $decoded;
        }
    }

    return $records;
}
