<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$mapUser = current_user();
$mapUserId =
    is_array($mapUser)
        ? (int) ($mapUser['id'] ?? 0)
        : 0;

$hasMapMemberAccess =
    user_has_member_access(
        $mapUserId > 0
            ? $mapUserId
            : null
    );

$pageTitle = 'Llama Scout | Know the Place Before You Go';
$pageDescription = 'Find campgrounds, RV-friendly stops, overnight parking, and useful resources like water and dump stations. Explore real places and practical details before you go with Llama Scout.';
$canonicalUrl = 'https://llamascout.com/';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/cell-coverage-update-banner.php';
?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<?php if ($hasMapMemberAccess): ?>
    <link
        rel="stylesheet"
        href="/css/map-tools.css"
    >
<?php endif; ?>

<section class="map-page">

    <header class="map-hero">
        <div class="map-shell map-hero-grid<?= $hasMapMemberAccess ? ' map-hero-grid-single' : '' ?>">

            <div>
                <p class="map-eyebrow">Welcome to Llama Scout</p>

                <h1>Know the place before you go.</h1>

                <p class="map-hero-lede">
                    Find campgrounds, places to park, and helpful roadside
                    stops. Check amenities, elevation, and land management,
                    or find Resources like water fills, dump stations, fuel,
                    and laundry.
                </p>

                <p class="map-hero-tip">
                    Search for a town, Place, or Resource, or move the map
                    to explore nearby.
                </p>
            </div>

        </div>
    </header>


    <section class="map-explorer">
        <div class="map-shell">

            <div class="map-toolbar">

                <label class="map-search">
                    <i aria-hidden="true"><?= llama_icon('search') ?></i>

                    <span class="visually-hidden">Search Places</span>

                    <input
                        id="map-search"
                        type="search"
                        placeholder="Search for a town, Place, or Resource..."
                        autocomplete="off"
                    >
                </label>


                <button
                    id="map-filter-toggle"
                    class="map-filter-toggle"
                    type="button"
                    aria-controls="map-filter-panel"
                    aria-expanded="false"
                >
                    <i aria-hidden="true"><?= llama_icon('adjustments-alt') ?></i>
                    Filters
                    <span id="map-filter-count" class="map-filter-count" hidden></span>
                </button>


                <button
                    id="map-clear"
                    class="map-clear"
                    type="button"
                    hidden
                >
                    Clear
                </button>

            </div>


            <div
                id="map-filter-panel"
                class="map-filter-panel"
                hidden
            >

                <label>
                    <span>State</span>
                    <select id="filter-state">
                        <option value="">All states</option>
                    </select>
                </label>

                <label>
                    <span>County</span>
                    <select id="filter-county">
                        <option value="">All counties</option>
                    </select>
                </label>

                <label>
                    <span>Nearest town</span>
                    <select id="filter-city">
                        <option value="">All towns</option>
                    </select>
                </label>

                <label>
                    <span>Place type</span>
                    <select id="filter-type">
                        <option value="">All Place types</option>
                    </select>
                </label>

                <label>
                    <span>Land manager</span>
                    <select id="filter-land-manager">
                        <option value="">All managers</option>
                    </select>
                </label>

                <label>
                    <span>Land type</span>
                    <select id="filter-land-type">
                        <option value="">All land types</option>
                    </select>
                </label>

                <label>
                    <span>Max elevation</span>
                    <select id="filter-elevation-max">
                        <option value="">Any elevation</option>
                        <option value="5000">Less than 5,000 ft</option>
                        <option value="7000">Less than 7,000 ft</option>
                        <option value="9000">Less than 9,000 ft</option>
                        <option value="11000">Less than 11,000 ft</option>
                    </select>
                </label>

                <label>
                    <span>Amenity</span>
                    <select id="filter-amenity">
                        <option value="">Any amenities</option>
                        <option value="toilets">Toilets</option>
                        <option value="potable_water">Potable water</option>
                        <option value="trash">Trash</option>
                        <option value="fire_ring">Fire ring</option>
                        <option value="picnic_table">Picnic table</option>
                        <option value="bear_box">Bear box</option>
                        <option value="showers">Showers</option>
                        <option value="electricity">Electricity</option>
                        <option value="dump_station">Dump station</option>
                    </select>
                </label>

            </div>


            <div class="map-layout">

                <div class="map-map-column">

                    <div
                        class="map-card"
                        data-map-member="<?= $hasMapMemberAccess ? '1' : '0' ?>"
                    >
                    <?php if ($hasMapMemberAccess): ?>
                    
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
                            class="llama-map"
                            aria-label="Map of Llama Scout Places"
                        ></div>

                        <div class="map-count-badge">
                            <strong id="map-status">Loading map...</strong>
                            <span id="map-location-precision">
                                Stored Place locations
                            </span>
                        </div>

                        <div class="map-resource-legend" aria-label="Resource marker icons">
                            <span class="map-resource-legend-item" title="A water connection or filling point; drinking-water safety is not confirmed"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-water_fill" aria-hidden="true"></i></span>Water fill</span>
                            <span class="map-resource-legend-item" title="RV waste disposal"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-dump_station" aria-hidden="true"></i></span>Dump station</span>
                            <span class="map-resource-legend-item" title="Propane fill"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-propane" aria-hidden="true"></i></span>Propane</span>
                            <span class="map-resource-legend-item" title="Firewood availability"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-firewood" aria-hidden="true"></i></span>Firewood</span>
                            <span class="map-resource-legend-item" title="Fuel station"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-fuel" aria-hidden="true"></i></span>Fuel</span>
                            <span class="map-resource-legend-item" title="Laundry facilities"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-laundry" aria-hidden="true"></i></span>Laundry</span>
                            <span class="map-resource-legend-item" title="Convenience store or groceries"><span class="map-resource-legend-symbol"><i class="map-resource-legend-icon is-service-groceries" aria-hidden="true"></i></span>Convenience store</span>
                        </div>
                    </div>

                </div>


                <aside class="map-results-column" aria-labelledby="map-results-heading">

                    <div class="map-results-heading">
                        <div>
                            <p class="map-eyebrow">Explore the map</p>
                            <h2 id="map-results-heading">Places &amp; helpful stops</h2>
                        </div>

                        <button
                            id="map-fit-results"
                            type="button"
                            class="map-fit-results"
                        >
                            <i aria-hidden="true"><?= llama_icon('arrows-maximize') ?></i>
                            Fit map
                        </button>
                    </div>

                    <section
                        id="place-results-section"
                        class="map-results-group"
                        aria-labelledby="map-places-heading"
                    >
                        <h3
                            id="map-places-heading"
                            class="map-results-subheading"
                        >
                            Places
                        </h3>

                        <div
                            id="place-results"
                            class="map-place-results"
                            aria-live="polite"
                        ></div>
                    </section>

                    <section
                        id="resource-results-section"
                        class="map-results-group"
                        aria-labelledby="map-resources-heading"
                        hidden
                    >
                        <h3
                            id="map-resources-heading"
                            class="map-results-subheading"
                        >
                            Resources
                        </h3>

                        <div
                            id="resource-results"
                            class="map-resource-results"
                            aria-live="polite"
                        ></div>
                    </section>

                    <div
                        id="map-empty"
                        class="map-empty"
                        hidden
                    >
                        <i aria-hidden="true"><?= llama_icon('map-pin') ?></i>
                        <h3>Nothing here just yet.</h3>
                        <p>Try another area, or clear your filters to explore more.</p>
                    </div>

                </aside>

            </div>

        </div>
    </section>

</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/map.js"></script>

<?php if ($hasMapMemberAccess): ?>
    <script src="/js/map-land-overlays.js"></script>
    <script src="/js/map-weather-overlays.js"></script>
    <script src="https://unpkg.com/h3-js@4.2.1"></script>
    <script src="/js/map-cell-overlays.js"></script>
    <script src="/js/map-tools.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
