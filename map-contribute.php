<?php

declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/place-map-proposals.php';
require_verified_email();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$db = db();
if ($userId < 1 || !llama_contributor_can($db, $userId, 'field_report')) {
    http_response_code(403);
    exit('Scout access is required.');
}
$slug = trim((string) ($_GET['slug'] ?? ''));
$place = $slug !== '' ? place_member_by_slug($slug) : null;
$placeId = (int) ($place['id'] ?? 0);
if (!$place || !user_has_place_complete_access($placeId, $userId)) {
    http_response_code(404);
    exit('Place not found or access unavailable.');
}
$latitude = isset($place['latitude']) ? (float) $place['latitude'] : 0;
$longitude = isset($place['longitude']) ? (float) $place['longitude'] : 0;
if (!is_finite($latitude) || !is_finite($longitude) || ($latitude === 0.0 && $longitude === 0.0)) {
    http_response_code(422);
    exit('This Place needs a location before its boundary can be mapped.');
}
$areas = [];
$features = llama_place_map_features($db, $placeId, true, true);
foreach ($features as $feature) {
    if (in_array((string) ($feature['feature_type'] ?? ''), ['camping_area','parking_area'], true)) {
        $areas[] = ['id' => (int) $feature['id'], 'name' => (string) ($feature['label'] ?: 'Mapped Area #' . $feature['id'])];
    }
}
$sites = [];
foreach (llama_place_campsites($db, $placeId) as $site) {
    $sites[] = ['id' => (int) $site['id'], 'name' => trim((string) ($site['site_code'] ?: $site['site_name'] ?: 'Site #' . $site['id']))];
}
function map_contribute_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
$pageTitle = 'Map a location | Llama Scout';
$pageStyles = ['site/pages/place-map-contribute.css'];
require __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<section class="map-contribute" id="map-contribute" data-slug="<?= map_contribute_h($slug) ?>"
    data-lat="<?= map_contribute_h((string) $latitude) ?>" data-lng="<?= map_contribute_h((string) $longitude) ?>"
    data-csrf="<?= map_contribute_h(community_csrf_token()) ?>">
    <header>
        <p class="eyebrow">Scout mapping</p>
        <h1>Map <?= map_contribute_h((string) $place['name']) ?></h1>
        <p>Add a boundary to an existing location. Nothing changes publicly until an admin reviews it.</p>
    </header>
    <section class="map-contribute-tutorial" aria-label="How mapping works">
        <h2>How to map</h2>
        <p>Choose what you are mapping, then tap around the perimeter to place three or more points. You can also tap Add GPS point as you walk the edge. Review the outline before submitting.</p>
    </section>
    <form id="map-contribute-form">
        <div class="map-contribute-fields">
            <label>What are you mapping?
                <select name="feature_type" required>
                    <option value="place_boundary">The whole named Place (green / purple)</option>
                    <option value="camping_area">A camping area within the Place (green)</option>
                    <option value="parking_area">A parking area (blue)</option>
                    <option value="camping_site">A specific campsite (orange)</option>
                </select>
            </label>
            <label>Map label <input name="label" maxlength="255" required value="<?= map_contribute_h((string) $place['name']) ?>"></label>
            <label>Update an existing mapped shape (optional)
                <select name="feature_id"><option value="0">New boundary for this existing Place</option>
                    <?php foreach ($features as $feature): ?>
                        <option value="<?= (int) $feature['id'] ?>" data-type="<?= map_contribute_h((string) $feature['feature_type']) ?>"><?= map_contribute_h((string) ($feature['label'] ?: '#' . $feature['id'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label data-parent-area>Containing Area (optional for a campsite)
                <select name="area_id"><option value="">No Area (standalone campsite)</option>
                    <?php foreach ($areas as $area): ?><option value="<?= $area['id'] ?>"><?= map_contribute_h($area['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label data-existing-site>Existing Site this boundary represents
                <select name="site_id"><option value="">Select a Site (admin will verify the match)</option>
                    <?php foreach ($sites as $site): ?><option value="<?= $site['id'] ?>"><?= map_contribute_h($site['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <div id="map-contribute-map" class="map-contribute-map" role="application" aria-label="Tap to add perimeter points"></div>
        <div class="map-contribute-toolbar">
            <button type="button" id="map-contribute-gps">Add GPS point</button>
            <button type="button" id="map-contribute-undo">Undo point</button>
            <button type="button" id="map-contribute-clear">Start over</button>
            <output id="map-contribute-count" aria-live="polite">0 points</output>
        </div>
        <p class="map-contribute-note">Coordinates are approximate when drawn manually. Walking the perimeter depends on your device's GPS accuracy. Do not enter restricted land.</p>
        <button type="submit" class="map-contribute-submit">Submit boundary for review</button>
        <p role="status" aria-live="polite" id="map-contribute-status"></p>
    </form>
</section>
<script type="application/json" id="map-contribute-features"><?= json_encode($features, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/map-contribute.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
