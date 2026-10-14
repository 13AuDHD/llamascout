(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor
        = window.LlamaPlaceMapEditor
        || {};

    namespace.createMapStyles =
        ({
            editor,
            map,
            leaflet
        }) => {
            if (
                !editor
                || !map
                || !leaflet
            ) {
                throw new Error(
                    'Mapped-area map styles require the editor, map, and Leaflet.'
                );
            }

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
                            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
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
            let selectedMapStyle =
                'satellite';

            const applyTileLayer =
                () => {
                    const source =
                        tileSources[
                            selectedMapStyle
                        ]
                        || tileSources
                            .satellite;

                    if (
                        activeTileLayer
                    ) {
                        map.removeLayer(
                            activeTileLayer
                        );
                    }

                    activeTileLayer =
                        leaflet.tileLayer(
                            source.url,
                            source.options
                        );

                    activeTileLayer.addTo(
                        map
                    );

                    activeTileLayer
                        .bringToBack();

                    editor
                        .querySelectorAll(
                            '[data-map-style]'
                        )
                        .forEach(
                            (button) => {
                                const active =
                                    button
                                        .dataset
                                        .mapStyle
                                    === selectedMapStyle;

                                button
                                    .classList
                                    .toggle(
                                        'is-active',
                                        active
                                    );
                            }
                        );
                };

            const setMapStyle =
                (value) => {
                    const next =
                        String(
                            value
                            || ''
                        );

                    selectedMapStyle =
                        Object.hasOwn(
                            tileSources,
                            next
                        )
                            ? next
                            : 'satellite';

                    applyTileLayer();
                };

            editor
                .querySelectorAll(
                    '[data-map-style]'
                )
                .forEach(
                    (button) => {
                        button.addEventListener(
                            'click',
                            () => {
                                setMapStyle(
                                    button
                                        .dataset
                                        .mapStyle
                                    || 'satellite'
                                );
                            }
                        );
                    }
                );

            applyTileLayer();

            return {
                applyTileLayer,
                setMapStyle,

                getMapStyle:
                    () =>
                        selectedMapStyle,

                getTileLayer:
                    () =>
                        activeTileLayer
            };
        };
})();
