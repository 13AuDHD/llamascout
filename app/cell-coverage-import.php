<?php

declare(strict_types=1);


/* =========================================================
   LLAMA SCOUT
   FCC MOBILE H3 IMPORTER V2

   Writes directly to the dedicated compact cell database.
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
): string {
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
): string {
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
): array {
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
                    !isset(
                        $columns[$name]
                    )
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
): float {
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
): int {
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
): ?array {
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

            $minX = min(
                $minX,
                $x
            );

            $maxX = max(
                $maxX,
                $x
            );

            $minY = min(
                $minY,
                $y
            );

            $maxY = max(
                $maxY,
                $y
            );

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
): ?array {
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
): ?string {
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


function llama_cell_state_name(
    string $fips
): string {
    $states = [
        '01' => 'Alabama',
        '02' => 'Alaska',
        '04' => 'Arizona',
        '05' => 'Arkansas',
        '06' => 'California',
        '08' => 'Colorado',
        '09' => 'Connecticut',
        '10' => 'Delaware',
        '11' => 'District of Columbia',
        '12' => 'Florida',
        '13' => 'Georgia',
        '15' => 'Hawaii',
        '16' => 'Idaho',
        '17' => 'Illinois',
        '18' => 'Indiana',
        '19' => 'Iowa',
        '20' => 'Kansas',
        '21' => 'Kentucky',
        '22' => 'Louisiana',
        '23' => 'Maine',
        '24' => 'Maryland',
        '25' => 'Massachusetts',
        '26' => 'Michigan',
        '27' => 'Minnesota',
        '28' => 'Mississippi',
        '29' => 'Missouri',
        '30' => 'Montana',
        '31' => 'Nebraska',
        '32' => 'Nevada',
        '33' => 'New Hampshire',
        '34' => 'New Jersey',
        '35' => 'New Mexico',
        '36' => 'New York',
        '37' => 'North Carolina',
        '38' => 'North Dakota',
        '39' => 'Ohio',
        '40' => 'Oklahoma',
        '41' => 'Oregon',
        '42' => 'Pennsylvania',
        '44' => 'Rhode Island',
        '45' => 'South Carolina',
        '46' => 'South Dakota',
        '47' => 'Tennessee',
        '48' => 'Texas',
        '49' => 'Utah',
        '50' => 'Vermont',
        '51' => 'Virginia',
        '53' => 'Washington',
        '54' => 'West Virginia',
        '55' => 'Wisconsin',
        '56' => 'Wyoming',
        '60' => 'American Samoa',
        '66' => 'Guam',
        '69' => 'Northern Mariana Islands',
        '72' => 'Puerto Rico',
        '78' => 'U.S. Virgin Islands',
    ];

    return $states[$fips]
        ?? ('FIPS ' . $fips);
}


function llama_cell_import_mask(
    string $provider,
    int $technologyCode
): int {
    return match (
        $provider
        . ':'
        . $technologyCode
    ) {
        'tmobile:400' => 1 | 2,
        'tmobile:500' => 4 | 8,
        'verizon:400' => 16 | 32,
        'verizon:500' => 64 | 128,
        'att:400' => 256 | 512,
        'att:500' => 1024 | 2048,
        default => 0,
    };
}


function llama_cell_import_row_flag(
    string $provider,
    int $technologyCode,
    int $environment
): int {
    $mask =
        llama_cell_import_mask(
            $provider,
            $technologyCode
        );

    if ($mask === 0) {
        return 0;
    }

    /*
     * In every provider/technology pair the first bit is
     * outdoor coverage and the second bit is in-vehicle.
     */
    $outdoorBit =
        $mask
        & (-$mask);

    $vehicleBit =
        $mask ^ $outdoorBit;

    if ($environment === 0) {
        return $outdoorBit;
    }

    if ($environment === 1) {
        return $outdoorBit
            | $vehicleBit;
    }

    return 0;
}


function llama_cell_import_h3_binary(
    string $h3
): ?string {
    $h3 =
        strtolower(
            trim($h3)
        );

    if (
        !preg_match(
            '/^[0-9a-f]{15}$/',
            $h3
        )
    ) {
        return null;
    }

    $binary =
        hex2bin(
            str_pad(
                $h3,
                16,
                '0',
                STR_PAD_LEFT
            )
        );

    return $binary === false
        ? null
        : $binary;
}


function llama_cell_import_identity(
    SQLite3 $sqlite,
    array $info
): array {
    $columns =
        $info['columns'];

    $brandColumn =
        $columns['brandname']
        ?? null;

    $select = [
        llama_cell_sqlite_identifier(
            $columns['providerid']
        )
            . ' AS providerid',

        llama_cell_sqlite_identifier(
            $columns['technology']
        )
            . ' AS technology',
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

    $query =
        'SELECT DISTINCT '
        . implode(
            ', ',
            $select
        )
        . '
         FROM '
        . llama_cell_sqlite_identifier(
            $info['table']
        )
        . '
         LIMIT 20';

    $result =
        $sqlite->query($query);

    if (!$result) {
        throw new RuntimeException(
            'The FCC GeoPackage identity could not be read.'
        );
    }

    $identities = [];

    while (
        $row =
            $result->fetchArray(
                SQLITE3_ASSOC
            )
    ) {
        $provider =
            llama_cell_provider_key(
                (int) (
                    $row['providerid']
                    ?? 0
                ),
                (string) (
                    $row['brandname']
                    ?? ''
                )
            );

        $technology =
            (int) (
                $row['technology']
                ?? 0
            );

        if (
            !$provider
            || !in_array(
                $technology,
                [400, 500],
                true
            )
        ) {
            continue;
        }

        $key =
            $provider
            . ':'
            . $technology;

        $identities[$key] = [
            'provider' => $provider,
            'technology_code' =>
                $technology,
            'technology' =>
                $technology === 500
                    ? '5g'
                    : '4g',
        ];
    }

    if (count($identities) !== 1) {
        throw new RuntimeException(
            'The GeoPackage must contain one supported provider and one mobile technology.'
        );
    }

    return array_values(
        $identities
    )[0];
}


function llama_cell_import_prepare_dataset(
    PDO $db,
    string $filename,
    string $stateFips,
    string $asOfDate,
    array $identity,
    int $sourceRows,
    int $sourceBytes
): void {
    $provider =
        $identity['provider'];

    $technology =
        $identity['technology'];

    $technologyCode =
        (int) $identity[
            'technology_code'
        ];

    $mask =
        llama_cell_import_mask(
            $provider,
            $technologyCode
        );

    $newerStmt =
        $db->prepare(
            'SELECT MAX(fcc_as_of_date)
             FROM cell_coverage_datasets
             WHERE state_fips = ?
               AND provider_key = ?
               AND technology = ?
               AND status = "current"'
        );

    $newerStmt->execute([
        $stateFips,
        $provider,
        $technology,
    ]);

    $newerDate =
        trim(
            (string) (
                $newerStmt->fetchColumn()
                ?: ''
            )
        );

    if (
        $newerDate !== ''
        && $newerDate > $asOfDate
    ) {
        throw new InvalidArgumentException(
            'A newer FCC dataset is already current for this state, provider, and technology.'
        );
    }

    $db->beginTransaction();

    try {
        /*
         * Remove only this provider/technology's old bits for the
         * state. Other carriers and technologies remain untouched.
         */
        $clearMask =
            65535 ^ $mask;

        $clearStmt =
            $db->prepare(
                'UPDATE cell_coverage_cells
                 SET coverage_flags =
                        coverage_flags & ?
                 WHERE state_fips = ?
                   AND (
                        coverage_flags & ?
                   ) <> 0'
            );

        $clearStmt->execute([
            $clearMask,
            $stateFips,
            $mask,
        ]);

        $deleteEmpty =
            $db->prepare(
                'DELETE FROM cell_coverage_cells
                 WHERE state_fips = ?
                   AND coverage_flags = 0'
            );

        $deleteEmpty->execute([
            $stateFips,
        ]);

        $ledger =
            $db->prepare(
                'INSERT INTO cell_coverage_datasets (
                    state_fips,
                    state_name,
                    provider_key,
                    technology,
                    fcc_as_of_date,
                    source_filename,
                    source_bytes,
                    status,
                    source_rows,
                    cells_written,
                    started_at,
                    completed_at,
                    error_message
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    "importing",
                    ?,
                    0,
                    UTC_TIMESTAMP(),
                    NULL,
                    NULL
                )
                ON DUPLICATE KEY UPDATE
                    state_name =
                        VALUES(state_name),
                    source_filename =
                        VALUES(source_filename),
                    source_bytes =
                        VALUES(source_bytes),
                    status =
                        "importing",
                    source_rows =
                        VALUES(source_rows),
                    cells_written =
                        0,
                    started_at =
                        UTC_TIMESTAMP(),
                    completed_at =
                        NULL,
                    error_message =
                        NULL'
            );

        $ledger->execute([
            $stateFips,
            llama_cell_state_name(
                $stateFips
            ),
            $provider,
            $technology,
            $asOfDate,
            $filename,
            $sourceBytes,
            $sourceRows,
        ]);

        $db->commit();

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}


function llama_cell_import_finish_dataset(
    PDO $db,
    string $stateFips,
    string $asOfDate,
    array $identity
): int {
    $provider =
        $identity['provider'];

    $technology =
        $identity['technology'];

    $mask =
        llama_cell_import_mask(
            $provider,
            (int) $identity[
                'technology_code'
            ]
        );

    $countStmt =
        $db->prepare(
            'SELECT COUNT(*)
             FROM cell_coverage_cells
             WHERE state_fips = ?
               AND (
                    coverage_flags & ?
               ) <> 0'
        );

    $countStmt->execute([
        $stateFips,
        $mask,
    ]);

    $cellCount =
        (int) $countStmt
            ->fetchColumn();

    $db->beginTransaction();

    try {
        $supersede =
            $db->prepare(
                'UPDATE cell_coverage_datasets
                 SET status = "superseded"
                 WHERE state_fips = ?
                   AND provider_key = ?
                   AND technology = ?
                   AND fcc_as_of_date <> ?
                   AND status = "current"'
            );

        $supersede->execute([
            $stateFips,
            $provider,
            $technology,
            $asOfDate,
        ]);

        $finish =
            $db->prepare(
                'UPDATE cell_coverage_datasets
                 SET status = "current",
                     cells_written = ?,
                     completed_at = UTC_TIMESTAMP(),
                     error_message = NULL
                 WHERE state_fips = ?
                   AND provider_key = ?
                   AND technology = ?
                   AND fcc_as_of_date = ?'
            );

        $finish->execute([
            $cellCount,
            $stateFips,
            $provider,
            $technology,
            $asOfDate,
        ]);

        $db->commit();

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }

    return $cellCount;
}


function llama_cell_import_mark_error(
    PDO $db,
    string $stateFips,
    string $asOfDate,
    ?array $identity,
    string $message
): void {
    if (!$identity) {
        return;
    }

    try {
        $stmt =
            $db->prepare(
                'UPDATE cell_coverage_datasets
                 SET status = "error",
                     error_message = ?,
                     completed_at = UTC_TIMESTAMP()
                 WHERE state_fips = ?
                   AND provider_key = ?
                   AND technology = ?
                   AND fcc_as_of_date = ?'
            );

        $stmt->execute([
            mb_substr(
                $message,
                0,
                4000
            ),
            $stateFips,
            $identity['provider'],
            $identity['technology'],
            $asOfDate,
        ]);
    } catch (Throwable) {
        // Do not hide the original import error.
    }
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

    $asOfDate =
        $date->format('Y-m-d');

    $offset =
        max(
            0,
            $offset
        );

    $batchSize =
        max(
            100,
            min(
                15000,
                $batchSize
            )
        );

    $path =
        llama_cell_import_safe_file(
            $filename
        );

    $sourceBytes =
        (int) filesize($path);

    $sqlite =
        new SQLite3(
            $path,
            SQLITE3_OPEN_READONLY
        );

    $sqlite->busyTimeout(3000);

    $identity = null;

    try {
        $info =
            llama_cell_import_table_info(
                $sqlite
            );

        $identity =
            llama_cell_import_identity(
                $sqlite,
                $info
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

        $totalRows =
            max(
                0,
                (int) $sqlite
                    ->querySingle(
                        'SELECT COUNT(*)
                         FROM '
                        . llama_cell_sqlite_identifier(
                            $table
                        )
                    )
            );

        if ($offset === 0) {
            llama_cell_import_prepare_dataset(
                $db,
                basename($path),
                $stateFips,
                $asOfDate,
                $identity,
                $totalRows,
                $sourceBytes
            );
        }

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

        $cells = [];

        $read = 0;
        $accepted = 0;
        $skipped = 0;

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

            $binaryH3 =
                llama_cell_import_h3_binary(
                    $h3
                );

            if ($binaryH3 === null) {
                $skipped++;
                continue;
            }

            $provider =
                llama_cell_provider_key(
                    (int) (
                        $row['providerid']
                        ?? 0
                    ),
                    (string) (
                        $row['brandname']
                        ?? ''
                    )
                );

            $technologyCode =
                (int) (
                    $row['technology']
                    ?? 0
                );

            if (
                $provider
                    !== $identity['provider']
                || $technologyCode
                    !== (int) $identity[
                        'technology_code'
                    ]
            ) {
                $skipped++;
                continue;
            }

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

            if ($technologyCode === 400) {
                if (
                    $mindown < 5
                    || $minup < 1
                ) {
                    $skipped++;
                    continue;
                }
            } elseif ($technologyCode === 500) {
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

            $environment =
                (int) (
                    $row['environmnt']
                    ?? -1
                );

            $flag =
                llama_cell_import_row_flag(
                    $provider,
                    $technologyCode,
                    $environment
                );

            if ($flag === 0) {
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

            $key = $h3;

            if (!isset($cells[$key])) {
                $cells[$key] = [
                    'h3' => $binaryH3,
                    'lat' =>
                        (float) $center['lat'],
                    'lng' =>
                        (float) $center['lng'],
                    'flags' => 0,
                ];
            }

            $cells[$key]['flags'] |=
                $flag;

            $accepted++;
        }

        $insert =
            $db->prepare(
                'INSERT INTO cell_coverage_cells (
                    state_fips,
                    h3_index,
                    center_lat,
                    center_lng,
                    coverage_flags
                ) VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
                ON DUPLICATE KEY UPDATE
                    center_lat =
                        VALUES(center_lat),
                    center_lng =
                        VALUES(center_lng),
                    coverage_flags =
                        coverage_flags
                        | VALUES(coverage_flags),
                    updated_at =
                        CURRENT_TIMESTAMP'
            );

        $db->beginTransaction();

        try {
            foreach ($cells as $cell) {
                $insert->bindValue(
                    1,
                    $stateFips,
                    PDO::PARAM_STR
                );

                $insert->bindValue(
                    2,
                    $cell['h3'],
                    PDO::PARAM_LOB
                );

                $insert->bindValue(
                    3,
                    $cell['lat']
                );

                $insert->bindValue(
                    4,
                    $cell['lng']
                );

                $insert->bindValue(
                    5,
                    $cell['flags'],
                    PDO::PARAM_INT
                );

                $insert->execute();
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

        $done =
            $read === 0
            || $nextOffset
                >= $totalRows;

        $datasetCells = null;

        if ($done) {
            $datasetCells =
                llama_cell_import_finish_dataset(
                    $db,
                    $stateFips,
                    $asOfDate,
                    $identity
                );
        }

        return [
            'filename' =>
                basename($path),

            'table' =>
                $table,

            'provider' =>
                $identity['provider'],

            'technology' =>
                $identity['technology'],

            'offset' =>
                $offset,

            'read' =>
                $read,

            /*
             * Keep "imported" for the existing Admin progress JS.
             * It means accepted source rows, not physical DB rows.
             */
            'imported' =>
                $accepted,

            'cells_written' =>
                count($cells),

            'dataset_cells' =>
                $datasetCells,

            'skipped' =>
                $skipped,

            'total_rows' =>
                $totalRows,

            'next_offset' =>
                $nextOffset,

            'done' =>
                $done,
        ];

    } catch (Throwable $e) {
        llama_cell_import_mark_error(
            $db,
            $stateFips,
            $asOfDate,
            $identity,
            $e->getMessage()
        );

        throw $e;

    } finally {
        $sqlite->close();
    }
}
