<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/osm-resource-browser.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
function admin_osm_step_reply(array $data,int $status=200): never {
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    moderation_require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') admin_osm_step_reply(['ok'=>false,'error'=>'POST required.'],405);
    if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) admin_osm_step_reply(['ok'=>false,'error'=>'Session expired. Reload Admin.'],403);
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    admin_osm_step_reply(['ok'=>true]+llama_osm_browser_step(db()));
} catch (LlamaOsmBrowserRetry $e) {
    header('Retry-After: 5');
    admin_osm_step_reply(['ok'=>false,'retry'=>true,'error'=>$e->getMessage()],429);
} catch (PDOException $e) {
    llama_log_caught_exception($e,'admin.osm_browser_step');
    admin_osm_step_reply(['ok'=>false,'error'=>'Database import step failed; existing reference data is unchanged. Resume to retry.'],500);
} catch (RuntimeException $e) {
    admin_osm_step_reply(['ok'=>false,'error'=>$e->getMessage()],400);
} catch (Throwable $e) {
    llama_log_caught_exception($e,'admin.osm_browser_step');
    admin_osm_step_reply(['ok'=>false,'error'=>'Import step failed; existing reference data is unchanged.'],500);
}
