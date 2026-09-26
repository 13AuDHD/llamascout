(() => {
    'use strict';

    const MAP_GLOBAL = window.LlamaScoutMap;
    const map = MAP_GLOBAL?.map;
    const mapElement = MAP_GLOBAL?.mapElement || MAP_GLOBAL?.element;
    const mapCard = mapElement?.closest('.map-card');

    if (!map || !mapElement || !mapCard || typeof L === 'undefined') {
        return;
    }

    /*
     * Land overlays are a Complete Access feature.
     * This client-side guard is intentional defense-in-depth.
     * map.php should also avoid loading this file for public/free users.
     */
    if (mapCard.dataset.mapMember !== '1') {
        return;
    }

    const STYLE_HREF = '/css/map-land-overlays.css?v=20260924-1';
    const STORAGE_KEY = 'llama-map-land-overlays';
    const MIN_ZOOM = 7;
    const VIEWPORT_PADDING = 0.35;
    const RETRY_DELAY_MS = 350;

    const sources = {
        usfs: {
            label: 'U.S. Forest Service',
            endpoints: [
                'https://gis.blm.gov/arcgis/rest/services/lands/BLM_Natl_SMA_LimitedScale/MapServer/24/query',
                'https://gis.blm.gov/arcgis/rest/services/lands/BLM_Natl_SMA_Cached_without_PriUnk/MapServer/23/query'
            ],
            fields: 'ADMIN_UNIT_NAME,ADMIN_UNIT_TYPE,ADMIN_ST',
            nameField: 'ADMIN_UNIT_NAME',
            detailField: 'ADMIN_UNIT_TYPE',
            sourceText: 'BLM National Surface Management Agency',
            style: {
                color: '#4f8f46',
                weight: 1.3,
                opacity: 0.52,
                fillColor: '#4f8f46',
                fillOpacity: 0.10
            }
        },

        blm: {
            label: 'Bureau of Land Management',
            endpoints: [
                'https://gis.blm.gov/arcgis/rest/services/lands/BLM_Natl_SMA_LimitedScale/MapServer/22/query',
                'https://gis.blm.gov/arcgis/rest/services/lands/BLM_Natl_SMA_Cached_without_PriUnk/MapServer/21/query'
            ],
            fields: 'ADMIN_UNIT_NAME,ADMIN_UNIT_TYPE,ADMIN_ST',
            nameField: 'ADMIN_UNIT_NAME',
            detailField: 'ADMIN_UNIT_TYPE',
            sourceText: 'BLM National Surface Management Agency',
            style: {
                color: '#c59a27',
                weight: 1.3,
                opacity: 0.54,
                fillColor: '#c59a27',
                fillOpacity: 0.11
            }
        },

        tribal: {
            label: 'Tribal land',
            endpoints: [
                'https://services3.arcgis.com/OYP7N6mAJJCyH6hd/ArcGIS/rest/services/BIA_AIAN_LAR_Layers/FeatureServer/0/query'
            ],
            fields: 'LARNAME,CLASSIFICATION,REGION,AGENCY',
            nameField: 'LARNAME',
            detailField: 'CLASSIFICATION',
            sourceText: 'Bureau of Indian Affairs National LAR',
            style: {
                color: '#9a536f',
                weight: 1.4,
                opacity: 0.56,
                fillColor: '#9a536f',
                fillOpacity: 0.11
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
        enabled: false,
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
            // Local storage is optional.
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
                            aria-pressed="false"
                            title="${escapeHtml(source.label)} boundaries"
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

const landSlot =
    document.getElementById('map-tools-land-slot');

const controlHost =
    landSlot || mapCard;

controlHost.appendChild(control);

if (landSlot) {
    control.classList.add(
        'map-land-control-embedded'
    );
}

statusNode =
    control.querySelector('#map-land-status');
        
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
                        state[key].error = false;
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

        if (zoom <= 7) return 7;
        if (zoom <= 9) return 9;
        if (zoom <= 11) return 11;

        return 12;
    }

    function maxAllowableOffset() {
        const zoom = map.getZoom();

        if (zoom <= 7) return 0.01;
        if (zoom <= 9) return 0.004;
        if (zoom <= 11) return 0.0015;

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

    function buildQueryUrl(endpoint, key, bounds) {
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
            resultRecordCount: '2000',
            f: 'geojson'
        });

        return `${endpoint}?${params.toString()}`;
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

        if (
            sourceState.layer &&
            map.hasLayer(sourceState.layer)
        ) {
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

function popupRow(
    label,
    value
) {
    const clean =
        String(value ?? '').trim();

    if (!clean) {
        return '';
    }

    return `
        <div class="map-overlay-popup-row">
            <strong>
                ${escapeHtml(label)}
            </strong>

            <span>
                ${escapeHtml(clean)}
            </span>
        </div>
    `;
}


function popupHtml(
    key,
    properties
) {
    const source =
        sources[key];

    const name =
        String(
            properties?.[
                source.nameField
            ] || ''
        ).trim();

    const detail =
        String(
            properties?.[
                source.detailField
            ] || ''
        ).trim();

    let extraRows = '';

    if (key === 'tribal') {
        extraRows +=
            popupRow(
                'Region',
                properties?.REGION
            );

        extraRows +=
            popupRow(
                'Agency',
                properties?.AGENCY
            );
    } else {
        extraRows +=
            popupRow(
                'State',
                properties?.ADMIN_ST
            );
    }

    return `
        <article
            class="
                map-overlay-popup
                map-land-popup
            "
        >

            <button
                type="button"
                class="map-overlay-popup-close"
                data-map-overlay-popup-close
                aria-label="Close boundary details"
            >
                ×
            </button>

            <p class="map-overlay-popup-eyebrow">
                ${escapeHtml(source.label)}
            </p>

            <h3>
                ${escapeHtml(
                    name ||
                    source.label
                )}
            </h3>

            <div class="map-overlay-popup-meta">

                ${
                    detail &&
                    detail !== name
                        ? popupRow(
                            'Type',
                            detail
                        )
                        : ''
                }

                ${extraRows}

            </div>

            <p class="map-overlay-popup-source">
                Boundary data:
                ${escapeHtml(
                    source.sourceText
                )}
            </p>

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

    function sleep(ms, signal) {
        return new Promise((resolve, reject) => {
            const timer = window.setTimeout(resolve, ms);

            signal?.addEventListener(
                'abort',
                () => {
                    window.clearTimeout(timer);

                    const error =
                        new DOMException(
                            'Request aborted.',
                            'AbortError'
                        );

                    reject(error);
                },
                { once: true }
            );
        });
    }

    async function requestGeoJson(
        endpoint,
        key,
        bounds,
        signal
    ) {
        const response = await fetch(
            buildQueryUrl(endpoint, key, bounds),
            {
                method: 'GET',
                mode: 'cors',
                cache: 'default',
                signal,
                headers: {
                    Accept:
                        'application/geo+json, application/json'
                }
            }
        );

        if (!response.ok) {
            throw new Error(
                `${sources[key].label} returned HTTP ${response.status}.`
            );
        }

        const data = await response.json();

        if (
            data?.type !== 'FeatureCollection' ||
            !Array.isArray(data.features)
        ) {
            throw new Error(
                `${sources[key].label} did not return GeoJSON.`
            );
        }

        return data;
    }

    async function fetchWithRetry(
        key,
        bounds,
        signal
    ) {
        const endpoints = sources[key].endpoints;
        let lastError = null;

        for (
            let endpointIndex = 0;
            endpointIndex < endpoints.length;
            endpointIndex++
        ) {
            const endpoint = endpoints[endpointIndex];

            for (let attempt = 0; attempt < 2; attempt++) {
                try {
                    return await requestGeoJson(
                        endpoint,
                        key,
                        bounds,
                        signal
                    );
                } catch (error) {
                    if (error?.name === 'AbortError') {
                        throw error;
                    }

                    lastError = error;

                    if (attempt === 0) {
                        await sleep(
                            RETRY_DELAY_MS,
                            signal
                        );
                    }
                }
            }
        }

        throw (
            lastError ||
            new Error(
                `${sources[key].label} boundaries could not load.`
            )
        );
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
        sourceState.controller =
            new AbortController();

        updateStatus();

        const bounds = queryBounds();
        const zoomBucket = currentZoomBucket();

        try {
            const data = await fetchWithRetry(
                key,
                bounds,
                sourceState.controller.signal
            );

            if (
                !state[key].enabled ||
                requestNumber !== state[key].requestNumber
            ) {
                return;
            }

            const nextLayer =
                makeGeoJsonLayer(key, data);

            /*
             * Only replace the existing successful layer after
             * the new response has been completely parsed.
             * A temporary service failure therefore cannot erase
             * the last good polygons.
             */
            removeSourceLayer(key);

            sourceState.layer = nextLayer;
            sourceState.loadedBounds = bounds;
            sourceState.zoomBucket = zoomBucket;
            sourceState.error = false;

            nextLayer.addTo(map);

        } catch (error) {
            if (error?.name !== 'AbortError') {
                sourceState.error = true;

                /*
                 * Keep the last successful layer visible.
                 */
                ensureSourceLayerShown(key);

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

    function failedLabels() {
        return enabledKeys()
            .filter((key) => state[key].error)
            .map((key) => sources[key].label);
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

        if (loading) {
            statusNode.hidden = false;
            statusNode.textContent =
                'Loading land boundaries...';
            return;
        }

        const failed = failedLabels();

        if (failed.length) {
            statusNode.hidden = false;

            if (failed.length === 1) {
                statusNode.textContent =
                    `${failed[0]} boundaries are temporarily unavailable.`;
            } else {
                statusNode.textContent =
                    `${failed.join(' and ')} boundaries are temporarily unavailable.`;
            }

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

    if (!map.getPane('llama-land-pane')) {
        map.createPane('llama-land-pane');
    }

    map.getPane('llama-land-pane')
        .style.zIndex = '350';

    createControl();

    map.on('moveend', () => {
        scheduleRefresh();
    });

    map.on('resize', () => {
        scheduleRefresh();
    });

    scheduleRefresh(0);
})();
