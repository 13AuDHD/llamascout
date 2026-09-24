(() => {
    'use strict';

    const MAP_GLOBAL = window.LlamaScoutMap;
    const map = MAP_GLOBAL?.map;
    const mapElement = MAP_GLOBAL?.mapElement || MAP_GLOBAL?.element;
    const mapCard = mapElement?.closest('.map-card');

    if (!map || !mapElement || !mapCard || typeof L === 'undefined') {
        return;
    }

    const STYLE_HREF = '/css/map-land-overlays.css?v=20260924-1';
    const STORAGE_KEY = 'llama-map-land-overlays';
    const MIN_ZOOM = 7;
    const VIEWPORT_PADDING = 0.35;

    const sources = {
        usfs: {
            label: 'USFS',
            fullLabel: 'U.S. Forest Service',
            endpoint:
                'https://gis.blm.gov/arcgis/rest/services/lands/BLM_Natl_SMA_Cached_without_PriUnk/MapServer/23/query',
            fields:
                'ADMIN_UNIT_NAME,ADMIN_UNIT_TYPE,ADMIN_ST',
            nameField: 'ADMIN_UNIT_NAME',
            detailField: 'ADMIN_UNIT_TYPE',
            style: {
                color: '#4f8f46',
                weight: 2,
                opacity: 0.92,
                fillColor: '#4f8f46',
                fillOpacity: 0.18
            }
        },

        blm: {
            label: 'BLM',
            fullLabel: 'Bureau of Land Management',
            endpoint:
                'https://gis.blm.gov/arcgis/rest/services/lands/BLM_Natl_SMA_Cached_without_PriUnk/MapServer/21/query',
            fields:
                'ADMIN_UNIT_NAME,ADMIN_UNIT_TYPE,ADMIN_ST',
            nameField: 'ADMIN_UNIT_NAME',
            detailField: 'ADMIN_UNIT_TYPE',
            style: {
                color: '#c99a28',
                weight: 2,
                opacity: 0.95,
                dashArray: '8 5',
                fillColor: '#c99a28',
                fillOpacity: 0.2
            }
        },

        tribal: {
            label: 'Tribal',
            fullLabel: 'Tribal land',
            endpoint:
                'https://services3.arcgis.com/OYP7N6mAJJCyH6hd/ArcGIS/rest/services/BIA_AIAN_LAR_Layers/FeatureServer/0/query',
            fields:
                'LARNAME,CLASSIFICATION,REGION,AGENCY',
            nameField: 'LARNAME',
            detailField: 'CLASSIFICATION',
            style: {
                color: '#b8583c',
                weight: 2.3,
                opacity: 0.98,
                dashArray: '2 5',
                fillColor: '#b8583c',
                fillOpacity: 0.2
            }
        }
    };

    const state = {
        usfs: createSourceState(),
        blm: createSourceState(),
        tribal: createSourceState()
    };

    let refreshTimer = null;
    let control = null;
    let statusNode = null;

    function createSourceState() {
        return {
            enabled: true,
            layer: null,
            loadedBounds: null,
            zoomBucket: null,
            controller: null,
            requestNumber: 0,
            loading: false,
            error: false
        };
    }

    function ensureStyles() {
        if (document.querySelector('link[data-map-land-overlays-style]')) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = STYLE_HREF;
        link.dataset.mapLandOverlaysStyle = '1';
        document.head.appendChild(link);
    }

    function loadPreferences() {
        try {
            const saved = JSON.parse(
                window.localStorage.getItem(STORAGE_KEY) || 'null'
            );

            if (!saved || typeof saved !== 'object') {
                return;
            }

            Object.keys(state).forEach((key) => {
                if (typeof saved[key] === 'boolean') {
                    state[key].enabled = saved[key];
                }
            });
        } catch (error) {
            // Local storage is optional. Defaults remain enabled.
        }
    }

    function savePreferences() {
        const payload = {};

        Object.keys(state).forEach((key) => {
            payload[key] = state[key].enabled;
        });

        try {
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(payload)
            );
        } catch (error) {
            // The map still works when storage is unavailable.
        }
    }

    function createControl() {
        control = document.createElement('div');
        control.id = 'map-land-control';
        control.className = 'map-land-control';
        control.setAttribute('aria-label', 'Land boundaries');

        control.innerHTML = `
            <span class="map-land-label">LAND</span>

            <div
                class="map-land-buttons"
                role="group"
                aria-label="Land boundary overlays"
            >
                ${Object.entries(sources)
                    .map(([key, source]) => `
                        <button
                            type="button"
                            data-land-layer="${key}"
                            aria-pressed="true"
                            title="${escapeHtml(source.fullLabel)} boundaries"
                        >
                            <span
                                class="map-land-swatch map-land-swatch-${key}"
                                aria-hidden="true"
                            ></span>
                            ${escapeHtml(source.label)}
                        </button>
                    `)
                    .join('')}
            </div>

            <span
                id="map-land-status"
                class="map-land-status"
                role="status"
                aria-live="polite"
                hidden
            ></span>
        `;

        mapCard.appendChild(control);

        statusNode = control.querySelector('#map-land-status');

        control
            .querySelectorAll('[data-land-layer]')
            .forEach((button) => {
                button.addEventListener('click', () => {
                    const key = button.dataset.landLayer;

                    if (!key || !state[key]) {
                        return;
                    }

                    state[key].enabled = !state[key].enabled;

                    if (!state[key].enabled) {
                        abortSource(key);
                        removeSourceLayer(key);
                    } else {
                        state[key].loadedBounds = null;
                        state[key].zoomBucket = null;
                    }

                    savePreferences();
                    syncButtons();
                    scheduleRefresh(0);
                });
            });

        syncButtons();
    }

    function syncButtons() {
        if (!control) {
            return;
        }

        control
            .querySelectorAll('[data-land-layer]')
            .forEach((button) => {
                const key = button.dataset.landLayer;
                const active = Boolean(key && state[key]?.enabled);

                button.classList.toggle('is-active', active);
                button.setAttribute(
                    'aria-pressed',
                    active ? 'true' : 'false'
                );
            });
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    }

    function currentZoomBucket() {
        const zoom = map.getZoom();

        if (zoom <= 7) {
            return 7;
        }

        if (zoom <= 9) {
            return 9;
        }

        if (zoom <= 11) {
            return 11;
        }

        return 12;
    }

    function maxAllowableOffset() {
        const zoom = map.getZoom();

        if (zoom <= 7) {
            return 0.01;
        }

        if (zoom <= 9) {
            return 0.004;
        }

        if (zoom <= 11) {
            return 0.0015;
        }

        return 0.0006;
    }

    function queryBounds() {
        return map
            .getBounds()
            .pad(VIEWPORT_PADDING);
    }

    function normalizedBounds(bounds) {
        return {
            west: Math.max(-180, bounds.getWest()),
            south: Math.max(-90, bounds.getSouth()),
            east: Math.min(180, bounds.getEast()),
            north: Math.min(90, bounds.getNorth())
        };
    }

    function buildQueryUrl(key, bounds) {
        const source = sources[key];
        const box = normalizedBounds(bounds);
        const params = new URLSearchParams({
            where: '1=1',
            geometry: [
                box.west,
                box.south,
                box.east,
                box.north
            ].join(','),
            geometryType: 'esriGeometryEnvelope',
            inSR: '4326',
            spatialRel: 'esriSpatialRelIntersects',
            outFields: source.fields,
            returnGeometry: 'true',
            outSR: '4326',
            geometryPrecision: '5',
            maxAllowableOffset: String(maxAllowableOffset()),
            f: 'geojson'
        });

        return `${source.endpoint}?${params.toString()}`;
    }

    function loadedAreaStillCoversView(key) {
        const sourceState = state[key];

        if (
            !sourceState.loadedBounds ||
            sourceState.zoomBucket !== currentZoomBucket()
        ) {
            return false;
        }

        const current = map.getBounds();

        return (
            sourceState.loadedBounds.contains(current.getNorthWest()) &&
            sourceState.loadedBounds.contains(current.getSouthEast())
        );
    }

    function abortSource(key) {
        const sourceState = state[key];

        if (sourceState.controller) {
            sourceState.controller.abort();
            sourceState.controller = null;
        }

        sourceState.loading = false;
    }

    function removeSourceLayer(key) {
        const sourceState = state[key];

        if (sourceState.layer && map.hasLayer(sourceState.layer)) {
            map.removeLayer(sourceState.layer);
        }
    }

    function ensureSourceLayerShown(key) {
        const sourceState = state[key];

        if (
            sourceState.enabled &&
            sourceState.layer &&
            !map.hasLayer(sourceState.layer)
        ) {
            sourceState.layer.addTo(map);
        }
    }

    function popupHtml(key, properties) {
        const source = sources[key];
        const name = String(
            properties?.[source.nameField] || ''
        ).trim();

        const detail = String(
            properties?.[source.detailField] || ''
        ).trim();

        const sourceLine = key === 'tribal'
            ? 'Boundary data: Bureau of Indian Affairs'
            : 'Boundary data: Bureau of Land Management SMA';

        return `
            <article class="map-land-popup">
                <strong>${escapeHtml(source.fullLabel)}</strong>

                ${
                    name
                        ? `<span>${escapeHtml(name)}</span>`
                        : ''
                }

                ${
                    detail && detail !== name
                        ? `<small>${escapeHtml(detail)}</small>`
                        : ''
                }

                <small>${escapeHtml(sourceLine)}</small>
            </article>
        `;
    }

    function makeGeoJsonLayer(key, data) {
        const source = sources[key];

        return L.geoJSON(data, {
            pane: 'llama-land-pane',

            style: () => ({
                ...source.style
            }),

            onEachFeature: (feature, layer) => {
                layer.bindPopup(
                    popupHtml(
                        key,
                        feature?.properties || {}
                    ),
                    {
                        maxWidth: 280
                    }
                );
            }
        });
    }

    async function loadSource(key) {
        const sourceState = state[key];

        if (!sourceState.enabled) {
            return;
        }

        if (loadedAreaStillCoversView(key)) {
            ensureSourceLayerShown(key);
            return;
        }

        abortSource(key);

        const requestNumber =
            sourceState.requestNumber + 1;

        sourceState.requestNumber = requestNumber;
        sourceState.loading = true;
        sourceState.error = false;
        sourceState.controller = new AbortController();

        updateStatus();

        const bounds = queryBounds();
        const zoomBucket = currentZoomBucket();

        try {
            const response = await fetch(
                buildQueryUrl(key, bounds),
                {
                    method: 'GET',
                    mode: 'cors',
                    cache: 'default',
                    signal: sourceState.controller.signal,
                    headers: {
                        'Accept':
                            'application/geo+json, application/json'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(
                    `${sources[key].fullLabel} returned HTTP ${response.status}.`
                );
            }

            const data = await response.json();

            if (
                data?.type !== 'FeatureCollection' ||
                !Array.isArray(data.features)
            ) {
                throw new Error(
                    `${sources[key].fullLabel} did not return GeoJSON.`
                );
            }

            if (
                !state[key].enabled ||
                requestNumber !== state[key].requestNumber
            ) {
                return;
            }

            const nextLayer =
                makeGeoJsonLayer(key, data);

            removeSourceLayer(key);

            sourceState.layer = nextLayer;
            sourceState.loadedBounds = bounds;
            sourceState.zoomBucket = zoomBucket;

            nextLayer.addTo(map);

        } catch (error) {
            if (error?.name !== 'AbortError') {
                sourceState.error = true;

                console.warn(
                    `Llama Scout land overlay (${key}):`,
                    error
                );
            }
        } finally {
            if (
                requestNumber === sourceState.requestNumber
            ) {
                sourceState.loading = false;
                sourceState.controller = null;
            }

            updateStatus();
        }
    }

    function enabledKeys() {
        return Object.keys(state)
            .filter((key) => state[key].enabled);
    }

    function updateStatus() {
        if (!statusNode) {
            return;
        }

        const enabled = enabledKeys();

        if (!enabled.length) {
            statusNode.hidden = true;
            statusNode.textContent = '';
            return;
        }

        if (map.getZoom() < MIN_ZOOM) {
            statusNode.hidden = false;
            statusNode.textContent =
                `Zoom in to level ${MIN_ZOOM} to show land boundaries.`;
            return;
        }

        const loading =
            enabled.some((key) => state[key].loading);

        const failed =
            enabled.some((key) => state[key].error);

        if (loading) {
            statusNode.hidden = false;
            statusNode.textContent =
                'Loading land boundaries...';
            return;
        }

        if (failed) {
            statusNode.hidden = false;
            statusNode.textContent =
                'Some land boundaries could not load.';
            return;
        }

        statusNode.hidden = true;
        statusNode.textContent = '';
    }

    function refresh() {
        window.clearTimeout(refreshTimer);
        refreshTimer = null;

        const enabled = enabledKeys();

        if (!enabled.length) {
            updateStatus();
            return;
        }

        if (map.getZoom() < MIN_ZOOM) {
            enabled.forEach((key) => {
                abortSource(key);
                removeSourceLayer(key);
            });

            updateStatus();
            return;
        }

        enabled.forEach((key) => {
            loadSource(key);
        });

        Object.keys(state)
            .filter((key) => !state[key].enabled)
            .forEach(removeSourceLayer);

        updateStatus();
    }

    function scheduleRefresh(delay = 120) {
        window.clearTimeout(refreshTimer);

        refreshTimer = window.setTimeout(
            refresh,
            delay
        );
    }

    ensureStyles();
    loadPreferences();

    map.createPane('llama-land-pane');
    map.getPane('llama-land-pane').style.zIndex = '350';

    createControl();

    map.on('moveend', () => {
        scheduleRefresh();
    });

    map.on('resize', () => {
        scheduleRefresh();
    });

    scheduleRefresh(0);
})();
