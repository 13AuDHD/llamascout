(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor
        = window.LlamaPlaceMapEditor
        || {};

    const isPlainObject =
        (value) =>
            value !== null
            && typeof value === 'object'
            && !Array.isArray(value);

    namespace.createFeatureState =
        ({
            map,
            leaflet,
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
            getFeatures,
            getSelectedFeatureId,
            setSelectedFeatureId,
            getDirty,
            setDirty,
            syncAreaDetailFields,
            syncSiteFields,
            syncSiteClasses,
            syncRates,
            clearSiteClasses,
            clearRates,
            setGeometry,
            clearGeometry,
            getShapeLayer,
            syncFeatureSummary,
            setHelp,
            setStatus,
            renderExistingLayers,
            areaUseLabels,
            feeStatusLabels,
            overnightStatusLabels,
            getSiteDetailLabels,
            rateMoney
        }) => {
            const currentFeatures =
                () =>
                    Array.isArray(getFeatures?.())
                        ? getFeatures()
                        : [];

            const currentSelectedFeatureId =
                () =>
                    Number(
                        getSelectedFeatureId?.()
                        || 0
                    );

            const editorRoot =
                featureType
                    ?.closest(
                        '[data-mapped-area-editor]'
                    )
                || null;

            const featureLanguage =
                (type) => {
                    const value =
                        String(
                            type
                            || ''
                        );

                    if (value === 'place_boundary') {
                        return {
                            noun:
                                'Place boundary',

                            shortNoun:
                                'boundary',

                            newLabel:
                                'New boundary',

                            saveLabel:
                                'Save boundary',

                            moveLabel:
                                'Move boundary',

                            createHelp:
                                'Draw the Place boundary on the map, or use device GPS to collect its points.',

                            editHelp:
                                'Drag any point to correct this Place boundary. Save when finished.'
                        };
                    }

                    if (value === 'camping_site') {
                        return {
                            noun:
                                'Site details',

                            shortNoun:
                                'Site',

                            newLabel:
                                'New Site',

                            saveLabel:
                                'Save Site',

                            moveLabel:
                                'Move Site',

                            createHelp:
                                'Draw this Site on the map, or use device GPS to collect its corners.',

                            editHelp:
                                'Drag any point to correct this Site. Save when finished.'
                        };
                    }

                    if (
                        value === 'camping_area'
                        || value === 'parking_area'
                    ) {
                        return {
                            noun:
                                'Area details',

                            shortNoun:
                                'Area',

                            newLabel:
                                'New Area',

                            saveLabel:
                                'Save Area',

                            moveLabel:
                                'Move Area',

                            createHelp:
                                'Draw this Area on the map, or use device GPS to collect its boundary points.',

                            editHelp:
                                'Drag any point to correct this Area. Save when finished.'
                        };
                    }

                    return {
                        noun:
                            'Map details',

                        shortNoun:
                            'feature',

                        newLabel:
                            'New feature',

                        saveLabel:
                            'Save',

                        moveLabel:
                            'Move shape',

                        createHelp:
                            'Draw this feature on the map, or use device GPS to collect its points.',

                        editHelp:
                            'Drag any point to correct this feature. Save when finished.'
                    };
                };

            const syncProductionLanguage =
                (
                    type = featureType?.value
                    || 'camping_area',
                    editing = currentSelectedFeatureId() > 0
                ) => {
                    if (!editorRoot) {
                        return;
                    }

                    const language =
                        featureLanguage(
                            type
                        );

                    const eyebrow =
                        editorRoot.querySelector(
                            '.mapped-area-editor-header .eyebrow'
                        );

                    if (eyebrow) {
                        eyebrow.textContent =
                            'Place layout';
                    }

                    const intro =
                        editorRoot.querySelector(
                            '.mapped-area-editor-header > div > p:last-child'
                        );

                    if (intro) {
                        intro.textContent =
                            'Map the Place boundary, Areas, and individual Sites. Geometry is part of each record and is not a separate identity.';
                    }

                    const mapElement =
                        editorRoot.querySelector(
                            '#mapped-area-editor-map'
                        );

                    if (mapElement) {
                        mapElement.setAttribute(
                            'aria-label',
                            'Place layout editor'
                        );
                    }

                    const detailsHeading =
                        editorRoot.querySelector(
                            '.mapped-area-editor-area-card h2'
                        );

                    if (detailsHeading) {
                        detailsHeading.textContent =
                            language.noun;
                    }

                    const newButton =
                        editorRoot.querySelector(
                            '[data-new-feature]'
                        );

                    if (newButton) {
                        newButton.textContent =
                            language.newLabel;
                    }

                    if (saveButton) {
                        saveButton.textContent =
                            language.saveLabel;
                    }

                    if (moveAreaButton) {
                        moveAreaButton.textContent =
                            language.moveLabel;
                    }

                    const existingHeading =
                        editorRoot.querySelector(
                            '.mapped-area-editor-existing h2'
                        );

                    if (existingHeading) {
                        existingHeading.textContent =
                            'Place layout';
                    }

                    const fitButton =
                        editorRoot.querySelector(
                            '[data-fit-all-areas]'
                        );

                    if (fitButton) {
                        fitButton.textContent =
                            'Fit all';
                    }

                    setHelp?.(
                        editing
                            ? language.editHelp
                            : language.createHelp
                    );
                };

            const featureTypeLabel =
                (value) => {
                    const option =
                        featureType
                            ?.querySelector(
                                `option[value="${CSS.escape(String(value))}"]`
                            );

                    return option
                        ? option.textContent.trim()
                        : String(value || '');
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
                                leaflet.latLng(
                                    Number(point[1]),
                                    Number(point[0])
                                )
                        );
                };

            const metadataForFeature =
                (
                    feature,
                    count
                ) => {
                    const vertices =
                        feature
                            ?.metadata
                            ?.vertices;

                    if (!Array.isArray(vertices)) {
                        return Array.from(
                            { length: count },
                            () => ({
                                source:
                                    feature?.source_type === 'gps'
                                        ? 'gps'
                                        : 'manual'
                            })
                        );
                    }

                    return Array.from(
                        { length: count },
                        (_, index) => {
                            const item =
                                vertices[index];

                            return isPlainObject(item)
                                ? { ...item }
                                : { source: 'manual' };
                        }
                    );
                };

            const fitAllAreas =
                () => {
                    const features =
                        currentFeatures();

                    if (features.length === 0) {
                        return;
                    }

                    const bounds =
                        leaflet.latLngBounds([]);

                    features.forEach(
                        (feature) => {
                            if (!feature?.geometry) {
                                return;
                            }

                            const layer =
                                leaflet.geoJSON(
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

            const selectionDetail =
                (feature) => {
                    const featureId =
                        Number(
                            feature?.id
                            || 0
                        );

                    const placeId =
                        Number(
                            feature?.place_id
                            || 0
                        );

                    const type =
                        String(
                            feature?.feature_type
                            || ''
                        );

                    const label =
                        String(
                            feature?.label
                            || featureTypeLabel(type)
                            || ''
                        );

                    if (type === 'place_boundary') {
                        return {
                            level:
                                'place',

                            place_id:
                                placeId,

                            area_id:
                                0,

                            site_id:
                                0,

                            selector_value:
                                '',

                            map_feature_id:
                                featureId,

                            parent_area_id:
                                0,

                            label
                        };
                    }

                    if (
                        type === 'camping_area'
                        || type === 'parking_area'
                    ) {
                        return {
                            level:
                                'area',

                            place_id:
                                placeId,

                            area_id:
                                featureId,

                            site_id:
                                0,

                            selector_value:
                                `map_feature:${featureId}`,

                            map_feature_id:
                                featureId,

                            parent_area_id:
                                0,

                            label
                        };
                    }

                    if (type === 'camping_site') {
                        const campsiteId =
                            Number(
                                feature
                                    ?.site_details
                                    ?.canonical_campsite_id
                                || 0
                            );

                        const parentAreaId =
                            Number(
                                feature
                                    ?.site_details
                                    ?.camping_area_feature_id
                                || 0
                            );

                        const siteCode =
                            String(
                                feature
                                    ?.site_details
                                    ?.site_code
                                || ''
                            ).trim();

                        const siteLabel =
                            siteCode !== ''
                                ? `Site ${siteCode}`
                                : (
                                    label !== ''
                                        ? label
                                        : 'Site'
                                );

                        return {
                            level:
                                'site',

                            place_id:
                                placeId,

                            area_id:
                                parentAreaId,

                            site_id:
                                campsiteId > 0
                                    ? campsiteId
                                    : featureId,

                            selector_value:
                                campsiteId > 0
                                    ? `campsite_record:${campsiteId}`
                                    : `map_feature:${featureId}`,

                            map_feature_id:
                                featureId,

                            parent_area_id:
                                parentAreaId,

                            label:
                                siteLabel
                        };
                    }

                    return {
                        level:
                            'place',

                        place_id:
                            placeId,

                        area_id:
                            0,

                        site_id:
                            0,

                        selector_value:
                            '',

                        map_feature_id:
                            featureId,

                        parent_area_id:
                            0,

                        label
                    };
                };

            const announceSelection =
                (feature) => {
                    if (!feature) {
                        return;
                    }

                    const detail =
                        selectionDetail(
                            feature
                        );

                    window.dispatchEvent(
                        new CustomEvent(
                            'llama:map-feature-selected',
                            {
                                detail
                            }
                        )
                    );
                };

            let chooseFeature =
                () => {};

            const renderFeatureList =
                () => {
                    syncProductionLanguage(
                        featureType?.value
                        || 'camping_area',
                        currentSelectedFeatureId() > 0
                    );

                    if (!featureList) {
                        return;
                    }

                    const features =
                        currentFeatures();

                    featureList.replaceChildren();

                    if (fitAllAreasButton) {
                        fitAllAreasButton.disabled =
                            features.length === 0;
                    }

                    if (features.length === 0) {
                        const empty =
                            document.createElement(
                                'p'
                            );

                        empty.textContent =
                            'No mapped features yet.';

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

                            button.classList.toggle(
                                'is-active',
                                Number(feature.id)
                                    === currentSelectedFeatureId()
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
                                feature.source_type === 'gps'
                                    ? 'GPS'
                                    : (
                                        feature.source_type === 'mixed'
                                            ? 'Mixed'
                                            : ''
                                    );

                            const rateSummary =
                                feature.effective_rate_summary
                                || feature.rate_summary
                                || {};

                            const minimum =
                                Number(
                                    rateSummary.minimum
                                );

                            const maximum =
                                Number(
                                    rateSummary.maximum
                                );

                            const rateLabel =
                                Number.isFinite(minimum)
                                    ? (
                                        minimum === maximum
                                            ? `${rateMoney?.(minimum) || ''} / night`
                                            : `${rateMoney?.(minimum) || ''} - ${rateMoney?.(maximum) || ''} / night`
                                    )
                                    : '';

                            const siteLabels =
                                getSiteDetailLabels?.(
                                    feature.site_details
                                    || {}
                                );

                            const detailLabels = [
                                areaUseLabels?.[
                                    feature.area_details
                                        ?.area_use
                                ] || '',

                                feeStatusLabels?.[
                                    feature.area_details
                                        ?.fee_status
                                ] || '',

                                overnightStatusLabels?.[
                                    feature.area_details
                                        ?.overnight_status
                                ] || '',

                                ...(
                                    Array.isArray(siteLabels)
                                        ? siteLabels
                                        : []
                                ),

                                rateLabel
                            ].filter(Boolean);

                            type.textContent =
                                [
                                    featureTypeLabel(
                                        feature.feature_type
                                    ),
                                    ...detailLabels,
                                    source
                                ]
                                    .filter(Boolean)
                                    .join(' Â· ');

                            button.append(
                                name,
                                type
                            );

                            button.addEventListener(
                                'click',
                                () => {
                                    chooseFeature(
                                        Number(feature.id)
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
                    setSelectedFeatureId?.(0);
                    setDirty?.(false);

                    clearGeometry?.();

                    if (featureType) {
                        featureType.value =
                            'camping_area';
                    }

                    if (featureLabel) {
                        featureLabel.value =
                            '';
                    }

                    syncAreaDetailFields?.();
                    syncSiteFields?.();

                    if (
                        typeof clearSiteClasses
                        === 'function'
                    ) {
                        clearSiteClasses();
                    } else {
                        syncSiteClasses?.([]);
                    }

                    if (
                        typeof clearRates
                        === 'function'
                    ) {
                        clearRates();
                    } else {
                        syncRates?.([]);
                    }

                    if (drawButton) {
                        drawButton.disabled =
                            false;
                    }

                    if (finishButton) {
                        finishButton.disabled =
                            true;
                    }

                    if (undoButton) {
                        undoButton.disabled =
                            true;
                    }

                    if (saveButton) {
                        saveButton.disabled =
                            true;
                    }

                    if (deleteButton) {
                        deleteButton.disabled =
                            true;
                    }

                    if (moveAreaButton) {
                        moveAreaButton.disabled =
                            true;

                        moveAreaButton.textContent =
                            featureLanguage(
                                featureType?.value
                                || 'camping_area'
                            ).moveLabel;
                    }

                    syncFeatureSummary?.();

                    syncProductionLanguage(
                        featureType?.value
                        || 'camping_area',
                        false
                    );

                    if (!keepStatus) {
                        setStatus?.('');
                    }

                    renderExistingLayers?.();
                    renderFeatureList();
                };

            const selectFeature =
                (
                    id,
                    announce = true
                ) => {
                    const feature =
                        currentFeatures()
                            .find(
                                (item) =>
                                    Number(item.id)
                                    === Number(id)
                            );

                    if (!feature) {
                        return;
                    }

                    setSelectedFeatureId?.(
                        Number(feature.id)
                    );

                    setDirty?.(false);

                    if (featureType) {
                        featureType.value =
                            feature.feature_type;
                    }

                    if (featureLabel) {
                        featureLabel.value =
                            feature.label
                            || '';
                    }

                    syncAreaDetailFields?.(
                        feature.area_details
                        || {}
                    );

                    syncSiteClasses?.(
                        feature.site_classes
                        || []
                    );

                    syncSiteFields?.(
                        feature.site_details
                        || {}
                    );

                    syncRates?.(
                        feature.rates
                        || []
                    );

                    const points =
                        geometryToPoints(
                            feature.geometry
                        );

                    const metadata =
                        metadataForFeature(
                            feature,
                            points.length
                        );

                    setGeometry?.(
                        points,
                        metadata
                    );

                    if (drawButton) {
                        drawButton.disabled =
                            false;
                    }

                    if (finishButton) {
                        finishButton.disabled =
                            true;
                    }

                    if (undoButton) {
                        undoButton.disabled =
                            true;
                    }

                    if (deleteButton) {
                        deleteButton.disabled =
                            false;
                    }

                    if (moveAreaButton) {
                        moveAreaButton.disabled =
                            points.length < 3;

                        moveAreaButton.textContent =
                            featureLanguage(
                                featureType?.value
                                || 'camping_area'
                            ).moveLabel;
                    }

                    syncProductionLanguage(
                        feature.feature_type,
                        true
                    );

                    setStatus?.('');

                    renderExistingLayers?.();
                    renderFeatureList();
                    syncFeatureSummary?.();

                    const shapeLayer =
                        getShapeLayer?.();

                    if (
                        shapeLayer
                        && typeof shapeLayer.getBounds
                            === 'function'
                    ) {
                        map.fitBounds(
                            shapeLayer.getBounds(),
                            {
                                padding:
                                    [50, 50],

                                maxZoom:
                                    20
                            }
                        );
                    }

                    if (announce) {
                        announceSelection(
                            feature
                        );
                    }
                };

            chooseFeature =
                (id) => {
                    if (
                        getDirty?.()
                        && Number(id)
                            !== currentSelectedFeatureId()
                        && !window.confirm(
                            'Discard the unsaved map changes?'
                        )
                    ) {
                        return;
                    }

                    selectFeature(
                        id,
                        true
                    );
                };

            const selectFromPlaceContext =
                (event) => {
                    const detail =
                        event?.detail;

                    if (
                        !detail
                        || typeof detail !== 'object'
                    ) {
                        return;
                    }

                    const featureId =
                        Math.max(
                            0,
                            Number(
                                detail.map_feature_id
                                || 0
                            )
                        );

                    if (
                        featureId < 1
                        || featureId
                            === currentSelectedFeatureId()
                    ) {
                        return;
                    }

                    if (
                        getDirty?.()
                        && !window.confirm(
                            'Discard the unsaved map changes?'
                        )
                    ) {
                        return;
                    }

                    selectFeature(
                        featureId,
                        false
                    );
                };

            window.addEventListener(
                'llama:place-context-changed',
                selectFromPlaceContext
            );

            fitAllAreasButton
                ?.addEventListener(
                    'click',
                    fitAllAreas
                );

            return {
                fitAllAreas,
                renderFeatureList,
                resetEditor,
                selectFeature,
                chooseFeature,
                featureTypeLabel,
                geometryToPoints,
                metadataForFeature,
                selectionDetail,
                featureLanguage,
                syncProductionLanguage
            };
        };
})();
