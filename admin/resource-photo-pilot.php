<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/resource-photo-pilot.php';
moderation_require_admin();
$db=db();
$config=llama_config();
$token=trim((string)($config['mapillary']['access_token'] ?? getenv('MAPILLARY_ACCESS_TOKEN') ?: ''));
$providers=['commons'=>'Wikimedia Commons','karta'=>'KartaView'];
if ($token!=='') $providers['mapillary']='Mapillary';
$ready=llama_resource_photo_discovery_ready($db);
$sessionKey='resource_photo_coverage_pilot_v1';
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Session expired; reload Admin.');
        if (!$ready) throw new RuntimeException('Existing photo discovery tables are required.');
        if (($_POST['action'] ?? '')==='start') {
            $sample=llama_photo_pilot_sample($db);
            $_SESSION[$sessionKey]=['sample'=>$sample,'providers'=>array_keys($providers),'next'=>0,'last'=>0];
            echo json_encode(['ok'=>true,'sample'=>$sample,'providers'=>$providers],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE); exit;
        }
        $run=&$_SESSION[$sessionKey];
        if (!is_array($run) || !isset($run['sample'],$run['providers'],$run['next'])) throw new RuntimeException('Start a sample first.');
        if ($run['next']>=count($run['sample'])*count($run['providers'])) {
            echo '{"ok":true,"done":true}'; exit;
        }
        if (microtime(true)-(float)$run['last']<1.5) throw new RuntimeException('Please wait before the next sample request.');
        $n=(int)$run['next']; $width=count($run['providers']);
        $r=$run['sample'][intdiv($n,$width)]; $provider=$run['providers'][$n%$width];
        $run['last']=microtime(true);
        // No gallery writes, no change to the original discovery records.
        try {
            $result=match($provider) {
                'commons'=>llama_photo_pilot_commons($r),
                'karta'=>llama_photo_pilot_karta($r),
                'mapillary'=>llama_photo_pilot_mapillary($r,$token),
                default=>throw new RuntimeException('Unknown provider.'),
            };
            $result['status']='ok';
        } catch (Throwable $e) {
            $result=['status'=>'error','message'=>mb_substr($e->getMessage(),0,250)];
        }
        $run['next']++;
        echo json_encode(['ok'=>true,'id'=>$r['id'],'provider'=>$provider,'result'=>$result],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e instanceof PDOException?'Database query failed.':$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}
$adminPageTitle='Resource Photo Coverage Test';
$adminPageEyebrow='Map Data';
$adminActiveNav='resource-map-data';
$e=static fn($v)=>moderation_e((string)$v);
require __DIR__.'/_header.php';
?>
<section class="admin-panel">
<header class="admin-panel-header"><div><p>Diagnostics</p><h2>20-Resource Photo Coverage Test</h2></div><a class="admin-button is-muted" href="/resource-photo-discovery.php">Back to Photo Discovery</a></header>
<p>This read-only test selects up to 20 previously unsuccessful Resources across Resource types. It checks Commons within 500 meters, including secondary coordinates, and KartaView street-level imagery within 100 meters. Nearby images are not verified Resource photographs.</p>
<p>Mapillary: <?= $token!==''?'configured; included in this test':'not configured; excluded from this test' ?>. No API subscription is required for the other sources.</p>
<?php if ($ready): ?>
<div data-photo-pilot data-csrf="<?= $e(moderation_csrf_token()) ?>">
<button class="admin-button" type="button" data-pilot-start>Run 20-Resource test</button>
<p role="status" aria-live="polite" data-pilot-status>Ready. Nothing will be saved to Resource galleries.</p>
<div data-pilot-summary></div>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Resource</th><th>Source</th><th>Raw results</th><th>Eligible / nearby</th><th>Preview / rejection details</th></tr></thead><tbody data-pilot-rows></tbody></table></div>
</div>
<script src="/js/admin-resource-photo-pilot.js?v=1" defer></script>
<?php else: ?><p>The existing Resource Photo Discovery migration is required.</p><?php endif; ?>
</section>
<?php require __DIR__.'/_footer.php'; ?>
