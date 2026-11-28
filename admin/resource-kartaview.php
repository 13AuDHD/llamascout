<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/resource-kartaview.php';
moderation_require_admin();
$db=db();
$state=mb_substr(trim((string)($_GET['state'] ?? $_POST['state'] ?? '')),0,120);
$ready=llama_kartaview_ready($db);
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Session expired. Reload Admin.');
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        echo json_encode(['ok'=>true]+llama_kartaview_step($db,$state),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e instanceof PDOException?'Database step failed.':$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}
$adminPageTitle='KartaView Hero Images';$adminPageEyebrow='Map Data';$adminActiveNav='resource-map-data';
$states=[];$counts=[];$used=0;$rows=[];
if ($ready) {
    $states=$db->query("SELECT DISTINCT state_region FROM resources WHERE is_active=1 AND parent_place_id IS NULL AND TRIM(COALESCE(state_region,''))<>'' ORDER BY state_region")->fetchAll(PDO::FETCH_COLUMN);
    $where='r.is_active=1 AND r.parent_place_id IS NULL';$params=[];
    if ($state!=='') {$where.=' AND r.state_region=?';$params[]=$state;}
    $stmt=$db->prepare("SELECT CASE WHEN EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id AND i.source_provider='KartaView') THEN 'attached'
        WHEN EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id) THEN 'other_photo'
        ELSE COALESCE(k.status,'pending') END AS result,COUNT(*) AS total
        FROM resources r LEFT JOIN resource_kartaview_discovery k ON k.resource_id=r.id WHERE ".$where." GROUP BY result");
    $stmt->execute($params);$counts=array_column($stmt->fetchAll(PDO::FETCH_ASSOC),'total','result');
    $used=(int)$db->query('SELECT COUNT(*) FROM resource_kartaview_requests WHERE requested_at>=CURRENT_TIMESTAMP-INTERVAL 1 HOUR')->fetchColumn();
    $stmt=$db->prepare("SELECT r.id,r.name,i.src,i.caption,i.source_url FROM resources r JOIN resource_images i ON i.resource_id=r.id AND i.source_provider='KartaView' WHERE ".$where." ORDER BY i.id DESC LIMIT 20");
    $stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
$e=static fn($v)=>moderation_e((string)$v);
require __DIR__.'/_header.php';
?>
<section class="admin-panel">
<header class="admin-panel-header"><div><p>Map Data</p><h2>KartaView Hero Images</h2></div><a class="admin-button is-muted" href="/resource-photo-discovery.php">Back to Photo Discovery</a></header>
<?php if (!$ready): ?><p>Run <code>20261128_resource_kartaview.sql</code> first.</p>
<?php else: ?>
<p>Automatically attach the closest available processed street-level image within 100 meters of a Resource's saved coordinates. No address is required. Images are labeled nearby street views, not verified photos of the Resource itself. Existing galleries are preserved. No individual approvals required.</p>
<form method="get"><label>State <select name="state"><option value="">All states</option><?php foreach ($states as $name): ?><option value="<?= $e($name) ?>" <?= $state===$name?'selected':'' ?>><?= $e($name) ?></option><?php endforeach; ?></select></label><button class="admin-button">Filter</button></form>
<p><?= number_format((int)($counts['attached'] ?? 0)) ?> KartaView photos · <?= number_format((int)($counts['other_photo'] ?? 0)) ?> existing photos · <?= number_format((int)($counts['pending'] ?? 0)) ?> pending · <?= number_format((int)($counts['none'] ?? 0)) ?> no coverage · <?= number_format((int)($counts['unusable'] ?? 0)) ?> no usable image.</p>
<p><?= $used ?>/<?= LLAMA_KARTAVIEW_HOURLY_LIMIT ?> discovery requests in the last hour.</p>
<div data-photo-discovery data-endpoint="/resource-kartaview.php" data-csrf="<?= $e(moderation_csrf_token()) ?>" data-state="<?= $e($state) ?>">
<button type="button" class="admin-button" data-discovery-start>Find and attach street photos / resume</button>
<button type="button" class="admin-button is-muted" data-discovery-pause disabled>Pause</button>
<a class="admin-button is-muted" href="?state=<?= $e(rawurlencode($state)) ?>">Refresh results</a>
<p data-discovery-status role="status" aria-live="polite">Keep this page open during processing. Each result is saved separately.</p>
</div>
<p>© Grab and KartaView Contributors · <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a> · <a href="https://kartaview.org/terms">Source terms</a></p>
<h3>Recently attached hero images</h3>
<div class="add-place-existing-photo-grid">
<?php foreach ($rows as $row): ?>
<div><img src="<?= $e($row['src']) ?>" alt="Nearby street view" loading="lazy" width="260"><p><a href="/resources.php?id=<?= (int)$row['id'] ?>"><?= $e($row['name']) ?></a></p><p><?= $e($row['caption']) ?></p></div>
<?php endforeach; ?>
</div>
<script src="/js/admin-resource-photo-discovery.js?v=20261128-3" defer></script>
<?php endif; ?>
</section>
<?php require __DIR__.'/_footer.php'; ?>
