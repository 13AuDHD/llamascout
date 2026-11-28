<?php
declare(strict_types=1);
// Public ODbL source baseline export. Never include native Resource IDs,
// Scout observations, account data, review notes, or independently authored fields.
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/osm-resource-publication.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET') { http_response_code(405); exit; }
try {
    $db=db();
    $rows=[];
    if (llama_osm_publication_ready($db)) {
        $stmt=$db->query("SELECT source_snapshot FROM osm_resource_publications WHERE disposition='published' ORDER BY osm_type,osm_id");
        while (($raw=$stmt->fetchColumn())!==false) $rows[]=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    }
    echo json_encode(['attribution'=>'© OpenStreetMap contributors','license'=>'ODbL-1.0',
        'license_url'=>'https://opendatacommons.org/licenses/odbl/1-0/','records'=>$rows],
        JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('OSM publication baseline export: '.$e->getMessage());
    http_response_code(503);
    echo '{"error":"Source export unavailable"}';
}
