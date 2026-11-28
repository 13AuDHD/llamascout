<?php
declare(strict_types=1);

// Machine-only transport. No browser session, cookie, or arbitrary command.
require_once dirname(__DIR__) . '/app/database.php';
require_once dirname(__DIR__) . '/app/osm-resource-import.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function osm_worker_reply(array $result, int $status = 200): never {
    http_response_code($status);
    echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') osm_worker_reply(['ok'=>false],405);
try {
    $key = (string)(llama_osm_resource_config()['worker_key'] ?? '');
    $supplied = (string)($_SERVER['HTTP_X_LLAMA_OSM_KEY'] ?? '');
    if (strlen($key)<32 || !hash_equals($key,$supplied)) osm_worker_reply(['ok'=>false],403);
    $stream = fopen('php://input','rb');
    $body = stream_get_contents($stream,1048577);
    fclose($stream);
    if ($body === false || strlen($body)>1048576) osm_worker_reply(['ok'=>false],413);
    $input = json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($input) || array_is_list($input)) osm_worker_reply(['ok'=>false],400);
    if (!llama_osm_resource_ready(db())) osm_worker_reply(['ok'=>false,'error'=>'Import migration required.'],503);
    $job = llama_osm_resource_worker(db(),$input);
    osm_worker_reply(['ok'=>true,'status'=>$job['status'],'received'=>(int)$job['received_rows']]);
} catch (InvalidArgumentException | JsonException $e) {
    osm_worker_reply(['ok'=>false,'error'=>$e->getMessage()],400);
} catch (Throwable $e) {
    error_log('OSM resource import worker: ' . $e->getMessage());
    osm_worker_reply(['ok'=>false,'error'=>'Import transaction failed.'],500);
}
