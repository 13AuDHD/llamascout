<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/osm-resource-publication.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
function osm_publication_reply(array $result,int $status=200): never {
    http_response_code($status);
    echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    $admin=moderation_require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') osm_publication_reply(['ok'=>false,'error'=>'POST required.'],405);
    if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) osm_publication_reply(['ok'=>false,'error'=>'Session expired. Reload Admin.'],403);
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $db=db();
    if (!llama_osm_publication_ready($db)) throw new RuntimeException('Run the existing publication migration first.');
    $region=(string)($_POST['region'] ?? '');
    $category=(string)($_POST['category'] ?? '');
    $result=llama_osm_publication_publish($db,$region,$category,[],(int)($admin['id'] ?? 0),true);
    osm_publication_reply(['ok'=>true,'done'=>($result['published']+$result['review'])===0]+$result);
} catch (InvalidArgumentException $e) {
    osm_publication_reply(['ok'=>false,'error'=>$e->getMessage()],400);
} catch (PDOException $e) {
    llama_log_caught_exception($e,'admin.osm_publication_step');
    osm_publication_reply(['ok'=>false,'error'=>'Database publication step failed. Earlier committed batches remain available.'],500);
} catch (RuntimeException $e) {
    osm_publication_reply(['ok'=>false,'error'=>$e->getMessage()],409);
} catch (Throwable $e) {
    llama_log_caught_exception($e,'admin.osm_publication_step');
    osm_publication_reply(['ok'=>false,'error'=>'Publication could not continue. Earlier committed batches remain available.'],500);
}
