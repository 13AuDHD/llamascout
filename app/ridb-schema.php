<?php

declare(strict_types=1);

function llama_ridb_schema_value_type(
    mixed $value
): string {
    if ($value === null) {
        return 'null';
    }

    if (is_bool($value)) {
        return 'boolean';
    }

    if (is_int($value)) {
        return 'integer';
    }

    if (is_float($value)) {
        return 'number';
    }

    if (is_string($value)) {
        return 'string';
    }

    if (is_array($value)) {
        return
            array_is_list($value)
                ? 'array'
                : 'object';
    }

    return gettype($value);
}

function llama_ridb_schema_is_blank(
    mixed $value
): bool {
    if ($value === null) {
        return true;
    }

    if (is_string($value)) {
        return trim($value) === '';
    }

    if (is_array($value)) {
        return count($value) === 0;
    }

    return false;
}

function llama_ridb_schema_example(
    mixed $value
): string {
    if ($value === null) {
        return 'null';
    }

    if (is_bool($value)) {
        return $value
            ? 'true'
            : 'false';
    }

    if (
        is_int($value)
        || is_float($value)
    ) {
        return (string) $value;
    }

    if (is_string($value)) {
        $value =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    strip_tags($value)
                )
                ?? $value
            );

        if (
            mb_strlen($value)
            > 160
        ) {
            $value =
                mb_substr(
                    $value,
                    0,
                    157
                )
                . '...';
        }

        return $value;
    }

    if (is_array($value)) {
        if (array_is_list($value)) {
            return
                '['
                . count($value)
                . ' item'
                . (
                    count($value) === 1
                        ? ''
                        : 's'
                )
                . ']';
        }

        return
            '{'
            . count($value)
            . ' field'
            . (
                count($value) === 1
                    ? ''
                    : 's'
            )
            . '}';
    }

    return gettype($value);
}

function llama_ridb_schema_add_observation(
    array &$stats,
    string $path,
    mixed $value,
    string $recordKey
): void {
    if ($path === '') {
        return;
    }

    if (!isset($stats[$path])) {
        $stats[$path] = [
            'path' => $path,
            'types' => [],
            'record_keys' => [],
            'nonblank_record_keys' => [],
            'blank_record_keys' => [],
            'examples' => [],
            'max_array_items' => null,
        ];
    }

    $type =
        llama_ridb_schema_value_type(
            $value
        );

    $stats[$path]['types'][$type] =
        true;

    $stats[$path]['record_keys'][$recordKey] =
        true;

    if (
        llama_ridb_schema_is_blank(
            $value
        )
    ) {
        $stats[$path]['blank_record_keys'][$recordKey] =
            true;
    } else {
        $stats[$path]['nonblank_record_keys'][$recordKey] =
            true;

        $example =
            llama_ridb_schema_example(
                $value
            );

        if (
            $example !== ''
            && !in_array(
                $example,
                $stats[$path]['examples'],
                true
            )
            && count(
                $stats[$path]['examples']
            ) < 3
        ) {
            $stats[$path]['examples'][] =
                $example;
        }
    }

    if (
        is_array($value)
        && array_is_list($value)
    ) {
        $count =
            count($value);

        $existing =
            $stats[$path][
                'max_array_items'
            ];

        $stats[$path][
            'max_array_items'
        ] =
            $existing === null
                ? $count
                : max(
                    $existing,
                    $count
                );
    }
}

function llama_ridb_schema_walk(
    array &$stats,
    mixed $value,
    string $path,
    string $recordKey
): void {
    llama_ridb_schema_add_observation(
        $stats,
        $path,
        $value,
        $recordKey
    );

    if (!is_array($value)) {
        return;
    }

    if (array_is_list($value)) {
        foreach ($value as $item) {
            if (!is_array($item)) {
                llama_ridb_schema_add_observation(
                    $stats,
                    $path . '[]',
                    $item,
                    $recordKey
                );

                continue;
            }

            llama_ridb_schema_walk(
                $stats,
                $item,
                $path . '[]',
                $recordKey
            );
        }

        return;
    }

    foreach ($value as $key => $child) {
        $childPath =
            $path === ''
                ? (string) $key
                : $path
                    . '.'
                    . (string) $key;

        llama_ridb_schema_walk(
            $stats,
            $child,
            $childPath,
            $recordKey
        );
    }
}

function llama_ridb_schema_finalize(
    array $stats
): array {
    $rows = [];

    foreach ($stats as $row) {
        $row['types'] =
            array_keys(
                $row['types']
            );

        sort(
            $row['types'],
            SORT_NATURAL
            | SORT_FLAG_CASE
        );

        $row['records_seen'] =
            count(
                $row['record_keys']
            );

        $row['records_nonblank'] =
            count(
                $row[
                    'nonblank_record_keys'
                ]
            );

        $row['records_blank'] =
            count(
                $row[
                    'blank_record_keys'
                ]
            );

        unset(
            $row['record_keys'],
            $row['nonblank_record_keys'],
            $row['blank_record_keys']
        );

        $rows[] = $row;
    }

    usort(
        $rows,
        static fn (
            array $a,
            array $b
        ): int =>
            strnatcasecmp(
                (string) $a['path'],
                (string) $b['path']
            )
    );

    return $rows;
}

function llama_ridb_schema_collect_json_rows(
    array $rows,
    string $idColumn,
    string $jsonColumn = 'source_json'
): array {
    $stats = [];
    $validRecords = 0;

    foreach ($rows as $row) {
        $recordKey =
            trim(
                (string) (
                    $row[$idColumn]
                    ?? ''
                )
            );

        $json =
            (string) (
                $row[$jsonColumn]
                ?? ''
            );

        if (
            $recordKey === ''
            || $json === ''
        ) {
            continue;
        }

        try {
            $decoded =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (Throwable) {
            continue;
        }

        if (!is_array($decoded)) {
            continue;
        }

        $validRecords++;

        foreach ($decoded as $key => $value) {
            llama_ridb_schema_walk(
                $stats,
                $value,
                (string) $key,
                $recordKey
            );
        }
    }

    return [
        'record_count' =>
            $validRecords,

        'field_count' =>
            count($stats),

        'fields' =>
            llama_ridb_schema_finalize(
                $stats
            ),
    ];
}

function llama_ridb_schema_dataset(
    PDO $ridbDb,
    string $scope
): array {
    $scope =
        strtolower(
            trim($scope)
        );

    $definitions = [
        'facility' => [
            'label' => 'Facilities',
            'table' => 'ridb_facilities',
            'id' => 'ridb_facility_id',
        ],

        'campsite' => [
            'label' => 'Campsites',
            'table' => 'ridb_campsites',
            'id' => 'ridb_campsite_id',
        ],

        'address' => [
            'label' => 'Facility addresses',
            'table' => 'ridb_facility_addresses',
            'id' => 'id',
        ],

        'media' => [
            'label' => 'Media',
            'table' => 'ridb_media',
            'id' => 'id',
        ],

        'link' => [
            'label' => 'Links',
            'table' => 'ridb_links',
            'id' => 'id',
        ],

        'activity' => [
            'label' => 'Activities',
            'table' => 'ridb_activities',
            'id' => 'id',
        ],

        'campsite_attribute' => [
            'label' => 'Campsite attributes',
            'table' => 'ridb_campsite_attributes',
            'id' => 'id',
        ],
    ];

    if (
        !isset(
            $definitions[$scope]
        )
    ) {
        $scope = 'facility';
    }

    $definition =
        $definitions[$scope];

    $rows =
        $ridbDb
            ->query(
                'SELECT '
                . $definition['id']
                . ', source_json
                 FROM '
                . $definition['table']
                . '
                 ORDER BY '
                . $definition['id']
                . ' ASC'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
        ?: [];

    $dataset =
        llama_ridb_schema_collect_json_rows(
            $rows,
            (string) $definition['id']
        );

    $dataset['scope'] =
        $scope;

    $dataset['label'] =
        (string) $definition['label'];

    $dataset['scopes'] =
        array_map(
            static fn (
                array $item
            ): string =>
                (string) $item['label'],
            $definitions
        );

    return $dataset;
}

function llama_ridb_schema_attribute_catalog(
    PDO $ridbDb
): array {
    $rows =
        $ridbDb
            ->query(
                'SELECT
                    ridb_campsite_id,
                    attribute_name,
                    attribute_value
                 FROM ridb_campsite_attributes
                 ORDER BY
                    attribute_name ASC,
                    ridb_campsite_id ASC'
            )
            ->fetchAll(
                PDO::FETCH_ASSOC
            )
        ?: [];

    $catalog = [];
    $sites = [];

    foreach ($rows as $row) {
        $siteId =
            trim(
                (string) (
                    $row[
                        'ridb_campsite_id'
                    ]
                    ?? ''
                )
            );

        $name =
            trim(
                (string) (
                    $row['attribute_name']
                    ?? ''
                )
            );

        $value =
            trim(
                (string) (
                    $row['attribute_value']
                    ?? ''
                )
            );

        if (
            $siteId === ''
            || $name === ''
        ) {
            continue;
        }

        $sites[$siteId] =
            true;

        if (!isset($catalog[$name])) {
            $catalog[$name] = [
                'attribute_name' =>
                    $name,

                'site_ids' =>
                    [],

                'nonblank_site_ids' =>
                    [],

                'examples' =>
                    [],
            ];
        }

        $catalog[$name][
            'site_ids'
        ][$siteId] =
            true;

        if ($value !== '') {
            $catalog[$name][
                'nonblank_site_ids'
            ][$siteId] =
                true;

            if (
                !in_array(
                    $value,
                    $catalog[$name][
                        'examples'
                    ],
                    true
                )
                && count(
                    $catalog[$name][
                        'examples'
                    ]
                ) < 5
            ) {
                $catalog[$name][
                    'examples'
                ][] =
                    $value;
            }
        }
    }

    $final = [];

    foreach ($catalog as $entry) {
        $final[] = [
            'attribute_name' =>
                $entry[
                    'attribute_name'
                ],

            'sites_seen' =>
                count(
                    $entry[
                        'site_ids'
                    ]
                ),

            'sites_nonblank' =>
                count(
                    $entry[
                        'nonblank_site_ids'
                    ]
                ),

            'examples' =>
                $entry['examples'],
        ];
    }

    usort(
        $final,
        static fn (
            array $a,
            array $b
        ): int =>
            strnatcasecmp(
                (string) $a[
                    'attribute_name'
                ],
                (string) $b[
                    'attribute_name'
                ]
            )
    );

    return [
        'site_count' =>
            count($sites),

        'attribute_count' =>
            count($final),

        'attributes' =>
            $final,
    ];
}

function llama_ridb_schema_summary(
    PDO $ridbDb
): array {
    $counts = [];

    foreach (
        [
            'facilities' =>
                'ridb_facilities',

            'campsites' =>
                'ridb_campsites',

            'addresses' =>
                'ridb_facility_addresses',

            'media' =>
                'ridb_media',

            'links' =>
                'ridb_links',

            'activities' =>
                'ridb_activities',

            'attributes' =>
                'ridb_campsite_attributes',
        ]
        as $key => $table
    ) {
        try {
            $counts[$key] =
                (int) $ridbDb
                    ->query(
                        'SELECT COUNT(*)
                         FROM '
                        . $table
                    )
                    ->fetchColumn();
        } catch (Throwable) {
            $counts[$key] = 0;
        }
    }

    return $counts;
}
