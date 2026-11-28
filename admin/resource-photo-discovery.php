<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/resource-photo-discovery.php';
moderation_require_admin();
$db=db();
$adminPageTitle='Resource Photo Discovery';
$adminPageEyebrow='Map Data';
$adminActiveNav='resource-map-data';
$ready=llama_resource_photo_discovery_ready($db);
$state=mb_substr(trim((string)($_GET['state'] ?? $_POST['state'] ?? '')),0,120);
$notice=''; $error='';
if ($ready && ($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    try {
        if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Session expired. Reload Admin.');
        $id=filter_var($_POST['resource_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new RuntimeException('Choose a Resource.');
        $action=(string)($_POST['action'] ?? '');
        $db->beginTransaction();
        $stmt=$db->prepare("SELECT r.*,d.location_hash,d.candidates,d.status AS discovery_status FROM resources r
            JOIN resource_photo_discovery d ON d.resource_id=r.id WHERE r.id=? FOR UPDATE");
        $stmt->execute([$id]); $r=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw new RuntimeException('Photo discovery result unavailable.');
        if ($action==='retry') {
            $db->prepare('DELETE FROM resource_photo_discovery WHERE resource_id=?')->execute([$id]);
            $notice='Resource queued for another search.';
        } elseif ($r['discovery_status']==='review' && $action==='skip') {
            $db->prepare("UPDATE resource_photo_discovery SET status='skipped' WHERE resource_id=?")->execute([$id]);
            $notice='Skipped nearby photos.';
        } elseif ($r['discovery_status']==='review' && $action==='attach') {
            if (!hash_equals($r['location_hash'],llama_resource_photo_hash($r))) throw new RuntimeException('Resource changed. Search again.');
            $photos=json_decode($r['candidates'],true,32,JSON_THROW_ON_ERROR);
            $index=filter_var($_POST['candidate'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if ($index===false || !isset($photos[$index])) throw new RuntimeException('Choose a photo.');
            llama_resource_photo_attach($db,$r,$photos[$index]);
            $db->prepare("UPDATE resource_photo_discovery SET status='attached' WHERE resource_id=?")->execute([$id]);
            $notice='Photo attached to Resource.';
        } else throw new RuntimeException('Invalid photo discovery action.');
        $db->commit();
        header('Location: /resource-photo-discovery.php?state='.rawurlencode($state).'&notice='.rawurlencode($notice),true,303); exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error=$e instanceof PDOException?'Database update failed. No photo was attached.':$e->getMessage();
    }
}
$notice=$notice?:mb_substr((string)($_GET['notice'] ?? ''),0,200);
$counts=[]; $rows=[]; $states=[];
if ($ready) {
    $states=$db->query("SELECT DISTINCT state_region FROM resources WHERE is_active=1 AND parent_place_id IS NULL AND state_region IS NOT NULL AND state_region<>'' ORDER BY state_region")->fetchAll(PDO::FETCH_COLUMN);
    $where='r.is_active=1 AND r.parent_place_id IS NULL';
    $params=[];
    if ($state!=='') {$where.=' AND r.state_region=?';$params[]=$state;}
    $stmt=$db->prepare("SELECT CASE WHEN EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id) THEN 'has_photo'
        ELSE COALESCE(d.status,'pending') END AS result,COUNT(*) AS total FROM resources r
        LEFT JOIN resource_photo_discovery d ON d.resource_id=r.id WHERE ".$where.' GROUP BY result');
    $stmt->execute($params); $counts=array_column($stmt->fetchAll(PDO::FETCH_ASSOC),'total','result');
    $stmt=$db->prepare("SELECT r.id,r.name,r.latitude,r.longitude,r.state_region,d.candidates,d.status
        FROM resources r JOIN resource_photo_discovery d ON d.resource_id=r.id
        WHERE ".$where." AND d.status='review' AND NOT EXISTS (SELECT 1 FROM resource_images i WHERE i.resource_id=r.id)
        ORDER BY r.id LIMIT 10");
    $stmt->execute($params); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
$e=static fn($v)=>moderation_e((string)$v);
require __DIR__.'/_header.php';
?>
<section class="admin-panel">
<header class="admin-panel-header"><div><p>Map Data</p><h2>Resource Photo Discovery</h2></div><a class="admin-button" href="/resource-photo-pilot.php">Test Photo Coverage (20)</a> <a class="admin-button is-muted" href="/resource-enrichment.php">Back to Enrichment</a></header>
<?php if ($error!==''): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
<?php if ($notice!==''): ?><p role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if (!$ready): ?>
<p>Run <code>tools/migrations/20261128_resource_photo_discovery.sql</code> first.</p>
<?php else: ?>
<p>Find reusable Wikimedia Commons photos. Exact source-linked photos can become the first gallery image automatically; nearby photos must be reviewed to confirm they show the actual Resource. Existing galleries are never replaced.</p>
<form method="get"><label>State <select name="state"><option value="">All states</option>
<?php foreach ($states as $name): ?><option value="<?= $e($name) ?>" <?= $state===$name?'selected':'' ?>><?= $e($name) ?></option><?php endforeach; ?>
</select></label><button class="admin-button">Filter</button></form>
<p><?= number_format((int)($counts['has_photo'] ?? 0)) ?> with photos · <?= number_format((int)($counts['pending'] ?? 0)) ?> pending · <?= number_format((int)($counts['review'] ?? 0)) ?> to review · <?= number_format((int)($counts['none'] ?? 0)) ?> no result · <?= number_format((int)($counts['skipped'] ?? 0)) ?> skipped.</p>
<div data-photo-discovery data-csrf="<?= $e(moderation_csrf_token()) ?>" data-state="<?= $e($state) ?>">
<button type="button" class="admin-button" data-discovery-start>Find photos / resume</button>
<button type="button" class="admin-button is-muted" data-discovery-pause disabled>Pause</button>
<a class="admin-button is-muted" href="?state=<?= $e(rawurlencode($state)) ?>">Refresh results</a>
<p role="status" data-discovery-status>Keep this page open while searching. Results are saved as each Resource is processed.</p>
</div>
<h3>Nearby photos to review</h3>
<p>Only attach an image if you recognize the location. A nearby road, sign or unrelated business is not a Resource photo.</p>
<?php foreach ($rows as $row): ?>
<div class="admin-user-action-box">
<h3><a href="/resources.php?id=<?= (int)$row['id'] ?>"><?= $e($row['name']) ?></a></h3>
<p><?= $e($row['state_region']) ?> · <?= $e($row['latitude']) ?>, <?= $e($row['longitude']) ?> · <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= $e(rawurlencode($row['latitude'].','.$row['longitude'])) ?>" target="_blank" rel="noopener noreferrer">Check map</a></p>
<div class="add-place-existing-photo-grid">
<?php foreach ((array)json_decode($row['candidates'],true) as $index=>$photo): ?>
<div>
<img src="<?= $e($photo['src']) ?>" alt="<?= $e($photo['title']) ?>" loading="lazy" width="260">
<p><?= $e($photo['title']) ?> · <?= $e($photo['credit']) ?> · <a href="<?= $e($photo['source']) ?>" target="_blank" rel="noopener noreferrer">Source / license</a></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>"><input type="hidden" name="state" value="<?= $e($state) ?>"><input type="hidden" name="resource_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="candidate" value="<?= (int)$index ?>"><button class="admin-button" name="action" value="attach">Use this photo</button></form>
</div>
<?php endforeach; ?>
</div>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>"><input type="hidden" name="state" value="<?= $e($state) ?>"><input type="hidden" name="resource_id" value="<?= (int)$row['id'] ?>"><button class="admin-button is-muted" name="action" value="skip">Skip all</button> <button class="admin-button is-muted" name="action" value="retry">Search again</button></form>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><p>No nearby photos awaiting review in this selection.</p><?php endif; ?>
<?php endif; ?>
</section>
<?php if ($ready): ?><script src="/js/admin-resource-photo-discovery.js?v=20261128-1" defer></script><?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
