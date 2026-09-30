(() => {
    'use strict';

    const mapElement =
        document.getElementById('llama-map');

    if (
        !mapElement
        || typeof L === 'undefined'
    ) {
        return;
    }

    const mapCard =
        mapElement.closest('.map-card');

    const latitude =
        Number(
            mapElement.dataset.placeLatitude
        );

    const longitude =
        Number(
            mapElement.dataset.placeLongitude
        );

    const placeName =
        String(
            mapElement.dataset.placeName
            || 'Place'
        ).trim();

    const maxZoom =
        Math.max(
            1,
            Math.min(
                20,
                Number(
                    mapElement.dataset.placeMaxZoom
                    || 11
                )
            )
        );

    const hasLayerAccess =
        mapCard?.dataset.mapMember === '1';

    const hasExactCoordinates =
        mapElement.dataset.placeExact === '1';

    const scopedPlaceAccess =
        mapCard?.dataset.mapScoped === '1';

    if (
        !Number.isFinite(latitude)
        || !Number.isFinite(longitude)
    ) {
        return;
    }

    const map =
        L.map(
            mapElement,
            {
                maxZoom,
                zoomControl: false
            }
        ).setView(
            [latitude, longitude],
            maxZoom
        );

    /*
     * The existing land/weather controls expect the full map's public global.
     * The Place page provides the same tiny interface.
     */
    window.LlamaScoutMap =
        Object.freeze({
            map,
            mapElement
        });

    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    /*
     * A Free Member with Complete Access only to this contributed Place can
     * inspect the surrounding area without turning this compact Place map into
     * a substitute for the full member map. The server independently enforces
     * the same idea for FCC cell requests.
     */
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
                    mapCard?.dataset.mapLightTile
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
                    mapCard?.dataset.mapDarkTile
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

    const systemPrefersDark = () =>
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

            if (selection === 'auto') {
                const automatic =
                    resolvedTheme() === 'dark'
                        ? 'dark'
                        : 'light';

                return tileSources[automatic]?.url
                    ? automatic
                    : 'street';
            }

            if (
                (
                    selection === 'light'
                    || selection === 'dark'
                )
                && !tileSources[selection]?.url
            ) {
                return 'street';
            }

            return tileSources[selection]
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
                    button.dataset.mapLayer
                    === selectedLayer;

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

        activeTileLayer.addTo(map);
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
                        button.dataset.mapLayer
                        || 'auto';

                    applyTileLayer();
                }
            );
        });

    const themeObserver =
        new MutationObserver(() => {
            if (
                hasLayerAccess
                && selectedLayer === 'auto'
            ) {
                applyTileLayer();
            }
        });

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
                && selectedLayer === 'auto'
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
            [latitude, longitude],
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

    marker.bindPopup(popup);
    marker.addTo(map);

    /*
     * A deferred invalidate keeps Leaflet sized correctly when the Place page
     * finishes laying out fonts and responsive cards around the compact map.
     */
    window.requestAnimationFrame(
        () => {
            map.invalidateSize(false);
        }
    );
})();
