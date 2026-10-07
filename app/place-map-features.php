<?php

declare(strict_types=1);

const LLAMA_PLACE_MAP_FEATURE_GEOMETRY_POLYGON = 'polygon';
const LLAMA_PLACE_MAP_FEATURE_SRID = 4326;
const LLAMA_PLACE_MAP_FEATURE_CRS = 'EPSG:4326';

function llama_place_map_feature_types(): array
{
    return [
        'place_boundary' => 'Place boundary',
        'camping_area' => 'Camping area',
        'parking_area' => 'Parking area',
    ];
}

function llama_place_map_feature_source_types(): array
{
    return [
        'manual',
        'gps',
        'mixed',
    ];
}

function llama_place_map_feature_type_is_valid(string $type): bool
{
    return array_key_exists(
        strtolower(trim($type)),
        llama_place_map_feature_types()
    );
}

function llama_place_map_feature_validate_polygon(array $geometry): array
{
    if (
        strtolower((string) ($geometry['type'] ?? ''))
        !== 'polygon'
    ) {
        throw new InvalidArgumentException(
            'Mapped Areas V1 accepts polygon geometry only.'
        );
    }

    $coordinates = $geometry['coordinates'] ?? null;

    if (
        !is_array($coordinates)
        || count($coordinates) !== 1
        || !is_array($coordinates[0])
    ) {
        throw new InvalidArgumentException(
            'Polygon geometry must contain one outer boundary.'
        );
    }

    $ring = array_values($coordinates[0]);

    if (count($ring) < 3) {
        throw new InvalidArgumentException(
            'A mapped area needs at least three points.'
        );
    }

    if (count($ring) > 500) {
        throw new InvalidArgumentException(
            'This mapped area has too many points.'
        );
    }

    $normalized = [];

    foreach ($ring as $point) {
        if (
            !is_array($point)
            || count($point) < 2
            || !is_numeric($point[0])
            || !is_numeric($point[1])
        ) {
            throw new InvalidArgumentException(
                'Every mapped-area point needs a valid longitude and latitude.'
            );
        }

        /*
         * GeoJSON coordinate order is longitude, latitude.
         * Seven decimal places preserves more precision than normal
         * consumer GPS can provide while keeping output deterministic.
         */
        $longitude = round((float) $point[0], 7);
        $latitude = round((float) $point[1], 7);

        if (
            $longitude < -180
            || $longitude > 180
            || $latitude < -90
            || $latitude > 90
        ) {
            throw new InvalidArgumentException(
                'A mapped-area point is outside the valid coordinate range.'
            );
        }

        $normalized[] = [
            $longitude,
            $latitude,
        ];
    }

    $first = $normalized[0];
    $last = $normalized[count($normalized) - 1];

    if (
        abs($first[0] - $last[0]) > 0.0000001
        || abs($first[1] - $last[1]) > 0.0000001
    ) {
        $normalized[] = $first;
    }

    if (count($normalized) < 4) {
        throw new InvalidArgumentException(
            'A mapped area needs at least three distinct points.'
        );
    }

    return [
        'type' => 'Polygon',
        'coordinates' => [
            $normalized,
        ],
    ];
}

function llama_place_map_feature_polygon_wkt(array $geometry): string
{
    $geometry =
        llama_place_map_feature_validate_polygon(
            $geometry
        );

    $ring =
        (array) (
            $geometry['coordinates'][0]
            ?? []
        );

    $pairs = [];

    foreach ($ring as $point) {
        $pairs[] =
            number_format(
                (float) $point[0],
                7,
                '.',
                ''
            )
            . ' '
            . number_format(
                (float) $point[1],
                7,
                '.',
                ''
            );
    }

    return
        'POLYGON(('
        . implode(',', $pairs)
        . '))';
}

function llama_place_map_feature_validate_source_type(
    string $sourceType
): string {
    $sourceType =
        strtolower(
            trim($sourceType)
        );

    if (
        !in_array(
            $sourceType,
            llama_place_map_feature_source_types(),
            true
        )
    ) {
        return 'manual';
    }

    return $sourceType;
}

function llama_place_map_feature_validate_accuracy(
    mixed $accuracy
): ?float {
    if (
        $accuracy === null
        || $accuracy === ''
    ) {
        return null;
    }

    if (!is_numeric($accuracy)) {
        throw new InvalidArgumentException(
            'GPS accuracy must be numeric.'
        );
    }

    $accuracy = round((float) $accuracy, 2);

    if (
        $accuracy < 0
        || $accuracy > 100000
    ) {
        throw new InvalidArgumentException(
            'GPS accuracy is outside the supported range.'
        );
    }

    return $accuracy;
}

function llama_place_map_feature_validate_metadata(
    mixed $metadata,
    int $vertexCount
): array {
    if (!is_array($metadata)) {
        return [];
    }

    $clean = [
        'coordinate_system' =>
            LLAMA_PLACE_MAP_FEATURE_CRS,
    ];

    $vertices =
        $metadata['vertices']
        ?? [];

    if (is_array($vertices)) {
        $cleanVertices = [];

        foreach (
            array_slice(
                array_values($vertices),
                0,
                $vertexCount
            )
            as $vertex
        ) {
            if (!is_array($vertex)) {
                $cleanVertices[] = [
                    'source' => 'manual',
                ];

                continue;
            }

            $source =
                strtolower(
                    trim(
                        (string) (
                            $vertex['source']
                            ?? 'manual'
                        )
                    )
                );

            if (
                !in_array(
                    $source,
                    [
                        'manual',
                        'gps',
                    ],
                    true
                )
            ) {
                $source = 'manual';
            }

            $cleanVertex = [
                'source' => $source,
            ];

            if (
                isset($vertex['accuracy_m'])
                && is_numeric($vertex['accuracy_m'])
            ) {
                $accuracy =
                    round(
                        (float) $vertex['accuracy_m'],
                        2
                    );

                if (
                    $accuracy >= 0
                    && $accuracy <= 100000
                ) {
                    $cleanVertex['accuracy_m'] =
                        $accuracy;
                }
            }

            $capturedAt =
                trim(
                    (string) (
                        $vertex['captured_at']
                        ?? ''
                    )
                );

            if ($capturedAt !== '') {
                $cleanVertex['captured_at'] =
                    mb_substr(
                        $capturedAt,
                        0,
                        40
                    );
            }

            if (
                !empty(
                    $vertex['adjusted']
                )
            ) {
                $cleanVertex['adjusted'] =
                    true;

                $adjustedAt =
                    trim(
                        (string) (
                            $vertex['adjusted_at']
                            ?? ''
                        )
                    );

                if ($adjustedAt !== '') {
                    $cleanVertex['adjusted_at'] =
                        mb_substr(
                            $adjustedAt,
                            0,
                            40
                        );
                }
            }

            $cleanVertices[] =
                $cleanVertex;
        }

        while (
            count($cleanVertices)
            < $vertexCount
        ) {
            $cleanVertices[] = [
                'source' => 'manual',
            ];
        }

        $clean['vertices'] =
            $cleanVertices;
    }

    $gpsSummary =
        $metadata['gps_summary']
        ?? null;

    if (is_array($gpsSummary)) {
        $summary = [];

        foreach (
            [
                'average_accuracy_m',
                'best_accuracy_m',
                'worst_accuracy_m',
            ]
            as $key
        ) {
            if (
                isset($gpsSummary[$key])
                && is_numeric($gpsSummary[$key])
            ) {
                $value =
                    round(
                        (float) $gpsSummary[$key],
                        2
                    );

                if (
                    $value >= 0
                    && $value <= 100000
                ) {
                    $summary[$key] =
                        $value;
                }
            }
        }

        if ($summary) {
            $clean['gps_summary'] =
                $summary;
        }
    }

    return $clean;
}

function llama_place_map_features(
    PDO $db,
    int $placeId,
    bool $activeOnly = true
): array {
    if ($placeId < 1) {
        return [];
    }

    $sql =
        'SELECT
            id,
            place_id,
            feature_type,
            label,
            geometry_type,
            source_type,
            accuracy_m,
            metadata_json,
            created_by,
            updated_by,
            verified_at,
            created_at,
            updated_at,
            is_active,
            ST_SRID(geometry) AS geometry_srid,
            ST_AsGeoJSON(geometry, 7) AS geometry_geojson
         FROM place_map_features
         WHERE place_id = ?';

    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }

    $sql .= ' ORDER BY sort_order ASC, id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute([$placeId]);

    $features = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $geometry = json_decode(
            (string) ($row['geometry_geojson'] ?? ''),
            true
        );

        if (!is_array($geometry)) {
            continue;
        }

        $metadata = json_decode(
            (string) ($row['metadata_json'] ?? ''),
            true
        );

        $features[] = [
            'id' => (int) $row['id'],
            'place_id' => (int) $row['place_id'],
            'feature_type' => (string) $row['feature_type'],
            'label' => (string) ($row['label'] ?? ''),
            'geometry_type' => (string) $row['geometry_type'],
            'crs' => LLAMA_PLACE_MAP_FEATURE_CRS,
            'srid' => (int) ($row['geometry_srid'] ?? 0),
            'source_type' =>
                (string) (
                    $row['source_type']
                    ?? 'manual'
                ),
            'accuracy_m' =>
                $row['accuracy_m'] === null
                    ? null
                    : (float) $row['accuracy_m'],
            'metadata' =>
                is_array($metadata)
                    ? $metadata
                    : [],
            'created_by' =>
                $row['created_by'] === null
                    ? null
                    : (int) $row['created_by'],
            'updated_by' =>
                $row['updated_by'] === null
                    ? null
                    : (int) $row['updated_by'],
            'verified_at' => $row['verified_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'is_active' =>
                (int) $row['is_active'] === 1,
            'geometry' => $geometry,
        ];
    }

    return $features;
}

function llama_place_map_feature(
    PDO $db,
    int $featureId,
    int $placeId
): ?array {
    foreach (
        llama_place_map_features(
            $db,
            $placeId,
            false
        )
        as $feature
    ) {
        if ((int) $feature['id'] === $featureId) {
            return $feature;
        }
    }

    return null;
}

function llama_place_map_feature_save(
    PDO $db,
    int $placeId,
    int $userId,
    string $featureType,
    string $label,
    array $geometry,
    int $featureId = 0,
    string $sourceType = 'manual',
    mixed $accuracyM = null,
    mixed $metadata = []
): int {
    if ($placeId < 1 || $userId < 1) {
        throw new InvalidArgumentException(
            'A valid Place and user are required.'
        );
    }

    $featureType =
        strtolower(
            trim($featureType)
        );

    if (
        !llama_place_map_feature_type_is_valid(
            $featureType
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid mapped-area type.'
        );
    }

    $label = trim($label);

    if (mb_strlen($label) > 120) {
        throw new InvalidArgumentException(
            'Mapped-area labels can be up to 120 characters.'
        );
    }

    $geometry =
        llama_place_map_feature_validate_polygon(
            $geometry
        );

    $vertexCount =
        max(
            0,
            count(
                (array) (
                    $geometry['coordinates'][0]
                    ?? []
                )
            ) - 1
        );

    $sourceType =
        llama_place_map_feature_validate_source_type(
            $sourceType
        );

    $accuracyM =
        llama_place_map_feature_validate_accuracy(
            $accuracyM
        );

    $metadata =
        llama_place_map_feature_validate_metadata(
            $metadata,
            $vertexCount
        );

    $metadataJson =
        $metadata
            ? json_encode(
                $metadata,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
            : null;

    $geometryWkt =
        llama_place_map_feature_polygon_wkt(
            $geometry
        );

    if ($featureId > 0) {
        $existing =
            llama_place_map_feature(
                $db,
                $featureId,
                $placeId
            );

        if (!$existing) {
            throw new RuntimeException(
                'That mapped area could not be found.'
            );
        }

        $stmt = $db->prepare(
            'UPDATE place_map_features
             SET
                feature_type = ?,
                label = ?,
                geometry_type = ?,
                geometry = ST_GeomFromText(?, ?),
                source_type = ?,
                accuracy_m = ?,
                metadata_json = ?,
                updated_by = ?,
                verified_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE id = ?
               AND place_id = ?
             LIMIT 1'
        );

        $stmt->execute([
            $featureType,
            $label !== '' ? $label : null,
            LLAMA_PLACE_MAP_FEATURE_GEOMETRY_POLYGON,
            $geometryWkt,
            LLAMA_PLACE_MAP_FEATURE_SRID,
            $sourceType,
            $accuracyM,
            $metadataJson,
            $userId,
            $featureId,
            $placeId,
        ]);

        return $featureId;
    }

    $sortStmt = $db->prepare(
        'SELECT COALESCE(MAX(sort_order), 0) + 10
         FROM place_map_features
         WHERE place_id = ?'
    );

    $sortStmt->execute([$placeId]);

    $sortOrder =
        (int) $sortStmt->fetchColumn();

    $stmt = $db->prepare(
        'INSERT INTO place_map_features
        (
            place_id,
            feature_type,
            label,
            geometry_type,
            geometry,
            source_type,
            accuracy_m,
            metadata_json,
            created_by,
            updated_by,
            verified_at,
            sort_order,
            is_active,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ST_GeomFromText(?, ?),
            ?,
            ?,
            ?,
            ?,
            ?,
            UTC_TIMESTAMP(),
            ?,
            1,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
        )'
    );

    $stmt->execute([
        $placeId,
        $featureType,
        $label !== '' ? $label : null,
        LLAMA_PLACE_MAP_FEATURE_GEOMETRY_POLYGON,
        $geometryWkt,
        LLAMA_PLACE_MAP_FEATURE_SRID,
        $sourceType,
        $accuracyM,
        $metadataJson,
        $userId,
        $userId,
        $sortOrder,
    ]);

    return (int) $db->lastInsertId();
}

function llama_place_map_feature_delete(
    PDO $db,
    int $placeId,
    int $featureId,
    int $userId
): void {
    if (
        $placeId < 1
        || $featureId < 1
        || $userId < 1
    ) {
        throw new InvalidArgumentException(
            'A valid mapped area is required.'
        );
    }

    $stmt = $db->prepare(
        'UPDATE place_map_features
         SET
            is_active = 0,
            updated_by = ?,
            updated_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND place_id = ?
         LIMIT 1'
    );

    $stmt->execute([
        $userId,
        $featureId,
        $placeId,
    ]);
}
