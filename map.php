<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Explore the Map | Llama Scout';
$pageDescription = 'Browse Llama Scout Places by location, type, land manager, elevation, and public amenities.';
$canonicalUrl = 'https://llamascout.com/map.php';

require __DIR__ . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<section class="map-page">

    <header class="map-hero">
        <div class="map-shell map-hero-grid">

            <div>
                <p class="map-eyebrow">Explore Llama Scout</p>

                <h1>Find a Place that works for you.</h1>

                <p class="map-hero-lede">
                    Search published Places by general area, land management,
                    type, elevation, and public amenities. Map pins use
                    approximate public coordinates unless your account has
                    access to the complete Place report.
                </p>
            </div>

            <div class="map-privacy-note">
                <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                <div>
                    <strong>Public pins are approximate.</strong>
                    <span>
                        Exact coordinates remain part of the complete Place report.
                    </span>
                </div>
            </div>

        </div>
    </header>


    <section class="map-explorer">
        <div class="map-shell">

            <div class="map-toolbar">

                <label class="map-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>

                    <span class="visually-hidden">Search Places</span>

                    <input
                        id="map-search"
                        type="search"
                        placeholder="Search Places, towns, counties..."
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
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
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
                    <span>Minimum elevation</span>
                    <select id="filter-elevation-min">
                        <option value="">Any elevation</option>
                        <option value="5000">5,000+ ft</option>
                        <option value="7000">7,000+ ft</option>
                        <option value="9000">9,000+ ft</option>
                        <option value="11000">11,000+ ft</option>
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

                    <div class="map-card">
                        <div
                            id="llama-map"
                            class="llama-map"
                            aria-label="Map of Llama Scout Places"
                        ></div>

                        <div class="map-count-badge">
                            <strong id="map-status">Loading Places...</strong>
                            <span>Approximate public locations</span>
                        </div>
                    </div>

                </div>


                <aside class="map-results-column" aria-labelledby="places-heading">

                    <div class="map-results-heading">
                        <div>
                            <p class="map-eyebrow">Results</p>
                            <h2 id="places-heading">Places</h2>
                        </div>

                        <button
                            id="map-fit-results"
                            type="button"
                            class="map-fit-results"
                        >
                            <i class="fa-solid fa-expand" aria-hidden="true"></i>
                            Fit map
                        </button>
                    </div>

                    <div
                        id="place-results"
                        class="map-place-results"
                        aria-live="polite"
                    ></div>

                    <div
                        id="map-empty"
                        class="map-empty"
                        hidden
                    >
                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
                        <h3>No Places match those filters.</h3>
                        <p>Try clearing one or more filters.</p>
                    </div>

                </aside>

            </div>

        </div>
    </section>

</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/map.js"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
