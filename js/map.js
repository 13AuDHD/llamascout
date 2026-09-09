(() => {
    'use strict';

    const mapElement = document.getElementById('llama-map');

    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    const mapCard = mapElement.closest('.map-card');
    const initialMemberAccess = mapCard?.dataset.mapMember === '1';

    const controls = {
        search: document.getElementById('map-search'),
        toggle: document.getElementById('map-filter-toggle'),
        panel: document.getElementById('map-filter-panel'),
        count: document.getElementById('map-filter-count'),
        clear: document.getElementById('map-clear'),
        fit: document.getElementById('map-fit-results'),
        status: document.getElementById('map-status'),
        precision: document.getElementById('map-location-precision'),
        results: document.getElementById('place-results'),
        empty: document.getElementById('map-empty'),
        layerControl: document.getElementById('map-layer-control'),

        state: document.getElementById('filter-state'),
        county: document.getElementById('filter-county'),
        city: document.getElementById('filter-city'),
        type: document.getElementById('filter-type'),
        landManager: document.getElementById('filter-land-manager'),
        landType: document.getElementById('filter-land-type'),
        elevationMax: document.getElementById('filter-elevation-max'),
        amenity: document.getElementById('filter-amenity')
    };

    const amenityLabels = {
        toilets: 'Toilets',
        potable_water: 'Potable water',
        trash: 'Trash',
        fire_ring: 'Fire ring',
        picnic_table: 'Picnic table',
        bear_box: 'Bear box',
        showers: 'Showers',
        electricity: 'Electricity',
        dump_station: 'Dump station'
    };

    let places = [];
    let visiblePlaces = [];
    let markers = [];
    let memberMapAccess = initialMemberAccess;
    let selectedLayer = 'auto';
    let activeTileLayer = null;

    const PUBLIC_MAX_ZOOM = 11;
    const MEMBER_MAX_ZOOM = 20;

    const map = L.map(mapElement, {
        maxZoom: initialMemberAccess ? MEMBER_MAX_ZOOM : PUBLIC_MAX_ZOOM,
        zoomControl: true
    }).setView([37.3, -107.4], 7);


    const tileSources = {
        public: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxNativeZoom: 19,
                maxZoom: PUBLIC_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }
        },

        light: {
            url: '',
            options: {
                maxNativeZoom: 20,
                maxZoom: MEMBER_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://www.geoapify.com/">Geoapify</a>'
            }
        },

        street: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: {
                maxNativeZoom: 19,
                maxZoom: MEMBER_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }
        },

        terrain: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
            options: {
                maxNativeZoom: 19,
                maxZoom: MEMBER_MAX_ZOOM,
                attribution:
                    'Tiles &copy; Esri and contributors'
            }
        },

        topo: {
            url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
            options: {
                maxNativeZoom: 17,
                maxZoom: MEMBER_MAX_ZOOM,
                attribution:
                    'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, SRTM | Map style &copy; <a href="https://opentopomap.org">OpenTopoMap</a>'
            }
        },

        dark: {
            url: '',
            options: {
                maxNativeZoom: 20,
                maxZoom: MEMBER_MAX_ZOOM,
                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://www.geoapify.com/">Geoapify</a>'
            }
        },

        satellite: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            options: {
                maxNativeZoom: 19,
                maxZoom: MEMBER_MAX_ZOOM,
                attribution:
                    'Tiles &copy; Esri and imagery contributors'
            }
        }
    };


    const getStoredTheme = () => {
        try {
            return localStorage.getItem('llama-theme') || 'system';
        } catch (error) {
            return 'system';
        }
    };


    const systemPrefersDark = () =>
        window.matchMedia?.('(prefers-color-scheme: dark)').matches === true;


    const resolvedTheme = () => {
        const stored = getStoredTheme();

        if (stored === 'dark') {
            return 'dark';
        }

        if (stored === 'light') {
            return 'light';
        }

        const documentTheme = document.documentElement.dataset.theme;

        if (documentTheme === 'dark' || documentTheme === 'light') {
            return documentTheme;
        }

        return systemPrefersDark() ? 'dark' : 'light';
    };


    const layerKeyForSelection = (selection) => {
        if (!memberMapAccess) {
            return 'public';
        }

        if (selection === 'auto') {
            const automatic =
                resolvedTheme() === 'dark' ? 'dark' : 'light';

            return tileSources[automatic]?.url
                ? automatic
                : 'street';
        }

        if (
            (selection === 'light' || selection === 'dark') &&
            !tileSources[selection]?.url
        ) {
            return 'street';
        }

        return tileSources[selection] ? selection : 'street';
    };


    const setActiveLayerButton = () => {
        controls.layerControl
            ?.querySelectorAll('[data-map-layer]')
            .forEach((button) => {
                const active = button.dataset.mapLayer === selectedLayer;

                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
    };


    const applyTileLayer = () => {
        const key = layerKeyForSelection(selectedLayer);
        const source = tileSources[key];

        if (!source) {
            return;
        }

        if (activeTileLayer) {
            map.removeLayer(activeTileLayer);
        }

        activeTileLayer = L.tileLayer(source.url, source.options);
        activeTileLayer.addTo(map);
        setActiveLayerButton();
    };


    const syncMapAccess = (hasAccess) => {
        memberMapAccess = hasAccess === true;

        map.setMaxZoom(
            memberMapAccess ? MEMBER_MAX_ZOOM : PUBLIC_MAX_ZOOM
        );

        if (!memberMapAccess && map.getZoom() > PUBLIC_MAX_ZOOM) {
            map.setZoom(PUBLIC_MAX_ZOOM);
        }

        if (controls.precision) {
            controls.precision.textContent = memberMapAccess
                ? 'Exact Place locations'
                : 'Approximate public locations';
        }

        if (controls.layerControl) {
            controls.layerControl.hidden = !memberMapAccess;
        }

        if (!memberMapAccess) {
            selectedLayer = 'auto';
        }

        applyTileLayer();
    };


    applyTileLayer();


    controls.layerControl
        ?.querySelectorAll('[data-map-layer]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                if (!memberMapAccess) {
                    return;
                }

                selectedLayer = button.dataset.mapLayer || 'auto';
                applyTileLayer();
            });
        });


    const themeObserver = new MutationObserver(() => {
        if (memberMapAccess && selectedLayer === 'auto') {
            applyTileLayer();
        }
    });

    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    const colorSchemeQuery =
        window.matchMedia?.('(prefers-color-scheme: dark)');

    const handleSystemThemeChange = () => {
        if (
            memberMapAccess &&
            selectedLayer === 'auto' &&
            getStoredTheme() === 'system'
        ) {
            applyTileLayer();
        }
    };

    if (colorSchemeQuery?.addEventListener) {
        colorSchemeQuery.addEventListener('change', handleSystemThemeChange);
    } else if (colorSchemeQuery?.addListener) {
        colorSchemeQuery.addListener(handleSystemThemeChange);
    }


    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };


    const normalize = (value) =>
        String(value ?? '').trim().toLowerCase();


    const formatLabel = (value) =>
        String(value ?? '')
            .replaceAll('_', ' ')
            .replaceAll('-', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());


    const imageUrl = (value) => {
        const src = String(value ?? '').trim();

        if (!src) {
            return '';
        }

        if (/^https?:\/\//i.test(src)) {
            return src;
        }

        return '/' + src.replace(/^\/+/, '');
    };


    const placeUrl = (place) =>
        '/place.php?slug=' +
        encodeURIComponent(String(place.slug || ''));


    const locationLabel = (place) =>
        String(place.public_location_label || '').trim() ||
        [place.city, place.state].filter(Boolean).join(', ');


    const placeCoordinates = (place) => {
        const exactLat = Number(place.latitude);
        const exactLng = Number(place.longitude);

        if (
            memberMapAccess &&
            Number.isFinite(exactLat) &&
            Number.isFinite(exactLng)
        ) {
            return [exactLat, exactLng];
        }

        const publicLat = Number(place.public_latitude);
        const publicLng = Number(place.public_longitude);

        if (
            Number.isFinite(publicLat) &&
            Number.isFinite(publicLng)
        ) {
            return [publicLat, publicLng];
        }

        return null;
    };


    const activeFilterCount = () => {
        let count = 0;

        [
            controls.state,
            controls.county,
            controls.city,
            controls.type,
            controls.landManager,
            controls.landType,
            controls.elevationMax,
            controls.amenity
        ].forEach((element) => {
            if (element?.value) {
                count++;
            }
        });

        if (controls.search?.value.trim()) {
            count++;
        }

        return count;
    };


    const updateFilterCount = () => {
        const count = activeFilterCount();

        if (controls.count) {
            controls.count.textContent = String(count);
            controls.count.hidden = count === 0;
        }

        if (controls.clear) {
            controls.clear.hidden = count === 0;
        }
    };


    const uniqueSorted = (field) =>
        [...new Set(
            places
                .map((place) => String(place[field] || '').trim())
                .filter(Boolean)
        )].sort((a, b) => a.localeCompare(b));


    const populateSelect = (element, values) => {
        if (!element) {
            return;
        }

        const current = element.value;

        while (element.options.length > 1) {
            element.remove(1);
        }

        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = formatLabel(value);
            element.appendChild(option);
        });

        if ([...element.options].some((option) => option.value === current)) {
            element.value = current;
        }
    };


    const populateFilters = () => {
        populateSelect(controls.state, uniqueSorted('state'));
        populateSelect(controls.county, uniqueSorted('county'));
        populateSelect(controls.city, uniqueSorted('city'));
        populateSelect(controls.type, uniqueSorted('type'));
        populateSelect(controls.landManager, uniqueSorted('land_manager'));
        populateSelect(controls.landType, uniqueSorted('land_type'));
    };


    const matches = (place) => {
        const search = normalize(controls.search?.value);
        const selectedAmenity = controls.amenity?.value || '';
        const maxElevation = Number(controls.elevationMax?.value || 0);

        const exactFilters = [
            ['state', controls.state?.value],
            ['county', controls.county?.value],
            ['city', controls.city?.value],
            ['type', controls.type?.value],
            ['land_manager', controls.landManager?.value],
            ['land_type', controls.landType?.value]
        ];

        for (const [field, selected] of exactFilters) {
            if (selected && String(place[field] || '') !== selected) {
                return false;
            }
        }

        if (
            maxElevation > 0 &&
            Number(place.elevation_feet || 0) > maxElevation
        ) {
            return false;
        }

        if (
            selectedAmenity &&
            Number(place.amenities?.[selectedAmenity] || 0) !== 1
        ) {
            return false;
        }

        if (!search) {
            return true;
        }

        const searchable = [
            place.name,
            place.type,
            place.city,
            place.county,
            place.state,
            place.region,
            place.land_manager,
            place.land_type,
            place.public_location_label
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchable.includes(search);
    };


    const clearMarkers = () => {
        markers.forEach((marker) => {
            map.removeLayer(marker);
        });

        markers = [];
    };


    const fitVisiblePlaces = () => {
        const bounds = visiblePlaces
            .map(placeCoordinates)
            .filter(Boolean);

        const fitMaxZoom = memberMapAccess ? 16 : 9;

        if (bounds.length > 1) {
            map.fitBounds(bounds, {
                padding: [42, 42],
                maxZoom: fitMaxZoom
            });
        } else if (bounds.length === 1) {
            map.setView(
                bounds[0],
                memberMapAccess ? 16 : 9
            );
        }
    };


    const popupHtml = (place) => {
        const location = locationLabel(place);
        const image = imageUrl(place.featured_image);

        return `
            <article class="map-popup">
                ${
                    image
                        ? `<img src="${escapeHtml(image)}"
                             alt="${escapeHtml(place.featured_image_alt || place.name)}">`
                        : ''
                }

                <div>
                    <span class="map-popup-type">
                        ${escapeHtml(formatLabel(place.type))}
                    </span>

                    <strong>${escapeHtml(place.name)}</strong>

                    ${
                        location
                            ? `<span>${escapeHtml(location)}</span>`
                            : ''
                    }

                    ${
                        memberMapAccess
                            ? '<span class="map-popup-exact"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i> Exact location</span>'
                            : ''
                    }

                    <a href="${placeUrl(place)}">View Place</a>
                </div>
            </article>
        `;
    };


    const renderAmenities = (place) => {
        const items = Object.entries(amenityLabels)
            .filter(([key]) => Number(place.amenities?.[key] || 0) === 1)
            .slice(0, 3);

        if (!items.length) {
            return '';
        }

        return `
            <div class="map-place-amenities">
                ${items
                    .map(([, label]) => `<span>${escapeHtml(label)}</span>`)
                    .join('')}
            </div>
        `;
    };


    const renderCard = (place, marker) => {
        const article = document.createElement('article');
        const image = imageUrl(place.featured_image);
        const location = locationLabel(place);
        const url = placeUrl(place);

        article.className = 'map-place-card';

        article.innerHTML = `
            <a class="map-place-image" href="${url}">
                ${
                    image
                        ? `
                            <img
                                src="${escapeHtml(image)}"
                                alt="${escapeHtml(place.featured_image_alt || place.name)}"
                                loading="lazy"
                            >
                        `
                        : `
                            <span class="map-place-image-placeholder">
                                <i class="fa-solid fa-mountain-sun" aria-hidden="true"></i>
                            </span>
                        `
                }
            </a>

            <div class="map-place-body">
                <div class="map-place-topline">
                    <span>${escapeHtml(formatLabel(place.type))}</span>

                    ${
                        place.status === 'featured'
                            ? '<strong>Featured</strong>'
                            : ''
                    }
                </div>

                <h3>
                    <a href="${url}">
                        ${escapeHtml(place.name)}
                    </a>
                </h3>

                ${
                    location
                        ? `
                            <p class="map-place-location">
                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                ${escapeHtml(location)}
                            </p>
                        `
                        : ''
                }

                <div class="map-place-meta">
                    ${
                        place.elevation_feet
                            ? `<span>${Number(place.elevation_feet).toLocaleString()} ft</span>`
                            : ''
                    }

                    ${
                        place.land_manager
                            ? `<span>${escapeHtml(place.land_manager)}</span>`
                            : ''
                    }
                </div>

                ${renderAmenities(place)}
            </div>
        `;

        if (marker) {
            article.addEventListener('mouseenter', () => {
                marker.openPopup();
            });

            article.addEventListener('focusin', () => {
                marker.openPopup();
            });
        }

        return article;
    };


    const render = (fitMap = false) => {
        visiblePlaces = places.filter(matches);

        clearMarkers();

        if (controls.results) {
            controls.results.innerHTML = '';
        }

        visiblePlaces.forEach((place) => {
            const coordinates = placeCoordinates(place);
            let marker = null;

            if (coordinates) {
                marker = L.marker(coordinates);
                marker.bindPopup(popupHtml(place));
                marker.addTo(map);
                markers.push(marker);
            }

            controls.results?.appendChild(
                renderCard(place, marker)
            );
        });

        if (controls.status) {
            controls.status.textContent =
                `${visiblePlaces.length} Place${visiblePlaces.length === 1 ? '' : 's'} shown`;
        }

        if (controls.empty) {
            controls.empty.hidden = visiblePlaces.length !== 0;
        }

        updateFilterCount();

        if (fitMap) {
            fitVisiblePlaces();
        }
    };


    const clearFilters = () => {
        if (controls.search) {
            controls.search.value = '';
        }

        [
            controls.state,
            controls.county,
            controls.city,
            controls.type,
            controls.landManager,
            controls.landType,
            controls.elevationMax,
            controls.amenity
        ].forEach((element) => {
            if (element) {
                element.value = '';
            }
        });

        render(true);
    };


    const toggleFilters = () => {
        if (!controls.panel || !controls.toggle) {
            return;
        }

        const opening = controls.panel.hidden;

        controls.panel.hidden = !opening;
        controls.toggle.setAttribute(
            'aria-expanded',
            opening ? 'true' : 'false'
        );
    };


    async function loadPlaces() {
        if (controls.status) {
            controls.status.textContent = 'Loading Places...';
        }

        try {
            const response = await fetch(
                '/api/places.php',
                {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('Unable to load Places.');
            }

            const data = await response.json();

            if (!data.ok || !Array.isArray(data.places)) {
                throw new Error('Unexpected Places response.');
            }

            syncMapAccess(data.member_map_access === true);

            if (
                memberMapAccess &&
                data.member_tiles?.geoapify_available === true
            ) {
                tileSources.light.url =
                    String(data.member_tiles.light || '');

                tileSources.dark.url =
                    String(data.member_tiles.dark || '');

                if (selectedLayer === 'auto') {
                    applyTileLayer();
                }
            }

            places = data.places;

            populateFilters();
            render(true);

        } catch (error) {
            console.error('Llama Scout map:', error);

            if (controls.status) {
                controls.status.textContent =
                    'Places could not be loaded.';
            }

            if (controls.empty) {
                controls.empty.hidden = false;

                const heading = controls.empty.querySelector('h3');
                const paragraph = controls.empty.querySelector('p');

                if (heading) {
                    heading.textContent =
                        'The map could not load Places.';
                }

                if (paragraph) {
                    paragraph.textContent =
                        'Try reloading the page.';
                }
            }
        }
    }


    controls.toggle?.addEventListener('click', toggleFilters);
    controls.clear?.addEventListener('click', clearFilters);
    controls.fit?.addEventListener('click', fitVisiblePlaces);

    controls.search?.addEventListener('input', () => render(false));

    [
        controls.state,
        controls.county,
        controls.city,
        controls.type,
        controls.landManager,
        controls.landType,
        controls.elevationMax,
        controls.amenity
    ].forEach((element) => {
        element?.addEventListener('change', () => render(true));
    });

    loadPlaces();
})();
