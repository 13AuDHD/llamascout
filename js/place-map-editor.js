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

    const placeName =
        String(
            editor.dataset.placeName
            || 'Place'
        );

    const modeButtons =
        Array.from(
            editor.querySelectorAll(
                '[data-map-mode-button]'
            )
        );

    const polygonEditor =
        editor.querySelector(
            '[data-polygon-editor]'
        );

    const sitePointPanel =
        editor.querySelector(
            '[data-site-point-panel]'
        );
    const facilityPanel = editor.querySelector('[data-facility-panel]');
    const facilityName = editor.querySelector('[data-facility-name]');
    const facilityKind = editor.querySelector('[data-facility-kind]');
    const facilityLat = editor.querySelector('[data-facility-latitude]');
    const facilityLng = editor.querySelector('[data-facility-longitude]');
    const facilityPickButton = editor.querySelector('[data-facility-pick]');
    const facilitySaveButton = editor.querySelector('[data-facility-save]');
    const facilityNewButton = editor.querySelector('[data-facility-new]');
    const canEditBoundaries = editor.dataset.canEditBoundaries === '1';
    const facilityStatus = editor.querySelector('[data-facility-status]');
    let facilityPicking = false;
    let facilityPreview = null;
    const facilityMode = () => editorMode === 'area'
        && areaFeatureType?.value === 'facility_area';

    const shapeTools =
        editor.querySelector(
            '[data-shape-tools]'
        );

    const areaTypeField =
        editor.querySelector(
            '[data-area-type-field]'
        );

    const areaFeatureType =
        editor.querySelector(
            '[data-area-feature-type]'
        );

    const placeBoundaryNote =
        editor.querySelector(
            '[data-place-boundary-note]'
        );

    const objectKind =
        editor.querySelector(
            '[data-object-kind]'
        );

    const objectExplanation =
        editor.querySelector(
            '[data-object-explanation]'
        );

    const siteTarget =
        editor.querySelector(
            '[data-site-point-target]'
        );

    const siteNameField =
        editor.querySelector(
            '[data-site-point-new-name]'
        );

    const siteName =
        editor.querySelector(
            '[data-site-point-name]'
        );

    const siteLatitude =
        editor.querySelector(
            '[data-site-point-latitude]'
        );

    const siteLongitude =
        editor.querySelector(
            '[data-site-point-longitude]'
        );

    const sitePointStatus =
        editor.querySelector(
            '[data-site-point-status]'
        );

    const sitePickButton =
        editor.querySelector(
            '[data-site-map-pick]'
        );

    const saveSitePointButton =
        editor.querySelector(
            '[data-save-site-point]'
        );

    let editorMode =
        'place';

    let sitePicking =
        false;

    let sitePreviewMarker =
        null;

    let siteLayers =
        [];

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

    // Initialize the basemap before geometry/report modules. A failure in
    // those modules must not leave the entire map as an empty grey box.
    modules.createMapStyles({
        editor,
        map,
        leaflet: L
    });

    const featureStyles = {
        place_boundary: {
            color:
                '#22c55e',

            fillColor:
                '#22c55e'
        },

        camping_area: {
            color:
                '#a855f7',

            fillColor:
                '#a855f7'
        },

        parking_area: {
            color:
                '#2563eb',

            fillColor:
                '#2563eb'
        },
        facility_area: { color: '#7931a9', fillColor: '#7931a9' },

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
                        : 'Use Developed campground for managed RV or tent sites. Set campsite prices in the Place rate card.';
            }
        };

    const areaDetailsPayload =
        () => {
            const currentFeature =
                features.find(
                    (feature) =>
                        Number(feature.id)
                        === selectedFeatureId
                )
                || null;

            return {
                area_use:
                    areaUse?.value
                    || '',

                // Pricing/cost controls were intentionally removed from this
                // editor. Preserve any existing cost-status metadata verbatim.
                fee_status:
                    feeStatus?.value
                    || String(
                        currentFeature
                            ?.area_details
                            ?.fee_status
                        || ''
                    ),

                overnight_status:
                    featureType?.value
                    === 'parking_area'
                        ? (
                            overnightStatus?.value
                            || ''
                        )
                        : ''
            };
        };

    const currentSite =
        () => {
            const id =
                Number(
                    siteTarget?.value
                    || 0
                );

            return campsites.find(
                (site) =>
                    Number(site.id)
                    === id
            )
            || null;
        };

    const clearSitePreview =
        () => {
            if (sitePreviewMarker) {
                map.removeLayer(
                    sitePreviewMarker
                );

                sitePreviewMarker =
                    null;
            }
        };

    const setSitePoint =
        (
            lat,
            lng,
            pan = false
        ) => {
            const nextLat =
                Number(lat);

            const nextLng =
                Number(lng);

            if (
                !Number.isFinite(nextLat)
                || !Number.isFinite(nextLng)
            ) {
                return;
            }

            if (siteLatitude) {
                siteLatitude.value =
                    nextLat.toFixed(7);
            }

            if (siteLongitude) {
                siteLongitude.value =
                    nextLng.toFixed(7);
            }

            clearSitePreview();

            sitePreviewMarker =
                L.circleMarker(
                    [nextLat, nextLng],
                    {
                        radius:
                            8,

                        color:
                            '#f59e0b',

                        weight:
                            3,

                        fillColor:
                            '#f59e0b',

                        fillOpacity:
                            .88
                    }
                ).addTo(
                    map
                );

            if (pan) {
                map.setView(
                    [nextLat, nextLng],
                    Math.max(
                        17,
                        map.getZoom()
                    )
                );
            }

            if (saveSitePointButton) {
                saveSitePointButton.disabled =
                    false;
            }
        };

    const syncSiteTarget =
        () => {
            const creating =
                !siteTarget
                || siteTarget.value
                    === 'new';

            if (siteNameField) {
                siteNameField.hidden =
                    !creating;
            }

            if (siteName) {
                siteName.disabled =
                    !creating;
            }

            clearSitePreview();

            if (creating) {
                if (siteLatitude) {
                    siteLatitude.value =
                        '';
                }

                if (siteLongitude) {
                    siteLongitude.value =
                        '';
                }

                if (sitePointStatus) {
                    sitePointStatus.textContent =
                        'Name the new Site, then place its exact point on the map or use Device GPS.';
                }

                if (saveSitePointButton) {
                    saveSitePointButton.disabled =
                        true;
                }

                return;
            }

            const site =
                currentSite();

            if (
                site
                && Number.isFinite(
                    Number(site.latitude)
                )
                && Number.isFinite(
                    Number(site.longitude)
                )
            ) {
                setSitePoint(
                    Number(site.latitude),
                    Number(site.longitude),
                    true
                );

                if (sitePointStatus) {
                    sitePointStatus.textContent =
                        'Existing Site location loaded. Move the point only if this Site needs a correction.';
                }
            } else {
                if (siteLatitude) {
                    siteLatitude.value =
                        '';
                }

                if (siteLongitude) {
                    siteLongitude.value =
                        '';
                }

                if (sitePointStatus) {
                    sitePointStatus.textContent =
                        'This Site has no exact point yet. Place it on the map or use Device GPS.';
                }

                if (saveSitePointButton) {
                    saveSitePointButton.disabled =
                        true;
                }
            }
        };

    const syncSiteOptions =
        (
            selectedId = null
        ) => {
            if (!siteTarget) {
                return;
            }

            const desired =
                selectedId === null
                    ? siteTarget.value
                    : String(
                        selectedId
                    );

            siteTarget.replaceChildren();

            const createOption =
                document.createElement(
                    'option'
                );

            createOption.value =
                'new';

            createOption.textContent =
                'New Site';

            siteTarget.append(
                createOption
            );

            campsites.forEach(
                (site) => {
                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        String(
                            site.id
                        );

                    option.textContent =
                        String(
                            site.display_name
                            || site.site_code
                            || site.site_name
                            || `Site ${site.id}`
                        );

                    siteTarget.append(
                        option
                    );
                }
            );

            siteTarget.value =
                Array.from(
                    siteTarget.options
                ).some(
                    (option) =>
                        option.value
                        === desired
                )
                    ? desired
                    : 'new';

            syncSiteTarget();
        };

    const clearFacilityPreview = () => {
        if (facilityPreview) map.removeLayer(facilityPreview);
        facilityPreview = null;
    };
    const setFacilityPoint = (lat, lng, pan = false) => {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)
            || lat < -90 || lat > 90 || lng < -180 || lng > 180
            || (lat === 0 && lng === 0)) {
            setStatus('Enter a valid Area GPS point.', true);
            return;
        }
        facilityLat.value = lat.toFixed(7);
        facilityLng.value = lng.toFixed(7);
        clearFacilityPreview();
        facilityPreview = L.circleMarker([lat, lng], {
            radius: 9, color: '#7931a9', weight: 3,
            fillColor: '#7931a9', fillOpacity: .88
        }).addTo(map);
        facilityPreview.bringToFront();
        if (pan) map.setView([lat, lng], Math.max(17, map.getZoom()));
        setDirty(true);
        facilityStatus.textContent = 'Point selected. Save when ready.';
    };
    const syncFacilityControls = () => {
        const facility = facilityMode();
        if (facilityPanel) facilityPanel.hidden = !facility;
        if (polygonEditor) polygonEditor.hidden = editorMode === 'site' || facility;
        if (shapeTools) shapeTools.hidden = editorMode === 'site' || facility;
        for (const btn of [drawButton, finishButton, undoButton, saveButton, newButton]) {
            if (btn) btn.hidden = editorMode === 'site' || facility;
        }
        if (facilitySaveButton) facilitySaveButton.hidden = !facility;
        if (!canEditBoundaries && !facility) {
            for (const btn of [drawButton, finishButton, undoButton, saveButton, deleteButton, newButton]) {
                if (btn) btn.hidden = true;
            }
        }
        if (!facility) {
            facilityPicking = false;
            clearFacilityPreview();
        }
        if (facility) {
            geometry?.clearGeometry();
            setHelp('Tap Place Area point on map, or enter GPS coordinates.');
        }
    };
    const setMode =
        (
            mode,
            options = {}
        ) => {
            const nextMode =
                ['place', 'area', 'site']
                    .includes(mode)
                    ? mode
                    : 'place';

            editorMode =
                nextMode;

            editor.dataset.mapMode =
                nextMode;

            modeButtons.forEach(
                (button) => {
                    const active =
                        button.dataset.mapModeButton
                        === nextMode;

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
                }
            );

            const siteMode =
                nextMode === 'site';

            if (polygonEditor) {
                polygonEditor.hidden =
                    siteMode;
            }

            if (sitePointPanel) {
                sitePointPanel.hidden =
                    !siteMode;
            }
            syncFacilityControls();

            if (shapeTools) {
                shapeTools.hidden =
                    siteMode;
            }

            if (sitePickButton) {
                sitePickButton.hidden =
                    !siteMode;
            }

            if (drawButton) {
                drawButton.hidden =
                    siteMode;
            }

            if (finishButton) {
                finishButton.hidden =
                    siteMode;
            }

            if (undoButton) {
                undoButton.hidden =
                    siteMode;
            }

            if (saveButton) {
                saveButton.hidden =
                    siteMode;
            }

            if (saveSitePointButton) {
                saveSitePointButton.hidden =
                    !siteMode;
            }

            if (deleteButton) {
                deleteButton.hidden =
                    siteMode;
            }

            if (newButton) {
                newButton.hidden =
                    siteMode;
            }

            if (areaTypeField) {
                areaTypeField.hidden =
                    nextMode !== 'area';
            }

            if (placeBoundaryNote) {
                placeBoundaryNote.hidden =
                    nextMode !== 'place';
            }

            if (objectKind) {
                objectKind.textContent =
                    nextMode === 'place'
                        ? 'Place'
                        : 'Area';
            }

            if (objectExplanation) {
                objectExplanation.textContent =
                    nextMode === 'place'
                        ? 'The green boundary represents this Place.'
                        : 'Areas organize sections inside the Place, such as a campground loop or parking area.';
            }

            if (siteMode) {
                geometry?.clearGeometry();
                selectedFeatureId =
                    0;

                sitePicking =
                    false;

                sitePickButton?.classList
                    .remove(
                        'is-active'
                    );

                syncSiteOptions(
                    options.siteId
                    ?? null
                );

                setHelp(
                    'Select a Site, then place its exact point on the map or use Device GPS.'
                );

                renderExistingLayers();

                return;
            }

            clearSitePreview();

            featureType.value =
                nextMode === 'place'
                    ? 'place_boundary'
                    : (
                        areaFeatureType?.value
                        || 'camping_area'
                    );

            syncAreaDetailFields(
                options.areaDetails
                || {}
            );

            if (
                nextMode === 'place'
                && !options.keepLabel
                && featureLabel
            ) {
                featureLabel.value =
                    placeName;
            }

            setHelp(
                nextMode === 'place'
                    ? 'Draw the Place boundary on the map, or use Device GPS to collect its points.'
                    : 'Draw the Area boundary inside the Place, or use Device GPS to collect its points.'
            );

            if (!facilityMode()) geometry?.renderEditableShape();
            syncFacilityControls();
            renderExistingLayers();
        };

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
                                pointToLayer: (_, latlng) => L.circleMarker(latlng, {
                                    radius: 9, color: '#7931a9', weight: 3,
                                    fillColor: '#7931a9', fillOpacity: .88
                                }),
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

                    if (feature.feature_type === 'facility_area') {
                        layer.bindTooltip(String(feature.label || 'Facility Area'));
                    }
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

            siteLayers.forEach(
                (layer) => {
                    map.removeLayer(
                        layer
                    );
                }
            );

            siteLayers =
                [];

            campsites.forEach(
                (site) => {
                    const lat =
                        Number(
                            site.latitude
                        );

                    const lng =
                        Number(
                            site.longitude
                        );

                    if (
                        !Number.isFinite(lat)
                        || !Number.isFinite(lng)
                        || (lat === 0 && lng === 0)
                    ) {
                        return;
                    }

                    const marker =
                        L.circleMarker(
                            [lat, lng],
                            {
                                radius:
                                    7,

                                color:
                                    '#f59e0b',

                                weight:
                                    3,

                                fillColor:
                                    '#f59e0b',

                                fillOpacity:
                                    .9,

                                bubblingMouseEvents:
                                    false
                            }
                        ).addTo(
                            map
                        );

                    marker.bindTooltip(
                        String(
                            site.display_name
                            || site.site_code
                            || site.site_name
                            || `Site ${site.id}`
                        ),
                        {
                            direction:
                                'top'
                        }
                    );

                    marker.on(
                        'click',
                        () => {
                            if (
                                dirty
                                && !window.confirm(
                                    'Discard the unsaved map changes?'
                                )
                            ) {
                                return;
                            }

                            setDirty(
                                false
                            );

                            setMode(
                                'site',
                                {
                                    siteId:
                                        Number(
                                            site.id
                                        )
                                }
                            );
                        }
                    );

                    siteLayers.push(
                        marker
                    );
                }
            );
        };

    /*
     * The unified editor no longer creates Site polygons or edits pricing.
     * Site locations use canonical campsite coordinates, while pricing remains
     * owned by the pricing/reporting workflows.
     */
    siteFields = null;
    pricing = null;

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
                (position) => {
                    if (
                        editorMode === 'site'
                    ) {
                        setSitePoint(
                            position.lat,
                            position.lng,
                            true
                        );

                        if (sitePointStatus) {
                            sitePointStatus.textContent =
                                position.accuracy === null
                                    ? 'Device GPS point captured.'
                                    : `Device GPS point captured at ±${Math.round(position.accuracy)} m accuracy.`;
                        }

                        return;
                    }

                    if (facilityMode()) {
                        setFacilityPoint(position.lat, position.lng, true);
                        return;
                    }
                    geometry.addGpsPoint(position);
                }
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
                () => {},
            syncSiteClasses:
                () => {},
            syncRates:
                () => {},
            clearSiteClasses:
                () => {},
            clearRates:
                () => {},

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
                () => [],

            rateMoney:
                () => '',

            onFeatureSelected:
                (feature) => {
                    const type =
                        String(
                            feature?.feature_type
                            || ''
                        );

                    if (
                        type === 'place_boundary'
                    ) {
                        setMode(
                            'place',
                            {
                                keepLabel:
                                    true
                            }
                        );

                        return;
                    }

                    if (
                        type === 'camping_area'
                        || type === 'parking_area'
                        || type === 'facility_area'
                    ) {
                        if (areaFeatureType) {
                            areaFeatureType.value =
                                type;
                        }

                        setMode(
                            'area',
                            {
                                keepLabel:
                                    true,

                                areaDetails:
                                    feature?.area_details
                                    || {}
                            }
                        );
                        if (type === 'facility_area' && feature.geometry?.type === 'Point') {
                            const [lng, lat] = feature.geometry.coordinates || [];
                            if (Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))) {
                                setFacilityPoint(Number(lat), Number(lng));
                                setDirty(false);
                            }
                            if (facilityName) facilityName.value = String(feature.label || '');
                            if (facilityKind) facilityKind.value = String(feature.metadata?.facility_kind || '');
                        }
                    }
                },

            onEditorReset:
                () => {
                    if (
                        editorMode !== 'site'
                    ) {
                        setMode(
                            editorMode
                        );
                    }
                }
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

    const saveFacilityPoint = async () => {
        if (!facilityMode()) return;
        const label = String(facilityName?.value || '').trim();
        const kind = String(facilityKind?.value || '').trim();
        const latText = String(facilityLat?.value || '').trim();
        const lngText = String(facilityLng?.value || '').trim();
        const lat = Number(latText), lng = Number(lngText);
        if (!kind) {
            setStatus('Choose the type of facility first.', true);
            facilityKind?.focus();
            return;
        }
        if (!label) {
            setStatus('Name this facility Area first.', true);
            facilityName?.focus();
            return;
        }
        if (!latText || !lngText || !Number.isFinite(lat) || !Number.isFinite(lng)
            || lat < -90 || lat > 90 || lng < -180 || lng > 180
            || (lat === 0 && lng === 0)) {
            setStatus('Choose valid Area coordinates.', true);
            return;
        }
        facilitySaveButton.disabled = true;
        setStatus('Saving Area point...');
        try {
            const result = await request({
                action: 'save',
                feature_id: selectedFeatureId,
                feature_type: 'facility_area',
                label,
                geometry: {type: 'Point', coordinates: [lng, lat]},
                area_details: {},
                metadata: { facility_kind: kind },
                source_type: 'manual'
            });
            applyApiState(result);
            clearFacilityPreview();
            const savedId = Number(result.feature_id || 0);
            featureState.resetEditor(true);
            if (savedId) featureState.selectFeature(savedId);
            setDirty(false);
            setStatus('Facility Area point saved.');
        } catch (error) {
            setStatus(error.message || 'Area point could not be saved.', true);
        } finally {
            facilitySaveButton.disabled = false;
        }
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
                editorMode === 'place'
                    ? 'Saving Place boundary...'
                    : 'Saving Area...'
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

                        // A Place perimeter must never silently become an
                        // Area because the hidden feature type is unset.
                        feature_type:
                            editorMode === 'place'
                                ? 'place_boundary'
                                : (
                                    areaFeatureType?.value === 'parking_area'
                                        ? 'parking_area'
                                        : 'camping_area'
                                ),

                        label:
                            featureLabel?.value
                            || '',

                        area_details:
                            areaDetailsPayload(),

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
                    editorMode === 'place'
                        ? 'Place boundary saved.'
                        : 'Area saved.'
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

    const adminReturn = editor.querySelector('[data-map-admin-return]');
    const linkToSiteReport = (siteId) => {
        if (!adminReturn || siteId < 1) return;
        const url = new URL(adminReturn.href);
        url.searchParams.set('site_id', String(siteId));
        url.hash = 'admin-site-report';
        adminReturn.href = url.toString();
        adminReturn.textContent = 'Edit Site report';
    };

    const saveSitePoint =
        async () => {
            const latText =
                String(
                    siteLatitude?.value
                    || ''
                ).trim();

            const lngText =
                String(
                    siteLongitude?.value
                    || ''
                ).trim();

            const lat =
                Number(
                    latText
                );

            const lng =
                Number(
                    lngText
                );

            if (
                latText === ''
                || lngText === ''
                || !Number.isFinite(lat)
                || !Number.isFinite(lng)
            ) {
                setStatus(
                    'Place the Site point first.',
                    true
                );

                return;
            }

            const targetValue =
                String(
                    siteTarget?.value
                    || 'new'
                );

            const creating =
                targetValue === 'new';

            const nextName =
                String(
                    siteName?.value
                    || ''
                ).trim();

            if (
                creating
                && nextName === ''
            ) {
                setStatus(
                    'Enter a name for the new Site.',
                    true
                );

                siteName?.focus();

                return;
            }

            saveSitePointButton.disabled =
                true;

            setStatus(
                'Saving Site point...'
            );

            try {
                const data =
                    await request({
                        action:
                            'save_site_point',

                        site_id:
                            creating
                                ? 0
                                : Number(
                                    targetValue
                                ),

                        site_name:
                            creating
                                ? nextName
                                : '',

                        latitude:
                            latText,

                        longitude:
                            lngText
                    });

                applyApiState(
                    data
                );

                const savedId =
                    Number(
                        data.site_id
                        || 0
                    );
                linkToSiteReport(savedId);

                syncSiteOptions(
                    savedId > 0
                        ? savedId
                        : null
                );

                renderExistingLayers();

                setStatus(
                    'Site point saved.'
                );

                if (sitePointStatus) {
                    sitePointStatus.textContent =
                        'This Site now has an exact map location.';
                }

            } catch (error) {
                saveSitePointButton.disabled =
                    false;

                setStatus(
                    error.message
                    || 'The Site point could not be saved.',
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

    const requestedSiteId = Number(new URLSearchParams(window.location.search).get('site_id'));
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
                'Loading map records...'
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
                        || 'Map records could not be loaded.'
                    );
                }

                applyApiState(
                    data
                );

                syncSiteOptions();

                featureState
                    .resetEditor(
                        true
                    );

                if (campsites.some(site => Number(site.id) === requestedSiteId)) {
                    setMode('site', {siteId: requestedSiteId});
                    linkToSiteReport(requestedSiteId);
                    setStatus('Site location loaded.');
                    return;
                }

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
                        'Map record loaded.'
                    );

                    return;
                }

                const placeBoundary =
                    features.find(
                        (feature) =>
                            feature.feature_type
                            === 'place_boundary'
                    )
                    || null;

                if (placeBoundary) {
                    featureState
                        .selectFeature(
                            Number(
                                placeBoundary.id
                            )
                        );
                } else {
                    setMode(
                        'place'
                    );
                }

                featureState
                    .fitAllAreas();

                setStatus('');

            } catch (error) {
                setStatus(
                    error.message
                    || 'Map records could not be loaded.',
                    true
                );
            }
        };

    map.on(
        'click',
        (event) => {
            if (
                editorMode === 'site'
            ) {
                if (!sitePicking) {
                    return;
                }

                sitePicking =
                    false;

                sitePickButton
                    ?.classList
                    .remove(
                        'is-active'
                    );

                setSitePoint(
                    event.latlng.lat,
                    event.latlng.lng
                );

                if (sitePointStatus) {
                    sitePointStatus.textContent =
                        'Site point selected. Save the Site point when it is correct.';
                }

                return;
            }

            if (facilityMode()) {
                if (!facilityPicking) return;
                facilityPicking = false;
                facilityPickButton?.classList.remove('is-active');
                setFacilityPoint(event.latlng.lat, event.latlng.lng);
                return;
            }
            geometry.addManualPoint(event.latlng);
        }
    );

    saveButton
        ?.addEventListener(
            'click',
            saveFeature
        );

    saveSitePointButton
        ?.addEventListener(
            'click',
            saveSitePoint
        );
    facilitySaveButton?.addEventListener('click', saveFacilityPoint);
    facilityNewButton?.addEventListener('click', () => {
        if (dirty && !window.confirm('Discard unsaved facility changes?')) return;
        clearFacilityPreview();
        facilityPicking = false;
        featureState.resetEditor(true);
        if (areaFeatureType) areaFeatureType.value = 'facility_area';
        setMode('area');
        if (facilityName) facilityName.value = '';
        if (facilityKind) facilityKind.value = '';
        if (facilityLat) facilityLat.value = '';
        if (facilityLng) facilityLng.value = '';
        setDirty(false);
        facilityStatus.textContent = 'New facility. Choose a point and give it a name.';
    });
    facilityPickButton?.addEventListener('click', () => {
        if (!facilityMode()) return;
        facilityPicking = !facilityPicking;
        facilityPickButton.classList.toggle('is-active', facilityPicking);
        facilityStatus.textContent = facilityPicking
            ? 'Tap the facility location on the map.'
            : 'Point placement canceled.';
    });
    for (const field of [facilityLat, facilityLng]) {
        field?.addEventListener('change', () => {
            if (String(facilityLat?.value || '').trim() && String(facilityLng?.value || '').trim()) {
                setFacilityPoint(Number(facilityLat.value), Number(facilityLng.value));
            }
        });
    }
    facilityName?.addEventListener('input', () => setDirty(true));
    facilityKind?.addEventListener('change', () => setDirty(true));

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
                        'Discard the unsaved map changes?'
                    )
                ) {
                    return;
                }

                featureState
                    .resetEditor();

                setMode(
                    editorMode
                );
            }
        );

    modeButtons.forEach(
        (button) => {
            button.addEventListener(
                'click',
                () => {
                    const nextMode =
                        String(
                            button.dataset
                                .mapModeButton
                            || ''
                        );

                    if (
                        nextMode === editorMode
                    ) {
                        return;
                    }

                    if (
                        dirty
                        && !window.confirm(
                            'Discard the unsaved map changes?'
                        )
                    ) {
                        return;
                    }

                    setDirty(
                        false
                    );

                    if (
                        nextMode === 'site'
                    ) {
                        setMode(
                            'site'
                        );

                        return;
                    }

                    featureState
                        .resetEditor(
                            true
                        );

                    setMode(
                        nextMode
                    );

                    if (nextMode === 'area') {
                        // Match Site selection: opening Areas chooses an
                        // existing Area rather than leaving the editor on a
                        // generic Place or an empty selection.
                        const firstArea = features.find(item =>
                            ['camping_area', 'parking_area', 'facility_area']
                                .includes(String(item.feature_type || '')));
                        if (firstArea) {
                            featureState.selectFeature(Number(firstArea.id));
                        }
                    }
                }
            );
        }
    );

    areaFeatureType
        ?.addEventListener(
            'change',
            () => {
                if (
                    editorMode !== 'area'
                ) {
                    return;
                }

                featureType.value =
                    areaFeatureType.value;

                syncAreaDetailFields();
                setDirty(
                    true
                );

                syncFacilityControls();
                if (!facilityMode()) geometry.renderEditableShape();
                featureState.renderFeatureList();
            }
        );

    sitePickButton
        ?.addEventListener(
            'click',
            () => {
                if (
                    editorMode !== 'site'
                ) {
                    return;
                }

                sitePicking =
                    !sitePicking;

                sitePickButton.classList
                    .toggle(
                        'is-active',
                        sitePicking
                    );

                if (sitePointStatus) {
                    sitePointStatus.textContent =
                        sitePicking
                            ? 'Tap the exact Site location on the map.'
                            : 'Site pin placement canceled.';
                }
            }
        );

    siteTarget
        ?.addEventListener(
            'change',
            syncSiteTarget
        );

    const previewTypedSitePoint =
        () => {
            const lat =
                Number(
                    siteLatitude?.value
                );

            const lng =
                Number(
                    siteLongitude?.value
                );

            if (
                String(
                    siteLatitude?.value
                    || ''
                ).trim() !== ''
                && String(
                    siteLongitude?.value
                    || ''
                ).trim() !== ''
                && Number.isFinite(lat)
                && Number.isFinite(lng)
            ) {
                setSitePoint(
                    lat,
                    lng
                );
            }
        };

    siteLatitude
        ?.addEventListener(
            'change',
            previewTypedSitePoint
        );

    siteLongitude
        ?.addEventListener(
            'change',
            previewTypedSitePoint
        );

    [
        areaUse,
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
