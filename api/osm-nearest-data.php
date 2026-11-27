<?php
declare(strict_types=1);

// Public ODbL reference-layer downloads. Never query Scout data or Site GPS.
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/osm-nearest-data.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit;
}
$region = (string) ($_GET['region'] ?? '');
if ($region !== '' && !llama_osm_nearest_region_valid($region)) {
    http_response_code(400);
    exit;
}
try {
    $db = db();
    if ($region === '') {
        header('Content-Type: application/json; charset=utf-8');
        $regions = llama_osm_nearest_regions($db);
        foreach ($regions as &$entry) {
            $entry['download'] = '/api/osm-nearest-data.php?region=' . rawurlencode($entry['region']);
        }
        unset($entry);
        echo json_encode(['attribution'=>'© OpenStreetMap contributors',
            'license'=>'ODbL-1.0','license_url'=>'https://www.openstreetmap.org/copyright',
            'regions'=>$regions], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
    if (!llama_osm_nearest_ready($db)) { http_response_code(404); exit; }
    // One query captures a consistent regional snapshot even during a refresh.
    $stmt = $db->prepare('SELECT osm_type,osm_id,service_type,name,latitude,longitude,snapshot_date
        FROM osm_nearest_services WHERE region=? ORDER BY osm_type,osm_id,service_type');
    $stmt->execute([$region]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); exit; }
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Content-Disposition: attachment; filename="osm-nearest-' . $region . '.ndjson"');
    $emit = static function(array $value): void {
        echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    };
    $emit(llama_osm_nearest_header($region, $row['snapshot_date']));
    $count = 0;
    do {
        unset($row['snapshot_date']);
        $row['osm_id'] = (int) $row['osm_id'];
        $row['latitude'] = (float) $row['latitude'];
        $row['longitude'] = (float) $row['longitude'];
        $emit($row);
        $count++;
    } while ($row = $stmt->fetch(PDO::FETCH_ASSOC));
    $emit(['end'=>true,'count'=>$count]);
} catch (Throwable $exception) {
    error_log('OSM reference export failed: ' . $exception->getMessage());
    if (!headers_sent()) http_response_code(503);
}
