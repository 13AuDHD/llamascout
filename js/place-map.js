(() => {
    'use strict';

    const mapElement =
        document.getElementById(
            'llama-map'
        );

    if (
        !mapElement
        || typeof L === 'undefined'
    ) {
        return;
    }

    const mapCard =
        mapElement.closest(
            '.map-card'
        );

    const latitude =
        Number(
            mapElement.dataset
                .placeLatitude
        );

    const longitude =
        Number(
            mapElement.dataset
                .placeLongitude
        );

    const placeName =
        String(
            mapElement.dataset
                .placeName
            || 'Place'
        ).trim();

    const maxZoom =
        Math.max(
            1,
            Math.min(
                20,
                Number(
                    mapElement.dataset
                        .placeMaxZoom
                    || 11
                )
            )
        );

    const hasLayerAccess =
        mapCard?.dataset
            .mapMember === '1';

    const hasExactCoordinates =
        mapElement.dataset
            .placeExact === '1';

    const scopedPlaceAccess =
        mapCard?.dataset
            .mapScoped === '1';

    const mappedAreasApi =
        String(
            mapCard?.dataset
                .placeMapFeaturesApi
            || ''
        ).trim();

    if (
        !Number.isFinite(latitude)
        || !Number.isFinite(longitude)
    ) {
        return;
    }

    const initialZoom =
        Math.min(
            15,
            maxZoom
        );

    const map =
        L.map(
            mapElement,
            {
                maxZoom,
                zoomControl: false
            }
        ).setView(
            [
                latitude,
                longitude
            ],
            initialZoom
        );

    window.LlamaScoutMap =
        Object.freeze({
            map,
            mapElement
        });

    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    if (
        scopedPlaceAccess
        && longitude > -178.5
        && longitude < 178.5
    ) {
        map.setMaxBounds(
            L.latLngBounds(
                [
                    latitude - 1.25,
                    longitude - 1.25
                ],
                [
                    latitude + 1.25,
                    longitude + 1.25
                ]
            )
        );

        map.options.maxBoundsViscosity =
            0.82;
    }

    const tileSources = {
        public: {
            url:
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

            options: {
                maxNativeZoom: 19,
                maxZoom: 11,

                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }
        },

        light: {
            url:
                String(
                    mapCard?.dataset
                        .mapLightTile
                    || ''
                ),

            options: {
                maxNativeZoom: 20,
                maxZoom: 20,

                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://www.geoapify.com/">Geoapify</a>'
            }
        },

        street: {
            url:
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

            options: {
                maxNativeZoom: 19,
                maxZoom: 20,

                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }
        },

        terrain: {
            url:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',

            options: {
                maxNativeZoom: 19,
                maxZoom: 20,

                attribution:
                    'Tiles &copy; Esri and contributors'
            }
        },

        topo: {
            url:
                'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',

            options: {
                maxNativeZoom: 17,
                maxZoom: 20,

                attribution:
                    'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, SRTM | Map style &copy; <a href="https://opentopomap.org">OpenTopoMap</a>'
            }
        },

        dark: {
            url:
                String(
                    mapCard?.dataset
                        .mapDarkTile
                    || ''
                ),

            options: {
                maxNativeZoom: 20,
                maxZoom: 20,

                attribution:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://www.geoapify.com/">Geoapify</a>'
            }
        },

        satellite: {
            url:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',

            options: {
                maxNativeZoom: 19,
                maxZoom: 20,

                attribution:
                    'Tiles &copy; Esri and imagery contributors'
            }
        }
    };

    let selectedLayer = 'auto';
    let activeTileLayer = null;

    const getStoredTheme = () => {
        try {
            return (
                localStorage.getItem(
                    'llama-theme'
                )
                || 'system'
            );
        } catch (error) {
            return 'system';
        }
    };

    const systemPrefersDark =
        () =>
            window.matchMedia?.(
                '(prefers-color-scheme: dark)'
            ).matches === true;

    const resolvedTheme = () => {
        const stored =
            getStoredTheme();

        if (
            stored === 'dark'
            || stored === 'light'
        ) {
            return stored;
        }

        const documentTheme =
            document.documentElement
                .dataset.theme;

        if (
            documentTheme === 'dark'
            || documentTheme === 'light'
        ) {
            return documentTheme;
        }

        return systemPrefersDark()
            ? 'dark'
            : 'light';
    };

    const layerKeyForSelection =
        (selection) => {
            if (!hasLayerAccess) {
                return 'public';
            }

            if (
                selection === 'auto'
            ) {
                const automatic =
                    resolvedTheme()
                    === 'dark'
                        ? 'dark'
                        : 'light';

                return tileSources[
                    automatic
                ]?.url
                    ? automatic
                    : 'street';
            }

            if (
                (
                    selection === 'light'
                    || selection === 'dark'
                )
                && !tileSources[
                    selection
                ]?.url
            ) {
                return 'street';
            }

            return tileSources[
                selection
            ]
                ? selection
                : 'street';
        };

    const setActiveLayerButton = () => {
        mapCard
            ?.querySelectorAll(
                '[data-map-layer]'
            )
            .forEach((button) => {
                const active =
                    button.dataset
                        .mapLayer
                    === selectedLayer;

                button.classList
                    .toggle(
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
    };

    const applyTileLayer = () => {
        const key =
            layerKeyForSelection(
                selectedLayer
            );

        const source =
            tileSources[key];

        if (
            !source
            || !source.url
        ) {
            return;
        }

        if (activeTileLayer) {
            map.removeLayer(
                activeTileLayer
            );
        }

        activeTileLayer =
            L.tileLayer(
                source.url,
                {
                    ...source.options,
                    maxZoom
                }
            );

        activeTileLayer
            .addTo(map);

        setActiveLayerButton();
    };

    applyTileLayer();

    mapCard
        ?.querySelectorAll(
            '[data-map-layer]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    if (!hasLayerAccess) {
                        return;
                    }

                    selectedLayer =
                        button.dataset
                            .mapLayer
                        || 'auto';

                    applyTileLayer();
                }
            );
        });

    const themeObserver =
        new MutationObserver(
            () => {
                if (
                    hasLayerAccess
                    && selectedLayer
                        === 'auto'
                ) {
                    applyTileLayer();
                }
            }
        );

    themeObserver.observe(
        document.documentElement,
        {
            attributes: true,

            attributeFilter: [
                'data-theme'
            ]
        }
    );

    const colorSchemeQuery =
        window.matchMedia?.(
            '(prefers-color-scheme: dark)'
        );

    const handleSystemThemeChange =
        () => {
            if (
                hasLayerAccess
                && selectedLayer
                    === 'auto'
                && getStoredTheme()
                    === 'system'
            ) {
                applyTileLayer();
            }
        };

    if (
        colorSchemeQuery
            ?.addEventListener
    ) {
        colorSchemeQuery
            .addEventListener(
                'change',
                handleSystemThemeChange
            );

    } else if (
        colorSchemeQuery
            ?.addListener
    ) {
        colorSchemeQuery
            .addListener(
                handleSystemThemeChange
            );
    }

    const marker =
        L.marker(
            [
                latitude,
                longitude
            ],
            {
                icon:
                    L.divIcon({
                        className:
                            'place-map-marker-shell',

                        html:
                            '<span class="place-map-marker" aria-hidden="true"></span>',

                        iconSize:
                            [36, 46],

                        iconAnchor:
                            [18, 44],

                        popupAnchor:
                            [0, -42]
                    })
            }
        );

    const popup =
        document.createElement(
            'div'
        );

    popup.className =
        'place-map-popup';

    const strong =
        document.createElement(
            'strong'
        );

    strong.textContent =
        placeName;

    const detail =
        document.createElement(
            'span'
        );

    detail.textContent =
        hasExactCoordinates
            ? 'Exact Place location'
            : 'Approximate public location';

    popup.append(
        strong,
        detail
    );

    marker.bindPopup(
        popup
    );

    marker.addTo(
        map
    );


    /* =====================================================
       MAPPED AREAS
       ===================================================== */

    const featureTypeLabels = {
        place_boundary:
            'Place boundary',

        camping_area:
            'Camping area',

        parking_area:
            'Parking area'
    };

    const areaUseLabels = {
        developed_campground:
            'Developed campground',

        designated_camping:
            'Designated camping area',

        dispersed_camping:
            'Dispersed camping area',

        general_parking:
            'General parking',

        overnight_vehicle_parking:
            'Overnight vehicle parking'
    };

    const feeStatusLabels = {
        free: 'Free',
        paid: 'Paid',
        varies: 'Cost varies',
        unknown: 'Cost unknown'
    };

    const overnightStatusLabels = {
        allowed: 'Overnight vehicle stay allowed',
        prohibited: 'Overnight vehicle stay not allowed',
        varies: 'Overnight rules vary',
        unknown: 'Overnight status unknown'
    };

    const mappedAreaGroups = new Map();
    let mappedAreaAllBounds = null;

    const featureClass =
        (featureType) => {
            const normalized =
                String(
                    featureType
                    || ''
                )
                    .toLowerCase()
                    .replace(
                        /[^a-z0-9_-]+/g,
                        '-'
                    )
                    .replace(
                        /_/g,
                        '-'
                    );

            return [
                'place-map-feature',
                `place-map-feature--${normalized}`
            ].join(' ');
        };

    const createFeaturePopup =
        (feature) => {
            const wrapper =
                document.createElement(
                    'div'
                );

            wrapper.className =
                'place-map-feature-popup';

            const title =
                document.createElement(
                    'strong'
                );

            title.textContent =
                String(
                    feature.label
                    || featureTypeLabels[
                        feature.feature_type
                    ]
                    || 'Mapped area'
                );

            const type =
                document.createElement(
                    'span'
                );

            type.textContent =
                featureTypeLabels[
                    feature.feature_type
                ]
                || 'Mapped area';

            wrapper.append(
                title,
                type
            );

            const details =
                feature.area_details
                || {};

            [
                areaUseLabels[
                    details.area_use
                ] || '',
                feeStatusLabels[
                    details.fee_status
                ] || '',
                overnightStatusLabels[
                    details.overnight_status
                ] || ''
            ]
                .filter(Boolean)
                .forEach(
                    (label) => {
                        const detail =
                            document.createElement(
                                'span'
                            );

                        detail.textContent =
                            label;

                        wrapper.append(
                            detail
                        );
                    }
                );

            return wrapper;
        };

    const syncMappedAreaControls =
        (features) => {
            const legend =
                mapCard
                    ?.querySelector(
                        '[data-place-map-area-legend]'
                    );

            if (!legend) {
                return;
            }

            const types =
                new Set(
                    features.map(
                        (feature) =>
                            String(
                                feature.feature_type
                                || ''
                            )
                    )
                );

            let visibleCount = 0;

            legend
                .querySelectorAll(
                    '[data-map-feature-toggle]'
                )
                .forEach((button) => {
                    const type =
                        String(
                            button.dataset
                                .mapFeatureToggle
                            || ''
                        );

                    const visible =
                        types.has(type);

                    button.hidden =
                        !visible;

                    if (visible) {
                        button.setAttribute(
                            'aria-pressed',
                            'true'
                        );

                        visibleCount++;
                    }
                });

            const fitButton =
                legend.querySelector(
                    '[data-fit-mapped-areas]'
                );

            if (fitButton) {
                fitButton.hidden =
                    visibleCount === 0;
            }

            legend.hidden =
                visibleCount === 0;
        };

    const fitMappedAreas = () => {
        if (
            !mappedAreaAllBounds
            || !mappedAreaAllBounds.isValid()
        ) {
            return;
        }

        map.fitBounds(
            mappedAreaAllBounds,
            {
                padding:
                    [36, 36],

                maxZoom:
                    Math.min(
                        18,
                        maxZoom
                    )
            }
        );
    };

    const setMappedAreaTypeVisible =
        (
            type,
            visible
        ) => {
            const group =
                mappedAreaGroups.get(
                    type
                );

            if (!group) {
                return;
            }

            if (visible) {
                if (!map.hasLayer(group)) {
                    group.addTo(map);
                }
            } else if (map.hasLayer(group)) {
                map.removeLayer(group);
            }

            const button =
                mapCard
                    ?.querySelector(
                        `[data-map-feature-toggle="${CSS.escape(type)}"]`
                    );

            if (button) {
                button.setAttribute(
                    'aria-pressed',
                    visible
                        ? 'true'
                        : 'false'
                );
            }

            marker.bringToFront();
        };

    mapCard
        ?.querySelectorAll(
            '[data-map-feature-toggle]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const type =
                        String(
                            button.dataset
                                .mapFeatureToggle
                            || ''
                        );

                    const visible =
                        button.getAttribute(
                            'aria-pressed'
                        ) !== 'true';

                    setMappedAreaTypeVisible(
                        type,
                        visible
                    );
                }
            );
        });

    mapCard
        ?.querySelector(
            '[data-fit-mapped-areas]'
        )
        ?.addEventListener(
            'click',
            fitMappedAreas
        );

    const loadMappedAreas =
        async () => {
            if (
                !hasExactCoordinates
                || mappedAreasApi === ''
            ) {
                return;
            }

            try {
                const response =
                    await fetch(
                        mappedAreasApi,
                        {
                            credentials:
                                'same-origin',

                            headers: {
                                Accept:
                                    'application/json'
                            }
                        }
                    );

                if (!response.ok) {
                    return;
                }

                const payload =
                    await response.json();

                if (
                    !payload
                    || payload.ok !== true
                    || !Array.isArray(
                        payload.features
                    )
                ) {
                    return;
                }

                const features =
                    payload.features
                        .filter(
                            (feature) =>
                                feature
                                && feature.geometry
                        );

                syncMappedAreaControls(
                    features
                );

                if (
                    features.length === 0
                ) {
                    return;
                }

                mappedAreaAllBounds =
                    L.latLngBounds([]);

                features.forEach(
                    (feature) => {
                        const type =
                            String(
                                feature.feature_type
                                || ''
                            );

                        if (
                            !mappedAreaGroups.has(
                                type
                            )
                        ) {
                            mappedAreaGroups.set(
                                type,
                                L.featureGroup()
                            );
                        }

                        const group =
                            mappedAreaGroups.get(
                                type
                            );

                        const layer =
                            L.geoJSON(
                                feature.geometry,
                                {
                                    style: {
                                        className:
                                            featureClass(
                                                type
                                            )
                                    },

                                    onEachFeature:
                                        (
                                            geoFeature,
                                            featureLayer
                                        ) => {
                                            featureLayer
                                                .bindPopup(
                                                    createFeaturePopup(
                                                        feature
                                                    )
                                                );
                                        }
                                }
                            );

                        layer.addTo(
                            group
                        );

                        const bounds =
                            layer.getBounds();

                        if (
                            bounds.isValid()
                        ) {
                            mappedAreaAllBounds
                                .extend(bounds);
                        }
                    }
                );

                mappedAreaGroups
                    .forEach(
                        (group) => {
                            group.addTo(map);
                        }
                    );

                fitMappedAreas();
                marker.bringToFront();

            } catch (error) {
                console.error(
                    'Llama Scout mapped area error:',
                    error
                );
            }
        };

    loadMappedAreas();


    window.requestAnimationFrame(
        () => {
            map.invalidateSize(
                false
            );
        }
    );
})();
