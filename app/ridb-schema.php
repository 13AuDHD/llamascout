<?php

declare(strict_types=1);

function llama_ridb_schema_value_type(mixed $value): string {
    if ($value === null) return 'null';
    if (is_bool($value)) return 'boolean';
    if (is_int($value)) return 'integer';
    if (is_float($value)) return 'number';
    if (is_string($value)) return 'string';
    if (is_array($value)) return array_is_list($value) ? 'array' : 'object';
    return gettype($value);
}

function llama_ridb_schema_is_blank(mixed $value): bool {
    if ($value === null) return true;
    if (is_string($value)) return trim($value) === '';
    if (is_array($value)) return count($value) === 0;
    return false;
}

function llama_ridb_schema_example(mixed $value): string {
    if ($value === null) return 'null';
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_int($value) || is_float($value)) return (string) $value;

    if (is_string($value)) {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value);
        if (mb_strlen($value) > 160) {
            $value = mb_substr($value, 0, 157) . '...';
        }
        return $value;
    }

    if (is_array($value)) {
        if (array_is_list($value)) {
            return '[' . count($value) . ' item' . (count($value) === 1 ? '' : 's') . ']';
        }
        return '{' . count($value) . ' field' . (count($value) === 1 ? '' : 's') . '}';
    }

    return gettype($value);
}

/**
 * Record one field-path observation without retaining every record ID.
 * $recordPaths exists only for the current source record.
 */
function llama_ridb_schema_add_observation(
    array &$stats,
    array &$recordPaths,
    string $path,
    mixed $value
): void {
    if ($path === '') return;

    if (!isset($stats[$path])) {
        $stats[$path] = [
            'path' => $path,
            'types' => [],
            'records_seen' => 0,
            'records_nonblank' => 0,
            'records_blank' => 0,
            'examples' => [],
            'max_array_items' => null,
        ];
    }

    $stats[$path]['types'][llama_ridb_schema_value_type($value)] = true;
    $blank = llama_ridb_schema_is_blank($value);

    if (!isset($recordPaths[$path])) {
        $recordPaths[$path] = ['nonblank' => false, 'blank' => false];
        $stats[$path]['records_seen']++;
    }

    if ($blank) {
        if (!$recordPaths[$path]['blank'] && !$recordPaths[$path]['nonblank']) {
            $recordPaths[$path]['blank'] = true;
            $stats[$path]['records_blank']++;
        }
    } else {
        if (!$recordPaths[$path]['nonblank']) {
            if ($recordPaths[$path]['blank']) {
                $stats[$path]['records_blank'] = max(0, $stats[$path]['records_blank'] - 1);
            }
            $recordPaths[$path]['blank'] = false;
            $recordPaths[$path]['nonblank'] = true;
            $stats[$path]['records_nonblank']++;
        }

        $example = llama_ridb_schema_example($value);
        if (
            $example !== ''
            && !in_array($example, $stats[$path]['examples'], true)
            && count($stats[$path]['examples']) < 3
        ) {
            $stats[$path]['examples'][] = $example;
        }
    }

    if (is_array($value) && array_is_list($value)) {
        $count = count($value);
        $existing = $stats[$path]['max_array_items'];
        $stats[$path]['max_array_items'] = $existing === null ? $count : max($existing, $count);
    }
}

function llama_ridb_schema_walk(
    array &$stats,
    array &$recordPaths,
    mixed $value,
    string $path
): void {
    llama_ridb_schema_add_observation($stats, $recordPaths, $path, $value);

    if (!is_array($value)) return;

    if (array_is_list($value)) {
        foreach ($value as $item) {
            if (!is_array($item)) {
                llama_ridb_schema_add_observation($stats, $recordPaths, $path . '[]', $item);
                continue;
            }
            llama_ridb_schema_walk($stats, $recordPaths, $item, $path . '[]');
        }
        return;
    }

    foreach ($value as $key => $child) {
        $childPath = $path === '' ? (string) $key : $path . '.' . (string) $key;
        llama_ridb_schema_walk($stats, $recordPaths, $child, $childPath);
    }
}

function llama_ridb_schema_finalize(array $stats): array {
    $rows = [];

    foreach ($stats as $row) {
        $row['types'] = array_keys($row['types']);
        sort($row['types'], SORT_NATURAL | SORT_FLAG_CASE);
        $rows[] = $row;
    }

    usort($rows, static fn(array $a, array $b): int => strnatcasecmp((string) $a['path'], (string) $b['path']));
    return $rows;
}

function llama_ridb_schema_collect_json_rows(
    array $rows,
    string $idColumn,
    string $jsonColumn = 'source_json',
    ?array &$stats = null,
    ?int &$validRecords = null
): array {
    $stats ??= [];
    $validRecords ??= 0;

    foreach ($rows as $row) {
        $recordKey = trim((string) ($row[$idColumn] ?? ''));
        $json = (string) ($row[$jsonColumn] ?? '');

        if ($recordKey === '' || $json === '') continue;

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            continue;
        }

        if (!is_array($decoded)) continue;

        $validRecords++;
        $recordPaths = [];

        foreach ($decoded as $key => $value) {
            llama_ridb_schema_walk($stats, $recordPaths, $value, (string) $key);
        }
    }

    return [
        'record_count' => $validRecords,
        'field_count' => count($stats),
        'fields' => llama_ridb_schema_finalize($stats),
    ];
}

function llama_ridb_schema_dataset(PDO $ridbDb, string $scope): array {
    $scope = strtolower(trim($scope));

    $definitions = [
        'facility' => ['label' => 'Facilities', 'table' => 'ridb_facilities', 'id' => 'ridb_facility_id'],
        'campsite' => ['label' => 'Campsites', 'table' => 'ridb_campsites', 'id' => 'ridb_campsite_id'],
        'address' => ['label' => 'Facility addresses', 'table' => 'ridb_facility_addresses', 'id' => 'id'],
        'media' => ['label' => 'Media', 'table' => 'ridb_media', 'id' => 'id'],
        'link' => ['label' => 'Links', 'table' => 'ridb_links', 'id' => 'id'],
        'activity' => ['label' => 'Activities', 'table' => 'ridb_activities', 'id' => 'id'],
        'campsite_attribute' => ['label' => 'Campsite attributes', 'table' => 'ridb_campsite_attributes', 'id' => 'id'],
    ];

    if (!isset($definitions[$scope])) $scope = 'facility';

    $definition = $definitions[$scope];
    $table = (string) $definition['table'];
    $idColumn = (string) $definition['id'];

    $stats = [];
    $validRecords = 0;
    $cursor = null;
    $batchSize = 100;

    while (true) {
        if ($cursor === null) {
            $stmt = $ridbDb->prepare(
                'SELECT ' . $idColumn . ', source_json FROM ' . $table
                . ' ORDER BY ' . $idColumn . ' ASC LIMIT ' . $batchSize
            );
            $stmt->execute();
        } else {
            $stmt = $ridbDb->prepare(
                'SELECT ' . $idColumn . ', source_json FROM ' . $table
                . ' WHERE ' . $idColumn . ' > ?'
                . ' ORDER BY ' . $idColumn . ' ASC LIMIT ' . $batchSize
            );
            $stmt->execute([$cursor]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) break;

        llama_ridb_schema_collect_json_rows($rows, $idColumn, 'source_json', $stats, $validRecords);

        $last = end($rows);
        $nextCursor = trim((string) ($last[$idColumn] ?? ''));
        unset($rows, $last);

        if ($nextCursor === '' || $nextCursor === $cursor) break;
        $cursor = $nextCursor;
    }

    return [
        'record_count' => $validRecords,
        'field_count' => count($stats),
        'fields' => llama_ridb_schema_finalize($stats),
        'scope' => $scope,
        'label' => (string) $definition['label'],
        'scopes' => array_map(static fn(array $item): string => (string) $item['label'], $definitions),
    ];
}

function llama_ridb_schema_attribute_catalog(PDO $ridbDb): array {
    $siteCount = (int) $ridbDb->query(
        'SELECT COUNT(DISTINCT ridb_campsite_id) FROM ridb_campsite_attributes'
    )->fetchColumn();

    $stmt = $ridbDb->query(
        'SELECT
            attribute_name,
            COUNT(DISTINCT ridb_campsite_id) AS sites_seen,
            COUNT(DISTINCT CASE
                WHEN attribute_value IS NOT NULL AND TRIM(attribute_value) <> ""
                THEN ridb_campsite_id ELSE NULL END
            ) AS sites_nonblank
         FROM ridb_campsite_attributes
         WHERE attribute_name IS NOT NULL
           AND TRIM(attribute_name) <> ""
         GROUP BY attribute_name
         ORDER BY attribute_name ASC'
    );

    $final = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $exampleStmt = $ridbDb->prepare(
        'SELECT DISTINCT attribute_value
         FROM ridb_campsite_attributes
         WHERE attribute_name = ?
           AND attribute_value IS NOT NULL
           AND TRIM(attribute_value) <> ""
         ORDER BY attribute_value ASC
         LIMIT 5'
    );

    foreach ($final as &$entry) {
        $name = trim((string) ($entry['attribute_name'] ?? ''));
        $entry['sites_seen'] = (int) ($entry['sites_seen'] ?? 0);
        $entry['sites_nonblank'] = (int) ($entry['sites_nonblank'] ?? 0);
        $entry['examples'] = [];

        if ($name === '') continue;

        $exampleStmt->execute([$name]);
        $entry['examples'] = array_values(array_filter(
            array_map(static fn(mixed $value): string => trim((string) $value), $exampleStmt->fetchAll(PDO::FETCH_COLUMN) ?: []),
            static fn(string $value): bool => $value !== ''
        ));
    }
    unset($entry);

    return [
        'site_count' => $siteCount,
        'attribute_count' => count($final),
        'attributes' => $final,
    ];
}

function llama_ridb_schema_summary(PDO $ridbDb): array {
    $counts = [];

    foreach ([
        'facilities' => 'ridb_facilities',
        'campsites' => 'ridb_campsites',
        'addresses' => 'ridb_facility_addresses',
        'media' => 'ridb_media',
        'links' => 'ridb_links',
        'activities' => 'ridb_activities',
        'attributes' => 'ridb_campsite_attributes',
    ] as $key => $table) {
        try {
            $counts[$key] = (int) $ridbDb->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        } catch (Throwable) {
            $counts[$key] = 0;
        }
    }

    return $counts;
}
