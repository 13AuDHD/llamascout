<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-mirror.php';

/**
 * Bounded, resumable local extraction of embedded campsite details.
 *
 * This file intentionally contains only the safe local campsite-extraction
 * helpers needed by admin/ridb.php. It does NOT contain any "run until
 * complete" loops or large all-in-one mirror operations.
 *
 * No RIDB API calls are made here. One invocation processes a small number
 * of already-mirrored campsite JSON rows and then exits.
 */

function llama_ridb_campsite_extract_embedded_list(
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

function llama_ridb_campsite_extract_record(
    PDO $ridbDb,
    array $site
): array {
    $siteId =
        llama_ridb_mirror_record_value(
            $site,
            [
                'CampsiteID',
                'campsiteID',
                'campsite_id',
            ]
        );

    if ($siteId === '') {
        return [
            'attributes' => 0,
            'equipment' => 0,
            'media' => 0,
        ];
    }

    $attributes =
        llama_ridb_campsite_extract_embedded_list(
            $site,
            [
                'ATTRIBUTES',
                'Attributes',
                'attributes',
            ]
        );

    $equipment =
        llama_ridb_campsite_extract_embedded_list(
            $site,
            [
                'PERMITTEDEQUIPMENT',
                'PermittedEquipment',
                'permittedEquipment',
            ]
        );

    $media =
        llama_ridb_campsite_extract_embedded_list(
            $site,
            [
                'ENTITYMEDIA',
                'EntityMedia',
                'entityMedia',
            ]
        );

    foreach ($media as &$item) {
        if (!is_array($item)) {
            continue;
        }

        if (
            llama_ridb_mirror_record_value(
                $item,
                [
                    'EntityID',
                    'entityID',
                    'entity_id',
                ]
            ) === ''
        ) {
            $item['EntityID'] =
                $siteId;
        }

        if (
            llama_ridb_mirror_record_value(
                $item,
                [
                    'EntityType',
                    'entityType',
                    'entity_type',
                ]
            ) === ''
        ) {
            $item['EntityType'] =
                'Campsite';
        }
    }
    unset($item);

    $endpointBase =
        '/campsites/'
        . rawurlencode($siteId);

    return [
        'attributes' =>
            llama_ridb_mirror_store_records(
                $ridbDb,
                'campsite_attributes',
                $endpointBase
                    . '#embedded-attributes',
                $attributes,
                [
                    'AttributeID',
                    'EntityAttributeID',
                    'attributeID',
                ],
                $siteId,
                'Campsite'
            ),

        'equipment' =>
            llama_ridb_mirror_store_records(
                $ridbDb,
                'campsite_permitted_equipment',
                $endpointBase
                    . '#embedded-permitted-equipment',
                $equipment,
                [],
                $siteId,
                'Campsite'
            ),

        'media' =>
            llama_ridb_mirror_store_records(
                $ridbDb,
                'media',
                $endpointBase
                    . '#embedded-media',
                $media,
                [
                    'EntityMediaID',
                    'MediaID',
                    'entityMediaID',
                ],
                $siteId,
                'Campsite'
            ),
    ];
}

function llama_ridb_campsite_extract_detail_counts(
    PDO $ridbDb
): array {
    $counts = [
        'campsite_attributes' => 0,
        'campsite_permitted_equipment' => 0,
        'campsite_media' => 0,
    ];

    foreach (
        [
            'campsite_attributes',
            'campsite_permitted_equipment',
        ]
        as $feed
    ) {
        $stmt =
            $ridbDb->prepare(
                'SELECT COUNT(*)
                 FROM ridb_raw_records
                 WHERE feed = ?'
            );

        $stmt->execute([
            $feed,
        ]);

        $counts[$feed] =
            (int) $stmt->fetchColumn();
    }

    $counts['campsite_media'] =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_raw_records
                 WHERE feed = "media"
                   AND LOWER(entity_type) = "campsite"'
            )
            ->fetchColumn();

    return $counts;
}

function llama_ridb_campsite_extract_feed(): string
{
    return 'campsite_embedded_extract';
}

function llama_ridb_campsite_extract_state(
    PDO $ridbDb
): array {
    return llama_ridb_mirror_sync_state(
        $ridbDb,
        llama_ridb_campsite_extract_feed(),
        'local:ridb_raw_records/campsites'
    );
}

function llama_ridb_campsite_extract_counts(
    PDO $ridbDb
): array {
    $detailCounts =
        llama_ridb_campsite_extract_detail_counts(
            $ridbDb
        );

    $totalCampsites =
        (int) $ridbDb
            ->query(
                'SELECT COUNT(*)
                 FROM ridb_raw_records
                 WHERE feed = "campsites"'
            )
            ->fetchColumn();

    $maxId =
        (int) $ridbDb
            ->query(
                'SELECT COALESCE(MAX(id), 0)
                 FROM ridb_raw_records
                 WHERE feed = "campsites"'
            )
            ->fetchColumn();

    return [
        'total_campsites' =>
            $totalCampsites,

        'max_raw_id' =>
            $maxId,

        'attributes' =>
            (int) (
                $detailCounts[
                    'campsite_attributes'
                ]
                ?? 0
            ),

        'equipment' =>
            (int) (
                $detailCounts[
                    'campsite_permitted_equipment'
                ]
                ?? 0
            ),

        'media' =>
            (int) (
                $detailCounts[
                    'campsite_media'
                ]
                ?? 0
            ),
    ];
}

function llama_ridb_campsite_extract_reset(
    PDO $ridbDb
): void {
    $feed =
        llama_ridb_campsite_extract_feed();

    /*
     * Ensure the synthetic state row exists before updating it.
     */
    llama_ridb_campsite_extract_state(
        $ridbDb
    );

    $stmt =
        $ridbDb->prepare(
            'UPDATE ridb_mirror_sync_state
             SET
                endpoint = ?,
                next_offset = 0,
                complete = 0,
                total_seen = 0,
                total_stored = 0,
                last_batch_count = 0,
                started_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE feed = ?'
        );

    $stmt->execute([
        'local:ridb_raw_records/campsites',
        $feed,
    ]);
}

function llama_ridb_campsite_extract_batch(
    PDO $ridbDb,
    int $batchSize = 100
): array {
    $batchSize =
        max(
            10,
            min(
                100,
                $batchSize
            )
        );

    $lockName =
        'llama_ridb_campsite_extract';

    $lockStmt =
        $ridbDb->prepare(
            'SELECT GET_LOCK(?, 0)'
        );

    $lockStmt->execute([
        $lockName,
    ]);

    $gotLock =
        (int) $lockStmt
            ->fetchColumn()
        === 1;

    if (!$gotLock) {
        throw new RuntimeException(
            'Another campsite extraction batch is already running.'
        );
    }

    try {
        $state =
            llama_ridb_campsite_extract_state(
                $ridbDb
            );

        if (
            (int) (
                $state['complete']
                ?? 0
            ) === 1
        ) {
            return [
                'seen' => 0,
                'attributes' => 0,
                'equipment' => 0,
                'media' => 0,
                'next_offset' =>
                    (int) (
                        $state[
                            'next_offset'
                        ]
                        ?? 0
                    ),
                'complete' => true,
            ];
        }

        $cursor =
            (int) (
                $state['next_offset']
                ?? 0
            );

        $stmt =
            $ridbDb->prepare(
                'SELECT
                    id,
                    source_json
                 FROM ridb_raw_records
                 WHERE feed = ?
                   AND id > ?
                 ORDER BY id ASC
                 LIMIT '
                . $batchSize
            );

        $stmt->execute([
            'campsites',
            $cursor,
        ]);

        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        $seen = 0;
        $attributes = 0;
        $equipment = 0;
        $media = 0;
        $lastId = $cursor;

        foreach ($rows as $row) {
            $lastId =
                (int) (
                    $row['id']
                    ?? $lastId
                );

            $site =
                json_decode(
                    (string) (
                        $row['source_json']
                        ?? ''
                    ),
                    true
                );

            if (!is_array($site)) {
                continue;
            }

            $result =
                llama_ridb_campsite_extract_record(
                    $ridbDb,
                    $site
                );

            $seen++;

            $attributes +=
                (int) (
                    $result['attributes']
                    ?? 0
                );

            $equipment +=
                (int) (
                    $result['equipment']
                    ?? 0
                );

            $media +=
                (int) (
                    $result['media']
                    ?? 0
                );
        }

        $complete =
            count($rows)
            < $batchSize;

        $stored =
            $attributes
            + $equipment
            + $media;

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
            'local:ridb_raw_records/campsites',
            $lastId,
            $complete
                ? 1
                : 0,
            $seen,
            $stored,
            $seen,
            llama_ridb_campsite_extract_feed(),
        ]);

        llama_ridb_log_sync_run(
            $ridbDb,
            'mirror_campsite_embedded_extract',
            $complete
                ? 'success'
                : 'partial',
            $seen,
            $stored,
            'Processed '
                . $seen
                . ' cached campsite records. Cursor '
                . $lastId
                . (
                    $complete
                        ? '; local extraction complete.'
                        : '; more cached campsites remain.'
                )
        );

        return [
            'seen' => $seen,
            'attributes' => $attributes,
            'equipment' => $equipment,
            'media' => $media,
            'next_offset' => $lastId,
            'complete' => $complete,
        ];

    } finally {
        try {
            $release =
                $ridbDb->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );

            $release->execute([
                $lockName,
            ]);
        } catch (Throwable) {
        }
    }
}
