<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/resource-address-enrichment.php';
moderation_require_admin();
$db=db();
$state=mb_substr(trim((string)($_GET['state'] ?? $_POST['state'] ?? '')),0,120);
$ready=llama_resource_address_ready($db);
if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST' && ($_POST['action'] ?? '')==='step') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Session expired. Reload Admin.');
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        echo json_encode(['ok'=>true]+llama_resource_address_step($db,$state),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e instanceof PDOException?'Database lookup failed.':$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}
$error=''; $notice='';
if ($ready && ($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    try {
        if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Session expired. Reload Admin.');
        $id=filter_var($_POST['resource_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new RuntimeException('Invalid Resource.');
        $db->beginTransaction();
        $stmt=$db->prepare('SELECT r.*,e.candidate,e.applied_fields,e.latitude AS lookup_lat,e.longitude AS lookup_lon FROM resources r
            JOIN resource_address_enrichment e ON e.resource_id=r.id WHERE r.id=? AND r.is_active=1 AND r.parent_place_id IS NULL FOR UPDATE');
        $stmt->execute([$id]);$r=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r || $r['latitude']!==$r['lookup_lat'] || $r['longitude']!==$r['lookup_lon']) throw new RuntimeException('Location changed. Run enrichment again.');
        if (($_POST['action'] ?? '')==='approve') {
            $candidate=json_decode($r['candidate'],true,32,JSON_THROW_ON_ERROR);
            $fields=array_intersect_key($candidate['fields'] ?? [],array_fill_keys((array)($_POST['fields'] ?? []),true));
            $applied=llama_resource_address_apply($db,$r,$fields);
            $previous=json_decode($r['applied_fields'],true,32,JSON_THROW_ON_ERROR);
            $final=array_replace($r,$applied);
            $missing=array_filter(array_keys(LLAMA_RESOURCE_ADDRESS_FIELDS),static fn($key)=>trim((string)($final[$key] ?? ''))==='');
            $db->prepare('UPDATE resource_address_enrichment SET applied_fields=?,status=? WHERE resource_id=?')
                ->execute([json_encode(array_replace($previous,$applied),JSON_THROW_ON_ERROR),$missing?'partial':'filled',$id]);
            $notice=count($applied).' missing fields saved.';
        } elseif (($_POST['action'] ?? '')==='retry') {
            // Preserve attribution and original coordinates while queueing a fresh attempt.
            $db->prepare("UPDATE resource_address_enrichment SET status='pending' WHERE resource_id=?")->execute([$id]);
            $notice='Resource queued for another lookup.';
        } else throw new RuntimeException('Invalid action.');
        $db->commit();
        header('Location: /resource-address-enrichment.php?state='.rawurlencode($state).'&notice='.rawurlencode($notice),true,303);exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error=$e instanceof PDOException?'Database update failed.':$e->getMessage();
    }
}
$notice=$notice?:mb_substr((string)($_GET['notice'] ?? ''),0,200);
$adminPageTitle='Resource Addresses';$adminPageEyebrow='Map Data';$adminActiveNav='resource-map-data';
$states=[];$rows=[];$total=0;$pending=0;$used=0;$page=max(1,min(100000,(int)($_GET['page'] ?? 1)));
if ($ready) {
    $states=$db->query("SELECT DISTINCT state_region FROM resources WHERE is_active=1 AND parent_place_id IS NULL AND TRIM(COALESCE(state_region,''))<>'' ORDER BY state_region")->fetchAll(PDO::FETCH_COLUMN);
    $where='r.is_active=1 AND r.parent_place_id IS NULL AND '.llama_resource_address_missing();
    $params=[];
    if ($state!=='') {$where.=' AND r.state_region=?';$params[]=$state;}
    $join=' FROM resources r LEFT JOIN resource_address_enrichment e ON e.resource_id=r.id WHERE '.$where;
    $stmt=$db->prepare('SELECT COUNT(*)'.$join);$stmt->execute($params);$total=(int)$stmt->fetchColumn();
    $stmt=$db->prepare('SELECT COUNT(*)'.$join.' AND (e.resource_id IS NULL OR e.status=\'pending\' OR e.latitude<>r.latitude OR e.longitude<>r.longitude OR JSON_EXTRACT(e.candidate,\'$.bulk_policy_v2\') IS NULL)');$stmt->execute($params);$pending=(int)$stmt->fetchColumn();
    $stmt=$db->prepare('SELECT r.*,e.status AS lookup_status,e.candidate,e.latitude AS lookup_lat,e.longitude AS lookup_lon'.$join.' ORDER BY r.id LIMIT 50 OFFSET '.(($page-1)*50));
    $stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $used=(int)$db->query('SELECT requests FROM resource_address_daily_usage WHERE usage_date=UTC_DATE()')->fetchColumn();
}
$e=static fn($v)=>moderation_e((string)$v);
require __DIR__.'/_header.php';
?>
<section class="admin-panel">
<header class="admin-panel-header"><div><p>Map Data</p><h2>Resource Addresses</h2></div><a class="admin-button is-muted" href="/resources.php">Back to Resources</a></header>
<?php if ($error!==''): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
<?php if ($notice!==''): ?><p role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if (!$ready): ?><p>Run <code>20261128_resource_address_enrichment.sql</code> first.</p>
<?php else: ?>
<p>Automatically fill missing address fields from saved coordinates using the existing Geoapify key. Existing values are preserved. Best available addresses are saved automatically; coordinate-derived street addresses may describe nearby roads or buildings.</p>
<form method="get"><label>State <select name="state"><option value="">All states</option><?php foreach ($states as $name): ?><option value="<?= $e($name) ?>" <?= $state===$name?'selected':'' ?>><?= $e($name) ?></option><?php endforeach; ?></select></label><button class="admin-button">Filter</button></form>
<p><?= number_format($total) ?> incomplete · <?= number_format($pending) ?> pending lookup · <?= number_format($used) ?>/<?= LLAMA_RESOURCE_ADDRESS_DAILY_LIMIT ?> bulk requests today (UTC).</p>
<div data-address-enrichment data-csrf="<?= $e(moderation_csrf_token()) ?>" data-state="<?= $e($state) ?>">
<button type="button" class="admin-button" data-address-start>Apply retrieved addresses and fill missing addresses</button>
<button type="button" class="admin-button is-muted" data-address-pause disabled>Pause</button>
<a class="admin-button is-muted" href="?state=<?= $e(rawurlencode($state)) ?>">Refresh queue</a>
<p data-address-status role="status" aria-live="polite">Keep this page open during processing. Each Resource is saved separately.</p>
</div>
<p>Address data: <a href="https://www.geoapify.com/">Geoapify</a> / <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>. Coordinate-derived addresses are approximate and do not establish a verified street address.</p>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Resource</th><th>Missing fields</th><th>Lookup / suggestions</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $r): $valid=$r['lookup_status'] && $r['lookup_status']!=='pending' && $r['latitude']===$r['lookup_lat'] && $r['longitude']===$r['lookup_lon'];$candidate=$valid?json_decode($r['candidate'],true):[]; ?>
<tr><td><?= $e($r['name']) ?><br><?= $e($r['state_region']) ?><br><?= $e($r['latitude']) ?>, <?= $e($r['longitude']) ?></td>
<td><?= $e(implode(', ',array_filter(array_keys(LLAMA_RESOURCE_ADDRESS_FIELDS),static fn($key)=>trim((string)($r[$key] ?? ''))===''))) ?></td>
<td><?= $e($valid?$r['lookup_status']:'Pending') ?>
<?php if ($candidate): ?><p><?= $e($candidate['label'] ?? '') ?> · <?= $e($candidate['reason'] ?? '') ?></p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>"><input type="hidden" name="state" value="<?= $e($state) ?>"><input type="hidden" name="resource_id" value="<?= (int)$r['id'] ?>">
<p>Available fields are applied by the bulk address job; no individual approval needed.</p>
<button class="admin-button is-muted" name="action" value="retry">Retry lookup</button></form><?php endif; ?></td>
<td><a class="admin-button" href="/resources.php?id=<?= (int)$r['id'] ?>&amp;address_queue=1">Edit Resource</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (!$rows): ?><p>No incomplete Resources in this selection.</p><?php endif; ?>
<?php if ($page>1): ?><a class="admin-button" href="?state=<?= $e(rawurlencode($state)) ?>&amp;page=<?= $page-1 ?>">Previous</a><?php endif; ?>
<?php if ($page*50<$total): ?><a class="admin-button" href="?state=<?= $e(rawurlencode($state)) ?>&amp;page=<?= $page+1 ?>">Next</a><?php endif; ?>
<script src="/js/admin-resource-address-enrichment.js?v=2" defer></script>
<?php endif; ?>
</section>
<?php require __DIR__.'/_footer.php'; ?>
