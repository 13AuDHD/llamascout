(() => {
    'use strict';

    const element = document.getElementById('public-home-map');

    if (!element || typeof L === 'undefined') {
        return;
    }

    const status = document.getElementById('public-home-map-status');

    const map = L.map(element, {
        zoomControl: true,
        scrollWheelZoom: false,
        maxZoom: 10
    }).setView([37.3, -107.4], 7);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 10,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }
    ).addTo(map);

    const escapeHtml = (value) => {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };

    const load = async () => {
        try {
            const response = await fetch(
                '/api/places.php',
                {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                throw new Error('Unable to load public places.');
            }

            const payload = await response.json();

            if (!payload?.ok || !Array.isArray(payload.places)) {
                throw new Error('Unexpected places response.');
            }

            const bounds = [];
            let count = 0;

            payload.places.forEach((place) => {
                const latitude = Number(place.public_latitude);
                const longitude = Number(place.public_longitude);

                if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                    return;
                }

                const url =
                    '/place.php?slug=' +
                    encodeURIComponent(String(place.slug || ''));

                const marker = L.marker([latitude, longitude]);

                marker.bindPopup(`
                    <strong>${escapeHtml(place.name)}</strong><br>
                    ${escapeHtml(
                        place.public_location_label ||
                        [place.city, place.state]
                            .filter(Boolean)
                            .join(', ')
                    )}<br>
                    <a href="${url}">View Place</a>
                `);

                marker.addTo(map);
                bounds.push([latitude, longitude]);
                count++;
            });

            if (bounds.length > 1) {
                map.fitBounds(bounds, {
                    padding: [34, 34],
                    maxZoom: 8
                });
            } else if (bounds.length === 1) {
                map.setView(bounds[0], 8);
            }

            if (status) {
                status.textContent =
                    `${count} public Place${count === 1 ? '' : 's'}`;
            }
        } catch (error) {
            console.error('Llama Scout homepage map:', error);

            if (status) {
                status.textContent = 'Open the full map to explore Places';
            }
        }
    };

    load();
})();
