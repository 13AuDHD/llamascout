<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/place-map.php';

$placeMapHasExactCoordinates =
    !empty($hasMemberAccess)
    && isset(
        $place['latitude'],
        $place['longitude']
    )
    && is_numeric(
        $place['latitude']
    )
    && is_numeric(
        $place['longitude']
    );

$placeMapHasPublicCoordinates =
    isset(
        $place['public_latitude'],
        $place['public_longitude']
    )
    && is_numeric(
        $place['public_latitude']
    )
    && is_numeric(
        $place['public_longitude']
    );

$placeMapLatitude =
    $placeMapHasExactCoordinates
        ? (float) $place['latitude']
        : (
            $placeMapHasPublicCoordinates
                ? (float) $place['public_latitude']
                : null
        );

$placeMapLongitude =
    $placeMapHasExactCoordinates
        ? (float) $place['longitude']
        : (
            $placeMapHasPublicCoordinates
                ? (float) $place['public_longitude']
                : null
        );

if (
    $placeMapLatitude === null
    || $placeMapLongitude === null
) {
    return;
}

$placeMapHasLayerAccess =
    !empty(
        $hasMemberAccess
    );

$placeMapCanEditAreas =
    $placeMapHasExactCoordinates
    && (int) ($userId ?? 0) > 0
    && llama_contributor_can(
        db(),
        (int) $userId,
        'field_report'
    );

$placeMapMaxZoom =
    $placeMapHasLayerAccess
        ? 20
        : 11;

$placeMapIsScopedContributor =
    $placeMapHasLayerAccess
    && empty(
        $hasGlobalMemberAccess
    )
    && !empty(
        $hasContributorPlaceAccess
    );

$placeMapTiles =
    $placeMapHasLayerAccess
        ? llama_place_map_member_tiles()
        : [
            'available' => false,
            'light' => '',
            'dark' => '',
        ];

$placeMapCellApi =
    '/api/place-cell-coverage.php?slug='
    . rawurlencode(
        (string) (
            $place['slug']
            ?? ''
        )
    );

$placeMapFeaturesApi =
    '/api/place-map-features.php?slug='
    . rawurlencode(
        (string) (
            $place['slug']
            ?? ''
        )
    );

$placeMapEditorUrl =
    '/place-map-editor.php?slug='
    . rawurlencode(
        (string) (
            $place['slug']
            ?? ''
        )
    );
?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<section
    class="place-map-section"
    aria-labelledby="place-map-heading"
>
    <div class="place-map-heading">
        <div>
            <p class="eyebrow">
                Map
            </p>

            <h2 id="place-map-heading">
                Explore around this Place
            </h2>
        </div>

        <div class="place-map-heading-actions">
            <span class="place-map-precision">
                <?= $placeMapHasExactCoordinates
                    ? 'Exact location'
                    : 'Approximate public location' ?>
            </span>

            <?php if ($placeMapCanEditAreas): ?>
                <a
                    class="place-map-edit-areas"
                    href="<?= place_h(
                        $placeMapEditorUrl
                    ) ?>"
                >
                    <?= llama_icon(
                        'edit'
                    ) ?>

                    Edit mapped areas
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div
        class="map-card place-map-card"
        data-map-member="<?= $placeMapHasLayerAccess ? '1' : '0' ?>"
        data-map-scoped="<?= $placeMapIsScopedContributor ? '1' : '0' ?>"
        data-map-light-tile="<?= place_h(
            (string) (
                $placeMapTiles['light']
                ?? ''
            )
        ) ?>"
        data-map-dark-tile="<?= place_h(
            (string) (
                $placeMapTiles['dark']
                ?? ''
            )
        ) ?>"
        data-place-cell-api="<?= place_h(
            $placeMapCellApi
        ) ?>"
        data-place-map-features-api="<?= $placeMapHasExactCoordinates
            ? place_h($placeMapFeaturesApi)
            : '' ?>"
    >
        <?php if ($placeMapHasLayerAccess): ?>

            <div
                id="map-layer-control"
                class="map-tools-control"
                aria-label="Map tools"
            >
                <div class="map-tools-bar">

                    <button
                        type="button"
                        class="map-tool-trigger"
                        data-map-tool="map"
                        aria-expanded="false"
                        aria-controls="map-tools-panel-map"
                    >
                        Map

                        <span
                            id="map-tool-map-value"
                            class="map-tool-value"
                        >
                            Auto
                        </span>
                    </button>

                    <button
                        type="button"
                        class="map-tool-trigger"
                        data-map-tool="land"
                        aria-expanded="false"
                        aria-controls="map-tools-panel-land"
                    >
                        Land

                        <span
                            id="map-tool-land-count"
                            class="map-tool-count"
                            hidden
                        >
                            0
                        </span>
                    </button>

                    <button
                        type="button"
                        class="map-tool-trigger"
                        data-map-tool="weather"
                        aria-expanded="false"
                        aria-controls="map-tools-panel-weather"
                    >
                        Weather
                    </button>

                    <button
                        type="button"
                        class="map-tool-trigger"
                        data-map-tool="cell"
                        aria-expanded="false"
                        aria-controls="map-tools-panel-cell"
                    >
                        Cell
                    </button>

                </div>

                <div
                    id="map-tools-panel-map"
                    class="map-tools-panel"
                    data-map-tool-panel="map"
                    hidden
                >
                    <p class="map-tools-panel-title">
                        Map style
                    </p>

                    <div
                        class="map-tools-map-options"
                        role="group"
                        aria-label="Choose map style"
                    >
                        <button
                            type="button"
                            data-map-layer="auto"
                            class="is-active"
                            aria-pressed="true"
                        >
                            Auto
                        </button>

                        <button
                            type="button"
                            data-map-layer="street"
                            aria-pressed="false"
                        >
                            Street
                        </button>

                        <button
                            type="button"
                            data-map-layer="terrain"
                            aria-pressed="false"
                        >
                            Terrain
                        </button>

                        <button
                            type="button"
                            data-map-layer="topo"
                            aria-pressed="false"
                        >
                            Topo
                        </button>

                        <button
                            type="button"
                            data-map-layer="dark"
                            aria-pressed="false"
                        >
                            Dark
                        </button>

                        <button
                            type="button"
                            data-map-layer="satellite"
                            aria-pressed="false"
                        >
                            Satellite
                        </button>
                    </div>
                </div>

                <div
                    id="map-tools-panel-land"
                    class="map-tools-panel"
                    data-map-tool-panel="land"
                    hidden
                >
                    <p class="map-tools-panel-title">
                        Land layers
                    </p>

                    <div id="map-tools-land-slot"></div>
                </div>

                <div
                    id="map-tools-panel-weather"
                    class="map-tools-panel"
                    data-map-tool-panel="weather"
                    hidden
                >
                    <p class="map-tools-panel-title">
                        Weather layers
                    </p>

                    <p class="map-tools-empty">
                        Weather overlays will appear here.
                    </p>
                </div>

                <div
                    id="map-tools-panel-cell"
                    class="map-tools-panel"
                    data-map-tool-panel="cell"
                    hidden
                >
                    <p class="map-tools-panel-title">
                        Cell coverage
                    </p>

                    <p class="map-tools-empty">
                        Carrier coverage layers will appear here.
                    </p>
                </div>
            </div>

        <?php endif; ?>

        <div
            id="llama-map"
            class="place-inline-map"
            data-place-latitude="<?= place_h(
                (string) $placeMapLatitude
            ) ?>"
            data-place-longitude="<?= place_h(
                (string) $placeMapLongitude
            ) ?>"
            data-place-name="<?= place_h(
                (string) (
                    $place['name']
                    ?? 'Place'
                )
            ) ?>"
            data-place-max-zoom="<?= $placeMapMaxZoom ?>"
            data-place-exact="<?= $placeMapHasExactCoordinates ? '1' : '0' ?>"
            aria-label="Map showing <?= place_h(
                (string) (
                    $place['name']
                    ?? 'this Place'
                )
            ) ?>"
        ></div>

        <div
            class="place-map-area-legend"
            data-place-map-area-legend
            hidden
            aria-label="Mapped area controls"
        >
            <button
                type="button"
                class="place-map-area-legend-item is-place-boundary"
                data-map-feature-toggle="place_boundary"
                aria-pressed="true"
                hidden
            >
                <i aria-hidden="true"></i>
                Place boundary
            </button>

            <button
                type="button"
                class="place-map-area-legend-item is-camping-area"
                data-map-feature-toggle="camping_area"
                aria-pressed="true"
                hidden
            >
                <i aria-hidden="true"></i>
                Camping area
            </button>

            <button
                type="button"
                class="place-map-area-legend-item is-parking-area"
                data-map-feature-toggle="parking_area"
                aria-pressed="true"
                hidden
            >
                <i aria-hidden="true"></i>
                Parking area
            </button>

            <button
                type="button"
                class="place-map-area-legend-item is-camping-site"
                data-map-feature-toggle="camping_site"
                aria-pressed="true"
                hidden
            >
                <i aria-hidden="true"></i>
                Individual campsites
            </button>

            <button
                type="button"
                class="place-map-area-fit"
                data-fit-mapped-areas
                hidden
            >
                Fit areas
            </button>
        </div>
    </div>

    <?php if (!$placeMapHasLayerAccess): ?>
        <p class="place-map-note">
            This map uses the approximate public location.
            Complete Place access unlocks the exact pin and detailed map layers.
        </p>
    <?php endif; ?>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/place-map.js"></script>

<?php if ($placeMapHasLayerAccess): ?>
    <script src="/js/map-land-overlays.js"></script>
    <script src="/js/map-weather-overlays.js"></script>
    <script src="https://unpkg.com/h3-js@4.2.1"></script>
    <script src="/js/place-map-cell-overlays.js"></script>
    <script src="/js/map-tools.js"></script>
<?php endif; ?>
