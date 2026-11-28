<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/osm-resource-enrichment.php';
moderation_require_admin();
$db=db();
$adminPageTitle='Resource Enrichment';
$adminPageEyebrow='Map Data';
$adminActiveNav='resource-map-data';
$ready=llama_enrichment_ready($db);
$rows=[]; $allResources=0;
if ($ready) {
    $allResources=(int)$db->query("SELECT COUNT(*) FROM resources WHERE is_active=1 AND parent_place_id IS NULL")->fetchColumn();
    $rows=$db->query("SELECT p.region,COUNT(*) AS total,COUNT(f.osm_id) AS found,
        SUM(CASE WHEN JSON_LENGTH(f.applied_fields)>0 THEN 1 ELSE 0 END) AS enriched,
        SUM(CASE WHEN f.review_reason IS NOT NULL THEN 1 ELSE 0 END) AS review,
        MAX(j.status) AS status,MAX(j.source_offset) AS source_offset,MAX(j.source_bytes) AS source_bytes
        FROM osm_resource_publications p LEFT JOIN osm_resource_enrichment_facts f ON f.osm_type=p.osm_type AND f.osm_id=p.osm_id
        LEFT JOIN osm_resource_enrichment_jobs j ON j.region=p.region
        WHERE p.disposition='published' GROUP BY p.region ORDER BY p.region")->fetchAll(PDO::FETCH_ASSOC);
}
$e=static fn($v)=>moderation_e((string)$v);
require __DIR__.'/_header.php';
?>
<section class="admin-panel">
    <header class="admin-panel-header"><div><p>OpenStreetMap</p><h2>Bulk Resource Enrichment</h2></div>
        <a class="admin-button is-muted" href="/resource-map-data.php">Back to Map Data</a></header>
    <?php if (!$ready): ?>
        <p>Run <code>tools/migrations/20261128_osm_resource_enrichment.sql</code> first.</p>
    <?php else: ?>
        <p>Recover address, contact and opening-hours tags directly from the state extract using each published Resource's original OSM identity. Existing values and verification dates are preserved. No paid lookup API or public OSM query service is used.</p>
        <p><?= number_format(array_sum(array_column($rows,'total'))) ?> OSM publication records eligible for source matching; <?= number_format($allResources) ?> active standalone Resources in the inventory. Non-OSM records require a different source.</p>
        <div data-enrichment-controls data-csrf="<?= $e(moderation_csrf_token()) ?>">
            <label>State <select data-enrichment-region><option value="">All published states</option>
                <?php foreach ($rows as $row): ?><option value="<?= $e($row['region']) ?>"><?= $e(llama_osm_resource_states()[$row['region']] ?? $row['region']) ?></option><?php endforeach; ?>
            </select></label>
            <div class="admin-user-form-actions">
                <button type="button" class="admin-button" data-enrichment-start>Enrich / resume</button>
                <button type="button" class="admin-button is-muted" data-enrichment-pause disabled>Pause</button>
                <button type="button" class="admin-button is-muted" data-enrichment-restart>Restart selected state</button>
                <a class="admin-button is-muted" href="/resource-enrichment.php">Refresh totals</a>
            </div>
            <progress max="100" value="0" data-enrichment-progress></progress>
            <p data-enrichment-status role="status" aria-live="polite">Select a state or all states to begin. Keep this page open while processing.</p>
            <noscript>JavaScript is required to process bounded batches.</noscript>
        </div>
        <p>Source matches are not proof that a business is currently open. Unmatched records, changed access and complex schedules may need review. Restart a state if its source changes during a scan or after publishing more Resources.</p>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>State</th><th>Published</th><th>Source found</th><th>Enriched</th><th>Review / no new fields</th><th>Scan</th></tr></thead>
            <tbody><?php foreach ($rows as $row): ?>
                <tr><td><?= $e(llama_osm_resource_states()[$row['region']] ?? $row['region']) ?></td><td><?= number_format((int)$row['total']) ?></td>
                    <td><?= number_format((int)$row['found']) ?></td><td><?= number_format((int)$row['enriched']) ?></td>
                    <td><?= number_format((int)$row['review']) ?></td><td><?= $e($row['status'] ?? 'Not started') ?><?= (int)$row['source_bytes']>0?' ('.(int)floor(100*(int)$row['source_offset']/(int)$row['source_bytes']).'%)':'' ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <p><a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors, ODbL</a></p>
    <?php endif; ?>
</section>
<?php if ($ready): ?><script src="/js/admin-resource-enrichment.js?v=20261128-1" defer></script><?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
