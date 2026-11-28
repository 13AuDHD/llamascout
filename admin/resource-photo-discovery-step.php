<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/resource-photo-discovery.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
try {
    moderation_require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST' || !moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403); echo '{"ok":false,"error":"Session expired. Reload Admin."}'; exit;
    }
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    echo json_encode(['ok'=>true]+llama_resource_photo_discover_step(db(),mb_substr(trim((string)($_POST['state'] ?? '')),0,120)),JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch (PDOException $e) {
    llama_log_caught_exception($e,'admin.resource_photo_discovery'); http_response_code(500); echo '{"ok":false,"error":"Database step failed. Previous results are saved."}';
} catch (RuntimeException $e) {
    http_response_code(503); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    llama_log_caught_exception($e,'admin.resource_photo_discovery'); http_response_code(500); echo '{"ok":false,"error":"Photo discovery failed. Resume to retry."}';
}
