<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/osm-resource-enrichment.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
function enrichment_reply(array $data,int $status=200): never {
    http_response_code($status); echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
try {
    moderation_require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') enrichment_reply(['ok'=>false,'error'=>'POST required.'],405);
    if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) enrichment_reply(['ok'=>false,'error'=>'Session expired. Reload Admin.'],403);
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    $region=(string)($_POST['region'] ?? '');
    if ($region!=='' && !isset(llama_osm_resource_states()[$region])) throw new InvalidArgumentException('Invalid state.');
    $db=db();
    if (($_POST['action'] ?? '')==='restart') {
        if ($region==='') throw new InvalidArgumentException('Select a specific state to restart.');
        $lockName='llama-osm-local-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
        $lock=$db->prepare('SELECT GET_LOCK(?,0)'); $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn()!==1) throw new LlamaOsmBrowserRetry('Another state operation is active.');
        try { $db->prepare('DELETE FROM osm_resource_enrichment_jobs WHERE region=?')->execute([$region]); }
        finally { $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]); }
        enrichment_reply(['ok'=>true,'restarted'=>true]);
    }
    enrichment_reply(['ok'=>true]+llama_enrichment_step($db,$region));
} catch (LlamaOsmBrowserRetry $e) {
    header('Retry-After: 5');
    enrichment_reply(['ok'=>false,'retry'=>true,'error'=>$e->getMessage()],429);
} catch (PDOException $e) {
    llama_log_caught_exception($e,'admin.resource_enrichment');
    enrichment_reply(['ok'=>false,'error'=>'Database step failed; previous batches are saved.'],500);
} catch (RuntimeException|InvalidArgumentException $e) {
    enrichment_reply(['ok'=>false,'error'=>$e->getMessage()],400);
} catch (Throwable $e) {
    llama_log_caught_exception($e,'admin.resource_enrichment');
    enrichment_reply(['ok'=>false,'error'=>'Enrichment step failed; previous batches are saved.'],500);
}
