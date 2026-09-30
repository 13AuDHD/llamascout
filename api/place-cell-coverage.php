<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');


function llama_place_cell_json(
    array $payload,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}


function llama_place_cell_float(
    string $name
): ?float {
    $raw = $_GET[$name] ?? null;

    if (
        $raw === null
        || $raw === ''
        || !is_numeric($raw)
    ) {
        return null;
    }

    return (float) $raw;
}


function llama_place_cell_requested_bit(
    string $provider,
    string $technology,
    string $environment
): int {
    return match (
        $provider
        . ':'
        . $technology
        . ':'
        . $environment
    ) {
        'tmobile:4g:outdoors' => 1,
        'tmobile:4g:vehicle' => 2,
        'tmobile:5g:outdoors' => 4,
        'tmobile:5g:vehicle' => 8,

        'verizon:4g:outdoors' => 16,
        'verizon:4g:vehicle' => 32,
        'verizon:5g:outdoors' => 64,
        'verizon:5g:vehicle' => 128,

        'att:4g:outdoors' => 256,
        'att:4g:vehicle' => 512,
        'att:5g:outdoors' => 1024,
        'att:5g:vehicle' => 2048,

        default => 0,
    };
}


function llama_place_cell_binary_to_h3(
    mixed $value
): string {
    if (
        !is_string($value)
        || strlen($value) !== 8
    ) {
        return '';
    }

    $hex =
        strtolower(
            bin2hex($value)
        );

    if (
        strlen($hex) === 16
        && $hex[0] === '0'
    ) {
        return substr($hex, 1);
    }

    return ltrim($hex, '0');
}


function llama_place_cell_distance_km(
    float $lat1,
    float $lng1,
    float $lat2,
    float $lng2
): float {
    $earthRadiusKm = 6371.0088;

    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLng = deg2rad($lng2 - $lng1);

    $a =
        sin($deltaLat / 2) ** 2
        + cos($lat1Rad)
        * cos($lat2Rad)
        * sin($deltaLng / 2) ** 2;

    $a =
        min(
            1.0,
            max(0.0, $a)
        );

    return
        $earthRadiusKm
        * 2
        * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );
}


try {
    $viewer =
        current_user();

    $viewerUserId =
        is_array($viewer)
            ? (int) (
                $viewer['id']
                ?? 0
            )
            : 0;

    $slug =
        trim(
            (string) (
                $_GET['slug']
                ?? ''
            )
        );

    if (
        $viewerUserId < 1
        || $slug === ''
    ) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Complete Place access is required.',
            ],
            403
        );
    }

    $publicPlace =
        place_public_by_slug(
            $slug
        );

    if (!$publicPlace) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Place not found.',
            ],
            404
        );
    }

    $placeId =
        (int) (
            $publicPlace['id']
            ?? 0
        );

    if (
        !user_has_place_complete_access(
            $placeId,
            $viewerUserId
        )
    ) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Complete Place access is required.',
            ],
            403
        );
    }

    $memberPlace =
        place_member_by_slug(
            $slug
        );

    $placeLatitude =
        isset($memberPlace['latitude'])
        && is_numeric(
            $memberPlace['latitude']
        )
            ? (float) $memberPlace['latitude']
            : null;

    $placeLongitude =
        isset($memberPlace['longitude'])
        && is_numeric(
            $memberPlace['longitude']
        )
            ? (float) $memberPlace['longitude']
            : null;

    if (
        $placeLatitude === null
        || $placeLongitude === null
    ) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'This Place does not have usable map coordinates.',
            ],
            409
        );
    }

    $allowedProviders = [
        'tmobile' => true,
        'verizon' => true,
        'att' => true,
    ];

    $providerInput =
        trim(
            (string) (
                $_GET['providers']
                ?? ''
            )
        );

    $providers =
        array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn(
                            string $value
                        ): string =>
                            strtolower(
                                trim($value)
                            ),
                        explode(
                            ',',
                            $providerInput
                        )
                    ),
                    static fn(
                        string $value
                    ): bool =>
                        isset(
                            $allowedProviders[
                                $value
                            ]
                        )
                )
            )
        );

    if (!$providers) {
        llama_place_cell_json(
            [
                'ok' => true,
                'coverage' => [],
                'as_of_dates' => [],
                'truncated' => false,
                'minimum_zoom' => 11,
            ]
        );
    }

    $technology =
        strtolower(
            trim(
                (string) (
                    $_GET['technology']
                    ?? '4g'
                )
            )
        );

    if (
        $technology !== '4g'
        && $technology !== '5g'
    ) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Invalid coverage technology.',
            ],
            400
        );
    }

    $environment =
        strtolower(
            trim(
                (string) (
                    $_GET['environment']
                    ?? 'vehicle'
                )
            )
        );

    if (
        $environment !== 'vehicle'
        && $environment !== 'outdoors'
    ) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Invalid coverage environment.',
            ],
            400
        );
    }

    $north =
        llama_place_cell_float(
            'north'
        );

    $south =
        llama_place_cell_float(
            'south'
        );

    $east =
        llama_place_cell_float(
            'east'
        );

    $west =
        llama_place_cell_float(
            'west'
        );

    $zoom =
        isset($_GET['zoom'])
        && is_numeric($_GET['zoom'])
            ? (int) $_GET['zoom']
            : 0;

    if (
        $north === null
        || $south === null
        || $east === null
        || $west === null
        || $north <= $south
        || $north > 90
        || $south < -90
        || $east > 180
        || $east < -180
        || $west > 180
        || $west < -180
    ) {
        llama_place_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Invalid map bounds.',
            ],
            400
        );
    }

    $minimumZoom = 11;

    if ($zoom < $minimumZoom) {
        llama_place_cell_json(
            [
                'ok' => true,
                'coverage' => [],
                'as_of_dates' => [],
                'truncated' => false,
                'too_broad' => true,
                'minimum_zoom' =>
                    $minimumZoom,
            ]
        );
    }

    $latitudeSpan =
        $north - $south;

    $longitudeSpan =
        $west <= $east
            ? $east - $west
            : (180 - $west)
                + ($east + 180);

    if (
        $latitudeSpan > 6.0
        || $longitudeSpan > 8.0
    ) {
        llama_place_cell_json(
            [
                'ok' => true,
                'coverage' => [],
                'as_of_dates' => [],
                'truncated' => false,
                'too_broad' => true,
                'minimum_zoom' =>
                    $minimumZoom,
            ]
        );
    }

    /*
     * Global Complete Access can use the compact Place map normally.
     * A contributor whose access is only for this one Place may inspect
     * coverage around that Place, but cannot turn this endpoint into a
     * nationwide member-cell API by changing the requested bounds.
     */
    $hasGlobalMemberAccess =
        user_has_member_access(
            $viewerUserId
        );

    if (!$hasGlobalMemberAccess) {
        $centerLatitude =
            ($north + $south) / 2;

        $centerLongitude =
            $west <= $east
                ? ($west + $east) / 2
                : (
                    (
                        $west
                        + ($east + 360)
                    )
                    / 2
                );

        if ($centerLongitude > 180) {
            $centerLongitude -= 360;
        }

        $distanceKm =
            llama_place_cell_distance_km(
                $placeLatitude,
                $placeLongitude,
                $centerLatitude,
                $centerLongitude
            );

        if ($distanceKm > 150.0) {
            llama_place_cell_json(
                [
                    'ok' => false,
                    'error' =>
                        'Cell coverage for this Place is limited to the surrounding area.',
                ],
                403
            );
        }
    }

    $db = cell_db();

    $tableCheck =
        $db->query(
            "SHOW TABLES LIKE 'cell_coverage_cells'"
        );

    if (
        !$tableCheck->fetchColumn()
    ) {
        llama_place_cell_json(
            [
                'ok' => true,
                'coverage' => [],
                'as_of_dates' => [],
                'truncated' => false,
                'data_ready' => false,
                'minimum_zoom' =>
                    $minimumZoom,
                'message' =>
                    'Cell coverage data has not been imported yet.',
            ]
        );
    }

    $providerBits = [];
    $combinedMask = 0;

    foreach (
        $providers
        as $provider
    ) {
        $bit =
            llama_place_cell_requested_bit(
                $provider,
                $technology,
                $environment
            );

        if ($bit <= 0) {
            continue;
        }

        $providerBits[$provider] =
            $bit;

        $combinedMask |=
            $bit;
    }

    if (
        !$providerBits
        || $combinedMask <= 0
    ) {
        llama_place_cell_json(
            [
                'ok' => true,
                'coverage' => [],
                'as_of_dates' => [],
                'truncated' => false,
                'data_ready' => true,
                'minimum_zoom' =>
                    $minimumZoom,
            ]
        );
    }

    $where = [
        '`center_lat` BETWEEN ? AND ?',
        '(`coverage_flags` & ?) <> 0',
    ];

    $params = [
        $south,
        $north,
        $combinedMask,
    ];

    if ($west <= $east) {
        $where[] =
            '`center_lng` BETWEEN ? AND ?';

        $params[] = $west;
        $params[] = $east;
    } else {
        $where[] =
            '(
                `center_lng` >= ?
                OR `center_lng` <= ?
            )';

        $params[] = $west;
        $params[] = $east;
    }

    $limit = 40000;

    $sql =
        'SELECT
            `h3_index`,
            `coverage_flags`
         FROM `cell_coverage_cells`
         WHERE '
        . implode(
            ' AND ',
            $where
        )
        . '
         LIMIT '
        . ($limit + 1);

    $stmt =
        $db->prepare($sql);

    $stmt->execute($params);

    $coverage = [];

    foreach (
        array_keys($providerBits)
        as $provider
    ) {
        $coverage[$provider] = [];
    }

    $rowCount = 0;
    $truncated = false;

    while (
        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
    ) {
        if (
            $rowCount
            >= $limit
        ) {
            $truncated = true;
            break;
        }

        $h3Index =
            llama_place_cell_binary_to_h3(
                $row['h3_index']
                ?? null
            );

        if ($h3Index === '') {
            continue;
        }

        $flags =
            (int) (
                $row['coverage_flags']
                ?? 0
            );

        foreach (
            $providerBits
            as $provider => $bit
        ) {
            if (
                ($flags & $bit)
                !== 0
            ) {
                $coverage[$provider][] =
                    $h3Index;
            }
        }

        $rowCount++;
    }

    $asOfDates = [];

    $ledgerCheck =
        $db->query(
            "SHOW TABLES LIKE 'cell_coverage_datasets'"
        );

    if (
        $ledgerCheck
            ->fetchColumn()
    ) {
        $providerPlaceholders =
            implode(
                ', ',
                array_fill(
                    0,
                    count($providers),
                    '?'
                )
            );

        $ledgerStmt =
            $db->prepare(
                'SELECT
                    provider_key,
                    MAX(fcc_as_of_date)
                        AS fcc_as_of_date
                 FROM cell_coverage_datasets
                 WHERE provider_key IN ('
                    . $providerPlaceholders
                    . ')
                   AND technology = ?
                   AND status = "current"
                 GROUP BY provider_key'
            );

        $ledgerStmt->execute([
            ...$providers,
            $technology,
        ]);

        while (
            $row =
                $ledgerStmt->fetch(
                    PDO::FETCH_ASSOC
                )
        ) {
            $provider =
                trim(
                    (string) (
                        $row['provider_key']
                        ?? ''
                    )
                );

            $date =
                trim(
                    (string) (
                        $row['fcc_as_of_date']
                        ?? ''
                    )
                );

            if (
                $provider !== ''
                && $date !== ''
            ) {
                $asOfDates[
                    $provider
                ] = $date;
            }
        }
    }

    llama_place_cell_json(
        [
            'ok' => true,
            'coverage' =>
                $coverage,
            'as_of_dates' =>
                $asOfDates,
            'count' =>
                $rowCount,
            'truncated' =>
                $truncated,
            'data_ready' =>
                true,
            'minimum_zoom' =>
                $minimumZoom,
        ]
    );

} catch (Throwable $e) {
    $reference =
        llama_log_caught_exception(
            $e,
            'api_place_cell_coverage'
        );

    llama_place_cell_json(
        [
            'ok' => false,
            'error' =>
                llama_error_message_with_reference(
                    'Unable to load cell coverage.',
                    $reference
                ),
            'reference' =>
                $reference,
        ],
        500
    );
}
