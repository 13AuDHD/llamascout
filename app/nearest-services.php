<?php

declare(strict_types=1);

require_once __DIR__ . '/resources.php';

/**
 * Calculated map distances are not Scout observations. Never write them
 * into the canonical answer store or award contribution points for them.
 */
function llama_nearest_service_categories(): array
{
    return [
        'nearest_town' => 'Nearest town',
        'nearest_fuel' => 'Nearest fuel',
        'nearest_ev_charging' => 'Nearest EV charging',
        'nearest_alcohol_sales' => 'Nearest alcohol sales',
        'nearest_propane' => 'Nearest propane',
        'nearest_grocery' => 'Nearest grocery',
        'nearest_water' => 'Nearest potable water',
        'nearest_toilet' => 'Nearest public toilet',
        'nearest_hospital' => 'Nearest hospital / emergency care',
    ];
}

function llama_nearest_service_keys(): array
{
    return [
        'fuel' => 'nearest_fuel',
        'ev_charging' => 'nearest_ev_charging',
        'propane' => 'nearest_propane',
        'groceries' => 'nearest_grocery',
        'potable_water' => 'nearest_water',
        'toilet' => 'nearest_toilet',
    ];
}

/** Great-circle distance in international miles, including antimeridian wrap. */
function llama_nearest_distance_miles(float $lat, float $lng, float $otherLat, float $otherLng): float
{
    $a = sin(deg2rad($otherLat - $lat) / 2) ** 2
        + cos(deg2rad($lat)) * cos(deg2rad($otherLat))
        * sin(deg2rad($otherLng - $lng) / 2) ** 2;
    return 3958.7613 * 2 * asin(sqrt(max(0.0, min(1.0, $a))));
}

function llama_nearest_service_results(float $lat, float $lng, iterable $rows, float $radius = 100.0): array
{
    $matches = [];
    $keys = llama_nearest_service_keys();
    foreach ($rows as $row) {
        $key = $keys[$row['service_type'] ?? ''] ?? null;
        if ($key === null || !llama_resource_coordinates_valid($row['latitude'] ?? null, $row['longitude'] ?? null)) continue;
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') continue;
        $distance = llama_nearest_distance_miles($lat, $lng, (float) $row['latitude'], (float) $row['longitude']);
        if ($distance > $radius) continue;
        $id = (int) ($row['id'] ?? 0);
        if (isset($matches[$key]) && ($distance > $matches[$key]['distance_miles']
            || ($distance === $matches[$key]['distance_miles'] && $id >= $matches[$key]['resource_id']))) continue;
        $matches[$key] = [
            'resource_id' => $id,
            'name' => $name,
            'distance_miles' => $distance,
            'source' => 'Llama Scout',
            'facility' => (int) ($row['parent_place_id'] ?? 0) > 0,
            'status' => (string) ($row['status'] ?? 'unknown'),
        ];
    }
    $results = [];
    foreach (llama_nearest_service_categories() as $key => $label) {
        $match = $matches[$key] ?? null;
        if ($match !== null) $match['distance_miles'] = round($match['distance_miles'], 3);
        $results[] = [
            'key' => $key, 'label' => $label, 'match' => $match,
            'supported' => in_array($key, $keys, true),
        ];
    }
    return $results;
}

/**
 * Search approved mapped Resources, plus Facilities belonging to this
 * published parent only. A facility from another Place may be restricted.
 * Do not treat water_fill as confirmed potable water or generic stores as
 * groceries. No unapproved or closed records and no external network calls.
 */
function llama_nearest_services(PDO $db, mixed $latitude, mixed $longitude, int $parentPlaceId = 0): array
{
    $base = ['method' => 'straight_line', 'radius_miles' => 100, 'services' => []];
    if (!llama_resource_coordinates_valid($latitude, $longitude)) {
        return $base + ['state' => 'no_coordinates'];
    }
    $lat = (float) $latitude;
    $lng = (float) $longitude;
    $parentReady = llama_resource_parent_place_ready($db);
    // 100 miles is at most 1.45 degrees latitude. Use a generous bound and
    // exact great-circle distance for the final ranking/filter.
    $sql = 'SELECT r.id, r.name, r.latitude, r.longitude, '
        . ($parentReady ? 'r.parent_place_id' : 'NULL AS parent_place_id')
        . ', s.service_type,
            CASE WHEN r.status = "seasonal" OR s.status = "seasonal" THEN "seasonal"
                 WHEN r.status = "available" AND s.status = "available" THEN "available"
                 ELSE "unknown" END AS status
         FROM resources r INNER JOIN resource_services s ON s.resource_id = r.id
         WHERE r.is_active = 1 AND s.is_active = 1
           AND r.status IN ("available", "seasonal", "unknown")
           AND s.status IN ("available", "seasonal", "unknown")
           AND r.latitude BETWEEN ? AND ?
           AND r.longitude BETWEEN -180 AND 180
           AND s.service_type IN ("fuel", "ev_charging", "propane", "groceries", "potable_water", "toilet")';
    $params = [max(-90, $lat - 1.5), min(90, $lat + 1.5)];
    if ($parentReady) {
        $sql .= ' AND (r.parent_place_id IS NULL' . ($parentPlaceId > 0 ? ' OR r.parent_place_id = ?' : '') . ')';
        if ($parentPlaceId > 0) $params[] = $parentPlaceId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = (static function () use ($stmt): Generator {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) yield $row;
    })();
    return array_replace($base, [
        'state' => 'ready',
        'services' => llama_nearest_service_results($lat, $lng, $rows),
    ]);
}
