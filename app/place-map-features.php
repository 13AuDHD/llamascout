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
        'camping_site' => 'Individual campsite',
    ];
}


function llama_place_map_area_use_options(string $featureType): array
{
    return match (strtolower(trim($featureType))) {
        'camping_area' => [
            'developed_campground' => 'Developed campground',
            'designated_camping' => 'Designated camping area',
            'dispersed_camping' => 'Dispersed camping area',
        ],

        'parking_area' => [
            'general_parking' => 'General parking',
            'overnight_vehicle_parking' => 'Overnight vehicle parking',
        ],

        default => [],
    };
}

function llama_place_map_fee_status_options(): array
{
    return [
        'free' => 'Free',
        'paid' => 'Paid',
        'varies' => 'Varies',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_overnight_status_options(): array
{
    return [
        'allowed' => 'Allowed',
        'prohibited' => 'Not allowed',
        'varies' => 'Varies',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_feature_validate_area_details(
    string $featureType,
    mixed $details
): array {
    if (!is_array($details)) {
        $details = [];
    }

    $featureType = strtolower(trim($featureType));

    if (
        $featureType === 'place_boundary'
        || $featureType === 'camping_site'
    ) {
        return [];
    }

    $areaUse = trim((string) ($details['area_use'] ?? ''));
    $feeStatus = trim((string) ($details['fee_status'] ?? ''));
    $overnightStatus =
        trim((string) ($details['overnight_status'] ?? ''));

    $useOptions =
        llama_place_map_area_use_options($featureType);

    if (
        $areaUse !== ''
        && !array_key_exists($areaUse, $useOptions)
    ) {
        throw new InvalidArgumentException(
            'Choose a valid mapped-area use.'
        );
    }

    if (
        $feeStatus !== ''
        && !array_key_exists(
            $feeStatus,
            llama_place_map_fee_status_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid cost status.'
        );
    }

    if ($featureType !== 'parking_area') {
        $overnightStatus = '';
    }

    if (
        $overnightStatus !== ''
        && !array_key_exists(
            $overnightStatus,
            llama_place_map_overnight_status_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid overnight vehicle-stay status.'
        );
    }

    return array_filter(
        [
            'area_use' =>
                $areaUse !== ''
                    ? $areaUse
                    : null,

            'fee_status' =>
                $feeStatus !== ''
                    ? $feeStatus
                    : null,

            'overnight_status' =>
                $overnightStatus !== ''
                    ? $overnightStatus
                    : null,
        ],
        static fn (mixed $value): bool =>
            $value !== null
    );
}

function llama_place_map_feature_area_details_save(
    PDO $db,
    int $featureId,
    int $userId,
    string $featureType,
    mixed $details
): void {
    $details =
        llama_place_map_feature_validate_area_details(
            $featureType,
            $details
        );

    if (!$details) {
        $stmt = $db->prepare(
            'DELETE FROM place_map_area_details
             WHERE feature_id = ?'
        );

        $stmt->execute([$featureId]);

        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_map_area_details
        (
            feature_id,
            area_use,
            fee_status,
            overnight_status,
            created_by,
            updated_by,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
        )
        ON DUPLICATE KEY UPDATE
            area_use = VALUES(area_use),
            fee_status = VALUES(fee_status),
            overnight_status = VALUES(overnight_status),
            updated_by = VALUES(updated_by),
            updated_at = UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $featureId,
        $details['area_use'] ?? null,
        $details['fee_status'] ?? null,
        $details['overnight_status'] ?? null,
        $userId,
        $userId,
    ]);
}

function llama_place_map_camping_site_type_options(): array
{
    return [
        'rv_site' => 'RV site',
        'tent_site' => 'Tent site',
        'mixed_site' => 'Mixed-use campsite',
        'vehicle_site' => 'Vehicle campsite',
        'group_site' => 'Group site',
        'other' => 'Other',
    ];
}

function llama_place_map_camping_site_parking_style_options(): array
{
    return [
        'pull_through' => 'Pull-through',
        'back_in' => 'Back-in',
        'pull_in' => 'Pull-in',
        'parallel' => 'Parallel',
        'other' => 'Other',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_camping_site_hookup_options(): array
{
    return [
        'full' => 'Full hookups',
        'electric_water' => 'Electric + water',
        'electric_only' => 'Electric only',
        'water_only' => 'Water only',
        'none' => 'No hookups',
        'varies' => 'Varies',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_camping_site_accessible_options(): array
{
    return [
        'yes' => 'Yes',
        'no' => 'No',
        'unknown' => 'Unknown',
    ];
}

function llama_place_map_feature_validate_site_details(
    PDO $db,
    int $placeId,
    string $featureType,
    mixed $details
): array {
    if ($featureType !== 'camping_site') {
        return [];
    }

    if (!is_array($details)) {
        $details = [];
    }

    $parentFeatureId =
        max(
            0,
            (int) (
                $details['camping_area_feature_id']
                ?? 0
            )
        );

    if ($parentFeatureId < 1) {
        throw new InvalidArgumentException(
            'Choose the Camping area that contains this campsite.'
        );
    }

    $parentStmt = $db->prepare(
        'SELECT id
         FROM place_map_features
         WHERE id = ?
           AND place_id = ?
           AND feature_type = ?
           AND is_active = 1
         LIMIT 1'
    );

    $parentStmt->execute([
        $parentFeatureId,
        $placeId,
        'camping_area',
    ]);

    if (!$parentStmt->fetchColumn()) {
        throw new InvalidArgumentException(
            'The selected parent Camping area is not available.'
        );
    }

    $siteCode =
        trim(
            (string) (
                $details['site_code']
                ?? ''
            )
        );

    if (mb_strlen($siteCode) > 60) {
        throw new InvalidArgumentException(
            'Campsite identifiers can be up to 60 characters.'
        );
    }

    $siteType =
        trim(
            (string) (
                $details['site_type']
                ?? ''
            )
        );

    if (
        $siteType !== ''
        && !array_key_exists(
            $siteType,
            llama_place_map_camping_site_type_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid campsite type.'
        );
    }

    $parkingStyle =
        trim(
            (string) (
                $details['parking_style']
                ?? ''
            )
        );

    if (
        $parkingStyle !== ''
        && !array_key_exists(
            $parkingStyle,
            llama_place_map_camping_site_parking_style_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid campsite parking style.'
        );
    }

    $hookupStatus =
        trim(
            (string) (
                $details['hookup_status']
                ?? ''
            )
        );

    if (
        $hookupStatus !== ''
        && !array_key_exists(
            $hookupStatus,
            llama_place_map_camping_site_hookup_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid campsite hookup status.'
        );
    }

    $accessibleStatus =
        trim(
            (string) (
                $details['accessible_status']
                ?? ''
            )
        );

    if (
        $accessibleStatus !== ''
        && !array_key_exists(
            $accessibleStatus,
            llama_place_map_camping_site_accessible_options()
        )
    ) {
        throw new InvalidArgumentException(
            'Choose a valid accessibility status.'
        );
    }

    return [
        'camping_area_feature_id' =>
            $parentFeatureId,

        'site_code' =>
            $siteCode !== ''
                ? $siteCode
                : null,

        'site_type' =>
            $siteType !== ''
                ? $siteType
                : null,

        'parking_style' =>
            $parkingStyle !== ''
                ? $parkingStyle
                : null,

        'hookup_status' =>
            $hookupStatus !== ''
                ? $hookupStatus
                : null,

        'accessible_status' =>
            $accessibleStatus !== ''
                ? $accessibleStatus
                : null,
    ];
}

function llama_place_map_feature_site_details_save(
    PDO $db,
    int $featureId,
    int $userId,
    string $featureType,
    array $details
): void {
    if ($featureType !== 'camping_site') {
        $stmt = $db->prepare(
            'DELETE FROM place_map_camping_sites
             WHERE feature_id = ?'
        );

        $stmt->execute([$featureId]);

        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_map_camping_sites
        (
            feature_id,
            camping_area_feature_id,
            site_code,
            site_type,
            parking_style,
            hookup_status,
            accessible_status,
            created_by,
            updated_by,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            UTC_TIMESTAMP(),
            UTC_TIMESTAMP()
        )
        ON DUPLICATE KEY UPDATE
            camping_area_feature_id =
                VALUES(camping_area_feature_id),
            site_code =
                VALUES(site_code),
            site_type =
                VALUES(site_type),
            parking_style =
                VALUES(parking_style),
            hookup_status =
                VALUES(hookup_status),
            accessible_status =
                VALUES(accessible_status),
            updated_by =
                VALUES(updated_by),
            updated_at =
                UTC_TIMESTAMP()'
    );

    $stmt->execute([
        $featureId,
        $details['camping_area_feature_id'],
        $details['site_code'],
        $details['site_type'],
        $details['parking_style'],
        $details['hookup_status'],
        $details['accessible_status'],
        $userId,
        $userId,
    ]);
}

function llama_place_map_feature_rate_type_options(): array
{
    return [
        'standard' => 'Standard nightly',
        'weekday' => 'Weekday',
        'weekend' => 'Weekend',
        'holiday' => 'Holiday',
        'seasonal' => 'Seasonal',
    ];
}

function llama_place_map_feature_rate_feature_type_supported(
    string $featureType
): bool {
    return in_array(
        strtolower(trim($featureType)),
        [
            'camping_area',
            'camping_site',
        ],
        true
    );
}

function llama_place_map_feature_validate_month_day(
    mixed $value,
    string $label
): ?string {
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^(\\d{2})-(\\d{2})$/', $value, $matches)) {
        throw new InvalidArgumentException(
            $label . ' must use MM-DD format.'
        );
    }

    $month = (int) $matches[1];
    $day = (int) $matches[2];

    if (!checkdate($month, $day, 2000)) {
        throw new InvalidArgumentException(
            $label . ' is not a valid month and day.'
        );
    }

    return sprintf('%02d-%02d', $month, $day);
}

function llama_place_map_feature_validate_rates(
    string $featureType,
    mixed $rates
): array {
    if (!llama_place_map_feature_rate_feature_type_supported($featureType)) {
        return [];
    }

    if (!is_array($rates)) {
        return [];
    }

    if (count($rates) > 50) {
        throw new InvalidArgumentException(
            'A mapped area can have up to 50 rates.'
        );
    }

    $typeOptions =
        llama_place_map_feature_rate_type_options();

    $clean = [];

    foreach (array_values($rates) as $rate) {
        if (!is_array($rate)) {
            continue;
        }

        $rateType =
            strtolower(
                trim(
                    (string) (
                        $rate['rate_type']
                        ?? 'standard'
                    )
                )
            );

        if (!array_key_exists($rateType, $typeOptions)) {
            throw new InvalidArgumentException(
                'Choose a valid rate type.'
            );
        }

        $label =
            trim(
                (string) (
                    $rate['label']
                    ?? ''
                )
            );

        if (mb_strlen($label) > 80) {
            throw new InvalidArgumentException(
                'Rate labels can be up to 80 characters.'
            );
        }

        $amount =
            $rate['amount']
            ?? null;

        if (
            $amount === null
            || $amount === ''
            || !is_numeric($amount)
        ) {
            throw new InvalidArgumentException(
                'Every rate needs a valid nightly amount.'
            );
        }

        $amount = round((float) $amount, 2);

        if ($amount < 0 || $amount > 999999.99) {
            throw new InvalidArgumentException(
                'A rate amount is outside the supported range.'
            );
        }

        $seasonStart =
            llama_place_map_feature_validate_month_day(
                $rate['season_start']
                ?? null,
                'Season start'
            );

        $seasonEnd =
            llama_place_map_feature_validate_month_day(
                $rate['season_end']
                ?? null,
                'Season end'
            );

        if (
            $rateType === 'seasonal'
            && (
                $seasonStart === null
                || $seasonEnd === null
            )
        ) {
            throw new InvalidArgumentException(
                'Seasonal rates need both a season start and season end.'
            );
        }

        if ($rateType !== 'seasonal') {
            $seasonStart = null;
            $seasonEnd = null;
        }

        $notes =
            trim(
                (string) (
                    $rate['notes']
                    ?? ''
                )
            );

        if (mb_strlen($notes) > 240) {
            throw new InvalidArgumentException(
                'Rate notes can be up to 240 characters.'
            );
        }

        $clean[] = [
            'rate_type' => $rateType,
            'label' => $label !== '' ? $label : null,
            'amount' => $amount,
            'currency' => 'USD',
            'season_start' => $seasonStart,
            'season_end' => $seasonEnd,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    return $clean;
}

function llama_place_map_feature_rates_save(
    PDO $db,
    int $featureId,
    int $userId,
    string $featureType,
    array $rates
): void {
    $deleteStmt = $db->prepare(
        'DELETE FROM place_map_feature_rates
         WHERE feature_id = ?'
    );

    $deleteStmt->execute([$featureId]);

    if (!llama_place_map_feature_rate_feature_type_supported($featureType)) {
        return;
    }

    if (!$rates) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO place_map_feature_rates
        (
            feature_id,
            rate_type,
            label,
            amount,
            currency,
            season_start,
            season_end,
            notes,
            sort_order,
            is_active,
            created_by,
            updated_by,
            created_at,
            updated_at
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?,
            UTC_TIMESTAMP(), UTC_TIMESTAMP()
        )'
    );

    foreach ($rates as $index => $rate) {
        $stmt->execute([
            $featureId,
            $rate['rate_type'],
            $rate['label'],
            $rate['amount'],
            $rate['currency'],
            $rate['season_start'],
            $rate['season_end'],
            $rate['notes'],
            ($index + 1) * 10,
            $userId,
            $userId,
        ]);
    }
}

function llama_place_map_feature_rate_summary(array $rates): array
{
    if (!$rates) {
        return [];
    }

    $amounts = [];

    foreach ($rates as $rate) {
        if (isset($rate['amount']) && is_numeric($rate['amount'])) {
            $amounts[] = (float) $rate['amount'];
        }
    }

    if (!$amounts) {
        return [];
    }

    return [
        'minimum' => min($amounts),
        'maximum' => max($amounts),
        'currency' => 'USD',
        'count' => count($rates),
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
            place_map_features.id AS id,
            place_map_features.place_id AS place_id,
            place_map_features.feature_type AS feature_type,
            place_map_features.label AS label,
            place_map_features.geometry_type AS geometry_type,
            place_map_features.source_type AS source_type,
            place_map_features.accuracy_m AS accuracy_m,
            place_map_features.metadata_json AS metadata_json,

            area_details.area_use AS area_use,
            area_details.fee_status AS fee_status,
            area_details.overnight_status AS overnight_status,

            site_details.camping_area_feature_id
                AS camping_area_feature_id,
            site_details.site_code AS site_code,
            site_details.site_type AS site_type,
            site_details.parking_style AS parking_style,
            site_details.hookup_status AS hookup_status,
            site_details.accessible_status
                AS accessible_status,

            parent_area.label AS parent_area_label,

            place_map_features.created_by AS created_by,
            place_map_features.updated_by AS updated_by,
            place_map_features.verified_at AS verified_at,
            place_map_features.created_at AS created_at,
            place_map_features.updated_at AS updated_at,
            place_map_features.is_active AS is_active,

            ST_SRID(place_map_features.geometry)
                AS geometry_srid,

            ST_AsGeoJSON(
                place_map_features.geometry,
                7
            ) AS geometry_geojson

         FROM place_map_features

         LEFT JOIN place_map_area_details AS area_details
            ON area_details.feature_id =
                place_map_features.id

         LEFT JOIN place_map_camping_sites AS site_details
            ON site_details.feature_id =
                place_map_features.id

         LEFT JOIN place_map_features AS parent_area
            ON parent_area.id =
                site_details.camping_area_feature_id

         WHERE place_map_features.place_id = ?';

    if ($activeOnly) {
        $sql .=
            ' AND place_map_features.is_active = 1';
    }

    $sql .=
        ' ORDER BY
            place_map_features.sort_order ASC,
            place_map_features.id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute([$placeId]);

    $features = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        ?: []
        as $row
    ) {
        $geometry =
            json_decode(
                (string) (
                    $row['geometry_geojson']
                    ?? ''
                ),
                true
            );

        if (!is_array($geometry)) {
            continue;
        }

        $metadata =
            json_decode(
                (string) (
                    $row['metadata_json']
                    ?? ''
                ),
                true
            );

        $features[] = [
            'id' =>
                (int) $row['id'],

            'place_id' =>
                (int) $row['place_id'],

            'feature_type' =>
                (string) $row['feature_type'],

            'label' =>
                (string) (
                    $row['label']
                    ?? ''
                ),

            'geometry_type' =>
                (string) $row['geometry_type'],

            'crs' =>
                LLAMA_PLACE_MAP_FEATURE_CRS,

            'srid' =>
                (int) (
                    $row['geometry_srid']
                    ?? 0
                ),

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

            'area_details' =>
                array_filter(
                    [
                        'area_use' =>
                            $row['area_use']
                            ?? null,

                        'fee_status' =>
                            $row['fee_status']
                            ?? null,

                        'overnight_status' =>
                            $row['overnight_status']
                            ?? null,
                    ],
                    static fn (
                        mixed $value
                    ): bool =>
                        $value !== null
                        && $value !== ''
                ),

            'site_details' =>
                array_filter(
                    [
                        'camping_area_feature_id' =>
                            $row[
                                'camping_area_feature_id'
                            ]
                            === null
                                ? null
                                : (int) $row[
                                    'camping_area_feature_id'
                                ],

                        'parent_area_label' =>
                            $row['parent_area_label']
                            ?? null,

                        'site_code' =>
                            $row['site_code']
                            ?? null,

                        'site_type' =>
                            $row['site_type']
                            ?? null,

                        'parking_style' =>
                            $row['parking_style']
                            ?? null,

                        'hookup_status' =>
                            $row['hookup_status']
                            ?? null,

                        'accessible_status' =>
                            $row['accessible_status']
                            ?? null,
                    ],
                    static fn (
                        mixed $value
                    ): bool =>
                        $value !== null
                        && $value !== ''
                ),

            'created_by' =>
                $row['created_by'] === null
                    ? null
                    : (int) $row['created_by'],

            'updated_by' =>
                $row['updated_by'] === null
                    ? null
                    : (int) $row['updated_by'],

            'verified_at' =>
                $row['verified_at'],

            'created_at' =>
                $row['created_at'],

            'updated_at' =>
                $row['updated_at'],

            'is_active' =>
                (int) $row['is_active']
                === 1,

            'geometry' =>
                $geometry,
        ];
    }

    if (!$features) {
        return [];
    }

    $featureIds =
        array_values(
            array_map(
                static fn (array $feature): int =>
                    (int) $feature['id'],
                $features
            )
        );

    $rateGroups = [];

    if ($featureIds) {
        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($featureIds),
                    '?'
                )
            );

        $rateStmt = $db->prepare(
            'SELECT
                id,
                feature_id,
                rate_type,
                label,
                amount,
                currency,
                season_start,
                season_end,
                notes,
                sort_order
             FROM place_map_feature_rates
             WHERE is_active = 1
               AND feature_id IN ('
             . $placeholders
             . ')
             ORDER BY feature_id ASC, sort_order ASC, id ASC'
        );

        $rateStmt->execute($featureIds);

        foreach (
            $rateStmt->fetchAll(PDO::FETCH_ASSOC)
            ?: []
            as $rateRow
        ) {
            $featureId =
                (int) $rateRow['feature_id'];

            $rateGroups[$featureId][] = [
                'id' => (int) $rateRow['id'],
                'rate_type' => (string) $rateRow['rate_type'],
                'label' => (string) ($rateRow['label'] ?? ''),
                'amount' => (float) $rateRow['amount'],
                'currency' => (string) ($rateRow['currency'] ?? 'USD'),
                'season_start' => $rateRow['season_start'],
                'season_end' => $rateRow['season_end'],
                'notes' => (string) ($rateRow['notes'] ?? ''),
            ];
        }
    }

    foreach ($features as &$feature) {
        $featureId =
            (int) $feature['id'];

        $feature['rates'] =
            array_values(
                $rateGroups[$featureId]
                ?? []
            );

        $feature['rate_summary'] =
            llama_place_map_feature_rate_summary(
                $feature['rates']
            );

        if ($feature['feature_type'] === 'camping_site') {
            $parentId =
                (int) (
                    $feature['site_details']['camping_area_feature_id']
                    ?? 0
                );

            $feature['parent_rates'] =
                array_values(
                    $rateGroups[$parentId]
                    ?? []
                );

            if ($feature['rates']) {
                $siteRateTypes =
                    array_fill_keys(
                        array_map(
                            static fn (array $rate): string =>
                                (string) ($rate['rate_type'] ?? ''),
                            $feature['rates']
                        ),
                        true
                    );

                $inheritedRates =
                    array_values(
                        array_filter(
                            $feature['parent_rates'],
                            static fn (array $rate): bool =>
                                !isset(
                                    $siteRateTypes[
                                        (string) (
                                            $rate['rate_type']
                                            ?? ''
                                        )
                                    ]
                                )
                        )
                    );

                $feature['effective_rates'] =
                    array_values(
                        array_merge(
                            $inheritedRates,
                            $feature['rates']
                        )
                    );

                $feature['effective_rate_source'] =
                    $feature['parent_rates']
                        ? 'site_override'
                        : 'site';
            } else {
                $feature['effective_rates'] =
                    $feature['parent_rates'];

                $feature['effective_rate_source'] =
                    $feature['parent_rates']
                        ? 'camping_area'
                        : '';
            }

            $feature['effective_rate_summary'] =
                llama_place_map_feature_rate_summary(
                    $feature['effective_rates']
                );
        } else {
            $feature['parent_rates'] = [];
            $feature['effective_rates'] = $feature['rates'];
            $feature['effective_rate_source'] =
                $feature['rates']
                    ? 'feature'
                    : '';
            $feature['effective_rate_summary'] =
                $feature['rate_summary'];
        }
    }
    unset($feature);

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
    mixed $metadata = [],
    mixed $areaDetails = [],
    mixed $siteDetails = [],
    mixed $rates = []
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

    $areaDetails =
        llama_place_map_feature_validate_area_details(
            $featureType,
            $areaDetails
        );

    $siteDetails =
        llama_place_map_feature_validate_site_details(
            $db,
            $placeId,
            $featureType,
            $siteDetails
        );

    $rates =
        llama_place_map_feature_validate_rates(
            $featureType,
            $rates
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

        llama_place_map_feature_area_details_save(
            $db,
            $featureId,
            $userId,
            $featureType,
            $areaDetails
        );

        llama_place_map_feature_site_details_save(
            $db,
            $featureId,
            $userId,
            $featureType,
            $siteDetails
        );

        llama_place_map_feature_rates_save(
            $db,
            $featureId,
            $userId,
            $featureType,
            $rates
        );

        return $featureId;
    }

    $sortStmt = $db->prepare(
        'SELECT
            COALESCE(
                MAX(sort_order),
                0
            ) + 10
         FROM place_map_features
         WHERE place_id = ?'
    );

    $sortStmt->execute([
        $placeId,
    ]);

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
        $label !== ''
            ? $label
            : null,
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

    $newFeatureId =
        (int) $db->lastInsertId();

    llama_place_map_feature_area_details_save(
        $db,
        $newFeatureId,
        $userId,
        $featureType,
        $areaDetails
    );

    llama_place_map_feature_site_details_save(
        $db,
        $newFeatureId,
        $userId,
        $featureType,
        $siteDetails
    );

    llama_place_map_feature_rates_save(
        $db,
        $newFeatureId,
        $userId,
        $featureType,
        $rates
    );

    return $newFeatureId;
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
