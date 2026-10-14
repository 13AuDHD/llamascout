(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor
        = window.LlamaPlaceMapEditor
        || {};

    namespace.createGeometryEditor =
        ({
            editor,
            map,
            mapElement,
            leaflet,
            featureType,
            drawButton,
            finishButton,
            undoButton,
            saveButton,
            deleteButton,
            moveAreaButton,
            revertFeatureButton,
            pointCount,
            selectedPointPanel,
            selectedPointNumber,
            selectedPointLatitude,
            selectedPointLongitude,
            selectedPointSource,
            removePointButton,
            useGpsPointButton,
            getFeatures,
            getSelectedFeatureId,
            getDirty,
            getGpsPosition,
            getFeatureStyle,
            setDirty,
            setStatus,
            setHelp,
            selectFeature,
            resetEditor,
            onShapeChanged,
            onSummaryChanged
        }) => {
            if (
                !editor
                || !map
                || !mapElement
                || !leaflet
            ) {
                throw new Error(
                    'Mapped-area geometry editor requires the editor, map, and Leaflet.'
                );
            }

            let drawing = false;
            let points = [];
            let vertexMetadata = [];
            let shapeLayer = null;
            let vertexLayers = [];
            let midpointLayers = [];
            let moveHandleLayer = null;
            let selectedPointIndex = -1;
            let moveMode = false;

            const isoNow =
                () =>
                    new Date()
                        .toISOString();

            const currentFeatures =
                () =>
                    Array.isArray(
                        getFeatures?.()
                    )
                        ? getFeatures()
                        : [];

            const selectedFeature =
                () =>
                    currentFeatures()
                        .find(
                            (feature) =>
                                Number(
                                    feature.id
                                )
                                === Number(
                                    getSelectedFeatureId?.()
                                    || 0
                                )
                        )
                    || null;

            const currentStyle =
                () =>
                    getFeatureStyle?.(
                        featureType?.value
                        || 'camping_area'
                    )
                    || {
                        color:
                            '#1f8f4e',

                        fillColor:
                            '#1f8f4e'
                    };

            const syncSelectedPointPanel =
                () => {
                    const hasSelection =
                        selectedPointIndex
                            >= 0
                        && selectedPointIndex
                            < points.length;

                    if (
                        selectedPointPanel
                    ) {
                        selectedPointPanel.hidden =
                            !hasSelection;
                    }

                    if (
                        removePointButton
                    ) {
                        removePointButton.disabled =
                            !hasSelection
                            || points.length <= 3
                            || drawing
                            || moveMode;
                    }

                    const gpsPosition =
                        getGpsPosition?.()
                        || null;

                    if (
                        useGpsPointButton
                    ) {
                        useGpsPointButton.disabled =
                            !hasSelection
                            || !gpsPosition
                            || drawing
                            || moveMode;
                    }

                    if (
                        !hasSelection
                    ) {
                        return;
                    }

                    const point =
                        points[
                            selectedPointIndex
                        ];

                    const metadata =
                        vertexMetadata[
                            selectedPointIndex
                        ]
                        || {
                            source:
                                'manual'
                        };

                    if (
                        selectedPointNumber
                    ) {
                        selectedPointNumber.textContent =
                            String(
                                selectedPointIndex
                                + 1
                            );
                    }

                    if (
                        selectedPointLatitude
                    ) {
                        selectedPointLatitude.textContent =
                            Number(
                                point.lat
                            ).toFixed(
                                7
                            );
                    }

                    if (
                        selectedPointLongitude
                    ) {
                        selectedPointLongitude.textContent =
                            Number(
                                point.lng
                            ).toFixed(
                                7
                            );
                    }

                    if (
                        selectedPointSource
                    ) {
                        const source =
                            metadata.source
                            === 'gps'
                                ? 'GPS'
                                : 'Manual';

                        const accuracy =
                            Number(
                                metadata
                                    .accuracy_m
                            );

                        selectedPointSource.textContent =
                            metadata.source
                            === 'gps'
                            && Number.isFinite(
                                accuracy
                            )
                                ? `${source} · ±${Math.round(accuracy)} m`
                                : source;
                    }
                };

            const updatePointCount =
                () => {
                    if (
                        pointCount
                    ) {
                        pointCount.hidden =
                            points.length
                            === 0;

                        pointCount.textContent =
                            `${points.length} point${points.length === 1 ? '' : 's'}`;
                    }

                    if (
                        finishButton
                    ) {
                        finishButton.disabled =
                            !drawing
                            || points.length
                                < 3;
                    }

                    if (
                        undoButton
                    ) {
                        undoButton.disabled =
                            !drawing
                            || points.length
                                === 0;
                    }

                    if (
                        saveButton
                    ) {
                        saveButton.disabled =
                            drawing
                            || points.length
                                < 3;
                    }

                    onSummaryChanged?.();
                };

            const clearVertexLayers =
                () => {
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

                    if (
                        moveHandleLayer
                    ) {
                        map.removeLayer(
                            moveHandleLayer
                        );

                        moveHandleLayer =
                            null;
                    }

                    vertexLayers = [];
                    midpointLayers = [];
                };

            const clearShapeLayer =
                () => {
                    if (
                        !shapeLayer
                    ) {
                        return;
                    }

                    map.removeLayer(
                        shapeLayer
                    );

                    shapeLayer =
                        null;
                };

            const vertexIcon =
                (
                    metadata,
                    selected = false
                ) => {
                    const isGps =
                        metadata?.source
                        === 'gps';

                    return leaflet.divIcon({
                        className:
                            '',

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
                        vertexMetadata[
                            index
                        ]
                        || {
                            source:
                                'manual'
                        };

                    vertexMetadata[
                        index
                    ] = {
                        ...current,

                        adjusted:
                            true,

                        adjusted_at:
                            isoNow()
                    };
                };

            const midpointBetween =
                (
                    first,
                    second
                ) =>
                    leaflet.latLng(
                        (
                            Number(
                                first.lat
                            )
                            + Number(
                                second.lat
                            )
                        )
                        / 2,

                        (
                            Number(
                                first.lng
                            )
                            + Number(
                                second.lng
                            )
                        )
                        / 2
                    );

            const insertPointAfter =
                (index) => {
                    const nextIndex =
                        (
                            index + 1
                        )
                        % points.length;

                    const midpoint =
                        midpointBetween(
                            points[
                                index
                            ],
                            points[
                                nextIndex
                            ]
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
                            source:
                                'manual'
                        }
                    );

                    selectedPointIndex =
                        index + 1;

                    setDirty?.(
                        true
                    );

                    setStatus?.(
                        'Point inserted. Drag it into position, then save the area.'
                    );

                    renderEditableShape();
                };

            const renderMoveHandle =
                () => {
                    if (
                        !moveMode
                        || points.length
                            < 3
                    ) {
                        return;
                    }

                    const bounds =
                        leaflet.latLngBounds(
                            points
                        );

                    const center =
                        bounds.getCenter();

                    const originalCenter =
                        leaflet.latLng(
                            center.lat,
                            center.lng
                        );

                    const originalPoints =
                        points.map(
                            (point) =>
                                leaflet.latLng(
                                    point.lat,
                                    point.lng
                                )
                        );

                    moveHandleLayer =
                        leaflet.marker(
                            center,
                            {
                                draggable:
                                    true,

                                icon:
                                    leaflet.divIcon({
                                        className:
                                            '',

                                        html:
                                            '<span class="mapped-area-editor-move-handle" aria-hidden="true">â</span>',

                                        iconSize:
                                            [28, 28],

                                        iconAnchor:
                                            [14, 14]
                                    })
                            }
                        ).addTo(
                            map
                        );

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
                                        leaflet.latLng(
                                            point.lat
                                            + deltaLat,

                                            point.lng
                                            + deltaLng
                                        )
                                );

                            if (
                                shapeLayer
                            ) {
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

                            setDirty?.(
                                true
                            );

                            setStatus?.(
                                'Area moved. Save the area to keep the change.'
                            );

                            renderEditableShape();
                        }
                    );
                };

            function renderEditableShape()
            {
                clearShapeLayer();
                clearVertexLayers();

                if (
                    points.length
                    === 0
                ) {
                    selectedPointIndex =
                        -1;

                    syncSelectedPointPanel();
                    updatePointCount();
                    onShapeChanged?.();

                    return;
                }

                const style =
                    currentStyle();

                shapeLayer =
                    leaflet.polygon(
                        points,
                        {
                            color:
                                style.color,

                            weight:
                                3,

                            opacity:
                                1,

                            fillColor:
                                style.fillColor,

                            fillOpacity:
                                drawing
                                    ? .16
                                    : .24
                        }
                    ).addTo(
                        map
                    );

                if (
                    moveMode
                ) {
                    renderMoveHandle();
                    syncSelectedPointPanel();
                    updatePointCount();
                    onShapeChanged?.();

                    return;
                }

                points.forEach(
                    (
                        latLng,
                        index
                    ) => {
                        const metadata =
                            vertexMetadata[
                                index
                            ]
                            || {
                                source:
                                    'manual'
                            };

                        const marker =
                            leaflet.marker(
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
                            ).addTo(
                                map
                            );

                        marker.on(
                            'click',
                            () => {
                                selectedPointIndex =
                                    index;

                                renderEditableShape();
                            }
                        );

                        if (
                            !drawing
                        ) {
                            marker.on(
                                'drag',
                                (event) => {
                                    points[
                                        index
                                    ] =
                                        event.target
                                            .getLatLng();

                                    if (
                                        shapeLayer
                                    ) {
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

                                    setDirty?.(
                                        true
                                    );

                                    setStatus?.(
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
                    && points.length
                        >= 3
                ) {
                    points.forEach(
                        (
                            point,
                            index
                        ) => {
                            const nextIndex =
                                (
                                    index + 1
                                )
                                % points.length;

                            const midpoint =
                                midpointBetween(
                                    point,
                                    points[
                                        nextIndex
                                    ]
                                );

                            const marker =
                                leaflet.marker(
                                    midpoint,
                                    {
                                        interactive:
                                            true,

                                        icon:
                                            leaflet.divIcon({
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
                                ).addTo(
                                    map
                                );

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
                onShapeChanged?.();
            }

            const startDrawing =
                () => {
                    drawing =
                        true;

                    moveMode =
                        false;

                    points =
                        [];

                    vertexMetadata =
                        [];

                    selectedPointIndex =
                        -1;

                    mapElement.classList
                        .remove(
                            'is-moving'
                        );

                    setDirty?.(
                        true
                    );

                    syncSelectedPointPanel();

                    clearShapeLayer();
                    clearVertexLayers();

                    if (
                        drawButton
                    ) {
                        drawButton.disabled =
                            true;
                    }

                    if (
                        saveButton
                    ) {
                        saveButton.disabled =
                            true;
                    }

                    if (
                        deleteButton
                    ) {
                        deleteButton.disabled =
                            Number(
                                getSelectedFeatureId?.()
                                || 0
                            )
                            < 1;
                    }

                    setStatus?.(
                        ''
                    );

                    setHelp?.(
                        'Tap the map to add points, or walk the boundary and use Add GPS point. Add as many points as needed, then choose Finish.'
                    );

                    updatePointCount();
                    onShapeChanged?.();
                };

            const finishDrawing =
                () => {
                    if (
                        points.length
                        < 3
                    ) {
                        return;
                    }

                    drawing =
                        false;

                    if (
                        drawButton
                    ) {
                        drawButton.disabled =
                            false;
                    }

                    if (
                        moveAreaButton
                    ) {
                        moveAreaButton.disabled =
                            false;
                    }

                    setDirty?.(
                        true
                    );

                    setHelp?.(
                        'Drag any point if the outline needs adjustment. Save when the shape is correct.'
                    );

                    setStatus?.(
                        'Area ready to save.'
                    );

                    renderEditableShape();
                };

            const geometryPayload =
                () => {
                    if (
                        points.length
                        < 3
                    ) {
                        return null;
                    }

                    const ring =
                        points.map(
                            (point) => [
                                Number(
                                    point.lng
                                        .toFixed(
                                            7
                                        )
                                ),

                                Number(
                                    point.lat
                                        .toFixed(
                                            7
                                        )
                                )
                            ]
                        );

                    ring.push([
                        ring[0][0],
                        ring[0][1]
                    ]);

                    return {
                        type:
                            'Polygon',

                        coordinates: [
                            ring
                        ]
                    };
                };

            const sourcePayload =
                () => {
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
                                    item
                                        ?.adjusted
                                    === true
                            );

                    let sourceType =
                        'manual';

                    if (
                        gpsVertices.length
                            === vertexMetadata.length
                        && gpsVertices.length
                            > 0
                        && !adjusted
                    ) {
                        sourceType =
                            'gps';

                    } else if (
                        gpsVertices.length
                        > 0
                    ) {
                        sourceType =
                            'mixed';
                    }

                    const accuracies =
                        gpsVertices
                            .map(
                                (item) =>
                                    Number(
                                        item
                                            .accuracy_m
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
                        accuracies.length
                            > 0
                            ? accuracies.reduce(
                                (
                                    total,
                                    value
                                ) =>
                                    total
                                    + value,
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
                                        item
                                            ?.captured_at
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
                                        item
                                            ?.adjusted
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
                        accuracies.length
                        > 0
                    ) {
                        metadata.gps_summary = {
                            average_accuracy_m:
                                Number(
                                    averageAccuracy
                                        .toFixed(
                                            2
                                        )
                                ),

                            best_accuracy_m:
                                Number(
                                    Math.min(
                                        ...accuracies
                                    )
                                        .toFixed(
                                            2
                                        )
                                ),

                            worst_accuracy_m:
                                Number(
                                    Math.max(
                                        ...accuracies
                                    )
                                        .toFixed(
                                            2
                                        )
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
                                        .toFixed(
                                            2
                                        )
                                ),

                        metadata
                    };
                };

            const removeSelectedPoint =
                () => {
                    if (
                        selectedPointIndex < 0
                        || selectedPointIndex
                            >= points.length
                        || points.length
                            <= 3
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
                            points.length
                            - 1;
                    }

                    setDirty?.(
                        true
                    );

                    setStatus?.(
                        'Point removed. Save the area to keep the change.'
                    );

                    renderEditableShape();
                };

            const replaceSelectedPointWithGps =
                () => {
                    const gpsPosition =
                        getGpsPosition?.()
                        || null;

                    if (
                        selectedPointIndex
                            < 0
                        || selectedPointIndex
                            >= points.length
                        || !gpsPosition
                        || drawing
                        || moveMode
                    ) {
                        return;
                    }

                    points[
                        selectedPointIndex
                    ] =
                        leaflet.latLng(
                            gpsPosition.lat,
                            gpsPosition.lng
                        );

                    vertexMetadata[
                        selectedPointIndex
                    ] = {
                        source:
                            'gps',

                        accuracy_m:
                            gpsPosition.accuracy
                            === null
                                ? null
                                : Number(
                                    gpsPosition
                                        .accuracy
                                        .toFixed(
                                            2
                                        )
                                ),

                        captured_at:
                            gpsPosition
                                .capturedAt
                    };

                    setDirty?.(
                        true
                    );

                    setStatus?.(
                        `Point ${selectedPointIndex + 1} replaced with the current GPS position.`
                    );

                    renderEditableShape();
                };

            const startMoveArea =
                () => {
                    if (
                        drawing
                        || points.length
                            < 3
                    ) {
                        return;
                    }

                    moveMode =
                        !moveMode;

                    selectedPointIndex =
                        -1;

                    mapElement.classList
                        .toggle(
                            'is-moving',
                            moveMode
                        );

                    if (
                        moveAreaButton
                    ) {
                        moveAreaButton.textContent =
                            moveMode
                                ? 'Cancel move'
                                : 'Move whole area';
                    }

                    setStatus?.(
                        moveMode
                            ? 'Drag the center handle to move the entire polygon.'
                            : 'Move canceled.'
                    );

                    renderEditableShape();
                };

            const revertFeature =
                () => {
                    if (
                        !getDirty?.()
                    ) {
                        return;
                    }

                    const selectedFeatureId =
                        Number(
                            getSelectedFeatureId?.()
                            || 0
                        );

                    if (
                        selectedFeatureId
                        > 0
                    ) {
                        selectFeature?.(
                            selectedFeatureId
                        );

                        setStatus?.(
                            'Unsaved changes reverted.'
                        );

                        return;
                    }

                    resetEditor?.(
                        true
                    );

                    setStatus?.(
                        'Unsaved area cleared.'
                    );
                };

            const addManualPoint =
                (latLng) => {
                    if (
                        !drawing
                    ) {
                        return;
                    }

                    points.push(
                        latLng
                    );

                    vertexMetadata.push({
                        source:
                            'manual'
                    });

                    selectedPointIndex =
                        points.length
                        - 1;

                    setDirty?.(
                        true
                    );

                    renderEditableShape();
                };

            const addGpsPoint =
                (
                    gpsPosition
                ) => {
                    if (
                        !gpsPosition
                    ) {
                        return;
                    }

                    if (
                        !drawing
                    ) {
                        startDrawing();
                    }

                    points.push(
                        leaflet.latLng(
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
                                        .toFixed(
                                            2
                                        )
                                ),

                        captured_at:
                            gpsPosition
                                .capturedAt
                    });

                    selectedPointIndex =
                        points.length
                        - 1;

                    setDirty?.(
                        true
                    );

                    renderEditableShape();
                };

            const undoPoint =
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
                        points.length
                        - 1;

                    setDirty?.(
                        true
                    );

                    renderEditableShape();
                };

            const setGeometry =
                (
                    nextPoints = [],
                    nextMetadata = []
                ) => {
                    points =
                        Array.isArray(
                            nextPoints
                        )
                            ? nextPoints
                            : [];

                    vertexMetadata =
                        Array.isArray(
                            nextMetadata
                        )
                            ? nextMetadata
                            : [];

                    drawing =
                        false;

                    moveMode =
                        false;

                    selectedPointIndex =
                        -1;

                    mapElement.classList
                        .remove(
                            'is-moving'
                        );

                    renderEditableShape();
                };

            const clearGeometry =
                () => {
                    drawing =
                        false;

                    moveMode =
                        false;

                    points =
                        [];

                    vertexMetadata =
                        [];

                    selectedPointIndex =
                        -1;

                    mapElement.classList
                        .remove(
                            'is-moving'
                        );

                    clearShapeLayer();
                    clearVertexLayers();
                    syncSelectedPointPanel();
                    updatePointCount();
                    onShapeChanged?.();
                };

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
                    undoPoint
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

            return {
                startDrawing,
                finishDrawing,
                undoPoint,
                addManualPoint,
                addGpsPoint,
                removeSelectedPoint,
                replaceSelectedPointWithGps,
                startMoveArea,
                revertFeature,
                renderEditableShape,
                syncSelectedPointPanel,
                updatePointCount,
                geometryPayload,
                sourcePayload,
                clearShapeLayer,
                clearVertexLayers,
                clearGeometry,
                setGeometry,

                getPoints:
                    () =>
                        points,

                getVertexMetadata:
                    () =>
                        vertexMetadata,

                getShapeLayer:
                    () =>
                        shapeLayer,

                getSelectedPointIndex:
                    () =>
                        selectedPointIndex,

                getDrawing:
                    () =>
                        drawing,

                getMoveMode:
                    () =>
                        moveMode,

                setSelectedPointIndex:
                    (value) => {
                        selectedPointIndex =
                            Number(
                                value
                            );
                    },

                setDrawing:
                    (value) => {
                        drawing =
                            value === true;
                    },

                setMoveMode:
                    (value) => {
                        moveMode =
                            value === true;
                    }
            };
        };
})();
