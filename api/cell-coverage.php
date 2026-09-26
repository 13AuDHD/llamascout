<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');


function llama_cell_json(
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


function llama_cell_float(
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


try {
    $viewer = current_user();

    $viewerUserId =
        is_array($viewer)
            ? (int) ($viewer['id'] ?? 0)
            : 0;

    if (
        $viewerUserId <= 0
        || !user_has_member_access(
            $viewerUserId
        )
    ) {
        llama_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Member map access is required.',
            ],
            403
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
                        static fn(string $value): string =>
                            strtolower(
                                trim($value)
                            ),
                        explode(
                            ',',
                            $providerInput
                        )
                    ),
                    static fn(string $value): bool =>
                        isset(
                            $allowedProviders[
                                $value
                            ]
                        )
                )
            )
        );

    if (!$providers) {
        llama_cell_json(
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
        llama_cell_json(
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
        llama_cell_json(
            [
                'ok' => false,
                'error' =>
                    'Invalid coverage environment.',
            ],
            400
        );
    }

    $north = llama_cell_float('north');
    $south = llama_cell_float('south');
    $east = llama_cell_float('east');
    $west = llama_cell_float('west');

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
        llama_cell_json(
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
        llama_cell_json(
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

    /*
     * Do not allow a spoofed zoom value to turn this endpoint into
     * a nationwide table scan.
     */
    if (
        $latitudeSpan > 6.0
        || $longitudeSpan > 8.0
    ) {
        llama_cell_json(
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

    $db = db();

    $tableCheck =
        $db->query(
            "SHOW TABLES LIKE 'cell_coverage_h3'"
        );

    if (!$tableCheck->fetchColumn()) {
        llama_cell_json(
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

    $technologyCode =
        $technology === '5g'
            ? 500
            : 400;

    $minimumDownload =
        $technology === '5g'
            ? 7
            : 5;

    $minimumUpload = 1;

    $providerPlaceholders =
        implode(
            ', ',
            array_fill(
                0,
                count($providers),
                '?'
            )
        );

    $where = [
        '`provider_key` IN ('
            . $providerPlaceholders
            . ')',
        '`technology_code` = ?',
        '`minimum_download` >= ?',
        '`minimum_upload` >= ?',
        '`center_lat` BETWEEN ? AND ?',
    ];

    $params = [
        ...$providers,
        $technologyCode,
        $minimumDownload,
        $minimumUpload,
        $south,
        $north,
    ];

    if ($environment === 'vehicle') {
        $where[] =
            '`environment` = 1';
    } else {
        $where[] =
            '`environment` IN (0, 1)';
    }

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
            `provider_key`,
            `h3_index`,
            MAX(`as_of_date`) AS `as_of_date`
        FROM `cell_coverage_h3`
        WHERE '
        . implode(
            ' AND ',
            $where
        )
        . '
        GROUP BY
            `provider_key`,
            `h3_index`
        LIMIT '
        . ($limit + 1);

    $stmt =
        $db->prepare($sql);

    $stmt->execute($params);

    $coverage = [];
    $asOfDates = [];

    foreach ($providers as $provider) {
        $coverage[$provider] = [];
    }

    $rowCount = 0;
    $truncated = false;

    while (
        $row =
            $stmt->fetch(PDO::FETCH_ASSOC)
    ) {
        if ($rowCount >= $limit) {
            $truncated = true;
            break;
        }

        $provider =
            (string) (
                $row['provider_key']
                ?? ''
            );

        $h3Index =
            trim(
                (string) (
                    $row['h3_index']
                    ?? ''
                )
            );

        if (
            !isset($coverage[$provider])
            || $h3Index === ''
        ) {
            continue;
        }

        $coverage[$provider][] =
            $h3Index;

        $asOfDate =
            trim(
                (string) (
                    $row['as_of_date']
                    ?? ''
                )
            );

        if (
            $asOfDate !== ''
            && (
                !isset(
                    $asOfDates[$provider]
                )
                || $asOfDate
                    > $asOfDates[$provider]
            )
        ) {
            $asOfDates[$provider] =
                $asOfDate;
        }

        $rowCount++;
    }

    llama_cell_json(
        [
            'ok' => true,
            'coverage' => $coverage,
            'as_of_dates' => $asOfDates,
            'count' => $rowCount,
            'truncated' => $truncated,
            'data_ready' => true,
            'minimum_zoom' =>
                $minimumZoom,
        ]
    );

} catch (Throwable $e) {
    $reference =
        llama_log_caught_exception(
            $e,
            'api_cell_coverage'
        );

    llama_cell_json(
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
