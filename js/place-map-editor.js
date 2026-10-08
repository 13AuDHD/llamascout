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

    const areaDetails =
        editor.querySelector(
            '[data-area-details]'
        );

    const areaUse =
        editor.querySelector(
            '[data-area-use]'
        );

    const feeStatus =
        editor.querySelector(
            '[data-fee-status]'
        );

    const overnightStatus =
        editor.querySelector(
            '[data-overnight-status]'
        );

    const overnightStatusField =
        editor.querySelector(
            '[data-overnight-status-field]'
        );

    const areaDetailsNote =
        editor.querySelector(
            '[data-area-details-note]'
        );

    const featureList =
        editor.querySelector(
            '[data-feature-list]'
        );

    const featureSummary =
        editor.querySelector(
            '[data-feature-summary]'
        );

    const summaryPoints =
        editor.querySelector(
            '[data-summary-points]'
        );

    const summarySource =
        editor.querySelector(
            '[data-summary-source]'
        );

    const summaryAccuracy =
        editor.querySelector(
            '[data-summary-accuracy]'
        );

    const summaryUpdated =
        editor.querySelector(
            '[data-summary-updated]'
        );

    const fitAllAreasButton =
        editor.querySelector(
            '[data-fit-all-areas]'
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

    const unsavedState =
        editor.querySelector(
            '[data-unsaved-state]'
        );

    const selectedPointPanel =
        editor.querySelector(
            '[data-selected-point]'
        );

    const selectedPointNumber =
        editor.querySelector(
            '[data-selected-point-number]'
        );

    const selectedPointLatitude =
        editor.querySelector(
            '[data-selected-point-latitude]'
        );

    const selectedPointLongitude =
        editor.querySelector(
            '[data-selected-point-longitude]'
        );

    const selectedPointSource =
        editor.querySelector(
            '[data-selected-point-source]'
        );

    const removePointButton =
        editor.querySelector(
            '[data-remove-point]'
        );

    const useGpsPointButton =
        editor.querySelector(
            '[data-use-gps-point]'
        );

    const moveAreaButton =
        editor.querySelector(
            '[data-move-area]'
        );

    const revertFeatureButton =
        editor.querySelector(
            '[data-revert-feature]'
        );

    const gpsState =
        editor.querySelector(
            '[data-gps-state]'
        );

    const gpsReading =
        editor.querySelector(
            '[data-gps-reading]'
        );

    const gpsLatitude =
        editor.querySelector(
            '[data-gps-latitude]'
        );

    const gpsLongitude =
        editor.querySelector(
            '[data-gps-longitude]'
        );

    const gpsAccuracy =
        editor.querySelector(
            '[data-gps-accuracy]'
        );

    const startGpsButton =
        editor.querySelector(
            '[data-start-gps]'
        );

    const stopGpsButton =
        editor.querySelector(
            '[data-stop-gps]'
        );

    const addGpsPointButton =
        editor.querySelector(
            '[data-add-gps-point]'
        );

    const centerGpsButton =
        editor.querySelector(
            '[data-center-gps]'
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
    let vertexMetadata = [];
    let shapeLayer = null;
    let vertexLayers = [];
    let midpointLayers = [];
    let moveHandleLayer = null;
    let existingLayers = [];

    let selectedPointIndex = -1;
    let moveMode = false;
    let dirty = false;

    let gpsWatchId = null;
    let gpsPosition = null;
    let gpsMarker = null;
    let gpsAccuracyCircle = null;

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

    const isoNow = () =>
        new Date().toISOString();

    const setStatus = (
        message = '',
        isError = false
    ) => {
        if (!status) {
            return;
        }

        status.textContent =
            message;

        status.classList.toggle(
            'is-error',
            isError
        );
    };

    const setHelp = (message) => {
        if (help) {
            help.textContent =
                message;
        }
    };

    const setDirty = (value = true) => {
        dirty = value === true;

        if (unsavedState) {
            unsavedState.hidden =
                !dirty;
        }

        if (revertFeatureButton) {
            revertFeatureButton.disabled =
                !dirty;
        }
    };

    const formatUpdatedAt =
        (value) => {
            const text =
                String(
                    value
                    || ''
                ).trim();

            if (text === '') {
                return '--';
            }

            const normalized =
                text.includes('T')
                    ? text
                    : text.replace(
                        ' ',
                        'T'
                    ) + 'Z';

            const date =
                new Date(
                    normalized
                );

            if (
                Number.isNaN(
                    date.getTime()
                )
            ) {
                return text;
            }

            return new Intl.DateTimeFormat(
                undefined,
                {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }
            ).format(date);
        };

    const syncFeatureSummary = () => {
        if (!featureSummary) {
            return;
        }

        const currentFeature =
            features.find(
                (feature) =>
                    Number(feature.id)
                    === selectedFeatureId
            )
            || null;

        const hasGeometry =
            points.length > 0;

        featureSummary.hidden =
            !hasGeometry
            && !currentFeature;

        if (
            !hasGeometry
            && !currentFeature
        ) {
            return;
        }

        if (summaryPoints) {
            summaryPoints.textContent =
                String(
                    points.length
                );
        }

        const gpsVertices =
            vertexMetadata.filter(
                (metadata) =>
                    metadata?.source
                    === 'gps'
            );

        const adjustedGps =
            gpsVertices.some(
                (metadata) =>
                    metadata?.adjusted
                    === true
            );

        let sourceLabel =
            'Manual';

        if (
            gpsVertices.length > 0
            && gpsVertices.length
                === vertexMetadata.length
            && !adjustedGps
        ) {
            sourceLabel =
                'GPS';

        } else if (
            gpsVertices.length > 0
        ) {
            sourceLabel =
                'Mixed';
        }

        if (summarySource) {
            summarySource.textContent =
                sourceLabel;
        }

        const accuracies =
            gpsVertices
                .map(
                    (metadata) =>
                        Number(
                            metadata
                                ?.accuracy_m
                        )
                )
                .filter(
                    (value) =>
                        Number.isFinite(
                            value
                        )
                        && value >= 0
                );

        if (summaryAccuracy) {
            if (
                accuracies.length
                === 0
            ) {
                summaryAccuracy.textContent =
                    '--';
            } else {
                const average =
                    accuracies.reduce(
                        (
                            total,
                            value
                        ) =>
                            total
                            + value,
                        0
                    )
                    / accuracies.length;

                summaryAccuracy.textContent =
                    `±${Math.round(
                        average
                    )} m avg`;
            }
        }

        if (summaryUpdated) {
            summaryUpdated.textContent =
                currentFeature
                    ? formatUpdatedAt(
                        currentFeature
                            .updated_at
                    )
                    : 'Not saved';
        }
    };

    const syncSelectedPointPanel = () => {
        const hasSelection =
            selectedPointIndex >= 0
            && selectedPointIndex < points.length;

        if (selectedPointPanel) {
            selectedPointPanel.hidden =
                !hasSelection;
        }

        if (removePointButton) {
            removePointButton.disabled =
                !hasSelection
                || points.length <= 3
                || drawing
                || moveMode;
        }

        if (useGpsPointButton) {
            useGpsPointButton.disabled =
                !hasSelection
                || !gpsPosition
                || drawing
                || moveMode;
        }

        if (!hasSelection) {
            return;
        }

        const point =
            points[selectedPointIndex];

        const metadata =
            vertexMetadata[selectedPointIndex]
            || {
                source: 'manual'
            };

        if (selectedPointNumber) {
            selectedPointNumber.textContent =
                String(
                    selectedPointIndex + 1
                );
        }

        if (selectedPointLatitude) {
            selectedPointLatitude.textContent =
                Number(point.lat)
                    .toFixed(7);
        }

        if (selectedPointLongitude) {
            selectedPointLongitude.textContent =
                Number(point.lng)
                    .toFixed(7);
        }

        if (selectedPointSource) {
            const source =
                metadata.source === 'gps'
                    ? 'GPS'
                    : 'Manual';

            const accuracy =
                Number(
                    metadata.accuracy_m
                );

            selectedPointSource.textContent =
                metadata.source === 'gps'
                && Number.isFinite(accuracy)
                    ? `${source} · ±${Math.round(accuracy)} m`
                    : source;
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

        syncFeatureSummary();
    };

    const applyTileLayer = () => {
        const source =
            tileSources[selectedMapStyle]
            || tileSources.satellite;

        if (activeTileLayer) {
            map.removeLayer(
                activeTileLayer
            );
        }

        activeTileLayer =
            L.tileLayer(
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
        vertexLayers.forEach(
            (layer) => {
                map.removeLayer(
                    layer
                );
            }
        );

        midpointLayers.forEach(
            (layer) => {
                map.removeLayer(
                    layer
                );
            }
        );

        if (moveHandleLayer) {
            map.removeLayer(
                moveHandleLayer
            );

            moveHandleLayer = null;
        }

        vertexLayers = [];
        midpointLayers = [];
    };

    const clearShapeLayer = () => {
        if (!shapeLayer) {
            return;
        }

        map.removeLayer(
            shapeLayer
        );

        shapeLayer = null;
    };

    const selectedStyle = () =>
        featureStyles[
            featureType?.value
        ]
        || featureStyles
            .camping_area;

    const vertexIcon =
        (
            metadata,
            selected = false
        ) => {
            const isGps =
                metadata?.source
                === 'gps';

            return L.divIcon({
                className: '',

                html:
                    `<span class="mapped-area-editor-vertex${isGps ? ' is-gps' : ''}${selected ? ' is-selected' : ''}" aria-hidden="true"></span>`,

                iconSize:
                    [18, 18],

                iconAnchor:
                    [9, 9]
            });
        };

    const markVertexAdjusted =
        (index) => {
            const current =
                vertexMetadata[index]
                || {
                    source: 'manual'
                };

            vertexMetadata[index] = {
                ...current,
                adjusted: true,
                adjusted_at:
                    isoNow()
            };
        };

    const midpointBetween =
        (
            first,
            second
        ) =>
            L.latLng(
                (
                    Number(first.lat)
                    + Number(second.lat)
                ) / 2,
                (
                    Number(first.lng)
                    + Number(second.lng)
                ) / 2
            );

    const insertPointAfter =
        (index) => {
            const nextIndex =
                (
                    index + 1
                ) % points.length;

            const midpoint =
                midpointBetween(
                    points[index],
                    points[nextIndex]
                );

            points.splice(
                index + 1,
                0,
                midpoint
            );

            vertexMetadata.splice(
                index + 1,
                0,
                {
                    source: 'manual'
                }
            );

            selectedPointIndex =
                index + 1;

            setDirty(true);

            setStatus(
                'Point inserted. Drag it into position, then save the area.'
            );

            renderEditableShape();
        };

    const renderMoveHandle = () => {
        if (
            !moveMode
            || points.length < 3
        ) {
            return;
        }

        const bounds =
            L.latLngBounds(
                points
            );

        const center =
            bounds.getCenter();

        const originalCenter =
            L.latLng(
                center.lat,
                center.lng
            );

        const originalPoints =
            points.map(
                (point) =>
                    L.latLng(
                        point.lat,
                        point.lng
                    )
            );

        moveHandleLayer =
            L.marker(
                center,
                {
                    draggable: true,

                    icon:
                        L.divIcon({
                            className: '',

                            html:
                                '<span class="mapped-area-editor-move-handle" aria-hidden="true">↕</span>',

                            iconSize:
                                [28, 28],

                            iconAnchor:
                                [14, 14]
                        })
                }
            ).addTo(map);

        moveHandleLayer.on(
            'drag',
            (event) => {
                const current =
                    event.target
                        .getLatLng();

                const deltaLat =
                    current.lat
                    - originalCenter.lat;

                const deltaLng =
                    current.lng
                    - originalCenter.lng;

                points =
                    originalPoints.map(
                        (point) =>
                            L.latLng(
                                point.lat
                                    + deltaLat,
                                point.lng
                                    + deltaLng
                            )
                    );

                if (shapeLayer) {
                    shapeLayer.setLatLngs(
                        points
                    );
                }
            }
        );

        moveHandleLayer.on(
            'dragend',
            () => {
                vertexMetadata =
                    vertexMetadata.map(
                        (metadata) => ({
                            ...(
                                metadata
                                || {
                                    source:
                                        'manual'
                                }
                            ),

                            adjusted:
                                true,

                            adjusted_at:
                                isoNow()
                        })
                    );

                moveMode =
                    false;

                mapElement.classList
                    .remove(
                        'is-moving'
                    );

                setDirty(true);

                setStatus(
                    'Area moved. Save the area to keep the change.'
                );

                renderEditableShape();
            }
        );
    };

    const renderEditableShape = () => {
        clearShapeLayer();
        clearVertexLayers();

        if (points.length === 0) {
            selectedPointIndex =
                -1;

            syncSelectedPointPanel();
            updatePointCount();

            return;
        }

        const style =
            selectedStyle();

        shapeLayer =
            L.polygon(
                points,
                {
                    color:
                        style.color,

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

        if (moveMode) {
            renderMoveHandle();
            syncSelectedPointPanel();
            updatePointCount();
            return;
        }

        points.forEach(
            (latLng, index) => {
                const metadata =
                    vertexMetadata[index]
                    || {
                        source:
                            'manual'
                    };

                const marker =
                    L.marker(
                        latLng,
                        {
                            draggable:
                                !drawing,

                            icon:
                                vertexIcon(
                                    metadata,
                                    index
                                        === selectedPointIndex
                                )
                        }
                    ).addTo(map);

                marker.on(
                    'click',
                    () => {
                        selectedPointIndex =
                            index;

                        renderEditableShape();
                    }
                );

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

                            syncSelectedPointPanel();
                        }
                    );

                    marker.on(
                        'dragend',
                        () => {
                            markVertexAdjusted(
                                index
                            );

                            selectedPointIndex =
                                index;

                            setDirty(true);

                            setStatus(
                                'Point moved. Save the area to keep the change.'
                            );

                            renderEditableShape();
                        }
                    );
                }

                vertexLayers.push(
                    marker
                );
            }
        );

        if (
            !drawing
            && points.length >= 3
        ) {
            points.forEach(
                (point, index) => {
                    const nextIndex =
                        (
                            index + 1
                        ) % points.length;

                    const midpoint =
                        midpointBetween(
                            point,
                            points[nextIndex]
                        );

                    const marker =
                        L.marker(
                            midpoint,
                            {
                                interactive:
                                    true,

                                icon:
                                    L.divIcon({
                                        className:
                                            '',

                                        html:
                                            '<span class="mapped-area-editor-midpoint" aria-hidden="true">+</span>',

                                        iconSize:
                                            [16, 16],

                                        iconAnchor:
                                            [8, 8]
                                    })
                            }
                        ).addTo(map);

                    marker.on(
                        'click',
                        () => {
                            insertPointAfter(
                                index
                            );
                        }
                    );

                    midpointLayers.push(
                        marker
                    );
                }
            );
        }

        syncSelectedPointPanel();
        updatePointCount();
    };

    const clearExistingLayers = () => {
        existingLayers.forEach(
            (layer) => {
                map.removeLayer(
                    layer
                );
            }
        );

        existingLayers = [];
    };

    const renderExistingLayers = () => {
        clearExistingLayers();

        features.forEach(
            (feature) => {
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
                    || featureStyles
                        .camping_area;

                const layer =
                    L.geoJSON(
                        feature.geometry,
                        {
                            style: {
                                color:
                                    style.color,

                                weight: 2,
                                opacity: .78,

                                fillColor:
                                    style.fillColor,

                                fillOpacity:
                                    .13
                            }
                        }
                    ).addTo(map);

                layer.on(
                    'click',
                    () => {
                        chooseFeature(
                            Number(
                                feature.id
                            )
                        );
                    }
                );

                existingLayers.push(
                    layer
                );
            }
        );
    };

    const areaUseOptions = {
        camping_area: [
            ['', 'Not specified'],
            ['developed_campground', 'Developed campground'],
            ['designated_camping', 'Designated camping area'],
            ['dispersed_camping', 'Dispersed camping area']
        ],

        parking_area: [
            ['', 'Not specified'],
            ['general_parking', 'General parking'],
            ['overnight_vehicle_parking', 'Overnight vehicle parking']
        ]
    };

    const areaUseLabels = Object.fromEntries(
        Object.values(areaUseOptions)
            .flat()
            .filter(
                ([value]) =>
                    value !== ''
            )
    );

    const feeStatusLabels = {
        free: 'Free',
        paid: 'Paid',
        varies: 'Varies',
        unknown: 'Unknown'
    };

    const overnightStatusLabels = {
        allowed: 'Overnight allowed',
        prohibited: 'Overnight not allowed',
        varies: 'Overnight varies',
        unknown: 'Overnight unknown'
    };

    const syncAreaDetailFields =
        (details = {}) => {
            const type =
                featureType?.value
                || 'camping_area';

            const supported =
                type === 'camping_area'
                || type === 'parking_area';

            if (areaDetails) {
                areaDetails.hidden =
                    !supported;
            }

            if (!supported) {
                if (areaUse) {
                    areaUse.replaceChildren();

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value = '';
                    option.textContent =
                        'Not specified';

                    areaUse.append(option);
                    areaUse.value = '';
                }

                if (feeStatus) {
                    feeStatus.value = '';
                }

                if (overnightStatus) {
                    overnightStatus.value = '';
                }

                if (overnightStatusField) {
                    overnightStatusField.hidden =
                        true;
                }

                return;
            }

            if (areaUse) {
                const desired =
                    String(
                        details.area_use
                        || ''
                    );

                areaUse.replaceChildren();

                (
                    areaUseOptions[type]
                    || []
                ).forEach(
                    ([value, label]) => {
                        const option =
                            document.createElement(
                                'option'
                            );

                        option.value =
                            value;

                        option.textContent =
                            label;

                        areaUse.append(
                            option
                        );
                    }
                );

                areaUse.value =
                    areaUse.querySelector(
                        `option[value="${CSS.escape(desired)}"]`
                    )
                        ? desired
                        : '';
            }

            if (feeStatus) {
                const desired =
                    String(
                        details.fee_status
                        || ''
                    );

                feeStatus.value =
                    feeStatus.querySelector(
                        `option[value="${CSS.escape(desired)}"]`
                    )
                        ? desired
                        : '';
            }

            const parking =
                type === 'parking_area';

            if (overnightStatusField) {
                overnightStatusField.hidden =
                    !parking;
            }

            if (overnightStatus) {
                const desired =
                    parking
                        ? String(
                            details.overnight_status
                            || ''
                        )
                        : '';

                overnightStatus.value =
                    overnightStatus.querySelector(
                        `option[value="${CSS.escape(desired)}"]`
                    )
                        ? desired
                        : '';
            }

            if (areaDetailsNote) {
                areaDetailsNote.textContent =
                    type === 'parking_area'
                        ? 'Use Overnight vehicle parking for ordinary parking spaces where sleeping in the vehicle is permitted. Local rules and posted signage still control.'
                        : 'Use Developed campground for managed RV or tent sites. Individual sites and detailed rates can be mapped inside this area later.';
            }
        };

    const areaDetailsPayload = () => ({
        area_use:
            areaUse?.value
            || '',

        fee_status:
            feeStatus?.value
            || '',

        overnight_status:
            featureType?.value
                === 'parking_area'
                ? (
                    overnightStatus?.value
                    || ''
                )
                : ''
    });

    const fitAllAreas = () => {
        if (
            !Array.isArray(features)
            || features.length === 0
        ) {
            return;
        }

        const bounds =
            L.latLngBounds([]);

        features.forEach(
            (feature) => {
                if (!feature?.geometry) {
                    return;
                }

                const layer =
                    L.geoJSON(
                        feature.geometry
                    );

                const layerBounds =
                    layer.getBounds();

                if (
                    layerBounds.isValid()
                ) {
                    bounds.extend(
                        layerBounds
                    );
                }
            }
        );

        if (!bounds.isValid()) {
            return;
        }

        map.fitBounds(
            bounds,
            {
                padding:
                    [50, 50],

                maxZoom:
                    20
            }
        );
    };

    const featureTypeLabel = (value) => {
        const option =
            featureType
                ?.querySelector(
                    `option[value="${CSS.escape(value)}"]`
                );

        return option
            ? option.textContent
                .trim()
            : value;
    };

    const renderFeatureList = () => {
        if (!featureList) {
            return;
        }

        featureList
            .replaceChildren();

        if (fitAllAreasButton) {
            fitAllAreasButton.disabled =
                features.length === 0;
        }

        if (
            features.length === 0
        ) {
            const empty =
                document.createElement(
                    'p'
                );

            empty.textContent =
                'No mapped areas yet.';

            featureList.append(
                empty
            );

            return;
        }

        features.forEach(
            (feature) => {
                const button =
                    document.createElement(
                        'button'
                    );

                button.type =
                    'button';

                button.classList
                    .toggle(
                        'is-active',
                        Number(
                            feature.id
                        )
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

                const source =
                    feature.source_type
                        === 'gps'
                        ? 'GPS'
                        : (
                            feature.source_type
                            === 'mixed'
                                ? 'Mixed'
                                : ''
                        );

                const detailLabels = [
                    areaUseLabels[
                        feature.area_details
                            ?.area_use
                    ] || '',
                    feeStatusLabels[
                        feature.area_details
                            ?.fee_status
                    ] || '',
                    overnightStatusLabels[
                        feature.area_details
                            ?.overnight_status
                    ] || ''
                ]
                    .filter(Boolean);

                type.textContent =
                    [
                        featureTypeLabel(
                            feature.feature_type
                        ),
                        ...detailLabels,
                        source
                    ]
                        .filter(Boolean)
                        .join(' · ');

                button.append(
                    name,
                    type
                );

                button.addEventListener(
                    'click',
                    () => {
                        chooseFeature(
                            Number(
                                feature.id
                            )
                        );
                    }
                );

                featureList.append(
                    button
                );
            }
        );
    };

    const resetEditor =
        (
            keepStatus = false
        ) => {
            selectedFeatureId = 0;
            drawing = false;
            moveMode = false;
            points = [];
            vertexMetadata = [];
            selectedPointIndex = -1;

            mapElement.classList
                .remove(
                    'is-moving'
                );

            setDirty(false);

            clearShapeLayer();
            clearVertexLayers();

            if (featureType) {
                featureType.value =
                    'camping_area';
            }

            if (featureLabel) {
                featureLabel.value =
                    '';
            }

            syncAreaDetailFields();

            drawButton.disabled =
                false;

            finishButton.disabled =
                true;

            undoButton.disabled =
                true;

            saveButton.disabled =
                true;

            deleteButton.disabled =
                true;

            if (moveAreaButton) {
                moveAreaButton.disabled =
                    true;

                moveAreaButton.textContent =
                    'Move whole area';
            }

            syncSelectedPointPanel();
            syncFeatureSummary();

            setHelp(
                'Choose Draw area and tap around the outside edge, or use device GPS below to collect the points while in the field.'
            );

            if (!keepStatus) {
                setStatus('');
            }

            renderExistingLayers();
            renderFeatureList();
            updatePointCount();
        };

    const geometryToPoints =
        (geometry) => {
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
                    === ring[
                        ring.length - 1
                    ][0]
                && ring[0][1]
                    === ring[
                        ring.length - 1
                    ][1]
                    ? ring.slice(
                        0,
                        -1
                    )
                    : ring;

            return withoutClosure
                .filter(
                    (point) =>
                        Array.isArray(
                            point
                        )
                        && point.length
                            >= 2
                )
                .map(
                    (point) =>
                        L.latLng(
                            Number(
                                point[1]
                            ),
                            Number(
                                point[0]
                            )
                        )
                );
        };

    const metadataForFeature =
        (feature, count) => {
            const vertices =
                feature
                    ?.metadata
                    ?.vertices;

            if (
                !Array.isArray(
                    vertices
                )
            ) {
                return Array.from(
                    {
                        length: count
                    },
                    () => ({
                        source:
                            feature
                                ?.source_type
                                === 'gps'
                                ? 'gps'
                                : 'manual'
                    })
                );
            }

            return Array.from(
                {
                    length: count
                },
                (_, index) => {
                    const item =
                        vertices[index];

                    return isPlainObject(
                        item
                    )
                        ? {
                            ...item
                        }
                        : {
                            source:
                                'manual'
                        };
                }
            );
        };

    function isPlainObject(value) {
        return (
            value !== null
            && typeof value
                === 'object'
            && !Array.isArray(
                value
            )
        );
    }

    const selectFeature = (id) => {
        const feature =
            features.find(
                (item) =>
                    Number(item.id)
                    === id
            );

        if (!feature) {
            return;
        }

        selectedFeatureId =
            Number(
                feature.id
            );

        drawing = false;
        moveMode = false;
        selectedPointIndex = -1;

        mapElement.classList
            .remove(
                'is-moving'
            );

        setDirty(false);

        if (featureType) {
            featureType.value =
                feature.feature_type;
        }

        if (featureLabel) {
            featureLabel.value =
                feature.label
                || '';
        }

        syncAreaDetailFields(
            feature.area_details
            || {}
        );

        points =
            geometryToPoints(
                feature.geometry
            );

        vertexMetadata =
            metadataForFeature(
                feature,
                points.length
            );

        drawButton.disabled =
            false;

        finishButton.disabled =
            true;

        undoButton.disabled =
            true;

        deleteButton.disabled =
            false;

        if (moveAreaButton) {
            moveAreaButton.disabled =
                points.length < 3;

            moveAreaButton.textContent =
                'Move whole area';
        }

        syncSelectedPointPanel();

        setHelp(
            'Drag any point to correct this area. Save when finished. Choose Draw area if you want to replace the shape completely.'
        );

        setStatus('');

        renderEditableShape();
        renderExistingLayers();
        renderFeatureList();
        syncFeatureSummary();

        if (shapeLayer) {
            map.fitBounds(
                shapeLayer.getBounds(),
                {
                    padding:
                        [50, 50],

                    maxZoom: 20
                }
            );
        }
    };

    const chooseFeature = (id) => {
        if (
            dirty
            && Number(id)
                !== selectedFeatureId
            && !window.confirm(
                'Discard the unsaved mapped-area changes?'
            )
        ) {
            return;
        }

        selectFeature(id);
    };


    const startDrawing = () => {
        drawing = true;
        moveMode = false;
        points = [];
        vertexMetadata = [];
        selectedPointIndex = -1;

        mapElement.classList
            .remove(
                'is-moving'
            );

        setDirty(true);
        syncSelectedPointPanel();

        clearShapeLayer();
        clearVertexLayers();

        drawButton.disabled =
            true;

        saveButton.disabled =
            true;

        deleteButton.disabled =
            selectedFeatureId
            < 1;

        setStatus('');

        setHelp(
            'Tap the map to add points, or walk the boundary and use Add GPS point. Add as many points as needed, then choose Finish.'
        );

        updatePointCount();
    };

    const finishDrawing = () => {
        if (
            points.length < 3
        ) {
            return;
        }

        drawing = false;

        drawButton.disabled =
            false;

        if (moveAreaButton) {
            moveAreaButton.disabled =
                false;
        }

        setDirty(true);

        setHelp(
            'Drag any point if the outline needs adjustment. Save when the shape is correct.'
        );

        setStatus(
            'Area ready to save.'
        );

        renderEditableShape();
    };

    const geometryPayload = () => {
        if (
            points.length < 3
        ) {
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
            coordinates: [
                ring
            ]
        };
    };

    const sourcePayload = () => {
        const gpsVertices =
            vertexMetadata
                .filter(
                    (item) =>
                        item?.source
                        === 'gps'
                );

        const adjusted =
            vertexMetadata
                .some(
                    (item) =>
                        item?.adjusted
                        === true
                );

        let sourceType =
            'manual';

        if (
            gpsVertices.length
            === vertexMetadata.length
            && gpsVertices.length > 0
            && !adjusted
        ) {
            sourceType = 'gps';

        } else if (
            gpsVertices.length > 0
        ) {
            sourceType = 'mixed';
        }

        const accuracies =
            gpsVertices
                .map(
                    (item) =>
                        Number(
                            item.accuracy_m
                        )
                )
                .filter(
                    (value) =>
                        Number.isFinite(
                            value
                        )
                        && value >= 0
                );

        const averageAccuracy =
            accuracies.length > 0
                ? accuracies.reduce(
                    (
                        total,
                        value
                    ) =>
                        total + value,
                    0
                )
                / accuracies.length
                : null;

        const metadata = {
            coordinate_system:
                'EPSG:4326',

            vertices:
                vertexMetadata.map(
                    (item) => ({
                        source:
                            item?.source
                            === 'gps'
                                ? 'gps'
                                : 'manual',

                        ...(
                            Number.isFinite(
                                Number(
                                    item
                                        ?.accuracy_m
                                )
                            )
                                ? {
                                    accuracy_m:
                                        Number(
                                            Number(
                                                item
                                                    .accuracy_m
                                            )
                                                .toFixed(
                                                    2
                                                )
                                        )
                                }
                                : {}
                        ),

                        ...(
                            item?.captured_at
                                ? {
                                    captured_at:
                                        String(
                                            item
                                                .captured_at
                                        )
                                }
                                : {}
                        ),

                        ...(
                            item?.adjusted
                                ? {
                                    adjusted:
                                        true,

                                    adjusted_at:
                                        String(
                                            item
                                                .adjusted_at
                                            || isoNow()
                                        )
                                }
                                : {}
                        )
                    })
                )
        };

        if (
            accuracies.length > 0
        ) {
            metadata.gps_summary = {
                average_accuracy_m:
                    Number(
                        averageAccuracy
                            .toFixed(2)
                    ),

                best_accuracy_m:
                    Number(
                        Math.min(
                            ...accuracies
                        )
                            .toFixed(2)
                    ),

                worst_accuracy_m:
                    Number(
                        Math.max(
                            ...accuracies
                        )
                            .toFixed(2)
                    )
            };
        }

        return {
            source_type:
                sourceType,

            accuracy_m:
                averageAccuracy
                    === null
                    ? null
                    : Number(
                        averageAccuracy
                            .toFixed(2)
                    ),

            metadata
        };
    };

    const request =
        async (payload) => {
            const response =
                await fetch(
                    '/api/place-map-features.php',
                    {
                        method:
                            'POST',

                        credentials:
                            'same-origin',

                        headers: {
                            'Content-Type':
                                'application/json',

                            Accept:
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
                await response
                    .json()
                    .catch(
                        () => null
                    );

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

    const saveFeature =
        async () => {
            const geometry =
                geometryPayload();

            if (!geometry) {
                setStatus(
                    'Draw at least three points first.',
                    true
                );

                return;
            }

            saveButton.disabled =
                true;

            setStatus(
                'Saving mapped area...'
            );

            try {
                const source =
                    sourcePayload();

                const data =
                    await request({
                        action:
                            'save',

                        feature_id:
                            selectedFeatureId,

                        feature_type:
                            featureType?.value
                            || 'camping_area',

                        label:
                            featureLabel?.value
                            || '',

                        area_details:
                            areaDetailsPayload(),

                        geometry,

                        ...source
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

                resetEditor(
                    true
                );

                if (savedId > 0) {
                    selectFeature(
                        savedId
                    );
                }

                setStatus(
                    'Mapped area saved.'
                );

            } catch (error) {
                saveButton.disabled =
                    false;

                setStatus(
                    error.message
                    || 'The mapped area could not be saved.',
                    true
                );
            }
        };

    const deleteFeature =
        async () => {
            if (
                selectedFeatureId
                < 1
            ) {
                return;
            }

            const confirmed =
                window.confirm(
                    'Delete this mapped area?'
                );

            if (!confirmed) {
                return;
            }

            deleteButton.disabled =
                true;

            setStatus(
                'Deleting mapped area...'
            );

            try {
                const data =
                    await request({
                        action:
                            'delete',

                        feature_id:
                            selectedFeatureId
                    });

                features =
                    Array.isArray(
                        data.features
                    )
                        ? data.features
                        : [];

                resetEditor(
                    true
                );

                setStatus(
                    'Mapped area deleted.'
                );

            } catch (error) {
                deleteButton.disabled =
                    false;

                setStatus(
                    error.message
                    || 'The mapped area could not be deleted.',
                    true
                );
            }
        };

    const loadFeatures =
        async () => {
            setStatus(
                'Loading mapped areas...'
            );

            try {
                const response =
                    await fetch(
                        apiUrl,
                        {
                            credentials:
                                'same-origin',

                            headers: {
                                Accept:
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

                resetEditor(
                    true
                );

                setStatus('');

                if (
                    features.length
                    > 0
                ) {
                    const bounds =
                        L.latLngBounds(
                            []
                        );

                    features.forEach(
                        (feature) => {
                            const layer =
                                L.geoJSON(
                                    feature.geometry
                                );

                            const layerBounds =
                                layer.getBounds();

                            if (
                                layerBounds
                                    .isValid()
                            ) {
                                bounds.extend(
                                    layerBounds
                                );
                            }
                        }
                    );

                    if (
                        bounds.isValid()
                    ) {
                        map.fitBounds(
                            bounds,
                            {
                                padding:
                                    [50, 50],

                                maxZoom:
                                    20
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


    const removeSelectedPoint = () => {
        if (
            selectedPointIndex < 0
            || selectedPointIndex >= points.length
            || points.length <= 3
            || drawing
            || moveMode
        ) {
            return;
        }

        points.splice(
            selectedPointIndex,
            1
        );

        vertexMetadata.splice(
            selectedPointIndex,
            1
        );

        if (
            selectedPointIndex
            >= points.length
        ) {
            selectedPointIndex =
                points.length - 1;
        }

        setDirty(true);

        setStatus(
            'Point removed. Save the area to keep the change.'
        );

        renderEditableShape();
    };

    const replaceSelectedPointWithGps = () => {
        if (
            selectedPointIndex < 0
            || selectedPointIndex >= points.length
            || !gpsPosition
            || drawing
            || moveMode
        ) {
            return;
        }

        points[selectedPointIndex] =
            L.latLng(
                gpsPosition.lat,
                gpsPosition.lng
            );

        vertexMetadata[selectedPointIndex] = {
            source:
                'gps',

            accuracy_m:
                gpsPosition.accuracy
                === null
                    ? null
                    : Number(
                        gpsPosition
                            .accuracy
                            .toFixed(2)
                    ),

            captured_at:
                gpsPosition.capturedAt
        };

        setDirty(true);

        setStatus(
            `Point ${selectedPointIndex + 1} replaced with the current GPS position.`
        );

        renderEditableShape();
    };

    const startMoveArea = () => {
        if (
            drawing
            || points.length < 3
        ) {
            return;
        }

        moveMode =
            !moveMode;

        selectedPointIndex =
            -1;

        mapElement.classList.toggle(
            'is-moving',
            moveMode
        );

        if (moveAreaButton) {
            moveAreaButton.textContent =
                moveMode
                    ? 'Cancel move'
                    : 'Move whole area';
        }

        setStatus(
            moveMode
                ? 'Drag the center handle to move the entire polygon.'
                : 'Move canceled.'
        );

        renderEditableShape();
    };

    const revertFeature = () => {
        if (!dirty) {
            return;
        }

        if (
            selectedFeatureId > 0
        ) {
            selectFeature(
                selectedFeatureId
            );

            setStatus(
                'Unsaved changes reverted.'
            );

            return;
        }

        resetEditor(true);

        setStatus(
            'Unsaved area cleared.'
        );
    };


    /* =====================================================
       DEVICE GPS
       ===================================================== */

    const setGpsState =
        (
            label,
            active = false
        ) => {
            if (!gpsState) {
                return;
            }

            gpsState.textContent =
                label;

            gpsState.classList.toggle(
                'is-active',
                active
            );
        };

    const clearGpsMapLayers = () => {
        if (gpsMarker) {
            map.removeLayer(
                gpsMarker
            );

            gpsMarker = null;
        }

        if (gpsAccuracyCircle) {
            map.removeLayer(
                gpsAccuracyCircle
            );

            gpsAccuracyCircle =
                null;
        }
    };

    const renderGpsPosition =
        (position) => {
            const lat =
                Number(
                    position.coords
                        .latitude
                );

            const lng =
                Number(
                    position.coords
                        .longitude
                );

            const accuracy =
                Number(
                    position.coords
                        .accuracy
                );

            if (
                !Number.isFinite(lat)
                || !Number.isFinite(lng)
            ) {
                return;
            }

            gpsPosition = {
                lat,
                lng,
                accuracy:
                    Number.isFinite(
                        accuracy
                    )
                        ? accuracy
                        : null,

                capturedAt:
                    new Date(
                        position.timestamp
                        || Date.now()
                    ).toISOString()
            };

            if (gpsReading) {
                gpsReading.hidden =
                    false;
            }

            if (gpsLatitude) {
                gpsLatitude.textContent =
                    lat.toFixed(7);
            }

            if (gpsLongitude) {
                gpsLongitude.textContent =
                    lng.toFixed(7);
            }

            if (gpsAccuracy) {
                gpsAccuracy.textContent =
                    gpsPosition.accuracy
                    === null
                        ? 'Unknown'
                        : `±${Math.round(
                            gpsPosition
                                .accuracy
                        )} m`;
            }

            addGpsPointButton.disabled =
                false;

            centerGpsButton.disabled =
                false;

            syncSelectedPointPanel();

            clearGpsMapLayers();

            gpsAccuracyCircle =
                L.circle(
                    [lat, lng],
                    {
                        radius:
                            Math.max(
                                1,
                                gpsPosition
                                    .accuracy
                                || 1
                            ),

                        color:
                            '#2563eb',

                        weight: 1,
                        opacity: .7,

                        fillColor:
                            '#2563eb',

                        fillOpacity:
                            .08,

                        interactive:
                            false
                    }
                ).addTo(map);

            gpsMarker =
                L.marker(
                    [lat, lng],
                    {
                        interactive:
                            false,

                        icon:
                            L.divIcon({
                                className:
                                    '',

                                html:
                                    '<span class="mapped-area-editor-gps-marker" aria-hidden="true"></span>',

                                iconSize:
                                    [18, 18],

                                iconAnchor:
                                    [9, 9]
                            })
                    }
                ).addTo(map);

            setGpsState(
                gpsPosition.accuracy
                    === null
                    ? 'GPS active'
                    : `±${Math.round(
                        gpsPosition
                            .accuracy
                    )} m`,
                true
            );
        };

    const gpsErrorMessage =
        (error) => {
            switch (
                Number(
                    error?.code
                )
            ) {
                case 1:
                    return 'Location permission was denied.';

                case 2:
                    return 'Your device could not determine a GPS position.';

                case 3:
                    return 'The GPS request timed out.';

                default:
                    return 'Device GPS is unavailable.';
            }
        };

    const stopGps = () => {
        if (
            gpsWatchId !== null
            && navigator.geolocation
        ) {
            navigator.geolocation
                .clearWatch(
                    gpsWatchId
                );
        }

        gpsWatchId = null;

        startGpsButton.disabled =
            false;

        stopGpsButton.disabled =
            true;

        setGpsState(
            gpsPosition
                ? 'Paused'
                : 'Off',
            false
        );
    };

    const startGps = () => {
        if (
            !('geolocation' in navigator)
        ) {
            setStatus(
                'This browser does not provide device geolocation.',
                true
            );

            return;
        }

        if (gpsWatchId !== null) {
            return;
        }

        startGpsButton.disabled =
            true;

        stopGpsButton.disabled =
            false;

        setGpsState(
            'Locating...',
            true
        );

        setStatus(
            'Waiting for a high-accuracy GPS position...'
        );

        gpsWatchId =
            navigator.geolocation
                .watchPosition(
                    (position) => {
                        renderGpsPosition(
                            position
                        );

                        setStatus('');
                    },

                    (error) => {
                        setStatus(
                            gpsErrorMessage(
                                error
                            ),
                            true
                        );

                        stopGps();
                    },

                    {
                        enableHighAccuracy:
                            true,

                        maximumAge:
                            1000,

                        timeout:
                            15000
                    }
                );
    };

    const addGpsPoint = () => {
        if (!gpsPosition) {
            return;
        }

        if (!drawing) {
            startDrawing();
        }

        points.push(
            L.latLng(
                gpsPosition.lat,
                gpsPosition.lng
            )
        );

        vertexMetadata.push({
            source:
                'gps',

            accuracy_m:
                gpsPosition.accuracy
                === null
                    ? null
                    : Number(
                        gpsPosition
                            .accuracy
                            .toFixed(2)
                    ),

            captured_at:
                gpsPosition
                    .capturedAt
        });

        selectedPointIndex =
            points.length - 1;

        setDirty(true);

        renderEditableShape();

        setStatus(
            `GPS point ${points.length} added${
                gpsPosition.accuracy
                === null
                    ? '.'
                    : ` at ±${Math.round(
                        gpsPosition
                            .accuracy
                    )} m accuracy.`
            }`
        );
    };

    const centerOnGps = () => {
        if (!gpsPosition) {
            return;
        }

        map.setView(
            [
                gpsPosition.lat,
                gpsPosition.lng
            ],
            Math.max(
                map.getZoom(),
                19
            )
        );
    };


    /* =====================================================
       EVENTS
       ===================================================== */

    map.on(
        'click',
        (event) => {
            if (!drawing) {
                return;
            }

            points.push(
                event.latlng
            );

            vertexMetadata.push({
                source:
                    'manual'
            });

            selectedPointIndex =
                points.length - 1;

            setDirty(true);

            renderEditableShape();
        }
    );

    drawButton
        ?.addEventListener(
            'click',
            startDrawing
        );

    finishButton
        ?.addEventListener(
            'click',
            finishDrawing
        );

    undoButton
        ?.addEventListener(
            'click',
            () => {
                if (
                    !drawing
                    || points.length
                        === 0
                ) {
                    return;
                }

                points.pop();
                vertexMetadata.pop();

                selectedPointIndex =
                    points.length - 1;

                setDirty(true);

                renderEditableShape();
            }
        );

    saveButton
        ?.addEventListener(
            'click',
            saveFeature
        );

    deleteButton
        ?.addEventListener(
            'click',
            deleteFeature
        );

    newButton
        ?.addEventListener(
            'click',
            () => {
                if (
                    dirty
                    && !window.confirm(
                        'Discard the unsaved mapped-area changes?'
                    )
                ) {
                    return;
                }

                resetEditor();
            }
        );

    featureType
        ?.addEventListener(
            'change',
            () => {
                syncAreaDetailFields();
                setDirty(true);
                renderEditableShape();
                renderFeatureList();
            }
        );

    [
        areaUse,
        feeStatus,
        overnightStatus
    ]
        .forEach(
            (control) => {
                control
                    ?.addEventListener(
                        'change',
                        () => {
                            setDirty(true);
                            renderFeatureList();
                        }
                    );
            }
        );

    featureLabel
        ?.addEventListener(
            'input',
            () => {
                setDirty(true);
            }
        );

    removePointButton
        ?.addEventListener(
            'click',
            removeSelectedPoint
        );

    useGpsPointButton
        ?.addEventListener(
            'click',
            replaceSelectedPointWithGps
        );

    moveAreaButton
        ?.addEventListener(
            'click',
            startMoveArea
        );

    revertFeatureButton
        ?.addEventListener(
            'click',
            revertFeature
        );

    startGpsButton
        ?.addEventListener(
            'click',
            startGps
        );

    stopGpsButton
        ?.addEventListener(
            'click',
            stopGps
        );

    addGpsPointButton
        ?.addEventListener(
            'click',
            addGpsPoint
        );

    centerGpsButton
        ?.addEventListener(
            'click',
            centerOnGps
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
                        button.dataset
                            .mapStyle
                        || 'satellite';

                    applyTileLayer();
                }
            );
        });

    fitAllAreasButton
        ?.addEventListener(
            'click',
            fitAllAreas
        );

    window.addEventListener(
        'pagehide',
        () => {
            stopGps();
        }
    );

    window.addEventListener(
        'beforeunload',
        (event) => {
            if (!dirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        }
    );

    applyTileLayer();

    window.requestAnimationFrame(
        () => {
            map.invalidateSize(
                false
            );
        }
    );

    loadFeatures();
})();
