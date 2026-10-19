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
    const siteMarkers = new Map();

    const focusSite = (id, scroll = false) => {
        const marker = siteMarkers.get(Number(id));
        if (!marker) {
            return false;
        }

        if (scroll) {
            document.getElementById('place-map-heading')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }

        map.setView(marker.getLatLng(), Math.min(18, map.getMaxZoom()), {
            animate: false,
        });
        marker.openPopup();
        return true;
    };

    /*
     * Site selection is owned by the existing Site browser. It updates its
     * selected card and Scout Report before the click reaches this section.
     * Imported GPS points have no map_feature_id, so do not let the older
     * polygon-only map handler replace their position with the Place center.
     * Only an explicit View on map click scrolls the page.
     */
    const stayOptions = document.querySelector('[data-stay-options]');
    stayOptions?.addEventListener('click', (event) => {
        const row = event.target instanceof Element
            ? event.target.closest('[data-campsite-id]') : null;
        if (!row || !stayOptions.contains(row)) {
            return;
        }

        const id = Number(row.dataset.campsiteId);
        if (!siteMarkers.has(id)) {
            return;
        }

        // The site button's own selection handler has already run.
        // Stop the legacy Area/map-feature listener for imported GPS Sites.
        event.stopImmediatePropagation();

        const context = document.querySelector('[data-selected-campsite-context]');
        if (context) {
            const label = context.querySelector('span');
            const selectedName = context.querySelector('strong');
            if (label) {
                label.textContent = 'Viewing Site: ';
            }
            if (selectedName) {
                selectedName.textContent = row.querySelector(
                    '.place-campsite-row-name'
                )?.textContent.trim() || `Site ${id}`;
            }
        }

        // Change the marker in place. Never scroll to the map on Site clicks.
        focusSite(id);
    });

    /*
     * "View on map" should focus the currently selected campsite, not the
     * campground center. Coordinates and marker identity come from the same
     * protected campsite-points endpoint used by the displayed map overlay.
     */
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const link = target.closest('a[href="#place-map-heading"]');
        if (!link) {
            return;
        }

        const headerLink = link.matches('.place-overnight-options-map-link');
        const context = link.closest('[data-selected-campsite-context]');
        if (!headerLink && !context) {
            return;
        }

        // The context can also refer to a mapped Area. Keep its existing
        // map-focus behavior instead of substituting an unrelated Site.
        if (context && !context.textContent.includes('Viewing Site:')) {
            return;
        }

        const selected = document.querySelector(
            '[data-stay-options] [data-campsite-id].is-selected'
        );
        if (!siteMarkers.has(Number(selected?.dataset.campsiteId))) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        focusSite(selected.dataset.campsiteId, true);
    }, true);

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
                    pane: 'markerPane',
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
                siteMarkers.set(id, point);
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
