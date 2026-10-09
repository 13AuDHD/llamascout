(() => {
    'use strict';

    const section = document.querySelector('[data-stay-options]');
    if (!section) return;
    const mapElement = document.getElementById('llama-map');
    const mapCard = mapElement?.closest('.map-card');
    const slug = document.querySelector('[data-place-campsites-mount]')?.dataset.placeSlug || '';
    let canonicalDataPromise = null;
    let mappedDataPromise = null;
    let highlight = null;
    let lastSelection = null;

    const getJson = async (url) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {Accept: 'application/json'}
        });
        if (!response.ok) throw new Error('Unable to retrieve mapped campsite information.');
        return response.json();
    };

    const canonicalSites = () => {
        if (!slug) return Promise.resolve([]);
        if (!canonicalDataPromise) {
            canonicalDataPromise = getJson(`/api/place-campsites.php?slug=${encodeURIComponent(slug)}`)
                .then(data => data.ok && Array.isArray(data.sites) ? data.sites : [])
                .catch(() => []);
        }
        return canonicalDataPromise;
    };

    const mappedFeatures = () => {
        const url = String(mapCard?.dataset.placeMapFeaturesApi || '');
        if (!url) return Promise.resolve([]);
        if (!mappedDataPromise) {
            mappedDataPromise = getJson(url)
                .then(data => data.ok && Array.isArray(data.features) ? data.features : [])
                .catch(() => []);
        }
        return mappedDataPromise;
    };

    const reportContext = () => {
        const header = document.querySelector('.scout-report-header');
        if (!header) return null;
        let element = header.querySelector('[data-selected-campsite-context]');
        if (!element) {
            element = document.createElement('p');
            element.className = 'place-selected-campsite-context';
            element.dataset.selectedCampsiteContext = '';
            const container = header.querySelector('div') || header;
            container.appendChild(element);
        }
        return element;
    };

    const showContext = (name, kind, selection) => {
        const context = reportContext();
        if (!context) return;
        context.replaceChildren();
        const label = document.createElement('span');
        label.textContent = kind === 'parking' ? 'Selected parking area: ' : 'Viewing campsite: ';
        const strong = document.createElement('strong');
        strong.textContent = name;
        context.append(label, strong);
        if (mapElement) {
            const view = document.createElement('a');
            view.href = '#place-map-heading';
            view.textContent = 'View on map';
            view.addEventListener('click', () => focusSelection(selection));
            context.appendChild(view);
        }
    };

    const buildPopup = (name, lines, mapped) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'place-map-feature-popup';
        const strong = document.createElement('strong');
        strong.textContent = name;
        wrapper.append(strong);
        for (const line of lines) {
            if (!line) continue;
            const span = document.createElement('span');
            span.textContent = line;
            wrapper.append(span);
        }
        if (!mapped) {
            const caution = document.createElement('span');
            caution.textContent = 'Individual campsite location not mapped. Map shows the campground, not this site.';
            wrapper.append(caution);
        }
        return wrapper;
    };

    const focusSelection = async (selection) => {
        const handle = window.LlamaScoutMap;
        if (!handle?.map || typeof L === 'undefined') return;
        const map = handle.map;
        const row = selection.row;
        let featureId = selection.featureId || 0;
        const lines = selection.lines || [];
        if (selection.kind === 'canonical') {
            const sites = await canonicalSites();
            const site = sites.find(item => String(item.feature_id) === String(selection.id));
            featureId = Number(site?.map_feature_id) || 0;
            if (site && Array.isArray(site.summary)) lines.push(...site.summary.slice(0, 3));
        }
        if (highlight) {
            map.removeLayer(highlight);
            highlight = null;
        }
        if (featureId) {
            const features = await mappedFeatures();
            const feature = features.find(item => Number(item.id) === featureId);
            if (feature?.geometry) {
                highlight = L.geoJSON(feature.geometry, {
                    style: {color: '#f8d028', weight: 4, opacity: 1, fillOpacity: .24},
                    pointToLayer: (_, latLng) => L.circleMarker(latLng, {radius: 11, color: '#f8d028', weight: 3})
                }).addTo(map);
                const bounds = highlight.getBounds();
                if (bounds.isValid()) {
                    if (bounds.getNorthEast().equals(bounds.getSouthWest())) {
                        map.setView(bounds.getCenter(), Math.min(17, map.getMaxZoom()), {animate: true});
                    } else {
                        map.fitBounds(bounds.pad(.8), {maxZoom: Math.min(17, map.getMaxZoom()), animate: true});
                    }
                    L.popup({maxWidth: 280})
                        .setLatLng(bounds.getCenter())
                        .setContent(buildPopup(selection.name, lines, true))
                        .openOn(map);
                    return;
                }
            }
        }
        // Never invent an individual position for a campsite that lacks
        // mapped geometry. The established Place center is the safe fallback.
        const lat = Number(mapElement?.dataset.placeLatitude);
        const lng = Number(mapElement?.dataset.placeLongitude);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            map.setView([lat, lng], Math.min(15, map.getMaxZoom()), {animate: true});
            L.popup({maxWidth: 280})
                .setLatLng([lat, lng])
                .setContent(buildPopup(selection.name, lines, false))
                .openOn(map);
        }
    };

    const choose = (row, shouldFocus = true) => {
        if (!row) return;
        const isCanonical = row.hasAttribute('data-campsite-id');
        const isMapSite = row.hasAttribute('data-stay-map-site-id');
        const isParking = row.hasAttribute('data-stay-parking-id');
        if (!isCanonical && !isMapSite && !isParking) return;
        const name = isCanonical
            ? row.querySelector('.place-campsite-row-name')?.textContent.trim() || 'Campsite'
            : isMapSite
                ? row.dataset.stayMapSiteName || 'Campsite'
                : row.dataset.stayParkingName || 'Parking area';
        const lines = isCanonical ? [] : [
            row.dataset.stayMapSiteSummary || row.dataset.stayParkingCost || '',
            row.dataset.stayMapSiteArea || row.dataset.stayParkingStatus || ''
        ].filter(Boolean);
        lastSelection = {
            row, name, lines, kind: isCanonical ? 'canonical' : (isMapSite ? 'mapped' : 'parking'),
            id: row.dataset.campsiteId || '',
            featureId: Number(row.dataset.stayMapSiteId || row.dataset.stayParkingId) || 0
        };
        showContext(name, isParking ? 'parking' : 'campsite', lastSelection);
        if (shouldFocus) void focusSelection(lastSelection);
    };

    section.addEventListener('click', (event) => {
        const row = event.target.closest('[data-campsite-id], [data-stay-map-site-id], [data-stay-parking-id]');
        if (row && section.contains(row)) choose(row, true);
    });

    // The canonical browser auto-selects a site on load and after filters.
    // Update the report label without touching either map or page scrolling.
    let selectedKey = '';
    const reflectSelection = () => {
        const row = section.querySelector('[data-campsite-id].is-selected');
        if (!row) return;
        const key = row.dataset.campsiteId || '';
        if (key && selectedKey !== key) {
            selectedKey = key;
            choose(row, false);
        }
    };
    const observer = new MutationObserver(() => requestAnimationFrame(reflectSelection));
    observer.observe(section, {subtree: true, childList: true, attributes: true, attributeFilter: ['class']});
    reflectSelection();
})();
