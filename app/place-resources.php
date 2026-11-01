<?php

declare(strict_types=1);

require_once __DIR__ . '/place-report/report-targets.php';

/**
 * Resource identities are direct children of a Place.
 *
 * An optional Area relationship is geographic/grouping context only. It does
 * not change Resource ownership from Place -> Resource.
 */

function llama_place_resource_types(): array
{
    return [
        'potable_water' => 'Potable water',
        'water_fill' => 'Water fill',
        'dump_station' => 'Dump station',
        'toilet' => 'Toilet',
        'shower' => 'Shower',
        'trash' => 'Trash',
        'recycling' => 'Recycling',
        'ev_charging' => 'EV charging',
        'propane' => 'Propane',
        'laundry' => 'Laundry',
        'other' => 'Other resource',
    ];
}

function llama_place_resource_statuses(): array
{
    return [
        'unknown' => 'Unknown',
        'available' => 'Available',
        'temporarily_unavailable' => 'Temporarily unavailable',
        'seasonal' => 'Seasonal',
        'closed' => 'Closed',
    ];
}

function llama_place_resource_fee_statuses(): array
{
    return [
        'unknown' => 'Unknown',
        'free' => 'Free',
        'paid' => 'Paid',
        'varies' => 'Varies',
    ];
}

function llama_place_resource_access_types(): array
{
    return [
        'unknown' => 'Unknown',
        'public' => 'Public',
        'registered_guests' => 'Registered guests',
        'customers' => 'Customers',
        'permit' => 'Permit required',
        'staff' => 'Staff only',
        'other' => 'Other',
    ];
}

function llama_place_resource_type_label(?string $value): string
{
    $key = strtolower(trim((string) $value));

    return llama_place_resource_types()[$key]
        ?? trim((string) $value);
}

function llama_place_resource_validate_area(
    PDO $db,
    int $placeId,
    ?int $areaFeatureId
): ?int {
    $areaFeatureId = max(0, (int) ($areaFeatureId ?? 0));

    if ($areaFeatureId < 1) {
        return null;
    }

    llama_report_verified_target(
        $db,
        $placeId,
        'area',
        'map_feature',
        $areaFeatureId
    );

    return $areaFeatureId;
}

function llama_place_resource_coordinates_valid(
    mixed $latitude,
    mixed $longitude
): bool {
    if (
        !is_numeric($latitude)
        || !is_numeric($longitude)
    ) {
        return false;
    }

    $latitude = (float) $latitude;
    $longitude = (float) $longitude;

    return $latitude >= -90
        && $latitude <= 90
        && $longitude >= -180
        && $longitude <= 180
        && ($latitude !== 0.0 || $longitude !== 0.0);
}

/**
 * Read Resource identities owned by one Place.
 *
 * This function intentionally returns practical Resource facts only. It is not
 * a Scout Report renderer and does not duplicate Place amenity questions.
 */
function llama_place_resources(
    PDO $db,
    int $placeId,
    ?int $onlyResourceId = null,
    bool $activeOnly = true
): array {
    if (
        $placeId < 1
        || (
            $onlyResourceId !== null
            && $onlyResourceId < 1
        )
    ) {
        return [];
    }

    $sql =
        'SELECT
            r.id,
            r.place_id,
            r.area_feature_id,
            r.resource_type,
            r.resource_subtype,
            r.name,
            r.latitude,
            r.longitude,
            r.status,
            r.fee_status,
            r.fee_description,
            r.hours_text,
            r.access_type,
            r.access_notes,
            r.notes,
            r.source_provider,
            r.source_external_id,
            r.source_url,
            r.source_updated_at,
            r.verified_at,
            r.created_by,
            r.updated_by,
            r.sort_order,
            r.is_active,
            r.created_at,
            r.updated_at,
            area.label AS area_label,
            area.feature_type AS area_feature_type
         FROM place_resources AS r
         LEFT JOIN place_map_features AS area
            ON area.id = r.area_feature_id
           AND area.place_id = r.place_id
           AND area.feature_type IN ("camping_area", "parking_area")
           AND area.is_active = 1
         WHERE r.place_id = ?';

    $args = [$placeId];

    if ($activeOnly) {
        $sql .=
            ' AND r.is_active = 1';
    }

    if ($onlyResourceId !== null) {
        $sql .=
            ' AND r.id = ?';

        $args[] =
            $onlyResourceId;
    }

    $sql .=
        ' ORDER BY
            r.sort_order ASC,
            COALESCE(NULLIF(r.name, ""), r.resource_type) ASC,
            r.id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($args);

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
        ?: [];

    foreach ($rows as &$row) {
        $resourceType =
            strtolower(
                trim(
                    (string) (
                        $row['resource_type']
                        ?? ''
                    )
                )
            );

        $row['resource_type'] =
            $resourceType;

        $row['resource_type_label'] =
            llama_place_resource_type_label(
                $resourceType
            );

        $row['display_name'] =
            trim(
                (string) (
                    $row['name']
                    ?? ''
                )
            );

        if ($row['display_name'] === '') {
            $row['display_name'] =
                $row['resource_type_label']
                !== ''
                    ? $row['resource_type_label']
                    : 'Resource';
        }

        $row['has_coordinates'] =
            llama_place_resource_coordinates_valid(
                $row['latitude']
                    ?? null,
                $row['longitude']
                    ?? null
            );

        if (!$row['has_coordinates']) {
            $row['latitude'] = null;
            $row['longitude'] = null;
        } else {
            $row['latitude'] =
                (float) $row['latitude'];

            $row['longitude'] =
                (float) $row['longitude'];
        }

        if (
            (int) (
                $row['area_feature_id']
                ?? 0
            ) > 0
            && trim(
                (string) (
                    $row['area_label']
                    ?? ''
                )
            ) === ''
        ) {
            /*
             * Do not pretend a stale/deactivated Area link is active.
             * The Resource remains a direct child of Place.
             */
            $row['area_feature_id'] = null;
            $row['area_feature_type'] = null;
        }
    }

    unset($row);

    return $rows;
}

function llama_place_resource(
    PDO $db,
    int $placeId,
    int $resourceId
): array {
    $rows =
        llama_place_resources(
            $db,
            $placeId,
            $resourceId,
            true
        );

    return $rows[0]
        ?? [];
}

/**
 * Strip one Resource to the fields intended for ordinary public Places.
 *
 * The caller must still authorize the parent Place before using this data.
 */
function llama_place_resource_public_view(
    array $resource
): array {
    return [
        'id' =>
            (int) (
                $resource['id']
                ?? 0
            ),

        'place_id' =>
            (int) (
                $resource['place_id']
                ?? 0
            ),

        'area_feature_id' =>
            isset($resource['area_feature_id'])
                ? (int) $resource['area_feature_id']
                : null,

        'area_label' =>
            trim(
                (string) (
                    $resource['area_label']
                    ?? ''
                )
            ),

        'resource_type' =>
            (string) (
                $resource['resource_type']
                ?? ''
            ),

        'resource_type_label' =>
            (string) (
                $resource['resource_type_label']
                ?? ''
            ),

        'resource_subtype' =>
            trim(
                (string) (
                    $resource['resource_subtype']
                    ?? ''
                )
            ),

        'display_name' =>
            (string) (
                $resource['display_name']
                ?? 'Resource'
            ),

        'latitude' =>
            !empty($resource['has_coordinates'])
                ? (float) $resource['latitude']
                : null,

        'longitude' =>
            !empty($resource['has_coordinates'])
                ? (float) $resource['longitude']
                : null,

        'has_coordinates' =>
            !empty(
                $resource['has_coordinates']
            ),

        'status' =>
            (string) (
                $resource['status']
                ?? 'unknown'
            ),

        'fee_status' =>
            (string) (
                $resource['fee_status']
                ?? 'unknown'
            ),

        'fee_description' =>
            trim(
                (string) (
                    $resource['fee_description']
                    ?? ''
                )
            ),

        'hours_text' =>
            trim(
                (string) (
                    $resource['hours_text']
                    ?? ''
                )
            ),

        'access_type' =>
            trim(
                (string) (
                    $resource['access_type']
                    ?? ''
                )
            ),

        'access_notes' =>
            trim(
                (string) (
                    $resource['access_notes']
                    ?? ''
                )
            ),

        'notes' =>
            trim(
                (string) (
                    $resource['notes']
                    ?? ''
                )
            ),

        'source_provider' =>
            trim(
                (string) (
                    $resource['source_provider']
                    ?? ''
                )
            ),

        'source_updated_at' =>
            $resource['source_updated_at']
                ?? null,

        'verified_at' =>
            $resource['verified_at']
                ?? null,
    ];
}

function llama_place_public_resources(
    PDO $db,
    int $placeId
): array {
    return array_values(
        array_map(
            'llama_place_resource_public_view',
            llama_place_resources(
                $db,
                $placeId
            )
        )
    );
}
