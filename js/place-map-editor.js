(() => {
    'use strict';

    const editor =
        document.querySelector(
            '[data-mapped-area-editor]'
        );

    const mapElement =
        document.getElementById(
            'mapped-area-editor-map'
        );

    if (
        !editor
        || !mapElement
        || typeof L === 'undefined'
    ) {
        return;
    }

    const slug =
        String(
            editor.dataset.placeSlug
            || ''
        );

    const csrfToken =
        String(
            editor.dataset.csrfToken
            || ''
        );

    const latitude =
        Number(
            editor.dataset.placeLatitude
        );

    const longitude =
        Number(
            editor.dataset.placeLongitude
        );

    const featureType =
        editor.querySelector(
            '[data-feature-type]'
        );

    const featureLabel =
        editor.querySelector(
            '[data-feature-label]'
        );

    const featureList =
        editor.querySelector(
            '[data-feature-list]'
        );

    const status =
        editor.querySelector(
            '[data-editor-status]'
        );

    const help =
        editor.querySelector(
            '[data-editor-help]'
        );

    const pointCount =
        editor.querySelector(
            '[data-point-count]'
        );

    const newButton =
        editor.querySelector(
            '[data-new-feature]'
        );

    const drawButton =
        editor.querySelector(
            '[data-start-drawing]'
        );

    const finishButton =
        editor.querySelector(
            '[data-finish-drawing]'
        );

    const undoButton =
        editor.querySelector(
            '[data-undo-point]'
        );

    const saveButton =
        editor.querySelector(
            '[data-save-feature]'
        );

    const deleteButton =
        editor.querySelector(
            '[data-delete-feature]'
        );

    const apiUrl =
        `/api/place-map-features.php?slug=${encodeURIComponent(slug)}`;

    const map =
        L.map(
            mapElement,
            {
                zoomControl: false,
                maxZoom: 20
            }
        ).setView(
            [latitude, longitude],
            18
        );

    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    const tileSources = {
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

    let activeTileLayer = null;
    let selectedMapStyle = 'satellite';

    let features = [];
    let selectedFeatureId = 0;
    let drawing = false;
    let points = [];
    let shapeLayer = null;
    let vertexLayers = [];
    let existingLayers = [];

    const featureStyles = {
        place_boundary: {
            color: '#8b5cf6',
            fillColor: '#8b5cf6'
        },

        camping_area: {
            color: '#1f8f4e',
            fillColor: '#1f8f4e'
        },

        parking_area: {
            color: '#2563eb',
            fillColor: '#2563eb'
        }
    };

    const setStatus = (
        message = '',
        isError = false
    ) => {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle(
            'is-error',
            isError
        );
    };

    const setHelp = (message) => {
        if (help) {
            help.textContent = message;
        }
    };

    const updatePointCount = () => {
        if (!pointCount) {
            return;
        }

        pointCount.hidden =
            points.length === 0;

        pointCount.textContent =
            `${points.length} point${
                points.length === 1
                    ? ''
                    : 's'
            }`;

        finishButton.disabled =
            !drawing
            || points.length < 3;

        undoButton.disabled =
            !drawing
            || points.length === 0;

        saveButton.disabled =
            drawing
            || points.length < 3;
    };

    const applyTileLayer = () => {
        const source =
            tileSources[selectedMapStyle]
            || tileSources.satellite;

        if (activeTileLayer) {
            map.removeLayer(activeTileLayer);
        }

        activeTileLayer =
            L.tileLayer(
                source.url,
                source.options
            );

        activeTileLayer.addTo(map);
        activeTileLayer.bringToBack();

        editor
            .querySelectorAll(
                '[data-map-style]'
            )
            .forEach((button) => {
                const active =
                    button.dataset.mapStyle
                    === selectedMapStyle;

                button.classList.toggle(
                    'is-active',
                    active
                );
            });
    };

    const clearVertexLayers = () => {
        vertexLayers.forEach((layer) => {
            map.removeLayer(layer);
        });

        vertexLayers = [];
    };

    const clearShapeLayer = () => {
        if (shapeLayer) {
            map.removeLayer(shapeLayer);
            shapeLayer = null;
        }
    };

    const selectedStyle = () =>
        featureStyles[
            featureType?.value
        ]
        || featureStyles.camping_area;

    const renderEditableShape = () => {
        clearShapeLayer();
        clearVertexLayers();

        if (points.length === 0) {
            updatePointCount();
            return;
        }

        const style =
            selectedStyle();

        shapeLayer =
            L.polygon(
                points,
                {
                    color: style.color,
                    weight: 3,
                    opacity: 1,
                    fillColor:
                        style.fillColor,
                    fillOpacity:
                        drawing
                            ? .16
                            : .24
                }
            ).addTo(map);

        points.forEach(
            (latLng, index) => {
                const marker =
                    L.marker(
                        latLng,
                        {
                            draggable: !drawing,
                            icon:
                                L.divIcon({
                                    className:
                                        '',
                                    html:
                                        '<span class="mapped-area-editor-vertex" aria-hidden="true"></span>',
                                    iconSize:
                                        [18, 18],
                                    iconAnchor:
                                        [9, 9]
                                })
                        }
                    ).addTo(map);

                if (!drawing) {
                    marker.on(
                        'drag',
                        (event) => {
                            points[index] =
                                event.target
                                    .getLatLng();

                            if (shapeLayer) {
                                shapeLayer
                                    .setLatLngs(
                                        points
                                    );
                            }
                        }
                    );

                    marker.on(
                        'dragend',
                        () => {
                            setStatus(
                                'Point moved. Save the area to keep the change.'
                            );
                        }
                    );
                }

                vertexLayers.push(marker);
            }
        );

        updatePointCount();
    };

    const clearExistingLayers = () => {
        existingLayers.forEach((layer) => {
            map.removeLayer(layer);
        });

        existingLayers = [];
    };

    const renderExistingLayers = () => {
        clearExistingLayers();

        features.forEach((feature) => {
            if (
                Number(feature.id)
                === selectedFeatureId
            ) {
                return;
            }

            const style =
                featureStyles[
                    feature.feature_type
                ]
                || featureStyles.camping_area;

            const layer =
                L.geoJSON(
                    feature.geometry,
                    {
                        style: {
                            color: style.color,
                            weight: 2,
                            opacity: .78,
                            fillColor:
                                style.fillColor,
                            fillOpacity: .13
                        }
                    }
                ).addTo(map);

            layer.on(
                'click',
                () => {
                    selectFeature(
                        Number(feature.id)
                    );
                }
            );

            existingLayers.push(layer);
        });
    };

    const featureTypeLabel = (value) => {
        const option =
            featureType
                ?.querySelector(
                    `option[value="${CSS.escape(value)}"]`
                );

        return option
            ? option.textContent.trim()
            : value;
    };

    const renderFeatureList = () => {
        if (!featureList) {
            return;
        }

        featureList.replaceChildren();

        if (features.length === 0) {
            const empty =
                document.createElement('p');

            empty.textContent =
                'No mapped areas yet.';

            featureList.append(empty);
            return;
        }

        features.forEach((feature) => {
            const button =
                document.createElement(
                    'button'
                );

            button.type = 'button';

            button.classList.toggle(
                'is-active',
                Number(feature.id)
                === selectedFeatureId
            );

            const name =
                document.createElement(
                    'span'
                );

            name.textContent =
                feature.label
                || featureTypeLabel(
                    feature.feature_type
                );

            const type =
                document.createElement(
                    'small'
                );

            type.textContent =
                featureTypeLabel(
                    feature.feature_type
                );

            button.append(
                name,
                type
            );

            button.addEventListener(
                'click',
                () => {
                    selectFeature(
                        Number(feature.id)
                    );
                }
            );

            featureList.append(button);
        });
    };

    const resetEditor = (
        keepStatus = false
    ) => {
        selectedFeatureId = 0;
        drawing = false;
        points = [];

        clearShapeLayer();
        clearVertexLayers();

        if (featureType) {
            featureType.value =
                'camping_area';
        }

        if (featureLabel) {
            featureLabel.value = '';
        }

        drawButton.disabled = false;
        finishButton.disabled = true;
        undoButton.disabled = true;
        saveButton.disabled = true;
        deleteButton.disabled = true;

        setHelp(
            'Choose Draw area, then tap around the outside edge. Three points is the minimum. Add more points for irregular or rounded shapes.'
        );

        if (!keepStatus) {
            setStatus('');
        }

        renderExistingLayers();
        renderFeatureList();
        updatePointCount();
    };

    const geometryToPoints = (geometry) => {
        const ring =
            geometry
                ?.coordinates
                ?.[0];

        if (!Array.isArray(ring)) {
            return [];
        }

        const withoutClosure =
            ring.length > 1
            && ring[0][0]
                === ring[ring.length - 1][0]
            && ring[0][1]
                === ring[ring.length - 1][1]
                ? ring.slice(0, -1)
                : ring;

        return withoutClosure
            .filter(
                (point) =>
                    Array.isArray(point)
                    && point.length >= 2
            )
            .map(
                (point) =>
                    L.latLng(
                        Number(point[1]),
                        Number(point[0])
                    )
            );
    };

    const selectFeature = (id) => {
        const feature =
            features.find(
                (item) =>
                    Number(item.id) === id
            );

        if (!feature) {
            return;
        }

        selectedFeatureId =
            Number(feature.id);

        drawing = false;

        if (featureType) {
            featureType.value =
                feature.feature_type;
        }

        if (featureLabel) {
            featureLabel.value =
                feature.label || '';
        }

        points =
            geometryToPoints(
                feature.geometry
            );

        drawButton.disabled = false;
        finishButton.disabled = true;
        undoButton.disabled = true;
        deleteButton.disabled = false;

        setHelp(
            'Drag any point to correct this area. Save when finished. Choose Draw area if you want to replace the shape completely.'
        );

        setStatus('');

        renderEditableShape();
        renderExistingLayers();
        renderFeatureList();

        if (shapeLayer) {
            map.fitBounds(
                shapeLayer.getBounds(),
                {
                    padding: [50, 50],
                    maxZoom: 20
                }
            );
        }
    };

    const startDrawing = () => {
        drawing = true;
        points = [];

        clearShapeLayer();
        clearVertexLayers();

        drawButton.disabled = true;
        saveButton.disabled = true;
        deleteButton.disabled =
            selectedFeatureId < 1;

        setStatus('');

        setHelp(
            'Tap around the outside edge of the area. Add as many points as needed, then choose Finish.'
        );

        updatePointCount();
    };

    const finishDrawing = () => {
        if (points.length < 3) {
            return;
        }

        drawing = false;

        drawButton.disabled = false;

        setHelp(
            'Drag any point if the outline needs adjustment. Save when the shape is correct.'
        );

        setStatus(
            'Area ready to save.'
        );

        renderEditableShape();
    };

    const geometryPayload = () => {
        if (points.length < 3) {
            return null;
        }

        const ring =
            points.map(
                (point) => [
                    Number(
                        point.lng
                            .toFixed(7)
                    ),
                    Number(
                        point.lat
                            .toFixed(7)
                    )
                ]
            );

        ring.push([
            ring[0][0],
            ring[0][1]
        ]);

        return {
            type: 'Polygon',
            coordinates: [ring]
        };
    };

    const request = async (payload) => {
        const response =
            await fetch(
                '/api/place-map-features.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'Accept':
                            'application/json'
                    },
                    body:
                        JSON.stringify({
                            slug,
                            csrf_token:
                                csrfToken,
                            ...payload
                        })
                }
            );

        const data =
            await response.json()
                .catch(() => null);

        if (
            !response.ok
            || !data
            || data.ok !== true
        ) {
            throw new Error(
                data?.error
                || 'The mapped area request failed.'
            );
        }

        return data;
    };

    const saveFeature = async () => {
        const geometry =
            geometryPayload();

        if (!geometry) {
            setStatus(
                'Draw at least three points first.',
                true
            );
            return;
        }

        saveButton.disabled = true;
        setStatus('Saving mapped area...');

        try {
            const data =
                await request({
                    action: 'save',
                    feature_id:
                        selectedFeatureId,
                    feature_type:
                        featureType?.value
                        || 'camping_area',
                    label:
                        featureLabel?.value
                        || '',
                    geometry
                });

            features =
                Array.isArray(
                    data.features
                )
                    ? data.features
                    : [];

            const savedId =
                Number(
                    data.feature_id
                    || 0
                );

            resetEditor(true);

            if (savedId > 0) {
                selectFeature(savedId);
            }

            setStatus(
                'Mapped area saved.'
            );

        } catch (error) {
            saveButton.disabled = false;

            setStatus(
                error.message
                || 'The mapped area could not be saved.',
                true
            );
        }
    };

    const deleteFeature = async () => {
        if (selectedFeatureId < 1) {
            return;
        }

        const confirmed =
            window.confirm(
                'Delete this mapped area?'
            );

        if (!confirmed) {
            return;
        }

        deleteButton.disabled = true;
        setStatus('Deleting mapped area...');

        try {
            const data =
                await request({
                    action: 'delete',
                    feature_id:
                        selectedFeatureId
                });

            features =
                Array.isArray(
                    data.features
                )
                    ? data.features
                    : [];

            resetEditor(true);

            setStatus(
                'Mapped area deleted.'
            );

        } catch (error) {
            deleteButton.disabled = false;

            setStatus(
                error.message
                || 'The mapped area could not be deleted.',
                true
            );
        }
    };

    const loadFeatures = async () => {
        setStatus('Loading mapped areas...');

        try {
            const response =
                await fetch(
                    apiUrl,
                    {
                        credentials:
                            'same-origin',
                        headers: {
                            'Accept':
                                'application/json'
                        }
                    }
                );

            const data =
                await response.json();

            if (
                !response.ok
                || data.ok !== true
            ) {
                throw new Error(
                    data.error
                    || 'Mapped areas could not be loaded.'
                );
            }

            features =
                Array.isArray(
                    data.features
                )
                    ? data.features
                    : [];

            resetEditor(true);
            setStatus('');

            if (features.length > 0) {
                const bounds =
                    L.latLngBounds([]);

                features.forEach(
                    (feature) => {
                        const layer =
                            L.geoJSON(
                                feature.geometry
                            );

                        const layerBounds =
                            layer.getBounds();

                        if (layerBounds.isValid()) {
                            bounds.extend(
                                layerBounds
                            );
                        }
                    }
                );

                if (bounds.isValid()) {
                    map.fitBounds(
                        bounds,
                        {
                            padding:
                                [50, 50],
                            maxZoom: 20
                        }
                    );
                }
            }

        } catch (error) {
            setStatus(
                error.message
                || 'Mapped areas could not be loaded.',
                true
            );
        }
    };

    map.on(
        'click',
        (event) => {
            if (!drawing) {
                return;
            }

            points.push(
                event.latlng
            );

            renderEditableShape();
        }
    );

    drawButton?.addEventListener(
        'click',
        startDrawing
    );

    finishButton?.addEventListener(
        'click',
        finishDrawing
    );

    undoButton?.addEventListener(
        'click',
        () => {
            if (
                !drawing
                || points.length === 0
            ) {
                return;
            }

            points.pop();
            renderEditableShape();
        }
    );

    saveButton?.addEventListener(
        'click',
        saveFeature
    );

    deleteButton?.addEventListener(
        'click',
        deleteFeature
    );

    newButton?.addEventListener(
        'click',
        () => {
            resetEditor();
        }
    );

    featureType?.addEventListener(
        'change',
        () => {
            renderEditableShape();
        }
    );

    editor
        .querySelectorAll(
            '[data-map-style]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    selectedMapStyle =
                        button.dataset.mapStyle
                        || 'satellite';

                    applyTileLayer();
                }
            );
        });

    applyTileLayer();

    window.requestAnimationFrame(
        () => {
            map.invalidateSize(false);
        }
    );

    loadFeatures();
})();
