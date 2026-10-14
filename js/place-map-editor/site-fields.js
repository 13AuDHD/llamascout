(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor
        = window.LlamaPlaceMapEditor
        || {};

    const siteTypeLabels = {
        rv_site:
            'RV site',

        tent_site:
            'Tent site',

        mixed_site:
            'Mixed-use campsite',

        vehicle_site:
            'Vehicle campsite',

        group_site:
            'Group site',

        other:
            'Other'
    };

    const siteParkingStyleLabels = {
        pull_through:
            'Pull-through',

        back_in:
            'Back-in',

        pull_in:
            'Pull-in',

        parallel:
            'Parallel',

        other:
            'Other',

        unknown:
            'Unknown'
    };

    const siteHookupLabels = {
        full:
            'Full hookups',

        electric_water:
            'Electric + water',

        electric_only:
            'Electric only',

        water_only:
            'Water only',

        none:
            'No hookups',

        varies:
            'Varies',

        unknown:
            'Unknown'
    };

    const siteAccessibleLabels = {
        yes:
            'Accessible',

        no:
            'Not accessible',

        unknown:
            'Accessibility unknown'
    };

    const normalizeArray =
        (value) =>
            Array.isArray(value)
                ? value
                : [];

    const createOption =
        (
            value,
            label,
            disabled = false
        ) => {
            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(value);

            option.textContent =
                String(label);

            option.disabled =
                disabled === true;

            return option;
        };

    const optionExists =
        (
            select,
            value
        ) =>
            Boolean(
                select
                ?.querySelector(
                    `option[value="${CSS.escape(
                        String(value)
                    )}"]`
                )
            );

    namespace.createSiteFields =
        ({
            editor,
            getFeatureType,
            getFeatures,
            getCampsites,
            getSelectedFeatureId,
            markDirty,
            renderFeatureList,
            renderRates
        }) => {
            if (!editor) {
                throw new Error(
                    'Mapped Site fields require an editor element.'
                );
            }

            const siteDetails =
                editor.querySelector(
                    '[data-site-details]'
                );

            const siteCanonical =
                editor.querySelector(
                    '[data-site-canonical]'
                );

            const siteParent =
                editor.querySelector(
                    '[data-site-parent]'
                );

            const siteClass =
                editor.querySelector(
                    '[data-site-class]'
                );

            const siteCode =
                editor.querySelector(
                    '[data-site-code]'
                );

            const siteType =
                editor.querySelector(
                    '[data-site-type]'
                );

            const siteParkingStyle =
                editor.querySelector(
                    '[data-site-parking-style]'
                );

            const siteHookups =
                editor.querySelector(
                    '[data-site-hookups]'
                );

            const siteAccessible =
                editor.querySelector(
                    '[data-site-accessible]'
                );

            const currentFeatureType =
                () =>
                    String(
                        getFeatureType?.()
                        || ''
                    );

            const currentFeatures =
                () =>
                    normalizeArray(
                        getFeatures?.()
                    );

            const currentCampsites =
                () =>
                    normalizeArray(
                        getCampsites?.()
                    );

            const currentSelectedFeatureId =
                () =>
                    Math.max(
                        0,
                        Number(
                            getSelectedFeatureId?.()
                            || 0
                        )
                    );

            const isSite =
                () =>
                    currentFeatureType()
                    === 'camping_site';

            const parentCampingAreaForSite =
                () => {
                    const parentId =
                        Number(
                            siteParent?.value
                            || 0
                        );

                    if (parentId < 1) {
                        return null;
                    }

                    return (
                        currentFeatures()
                            .find(
                                (feature) =>
                                    Number(
                                        feature.id
                                    )
                                    === parentId
                                    && feature.feature_type
                                        === 'camping_area'
                            )
                        || null
                    );
                };

            const syncSiteClassOptions =
                (
                    desiredClassId = ''
                ) => {
                    if (!siteClass) {
                        return;
                    }

                    const parent =
                        parentCampingAreaForSite();

                    const classes =
                        normalizeArray(
                            parent?.site_classes
                        );

                    siteClass.replaceChildren();

                    siteClass.append(
                        createOption(
                            '',
                            classes.length > 0
                                ? 'No pricing class'
                                : 'No pricing classes available'
                        )
                    );

                    classes.forEach(
                        (item) => {
                            siteClass.append(
                                createOption(
                                    item.id,
                                    item.name
                                    || `Pricing class #${item.id}`
                                )
                            );
                        }
                    );

                    const desired =
                        String(
                            desiredClassId
                            || ''
                        );

                    siteClass.value =
                        desired !== ''
                        && optionExists(
                            siteClass,
                            desired
                        )
                            ? desired
                            : '';
                };

            const syncCanonicalCampsiteOptions =
                (
                    details = {}
                ) => {
                    if (!siteCanonical) {
                        return;
                    }

                    const selectedFeatureId =
                        currentSelectedFeatureId();

                    const desired =
                        isSite()
                            ? String(
                                details
                                    .canonical_campsite_id
                                || ''
                            )
                            : '';

                    siteCanonical.replaceChildren();

                    siteCanonical.append(
                        createOption(
                            '',
                            'Map-only Site'
                        )
                    );

                    currentCampsites()
                        .forEach(
                            (campsite) => {
                                const campsiteId =
                                    String(
                                        campsite.id
                                        || ''
                                    );

                                if (
                                    campsiteId
                                    === ''
                                ) {
                                    return;
                                }

                                const mappedFeatureId =
                                    Math.max(
                                        0,
                                        Number(
                                            campsite
                                                .map_feature_id
                                            || 0
                                        )
                                    );

                                const mappedElsewhere =
                                    mappedFeatureId > 0
                                    && mappedFeatureId
                                        !== selectedFeatureId;

                                const option =
                                    createOption(
                                        campsiteId,
                                        campsite.label
                                        || `Site #${campsiteId}`,
                                        mappedElsewhere
                                    );

                                if (
                                    mappedElsewhere
                                ) {
                                    option.textContent +=
                                        ' · mapped elsewhere';
                                }

                                /*
                                 * Historical data can contain a stale or
                                 * conflicting association. The currently
                                 * selected value must remain visible so the
                                 * editor can surface and repair it rather than
                                 * silently replacing it.
                                 */
                                if (
                                    campsiteId
                                    === desired
                                ) {
                                    option.disabled =
                                        false;
                                }

                                siteCanonical.append(
                                    option
                                );
                            }
                        );

                    siteCanonical.value =
                        optionExists(
                            siteCanonical,
                            desired
                        )
                            ? desired
                            : '';

                    siteCanonical.disabled =
                        !isSite();
                };

            const sync =
                (
                    details = {}
                ) => {
                    const active =
                        isSite();

                    if (siteDetails) {
                        siteDetails.hidden =
                            !active;
                    }

                    syncCanonicalCampsiteOptions(
                        details
                    );

                    if (!siteParent) {
                        return;
                    }

                    const desiredParent =
                        String(
                            details
                                .camping_area_feature_id
                            || ''
                        );

                    const campingAreas =
                        currentFeatures()
                            .filter(
                                (feature) =>
                                    feature.feature_type
                                        === 'camping_area'
                                    && feature.is_active
                                        !== false
                            );

                    siteParent.replaceChildren();

                    siteParent.append(
                        createOption(
                            '',
                            campingAreas.length > 0
                                ? 'Choose a Camping area'
                                : 'Create a Camping area first'
                        )
                    );

                    campingAreas.forEach(
                        (feature) => {
                            siteParent.append(
                                createOption(
                                    feature.id,
                                    feature.label
                                    || `Camping area #${feature.id}`
                                )
                            );
                        }
                    );

                    if (active) {
                        if (
                            desiredParent !== ''
                            && optionExists(
                                siteParent,
                                desiredParent
                            )
                        ) {
                            siteParent.value =
                                desiredParent;

                        } else if (
                            campingAreas.length
                            === 1
                        ) {
                            siteParent.value =
                                String(
                                    campingAreas[0].id
                                );

                        } else {
                            siteParent.value =
                                '';
                        }

                    } else {
                        siteParent.value =
                            '';
                    }

                    syncSiteClassOptions(
                        active
                            ? String(
                                details
                                    .site_class_id
                                || ''
                            )
                            : ''
                    );

                    if (siteCode) {
                        siteCode.value =
                            active
                                ? String(
                                    details.site_code
                                    || ''
                                )
                                : '';
                    }

                    const syncSelect =
                        (
                            select,
                            desiredValue
                        ) => {
                            if (!select) {
                                return;
                            }

                            const desired =
                                active
                                    ? String(
                                        desiredValue
                                        || ''
                                    )
                                    : '';

                            select.value =
                                optionExists(
                                    select,
                                    desired
                                )
                                    ? desired
                                    : '';
                        };

                    syncSelect(
                        siteType,
                        details.site_type
                    );

                    syncSelect(
                        siteParkingStyle,
                        details.parking_style
                    );

                    syncSelect(
                        siteHookups,
                        details.hookup_status
                    );

                    syncSelect(
                        siteAccessible,
                        details.accessible_status
                    );
                };

            const payload =
                () => {
                    if (!isSite()) {
                        return {};
                    }

                    return {
                        canonical_campsite_id:
                            siteCanonical?.value
                            || '',

                        camping_area_feature_id:
                            siteParent?.value
                            || '',

                        site_class_id:
                            siteClass?.value
                            || '',

                        site_code:
                            siteCode?.value
                            || '',

                        site_type:
                            siteType?.value
                            || '',

                        parking_style:
                            siteParkingStyle?.value
                            || '',

                        hookup_status:
                            siteHookups?.value
                            || '',

                        accessible_status:
                            siteAccessible?.value
                            || ''
                    };
                };

            const detailLabels =
                (
                    details = {}
                ) => {
                    const labels = [];

                    if (details.site_code) {
                        labels.push(
                            `Site ${details.site_code}`
                        );
                    }

                    if (
                        details
                            .canonical_campsite_label
                    ) {
                        labels.push(
                            `Linked: ${details.canonical_campsite_label}`
                        );
                    }

                    if (
                        details
                            .canonical_campsite_conflict
                    ) {
                        labels.push(
                            'Association conflict'
                        );
                    }

                    if (
                        details
                            .site_class_name
                    ) {
                        labels.push(
                            `Class: ${details.site_class_name}`
                        );
                    }

                    const typeLabel =
                        siteTypeLabels[
                            details.site_type
                        ];

                    if (typeLabel) {
                        labels.push(
                            typeLabel
                        );
                    }

                    const parkingLabel =
                        siteParkingStyleLabels[
                            details.parking_style
                        ];

                    if (parkingLabel) {
                        labels.push(
                            parkingLabel
                        );
                    }

                    const hookupLabel =
                        siteHookupLabels[
                            details.hookup_status
                        ];

                    if (hookupLabel) {
                        labels.push(
                            hookupLabel
                        );
                    }

                    return labels;
                };

            const notifyChange =
                () => {
                    markDirty?.(
                        true
                    );

                    renderFeatureList?.();
                    renderRates?.();
                };

            siteParent
                ?.addEventListener(
                    'change',
                    () => {
                        syncSiteClassOptions(
                            ''
                        );

                        notifyChange();
                    }
                );

            [
                siteClass,
                siteType,
                siteParkingStyle,
                siteHookups,
                siteAccessible,
                siteCanonical
            ]
                .forEach(
                    (control) => {
                        control
                            ?.addEventListener(
                                'change',
                                notifyChange
                            );
                    }
                );

            siteCode
                ?.addEventListener(
                    'input',
                    () => {
                        markDirty?.(
                            true
                        );

                        renderFeatureList?.();
                    }
                );

            return {
                sync,
                payload,
                detailLabels,
                syncSiteClassOptions,
                syncCanonicalCampsiteOptions,

                controls: {
                    siteDetails,
                    siteCanonical,
                    siteParent,
                    siteClass,
                    siteCode,
                    siteType,
                    siteParkingStyle,
                    siteHookups,
                    siteAccessible
                },

                labels: {
                    siteType:
                        siteTypeLabels,

                    parkingStyle:
                        siteParkingStyleLabels,

                    hookup:
                        siteHookupLabels,

                    accessible:
                        siteAccessibleLabels
                }
            };
        };
})();
