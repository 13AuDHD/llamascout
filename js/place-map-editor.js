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

    const modules =
        window.LlamaPlaceMapEditor
        || {};

    if (
        !editor
        || !mapElement
        || typeof L === 'undefined'
    ) {
        return;
    }

    const requiredFactories = [
        'createSiteFields',
        'createPricing',
        'createFeatureState',
        'createGeometryEditor',
        'createGps',
        'createMapStyles'
    ];

    const missingFactory =
        requiredFactories.find(
            (name) =>
                typeof modules[name]
                !== 'function'
        );

    if (missingFactory) {
        throw new Error(
            `Mapped-area editor module is missing: ${missingFactory}`
        );
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

    const siteParent =
        editor.querySelector(
            '[data-site-parent]'
        );

    const siteClass =
        editor.querySelector(
            '[data-site-class]'
        );

    const apiUrl =
        `/api/place-map-features.php?slug=${encodeURIComponent(slug)}`;

    const map =
        L.map(
            mapElement,
            {
                zoomControl:
                    false,

                maxZoom:
                    20
            }
        ).setView(
            [
                latitude,
                longitude
            ],
            18
        );

    L.control.zoom({
        position:
            'bottomright'
    }).addTo(
        map
    );

    const featureStyles = {
        place_boundary: {
            color:
                '#8b5cf6',

            fillColor:
                '#8b5cf6'
        },

        camping_area: {
            color:
                '#1f8f4e',

            fillColor:
                '#1f8f4e'
        },

        parking_area: {
            color:
                '#2563eb',

            fillColor:
                '#2563eb'
        },

        camping_site: {
            color:
                '#d97706',

            fillColor:
                '#d97706'
        }
    };

    const areaUseOptions = {
        camping_area: [
            [
                '',
                'Not specified'
            ],
            [
                'developed_campground',
                'Developed campground'
            ],
            [
                'designated_camping',
                'Designated camping area'
            ],
            [
                'dispersed_camping',
                'Dispersed camping area'
            ]
        ],

        parking_area: [
            [
                '',
                'Not specified'
            ],
            [
                'general_parking',
                'General parking'
            ],
            [
                'overnight_vehicle_parking',
                'Overnight vehicle parking'
            ]
        ]
    };

    const areaUseLabels =
        Object.fromEntries(
            Object.values(
                areaUseOptions
            )
                .flat()
                .filter(
                    ([value]) =>
                        value !== ''
                )
        );

    const feeStatusLabels = {
        free:
            'Free',

        paid:
            'Paid',

        varies:
            'Varies',

        unknown:
            'Unknown'
    };

    const overnightStatusLabels = {
        allowed:
            'Overnight allowed',

        prohibited:
            'Overnight not allowed',

        varies:
            'Overnight varies',

        unknown:
            'Overnight unknown'
    };

    let features = [];
    let campsites = [];
    let selectedFeatureId = 0;
    let dirty = false;
    let existingLayers = [];

    let siteFields = null;
    let pricing = null;
    let geometry = null;
    let gps = null;
    let featureState = null;

    const setStatus =
        (
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

    const setHelp =
        (message) => {
            if (help) {
                help.textContent =
                    message;
            }
        };

    const setDirty =
        (value = true) => {
            dirty =
                value === true;

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
                    year:
                        'numeric',

                    month:
                        'short',

                    day:
                        'numeric'
                }
            ).format(
                date
            );
        };

    const syncFeatureSummary =
        () => {
            if (
                !featureSummary
                || !geometry
            ) {
                return;
            }

            const currentFeature =
                features.find(
                    (feature) =>
                        Number(feature.id)
                        === selectedFeatureId
                )
                || null;

            const points =
                geometry.getPoints();

            const vertexMetadata =
                geometry.getVertexMetadata();

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
                        `±${Math.round(average)} m avg`;
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

    const syncAreaDetailFields =
        (
            details = {}
        ) => {
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

                    option.value =
                        '';

                    option.textContent =
                        'Not specified';

                    areaUse.append(
                        option
                    );

                    areaUse.value =
                        '';
                }

                if (feeStatus) {
                    feeStatus.value =
                        '';
                }

                if (overnightStatus) {
                    overnightStatus.value =
                        '';
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
                    ([
                        value,
                        label
                    ]) => {
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
                            details
                                .overnight_status
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

    const areaDetailsPayload =
        () => ({
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

    const clearExistingLayers =
        () => {
            existingLayers.forEach(
                (layer) => {
                    map.removeLayer(
                        layer
                    );
                }
            );

            existingLayers =
                [];
        };

    const renderExistingLayers =
        () => {
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

                                    weight:
                                        2,

                                    opacity:
                                        .78,

                                    fillColor:
                                        style.fillColor,

                                    fillOpacity:
                                        .13
                                }
                            }
                        ).addTo(
                            map
                        );

                    layer.on(
                        'click',
                        () => {
                            featureState
                                ?.chooseFeature(
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

    siteFields =
        modules.createSiteFields({
            editor,

            getFeatureType:
                () =>
                    featureType?.value
                    || '',

            getFeatures:
                () =>
                    features,

            getCampsites:
                () =>
                    campsites,

            getSelectedFeatureId:
                () =>
                    selectedFeatureId,

            markDirty:
                setDirty,

            renderFeatureList:
                () =>
                    featureState
                        ?.renderFeatureList(),

            renderRates:
                () =>
                    pricing
                        ?.renderRates()
        });

    pricing =
        modules.createPricing({
            editor,

            getFeatureType:
                () =>
                    featureType?.value
                    || '',

            getFeatures:
                () =>
                    features,

            getSelectedFeatureId:
                () =>
                    selectedFeatureId,

            getSiteParentId:
                () =>
                    siteParent?.value
                    || '',

            getSiteClassId:
                () =>
                    siteClass?.value
                    || '',

            markDirty:
                setDirty
        });

    geometry =
        modules.createGeometryEditor({
            editor,
            map,
            mapElement,
            leaflet:
                L,
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

            getFeatures:
                () =>
                    features,

            getSelectedFeatureId:
                () =>
                    selectedFeatureId,

            getDirty:
                () =>
                    dirty,

            getGpsPosition:
                () =>
                    gps
                        ?.getGpsPosition()
                    || null,

            getFeatureStyle:
                (type) =>
                    featureStyles[type]
                    || featureStyles
                        .camping_area,

            setDirty,
            setStatus,
            setHelp,

            selectFeature:
                (id) =>
                    featureState
                        ?.selectFeature(
                            id
                        ),

            resetEditor:
                (keepStatus) =>
                    featureState
                        ?.resetEditor(
                            keepStatus
                        ),

            onSummaryChanged:
                syncFeatureSummary
        });

    gps =
        modules.createGps({
            editor,
            map,
            leaflet:
                L,
            setStatus,

            syncSelectedPointPanel:
                () =>
                    geometry
                        .syncSelectedPointPanel(),

            addGpsPoint:
                (position) =>
                    geometry
                        .addGpsPoint(
                            position
                        )
        });

    featureState =
        modules.createFeatureState({
            map,
            leaflet:
                L,
            featureType,
            featureLabel,
            featureList,
            fitAllAreasButton,
            drawButton,
            finishButton,
            undoButton,
            saveButton,
            deleteButton,
            moveAreaButton,

            getFeatures:
                () =>
                    features,

            getSelectedFeatureId:
                () =>
                    selectedFeatureId,

            setSelectedFeatureId:
                (value) => {
                    selectedFeatureId =
                        Number(value)
                        || 0;
                },

            getDirty:
                () =>
                    dirty,

            setDirty,
            syncAreaDetailFields,

            syncSiteFields:
                (details) =>
                    siteFields.sync(
                        details
                    ),

            syncSiteClasses:
                (classes) =>
                    pricing
                        .syncSiteClasses(
                            classes
                        ),

            syncRates:
                (rates) =>
                    pricing
                        .syncRates(
                            rates
                        ),

            clearSiteClasses:
                () =>
                    pricing
                        .clearSiteClasses(),

            clearRates:
                () =>
                    pricing
                        .clearRates(),

            setGeometry:
                (
                    points,
                    metadata
                ) =>
                    geometry
                        .setGeometry(
                            points,
                            metadata
                        ),

            clearGeometry:
                () =>
                    geometry
                        .clearGeometry(),

            getShapeLayer:
                () =>
                    geometry
                        .getShapeLayer(),

            syncFeatureSummary,
            setHelp,
            setStatus,
            renderExistingLayers,
            areaUseLabels,
            feeStatusLabels,
            overnightStatusLabels,

            getSiteDetailLabels:
                (details) =>
                    siteFields
                        .detailLabels(
                            details
                        ),

            rateMoney:
                (amount) =>
                    pricing
                        .rateMoney(
                            amount
                        )
        });

    modules.createMapStyles({
        editor,
        map,
        leaflet:
            L
    });

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

    const applyApiState =
        (data) => {
            features =
                Array.isArray(
                    data.features
                )
                    ? data.features
                    : [];

            campsites =
                Array.isArray(
                    data.campsites
                )
                    ? data.campsites
                    : [];
        };

    const saveFeature =
        async () => {
            const geometryPayload =
                geometry
                    .geometryPayload();

            if (!geometryPayload) {
                setStatus(
                    'Draw at least three points first.',
                    true
                );

                return;
            }

            if (saveButton) {
                saveButton.disabled =
                    true;
            }

            setStatus(
                'Saving mapped area...'
            );

            try {
                const source =
                    geometry
                        .sourcePayload();

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

                        site_details:
                            siteFields
                                .payload(),

                        rates:
                            pricing
                                .ratesPayload(),

                        site_classes:
                            pricing
                                .siteClassesPayload(),

                        geometry:
                            geometryPayload,

                        ...source
                    });

                applyApiState(
                    data
                );

                const savedId =
                    Number(
                        data.feature_id
                        || 0
                    );

                featureState
                    .resetEditor(
                        true
                    );

                if (savedId > 0) {
                    featureState
                        .selectFeature(
                            savedId
                        );
                }

                setStatus(
                    'Mapped area saved.'
                );

            } catch (error) {
                if (saveButton) {
                    saveButton.disabled =
                        false;
                }

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

            if (
                !window.confirm(
                    'Delete this mapped area?'
                )
            ) {
                return;
            }

            if (deleteButton) {
                deleteButton.disabled =
                    true;
            }

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

                applyApiState(
                    data
                );

                featureState
                    .resetEditor(
                        true
                    );

                setStatus(
                    'Mapped area deleted.'
                );

            } catch (error) {
                if (deleteButton) {
                    deleteButton.disabled =
                        false;
                }

                setStatus(
                    error.message
                    || 'The mapped area could not be deleted.',
                    true
                );
            }
        };

    const requestedFeatureId =
        Math.max(
            0,
            Number.parseInt(
                new URLSearchParams(
                    window.location.search
                ).get(
                    'feature'
                )
                || '0',
                10
            )
            || 0
        );

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
                    await response
                        .json();

                if (
                    !response.ok
                    || data.ok !== true
                ) {
                    throw new Error(
                        data.error
                        || 'Mapped areas could not be loaded.'
                    );
                }

                applyApiState(
                    data
                );

                featureState
                    .resetEditor(
                        true
                    );

                const requestedFeature =
                    requestedFeatureId > 0
                        ? features.find(
                            (feature) =>
                                Number(
                                    feature.id
                                )
                                === requestedFeatureId
                        )
                        : null;

                if (requestedFeature) {
                    featureState
                        .selectFeature(
                            requestedFeatureId
                        );

                    setStatus(
                        'Mapped area loaded from the Place page.'
                    );

                    return;
                }

                setStatus('');

                featureState
                    .fitAllAreas();

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
            geometry
                .addManualPoint(
                    event.latlng
                );
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

                featureState
                    .resetEditor();
            }
        );

    featureType
        ?.addEventListener(
            'change',
            () => {
                syncAreaDetailFields();
                siteFields.sync();

                if (
                    featureType.value
                    !== 'camping_area'
                ) {
                    pricing
                        .clearSiteClasses();
                } else {
                    pricing
                        .renderSiteClasses();
                }

                if (
                    !pricing
                        .rateFeatureSupported()
                ) {
                    pricing
                        .clearRates();
                } else {
                    pricing
                        .renderRates();
                }

                setDirty(
                    true
                );

                geometry
                    .renderEditableShape();

                featureState
                    .renderFeatureList();
            }
        );

    [
        areaUse,
        feeStatus,
        overnightStatus
    ].forEach(
        (control) => {
            control
                ?.addEventListener(
                    'change',
                    () => {
                        setDirty(
                            true
                        );

                        featureState
                            .renderFeatureList();

                        pricing
                            .renderRates();
                    }
                );
        }
    );

    featureLabel
        ?.addEventListener(
            'input',
            () => {
                setDirty(
                    true
                );
            }
        );

    window.addEventListener(
        'beforeunload',
        (event) => {
            if (!dirty) {
                return;
            }

            event.preventDefault();
            event.returnValue =
                '';
        }
    );

    window.requestAnimationFrame(
        () => {
            map.invalidateSize(
                false
            );
        }
    );

    loadFeatures();
})();
