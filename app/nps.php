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

    if (!array_key_exists($parkCode, llama_nps_parks())) {
        throw new InvalidArgumentException('Choose a supported pilot park.');
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

/** Only approved NPS resources, fixed pilot parks, and bounded page sizes. */
function llama_nps_request(
    string $resource,
    string $parkCode,
    int $limit = 5
): array {
    if (!isset(llama_nps_resources()[$resource])) {
        throw new InvalidArgumentException('Unsupported NPS resource.');
    }

    $parkCode = llama_nps_validate_park($parkCode);
    $limit = max(1, min(10, $limit));
    $config = llama_nps_config();
    $url = $config['base_url'] . '/' . $resource . '?' . http_build_query(
        ['parkCode' => $parkCode, 'start' => 0, 'limit' => $limit],
        '', '&', PHP_QUERY_RFC3986
    );

    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Could not initialize the NPS request.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'X-Api-Key: ' . $config['api_key'],
            'Accept: application/json',
        ],
        CURLOPT_USERAGENT => 'LlamaScout-NPS-SourceAudit/1.0',
    ]);

    try {
        $body = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
    } finally {
        curl_close($handle);
    }

    if ($body === false) {
        throw new RuntimeException('NPS connection failed: ' . $error);
    }

    if ($httpCode !== 200) {
        // Never print a request, key, or untrusted API error body.
        throw new RuntimeException('NPS returned HTTP ' . $httpCode . '.');
    }

    try {
        $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('NPS returned invalid JSON.');
    }

    if (!is_array($response)
        || !isset($response['data'])
        || !is_array($response['data'])
        || !array_is_list($response['data'])
    ) {
        throw new RuntimeException('NPS returned an unexpected data structure.');
    }

    foreach ($response['data'] as $record) {
        if (!is_array($record) || array_is_list($record)) {
            throw new RuntimeException('NPS returned an invalid record.');
        }
    }

    return [
        'resource' => $resource,
        'park_code' => $parkCode,
        'total' => max(0, (int) ($response['total'] ?? count($response['data']))),
        'records' => $response['data'],
    ];
}

function llama_nps_record_id(array $record): string
{
    foreach (['id', 'idNumber'] as $key) {
        $value = trim((string) ($record[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    // Never discard a source record solely because its ID is absent.
    return 'unidentified-' . substr(
        hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)), 0, 48
    );
}

/**
 * Store a bounded source snapshot. No interaction with places, moderation or
 * published reports. Existing cache rows update by resource + authoritative ID.
 */
function llama_nps_store_sample(PDO $db, array $page): array
{
    $resource = (string) ($page['resource'] ?? '');
    if (!isset(llama_nps_resources()[$resource])) {
        throw new InvalidArgumentException('Unknown NPS resource.');
    }

    $parkCode = llama_nps_validate_park((string) ($page['park_code'] ?? ''));
    $records = (array) ($page['records'] ?? []);

    if (count($records) > 10) {
        throw new InvalidArgumentException('A pilot import can contain at most 10 records.');
    }

    $stmt = $db->prepare(
        'INSERT INTO nps_raw_records
            (resource_type, record_id, park_code, record_name, source_url,
             source_updated_at, source_json, source_hash, fetched_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            park_code = VALUES(park_code),
            record_name = VALUES(record_name),
            source_url = VALUES(source_url),
            source_updated_at = VALUES(source_updated_at),
            source_json = VALUES(source_json),
            source_hash = VALUES(source_hash),
            fetched_at = UTC_TIMESTAMP()'
    );

    $count = 0;
    $db->beginTransaction();
    try {
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new UnexpectedValueException('Invalid source record.');
            }

            $json = json_encode(
                $record,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            // The cache key records which park was queried. One NPS record can
            // belong to multiple parks; the original parkCode stays in JSON.
            $park = $parkCode;
            $name = trim((string) (
                $record['name'] ?? $record['title'] ?? $record['fullName'] ?? ''
            ));
            $url = trim((string) ($record['url'] ?? ''));
            $updated = trim((string) (
                $record['lastIndexedDate'] ?? $record['lastUpdated'] ?? ''
            ));

            $stmt->execute([
                $resource,
                llama_nps_record_id($record),
                substr($park, 0, 64),
                substr($name, 0, 255),
                substr($url, 0, 1024),
                substr($updated, 0, 64),
                $json,
                hash('sha256', $json),
            ]);
            $count++;
        }

        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    return ['stored' => $count, 'resource' => $resource, 'park_code' => $parkCode];
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
