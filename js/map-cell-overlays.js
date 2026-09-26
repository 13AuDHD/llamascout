(() => {
    'use strict';

    const MAP_GLOBAL = window.LlamaScoutMap;
    const map = MAP_GLOBAL?.map;
    const mapElement = MAP_GLOBAL?.mapElement || MAP_GLOBAL?.element;
    const mapCard = mapElement?.closest('.map-card');
    const panel = document.getElementById('map-tools-panel-cell');
    const trigger = document.querySelector('[data-map-tool="cell"]');

    if (
        !map ||
        !mapElement ||
        !mapCard ||
        !panel ||
        !trigger
    ) {
        return;
    }

    if (mapCard.dataset.mapMember !== '1') {
        return;
    }

    const STYLE_HREF =
        '/css/map-cell-overlays.css?v=20260926-1';

    const STORAGE_KEY =
        'llama-map-cell-coverage';

    const providers = {
        tmobile: {
            label: 'T-Mobile',
            providerId: 130403
        },

        verizon: {
            label: 'Verizon',
            providerId: 131425
        },

        att: {
            label: 'AT&T',
            providerId: 130077
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
        structuredClone
            ? structuredClone(defaults)
            : JSON.parse(JSON.stringify(defaults));

    let countNode = null;


    function ensureStyles() {
        if (
            document.querySelector(
                'link[data-map-cell-overlays-style]'
            )
        ) {
            return;
        }

        const link =
            document.createElement('link');

        link.rel = 'stylesheet';
        link.href = STYLE_HREF;
        link.dataset.mapCellOverlaysStyle = '1';

        document.head.appendChild(link);
    }


    function loadPreferences() {
        try {
            const saved =
                JSON.parse(
                    window.localStorage.getItem(
                        STORAGE_KEY
                    ) || 'null'
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
                        typeof saved.providers?.[key]
                        === 'boolean'
                    ) {
                        state.providers[key] =
                            saved.providers[key];
                    }
                });

            if (
                saved.technology === '4g' ||
                saved.technology === '5g'
            ) {
                state.technology =
                    saved.technology;
            }

            if (
                saved.environment === 'vehicle' ||
                saved.environment === 'outdoors'
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
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(state)
            );
        } catch (error) {
            // The controls still work if storage is unavailable.
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
            document.createElement('span');

        countNode.id =
            'map-tool-cell-count';

        countNode.className =
            'map-tool-count';

        countNode.hidden = true;
        countNode.textContent = '0';

        trigger.appendChild(countNode);
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

                                ${escapeHtml(provider.label)}
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
                Coverage data: FCC National Broadband Map
            </p>
        `;

        panel
            .querySelectorAll(
                '[data-cell-provider]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const key =
                            button.dataset.cellProvider;

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
                            button.dataset.cellTechnology;

                        if (
                            value !== '4g' &&
                            value !== '5g'
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
                            button.dataset.cellEnvironment;

                        if (
                            value !== 'vehicle' &&
                            value !== 'outdoors'
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


    function escapeHtml(value) {
        const node =
            document.createElement('div');

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    }


    function activeProviderKeys() {
        return Object.keys(providers)
            .filter(
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
                    button.dataset.cellProvider;

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
                    button.dataset.cellTechnology
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
                    button.dataset.cellEnvironment
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


    function requestFilters() {
        const technology =
            state.technology === '5g'
                ? {
                    code: 500,
                    minimumDownload: 7,
                    minimumUpload: 1
                }
                : {
                    code: 400,
                    minimumDownload: 5,
                    minimumUpload: 1
                };

        return {
            providers:
                activeProviderKeys()
                    .map((key) => ({
                        key,
                        label:
                            providers[key].label,
                        providerId:
                            providers[key].providerId
                    })),

            technology,

            environment:
                state.environment === 'vehicle'
                    ? {
                        key: 'vehicle',
                        fccValues: [1]
                    }
                    : {
                        key: 'outdoors',
                        fccValues: [0, 1]
                    }
        };
    }


    function dispatchChange() {
        document.dispatchEvent(
            new CustomEvent(
                'llama:cell-coverage-change',
                {
                    detail:
                        requestFilters()
                }
            )
        );
    }


    function changed() {
        savePreferences();
        syncControls();
        dispatchChange();
    }


    function getState() {
        return {
            providers:
                { ...state.providers },

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

    window.LlamaScoutCellCoverage = {
        getState,
        getRequestFilters:
            requestFilters
    };

    dispatchChange();
})();
