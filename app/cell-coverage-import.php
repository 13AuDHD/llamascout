<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   FCC MOBILE H3 IMPORTER
   ========================================================= */

function llama_cell_import_directory(): string
{
    $directory =
        dirname(__DIR__, 2)
        . '/private/fcc-imports';

    if (
        !is_dir($directory)
        && !mkdir(
            $directory,
            0750,
            true
        )
        && !is_dir($directory)
    ) {
        throw new RuntimeException(
            'The private FCC import directory could not be created.'
        );
    }

    return $directory;
}


function llama_cell_import_files(): array
{
    $directory =
        llama_cell_import_directory();

    $files = [];

    foreach (
        glob(
            $directory . '/*.gpkg'
        ) ?: []
        as $path
    ) {
        if (!is_file($path)) {
            continue;
        }

        $files[] = [
            'name' => basename($path),
            'path' => $path,
            'bytes' => (int) filesize($path),
            'modified_at' =>
                (int) filemtime($path),
        ];
    }

    usort(
        $files,
        static fn(array $a, array $b): int =>
            ($b['modified_at'] ?? 0)
            <=>
            ($a['modified_at'] ?? 0)
    );

    return $files;
}


function llama_cell_import_safe_file(
    string $filename
): string
{
    $filename =
        basename(
            trim($filename)
        );

    if (
        $filename === ''
        || !preg_match(
            '/\.gpkg$/i',
            $filename
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid GeoPackage file.'
        );
    }

    $path =
        llama_cell_import_directory()
        . '/'
        . $filename;

    if (!is_file($path)) {
        throw new RuntimeException(
            'The selected GeoPackage file was not found.'
        );
    }

    return $path;
}


function llama_cell_sqlite_identifier(
    string $identifier
): string
{
    return '"'
        . str_replace(
            '"',
            '""',
            $identifier
        )
        . '"';
}


function llama_cell_import_table_info(
    SQLite3 $sqlite
): array
{
    $required = [
        'h3_res9_id',
        'providerid',
        'technology',
        'mindown',
        'minup',
        'environmnt',
    ];

    $contents =
        $sqlite->query(
            "SELECT table_name
             FROM gpkg_contents
             WHERE data_type = 'features'
             ORDER BY table_name"
        );

    if (!$contents) {
        throw new RuntimeException(
            'The GeoPackage does not contain a readable feature table.'
        );
    }

    while (
        $row =
            $contents->fetchArray(
                SQLITE3_ASSOC
            )
    ) {
        $table =
            trim(
                (string) (
                    $row['table_name']
                    ?? ''
                )
            );

        if ($table === '') {
            continue;
        }

        $columns = [];

        $pragma =
            $sqlite->query(
                'PRAGMA table_info('
                . llama_cell_sqlite_identifier(
                    $table
                )
                . ')'
            );

        if (!$pragma) {
            continue;
        }

        while (
            $column =
                $pragma->fetchArray(
                    SQLITE3_ASSOC
                )
        ) {
            $name =
                trim(
                    (string) (
                        $column['name']
                        ?? ''
                    )
                );

            if ($name !== '') {
                $columns[
                    strtolower($name)
                ] = $name;
            }
        }

        $missing =
            array_filter(
                $required,
                static fn(string $name): bool =>
                    !isset($columns[$name])
            );

        if ($missing) {
            continue;
        }

        $geometryStmt =
            $sqlite->prepare(
                'SELECT column_name
                 FROM gpkg_geometry_columns
                 WHERE table_name = :table
                 LIMIT 1'
            );

        $geometryStmt->bindValue(
            ':table',
            $table,
            SQLITE3_TEXT
        );

        $geometryResult =
            $geometryStmt->execute();

        $geometryRow =
            $geometryResult
                ? $geometryResult
                    ->fetchArray(
                        SQLITE3_ASSOC
                    )
                : false;

        $geometry =
            trim(
                (string) (
                    $geometryRow[
                        'column_name'
                    ]
                    ?? ''
                )
            );

        if (
            $geometry === ''
            || !isset(
                $columns[
                    strtolower(
                        $geometry
                    )
                ]
            )
        ) {
            continue;
        }

        return [
            'table' => $table,
            'geometry' => $geometry,
            'columns' => $columns,
        ];
    }

    throw new RuntimeException(
        'No FCC H3 mobile broadband feature table was found in this GeoPackage.'
    );
}


function llama_cell_unpack_double(
    string $bytes,
    bool $littleEndian
): float
{
    if (strlen($bytes) !== 8) {
        throw new RuntimeException(
            'Invalid GeoPackage geometry coordinate.'
        );
    }

    $value =
        unpack(
            $littleEndian
                ? 'evalue'
                : 'Evalue',
            $bytes
        );

    return (float) (
        $value['value']
        ?? 0.0
    );
}


function llama_cell_unpack_uint32(
    string $bytes,
    bool $littleEndian
): int
{
    if (strlen($bytes) !== 4) {
        throw new RuntimeException(
            'Invalid GeoPackage geometry integer.'
        );
    }

    $value =
        unpack(
            $littleEndian
                ? 'Vvalue'
                : 'Nvalue',
            $bytes
        );

    return (int) (
        $value['value']
        ?? 0
    );
}


function llama_cell_wkb_polygon_bounds(
    string $wkb
): ?array
{
    $length = strlen($wkb);

    if ($length < 9) {
        return null;
    }

    $offset = 0;

    $byteOrder =
        ord($wkb[$offset]);

    $offset++;

    if (
        $byteOrder !== 0
        && $byteOrder !== 1
    ) {
        return null;
    }

    $little =
        $byteOrder === 1;

    $type =
        llama_cell_unpack_uint32(
            substr(
                $wkb,
                $offset,
                4
            ),
            $little
        );

    $offset += 4;

    /*
     * FCC H3 downloads use ordinary 2D polygon geometries.
     * Keep a small compatibility allowance for ISO SQL/MM
     * dimensional type offsets.
     */
    $baseType =
        $type >= 1000
            ? $type % 1000
            : $type;

    if ($baseType !== 3) {
        return null;
    }

    if ($offset + 4 > $length) {
        return null;
    }

    $ringCount =
        llama_cell_unpack_uint32(
            substr(
                $wkb,
                $offset,
                4
            ),
            $little
        );

    $offset += 4;

    $minX = INF;
    $maxX = -INF;
    $minY = INF;
    $maxY = -INF;

    $coordinateSize = 16;

    if (
        $type >= 1000
        && $type < 2000
    ) {
        $coordinateSize = 24;
    } elseif (
        $type >= 2000
        && $type < 3000
    ) {
        $coordinateSize = 24;
    } elseif ($type >= 3000) {
        $coordinateSize = 32;
    }

    for (
        $ring = 0;
        $ring < $ringCount;
        $ring++
    ) {
        if ($offset + 4 > $length) {
            return null;
        }

        $pointCount =
            llama_cell_unpack_uint32(
                substr(
                    $wkb,
                    $offset,
                    4
                ),
                $little
            );

        $offset += 4;

        for (
            $point = 0;
            $point < $pointCount;
            $point++
        ) {
            if (
                $offset
                + $coordinateSize
                > $length
            ) {
                return null;
            }

            $x =
                llama_cell_unpack_double(
                    substr(
                        $wkb,
                        $offset,
                        8
                    ),
                    $little
                );

            $y =
                llama_cell_unpack_double(
                    substr(
                        $wkb,
                        $offset + 8,
                        8
                    ),
                    $little
                );

            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);

            $offset +=
                $coordinateSize;
        }
    }

    if (
        !is_finite($minX)
        || !is_finite($maxX)
        || !is_finite($minY)
        || !is_finite($maxY)
    ) {
        return null;
    }

    return [
        'min_x' => $minX,
        'max_x' => $maxX,
        'min_y' => $minY,
        'max_y' => $maxY,
    ];
}


function llama_cell_gpkg_center(
    mixed $geometry
): ?array
{
    if (
        !is_string($geometry)
        || strlen($geometry) < 8
        || substr(
            $geometry,
            0,
            2
        ) !== 'GP'
    ) {
        return null;
    }

    $flags =
        ord($geometry[3]);

    $littleEndian =
        ($flags & 1) === 1;

    $envelopeType =
        ($flags >> 1) & 7;

    $envelopeValues =
        match ($envelopeType) {
            1 => 4,
            2, 3 => 6,
            4 => 8,
            default => 0,
        };

    if ($envelopeValues >= 4) {
        $offset = 8;

        if (
            strlen($geometry)
            < $offset
                + ($envelopeValues * 8)
        ) {
            return null;
        }

        $minX =
            llama_cell_unpack_double(
                substr(
                    $geometry,
                    $offset,
                    8
                ),
                $littleEndian
            );

        $maxX =
            llama_cell_unpack_double(
                substr(
                    $geometry,
                    $offset + 8,
                    8
                ),
                $littleEndian
            );

        $minY =
            llama_cell_unpack_double(
                substr(
                    $geometry,
                    $offset + 16,
                    8
                ),
                $littleEndian
            );

        $maxY =
            llama_cell_unpack_double(
                substr(
                    $geometry,
                    $offset + 24,
                    8
                ),
                $littleEndian
            );

        return [
            'lat' =>
                ($minY + $maxY) / 2,
            'lng' =>
                ($minX + $maxX) / 2,
        ];
    }

    $bounds =
        llama_cell_wkb_polygon_bounds(
            substr(
                $geometry,
                8
            )
        );

    if (!$bounds) {
        return null;
    }

    return [
        'lat' =>
            (
                $bounds['min_y']
                + $bounds['max_y']
            ) / 2,

        'lng' =>
            (
                $bounds['min_x']
                + $bounds['max_x']
            ) / 2,
    ];
}


function llama_cell_provider_key(
    int $providerId,
    string $brandName
): ?string
{
    $byId = [
        130403 => 'tmobile',
        131425 => 'verizon',
        130077 => 'att',
    ];

    if (isset($byId[$providerId])) {
        return $byId[$providerId];
    }

    $brand =
        strtolower(
            trim($brandName)
        );

    if (
        str_contains(
            $brand,
            't-mobile'
        )
        || str_contains(
            $brand,
            'tmobile'
        )
    ) {
        return 'tmobile';
    }

    if (
        str_contains(
            $brand,
            'verizon'
        )
    ) {
        return 'verizon';
    }

    if (
        $brand === 'at&t'
        || str_contains(
            $brand,
            'at&t'
        )
        || str_contains(
            $brand,
            'att mobility'
        )
    ) {
        return 'att';
    }

    return null;
}


function llama_cell_import_batch(
    PDO $db,
    string $filename,
    string $stateFips,
    string $asOfDate,
    int $offset = 0,
    int $batchSize = 1500
): array {
    if (!class_exists('SQLite3')) {
        throw new RuntimeException(
            'PHP SQLite3 support is not enabled on this server.'
        );
    }

    $stateFips =
        trim($stateFips);

    if (
        !preg_match(
            '/^\d{2}$/',
            $stateFips
        )
    ) {
        throw new InvalidArgumentException(
            'State FIPS must contain exactly two digits.'
        );
    }

    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            trim($asOfDate)
        );

    if (
        !$date
        || $date->format('Y-m-d')
            !== trim($asOfDate)
    ) {
        throw new InvalidArgumentException(
            'Choose a valid FCC data as-of date.'
        );
    }

    $offset =
        max(0, $offset);

    $batchSize =
        max(
            100,
            min(
                3000,
                $batchSize
            )
        );

    $path =
        llama_cell_import_safe_file(
            $filename
        );

    $sqlite =
        new SQLite3(
            $path,
            SQLITE3_OPEN_READONLY
        );

    $sqlite->busyTimeout(3000);

    try {
        $info =
            llama_cell_import_table_info(
                $sqlite
            );

        $table =
            $info['table'];

        $geometry =
            $info['geometry'];

        $columns =
            $info['columns'];

        $brandColumn =
            $columns['brandname']
            ?? null;

        $select = [
            llama_cell_sqlite_identifier(
                $columns['h3_res9_id']
            )
                . ' AS h3_res9_id',

            llama_cell_sqlite_identifier(
                $columns['providerid']
            )
                . ' AS providerid',

            llama_cell_sqlite_identifier(
                $columns['technology']
            )
                . ' AS technology',

            llama_cell_sqlite_identifier(
                $columns['mindown']
            )
                . ' AS mindown',

            llama_cell_sqlite_identifier(
                $columns['minup']
            )
                . ' AS minup',

            llama_cell_sqlite_identifier(
                $columns['environmnt']
            )
                . ' AS environmnt',

            llama_cell_sqlite_identifier(
                $geometry
            )
                . ' AS __geometry',
        ];

        if ($brandColumn) {
            $select[] =
                llama_cell_sqlite_identifier(
                    $brandColumn
                )
                . ' AS brandname';
        } else {
            $select[] =
                "'' AS brandname";
        }

        $count =
            $sqlite->querySingle(
                'SELECT COUNT(*)
                 FROM '
                . llama_cell_sqlite_identifier(
                    $table
                )
            );

        $totalRows =
            max(
                0,
                (int) $count
            );

        $query =
            'SELECT '
            . implode(
                ', ',
                $select
            )
            . '
             FROM '
            . llama_cell_sqlite_identifier(
                $table
            )
            . '
             LIMIT '
            . $batchSize
            . '
             OFFSET '
            . $offset;

        $rows =
            $sqlite->query($query);

        if (!$rows) {
            throw new RuntimeException(
                'The FCC GeoPackage batch could not be read.'
            );
        }

        $insert =
            $db->prepare(
                'INSERT INTO cell_coverage_h3 (
                    h3_index,
                    provider_key,
                    provider_id,
                    technology_code,
                    minimum_download,
                    minimum_upload,
                    environment,
                    center_lat,
                    center_lng,
                    state_fips,
                    as_of_date
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
                ON DUPLICATE KEY UPDATE
                    provider_id =
                        VALUES(provider_id),
                    minimum_download =
                        GREATEST(
                            minimum_download,
                            VALUES(minimum_download)
                        ),
                    minimum_upload =
                        GREATEST(
                            minimum_upload,
                            VALUES(minimum_upload)
                        ),
                    center_lat =
                        VALUES(center_lat),
                    center_lng =
                        VALUES(center_lng),
                    as_of_date =
                        GREATEST(
                            as_of_date,
                            VALUES(as_of_date)
                        ),
                    imported_at =
                        CURRENT_TIMESTAMP'
            );

        $read = 0;
        $imported = 0;
        $skipped = 0;

        $db->beginTransaction();

        try {
            while (
                $row =
                    $rows->fetchArray(
                        SQLITE3_ASSOC
                    )
            ) {
                $read++;

                $h3 =
                    strtolower(
                        trim(
                            (string) (
                                $row[
                                    'h3_res9_id'
                                ]
                                ?? ''
                            )
                        )
                    );

                if (
                    !preg_match(
                        '/^[0-9a-f]{15}$/',
                        $h3
                    )
                ) {
                    $skipped++;
                    continue;
                }

                $providerId =
                    (int) (
                        $row['providerid']
                        ?? 0
                    );

                $providerKey =
                    llama_cell_provider_key(
                        $providerId,
                        (string) (
                            $row['brandname']
                            ?? ''
                        )
                    );

                if (!$providerKey) {
                    $skipped++;
                    continue;
                }

                $technology =
                    (int) (
                        $row['technology']
                        ?? 0
                    );

                $mindown =
                    (float) (
                        $row['mindown']
                        ?? 0
                    );

                $minup =
                    (float) (
                        $row['minup']
                        ?? 0
                    );

                $environment =
                    (int) (
                        $row['environmnt']
                        ?? -1
                    );

                if (
                    !in_array(
                        $environment,
                        [0, 1],
                        true
                    )
                ) {
                    $skipped++;
                    continue;
                }

                if ($technology === 400) {
                    if (
                        $mindown < 5
                        || $minup < 1
                    ) {
                        $skipped++;
                        continue;
                    }
                } elseif (
                    $technology === 500
                ) {
                    /*
                     * Llama Scout's 5G toggle means
                     * "at least the FCC 7/1 Mbps 5G tier."
                     * Faster 35/3 coverage therefore still
                     * counts as 5G coverage.
                     */
                    if (
                        $mindown < 7
                        || $minup < 1
                    ) {
                        $skipped++;
                        continue;
                    }
                } else {
                    $skipped++;
                    continue;
                }

                $center =
                    llama_cell_gpkg_center(
                        $row['__geometry']
                        ?? null
                    );

                if (
                    !$center
                    || $center['lat'] < -90
                    || $center['lat'] > 90
                    || $center['lng'] < -180
                    || $center['lng'] > 180
                ) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    $h3,
                    $providerKey,
                    $providerId > 0
                        ? $providerId
                        : null,
                    $technology,
                    (int) round($mindown),
                    (int) round($minup),
                    $environment,
                    round(
                        (float) $center['lat'],
                        6
                    ),
                    round(
                        (float) $center['lng'],
                        6
                    ),
                    $stateFips,
                    $date->format(
                        'Y-m-d'
                    ),
                ]);

                $imported++;
            }

            $db->commit();

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        $nextOffset =
            $offset + $read;

        return [
            'filename' => basename($path),
            'table' => $table,
            'offset' => $offset,
            'read' => $read,
            'imported' => $imported,
            'skipped' => $skipped,
            'total_rows' => $totalRows,
            'next_offset' => $nextOffset,
            'done' =>
                $read === 0
                || $nextOffset >= $totalRows,
        ];

    } finally {
        $sqlite->close();
    }
}
