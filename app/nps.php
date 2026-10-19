<?php

declare(strict_types=1);

/**
 * NPS source cache and inspection helpers. Phase 1B is intentionally isolated:
 * no writes to the primary Llama Scout database and no Place publishing.
 */

function nps_db(): PDO
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    $db = llama_database_connection(
        (array) (llama_config()['nps_database'] ?? []),
        'NPS'
    );

    return $db;
}

function llama_nps_resources(): array
{
    return [
        'parks' => 'Parks',
        'campgrounds' => 'Campgrounds',
        'alerts' => 'Alerts and closures',
        'feespasses' => 'Fees and passes',
        'parkinglots' => 'Parking lots',
        'roadevents' => 'Road events',
    ];
}

function llama_nps_parks(): array
{
    return [
        'meve' => 'Mesa Verde',
        'grsa' => 'Great Sand Dunes',
        'grca' => 'Grand Canyon',
        'yose' => 'Yosemite',
        'zion' => 'Zion',
    ];
}

function llama_nps_validate_park(string $parkCode): string
{
    $parkCode = strtolower(trim($parkCode));

    if (preg_match('/^[a-z]{4}$/D', $parkCode) !== 1) {
        throw new InvalidArgumentException('Choose a valid four-letter NPS park code.');
    }

    return $parkCode;
}

function llama_nps_config(): array
{
    $config = (array) (llama_config()['nps'] ?? []);
    $key = trim((string) ($config['api_key'] ?? ''));
    $url = rtrim(trim((string) (
        $config['base_url'] ?? 'https://developer.nps.gov/api/v1'
    )), '/');

    if ($key === '') {
        throw new RuntimeException('NPS API key is missing from private config.');
    }

    // Restrict requests to the official host; no redirect or user-provided URL.
    $parts = parse_url($url);
    if (!is_array($parts)
        || ($parts['scheme'] ?? '') !== 'https'
        || ($parts['host'] ?? '') !== 'developer.nps.gov'
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['port'])
        || isset($parts['query'])
        || isset($parts['fragment'])
        || ($parts['path'] ?? '') !== '/api/v1'
    ) {
        throw new RuntimeException('NPS API base URL must be https://developer.nps.gov/api/v1.');
    }

    return ['api_key' => $key, 'base_url' => $url];
}

/** Stop before decoding a response that could exhaust a shared PHP worker. */
function llama_nps_response_budget(): int
{
    $maximum = 2 * 1024 * 1024;
    $setting = trim((string) ini_get('memory_limit'));
    if ($setting !== '' && $setting !== '-1') {
        $limit = (int) $setting;
        $unit = strtolower(substr($setting, -1));
        $limit *= match ($unit) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        // Leave room for PHP's decoded arrays, prepared statements, and the page snapshot.
        $maximum = min($maximum, max(0, (int) (($limit - memory_get_usage(true)) / 32)));
    }
    if ($maximum < 256 * 1024) {
        throw new RuntimeException('There is not enough free PHP memory for an NPS batch. The import was paused without raising the server memory limit.');
    }
    return $maximum;
}

/** One bounded response; an empty park filter means every NPS park. */
function llama_nps_request_page(
    string $resource,
    string $parkCode = '',
    int $limit = 50,
    int $start = 0
): array {
    if (!isset(llama_nps_resources()[$resource]) || $start < 0) {
        throw new InvalidArgumentException('Unsupported NPS request.');
    }
    if ($parkCode !== '') {
        $parkCode = llama_nps_validate_park($parkCode);
    }
    $limit = max(1, min(50, $limit));
    $query = $parkCode !== '' ? ['parkCode' => $parkCode] : [];
    // Road events are a single GeoJSON feed, not a paginated data array.
    if ($resource !== 'roadevents') {
        $query += ['start' => $start, 'limit' => $limit];
        if ($resource === 'parks') {
            $query['sort'] = 'parkCode';
        } elseif ($resource === 'campgrounds') {
            $query['sort'] = 'name';
        }
    } elseif ($start !== 0) {
        throw new InvalidArgumentException('The road-event feed does not use offsets.');
    }
    $config = llama_nps_config();
    $url = $config['base_url'] . '/' . $resource;
    if ($query) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the NPS request.');
    }
    $remaining = null;
    $body = '';
    $oversized = false;
    $budget = llama_nps_response_budget();
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'X-Api-Key: ' . $config['api_key'],
            'Accept: application/json',
        ],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$remaining): int {
            if (preg_match('/^X-RateLimit-Remaining:\s*(\d+)/i', $header, $match)) {
                $remaining = (int) $match[1];
            }
            return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$oversized, $budget): int {
            if (strlen($body) + strlen($chunk) > $budget) {
                $oversized = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
        CURLOPT_USERAGENT => 'LlamaScout-NPS-SourceImport/1.0',
    ]);
    try {
        $received = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    } finally {
        curl_close($handle);
    }
    if ($oversized) {
        throw new RuntimeException('The NPS response exceeds this server\'s import memory budget. This record type was stopped before JSON decoding; its saved offset was kept.');
    }
    if ($received === false) {
        throw new RuntimeException('The NPS request timed out or could not connect. Resume to retry this page.');
    }
    if ($httpCode !== 200) {
        // No API key, request URL, or untrusted API error body is exposed.
        throw new RuntimeException($httpCode === 429
            ? 'NPS request limit reached. Wait for the hourly limit to reset, then resume.'
            : 'NPS returned HTTP ' . $httpCode . '. Resume to retry this record type.', $httpCode);
    }
    try {
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('NPS returned invalid JSON. This page was not saved.');
    }
    $page = llama_nps_response_page($resource, $response, $start, $limit);
    $page['park_code'] = $parkCode;
    $page['rate_remaining'] = $remaining;
    return $page;
}

/** Validate pagination before any source data or checkpoint is written. */
function llama_nps_response_page(string $resource, mixed $response, int $start, int $limit): array
{
    if (!is_array($response) || array_is_list($response)) {
        throw new RuntimeException('NPS returned an unexpected response object.');
    }
    $roadFeed = $resource === 'roadevents';
    $records = $response[$roadFeed ? 'features' : 'data'] ?? null;
    if (!is_array($records) || !array_is_list($records)) {
        throw new RuntimeException($roadFeed
            ? 'NPS did not return a road-event FeatureCollection.'
            : 'NPS did not return a record list.');
    }
    if ($roadFeed) {
        if (($response['type'] ?? '') !== 'FeatureCollection' || $start !== 0) {
            throw new RuntimeException('NPS returned an invalid road-event feed.');
        }
        $total = count($records);
    } else {
        $totalRaw = $response['total'] ?? null;
        $startRaw = $response['start'] ?? null;
        if (!is_scalar($totalRaw) || !ctype_digit((string) $totalRaw)
            || !is_scalar($startRaw) || !ctype_digit((string) $startRaw)
            || (int) $startRaw !== $start || count($records) > $limit) {
            throw new RuntimeException('NPS returned inconsistent pagination. This page was not saved.');
        }
        $total = (int) $totalRaw;
        if ((!$records && $start < $total) || ($records && $start + count($records) > $total)) {
            throw new RuntimeException('NPS returned incomplete pagination. Resume to retry this page.');
        }
    }
    $ids = [];
    foreach ($records as $record) {
        if (!is_array($record) || array_is_list($record)) {
            throw new RuntimeException('NPS returned an invalid source record.');
        }
        $id = llama_nps_record_id($record, $resource);
        if (isset($ids[$id])) {
            throw new RuntimeException('NPS repeated a source ID within this page. This page was not saved.');
        }
        $ids[$id] = true;
    }
    $metadata = $response;
    unset($metadata['data'], $metadata['features']);
    return [
        'resource' => $resource, 'records' => $records, 'total' => $total,
        'start' => $start, 'next_start' => $start + count($records),
        'complete' => $roadFeed || $start + count($records) >= $total,
        'page_hash' => hash('sha256', json_encode(array_keys($ids), JSON_THROW_ON_ERROR)),
        'metadata' => $metadata,
    ];
}

/** Retain the existing limited preview/sample workflow. */
function llama_nps_request(string $resource, string $parkCode, int $limit = 5): array
{
    $limit = max(1, min(10, $limit));
    $page = llama_nps_request_page($resource, llama_nps_validate_park($parkCode), $limit);
    if ($resource === 'roadevents') {
        $page['records'] = array_slice($page['records'], 0, $limit);
    }
    return $page;
}

function llama_nps_record_id(array $record, string $resource = ''): string
{
    foreach (['id', 'idNumber'] as $key) {
        $value = trim((string) ($record[$key] ?? ''));
        if ($value !== '') {
            if (strlen($value) > 128) {
                throw new RuntimeException('An NPS source ID exceeds the source-table capacity.');
            }
            return $value;
        }
    }
    // feespasses has no record ID: its documented identity is its park code.
    // A mutable JSON hash would create a new row whenever the fee changes.
    if ($resource === 'feespasses' && preg_match('/^[a-z]{4}$/iD', (string) ($record['parkCode'] ?? ''))) {
        return 'park-fees-' . strtolower((string) $record['parkCode']);
    }
    return 'unidentified-' . substr(hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)), 0, 48);
}

function llama_nps_index_text(string $value, int $length): string
{
    preg_match('/^.{0,' . $length . '}/us', $value, $match);
    return $match[0] ?? '';
}

function llama_nps_record_parks(array $record): array
{
    $codes = explode(',', (string) ($record['parkCode'] ?? ''));
    foreach ((array) ($record['relatedParks'] ?? []) as $park) {
        if (is_array($park)) {
            $codes[] = (string) ($park['parkCode'] ?? '');
        }
    }
    $valid = [];
    foreach ($codes as $code) {
        $code = strtolower(trim($code));
        if (preg_match('/^[a-z]{4}$/D', $code)) {
            $valid[$code] = $code;
        }
    }
    return array_values($valid);
}

/** Shared upsert for samples and full imports, always in the supplied NPS DB. */
function llama_nps_store_page(PDO $db, array $page): int
{
    $resource = (string) ($page['resource'] ?? '');
    if (!isset(llama_nps_resources()[$resource])) {
        throw new InvalidArgumentException('Unknown NPS resource.');
    }
    $filter = (string) ($page['park_code'] ?? '');
    if ($filter !== '') {
        $filter = llama_nps_validate_park($filter);
    }
    $records = (array) ($page['records'] ?? []);
    if (count($records) > 50) {
        throw new InvalidArgumentException('An NPS source batch cannot exceed 50 records.');
    }
    $legacyFee = $db->prepare('SELECT record_id FROM nps_raw_records
        WHERE resource_type = ? AND park_code = ? ORDER BY fetched_at DESC, record_id LIMIT 1');
    $upsert = $db->prepare('INSERT INTO nps_raw_records
        (resource_type, record_id, park_code, record_name, source_url,
         source_updated_at, source_json, source_hash, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE record_name = VALUES(record_name),
         source_url = VALUES(source_url), source_updated_at = VALUES(source_updated_at),
         source_json = VALUES(source_json), source_hash = VALUES(source_hash), fetched_at = UTC_TIMESTAMP()');
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }
    try {
        $entries = [];
        foreach ($records as $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new UnexpectedValueException('Invalid NPS source record.');
            }
            $id = llama_nps_record_id($record, $resource);
            $parks = $filter !== '' ? [$filter] : llama_nps_record_parks($record);
            if ($resource === 'feespasses' && str_starts_with($id, 'park-fees-') && $parks) {
                // Keep an existing pilot row's identity rather than adding a duplicate.
                $legacyFee->execute([$resource, $parks[0]]);
                $legacyId = $legacyFee->fetchColumn();
                if ($legacyId !== false) {
                    $id = (string) $legacyId;
                }
            }
            $entries[] = ['record' => $record, 'id' => $id, 'parks' => $parks];
        }
        $contexts = [];
        if ($entries) {
            // One scan per batch, instead of one lookup per source record.
            $ids = array_values(array_unique(array_column($entries, 'id')));
            $existing = $db->prepare('SELECT record_id, park_code FROM nps_raw_records
                WHERE resource_type = ? AND record_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $existing->execute(array_merge([$resource], $ids));
            foreach ($existing->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $contexts[(string) $row['record_id']][] = (string) $row['park_code'];
            }
        }
        $stored = 0;
        foreach ($entries as $entry) {
            $record = $entry['record'];
            $id = $entry['id'];
            $parks = array_values(array_unique(array_merge($entry['parks'], $contexts[$id] ?? [])));
            if (!$parks) {
                $parks = ['']; // No inferred park association; original metadata is retained.
            }
            $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $core = (array) ($record['properties']['core_details'] ?? []);
            $name = (string) ($record['name'] ?? $record['title'] ?? $record['fullName'] ?? $core['name'] ?? '');
            $url = (string) ($record['url'] ?? 'https://developer.nps.gov/api/v1/' . $resource);
            $updated = (string) ($record['lastIndexedDate'] ?? $record['lastUpdated']
                ?? ($resource === 'roadevents' ? ($page['metadata']['road_event_feed_info']['update_date'] ?? '') : ''));
            foreach ($parks as $park) {
                $upsert->execute([$resource, $id, $park, llama_nps_index_text($name, 255),
                    llama_nps_index_text($url, 1024), llama_nps_index_text($updated, 64), $json, hash('sha256', $json)]);
            }
            $stored++;
        }
        if ($ownsTransaction) {
            $db->commit();
        }
        return $stored;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function llama_nps_store_sample(PDO $db, array $page): array
{
    llama_nps_validate_park((string) ($page['park_code'] ?? ''));
    if (count((array) ($page['records'] ?? [])) > 10) {
        throw new InvalidArgumentException('A sample can contain at most 10 records.');
    }
    return ['stored' => llama_nps_store_page($db, $page),
        'resource' => $page['resource'], 'park_code' => $page['park_code']];
}

function llama_nps_cache_summary(PDO $db): array
{
    $counts = array_fill_keys(array_keys(llama_nps_resources()), 0);
    $stmt = $db->query(
        'SELECT resource_type, COUNT(*) AS records
           FROM nps_raw_records
          GROUP BY resource_type'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $type = (string) $row['resource_type'];
        if (array_key_exists($type, $counts)) {
            $counts[$type] = (int) $row['records'];
        }
    }

    return $counts;
}

function llama_nps_sample_rows(PDO $db, string $resource, string $parkCode): array
{
    $stmt = $db->prepare(
        'SELECT record_id, record_name, source_url, source_updated_at, fetched_at
           FROM nps_raw_records
          WHERE resource_type = ? AND park_code = ?
          ORDER BY record_name, record_id
          LIMIT 100'
    );
    $stmt->execute([$resource, $parkCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Flatten keys, not assumptions about API attributes. */
function llama_nps_collect_paths(
    mixed $value,
    string $prefix,
    array &$result,
    int $depth = 0
): void {
    if ($depth > 9 || count($result) > 900) {
        return;
    }

    if (is_array($value)) {
        if (array_is_list($value)) {
            $key = $prefix . '[]';
            if ($value === []) {
                $result[$key]['seen'] = ($result[$key]['seen'] ?? 0) + 1;
                $result[$key]['type']['empty array'] = true;
            }
            foreach (array_slice($value, 0, 20) as $entry) {
                llama_nps_collect_paths($entry, $key, $result, $depth + 1);
            }
            return;
        }

        foreach ($value as $key => $entry) {
            $next = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
            llama_nps_collect_paths($entry, $next, $result, $depth + 1);
        }
        return;
    }

    $kind = match (true) {
        $value === null => 'null',
        is_bool($value) => 'boolean',
        is_int($value), is_float($value) => 'number',
        default => 'text',
    };

    if (!isset($result[$prefix])) {
        $result[$prefix] = ['type' => [], 'seen' => 0, 'examples' => []];
    }
    $result[$prefix]['seen']++;
    $result[$prefix]['type'][$kind] = true;
    if ($value !== null
        && (!is_string($value) || $value !== '')
        && count($result[$prefix]['examples']) < 2) {
        $sample = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $matched = [];
        $sample = preg_match('/^.{0,110}/us', $sample, $matched) === 1
            ? $matched[0]
            : '';
        if (!in_array($sample, $result[$prefix]['examples'], true)) {
            $result[$prefix]['examples'][] = $sample;
        }
    }
}

function llama_nps_field_inventory(PDO $db, string $resource, string $parkCode): array
{
    $stmt = $db->prepare(
        'SELECT source_json FROM nps_raw_records
          WHERE resource_type = ? AND park_code = ?
          ORDER BY record_id LIMIT 100'
    );
    $stmt->execute([$resource, $parkCode]);

    $paths = [];
    $records = 0;
    while (($raw = $stmt->fetchColumn()) !== false) {
        try {
            $json = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            continue;
        }
        if (is_array($json)) {
            llama_nps_collect_paths($json, '', $paths);
            $records++;
        }
    }

    ksort($paths, SORT_STRING);
    return ['records' => $records, 'fields' => $paths];
}


/** Include nationally imported parks in the existing source explorer. */
function llama_nps_available_parks(PDO $db): array
{
    $parks = llama_nps_parks();
    $stmt = $db->query("SELECT source_json FROM nps_raw_records WHERE resource_type = 'parks'");
    while (($json = $stmt->fetchColumn()) !== false) {
        $record = json_decode((string) $json, true);
        $code = strtolower((string) ($record['parkCode'] ?? ''));
        if (preg_match('/^[a-z]{4}$/D', $code)) {
            $parks[$code] = (string) ($record['fullName'] ?? $record['name'] ?? strtoupper($code));
        }
    }
    asort($parks, SORT_NATURAL | SORT_FLAG_CASE);
    return $parks;
}

/** This table and every import write belong to nps_db(), not db()/ridb_db(). */
function llama_nps_import_prepare(PDO $db): void
{
    // Check the actual raw-table columns before starting a full import.
    $db->query('SELECT resource_type, record_id, park_code, record_name, source_url,
        source_updated_at, source_json, source_hash, fetched_at FROM nps_raw_records LIMIT 0');
    $db->exec('CREATE TABLE IF NOT EXISTS nps_import_progress (
        resource_type VARCHAR(32) NOT NULL PRIMARY KEY,
        status VARCHAR(16) NOT NULL DEFAULT \'pending\',
        next_start INT UNSIGNED NOT NULL DEFAULT 0,
        total_records INT UNSIGNED NULL DEFAULT NULL,
        last_page_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
        response_metadata LONGTEXT NULL DEFAULT NULL,
        last_error TEXT NULL DEFAULT NULL,
        started_at DATETIME NULL DEFAULT NULL,
        updated_at DATETIME NOT NULL,
        completed_at DATETIME NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->query('SELECT resource_type, status, next_start, total_records, last_page_hash,
        response_metadata, last_error, started_at, updated_at, completed_at
        FROM nps_import_progress LIMIT 0');
    $insert = $db->prepare('INSERT IGNORE INTO nps_import_progress (resource_type, updated_at)
        VALUES (?, UTC_TIMESTAMP())');
    foreach (array_keys(llama_nps_resources()) as $resource) {
        $insert->execute([$resource]);
    }
}

function llama_nps_import_summary(PDO $db): array
{
    $rows = [];
    $stmt = $db->query('SELECT resource_type, status, next_start, total_records,
        last_error, updated_at FROM nps_import_progress');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[(string) $row['resource_type']] = $row;
    }
    $result = ['resources' => [], 'complete' => true, 'pending' => false, 'errors' => false];
    foreach (llama_nps_resources() as $resource => $label) {
        $row = $rows[$resource] ?? [];
        $status = (string) ($row['status'] ?? 'not_started');
        $result['resources'][] = [
            'resource' => $resource, 'label' => $label, 'status' => $status,
            'processed' => (int) ($row['next_start'] ?? 0),
            'total' => isset($row['total_records']) ? (int) $row['total_records'] : null,
            'error' => (string) ($row['last_error'] ?? ''),
        ];
        $result['complete'] = $result['complete'] && $status === 'complete';
        $result['pending'] = $result['pending'] || $status === 'pending';
        $result['errors'] = $result['errors'] || $status === 'error';
    }
    return $result;
}

/** Serialize requests from multiple tabs; never advance an in-flight page twice. */
function llama_nps_import_locked(PDO $db, callable $callback): array
{
    $database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    $lock = 'llama_nps_import_' . substr(hash('sha256', $database), 0, 32);
    $stmt = $db->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->execute([$lock]);
    if ((int) $stmt->fetchColumn() !== 1) {
        return ['busy' => true, 'retry_after' => 4];
    }
    try {
        return $callback();
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lock]);
    }
}

function llama_nps_import_start(PDO $db, bool $refresh = false): array
{
    return llama_nps_import_locked($db, static function () use ($db, $refresh): array {
        llama_nps_import_prepare($db);
        $summary = llama_nps_import_summary($db);
        if ($refresh && $summary['complete']) {
            // A new traversal refreshes cached records; it never deletes sources or reviews.
            $db->exec("UPDATE nps_import_progress SET status = 'pending', next_start = 0,
                total_records = NULL, last_page_hash = NULL, response_metadata = NULL,
                last_error = NULL, started_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP(),
                completed_at = NULL");
        } else {
            $db->exec("UPDATE nps_import_progress SET status = 'pending', last_error = NULL,
                started_at = COALESCE(started_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()
                WHERE status IN ('pending', 'paused', 'error')");
        }
        return ['summary' => llama_nps_import_summary($db)];
    });
}

function llama_nps_import_pause(PDO $db): array
{
    return llama_nps_import_locked($db, static function () use ($db): array {
        $db->exec("UPDATE nps_import_progress SET status = 'paused', updated_at = UTC_TIMESTAMP()
            WHERE status = 'pending'");
        return ['summary' => llama_nps_import_summary($db)];
    });
}

/** Commit a source page and its cursor atomically, or keep the previous cursor. */
function llama_nps_import_commit_page(PDO $db, array $state, array $page): void
{
    if (count($page['records']) > 50) {
        throw new RuntimeException('An NPS write batch cannot contain more than 50 source records.');
    }
    if (($page['resource'] ?? '') !== $state['resource_type']
        || (int) ($page['start'] ?? -1) !== (int) $state['next_start']) {
        throw new RuntimeException('The NPS page does not match the saved import cursor.');
    }
    if ($page['records'] && !empty($state['last_page_hash'])
        && hash_equals((string) $state['last_page_hash'], (string) $page['page_hash'])) {
        throw new RuntimeException('NPS repeated the previous page. The saved offset was kept.');
    }
    $db->beginTransaction();
    try {
        llama_nps_store_page($db, $page);
        $stmt = $db->prepare('UPDATE nps_import_progress SET status = ?, next_start = ?,
            total_records = ?, last_page_hash = ?, response_metadata = COALESCE(?, response_metadata), last_error = NULL,
            updated_at = UTC_TIMESTAMP(), completed_at = ? WHERE resource_type = ?');
        $stmt->execute([
            $page['complete'] ? 'complete' : 'pending', $page['next_start'], $page['total'],
            $page['page_hash'], !empty($page['retain_snapshot']) ? null
                : json_encode($page['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $page['complete'] ? gmdate('Y-m-d H:i:s') : null, $state['resource_type'],
        ]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

/** Road events have no API pagination. Cache one capped feed, then extract 50 records per request. */
function llama_nps_import_road_page(PDO $db, array $state): array
{
    $start = (int) $state['next_start'];
    $read = $db->prepare("SELECT JSON_LENGTH(response_metadata, '$.features') AS total,
        JSON_REMOVE(response_metadata, '$.features') AS metadata
        FROM nps_import_progress WHERE resource_type = ?");
    $read->execute(['roadevents']);
    $snapshot = $read->fetch(PDO::FETCH_ASSOC);
    $remaining = null;
    if ($snapshot['total'] === null) {
        if ($start !== 0) {
            throw new RuntimeException('The saved road-event snapshot is missing. Its last committed offset was kept.');
        }
        $feed = llama_nps_request_page('roadevents');
        $metadata = $feed['metadata'];
        $metadata['features'] = $feed['records'];
        $save = $db->prepare('UPDATE nps_import_progress SET response_metadata = ?,
            total_records = ?, updated_at = UTC_TIMESTAMP() WHERE resource_type = ?');
        $save->execute([json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $feed['total'], 'roadevents']);
        $remaining = $feed['rate_remaining'];
        unset($feed, $metadata);
        $read->execute(['roadevents']);
        $snapshot = $read->fetch(PDO::FETCH_ASSOC);
    }
    $total = (int) $snapshot['total'];
    // MariaDB extracts just this batch; PHP does not reload/decode the entire saved feed.
    $items = $db->prepare("SELECT item.source_json FROM nps_import_progress AS progress
        CROSS JOIN JSON_TABLE(progress.response_metadata, '$.features[*]'
            COLUMNS (item_ordinal FOR ORDINALITY, source_json JSON PATH '$' ERROR ON ERROR)) AS item
        WHERE progress.resource_type = ? AND item.item_ordinal > ? AND item.item_ordinal <= ?
        ORDER BY item.item_ordinal");
    $items->execute(['roadevents', $start, $start + 50]);
    $records = [];
    $ids = [];
    while (($json = $items->fetchColumn()) !== false) {
        $record = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        $records[] = $record;
        $ids[] = llama_nps_record_id($record, 'roadevents');
    }
    if (count($records) !== min(50, max(0, $total - $start))) {
        throw new RuntimeException('The saved road-event batch is incomplete. Its offset was kept.');
    }
    $complete = $start + count($records) >= $total;
    return ['resource' => 'roadevents', 'park_code' => '', 'records' => $records,
        'start' => $start, 'next_start' => $start + count($records), 'total' => $total,
        'complete' => $complete, 'retain_snapshot' => !$complete,
        'metadata' => json_decode((string) $snapshot['metadata'], true, 512, JSON_THROW_ON_ERROR),
        'page_hash' => hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR)), 'rate_remaining' => $remaining];
}

function llama_nps_import_next(PDO $db): array
{
    return llama_nps_import_locked($db, static function () use ($db): array {
        $summary = llama_nps_import_summary($db);
        $resource = null;
        foreach ($summary['resources'] as $item) {
            if ($item['status'] === 'pending') {
                $resource = $item['resource'];
                break;
            }
        }
        if ($resource === null) {
            return ['summary' => $summary];
        }
        // Server-side pacing also applies when more than one tab is open.
        $wait = (int) $db->query('SELECT GREATEST(0, 4 - TIMESTAMPDIFF(SECOND,
            MAX(updated_at), UTC_TIMESTAMP())) FROM nps_import_progress')->fetchColumn();
        if ($wait > 0) {
            return ['busy' => true, 'retry_after' => $wait];
        }
        $stmt = $db->prepare('SELECT resource_type, next_start, last_page_hash
            FROM nps_import_progress WHERE resource_type = ?');
        $stmt->execute([$resource]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $message = '';
        try {
            $page = $resource === 'roadevents' ? llama_nps_import_road_page($db, $state)
                : llama_nps_request_page($resource, '', 50, (int) $state['next_start']);
            llama_nps_import_commit_page($db, $state, $page);
            if ($page['rate_remaining'] !== null && $page['rate_remaining'] <= 5) {
                $db->exec("UPDATE nps_import_progress SET status = 'paused', updated_at = UTC_TIMESTAMP()
                    WHERE status = 'pending'");
                $message = 'The NPS request allowance is nearly exhausted. Progress is saved. Resume after the hourly limit resets.';
            }
        } catch (Throwable $exception) {
            $message = $exception instanceof PDOException
                ? 'Could not save this NPS page. The previous checkpoint was kept. Check the NPS database connection and resume.'
                : $exception->getMessage();
            if (function_exists('llama_log_caught_exception')) {
                $reference = llama_log_caught_exception($exception, 'admin.nps_source_import',
                    ['resource' => $resource, 'start' => (int) $state['next_start']], [RuntimeException::class]);
                $message = llama_error_message_with_reference($message, $reference);
            }
            $failure = $db->prepare("UPDATE nps_import_progress SET status = 'error', last_error = ?,
                updated_at = UTC_TIMESTAMP() WHERE resource_type = ?");
            $failure->execute([$message, $resource]);
            if ($exception->getCode() === 429) {
                $db->exec("UPDATE nps_import_progress SET status = 'paused', updated_at = UTC_TIMESTAMP()
                    WHERE status = 'pending'");
            }
        }
        return ['summary' => llama_nps_import_summary($db), 'message' => $message];
    });
}
