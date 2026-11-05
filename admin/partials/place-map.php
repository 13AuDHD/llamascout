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
