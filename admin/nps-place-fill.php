<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/nps.php';
require_once dirname(__DIR__) . '/app/nps-place-fill.php';

$adminUser = moderation_require_admin();
$adminPageTitle = 'NPS Place Enrichment';
$adminPageEyebrow = 'Reference Data';
$adminActiveNav = 'reference-data';

$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? 'Tuolumne'));
$query = mb_substr($query, 0, 100);
if ($query === '') {
    $query = 'Tuolumne';
}
$error = '';
$notice = '';
$matches = [];
$previews = [];

try {
    $mainDb = db();
    $npsDb = nps_db();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'fill_missing') {
        if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Session expired. Reload the page before importing.');
        }
        $park = trim((string) ($_POST['park'] ?? ''));
        $npsId = trim((string) ($_POST['nps_id'] ?? ''));
        $ridbId = trim((string) ($_POST['ridb_id'] ?? ''));
        if ($park === '' || $npsId === '' || $ridbId === '') {
            throw new InvalidArgumentException('The selected source pairing was incomplete.');
        }
        $result = llama_nps_fill_apply($npsDb, $mainDb, $park, $npsId, $ridbId, (int) ($adminUser['id'] ?? 0));
        $notice = 'Updated ' . $result['place_name'] . ': '
            . $result['fields_filled'] . ' empty fields filled, '
            . $result['photos_added'] . ' photos added, '
            . $result['fields_skipped'] . ' conflicting fields preserved for review.';
    }
    $list = $npsDb->prepare(
        "SELECT n.park_code, n.record_id, n.record_name, r.ridb_facility_id
           FROM nps_raw_records n
           JOIN nps_ridb_review_events r
             ON r.nps_park_code = n.park_code AND r.nps_record_id = n.record_id
      LEFT JOIN nps_ridb_review_events newer
             ON newer.nps_park_code = r.nps_park_code
            AND newer.nps_record_id = r.nps_record_id AND newer.id > r.id
          WHERE n.resource_type = 'campgrounds'
            AND r.decision = 'confirmed_match' AND newer.id IS NULL
            AND n.source_hash = r.nps_source_hash
            AND n.record_name LIKE ?
          ORDER BY n.record_name LIMIT 25"
    );
    $list->execute(['%' . $query . '%']);
    foreach ($list->fetchAll(PDO::FETCH_ASSOC) ?: [] as $candidate) {
        try {
            $pair = llama_nps_fill_pair($npsDb, $mainDb,
                (string) $candidate['park_code'], (string) $candidate['record_id'],
                (string) $candidate['ridb_facility_id']);
            $preview = llama_nps_fill_preview($mainDb, $pair);
            $matches[] = ['candidate' => $candidate, 'pair' => $pair];
            $previews[(string) $candidate['record_id']] = $preview;
        } catch (RuntimeException $exception) {
            $matches[] = ['candidate' => $candidate, 'error' => $exception->getMessage()];
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <header class="admin-panel-header">
        <div>
            <p>NPS / RIDB</p>
            <h2>Fill missing Place information</h2>
        </div>
    </header>
    <div class="admin-panel-body">
        <p>This reads the existing NPS source cache. It only fills empty Place facts and adds
            photos with new URLs. Existing answers are protected and disagreements stay visible below.
            Numbered campsite records are not changed.</p>
        <form class="admin-form" method="get" action="/nps-place-fill.php">
            <label>
                <span>Campground name</span>
                <input type="search" name="q" value="<?= moderation_e($query) ?>" maxlength="100" required>
            </label>
            <button class="admin-button" type="submit">Find confirmed campgrounds</button>
        </form>
        <div class="admin-user-form-actions">
            <a class="admin-button is-secondary" href="/nps-ridb-matches.php">Review campground matches</a>
            <a class="admin-button is-secondary" href="/nps-field-reconciliation.php">Review NPS / RIDB facts</a>
        </div>
    </div>
</section>

<?php if ($error !== ''): ?>
<section class="admin-panel"><div class="admin-panel-body">
    <div class="admin-user-notice is-error" role="alert"><?= moderation_e($error) ?></div>
</div></section>
<?php endif; ?>
<?php if ($notice !== ''): ?>
<section class="admin-panel"><div class="admin-panel-body">
    <div class="admin-user-notice" role="status"><?= moderation_e($notice) ?></div>
</div></section>
<?php endif; ?>

<?php if ($error === '' && !$matches): ?>
<section class="admin-panel"><div class="admin-panel-body">
    <p>No current confirmed, already-imported RIDB Place matches for “<?= moderation_e($query) ?>”.
        Confirm the NPS/RIDB source identity first on the campground matching page.
        A new NPS import is not necessary.</p>
</div></section>
<?php endif; ?>

<?php foreach ($matches as $item): ?>
    <?php $row = $item['candidate']; $preview = $previews[(string) $row['record_id']] ?? null; ?>
    <section class="admin-panel">
        <header class="admin-panel-header">
            <div>
                <p><?= moderation_e(strtoupper((string) $row['park_code'])) ?> ·
                    RIDB <?= moderation_e((string) $row['ridb_facility_id']) ?></p>
                <h2><?= moderation_e((string) $row['record_name']) ?></h2>
            </div>
        </header>
        <div class="admin-panel-body">
            <?php if ($preview === null): ?>
                <p><?= moderation_e((string) ($item['error'] ?? 'Unable to compare this campground.')) ?></p>
            <?php else: ?>
                <p><strong><?= count($preview['fill']) ?></strong> empty fields available to fill;
                    <strong><?= count($preview['conflict']) ?></strong> different existing values protected;
                    <strong><?= count($preview['images_new']) ?></strong> additional photo URLs.</p>
                <?php if ($preview['fill']): ?>
                    <details>
                        <summary>Fields that can be filled (<?= count($preview['fill']) ?>)</summary>
                        <ul>
                            <?php foreach ($preview['fill'] as $field => $value): ?>
                                <li><strong><?= moderation_e($field) ?>:</strong>
                                    <?= moderation_e((string) $value) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <details <?= $preview['conflict'] ? 'open' : '' ?>>
                    <summary>Preserved for review (<?= count($preview['conflict']) ?>)</summary>
                    <?php if (!$preview['conflict']): ?>
                        <p>No conflicting mapped Place fields.</p>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead><tr><th>Field</th><th>Existing value</th><th>NPS value (not applied)</th></tr></thead>
                            <tbody>
                            <?php foreach ($preview['conflict'] as $field => $values): ?>
                                <tr>
                                    <td><?= moderation_e($field) ?></td>
                                    <td><?= moderation_e((string) $values['existing']) ?></td>
                                    <td><?= moderation_e((string) $values['nps']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </details>
                <p>Other information remains in the NPS source cache.
                    Similar photos at different URLs may still require later visual deduplication.</p>
                <form class="admin-form" method="post" action="/nps-place-fill.php">
                    <input type="hidden" name="action" value="fill_missing">
                    <input type="hidden" name="q" value="<?= moderation_e($query) ?>">
                    <input type="hidden" name="csrf_token" value="<?= moderation_e(moderation_csrf_token()) ?>">
                    <input type="hidden" name="park" value="<?= moderation_e((string) $row['park_code']) ?>">
                    <input type="hidden" name="nps_id" value="<?= moderation_e((string) $row['record_id']) ?>">
                    <input type="hidden" name="ridb_id" value="<?= moderation_e((string) $row['ridb_facility_id']) ?>">
                    <button class="admin-button" type="submit">Fill missing fields and add photos</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/_footer.php'; ?>
