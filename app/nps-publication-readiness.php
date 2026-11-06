<?php

declare(strict_types=1);

/**
 * Read-only NPS -> RIDB -> published Place coverage and reviewed-field queue.
 *
 * All three systems remain independent:
 * 1. NPS source cache and human match/review events
 * 2. RIDB facility cache
 * 3. Canonical Llama Scout Places and their external source links
 *
 * This report never imports, edits, links, or publishes anything.
 */
function llama_nps_publication_readiness(
    PDO $npsDb,
    PDO $ridbDb,
    PDO $mainDb,
    int $maxPairs = 500
): array {
    if ($maxPairs < 1 || $maxPairs > 500) {
        throw new InvalidArgumentException('Choose a valid NPS coverage limit.');
    }
    $allCount = (int) $npsDb->query(
        "SELECT COUNT(*) FROM nps_raw_records WHERE resource_type = 'campgrounds'"
    )->fetchColumn();

    // Only the most recent human identity event is authoritative. This
    // includes stale/reopened identities so they are never silently omitted.
    $query = $npsDb->query(
        "SELECT n.park_code, n.record_id, n.record_name,
                n.source_hash, r.decision, r.ridb_facility_id,
                r.nps_source_hash
         FROM nps_raw_records n
         JOIN nps_ridb_review_events r
           ON r.nps_park_code = n.park_code
          AND r.nps_record_id = n.record_id
         LEFT JOIN nps_ridb_review_events newer
           ON newer.nps_park_code = r.nps_park_code
          AND newer.nps_record_id = r.nps_record_id
          AND newer.id > r.id
         WHERE n.resource_type = 'campgrounds'
           AND newer.id IS NULL
           AND r.decision = 'confirmed_match'
         ORDER BY n.park_code, n.record_name, n.record_id
         LIMIT " . ($maxPairs + 1)
    );
    $raw = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $truncated = count($raw) > $maxPairs;
    $raw = array_slice($raw, 0, $maxPairs);

    $loadPlace = $mainDb->prepare(
        "SELECT p.id, p.name FROM place_external_sources s
         JOIN places p ON p.id = s.place_id
         WHERE s.source_provider = 'ridb' AND s.source_external_id = ?
         LIMIT 2"
    );
    $loadNpsLink = $mainDb->prepare(
        "SELECT s.place_id FROM place_external_sources s
         WHERE s.source_provider = 'nps' AND s.source_external_id = ?
         LIMIT 2"
    );
    $loadRidb = $ridbDb->prepare(
        'SELECT source_json FROM ridb_facilities WHERE ridb_facility_id = ? LIMIT 1'
    );

    $duplicates = [];
    foreach ($raw as $item) {
        if ((string) $item['ridb_facility_id'] !== '') {
            $ridbId = (string) $item['ridb_facility_id'];
            $duplicates[$ridbId] = ($duplicates[$ridbId] ?? 0) + 1;
        }
    }

    $pairs = [];
    $byNps = [];
    $totals = [
        'cached_campgrounds' => $allCount,
        'confirmed_pairs' => count($raw),
        'published_places' => 0,
        'unpublished_ridb' => 0,
        'stale_identities' => 0,
        'identity_conflicts' => 0,
    ];
    $ridbHashes = [];
    foreach ($raw as $item) {
        $park = (string) $item['park_code'];
        $npsId = (string) $item['record_id'];
        $ridbId = trim((string) ($item['ridb_facility_id'] ?? ''));
        $row = [
            'park_code' => $park,
            'record_id' => $npsId,
            'name' => (string) $item['record_name'],
            'ridb_id' => $ridbId,
            'place_id' => null,
            'place_name' => '',
            'status' => '',
            'reviewed_fields' => 0,
        ];
        if ($ridbId === '' || ($duplicates[$ridbId] ?? 0) > 1) {
            $row['status'] = 'identity_conflict';
        } elseif (!hash_equals(
            (string) $item['source_hash'], (string) $item['nps_source_hash']
        )) {
            $row['status'] = 'stale_identity';
        } else {
            $loadRidb->execute([$ridbId]);
            $cachedRidb = $loadRidb->fetchColumn();
            if (!is_string($cachedRidb) || $cachedRidb === '') {
                $row['status'] = 'identity_conflict';
            } else {
                $ridbHashes[$ridbId] = hash('sha256', $cachedRidb);
                $loadPlace->execute([$ridbId]);
                $places = $loadPlace->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (count($places) > 1) {
                    $row['status'] = 'identity_conflict';
                } else {
                    $loadNpsLink->execute([$npsId]);
                    $existingLinks = $loadNpsLink->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    $placeId = $places ? (int) $places[0]['id'] : null;
                    if (count($existingLinks) > 1
                        || ($existingLinks && (int) $existingLinks[0] !== $placeId)) {
                        $row['status'] = 'identity_conflict';
                    } elseif ($placeId === null) {
                        $row['status'] = 'unpublished_ridb';
                    } else {
                        $row['status'] = 'published_place';
                        $row['place_id'] = $placeId;
                        $row['place_name'] = (string) $places[0]['name'];
                    }
                }
            }
        }
        if ($row['status'] === 'published_place') {
            $totals['published_places']++;
        } elseif ($row['status'] === 'unpublished_ridb') {
            $totals['unpublished_ridb']++;
        } elseif ($row['status'] === 'stale_identity') {
            $totals['stale_identities']++;
        } else {
            $totals['identity_conflicts']++;
        }
        $key = $park . ':' . $npsId;
        $byNps[$key] = count($pairs);
        $pairs[] = $row + [
            'nps_hash' => (string) $item['source_hash'],
        ];
    }

    $reviews = [];
    $reviewError = '';
    try {
        $reviewStmt = $npsDb->query(
            'SELECT e.nps_park_code, e.nps_record_id, e.ridb_facility_id,
                    e.field_key, e.decision, e.selected_value,
                    e.nps_source_hash, e.ridb_source_hash,
                    e.review_note, e.reviewed_at
             FROM nps_field_review_events e
             LEFT JOIN nps_field_review_events newer
               ON newer.nps_park_code = e.nps_park_code
              AND newer.nps_record_id = e.nps_record_id
              AND newer.field_key = e.field_key
              AND newer.id > e.id
             WHERE newer.id IS NULL
             ORDER BY e.nps_park_code, e.nps_record_id, e.field_key
             LIMIT 1001'
        );
        $entries = $reviewStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($entries) > 1000) {
            $reviewError = 'Field reviews exceed the display limit; use the full reconciliation page.';
            $entries = array_slice($entries, 0, 1000);
        }
        foreach ($entries as $entry) {
            $key = (string) $entry['nps_park_code'] . ':'
                . (string) $entry['nps_record_id'];
            if (!array_key_exists($key, $byNps)) {
                continue;
            }
            $pairIndex = $byNps[$key];
            $pair = $pairs[$pairIndex];
            $pairs[$pairIndex]['reviewed_fields']++;
            $selected = trim((string) ($entry['selected_value'] ?? ''));
            $current = (string) $entry['ridb_facility_id'] === $pair['ridb_id']
                && hash_equals(
                    (string) $entry['nps_source_hash'], $pair['nps_hash']
                )
                && isset($ridbHashes[$pair['ridb_id']])
                && hash_equals(
                    (string) $entry['ridb_source_hash'], $ridbHashes[$pair['ridb_id']]
                );
            $status = !$current ? 'stale_sources' :
                ((string) $entry['decision'] === 'unresolved' || $selected === ''
                    ? 'unresolved' : (
                        $pair['status'] === 'published_place'
                            ? 'reviewed_not_published'
                            : 'waiting_for_place'
                    ));
            $reviews[] = [
                'park_code' => (string) $entry['nps_park_code'],
                'record_id' => (string) $entry['nps_record_id'],
                'campground' => $pair['name'],
                'ridb_id' => $pair['ridb_id'],
                'place_id' => $pair['place_id'],
                'field_key' => (string) $entry['field_key'],
                'decision' => (string) $entry['decision'],
                'value' => $selected,
                'status' => $status,
                'reviewed_at' => (string) $entry['reviewed_at'],
            ];
        }
    } catch (PDOException $error) {
        // The source-identity coverage is useful even on installations
        // where the optional field-review audit table has not been added.
        if (in_array((int) ($error->errorInfo[1] ?? 0), [1146, 1], true)) {
            $reviewError = 'NPS field-review ledger is unavailable.';
        } else {
            throw $error;
        }
    }

    return [
        'totals' => $totals,
        'pairs' => $pairs,
        'field_reviews' => $reviews,
        'review_error' => $reviewError,
        'truncated' => $truncated,
    ];
}
