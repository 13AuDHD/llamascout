<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Explore the Map | Llama Scout';

require __DIR__ . '/partials/header.php';
?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<section class="map-intro">
    <p class="eyebrow">Explore Llama Scout</p>

    <h1>Find a place that works for you.</h1>

    <p>
        Browse campsites, pullouts, scenic stops, and other places
        with access and sensory information before you arrive.
    </p>
</section>

<section class="map-toolbar" aria-label="Map controls">

    <label class="map-search" for="map-search">
        <span class="visually-hidden">Search places</span>

        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>

        <input
            type="search"
            id="map-search"
            placeholder="Search places, towns, counties..."
        >
    </label>

    <label class="map-state-filter" for="map-state">
        <span class="visually-hidden">Filter by state</span>

        <select id="map-state">
            <option value="">All states</option>
        </select>
    </label>

</section>

<div
    id="llama-map"
    class="llama-map"
    aria-label="Map of Llama Scout places"
></div>

<p
    id="map-status"
    class="map-status"
    aria-live="polite"
></p>

<section aria-labelledby="places-heading">

    <h2 id="places-heading">Places</h2>

    <div
        id="place-results"
        class="place-results"
    ></div>

</section>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

<script src="/js/map.js?v=1"></script>

<?php require __DIR__ . '/partials/footer.php'; ?>
