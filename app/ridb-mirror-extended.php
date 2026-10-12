<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-mirror.php';

/**
 * Finish a global mirror feed without requiring repeated button presses.
 * Existing llama_ridb_mirror_sync_feed_batch() remains the checkpointing
 * primitive, so progress is saved after every 5,000-record pass.
 */
function llama_ridb_mirror_sync_feed_until_complete(
    PDO $ridbDb,
    string $feed
): array {
    $totalSeen = 0;
    $totalStored = 0;
    $passes = 0;
    $lastOffset = -1;

    do {
        $result =
            llama_ridb_mirror_sync_feed_batch(
                $ridbDb,
                $feed,
                5000
            );

        $passes++;
        $totalSeen +=
            (int) ($result['seen'] ?? 0);
        $totalStored +=
            (int) ($result['stored'] ?? 0);

        $offset =
            (int) ($result['next_offset'] ?? 0);

        $complete =
            !empty($result['complete']);

        if (
            !$complete
            && $offset === $lastOffset
        ) {
            throw new RuntimeException(
                'RIDB mirror made no forward progress for '
                . $feed
                . '. The saved offset was not advanced.'
            );
        }

        $lastOffset = $offset;
    } while (!$complete);

    return [
        'feed' => $feed,
        'seen' => $totalSeen,
        'stored' => $totalStored,
        'passes' => $passes,
        'next_offset' => $lastOffset,
        'complete' => true,
    ];
}

/**
 * Finish every incomplete global mirror feed in one admin action.
 */
function llama_ridb_mirror_sync_all_until_complete(
    PDO $ridbDb
): array {
    $results = [];

    foreach (
        llama_ridb_mirror_feeds()
        as $feed => $definition
    ) {
        $state =
            llama_ridb_mirror_sync_state(
                $ridbDb,
                $feed,
                $definition['endpoint']
            );

        if (
            (int) ($state['complete'] ?? 0)
            === 1
        ) {
            continue;
        }

        $results[$feed] =
            llama_ridb_mirror_sync_feed_until_complete(
                $ridbDb,
                $feed
            );
    }

    return $results;
}

/**
 * The full RIDB Campsite model already embeds ATTRIBUTES,
 * PERMITTEDEQUIPMENT, and ENTITYMEDIA. Extract those records from the
 * campsite JSON we have already mirrored instead of making one HTTP request
 * per campsite for data RIDB already sent us.
 */
function llama_ridb_mirror_embedded_list(
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
            return
                array_values(
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

function llama_ridb_mirror_extract_campsite_record(
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
        llama_ridb_mirror_embedded_list(
            $site,
            [
                'ATTRIBUTES',
                'Attributes',
                'attributes',
            ]
        );

    $equipment =
        llama_ridb_mirror_embedded_list(
            $site,
            [
                'PERMITTEDEQUIPMENT',
                'PermittedEquipment',
                'permittedEquipment',
            ]
        );

    $media =
        llama_ridb_mirror_embedded_list(
            $site,
            [
                'ENTITYMEDIA',
                'EntityMedia',
                'entityMedia',
            ]
        );

    /*
     * Some RIDB media payloads already contain EntityID / EntityType.
     * Fill them when omitted so the media remains directly queryable as
     * campsite media in our lossless mirror.
     */
    foreach ($media as &$item) {
        if (!is_array($item)) {
            continue;
        }

        if (
            llama_ridb_mirror_record_value(
                $item,
                ['EntityID', 'entityID', 'entity_id']
            ) === ''
        ) {
            $item['EntityID'] = $siteId;
        }

        if (
            llama_ridb_mirror_record_value(
                $item,
                ['EntityType', 'entityType', 'entity_type']
            ) === ''
        ) {
            $item['EntityType'] = 'Campsite';
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

/**
 * Backfill embedded campsite details from already cached campsite JSON.
 * This is entirely local database work and does not make RIDB API requests.
 */
function llama_ridb_mirror_extract_cached_campsite_details(
    PDO $ridbDb,
    int $chunkSize = 1000
): array {
    $chunkSize =
        max(
            100,
            min(5000, $chunkSize)
        );

    $lastId = 0;
    $sites = 0;
    $attributes = 0;
    $equipment = 0;
    $media = 0;

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
            . $chunkSize
        );

    do {
        $stmt->execute([
            'campsites',
            $lastId,
        ]);

        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];

        foreach ($rows as $row) {
            $lastId =
                (int) $row['id'];

            $site =
                json_decode(
                    (string) $row['source_json'],
                    true
                );

            if (!is_array($site)) {
                continue;
            }

            $result =
                llama_ridb_mirror_extract_campsite_record(
                    $ridbDb,
                    $site
                );

            $sites++;
            $attributes +=
                (int) $result['attributes'];
            $equipment +=
                (int) $result['equipment'];
            $media +=
                (int) $result['media'];
        }
    } while (count($rows) === $chunkSize);

    llama_ridb_log_sync_run(
        $ridbDb,
        'mirror_campsite_embedded_backfill',
        'success',
        $sites,
        $attributes
            + $equipment
            + $media,
        'Extracted embedded campsite details from '
            . $sites
            . ' cached campsite records.'
    );

    return [
        'sites' => $sites,
        'attributes' => $attributes,
        'equipment' => $equipment,
        'media' => $media,
    ];
}

/**
 * For new campsite pages arriving during an ordinary /campsites mirror sync,
 * extract the embedded details immediately. This can be called after the
 * global campsite feed finishes to make sure the cached mirror is complete.
 */
function llama_ridb_mirror_campsite_detail_counts(
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

        $stmt->execute([$feed]);

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

/**
 * Campsite attributes no longer need an HTTP request for every campsite when
 * they were already included in the mirrored campsite payload. Keep the
 * original child-feed machinery for permit entrances and tours.
 */
function llama_ridb_mirror_recommended_child_feeds(): array
{
    $feeds =
        llama_ridb_mirror_child_feeds();

    unset(
        $feeds['campsite_attributes']
    );

    return $feeds;
}

function llama_ridb_mirror_sync_child_until_complete(
    PDO $ridbDb,
    string $feed
): array {
    $totalParents = 0;
    $totalStored = 0;
    $passes = 0;
    $lastOffset = -1;

    do {
        $result =
            llama_ridb_mirror_sync_child_batch(
                $ridbDb,
                $feed,
                250
            );

        $passes++;
        $totalParents +=
            (int) (
                $result['parents_seen']
                ?? 0
            );

        $totalStored +=
            (int) (
                $result['stored']
                ?? 0
            );

        $offset =
            (int) (
                $result['next_offset']
                ?? 0
            );

        $complete =
            !empty(
                $result['complete']
            );

        if (
            !$complete
            && $offset === $lastOffset
        ) {
            throw new RuntimeException(
                'RIDB child mirror made no forward progress for '
                . $feed
                . '.'
            );
        }

        $lastOffset = $offset;
    } while (!$complete);

    return [
        'feed' => $feed,
        'parents_seen' => $totalParents,
        'stored' => $totalStored,
        'passes' => $passes,
        'next_offset' => $lastOffset,
        'complete' => true,
    ];
}
