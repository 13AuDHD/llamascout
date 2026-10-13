<?php

declare(strict_types=1);

require_once __DIR__ . '/ridb-mirror-extended.php';

/**
 * Bounded, resumable local extraction of embedded campsite details.
 *
 * No RIDB API calls are made here. One invocation processes a small number
 * of already-mirrored campsite JSON rows and then exits.
 */

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
        llama_ridb_mirror_campsite_detail_counts(
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

    $state =
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
        (int) $lockStmt->fetchColumn()
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
                llama_ridb_mirror_extract_campsite_record(
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
            $complete ? 1 : 0,
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
