<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

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

// Match the complete-Place access gate used by the existing Site and map APIs.
if ($userId < 1 || !user_has_place_complete_access($placeId, $userId)) {
    llama_campsite_points_response([
        'ok' => false,
        'error' => 'Complete Place access is required.',
    ], 403);
}

try {
    $db = db();
    $query = $db->prepare(
        'SELECT id, site_code, site_name, latitude, longitude
           FROM place_campsites
          WHERE place_id = ?
            AND is_active = 1
            AND (map_feature_id IS NULL OR map_feature_id = 0)
            AND latitude BETWEEN -90 AND 90
            AND longitude BETWEEN -180 AND 180
            AND (latitude <> 0 OR longitude <> 0)
          ORDER BY id ASC
          LIMIT 1001'
    );
    $query->execute([$placeId]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fail visibly rather than silently clipping large imported campgrounds.
    if (count($rows) > 1000) {
        llama_campsite_points_response([
            'ok' => false,
            'error' => 'Too many campsite points for the current map layer.',
        ], 422);
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
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
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
