<?php

declare(strict_types=1);

/**
 * Bounded NPS -> already-published RIDB Place enrichment.
 *
 * This is deliberately NOT an automated identity matcher. A current,
 * human-confirmed NPS/RIDB pairing is required for each record. The existing
 * filler only fills empty Place fields and reuses exact photo URLs.
 *
 * The Admin page issues one explicit POST for each small batch. There is no
 * background worker, offset-scanning on shared hosting, or new schema.
 */
require_once __DIR__ . '/nps-place-fill.php';

/** Keep untrusted pagination state out of queries except as bound values. */
function llama_nps_fill_batch_cursor(string $park, string $id): array
{
    $park = trim(strtolower($park));
    $id = trim($id);
    if ($park === '' && $id === '') {
        return ['', ''];
    }
    if (!preg_match('/^[a-z]{4}$/D', $park)
        || $id === ''
        || strlen($id) > 128
        || preg_match('/[\x00-\x1F\x7F]/', $id)) {
        throw new InvalidArgumentException('Invalid NPS batch cursor. Restart from the first batch.');
    }
    return [$park, $id];
}

/** Stable keyset paging in the isolated NPS source database. */
function llama_nps_fill_batch_page(
    PDO $npsDb,
    string $afterPark = '',
    string $afterId = '',
    int $limit = 5
): array {
    [$afterPark, $afterId] = llama_nps_fill_batch_cursor($afterPark, $afterId);
    if ($limit < 1 || $limit > 10) {
        throw new InvalidArgumentException('NPS batch size must be between 1 and 10.');
    }
    $where = '';
    $params = [];
    if ($afterPark !== '') {
        $where = ' AND (n.park_code > ? OR (n.park_code = ? AND n.record_id > ?))';
        $params = [$afterPark, $afterPark, $afterId];
    }
    $stmt = $npsDb->prepare(
        "SELECT n.park_code, n.record_id, n.record_name, n.source_hash,
                r.ridb_facility_id
           FROM nps_raw_records n
           JOIN nps_ridb_review_events r
             ON r.nps_park_code = n.park_code
            AND r.nps_record_id = n.record_id
      LEFT JOIN nps_ridb_review_events newer
             ON newer.nps_park_code = r.nps_park_code
            AND newer.nps_record_id = r.nps_record_id
            AND newer.id > r.id
          WHERE n.resource_type = 'campgrounds'
            AND r.decision = 'confirmed_match'
            AND newer.id IS NULL
            AND n.source_hash = r.nps_source_hash"
        . $where
        . " ORDER BY n.park_code, n.record_id LIMIT " . ($limit + 1)
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $hasMore = count($rows) > $limit;
    $items = array_slice($rows, 0, $limit);
    $last = $items ? $items[count($items) - 1] : null;
    return [
        'items' => $items,
        'has_more' => $hasMore,
        'after_park' => $last !== null ? (string) $last['park_code'] : $afterPark,
        'after_id' => $last !== null ? (string) $last['record_id'] : $afterId,
        'limit' => $limit,
    ];
}

/**
 * Bind the Admin's previewed source identities to the explicit POST.
 * The NPS cache or identity review may change while a browser tab is open.
 * In that case the batch is rejected rather than silently changing its scope.
 */
function llama_nps_fill_batch_signature(array $items): string
{
    $ids = [];
    foreach ($items as $item) {
        $ids[] = [
            (string) ($item['park_code'] ?? ''),
            (string) ($item['record_id'] ?? ''),
            (string) ($item['ridb_facility_id'] ?? ''),
            (string) ($item['source_hash'] ?? ''),
        ];
    }
    return hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR));
}

/**
 * Each row has its own existing transaction and identity revalidation.
 * Failures are reported per row; a bad match never blocks later matches.
 */
function llama_nps_fill_batch_apply(
    PDO $npsDb,
    PDO $mainDb,
    array $page,
    string $expectedSignature,
    int $adminId,
    bool $includePhotos = false
): array {
    $items = (array) ($page['items'] ?? []);
    if ($adminId < 1 || !$items || count($items) > 10
        || !preg_match('/^[0-9a-f]{64}$/D', $expectedSignature)
        || !hash_equals(llama_nps_fill_batch_signature($items), $expectedSignature)) {
        throw new InvalidArgumentException(
            'The NPS batch changed or was not previewed. Reload before applying.'
        );
    }
    $results = [];
    $stats = [
        'checked' => 0, 'completed' => 0,
        'fields_filled' => 0, 'photos_added' => 0,
        'conflicts_kept' => 0, 'errors' => 0,
    ];
    foreach ($items as $item) {
        $stats['checked']++;
        $name = (string) ($item['record_name'] ?? '');
        try {
            $result = llama_nps_fill_apply(
                $npsDb,
                $mainDb,
                (string) $item['park_code'],
                (string) $item['record_id'],
                (string) $item['ridb_facility_id'],
                $adminId,
                $includePhotos
            );
            $stats['completed']++;
            $stats['fields_filled'] += (int) $result['fields_filled'];
            $stats['photos_added'] += (int) $result['photos_added'];
            $stats['conflicts_kept'] += (int) $result['fields_skipped'];
            $results[] = [
                'name' => $name,
                'status' => 'completed',
                'message' => (int) $result['fields_filled'] . ' fields, '
                    . (int) $result['photos_added'] . ' photos; '
                    . (int) $result['fields_skipped'] . ' conflicts preserved',
                'place_id' => (int) $result['place_id'],
            ];
        } catch (Throwable $error) {
            $stats['errors']++;
            if (function_exists('llama_log_caught_exception')) {
                llama_log_caught_exception($error, 'admin.nps_batch_fill', [
                    'park_code' => (string) ($item['park_code'] ?? ''),
                    'nps_record_id' => (string) ($item['record_id'] ?? ''),
                    'ridb_facility_id' => (string) ($item['ridb_facility_id'] ?? ''),
                ]);
            }
            $results[] = [
                'name' => $name,
                'status' => 'error',
                'message' => $error->getMessage(),
                'place_id' => null,
            ];
        }
    }
    return ['stats' => $stats, 'results' => $results];
}
