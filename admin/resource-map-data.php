<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/osm-resource-local.php';

$adminUser = moderation_require_admin();
$adminPageTitle = 'Resources';
$adminPageEyebrow = 'Map Data';
$adminActiveNav = 'resource-map-data';
$db = db();
$ready = llama_osm_resource_ready($db);
$workerStatus = llama_osm_local_worker_status();
$notice = '';
$error = '';
$inventory = $ready ? llama_osm_resource_inventory($db) : [];
if ($ready && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        if (!moderation_verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
            throw new InvalidArgumentException('Session expired. Reload this page.');
        }
        $action = (string)($_POST['action'] ?? '');
        $regions = (array)($_POST['regions'] ?? []);
        if ($action === 'missing') {
            $regions = array_keys(array_filter($inventory,static fn($state)=>$state['snapshot'] === null));
        } elseif ($action === 'refresh') {
            $regions = array_keys(array_filter($inventory,static fn($state)=>$state['snapshot'] !== null));
        } elseif (!in_array($action,['selected','restart'],true)) {
            throw new InvalidArgumentException('Choose an import action.');
        }
        $queued = $regions ? llama_osm_resource_queue($db,$regions,$action === 'restart') : [];
        $notice = $queued ? count($queued) . ' state(s) queued. Existing snapshots remain available during refresh.'
            : 'No additional states to queue. Select a state for a selected-state action; existing queued or importing states were left untouched.';
    } catch (PDOException $e) {
        llama_log_caught_exception($e,'admin.osm_resource_import');
        $error = 'The database could not queue the import. Existing snapshots are unchanged.';
    } catch (InvalidArgumentException | RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        llama_log_caught_exception($e,'admin.osm_resource_import');
        $error = 'The import could not be queued. Existing snapshots are unchanged.';
    }
    $inventory = llama_osm_resource_inventory($db);
}
$priorities = llama_osm_resource_priorities();
$region = (string)($_GET['region'] ?? 'colorado');
if (!isset(llama_osm_resource_states()[$region])) $region = 'colorado';
$category = (string)($_GET['category'] ?? '');
if ($category !== '' && !isset($priorities[$category])) $category = '';
$counts = [];
$candidates = [];
$imported = count(array_filter($inventory,static fn($state)=>$state['snapshot'] !== null));
$working = count(array_filter($inventory,static fn($state)=>in_array($state['job']['status'] ?? '',['queued','importing'],true)));
if ($ready) {
    $stmt = $db->prepare('SELECT service_type,COUNT(*) AS total FROM osm_nearest_services WHERE region=? GROUP BY service_type');
    $stmt->execute([$region]);
    $counts = array_column($stmt->fetchAll(PDO::FETCH_ASSOC),'total','service_type');
    // A source identity is one candidate, even when several mapped services
    // belong to the same OSM feature. Do not automatically merge nearby
    // unrelated locations or create production Resource records.
    $types = array_keys($priorities);
    $placeholders = implode(',',array_fill(0,count($types),'?'));
    $query = 'SELECT osm_type,osm_id,MIN(name) AS name,MIN(latitude) AS latitude,MIN(longitude) AS longitude,
        GROUP_CONCAT(DISTINCT service_type ORDER BY service_type) AS services
        FROM osm_nearest_services WHERE region=? AND service_type IN (' . $placeholders . ')
        GROUP BY osm_type,osm_id';
    $params = [$region,...$types];
    if ($category !== '') {
        $query .= ' HAVING SUM(service_type=?)>0';
        $params[] = $category;
    }
    $query .= ' ORDER BY ' . ($category === '' ? "CASE
        WHEN SUM(service_type='potable_water')>0 THEN 1
        WHEN SUM(service_type='dump_station')>0 THEN 2
        WHEN SUM(service_type='shower')>0 THEN 3
        WHEN SUM(service_type='laundry')>0 THEN 4
        WHEN SUM(service_type='propane')>0 THEN 5
        WHEN SUM(service_type='toilet')>0 THEN 6
        WHEN SUM(service_type='trash')>0 THEN 7
        WHEN SUM(service_type='fuel')>0 THEN 8
        WHEN SUM(service_type='groceries')>0 THEN 9 ELSE 10 END, " : '') . 'name,osm_type,osm_id LIMIT 100';
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$e = static fn($value)=>moderation_e((string)$value);
require __DIR__ . '/_header.php';
?>
<?php if ($notice !== ''): ?><p class="admin-panel" role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="admin-panel" role="alert"><?= $e($error) ?></p><?php endif; ?>
<section class="admin-panel">
    <header class="admin-panel-header"><div><p>OpenStreetMap reference data</p><h2>State imports and refreshes</h2></div>
        <a class="admin-button" href="/resource-map-data.php">Refresh status</a>
    </header>
    <?php if (!$ready): ?>
        <p>Run the primary database migration 20261127_osm_resource_import.sql first. Existing imported regions will be retained.</p>
    <?php else: ?>
        <p><?= $imported ?> of <?= count($inventory) ?> regions imported. <?= $working ?> queued or importing. Existing Colorado and Oregon snapshots are recognized automatically.</p>
        <p>Imports run through the local server worker; you do not need to download files or leave this page open. Refresh replaces only the selected reference snapshot after every row is received. Imported rows are not Scout observations.</p>
        <p>Buttons queue work directly in the primary database. The local scheduled worker processes the queue one state at a time. No API credentials are required.</p>
        <?php if ($workerStatus === null): ?>
            <p role="status"><strong>Local worker not detected.</strong> Imports can be queued now, but will not start until the scheduled server worker is installed and running.</p>
        <?php else: ?>
            <p role="status"><strong>Local worker: <?= $e(ucfirst($workerStatus['state'])) ?></strong> · <?= $e($workerStatus['message']) ?> Last status: <?= $e($workerStatus['at']) ?>.</p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">
            <div class="admin-panel-header">
                <button class="admin-button is-primary" name="action" value="missing">Import all missing states</button>
                <button class="admin-button" name="action" value="refresh">Refresh imported states</button>
                <button class="admin-button" name="action" value="selected">Import / refresh selected</button>
                <button class="admin-button" name="action" value="restart">Restart selected jobs</button>
            </div>
            <p>Restart selected supersedes previous jobs for those states; use only for stuck or failed jobs.</p>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Select</th><th>State</th><th>Imported snapshot</th><th>Reference rows</th><th>Job status</th><th>Received</th><th>Updated (UTC)</th><th></th></tr></thead>
                    <tbody><?php foreach ($inventory as $slug=>$state): $job=$state['job']; $snapshot=$state['snapshot']; ?>
                        <tr>
                            <td><input type="checkbox" name="regions[]" value="<?= $e($slug) ?>" aria-label="Select <?= $e($state['name']) ?>"></td>
                            <td><?= $e($state['name']) ?></td>
                            <td><?= $e($snapshot['snapshot_date'] ?? 'Not imported') ?></td>
                            <td><?= number_format((int)($snapshot['service_rows'] ?? 0)) ?></td>
                            <td><?= $e(ucfirst((string)($job['status'] ?? ($snapshot?'Imported':'Missing')))) ?>
                                <?php if (!empty($job['message'])): ?><small><?= $e($job['message']) ?></small><?php endif; ?></td>
                            <td><?= $job && (int)$job['expected_rows']>0 ? number_format((int)$job['received_rows']) . ' / ' . number_format((int)$job['expected_rows']) : '' ?></td>
                            <td><?= $e($job['updated_at'] ?? '') ?></td>
                            <td><a href="?region=<?= $e($slug) ?>#resource-candidates">View candidates</a></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</section>
<?php if ($ready): ?>
<section class="admin-panel" id="resource-candidates">
    <header class="admin-panel-header"><div><p>Imported reference inventory</p><h2>Resource candidates</h2></div></header>
    <form method="get" action="/resource-map-data.php#resource-candidates">
        <label>State <select name="region"><?php foreach (llama_osm_resource_states() as $slug=>$name): ?>
            <option value="<?= $e($slug) ?>" <?= $slug===$region?'selected':'' ?>><?= $e($name) ?></option>
        <?php endforeach; ?></select></label>
        <label>Service <select name="category"><option value="">All priority services</option><?php foreach ($priorities as $key=>$name): ?>
            <option value="<?= $e($key) ?>" <?= $key===$category?'selected':'' ?>><?= $e($name) ?> (<?= number_format((int)($counts[$key] ?? 0)) ?>)</option>
        <?php endforeach; ?></select></label>
        <button class="admin-button">View</button>
    </form>
    <p>First 100 source candidates, grouped by OpenStreetMap feature identity. Counts are mapped services, not verified Resources. A separate publication and duplicate-review step is required before they appear on the public Resource map.</p>
    <div class="admin-table-wrap">
        <table class="admin-table"><thead><tr><th>Name</th><th>Services</th><th>Coordinates</th><th>Source</th></tr></thead><tbody>
        <?php foreach ($candidates as $candidate): ?>
            <tr><td><?= $e($candidate['name']) ?></td>
                <td><?= $e(implode(', ',array_map(static fn($type)=>$priorities[$type] ?? $type,explode(',',$candidate['services'])))) ?></td>
                <td><?= $e($candidate['latitude']) ?>, <?= $e($candidate['longitude']) ?></td>
                <td><a href="https://www.openstreetmap.org/<?= $e($candidate['osm_type']) ?>/<?= (int)$candidate['osm_id'] ?>" target="_blank" rel="noopener noreferrer">OSM <?= $e($candidate['osm_type']) ?> <?= (int)$candidate['osm_id'] ?></a></td></tr>
        <?php endforeach; ?>
        <?php if (!$candidates): ?><tr><td colspan="4">No matching reference candidates. Import or refresh this state first.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
    <small><a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors (ODbL)</a></small>
</section>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
