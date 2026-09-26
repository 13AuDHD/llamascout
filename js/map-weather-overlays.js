(() => {
    'use strict';

    const MAP_GLOBAL = window.LlamaScoutMap;
    const map = MAP_GLOBAL?.map;
    const mapElement = MAP_GLOBAL?.mapElement || MAP_GLOBAL?.element;
    const mapCard = mapElement?.closest('.map-card');
    const panel = document.getElementById('map-tools-panel-weather');
    const trigger = document.querySelector('[data-map-tool="weather"]');

    if (!map || !mapElement || !mapCard || !panel || !trigger || typeof L === 'undefined') {
        return;
    }

    if (mapCard.dataset.mapMember !== '1') {
        return;
    }

    const STYLE_HREF = '/css/map-weather-overlays.css?v=20260926-2';
    const STORAGE_KEY = 'llama-map-weather-overlays';

    const sources = {
        radar: {
            label: 'Radar',
            detail: 'MRMS base reflectivity',
            url: 'https://nowcoast.noaa.gov/geoserver/observations/weather_radar/ows',
            layers: 'conus_base_reflectivity_mosaic',
            styles: 'weather_radar_base_reflectivity',
            opacity: 0.68,
            pane: 'llama-weather-radar-pane',
            zIndex: 335,
            refreshMs: 4 * 60 * 1000
        },

        clouds: {
            label: 'Clouds',
            detail: 'GOES longwave infrared',
            url: 'https://nowcoast.noaa.gov/geoserver/observations/satellite/ows',
            layers: 'goes_longwave_imagery',
            styles: '',
            opacity: 0.48,
            pane: 'llama-weather-clouds-pane',
            zIndex: 325,
            refreshMs: 5 * 60 * 1000
        },

        lightning: {
            label: 'Lightning',
            detail: '15-minute strike density',
            url: 'https://nowcoast.noaa.gov/geoserver/observations/lightning_detection/ows',
            layers: 'ldn_lightning_strike_density',
            styles: 'lightning_density',
            opacity: 0.76,
            pane: 'llama-weather-lightning-pane',
            zIndex: 340,
            refreshMs: 10 * 60 * 1000
        },

        alerts: {
            label: 'Active Alerts',
            detail: 'Watches, warnings & advisories',
            url: 'https://nowcoast.noaa.gov/geoserver/ows',
            layers: 'alerts:watches_warnings_advisories',
            styles: '',
            opacity: 0.58,
            pane: 'llama-weather-alerts-pane',
            zIndex: 345,
            refreshMs: 2 * 60 * 1000
        }
    };

    const state = {};

    Object.keys(sources).forEach((key) => {
        state[key] = {
            enabled: false,
            layer: null,
            loading: false,
            error: false,
            refreshTimer: null
        };
    });

    let statusNode = null;
    let countNode = null;
    let alertRequestController = null;
    let alertRequestNumber = 0;

    

    function ensureStyles() {
        if (
            document.querySelector(
                'link[data-map-weather-overlays-style]'
            )
        ) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = STYLE_HREF;
        link.dataset.mapWeatherOverlaysStyle = '1';
        document.head.appendChild(link);
    }


    function ensurePanes() {
        Object.values(sources).forEach((source) => {
            if (!map.getPane(source.pane)) {
                map.createPane(source.pane);
            }

            const pane = map.getPane(source.pane);

            if (pane) {
                pane.style.zIndex = String(source.zIndex);
                pane.style.pointerEvents = 'none';
            }
        });
    }


    function loadPreferences() {
        try {
            const saved =
                JSON.parse(
                    window.localStorage.getItem(STORAGE_KEY)
                    || 'null'
                );

            if (
                !saved ||
                typeof saved !== 'object'
            ) {
                return;
            }

            Object.keys(state).forEach((key) => {
                if (
                    typeof saved[key]
                    === 'boolean'
                ) {
                    state[key].enabled =
                        saved[key];
                }
            });

        } catch (error) {
            // Local storage is optional.
        }
    }


    function savePreferences() {
        const payload = {};

        Object.keys(state).forEach((key) => {
            payload[key] =
                state[key].enabled;
        });

        try {
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(payload)
            );
        } catch (error) {
            // The map still works if storage is unavailable.
        }
    }


    function escapeHtml(value) {
        const node =
            document.createElement('div');

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    }


    function createCountNode() {
        countNode =
            document.getElementById(
                'map-tool-weather-count'
            );

        if (countNode) {
            return;
        }

        countNode =
            document.createElement('span');

        countNode.id =
            'map-tool-weather-count';

        countNode.className =
            'map-tool-count';

        countNode.hidden = true;
        countNode.textContent = '0';

        trigger.appendChild(countNode);
    }


    function createControls() {
        panel.innerHTML = `
            <p class="map-tools-panel-title">
                Weather layers
            </p>

            <div
                class="map-weather-buttons"
                role="group"
                aria-label="Weather overlays"
            >
                ${Object.entries(sources)
                    .map(([key, source]) => `
                        <button
                            type="button"
                            data-weather-layer="${key}"
                            aria-pressed="false"
                        >
                            <span
                                class="map-weather-swatch map-weather-swatch-${key}"
                                aria-hidden="true"
                            ></span>

                            <span class="map-weather-button-copy">
                                <strong>
                                    ${escapeHtml(source.label)}
                                </strong>

                                <small>
                                    ${escapeHtml(source.detail)}
                                </small>
                            </span>
                        </button>
                    `)
                    .join('')}
            </div>

            <p class="map-weather-source">
                Weather data from NOAA nowCOAST.
            </p>

            <span
                id="map-weather-status"
                class="map-weather-status"
                role="status"
                aria-live="polite"
                hidden
            ></span>
        `;

        statusNode =
            panel.querySelector(
                '#map-weather-status'
            );

        panel
            .querySelectorAll(
                '[data-weather-layer]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const key =
                            button.dataset
                                .weatherLayer;

                        if (
                            !key ||
                            !state[key]
                        ) {
                            return;
                        }

                        setEnabled(
                            key,
                            !state[key].enabled
                        );
                    }
                );
            });

        syncButtons();
    }


    function createLayer(key) {
        const source =
            sources[key];

        const layer =
            L.tileLayer.wms(
                source.url,
                {
                    layers:
                        source.layers,

                    styles:
                        source.styles,

                    format:
                        'image/png',

                    transparent:
                        true,

                    version:
                        '1.1.1',

                    opacity:
                        source.opacity,

                    pane:
                        source.pane,

                    attribution:
                        'NOAA nowCOAST',

                    updateWhenIdle:
                        true,

                    keepBuffer:
                        2
                }
            );

        layer.on('loading', () => {
            state[key].loading = true;
            state[key].error = false;
            updateStatus();
        });

        layer.on('load', () => {
            state[key].loading = false;
            state[key].error = false;
            updateStatus();
        });

        layer.on('tileerror', () => {
            state[key].loading = false;
            state[key].error = true;
            updateStatus();
        });

        return layer;
    }


    function refreshLayer(key) {
        const sourceState =
            state[key];

        if (
            !sourceState.enabled ||
            !sourceState.layer
        ) {
            return;
        }

        sourceState.layer.setParams(
            {
                llama_refresh:
                    Date.now()
            },
            false
        );

        sourceState.layer.redraw();
    }


    function clearRefreshTimer(key) {
        if (
            state[key].refreshTimer
            !== null
        ) {
            window.clearInterval(
                state[key].refreshTimer
            );

            state[key].refreshTimer =
                null;
        }
    }


    function startRefreshTimer(key) {
        clearRefreshTimer(key);

        state[key].refreshTimer =
            window.setInterval(
                () => {
                    refreshLayer(key);
                },
                sources[key].refreshMs
            );
    }


    function showLayer(key) {
        const sourceState =
            state[key];

        if (!sourceState.layer) {
            sourceState.layer =
                createLayer(key);
        }

        if (
            !map.hasLayer(
                sourceState.layer
            )
        ) {
            sourceState.layer.addTo(map);
        }

        refreshLayer(key);
        startRefreshTimer(key);
    }


    function hideLayer(key) {
        const sourceState =
            state[key];

        clearRefreshTimer(key);

        sourceState.loading = false;
        sourceState.error = false;

        if (
            sourceState.layer &&
            map.hasLayer(
                sourceState.layer
            )
        ) {
            map.removeLayer(
                sourceState.layer
            );
        }
    }


    function setEnabled(
        key,
        enabled,
        save = true
    ) {
        state[key].enabled =
            enabled === true;

        if (state[key].enabled) {
            showLayer(key);
        } else {
            hideLayer(key);
        }

        if (save) {
            savePreferences();
        }

        syncButtons();
        updateStatus();
    }


    function enabledKeys() {
        return Object.keys(state)
            .filter(
                (key) =>
                    state[key].enabled
            );
    }


    function syncButtons() {
        panel
            .querySelectorAll(
                '[data-weather-layer]'
            )
            .forEach((button) => {
                const key =
                    button.dataset
                        .weatherLayer;

                const active =
                    Boolean(
                        key &&
                        state[key]?.enabled
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

        const enabled =
            enabledKeys().length;

        if (countNode) {
            countNode.textContent =
                String(enabled);

            countNode.hidden =
                enabled === 0;
        }

        trigger.classList.toggle(
            'has-active',
            enabled > 0
        );
    }


    function updateStatus() {
        if (!statusNode) {
            return;
        }

        const enabled =
            enabledKeys();

        if (!enabled.length) {
            statusNode.hidden = true;
            statusNode.textContent = '';
            return;
        }

        const loading =
            enabled.filter(
                (key) =>
                    state[key].loading
            );

        if (loading.length) {
            statusNode.hidden = false;

            statusNode.textContent =
                loading.length === 1
                    ? `Loading ${sources[loading[0]].label.toLowerCase()}...`
                    : 'Loading weather layers...';

            return;
        }

        const failed =
            enabled.filter(
                (key) =>
                    state[key].error
            );

        if (failed.length) {
            statusNode.hidden = false;

            statusNode.textContent =
                failed.length === 1
                    ? `${sources[failed[0]].label} is temporarily unavailable.`
                    : 'One or more weather layers are temporarily unavailable.';

            return;
        }

        statusNode.hidden = true;
        statusNode.textContent = '';
    }


    function restoreEnabledLayers() {
        Object.keys(state)
            .forEach((key) => {
                setEnabled(
                    key,
                    state[key].enabled,
                    false
                );
            });
    }


    function handleVisibilityChange() {
        if (document.hidden) {
            return;
        }

        enabledKeys()
            .forEach((key) => {
                refreshLayer(key);
            });
    }

    function formatAlertTime(
    value
) {
    if (!value) {
        return '';
    }

    const date =
        new Date(value);

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return '';
    }

    return new Intl.DateTimeFormat(
        undefined,
        {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZoneName: 'short'
        }
    ).format(date);
}


function shortenedText(
    value,
    maxLength = 240
) {
    const text =
        String(value ?? '')
            .trim()
            .replace(/\s+/g, ' ');

    if (
        text.length <= maxLength
    ) {
        return text;
    }

    return (
        text.slice(
            0,
            maxLength - 1
        ).trim()
        + '…'
    );
}


function alertPopupHtml(
    features
) {
    const alerts =
        features.slice(0, 4);

    return `
        <article
            class="
                map-overlay-popup
                map-weather-alert-popup
            "
        >

            <button
                type="button"
                class="map-overlay-popup-close"
                data-map-overlay-popup-close
                aria-label="Close weather alert details"
            >
                ×
            </button>

            <p class="map-overlay-popup-eyebrow">
                National Weather Service
            </p>

            <h3>
                ${
                    alerts.length === 1
                        ? 'Active Weather Alert'
                        : `${alerts.length} Active Weather Alerts`
                }
            </h3>

            ${alerts
                .map((feature) => {
                    const props =
                        feature?.properties
                        || {};

                    const event =
                        props.event
                        || 'Weather Alert';

                    const headline =
                        props.headline
                        || '';

                    const severity = [
                        props.severity,
                        props.urgency,
                        props.certainty
                    ]
                        .filter(Boolean)
                        .join(' · ');

                    const expires =
                        formatAlertTime(
                            props.ends
                            || props.expires
                        );

                    const area =
                        props.areaDesc
                        || '';

                    const instruction =
                        shortenedText(
                            props.instruction
                            || props.description
                        );

                    return `
                        <section
                            class="map-weather-alert-item"
                        >

                            <strong
                                class="map-weather-alert-event"
                            >
                                ${escapeHtml(event)}
                            </strong>

                            ${
                                headline
                                    ? `
                                        <p
                                            class="map-overlay-popup-headline"
                                        >
                                            ${escapeHtml(headline)}
                                        </p>
                                    `
                                    : ''
                            }

                            <div
                                class="map-overlay-popup-meta"
                            >

                                ${
                                    severity
                                        ? `
                                            <div
                                                class="map-overlay-popup-row"
                                            >
                                                <strong>
                                                    Status
                                                </strong>

                                                <span>
                                                    ${escapeHtml(severity)}
                                                </span>
                                            </div>
                                        `
                                        : ''
                                }

                                ${
                                    expires
                                        ? `
                                            <div
                                                class="map-overlay-popup-row"
                                            >
                                                <strong>
                                                    Until
                                                </strong>

                                                <span>
                                                    ${escapeHtml(expires)}
                                                </span>
                                            </div>
                                        `
                                        : ''
                                }

                                ${
                                    area
                                        ? `
                                            <div
                                                class="map-overlay-popup-row"
                                            >
                                                <strong>
                                                    Area
                                                </strong>

                                                <span>
                                                    ${escapeHtml(area)}
                                                </span>
                                            </div>
                                        `
                                        : ''
                                }

                            </div>

                            ${
                                instruction
                                    ? `
                                        <p
                                            class="map-overlay-popup-note"
                                        >
                                            ${escapeHtml(instruction)}
                                        </p>
                                    `
                                    : ''
                            }

                        </section>
                    `;
                })
                .join('')}

            ${
                features.length > alerts.length
                    ? `
                        <p
                            class="map-overlay-popup-note"
                        >
                            ${
                                features.length
                                - alerts.length
                            }
                            additional alert(s)
                            also apply here.
                        </p>
                    `
                    : ''
            }

            <p class="map-overlay-popup-source">
                Alert details:
                National Weather Service
            </p>

        </article>
    `;
}


async function showAlertsAtPoint(
    latlng
) {
    if (
        !state.alerts?.enabled
    ) {
        return;
    }

    alertRequestController
        ?.abort();

    alertRequestController =
        new AbortController();

    const requestNumber =
        ++alertRequestNumber;

    const latitude =
        Number(
            latlng.lat
        ).toFixed(4);

    const longitude =
        Number(
            latlng.lng
        ).toFixed(4);

    const url =
        'https://api.weather.gov/alerts/active'
        + '?point='
        + encodeURIComponent(
            `${latitude},${longitude}`
        );

    try {
        const response =
            await fetch(
                url,
                {
                    method: 'GET',

                    headers: {
                        Accept:
                            'application/geo+json'
                    },

                    cache:
                        'no-store',

                    signal:
                        alertRequestController
                            .signal
                }
            );

        if (!response.ok) {
            throw new Error(
                `NWS alerts returned HTTP ${response.status}.`
            );
        }

        const data =
            await response.json();

        if (
            requestNumber
            !== alertRequestNumber
        ) {
            return;
        }

        const features =
            Array.isArray(
                data?.features
            )
                ? data.features
                : [];

        if (!features.length) {
            return;
        }

        L.popup(
            {
                maxWidth: 300,
                closeButton: false,
                className:
                    'map-overlay-leaflet-popup'
            }
        )
            .setLatLng(
                latlng
            )
            .setContent(
                alertPopupHtml(
                    features
                )
            )
            .openOn(map);

    } catch (error) {
        if (
            error?.name
            !== 'AbortError'
        ) {
            console.warn(
                'Llama Scout NWS alert details:',
                error
            );
        }
    }
}


map.on(
    'click',
    (event) => {
        if (
            state.alerts?.enabled
        ) {
            showAlertsAtPoint(
                event.latlng
            );
        }
    }
);

    ensureStyles();
    ensurePanes();
    loadPreferences();
    createCountNode();
    createControls();
    restoreEnabledLayers();

    document.addEventListener(
        'visibilitychange',
        handleVisibilityChange
    );
})();
