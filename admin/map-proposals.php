<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-proposals.php';
$admin = moderation_require_admin();
$actorId = (int) ($admin['id'] ?? 0);
$db = db();
$notice = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session expired. Reload before reviewing.';
    } else {
        try {
            $result = llama_map_proposal_review(
                $db,
                (int) ($_POST['proposal_id'] ?? 0),
                $actorId,
                (string) ($_POST['decision'] ?? ''),
                (int) ($_POST['campsite_id'] ?? 0),
                (string) ($_POST['note'] ?? '')
            );
            $notice = 'Proposal #' . $result['proposal_id'] . ' ' . $result['status'] . '.';
        } catch (Throwable $exception) {
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage() : 'Proposal could not be reviewed; no change was published.';
            if (!($exception instanceof InvalidArgumentException)) {
                llama_log_caught_exception($exception, 'admin.map-proposal-review', ['actor_id' => $actorId]);
            }
        }
    }
}
$filterPlace = max(0, (int) ($_GET['place'] ?? 0));
if ($filterPlace > 0) {
    $stmt = $db->prepare('SELECT p.*, pl.name AS place_name FROM place_map_proposals p JOIN places pl ON pl.id = p.place_id WHERE p.status = "pending" AND p.place_id = ? ORDER BY p.created_at ASC LIMIT 150');
    $stmt->execute([$filterPlace]);
} else {
    $stmt = $db->query('SELECT p.*, pl.name AS place_name FROM place_map_proposals p JOIN places pl ON pl.id = p.place_id WHERE p.status = "pending" ORDER BY p.created_at ASC LIMIT 150');
}
$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$sitesForPlace = [];
foreach ($proposals as $proposal) {
    $pid = (int) $proposal['place_id'];
    if (array_key_exists($pid, $sitesForPlace)) continue;
    $siteStmt = $db->prepare('SELECT id, site_code, site_name, map_feature_id FROM place_campsites WHERE place_id = ? AND is_active = 1 ORDER BY COALESCE(NULLIF(site_code,""),site_name),id');
    $siteStmt->execute([$pid]);
    $sitesForPlace[$pid] = $siteStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function map_mod_h(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Map proposals | Llama Scout Admin</title><link rel="stylesheet" href="https://llamascout.com/css/site/pages/place-map-contribute.css"></head>
<body class="map-review"><main class="map-contribute">
<header><p class="eyebrow">Llama Scout Administration</p><h1>Map proposals</h1><p>Review proposed boundaries before any public map changes. Existing Places and Sites keep their identities.</p>
<p><a href="/map-area-links.php">Manage Area-to-Site links</a></p></header>
<?php if ($notice !== ''): ?><p role="status"><?= map_mod_h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p role="alert"><?= map_mod_h($error) ?></p><?php endif; ?>
<?php if (!$proposals): ?><p>No pending map proposals<?= $filterPlace ? ' for this Place' : '' ?>.</p><?php endif; ?>
<?php foreach ($proposals as $proposal): ?>
<?php $data = json_decode((string) $proposal['proposal_json'], true) ?: []; $isSite = $proposal['feature_type'] === 'camping_site'; ?>
<section class="map-contribute-tutorial">
    <h2>#<?= (int) $proposal['id'] ?> · <?= map_mod_h($proposal['place_name']) ?></h2>
    <p><strong><?= map_mod_h($proposal['label']) ?></strong> · <?= map_mod_h($proposal['feature_type']) ?> · <?= map_mod_h($proposal['created_at']) ?></p>
    <p><?= !empty($proposal['feature_id']) ? 'Proposed correction to existing geometry #' . (int) $proposal['feature_id'] : 'Proposed new geometry for an existing Place' ?></p>
    <div class="map-proposal-preview" data-map-proposal-preview="<?= (int) $proposal['id'] ?>" aria-label="Proposed boundary map"></div>
    <details><summary>Inspect submitted GeoJSON</summary><pre class="map-review-json"><?= map_mod_h(json_encode($data['geometry'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details>
    <p><a target="_blank" rel="noopener noreferrer" href="https://llamascout.com/place-map-editor.php?slug=<?= rawurlencode((string) ($db->query('SELECT slug FROM places WHERE id = ' . (int) $proposal['place_id'])->fetchColumn() ?: '')) ?>">Open existing map editor for reference</a></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= map_mod_h(moderation_csrf_token()) ?>">
        <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>">
        <?php if ($isSite): ?>
            <label>Site identity this polygon belongs to (required before approval)
                <select name="campsite_id" required>
                    <option value="">Choose the existing Site, do not create a duplicate</option>
                    <?php foreach ($sitesForPlace[(int) $proposal['place_id']] as $site): ?>
                        <option value="<?= (int) $site['id'] ?>" <?= (int) ($data['site_details']['proposed_campsite_id'] ?? 0) === (int) $site['id'] ? 'selected' : '' ?>>
                            <?= map_mod_h($site['site_code'] ?: $site['site_name'] ?: 'Site #' . $site['id']) ?> (#<?= (int) $site['id'] ?>)<?= $site['map_feature_id'] ? ' · mapped' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Moderation note (optional)<input name="note" maxlength="2000"></label>
        <div class="map-contribute-toolbar">
            <button type="submit" name="decision" value="approve">Approve and publish</button>
            <button type="submit" name="decision" value="reject" formnovalidate>Reject</button>
        </div>
    </form>
</section>
<?php endforeach; ?>
<script type="application/json" id="map-proposals-payload"><?= json_encode(array_map(static function (array $p): array {
    $data = json_decode((string) $p['proposal_json'], true) ?: [];
    return ['id' => (int) $p['id'], 'geometry' => $data['geometry'] ?? null];
}, $proposals), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://llamascout.com/js/map-proposal-review.js"></script>
</main></body></html>
