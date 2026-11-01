<?php

declare(strict_types=1);

require_once __DIR__ . '/resource-media.php';

/**
 * V3 standalone Resource model.
 *
 * A Resource is its own map entity. It does not require a Place, Area, or Site
 * parent. A Resource can provide one or more services.
 */

function llama_resource_slug(string $name): string
{
    $slug =
        strtolower(
            trim(
                preg_replace(
                    '/[^a-z0-9]+/i',
                    '-',
                    $name
                )
                ?? ''
            )
        );

    return trim(
        mb_substr($slug, 0, 190),
        '-'
    );
}

function llama_resource_types(): array
{
    return [
        'convenience_store' => 'Convenience store',
        'water_station' => 'Water station',
        'dump_station' => 'Dump station',
        'restroom_facility' => 'Restroom facility',
        'shower_facility' => 'Shower facility',
        'laundry' => 'Laundry',
        'propane_fill' => 'Propane fill',
        'ev_charging' => 'EV charging',
        'fuel_station' => 'Fuel station',
        'other' => 'Other resource',
    ];
}

function llama_resource_service_types(): array
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
        'fuel' => 'Fuel',
        'air' => 'Air',
        'ice' => 'Ice',
        'groceries' => 'Groceries',
        'other' => 'Other service',
    ];
}

function llama_resource_statuses(): array
{
    return [
        'unknown' => 'Unknown',
        'available' => 'Available',
        'temporarily_unavailable' => 'Temporarily unavailable',
        'seasonal' => 'Seasonal',
        'closed' => 'Closed',
    ];
}

function llama_resource_fee_statuses(): array
{
    return [
        'unknown' => 'Unknown',
        'free' => 'Free',
        'paid' => 'Paid',
        'varies' => 'Varies',
    ];
}

function llama_resource_type_label(?string $value): string
{
    $key = strtolower(trim((string) $value));

    return llama_resource_types()[$key]
        ?? trim((string) $value);
}

function llama_resource_service_type_label(?string $value): string
{
    $key = strtolower(trim((string) $value));

    return llama_resource_service_types()[$key]
        ?? trim((string) $value);
}

function llama_resource_coordinates_valid(
    mixed $latitude,
    mixed $longitude
): bool {
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
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

function llama_resource_services(
    PDO $db,
    int $resourceId,
    bool $activeOnly = true
): array {
    if ($resourceId < 1) {
        return [];
    }

    $sql =
        'SELECT
            id,
            resource_id,
            service_type,
            status,
            fee_status,
            fee_description,
            access_notes,
            notes,
            sort_order,
            is_active,
            created_at,
            updated_at
         FROM resource_services
         WHERE resource_id = ?';

    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }

    $sql .=
        ' ORDER BY
            sort_order ASC,
            service_type ASC,
            id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute([$resourceId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['service_type'] =
            strtolower(
                trim(
                    (string) (
                        $row['service_type']
                        ?? ''
                    )
                )
            );

        $row['service_type_label'] =
            llama_resource_service_type_label(
                $row['service_type']
            );
    }

    unset($row);

    return $rows;
}

function llama_resources(
    PDO $db,
    ?int $onlyResourceId = null,
    bool $activeOnly = true
): array {
    if ($onlyResourceId !== null && $onlyResourceId < 1) {
        return [];
    }

    $sql =
        'SELECT
            id,
            resource_type,
            name,
            slug,
            latitude,
            longitude,
            street_address,
            city,
            state_region,
            postal_code,
            country_code,
            phone,
            website_url,
            hours_text,
            status,
            notes,
            source_provider,
            source_external_id,
            source_url,
            source_updated_at,
            verified_at,
            created_by,
            updated_by,
            is_active,
            created_at,
            updated_at
         FROM resources
         WHERE 1 = 1';

    $args = [];

    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }

    if ($onlyResourceId !== null) {
        $sql .= ' AND id = ?';
        $args[] = $onlyResourceId;
    }

    $sql .=
        ' ORDER BY
            name ASC,
            id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($args);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['resource_type'] =
            strtolower(
                trim(
                    (string) (
                        $row['resource_type']
                        ?? ''
                    )
                )
            );

        $row['resource_type_label'] =
            llama_resource_type_label(
                $row['resource_type']
            );

        $row['slug'] =
            trim(
                (string) (
                    $row['slug']
                    ?? ''
                )
            );

        $row['has_coordinates'] =
            llama_resource_coordinates_valid(
                $row['latitude'] ?? null,
                $row['longitude'] ?? null
            );

        if ($row['has_coordinates']) {
            $row['latitude'] = (float) $row['latitude'];
            $row['longitude'] = (float) $row['longitude'];
        } else {
            $row['latitude'] = null;
            $row['longitude'] = null;
        }

        $resourceId =
            (int) ($row['id'] ?? 0);

        $row['services'] =
            llama_resource_services(
                $db,
                $resourceId,
                $activeOnly
            );

        $row['images'] =
            llama_resource_images(
                $db,
                $resourceId
            );

        $row['featured_image'] =
            $row['images'][0]
            ?? [];
    }

    unset($row);

    return $rows;
}

function llama_resource_by_slug(
    PDO $db,
    string $slug
): array {
    $slug = trim($slug);

    if ($slug === '') {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT id
         FROM resources
         WHERE slug = ?
           AND is_active = 1
         LIMIT 1'
    );

    $stmt->execute([$slug]);

    $resourceId =
        (int) (
            $stmt->fetchColumn()
            ?: 0
        );

    return $resourceId > 0
        ? llama_resource(
            $db,
            $resourceId
        )
        : [];
}

function llama_resource(
    PDO $db,
    int $resourceId
): array {
    $rows =
        llama_resources(
            $db,
            $resourceId,
            true
        );

    return $rows[0] ?? [];
}

/**
 * Public Resource projection.
 *
 * Resource identity and practical services are intended to be independently
 * discoverable on the map. Internal ownership/source identifiers stay private.
 */
function llama_resource_public_view(array $resource): array
{
    $services = [];

    foreach ((array) ($resource['services'] ?? []) as $service) {
        if (!is_array($service)) {
            continue;
        }

        $services[] = [
            'service_type' =>
                (string) (
                    $service['service_type']
                    ?? ''
                ),
            'service_type_label' =>
                (string) (
                    $service['service_type_label']
                    ?? ''
                ),
            'status' =>
                (string) (
                    $service['status']
                    ?? 'unknown'
                ),
            'fee_status' =>
                (string) (
                    $service['fee_status']
                    ?? 'unknown'
                ),
            'fee_description' =>
                trim(
                    (string) (
                        $service['fee_description']
                        ?? ''
                    )
                ),
            'access_notes' =>
                trim(
                    (string) (
                        $service['access_notes']
                        ?? ''
                    )
                ),
            'notes' =>
                trim(
                    (string) (
                        $service['notes']
                        ?? ''
                    )
                ),
        ];
    }

    return [
        'id' =>
            (int) (
                $resource['id']
                ?? 0
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
        'name' =>
            trim(
                (string) (
                    $resource['name']
                    ?? ''
                )
            ),
        'slug' =>
            trim(
                (string) (
                    $resource['slug']
                    ?? ''
                )
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
            !empty($resource['has_coordinates']),
        'street_address' =>
            trim(
                (string) (
                    $resource['street_address']
                    ?? ''
                )
            ),
        'city' =>
            trim(
                (string) (
                    $resource['city']
                    ?? ''
                )
            ),
        'state_region' =>
            trim(
                (string) (
                    $resource['state_region']
                    ?? ''
                )
            ),
        'postal_code' =>
            trim(
                (string) (
                    $resource['postal_code']
                    ?? ''
                )
            ),
        'country_code' =>
            strtoupper(
                trim(
                    (string) (
                        $resource['country_code']
                        ?? ''
                    )
                )
            ),
        'phone' =>
            trim(
                (string) (
                    $resource['phone']
                    ?? ''
                )
            ),
        'website_url' =>
            trim(
                (string) (
                    $resource['website_url']
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
        'status' =>
            (string) (
                $resource['status']
                ?? 'unknown'
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
        'featured_image' =>
            !empty($resource['featured_image']['src'])
                ? [
                    'src' =>
                        llama_resource_image_url(
                            $resource['featured_image']['src']
                        ),
                    'alt_text' =>
                        trim(
                            (string) (
                                $resource['featured_image']['alt_text']
                                ?? $resource['name']
                                ?? ''
                            )
                        ),
                ]
                : null,
        'services' =>
            $services,
    ];
}

function llama_public_resources(PDO $db): array
{
    return array_values(
        array_map(
            'llama_resource_public_view',
            llama_resources($db)
        )
    );
}
