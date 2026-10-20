<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/place-map-proposals.php';
$admin = moderation_require_admin();
$db = db();
$actorId = (int) ($admin['id'] ?? 0);
$placeId = max(0, (int) ($_GET['place'] ?? $_POST['place_id'] ?? 0));
$error = '';
$notice = '';
$place = null;
if ($placeId > 0) {
    $q = $db->prepare('SELECT id, name FROM places WHERE id = ? LIMIT 1');
    $q->execute([$placeId]);
    $place = $q->fetch(PDO::FETCH_ASSOC);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!moderation_verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'Session expired. Reload and try again.';
    } elseif (!$place) {
        $error = 'Choose a valid Place.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'save');
            if ($action === 'save') {
                llama_map_link_site_area($db, $placeId, (int) ($_POST['campsite_id'] ?? 0), (int) ($_POST['area_feature_id'] ?? 0), $actorId);
                $notice = 'Site-to-Area relationship saved.';
            } elseif ($action === 'assign_unambiguous') {
                $candidates = $db->prepare('SELECT cs.id AS campsite_id, MIN(mf.id) AS area_id, COUNT(mf.id) AS matches FROM place_campsites cs JOIN place_map_features mf ON mf.place_id = cs.place_id AND mf.feature_type IN ("camping_area","parking_area") AND mf.is_active = 1 AND ST_CONTAINS(mf.geometry,ST_GeomFromText(CONCAT("POINT(",cs.longitude," ",cs.latitude,")"),4326)) LEFT JOIN place_campsite_area_links l ON l.place_id = cs.place_id AND l.campsite_id = cs.id WHERE cs.place_id = ? AND cs.is_active = 1 AND l.campsite_id IS NULL AND cs.latitude IS NOT NULL AND cs.longitude IS NOT NULL GROUP BY cs.id HAVING COUNT(mf.id) = 1');
                $candidates->execute([$placeId]);
                $rows = $candidates->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $db->beginTransaction();
                try {
                    foreach ($rows as $row) llama_map_link_site_area($db, $placeId, (int) $row['campsite_id'], (int) $row['area_id'], $actorId);
                    $db->commit();
                } catch (Throwable $exception) {
                    if ($db->inTransaction()) $db->rollBack();
                    throw $exception;
                }
                $notice = count($rows) . ' Sites with exactly one containing Area were linked. Overlaps and unmatched Sites were skipped.';
            } else {
                throw new InvalidArgumentException('Unknown action.');
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Linking failed. No partial batch was committed.';
            if (!($exception instanceof InvalidArgumentException)) llama_log_caught_exception($exception, 'admin.map-area-links', ['place_id' => $placeId]);
        }
    }
}
$sites = [];
$areas = [];
$links = [];
if ($place) {
    $q = $db->prepare('SELECT id, site_code, site_name, latitude, longitude, map_feature_id FROM place_campsites WHERE place_id = ? AND is_active = 1 ORDER BY COALESCE(NULLIF(site_code,""),site_name),id');
    $q->execute([$placeId]);
    $sites = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $q = $db->prepare('SELECT id, label, feature_type FROM place_map_features WHERE place_id = ? AND is_active = 1 AND feature_type IN ("camping_area", "parking_area") ORDER BY label,id');
    $q->execute([$placeId]);
    $areas = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $q = $db->prepare('SELECT campsite_id, area_feature_id FROM place_campsite_area_links WHERE place_id = ?');
    $q->execute([$placeId]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $links[(int) $row['campsite_id']] = (int) $row['area_feature_id'];
}
function area_link_h(mixed $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Area-to-Site relationships | Llama Scout</title><link rel="stylesheet" href="https://llamascout.com/css/site/pages/place-map-contribute.css"></head>
<body class="map-review"><main class="map-contribute">
<header><p class="eyebrow">Llama Scout Administration</p><h1>Area-to-Site relationships</h1><p>Use the existing Site IDs and green or blue mapped Areas. No duplicated sites or automatic replacement of conflicting relationships.</p><p><a href="/map-proposals.php">Map moderation queue</a></p></header>
<form method="get"><label>Place ID<input name="place" inputmode="numeric" type="number" min="1" required value="<?= $placeId ?: '' ?>"></label><button type="submit">Open Place</button></form>
<?php if ($error): ?><p role="alert"><?= area_link_h($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?= area_link_h($notice) ?></p><?php endif; ?>
<?php if ($place): ?>
<section class="map-contribute-tutorial"><h2><?= area_link_h($place['name']) ?></h2>
<p><?= count($sites) ?> Sites · <?= count($areas) ?> mapped Areas · <?= count($links) ?> explicit relationships.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= area_link_h(moderation_csrf_token()) ?>"><input type="hidden" name="place_id" value="<?= $placeId ?>"><input type="hidden" name="action" value="assign_unambiguous"><button type="submit">Link unambiguous GPS matches</button></form>
<p>Only Sites inside exactly one Area and without a saved relationship will be linked. Sites in overlapping Areas and Sites outside all Areas stay unchanged.</p></section>
<section class="map-contribute-tutorial"><h2>Assign one Site</h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= area_link_h(moderation_csrf_token()) ?>"><input type="hidden" name="place_id" value="<?= $placeId ?>"><input type="hidden" name="action" value="save">
<label>Existing Site<select name="campsite_id" required><option value="">Choose Site</option><?php foreach ($sites as $site): ?><option value="<?= (int) $site['id'] ?>"><?= area_link_h($site['site_code'] ?: $site['site_name'] ?: 'Site #' . $site['id']) ?> (#<?= (int) $site['id'] ?>)<?= isset($links[(int) $site['id']]) ? ' · linked to Area #' . $links[(int) $site['id']] : ' · unlinked' ?></option><?php endforeach; ?></select></label>
<label>Home Area<select name="area_feature_id" required><option value="0">No Area (clear relationship)</option><?php foreach ($areas as $area): ?><option value="<?= (int) $area['id'] ?>"><?= area_link_h($area['label'] ?: 'Area #' . $area['id']) ?> · <?= area_link_h($area['feature_type']) ?></option><?php endforeach; ?></select></label>
<button type="submit">Save Site's Area</button></form></section>
<?php endif; ?>
</main></body></html>
