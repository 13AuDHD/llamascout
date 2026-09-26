(() => {
    'use strict';

    const MAP_GLOBAL =
        window.LlamaScoutMap;

    const map =
        MAP_GLOBAL?.map;

    const mapElement =
        MAP_GLOBAL?.mapElement
        || MAP_GLOBAL?.element;

    const mapCard =
        mapElement?.closest(
            '.map-card'
        );

    const panel =
        document.getElementById(
            'map-tools-panel-cell'
        );

    const trigger =
        document.querySelector(
            '[data-map-tool="cell"]'
        );

    if (
        !map ||
        !mapElement ||
        !mapCard ||
        !panel ||
        !trigger ||
        typeof L === 'undefined'
    ) {
        return;
    }

    if (
        mapCard.dataset.mapMember
        !== '1'
    ) {
        return;
    }

    const STYLE_HREF =
        '/css/map-cell-overlays.css?v=20260926-2';

    const STORAGE_KEY =
        'llama-map-cell-coverage';

    const API_URL =
        '/api/cell-coverage.php';

    const MIN_ZOOM = 11;

    const VIEWPORT_PADDING =
        0.18;

    const providers = {
        tmobile: {
            label: 'T-Mobile',
            color: '#d42a87'
        },

        verizon: {
            label: 'Verizon',
            color: '#d34a4a'
        },

        att: {
            label: 'AT&T',
            color: '#3d8bc9'
        }
    };

    const defaults = {
        providers: {
            tmobile: false,
            verizon: false,
            att: false
        },

        technology: '4g',
        environment: 'vehicle'
    };

    const state =
        typeof structuredClone
        === 'function'
            ? structuredClone(
                defaults
            )
            : JSON.parse(
                JSON.stringify(
                    defaults
                )
            );

    const coverageLayers = {};

    let countNode = null;
    let statusNode = null;
    let refreshTimer = null;
    let requestController = null;
    let requestNumber = 0;


    function ensureStyles() {
        if (
            document.querySelector(
                'link[data-map-cell-overlays-style]'
            )
        ) {
            return;
        }

        const link =
            document.createElement(
                'link'
            );

        link.rel = 'stylesheet';
        link.href = STYLE_HREF;

        link.dataset
            .mapCellOverlaysStyle =
                '1';

        document.head
            .appendChild(link);
    }


    function ensurePane() {
        const paneName =
            'llama-cell-coverage-pane';

        if (!map.getPane(paneName)) {
            map.createPane(paneName);
        }

        const pane =
            map.getPane(paneName);

        if (pane) {
            pane.style.zIndex = '330';
            pane.style.pointerEvents =
                'none';
        }

        return paneName;
    }


    const paneName =
        ensurePane();


    function loadPreferences() {
        try {
            const saved =
                JSON.parse(
                    window.localStorage
                        .getItem(
                            STORAGE_KEY
                        )
                    || 'null'
                );

            if (
                !saved ||
                typeof saved !== 'object'
            ) {
                return;
            }

            Object.keys(providers)
                .forEach((key) => {
                    if (
                        typeof saved
                            .providers?.[key]
                        === 'boolean'
                    ) {
                        state.providers[key] =
                            saved.providers[key];
                    }
                });

            if (
                saved.technology
                    === '4g'
                || saved.technology
                    === '5g'
            ) {
                state.technology =
                    saved.technology;
            }

            if (
                saved.environment
                    === 'vehicle'
                || saved.environment
                    === 'outdoors'
            ) {
                state.environment =
                    saved.environment;
            }

        } catch (error) {
            // Local storage is optional.
        }
    }


    function savePreferences() {
        try {
            window.localStorage
                .setItem(
                    STORAGE_KEY,
                    JSON.stringify(
                        state
                    )
                );
        } catch (error) {
            // Coverage still works without storage.
        }
    }


    function createCountNode() {
        countNode =
            document.getElementById(
                'map-tool-cell-count'
            );

        if (countNode) {
            return;
        }

        countNode =
            document.createElement(
                'span'
            );

        countNode.id =
            'map-tool-cell-count';

        countNode.className =
            'map-tool-count';

        countNode.hidden = true;
        countNode.textContent = '0';

        trigger.appendChild(
            countNode
        );
    }


    function escapeHtml(value) {
        const node =
            document.createElement(
                'div'
            );

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    }


    function createControls() {
        panel.innerHTML = `
            <p class="map-tools-panel-title">
                Cell coverage
            </p>

            <div class="map-cell-section">
                <span class="map-cell-section-label">
                    Providers
                </span>

                <div
                    class="map-cell-provider-buttons"
                    role="group"
                    aria-label="Cell providers"
                >
                    ${Object.entries(providers)
                        .map(([key, provider]) => `
                            <button
                                type="button"
                                data-cell-provider="${key}"
                                aria-pressed="false"
                            >
                                <span
                                    class="map-cell-swatch map-cell-swatch-${key}"
                                    aria-hidden="true"
                                ></span>

                                ${escapeHtml(
                                    provider.label
                                )}
                            </button>
                        `)
                        .join('')}
                </div>
            </div>

            <div class="map-cell-section">
                <span class="map-cell-section-label">
                    Coverage
                </span>

                <div
                    class="map-cell-toggle"
                    role="group"
                    aria-label="Cell technology"
                >
                    <button
                        type="button"
                        data-cell-technology="4g"
                        aria-pressed="false"
                    >
                        4G LTE
                    </button>

                    <button
                        type="button"
                        data-cell-technology="5g"
                        aria-pressed="false"
                    >
                        5G
                    </button>
                </div>
            </div>

            <div class="map-cell-section">
                <span class="map-cell-section-label">
                    Environment
                </span>

                <div
                    class="map-cell-toggle"
                    role="group"
                    aria-label="Coverage environment"
                >
                    <button
                        type="button"
                        data-cell-environment="vehicle"
                        aria-pressed="false"
                    >
                        In vehicle
                    </button>

                    <button
                        type="button"
                        data-cell-environment="outdoors"
                        aria-pressed="false"
                    >
                        Outdoors
                    </button>
                </div>
            </div>

            <p class="map-cell-source">
                Coverage data:
                FCC National Broadband Map
            </p>

            <span
                id="map-cell-status"
                class="map-cell-status"
                role="status"
                aria-live="polite"
                hidden
            ></span>
        `;

        statusNode =
            panel.querySelector(
                '#map-cell-status'
            );

        panel
            .querySelectorAll(
                '[data-cell-provider]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const key =
                            button.dataset
                                .cellProvider;

                        if (
                            !key ||
                            !Object.hasOwn(
                                state.providers,
                                key
                            )
                        ) {
                            return;
                        }

                        state.providers[key] =
                            !state.providers[key];

                        changed();
                    }
                );
            });

        panel
            .querySelectorAll(
                '[data-cell-technology]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const value =
                            button.dataset
                                .cellTechnology;

                        if (
                            value !== '4g'
                            && value !== '5g'
                        ) {
                            return;
                        }

                        state.technology =
                            value;

                        changed();
                    }
                );
            });

        panel
            .querySelectorAll(
                '[data-cell-environment]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const value =
                            button.dataset
                                .cellEnvironment;

                        if (
                            value !== 'vehicle'
                            && value !== 'outdoors'
                        ) {
                            return;
                        }

                        state.environment =
                            value;

                        changed();
                    }
                );
            });

        syncControls();
    }


    function activeProviderKeys() {
        return Object.keys(
            providers
        ).filter(
            (key) =>
                state.providers[key]
        );
    }


    function syncControls() {
        panel
            .querySelectorAll(
                '[data-cell-provider]'
            )
            .forEach((button) => {
                const key =
                    button.dataset
                        .cellProvider;

                const active =
                    Boolean(
                        key &&
                        state.providers[key]
                    );

                button.classList.toggle(
                    'is-active',
                    active
                );

                button.setAttribute(
                    'aria-pressed',
                    active
                        ? 'true'
                        : 'false'
                );
            });

        panel
            .querySelectorAll(
                '[data-cell-technology]'
            )
            .forEach((button) => {
                const active =
                    button.dataset
                        .cellTechnology
                    === state.technology;

                button.classList.toggle(
                    'is-active',
                    active
                );

                button.setAttribute(
                    'aria-pressed',
                    active
                        ? 'true'
                        : 'false'
                );
            });

        panel
            .querySelectorAll(
                '[data-cell-environment]'
            )
            .forEach((button) => {
                const active =
                    button.dataset
                        .cellEnvironment
                    === state.environment;

                button.classList.toggle(
                    'is-active',
                    active
                );

                button.setAttribute(
                    'aria-pressed',
                    active
                        ? 'true'
                        : 'false'
                );
            });

        const providerCount =
            activeProviderKeys().length;

        if (countNode) {
            countNode.textContent =
                String(providerCount);

            countNode.hidden =
                providerCount === 0;
        }

        trigger.classList.toggle(
            'has-active',
            providerCount > 0
        );
    }


    function showStatus(message) {
        if (!statusNode) {
            return;
        }

        const clean =
            String(message ?? '')
                .trim();

        statusNode.textContent =
            clean;

        statusNode.hidden =
            clean === '';
    }


    function clearLayers() {
        Object.values(
            coverageLayers
        ).forEach((layer) => {
            if (
                layer &&
                map.hasLayer(layer)
            ) {
                map.removeLayer(layer);
            }
        });

        Object.keys(
            coverageLayers
        ).forEach((key) => {
            delete coverageLayers[key];
        });
    }


    function clearProviderLayer(key) {
        const layer =
            coverageLayers[key];

        if (
            layer &&
            map.hasLayer(layer)
        ) {
            map.removeLayer(layer);
        }

        delete coverageLayers[key];
    }


    function boundaryForCell(
        h3Index
    ) {
        if (
            !window.h3 ||
            typeof window.h3
                .cellToBoundary
                !== 'function'
        ) {
            return null;
        }

        try {
            const boundary =
                window.h3.cellToBoundary(
                    h3Index,
                    true
                );

            if (
                !Array.isArray(boundary)
                || boundary.length < 3
            ) {
                return null;
            }

            const ring =
                boundary.map(
                    (point) => [
                        Number(point[0]),
                        Number(point[1])
                    ]
                );

            const first =
                ring[0];

            const last =
                ring[
                    ring.length - 1
                ];

            if (
                first[0] !== last[0]
                || first[1] !== last[1]
            ) {
                ring.push(
                    [...first]
                );
            }

            return ring;

        } catch (error) {
            return null;
        }
    }


    function renderProvider(
        key,
        cells
    ) {
        clearProviderLayer(key);

        const provider =
            providers[key];

        if (
            !provider ||
            !Array.isArray(cells)
            || !cells.length
        ) {
            return;
        }

        const polygons = [];

        cells.forEach((h3Index) => {
            const ring =
                boundaryForCell(
                    h3Index
                );

            if (ring) {
                polygons.push(
                    [ring]
                );
            }
        });

        if (!polygons.length) {
            return;
        }

        const feature = {
            type: 'Feature',
            properties: {
                provider:
                    provider.label
            },
            geometry: {
                type:
                    'MultiPolygon',
                coordinates:
                    polygons
            }
        };

        const layer =
            L.geoJSON(
                feature,
                {
                    pane:
                        paneName,

                    interactive:
                        false,

                    style: {
                        color:
                            provider.color,

                        weight:
                            0.55,

                        opacity:
                            0.65,

                        fillColor:
                            provider.color,

                        fillOpacity:
                            0.20
                    }
                }
            );

        layer.addTo(map);

        coverageLayers[key] =
            layer;
    }


    function buildRequestUrl() {
        const activeProviders =
            activeProviderKeys();

        if (!activeProviders.length) {
            return null;
        }

        const bounds =
            map.getBounds()
                .pad(
                    VIEWPORT_PADDING
                );

        const params =
            new URLSearchParams({
                providers:
                    activeProviders
                        .join(','),

                technology:
                    state.technology,

                environment:
                    state.environment,

                north:
                    String(
                        Math.min(
                            90,
                            bounds.getNorth()
                        )
                    ),

                south:
                    String(
                        Math.max(
                            -90,
                            bounds.getSouth()
                        )
                    ),

                east:
                    String(
                        Math.min(
                            180,
                            bounds.getEast()
                        )
                    ),

                west:
                    String(
                        Math.max(
                            -180,
                            bounds.getWest()
                        )
                    ),

                zoom:
                    String(
                        map.getZoom()
                    )
            });

        return (
            API_URL
            + '?'
            + params.toString()
        );
    }


    async function refreshCoverage() {
        const activeProviders =
            activeProviderKeys();

        if (!activeProviders.length) {
            requestController
                ?.abort();

            clearLayers();
            showStatus('');
            return;
        }

        if (
            map.getZoom()
            < MIN_ZOOM
        ) {
            requestController
                ?.abort();

            clearLayers();

            showStatus(
                `Zoom in to level ${MIN_ZOOM} or closer to see cell coverage.`
            );

            return;
        }

        if (
            !window.h3 ||
            typeof window.h3
                .cellToBoundary
                !== 'function'
        ) {
            clearLayers();

            showStatus(
                'Cell coverage renderer could not load.'
            );

            return;
        }

        const url =
            buildRequestUrl();

        if (!url) {
            return;
        }

        requestController
            ?.abort();

        requestController =
            new AbortController();

        const thisRequest =
            ++requestNumber;

        showStatus(
            'Loading cell coverage...'
        );

        try {
            const response =
                await fetch(
                    url,
                    {
                        credentials:
                            'same-origin',

                        cache:
                            'no-store',

                        signal:
                            requestController
                                .signal
                    }
                );

            const data =
                await response.json();

            if (
                thisRequest
                !== requestNumber
            ) {
                return;
            }

            if (
                !response.ok
                || data?.ok !== true
            ) {
                throw new Error(
                    data?.error
                    || 'Unable to load cell coverage.'
                );
            }

            if (
                data?.too_broad
            ) {
                clearLayers();

                showStatus(
                    'Zoom in a little farther to load cell coverage.'
                );

                return;
            }

            if (
                data?.data_ready
                === false
            ) {
                clearLayers();

                showStatus(
                    data?.message
                    || 'Cell coverage data is not ready yet.'
                );

                return;
            }

            activeProviders
                .forEach((key) => {
                    renderProvider(
                        key,
                        data?.coverage?.[key]
                        || []
                    );
                });

            Object.keys(
                coverageLayers
            ).forEach((key) => {
                if (
                    !activeProviders
                        .includes(key)
                ) {
                    clearProviderLayer(
                        key
                    );
                }
            });

            if (data?.truncated) {
                showStatus(
                    'Coverage is dense here. Zoom in for the complete view.'
                );

                return;
            }

            const total =
                activeProviders.reduce(
                    (sum, key) =>
                        sum
                        + (
                            data?.coverage?.[key]
                                ?.length
                            || 0
                        ),
                    0
                );

            if (!total) {
                showStatus(
                    'No matching FCC coverage is loaded for this view.'
                );

                return;
            }

            showStatus('');

        } catch (error) {
            if (
                error?.name
                === 'AbortError'
            ) {
                return;
            }

            clearLayers();

            showStatus(
                'Cell coverage is temporarily unavailable.'
            );

            console.warn(
                'Llama Scout cell coverage:',
                error
            );
        }
    }


    function scheduleRefresh(
        delay = 260
    ) {
        if (
            refreshTimer
            !== null
        ) {
            window.clearTimeout(
                refreshTimer
            );
        }

        refreshTimer =
            window.setTimeout(
                () => {
                    refreshTimer =
                        null;

                    refreshCoverage();
                },
                delay
            );
    }


    function changed() {
        savePreferences();
        syncControls();
        scheduleRefresh(0);
    }


    function getState() {
        return {
            providers:
                {
                    ...state.providers
                },

            technology:
                state.technology,

            environment:
                state.environment
        };
    }


    ensureStyles();
    loadPreferences();
    createCountNode();
    createControls();

    map.on(
        'moveend',
        () => {
            scheduleRefresh();
        }
    );

    map.on(
        'zoomend',
        () => {
            scheduleRefresh();
        }
    );

    window.LlamaScoutCellCoverage = {
        getState,
        refresh:
            refreshCoverage
    };

    scheduleRefresh(0);
})();
