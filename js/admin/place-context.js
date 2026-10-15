(() => {
    'use strict';

    const form =
        document.getElementById(
            'place-report'
        );

    const configNode =
        form?.querySelector(
            '[data-admin-place-context]'
        );

    if (!form || !configNode) {
        return;
    }

    let config;

    try {
        config =
            JSON.parse(
                configNode.textContent
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

    const areas =
        config.areas
        && typeof config.areas === 'object'
            ? config.areas
            : {};

    const sites =
        config.sites
        && typeof config.sites === 'object'
            ? config.sites
            : {};

    const areaSelector =
        () =>
            form.querySelector(
                '[data-admin-area-selector]'
            );

    const siteSelector =
        () =>
            form.querySelector(
                '[data-admin-campsite-selector]'
            );

    const normalizeSelectionLabels =
        () => {
            const selector =
                siteSelector();

            if (!selector) {
                return false;
            }

            const wrapper =
                selector.closest(
                    '.contribution-field'
                );

            const label =
                wrapper?.querySelector(
                    '.place-report-field-label > span:first-child'
                );

            if (label) {
                label.textContent =
                    'Site';
            }

            const first =
                selector.options[0];

            if (first) {
                first.textContent =
                    selector.options.length > 1
                        ? 'Place'
                        : 'Place - no Sites yet';
            }

            Array.from(
                selector.options
            )
                .slice(1)
                .forEach(
                    (option) => {
                        option.textContent =
                            option.textContent
                                .replace(
                                    /\s+-\s+Mapped$/i,
                                    ''
                                )
                                .replace(
                                    /\s+-\s+No mapped geometry$/i,
                                    ''
                                );
                    }
                );

            return true;
        };

    const current =
        () => {
            const siteValue =
                String(
                    siteSelector()?.value
                    || ''
                ).trim();

            if (
                siteValue !== ''
                && sites[siteValue]
            ) {
                const site =
                    sites[siteValue];

                return {
                    level:
                        'site',

                    place_id:
                        Number(
                            place.place_id
                            || 0
                        ),

                    area_id:
                        Number(
                            site.parent_area_id
                            || 0
                        ),

                    site_id:
                        Number(
                            site.site_id
                            || 0
                        ),

                    selector_value:
                        siteValue,

                    map_feature_id:
                        Number(
                            site.map_feature_id
                            || 0
                        ),

                    label:
                        String(
                            site.label
                            || 'Site'
                        )
                };
            }

            const areaValue =
                String(
                    areaSelector()?.value
                    || ''
                ).trim();

            if (
                areaValue !== ''
                && areas[areaValue]
            ) {
                const area =
                    areas[areaValue];

                return {
                    level:
                        'area',

                    place_id:
                        Number(
                            place.place_id
                            || 0
                        ),

                    area_id:
                        Number(
                            area.area_id
                            || 0
                        ),

                    site_id:
                        0,

                    selector_value:
                        areaValue,

                    map_feature_id:
                        Number(
                            area.map_feature_id
                            || 0
                        ),

                    label:
                        String(
                            area.label
                            || 'Area'
                        )
                };
            }

            return {
                level:
                    'place',

                place_id:
                    Number(
                        place.place_id
                        || 0
                    ),

                area_id:
                    0,

                site_id:
                    0,

                selector_value:
                    '',

                map_feature_id:
                    Number(
                        place.map_feature_id
                        || 0
                    ),

                label:
                    String(
                        place.label
                        || 'Place'
                    )
            };
        };

    let lastSignature = '';

    const emit =
        (
            source = 'ui'
        ) => {
            const context =
                current();

            const signature =
                JSON.stringify(
                    context
                );

            if (
                signature === lastSignature
                && source !== 'force'
            ) {
                return context;
            }

            lastSignature =
                signature;

            const detail = {
                ...context,
                source:
                    source === 'force'
                        ? 'ui'
                        : source
            };

            form.dispatchEvent(
                new CustomEvent(
                    'llama:place-context-changed',
                    {
                        bubbles:
                            true,

                        detail
                    }
                )
            );

            window.dispatchEvent(
                new CustomEvent(
                    'llama:place-context-changed',
                    {
                        detail
                    }
                )
            );

            return context;
        };

    const dispatchChange =
        (control) => {
            control?.dispatchEvent(
                new Event(
                    'change',
                    {
                        bubbles:
                            true
                    }
                )
            );
        };

    const clearSite =
        () => {
            const control =
                siteSelector();

            if (
                !control
                || control.value === ''
            ) {
                return true;
            }

            control.value =
                '';

            dispatchChange(
                control
            );

            return control.value === '';
        };

    const clearArea =
        () => {
            const control =
                areaSelector();

            if (
                !control
                || control.value === ''
            ) {
                return true;
            }

            control.value =
                '';

            dispatchChange(
                control
            );

            return control.value === '';
        };

    const selectPlace =
        () => {
            if (!clearSite()) {
                emit(
                    'selection-rejected'
                );

                return false;
            }

            if (!clearArea()) {
                emit(
                    'selection-rejected'
                );

                return false;
            }

            queueMicrotask(
                () =>
                    emit(
                        'place'
                    )
            );

            return true;
        };

    const selectAreaByFeatureId =
        (featureId) => {
            const id =
                Math.max(
                    0,
                    Number(
                        featureId
                        || 0
                    )
                );

            const entry =
                Object.values(
                    areas
                )
                    .find(
                        (area) =>
                            Number(
                                area.map_feature_id
                                || area.area_id
                                || 0
                            ) === id
                    );

            if (!entry) {
                return false;
            }

            if (!clearSite()) {
                emit(
                    'selection-rejected'
                );

                return false;
            }

            const control =
                areaSelector();

            if (!control) {
                return false;
            }

            control.value =
                String(
                    entry.selector_value
                    || ''
                );

            dispatchChange(
                control
            );

            queueMicrotask(
                () =>
                    emit(
                        'map'
                    )
            );

            return control.value !== '';
        };

    const selectSiteByValue =
        (selectorValue) => {
            const value =
                String(
                    selectorValue
                    || ''
                ).trim();

            if (
                value === ''
                || !sites[value]
            ) {
                return false;
            }

            if (!clearArea()) {
                emit(
                    'selection-rejected'
                );

                return false;
            }

            const control =
                siteSelector();

            if (!control) {
                return false;
            }

            control.value =
                value;

            dispatchChange(
                control
            );

            queueMicrotask(
                () =>
                    emit(
                        'map'
                    )
            );

            return control.value === value;
        };

    const selectSiteByMapFeatureId =
        (featureId) => {
            const id =
                Math.max(
                    0,
                    Number(
                        featureId
                        || 0
                    )
                );

            const entry =
                Object.values(
                    sites
                )
                    .find(
                        (site) =>
                            Number(
                                site.map_feature_id
                                || 0
                            ) === id
                    );

            if (!entry) {
                return false;
            }

            return selectSiteByValue(
                entry.selector_value
            );
        };

    const selectGeometryFeature =
        (featureId) => {
            const id =
                Math.max(
                    0,
                    Number(
                        featureId
                        || 0
                    )
                );

            if (id < 1) {
                return false;
            }

            if (
                Number(
                    place.map_feature_id
                    || 0
                ) === id
            ) {
                return selectPlace();
            }

            if (
                selectSiteByMapFeatureId(
                    id
                )
            ) {
                return true;
            }

            return selectAreaByFeatureId(
                id
            );
        };

    form.addEventListener(
        'change',
        (event) => {
            const target =
                event.target;

            if (
                target === areaSelector()
                || target === siteSelector()
            ) {
                queueMicrotask(
                    () =>
                        emit(
                            'selector'
                        )
                );
            }
        },
        true
    );

    form.addEventListener(
        'llama:campsite-selected',
        () => {
            queueMicrotask(
                () =>
                    emit(
                        'site'
                    )
            );
        }
    );


    window.addEventListener(
        'llama:map-feature-selected',
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

            if (featureId < 1) {
                return;
            }

            selectGeometryFeature(
                featureId
            );
        }
    );

    if (!normalizeSelectionLabels()) {
        const labelObserver =
            new MutationObserver(
                () => {
                    if (normalizeSelectionLabels()) {
                        labelObserver.disconnect();
                    }
                }
            );

        labelObserver.observe(
            form,
            {
                childList:
                    true,

                subtree:
                    true
            }
        );
    }

    window.LlamaPlaceContext = {
        get:
            current,

        emit,

        selectPlace,

        selectAreaByFeatureId,

        selectSiteByValue,

        selectSiteByMapFeatureId,

        selectGeometryFeature
    };

    emit(
        'force'
    );
})();
