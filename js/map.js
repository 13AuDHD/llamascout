(() => {
    'use strict';

    const mapElement = document.getElementById('llama-map');

    if (!mapElement || typeof L === 'undefined') {
        return;
    }

    const searchInput = document.getElementById('map-search');
    const stateSelect = document.getElementById('map-state');
    const resultsElement = document.getElementById('place-results');
    const statusElement = document.getElementById('map-status');

    let places = [];
    let markers = [];

    const map = L.map('llama-map', {
        maxZoom: 11
    }).setView(
        [37.3, -107.4],
        7
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 11,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }
    ).addTo(map);


    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }


    function clearMarkers() {
        markers.forEach((marker) => {
            map.removeLayer(marker);
        });

        markers = [];
    }


    function getFilteredPlaces() {
        const search =
            (searchInput?.value ?? '')
                .trim()
                .toLowerCase();

        const state =
            stateSelect?.value ?? '';

        return places.filter((place) => {
            if (state && place.state !== state) {
                return false;
            }

            if (!search) {
                return true;
            }

            const searchable = [
                place.name,
                place.city,
                place.county,
                place.state,
                place.region,
                place.land_manager,
                place.public_location_label
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return searchable.includes(search);
        });
    }


    function renderPlaces() {
        const filtered = getFilteredPlaces();

        clearMarkers();

        resultsElement.innerHTML = '';

        const bounds = [];

        filtered.forEach((place) => {
            const latitude =
                Number(place.public_latitude);

            const longitude =
                Number(place.public_longitude);

            const url =
                '/place.php?slug=' +
                encodeURIComponent(place.slug);

            if (
                Number.isFinite(latitude) &&
                Number.isFinite(longitude)
            ) {
                const marker = L.marker([
                    latitude,
                    longitude
                ]);

                marker.bindPopup(`
                    <strong>${escapeHtml(place.name)}</strong><br>
                    ${escapeHtml(
                        place.public_location_label ||
                        place.city ||
                        place.state ||
                        ''
                    )}<br>
                    <a href="${url}">View place</a>
                `);

                marker.addTo(map);

                markers.push(marker);
                bounds.push([latitude, longitude]);
            }

            const card =
                document.createElement('article');

            card.className = 'place-card';

            card.innerHTML = `
                ${
                    place.featured_image
                        ? `
                            <img
                                src="/${escapeHtml(place.featured_image)}"
                                alt="${escapeHtml(
                                    place.featured_image_alt ||
                                    place.name
                                )}"
                                loading="lazy"
                            >
                        `
                        : ''
                }

                <div class="place-card-content">

                    <p class="place-card-type">
                        ${escapeHtml(
                            (place.type || '')
                                .replaceAll('_', ' ')
                        )}
                    </p>

                    <h3>
                        <a href="${url}">
                            ${escapeHtml(place.name)}
                        </a>
                    </h3>

                    <p>
                        ${escapeHtml(
                            place.public_location_label ||
                            [
                                place.city,
                                place.state
                            ]
                                .filter(Boolean)
                                .join(', ')
                        )}
                    </p>

                    ${
                        place.public_summary
                            ? `
                                <p>
                                    ${escapeHtml(
                                        place.public_summary
                                    )}
                                </p>
                            `
                            : ''
                    }

                </div>
            `;

            resultsElement.appendChild(card);
        });

        statusElement.textContent =
            `${filtered.length} place${filtered.length === 1 ? '' : 's'} shown`;

        if (bounds.length > 1) {
            map.fitBounds(bounds, {
                padding: [40, 40],
                maxZoom: 9
            });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 9);
        }
    }


    function populateStates() {
        const states = [
            ...new Set(
                places
                    .map((place) => place.state)
                    .filter(Boolean)
            )
        ].sort();

        states.forEach((state) => {
            const option =
                document.createElement('option');

            option.value = state;
            option.textContent = state;

            stateSelect.appendChild(option);
        });
    }


    async function loadPlaces() {
        statusElement.textContent =
            'Loading places…';

        try {
            const response = await fetch(
                '/api/places.php',
                {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                throw new Error(
                    'Unable to load places.'
                );
            }

            const data = await response.json();

            if (
                !data.ok ||
                !Array.isArray(data.places)
            ) {
                throw new Error(
                    'Unexpected places response.'
                );
            }

            places = data.places;

            populateStates();
            renderPlaces();

        } catch (error) {
            console.error(error);

            statusElement.textContent =
                'Places could not be loaded.';
        }
    }


    searchInput?.addEventListener(
        'input',
        renderPlaces
    );

    stateSelect?.addEventListener(
        'change',
        renderPlaces
    );


    loadPlaces();
})();
