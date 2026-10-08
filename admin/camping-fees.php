<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/camping-fees.php';
require_once __DIR__ . '/_dashboard.php';
$adminUser = moderation_require_admin();
$db = db();
$notice = '';
$error = '';
$placeId = max(0, (int) ($_GET['place_id'] ?? $_POST['place_id'] ?? 0));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session expired. Reload and try again.';
    } else {
        try {
            $siteId = (int) ($_POST['place_campsite_id'] ?? 0);
            llama_camping_fee_record_evidence(
                $db, $placeId, $siteId > 0 ? $siteId : null,
                (string) ($_POST['status'] ?? 'unknown'),
                (string) ($_POST['source_name'] ?? ''),
                (string) ($_POST['source_url'] ?? ''),
                (string) ($_POST['evidence_text'] ?? ''),
                (string) ($_POST['verified_on'] ?? ''),
                (int) $adminUser['id']
            );
            $notice = 'Verification saved. Source information remains unchanged.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$place = null;
$status = ['status' => 'unknown'];
$sites = [];
$history = [];
$recent = [];
try {
    if ($placeId > 0) {
        $stmt = $db->prepare('SELECT id, name, source_type FROM places WHERE id = ? LIMIT 1');
        $stmt->execute([$placeId]);
        $place = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($place) {
            $status = llama_camping_fee_for_place($db, $placeId);
            $stmt = $db->prepare('SELECT id, site_code, site_name FROM place_campsites WHERE place_id = ? ORDER BY site_code, id LIMIT 500');
            $stmt->execute([$placeId]);
            $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $db->prepare('SELECT place_campsite_id, status, source_name, source_url, evidence_text, verified_on FROM place_camping_fee_evidence WHERE place_id = ? ORDER BY verified_on DESC, id DESC LIMIT 40');
            $stmt->execute([$placeId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    $recent = $db->query("SELECT id, name FROM places WHERE status NOT IN ('removed','archived') ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Could not read the fee verification records. Ensure the SQL table is installed.';
}
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$adminPageTitle = 'Camping Fee Verification';
$adminPageEyebrow = 'Places';
$adminActiveNav = 'places';
$adminUsesSplitCss = true;
$adminPageStyles = [];
$adminFeatureStyles = [];
require __DIR__ . '/_header.php';
?>
<section class="admin-panel">
    <header class="admin-panel-header"><h2>Find a Place</h2></header>
    <form method="get" action="/admin/camping-fees.php">
        <label for="fee-place-id">Place ID</label>
        <input id="fee-place-id" type="number" name="place_id" min="1" value="<?= $placeId ?: '' ?>" required>
        <button type="submit">Open Place</button>
    </form>
    <h3>Recent Places</h3>
    <ul>
        <?php foreach ($recent as $item): ?>
            <li><a href="/admin/camping-fees.php?place_id=<?= (int) $item['id'] ?>"><?= $h($item['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php if ($notice !== ''): ?><p role="status"><?= $h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p role="alert"><?= $h($error) ?></p><?php endif; ?>
<?php if ($place): ?>
<section class="admin-panel">
    <header class="admin-panel-header"><h2><?= $h($place['name']) ?></h2></header>
    <p>Campground classification: <strong><?= $h(ucfirst((string) $status['status'])) ?></strong></p>
    <p>Only save evidence from a source that explicitly confirms paid camping, free camping, or both. Reservation availability alone is not proof of payment.</p>
    <form method="post" action="/admin/camping-fees.php?place_id=<?= $placeId ?>">
        <input type="hidden" name="csrf_token" value="<?= $h(moderation_csrf_token()) ?>">
        <input type="hidden" name="place_id" value="<?= $placeId ?>">
        <label for="fee-site">Record applies to</label>
        <select id="fee-site" name="place_campsite_id">
            <option value="0">Whole campground / Place</option>
            <?php foreach ($sites as $site): ?>
                <option value="<?= (int) $site['id'] ?>"><?= $h($site['site_code'] ?: $site['site_name'] ?: ('Site ' . $site['id'])) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="fee-status">Confirmed status</label>
        <select id="fee-status" name="status" required>
            <option value="paid">Paid</option><option value="free">Free</option><option value="mixed">Mixed (whole campground only)</option>
        </select>
        <label for="fee-source">Official source</label>
        <input id="fee-source" name="source_name" maxlength="255" required>
        <label for="fee-url">Evidence URL</label>
        <input id="fee-url" name="source_url" type="url" required>
        <label for="fee-evidence">Evidence and reasoning</label>
        <textarea id="fee-evidence" name="evidence_text" rows="5" required></textarea>
        <label for="fee-date">Checked on</label>
        <input id="fee-date" type="date" name="verified_on" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
        <button type="submit">Save Verified Evidence</button>
    </form>
</section>
<section class="admin-panel">
    <header class="admin-panel-header"><h2>Verification History</h2></header>
    <?php if (!$history): ?><p>No verified fee evidence saved.</p><?php endif; ?>
    <ul>
    <?php foreach ($history as $entry): ?>
        <li><strong><?= $h($entry['status']) ?></strong> (<?= $entry['place_campsite_id'] === null ? 'Whole Place' : 'Campsite ' . (int) $entry['place_campsite_id'] ?>), <?= $h($entry['verified_on']) ?>, <?= $h($entry['source_name']) ?>.
        <a href="<?= $h($entry['source_url']) ?>" rel="noopener noreferrer" target="_blank">Source</a>
        <p><?= nl2br($h($entry['evidence_text'])) ?></p></li>
    <?php endforeach; ?>
    </ul>
</section>
<?php elseif ($placeId > 0): ?><section class="admin-panel"><p>Place not found.</p></section><?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
