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

    /*
     * Reconciliation shows every Site question, but it must never make
     * a field editable under a scope that cannot own its answer.
     * Both key sets come from the canonical field registry on the server.
     */
    const scopeConfigNode =
        form.querySelector('[data-admin-place-campsites]');
    let scopeConfig = {};

    try {
        scopeConfig = JSON.parse(
            scopeConfigNode?.textContent || '{}'
        );
    } catch (_) {
        scopeConfig = {};
    }

    const siteFieldKeys = new Set(
        Array.isArray(scopeConfig.site_field_keys)
            ? scopeConfig.site_field_keys : []
    );

    const placeFieldKeys = new Set(
        Array.isArray(scopeConfig.place_field_keys)
            ? scopeConfig.place_field_keys : []
    );

    const restrictedKeys = [
        ...new Set([
            ...siteFieldKeys,
            ...placeFieldKeys
        ])
    ];

    const scopedControls = new Map();
    const initialDisabled = new WeakMap();

    restrictedKeys.forEach((key) => {
        const group = Array.from(form.elements).filter(
            (control) => control.name === key
        );
        scopedControls.set(key, group);
        group.forEach((control) => {
            initialDisabled.set(control, control.disabled);
        });
    });

    const ownershipNotice = document.createElement('span');
    ownershipNotice.className = 'admin-place-muted';
    ownershipNotice.setAttribute('role', 'status');
    const reportHeader = form.querySelector(
        '.admin-place-report-header'
    );

    if (reportHeader) {
        reportHeader.appendChild(ownershipNotice);
    }

    const syncFieldOwnership = () => {
        const siteActive = current().level === 'site';
        const bulkEnabled = !siteActive && Boolean(
            form.querySelector('[name="admin_bulk_site_answers_json"]')
            && form.querySelector('[data-admin-bulk-site-toggle]')?.checked
        );
        let restricted = 0;

        for (const [key, group] of scopedControls) {
            const permitted = siteActive
                ? siteFieldKeys.has(key)
                : (placeFieldKeys.has(key) || (bulkEnabled && siteFieldKeys.has(key)));

            for (const control of group) {
                control.disabled =
                    initialDisabled.get(control) || !permitted;

                const wrapper = control.closest(
                    '.contribution-field, .contribution-check'
                );

                if (wrapper) {
                    wrapper.dataset.reportScopeOwnership =
                        permitted ? '' : (siteActive ? 'place' : 'site');
                }
            }

            if (!permitted && group.length) {
                restricted++;
            }
        }

        ownershipNotice.textContent = restricted
            ? (
                siteActive
                    ? 'Place-only questions remain visible for reconciliation. Select Place to edit those answers.'
                    : 'Site-only questions remain visible for reconciliation. Select a Site to edit those answers.'
            )
            : '';
    };

    form.addEventListener('llama:admin-bulk-site-mode-changed', () => {
        queueMicrotask(syncFieldOwnership);
    });

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
                queueMicrotask(syncFieldOwnership);
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

            // Run after the shared Site editor has synchronized controls.
            queueMicrotask(syncFieldOwnership);

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

    const areaEntryForSite =
        (site) => {
            const parentAreaId =
                Math.max(
                    0,
                    Number(
                        site?.parent_area_id
                        || 0
                    )
                );

            if (parentAreaId < 1) {
                return null;
            }

            return (
                Object.values(
                    areas
                )
                    .find(
                        (area) =>
                            Number(
                                area.area_id
                                || area.map_feature_id
                                || 0
                            ) === parentAreaId
                    )
                || null
            );
        };

    const syncParentAreaForSite =
        (site) => {
            const control =
                areaSelector();

            if (!control) {
                return true;
            }

            const area =
                areaEntryForSite(
                    site
                );

            const next =
                area
                    ? String(
                        area.selector_value
                        || ''
                    )
                    : '';

            if (
                control.value
                === next
            ) {
                return true;
            }

            const previous =
                control.value;

            control.value =
                next;

            dispatchChange(
                control
            );

            return control.value
                === next
                || control.value
                    === previous;
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

            if (
                control.value
                !== value
            ) {
                emit(
                    'selection-rejected'
                );

                return false;
            }

            syncParentAreaForSite(
                sites[value]
            );

            queueMicrotask(
                () =>
                    emit(
                        'map'
                    )
            );

            return true;
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
                target
                === siteSelector()
            ) {
                const value =
                    String(
                        target.value
                        || ''
                    ).trim();

                if (
                    value !== ''
                    && sites[value]
                ) {
                    queueMicrotask(
                        () => {
                            syncParentAreaForSite(
                                sites[value]
                            );

                            emit(
                                'selector'
                            );
                        }
                    );

                    return;
                }
            }

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

    window.LlamaPlaceContext = {
        get:
            current,

        emit,

        selectPlace,

        selectAreaByFeatureId,

        selectSiteByValue,

        selectSiteByMapFeatureId,

        selectGeometryFeature,

        areaEntryForSite,

        syncParentAreaForSite
    };

    emit(
        'force'
    );
})();
