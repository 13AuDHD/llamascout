<?php
declare(strict_types=1);
// Public, paginated ODbL source facts only; no native IDs, Scout fields or audit decisions.
require_once dirname(__DIR__).'/app/database.php';
require_once dirname(__DIR__).'/app/osm-resource-enrichment.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='GET') { http_response_code(405); exit; }
$region=(string)($_GET['region'] ?? '');
$page=filter_var($_GET['page'] ?? '1',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
if (!isset(llama_osm_resource_states()[$region]) || !$page) { http_response_code(400); echo '{"error":"Valid region and page required"}'; exit; }
try {
    $db=db(); $rows=[];
    if (llama_enrichment_ready($db)) {
        $offset=($page-1)*500;
        $stmt=$db->prepare("SELECT osm_type,osm_id,region,snapshot_date,tags FROM osm_resource_enrichment_facts WHERE region=? ORDER BY osm_type,osm_id LIMIT 501 OFFSET ".$offset);
        $stmt->execute([$region]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $more=count($rows)>500; if ($more) array_pop($rows);
    foreach ($rows as &$row) $row['tags']=json_decode($row['tags'],true,32,JSON_THROW_ON_ERROR);
    unset($row);
    echo json_encode(['attribution'=>'© OpenStreetMap contributors','license'=>'ODbL-1.0',
        'license_url'=>'https://opendatacommons.org/licenses/odbl/1-0/','region'=>$region,'page'=>$page,
        'next_page'=>$more?$page+1:null,'records'=>$rows],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) { error_log('OSM enrichment export: '.$e->getMessage()); http_response_code(503); echo '{"error":"Source export unavailable"}'; }
