<?php

declare(strict_types=1);

$mapFeatures =
    is_array($adminPlaceMapFeatures ?? null)
        ? $adminPlaceMapFeatures
        : [];

$mapLatitude =
    isset($place['latitude'])
    && is_numeric($place['latitude'])
        ? (float) $place['latitude']
        : null;

$mapLongitude =
    isset($place['longitude'])
    && is_numeric($place['longitude'])
        ? (float) $place['longitude']
        : null;
?>

<section
    class="admin-place-map-panel"
    id="place-map"
    data-admin-place-map-panel
>
    <header class="admin-place-map-header">
        <div>
            <p>Map</p>
            <h2>Place layout</h2>
            <span class="admin-place-muted">
                Tap the Place boundary, an Area, or a Site to edit that selection below.
            </span>
        </div>

        <a
            class="admin-button is-muted"
            data-admin-place-map-edit
            href="https://llamascout.com/place-map-editor.php?slug=<?= rawurlencode(
                (string) ($place['slug'] ?? '')
            ) ?>"
            target="_blank"
            rel="noopener"
        >
            Edit map
        </a>
    </header>

    <div class="admin-place-map-wrap">
        <div
            class="admin-place-map-style"
            role="group"
            aria-label="Map style"
        >
            <button
                type="button"
                data-admin-map-style="street"
            >
                Street
            </button>

            <button
                type="button"
                data-admin-map-style="satellite"
                class="is-active"
            >
                Satellite
            </button>

            <button
                type="button"
                data-admin-map-style="terrain"
            >
                Terrain
            </button>
        </div>

        <div
            class="admin-place-map"
            data-admin-place-map
            aria-label="Place, Area, and Site map"
        ></div>
    </div>

    <div
        class="admin-place-map-selection"
        data-admin-place-map-selection
        role="status"
        aria-live="polite"
    >
        <strong><?= $e((string) ($place['name'] ?? 'Place')) ?></strong>
        <span>Place</span>
    </div>

    <form
        class="admin-place-site-location"
        data-admin-site-location-form
        method="post"
        action="/place.php?id=<?= (int) $placeId ?>#place-map"
    >
        <input type="hidden" name="place_id" value="<?= (int) $placeId ?>">
        <input type="hidden" name="place_admin_action" value="save-site-location">
        <input type="hidden" name="csrf_token" value="<?= $e(moderation_csrf_token()) ?>">

        <header class="admin-place-site-location-header">
            <h3>Individual Site GPS point</h3>
            <p>Mark the exact Site location without changing the Place boundary or other Sites.</p>
        </header>

        <label for="admin-site-location-target">Site</label>
        <select
            name="site_target"
            id="admin-site-location-target"
            data-site-location-target
            required
        >
            <option value="">Choose a Site</option>
            <option value="new">+ Create new Site from a GPS point</option>
            <?php foreach ($adminPlaceCampsites as $site): ?>
                <option
                    value="<?= $e((string) ($site['id'] ?? '')) ?>"
                    data-latitude="<?= $e((string) ($site['latitude'] ?? '')) ?>"
                    data-longitude="<?= $e((string) ($site['longitude'] ?? '')) ?>"
                    <?= (int) ($_GET['site'] ?? 0) === (int) ($site['scope_id'] ?? 0) ? 'selected' : '' ?>
                ><?= $e((string) ($site['label'] ?? 'Unnamed Site')) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="admin-place-site-location-new" data-site-location-new hidden>
            <span>New Site name</span>
            <input type="text" name="site_name" data-site-location-name
                maxlength="120" autocomplete="off" placeholder="Rocky Camp" disabled>
        </label>

        <div class="admin-place-site-location-coordinates">
            <label>
                <span>Latitude</span>
                <input type="text" name="site_latitude" data-site-location-latitude
                    inputmode="decimal" placeholder="37.226325" autocomplete="off" required>
            </label>
            <label>
                <span>Longitude</span>
                <input type="text" name="site_longitude" data-site-location-longitude
                    inputmode="decimal" placeholder="-107.695025" autocomplete="off" required>
            </label>
        </div>

        <div class="admin-place-site-location-actions">
            <button type="button" class="admin-button is-muted" data-site-location-pick>Place pin on map</button>
            <button type="button" class="admin-button is-muted" data-site-location-gps>Use device GPS</button>
            <button type="submit" class="admin-button" data-site-location-save>Save Site GPS point</button>
        </div>
        <p class="admin-place-site-location-status" data-site-location-status role="status" aria-live="polite">
            Choose an existing Site, then enter its coordinates or select its location on the map.
        </p>
    </form>

    <script
        type="application/json"
        data-admin-place-map-data
    ><?= json_encode(
        [
            'place' => [
                'id' => $placeId,
                'slug' => (string) ($place['slug'] ?? ''),
                'name' => (string) ($place['name'] ?? ''),
                'latitude' => $mapLatitude,
                'longitude' => $mapLongitude,
            ],
            'features' => $mapFeatures,
        ],
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_THROW_ON_ERROR
    ) ?></script>
</section>
