(() => {
    'use strict';

    const mapCard = document.querySelector('.place-map-card');
    const handle = window.LlamaScoutMap;
    const mappedApi = mapCard?.dataset.placeMapFeaturesApi || '';

    if (!handle?.map || mapCard?.dataset.mapMember !== '1' || !mappedApi
        || typeof L === 'undefined') {
        return;
    }

    const slug = new URL(mappedApi, window.location.origin).searchParams.get('slug');
    if (!slug) {
        return;
    }

    const map = handle.map;

    const loadPoints = async () => {
        try {
            const response = await fetch(
                `/api/place-campsite-points.php?slug=${encodeURIComponent(slug)}`,
                { credentials: 'same-origin', headers: { Accept: 'application/json' } }
            );

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (payload?.ok !== true || !Array.isArray(payload.sites)
                || payload.sites.length === 0) {
                return;
            }

            const layer = L.featureGroup();

            for (const site of payload.sites) {
                const id = Number(site.id);
                const latitude = Number(site.latitude);
                const longitude = Number(site.longitude);
                if (!Number.isSafeInteger(id) || id < 1
                    || !Number.isFinite(latitude) || !Number.isFinite(longitude)
                    || latitude < -90 || latitude > 90
                    || longitude < -180 || longitude > 180
                    || (latitude === 0 && longitude === 0)) {
                    continue;
                }

                const rawName = String(site.name || id).trim();
                const name = /^site\b/i.test(rawName) ? rawName : `Site ${rawName}`;
                const point = L.circleMarker([latitude, longitude], {
                    radius: 7,
                    className: 'place-map-feature place-map-feature--camping-site',
                    keyboard: true,
                });

                const popup = document.createElement('div');
                popup.className = 'place-map-feature-popup';

                const heading = document.createElement('strong');
                heading.textContent = name;

                const detail = document.createElement('span');
                detail.textContent = 'Location from the imported campsite record.';

                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'place-map-edit-areas';
                button.textContent = 'View Site details';
                button.addEventListener('click', () => {
                    const target = document.querySelector(
                        `[data-campsite-id="${CSS.escape(String(id))}"]`
                    );
                    if (target) {
                        // The Site browser owns selection and its Scout Report.
                        // A non-bubbling click avoids the separate map-focus handler
                        // that still expects manually drawn Site geometries.
                        target.dispatchEvent(new MouseEvent('click', { bubbles: false }));
                        return;
                    }
                    const destination = new URL(window.location.href);
                    destination.searchParams.set('site', String(id));
                    window.location.assign(destination.toString());
                });

                popup.append(heading, detail, button);
                point.bindPopup(popup);
                layer.addLayer(point);
            }

            if (layer.getLayers().length === 0) {
                return;
            }

            layer.addTo(map);
            // Fit the imported points only when they form an extent. Don't
            // create or modify map_feature_id associations in the database.
            const bounds = layer.getBounds();
            if (bounds.isValid() && layer.getLayers().length > 1) {
                map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
            }
        } catch (error) {
            console.error('Llama Scout campsite coordinates could not be displayed:', error);
        }
    };

    void loadPoints();
})();
