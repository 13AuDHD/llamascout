(() => {
    'use strict';

    const mapElement = document.getElementById('llama-map');

    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    const controls = {
        search: document.getElementById('map-search'),
        toggle: document.getElementById('map-filter-toggle'),
        panel: document.getElementById('map-filter-panel'),
        count: document.getElementById('map-filter-count'),
        clear: document.getElementById('map-clear'),
        fit: document.getElementById('map-fit-results'),
        status: document.getElementById('map-status'),
        results: document.getElementById('place-results'),
        empty: document.getElementById('map-empty'),

        state: document.getElementById('filter-state'),
        county: document.getElementById('filter-county'),
        city: document.getElementById('filter-city'),
        type: document.getElementById('filter-type'),
        landManager: document.getElementById('filter-land-manager'),
        landType: document.getElementById('filter-land-type'),
        elevationMin: document.getElementById('filter-elevation-min'),
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

    const map = L.map(mapElement, {
        maxZoom: 11,
        zoomControl: true
    }).setView([37.3, -107.4], 7);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 11,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }
    ).addTo(map);


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


    const activeFilterCount = () => {
        let count = 0;

        [
            controls.state,
            controls.county,
            controls.city,
            controls.type,
            controls.landManager,
            controls.landType,
            controls.elevationMin,
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
        const minElevation = Number(controls.elevationMin?.value || 0);

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
            minElevation > 0 &&
            Number(place.elevation_feet || 0) < minElevation
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
            .map((place) => [
                Number(place.public_latitude),
                Number(place.public_longitude)
            ])
            .filter(([lat, lng]) =>
                Number.isFinite(lat) &&
                Number.isFinite(lng)
            );

        if (bounds.length > 1) {
            map.fitBounds(bounds, {
                padding: [42, 42],
                maxZoom: 9
            });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 9);
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

        controls.results.innerHTML = '';

        visiblePlaces.forEach((place) => {
            const lat = Number(place.public_latitude);
            const lng = Number(place.public_longitude);
            let marker = null;

            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                marker = L.marker([lat, lng]);
                marker.bindPopup(popupHtml(place));
                marker.addTo(map);
                markers.push(marker);
            }

            controls.results.appendChild(
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
        controls.search.value = '';

        [
            controls.state,
            controls.county,
            controls.city,
            controls.type,
            controls.landManager,
            controls.landType,
            controls.elevationMin,
            controls.amenity
        ].forEach((element) => {
            if (element) {
                element.value = '';
            }
        });

        render(true);
    };


    const toggleFilters = () => {
        const opening = controls.panel.hidden;

        controls.panel.hidden = !opening;
        controls.toggle.setAttribute(
            'aria-expanded',
            opening ? 'true' : 'false'
        );
    };


    async function loadPlaces() {
        controls.status.textContent = 'Loading Places...';

        try {
            const response = await fetch(
                '/api/places.php',
                {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                throw new Error('Unable to load Places.');
            }

            const data = await response.json();

            if (!data.ok || !Array.isArray(data.places)) {
                throw new Error('Unexpected Places response.');
            }

            places = data.places;

            populateFilters();
            render(true);

        } catch (error) {
            console.error('Llama Scout map:', error);

            controls.status.textContent =
                'Places could not be loaded.';

            controls.empty.hidden = false;
            controls.empty.querySelector('h3').textContent =
                'The map could not load Places.';
            controls.empty.querySelector('p').textContent =
                'Try reloading the page.';
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
        controls.elevationMin,
        controls.amenity
    ].forEach((element) => {
        element?.addEventListener('change', () => render(true));
    });

    loadPlaces();
})();
