<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/site-visibility.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function llama_campsite_points_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function llama_campsite_popup_site_type_label(?string $value): string
{
    return match (strtolower(trim((string) $value))) {
        'rv_site' => 'RV site',
        'tent_site' => 'Tent site',
        'mixed_site' => 'Tent / RV',
        'vehicle_site' => 'Vehicle site',
        'group_site' => 'Group site',
        default => trim((string) $value),
    };
}

function llama_campsite_popup_parking_label(?string $value): string
{
    return match (strtolower(trim((string) $value))) {
        'pull_through' => 'Pull-through',
        'back_in' => 'Back-in',
        'pull_in' => 'Pull-in',
        'parallel' => 'Parallel',
        'unknown' => 'Unknown',
        default => trim((string) $value),
    };
}

function llama_campsite_popup_bool(mixed $value): ?bool
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, ['1', 'y', 'yes', 'true'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'n', 'no', 'false'], true)) {
        return false;
    }

    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    llama_campsite_points_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    llama_campsite_points_response(['ok' => false, 'error' => 'A Place is required.'], 400);
}

$place = place_public_by_slug($slug);
if (!$place) {
    llama_campsite_points_response(['ok' => false, 'error' => 'Place not found.'], 404);
}

$placeId = (int) ($place['id'] ?? 0);
$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$canRead = $userId > 0 && user_has_place_complete_access($placeId, $userId);

try {
    $db = db();
    $query = $db->prepare(
        'SELECT
            cs.id,
            cs.site_code,
            cs.site_name,
            cs.site_type,
            cs.raw_site_type,
            cs.parking_style,
            cs.accessible_status,
            cs.latitude,
            cs.longitude,
            cf.max_vehicle_length_ft,
            cf.max_people,
            cf.max_vehicles,
            cf.electric_hookup,
            cf.water_hookup,
            cf.sewer_hookup
           FROM place_campsites AS cs
           LEFT JOIN place_campsite_facts AS cf
             ON cf.campsite_id = cs.id
          WHERE cs.place_id = ?
            AND cs.is_active = 1
            AND (cs.map_feature_id IS NULL OR cs.map_feature_id = 0)
            AND cs.latitude BETWEEN -90 AND 90
            AND cs.longitude BETWEEN -180 AND 180
            AND (cs.latitude <> 0 OR cs.longitude <> 0)
          ORDER BY cs.id ASC
          LIMIT 1001'
    );
    $query->execute([$placeId]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $publicSiteIds = $canRead ? [] : llama_site_public_visibility_ids($db, $placeId);
    $rows = array_values(array_filter(
        $rows,
        static fn(array $site): bool =>
            llama_site_can_read($place, $site, $canRead, $publicSiteIds)
    ));

    if (count($rows) > 1000) {
        llama_campsite_points_response([
            'ok' => false,
            'error' => 'Too many campsite points for the current map layer.',
        ], 422);
    }

    /*
     * A map card needs one hero image, not an entire campsite gallery.
     * Read existing photo metadata once for this Place. Only the first
     * nonempty image per campsite is used, in gallery priority order.
     * The browser does not download the actual image until its popup opens.
     */
    $heroImages = [];

    if ($rows !== []) {
        $photoQuery = $db->prepare(
            'SELECT campsite_id, src
               FROM place_campsite_images
              WHERE place_id = ?
                AND src IS NOT NULL
              ORDER BY
                campsite_id ASC,
                is_featured DESC,
                sort_order ASC,
                id ASC'
        );
        $photoQuery->execute([$placeId]);

        while ($photo = $photoQuery->fetch(PDO::FETCH_ASSOC)) {
            $siteId = (int) ($photo['campsite_id'] ?? 0);
            $src = trim((string) ($photo['src'] ?? ''));

            if ($siteId > 0 && $src !== '' && !isset($heroImages[$siteId])) {
                $heroImages[$siteId] = $src;
            }
        }
    }

    $points = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['site_code'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['site_name'] ?? ''));
        }
        if ($name === '') {
            $name = 'Site ' . (int) $row['id'];
        }

        $points[] = [
            'id' => (int) $row['id'],
            'name' => $name,
            'hero_image' => $heroImages[(int) $row['id']] ?? null,
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'site_type_label' => llama_campsite_popup_site_type_label((string) ($row['site_type'] ?? '')),
            'parking_label' => llama_campsite_popup_parking_label((string) ($row['parking_style'] ?? '')),
            'max_vehicle_length_ft' => isset($row['max_vehicle_length_ft']) && $row['max_vehicle_length_ft'] !== ''
                ? (float) $row['max_vehicle_length_ft']
                : null,
            'max_people' => isset($row['max_people']) && $row['max_people'] !== ''
                ? (int) $row['max_people']
                : null,
            'max_vehicles' => isset($row['max_vehicles']) && $row['max_vehicles'] !== ''
                ? (int) $row['max_vehicles']
                : null,
            'accessible' => strtolower(trim((string) ($row['accessible_status'] ?? ''))) === 'yes',
            'electric' => llama_campsite_popup_bool($row['electric_hookup'] ?? null),
            'water' => llama_campsite_popup_bool($row['water_hookup'] ?? null),
            'sewer' => llama_campsite_popup_bool($row['sewer_hookup'] ?? null),
        ];
    }

    llama_campsite_points_response([
        'ok' => true,
        'place_id' => $placeId,
        'count' => count($points),
        'sites' => $points,
    ]);
} catch (Throwable $exception) {
    llama_log_caught_exception($exception, 'place.campsite.points.api', [
        'place_id' => $placeId,
    ]);
    llama_campsite_points_response([
        'ok' => false,
        'error' => 'Campsite map points could not be loaded.',
    ], 500);
}
