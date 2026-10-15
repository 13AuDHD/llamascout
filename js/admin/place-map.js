(() => {
    'use strict';

    const panel =
        document.querySelector(
            '[data-admin-place-map-panel]'
        );

    const mapElement =
        panel?.querySelector(
            '[data-admin-place-map]'
        );

    const dataNode =
        panel?.querySelector(
            '[data-admin-place-map-data]'
        );

    if (
        !panel
        || !mapElement
        || !dataNode
        || typeof L === 'undefined'
    ) {
        return;
    }

    let config;

    try {
        config =
            JSON.parse(
                dataNode.textContent
                || '{}'
            );
    } catch (_) {
        return;
    }

    const place =
        config.place
        && typeof config.place === 'object'
            ? config.place
            : {};

    const features =
        Array.isArray(config.features)
            ? config.features
            : [];

    const selection =
        panel.querySelector(
            '[data-admin-place-map-selection]'
        );

    const editLink =
        panel.querySelector(
            '[data-admin-place-map-edit]'
        );

    const map =
        L.map(
            mapElement,
            {
                zoomControl:
                    false,

                maxZoom:
                    20
            }
        );

    L.control.zoom({
        position:
            'bottomright'
    }).addTo(
        map
    );

    const tileSources = {
        street: {
            url:
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

            options: {
                maxNativeZoom:
                    19,

                maxZoom:
                    20,

                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        },

        terrain: {
            url:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',

            options: {
                maxNativeZoom:
                    19,

                maxZoom:
                    20,

                attribution:
                    'Tiles &copy; Esri and contributors'
            }
        },

        satellite: {
            url:
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',

            options: {
                maxNativeZoom:
                    19,

                maxZoom:
                    20,

                attribution:
                    'Tiles &copy; Esri and imagery contributors'
            }
        }
    };

    let activeTileLayer = null;
    let mapStyle =
        'satellite';

    const applyMapStyle =
        (value) => {
            mapStyle =
                Object.hasOwn(
                    tileSources,
                    value
                )
                    ? value
                    : 'satellite';

            if (activeTileLayer) {
                map.removeLayer(
                    activeTileLayer
                );
            }

            const source =
                tileSources[
                    mapStyle
                ];

            activeTileLayer =
                L.tileLayer(
                    source.url,
                    source.options
                );

            activeTileLayer.addTo(
                map
            );

            activeTileLayer.bringToBack();

            panel
                .querySelectorAll(
                    '[data-admin-map-style]'
                )
                .forEach(
                    (button) => {
                        button
                            .classList
                            .toggle(
                                'is-active',
                                button.dataset.adminMapStyle
                                    === mapStyle
                            );
                    }
                );
        };

    panel
        .querySelectorAll(
            '[data-admin-map-style]'
        )
        .forEach(
            (button) => {
                button.addEventListener(
                    'click',
                    () => {
                        applyMapStyle(
                            button.dataset.adminMapStyle
                            || 'satellite'
                        );
                    }
                );
            }
        );

    map.createPane(
        'llama-place-boundary'
    );

    map.getPane(
        'llama-place-boundary'
    ).style.zIndex =
        '410';

    map.createPane(
        'llama-area'
    );

    map.getPane(
        'llama-area'
    ).style.zIndex =
        '420';

    map.createPane(
        'llama-site'
    );

    map.getPane(
        'llama-site'
    ).style.zIndex =
        '430';

    const styleFor =
        (
            type,
            active = false
        ) => {
            const palette = {
                place_boundary:
                    '#8b5cf6',

                camping_area:
                    '#1f8f4e',

                parking_area:
                    '#2563eb',

                camping_site:
                    '#d97706'
            };

            const color =
                palette[type]
                || '#64748b';

            return {
                color,
                fillColor:
                    color,

                weight:
                    active
                        ? 4
                        : 2,

                opacity:
                    active
                        ? 1
                        : .82,

                fillOpacity:
                    active
                        ? .28
                        : .13
            };
        };

    const paneFor =
        (type) => {
            if (type === 'camping_site') {
                return 'llama-site';
            }

            if (
                type === 'camping_area'
                || type === 'parking_area'
            ) {
                return 'llama-area';
            }

            return 'llama-place-boundary';
        };

    const layers =
        new Map();

    const allBounds =
        L.latLngBounds([]);

    features.forEach(
        (feature) => {
            const featureId =
                Number(
                    feature?.id
                    || 0
                );

            const type =
                String(
                    feature?.feature_type
                    || ''
                );

            if (
                featureId < 1
                || !feature?.geometry
            ) {
                return;
            }

            const layer =
                L.geoJSON(
                    feature.geometry,
                    {
                        pane:
                            paneFor(
                                type
                            ),

                        style:
                            styleFor(
                                type,
                                false
                            ),

                        bubblingMouseEvents:
                            false
                    }
                );

            layer.addTo(
                map
            );

            layer.eachLayer(
                (child) => {
                    child.options.bubblingMouseEvents =
                        false;

                    child.on(
                        'click',
                        () => {
                            window.dispatchEvent(
                                new CustomEvent(
                                    'llama:map-feature-selected',
                                    {
                                        detail: {
                                            map_feature_id:
                                                featureId
                                        }
                                    }
                                )
                            );
                        }
                    );
                }
            );

            const bounds =
                layer.getBounds();

            if (bounds.isValid()) {
                allBounds.extend(
                    bounds
                );
            }

            layers.set(
                featureId,
                {
                    feature,
                    layer
                }
            );
        }
    );

    let selectedFeatureId =
        0;

    const updateStyles =
        () => {
            layers.forEach(
                (
                    entry,
                    featureId
                ) => {
                    const type =
                        String(
                            entry.feature
                                ?.feature_type
                            || ''
                        );

                    entry.layer.setStyle(
                        styleFor(
                            type,
                            featureId
                                === selectedFeatureId
                        )
                    );
                }
            );
        };

    const fitSelection =
        (
            featureId,
            level
        ) => {
            const entry =
                layers.get(
                    featureId
                );

            if (!entry) {
                if (
                    level === 'place'
                    && allBounds.isValid()
                ) {
                    map.fitBounds(
                        allBounds,
                        {
                            padding:
                                [30, 30],

                            maxZoom:
                                19
                        }
                    );
                }

                return;
            }

            const bounds =
                entry.layer.getBounds();

            if (!bounds.isValid()) {
                return;
            }

            map.fitBounds(
                bounds,
                {
                    padding:
                        [34, 34],

                    maxZoom:
                        level === 'site'
                            ? 20
                            : 19
                }
            );
        };

    const updateSelectionLabel =
        (detail) => {
            if (!selection) {
                return;
            }

            const strong =
                selection.querySelector(
                    'strong'
                );

            const span =
                selection.querySelector(
                    'span'
                );

            if (strong) {
                strong.textContent =
                    String(
                        detail?.label
                        || place.name
                        || 'Place'
                    );
            }

            if (span) {
                const level =
                    String(
                        detail?.level
                        || 'place'
                    );

                span.textContent =
                    level === 'site'
                        ? 'Site'
                        : (
                            level === 'area'
                                ? 'Area'
                                : 'Place'
                        );
            }
        };

    const updateEditLink =
        (featureId) => {
            if (!editLink) {
                return;
            }

            const slug =
                String(
                    place.slug
                    || ''
                );

            const url =
                new URL(
                    'https://llamascout.com/place-map-editor.php'
                );

            url.searchParams.set(
                'slug',
                slug
            );

            if (featureId > 0) {
                url.searchParams.set(
                    'feature',
                    String(
                        featureId
                    )
                );
            }

            editLink.href =
                url.toString();
        };

    window.addEventListener(
        'llama:place-context-changed',
        (event) => {
            const detail =
                event?.detail;

            if (
                !detail
                || typeof detail !== 'object'
            ) {
                return;
            }

            selectedFeatureId =
                Math.max(
                    0,
                    Number(
                        detail.map_feature_id
                        || 0
                    )
                );

            updateStyles();

            updateSelectionLabel(
                detail
            );

            updateEditLink(
                selectedFeatureId
            );

            fitSelection(
                selectedFeatureId,
                String(
                    detail.level
                    || 'place'
                )
            );
        }
    );

    applyMapStyle(
        mapStyle
    );

    if (allBounds.isValid()) {
        map.fitBounds(
            allBounds,
            {
                padding:
                    [30, 30],

                maxZoom:
                    19
            }
        );
    } else {
        const latitude =
            Number(
                place.latitude
            );

        const longitude =
            Number(
                place.longitude
            );

        if (
            Number.isFinite(
                latitude
            )
            && Number.isFinite(
                longitude
            )
        ) {
            map.setView(
                [
                    latitude,
                    longitude
                ],
                18
            );
        } else {
            map.setView(
                [
                    0,
                    0
                ],
                2
            );
        }
    }

    window.requestAnimationFrame(
        () => {
            map.invalidateSize(
                false
            );
        }
    );
})();
