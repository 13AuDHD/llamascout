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

            let chooseFeature =
                () => {};

            const renderFeatureList =
                () => {
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
                                            : `${rateMoney?.(minimum) || ''}â${rateMoney?.(maximum) || ''} / night`
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
                                    .join(' · ');

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
                            'Move whole area';
                    }

                    syncFeatureSummary?.();

                    setHelp?.(
                        'Choose Draw area and tap around the outside edge, or use device GPS below to collect the points while in the field.'
                    );

                    if (!keepStatus) {
                        setStatus?.('');
                    }

                    renderExistingLayers?.();
                    renderFeatureList();
                };

            const selectFeature =
                (id) => {
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
                            'Move whole area';
                    }

                    setHelp?.(
                        'Drag any point to correct this area. Save when finished. Choose Draw area if you want to replace the shape completely.'
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
                };

            chooseFeature =
                (id) => {
                    if (
                        getDirty?.()
                        && Number(id)
                            !== currentSelectedFeatureId()
                        && !window.confirm(
                            'Discard the unsaved mapped-area changes?'
                        )
                    ) {
                        return;
                    }

                    selectFeature(id);
                };

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
                metadataForFeature
            };
        };
})();
