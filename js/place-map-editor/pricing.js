(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor
        = window.LlamaPlaceMapEditor
        || {};

    const rateTypeLabels = {
        standard:
            'Standard nightly',

        weekday:
            'Weekday',

        weekend:
            'Weekend',

        holiday:
            'Holiday',

        seasonal:
            'Seasonal'
    };

    const normalizeRates =
        (rates = []) =>
            Array.isArray(rates)
                ? rates.map(
                    (rate) => ({
                        rate_type:
                            String(
                                rate.rate_type
                                || 'standard'
                            ),

                        label:
                            String(
                                rate.label
                                || ''
                            ),

                        amount:
                            rate.amount
                            ?? '',

                        season_start:
                            String(
                                rate.season_start
                                || ''
                            ),

                        season_end:
                            String(
                                rate.season_end
                                || ''
                            ),

                        notes:
                            String(
                                rate.notes
                                || ''
                            )
                    })
                )
                : [];

    const ratePayload =
        (rate) => ({
            rate_type:
                rate.rate_type
                || 'standard',

            label:
                rate.label
                || '',

            amount:
                rate.amount
                ?? '',

            season_start:
                rate.season_start
                || '',

            season_end:
                rate.season_end
                || '',

            notes:
                rate.notes
                || ''
        });

    const rateFind =
        (
            labelText,
            control
        ) => {
            const label =
                document.createElement(
                    'label'
                );

            const span =
                document.createElement(
                    'span'
                );

            span.textContent =
                labelText;

            label.append(
                span,
                control
            );

            return label;
        };

    const rateMoney =
        (amount) => {
            const value =
                Number(amount);

            if (
                !Number.isFinite(
                    value
                )
            ) {
                return '';
            }

            return new Intl.NumberFormat(
                undefined,
                {
                    style:
                        'currency',

                    currency:
                        'USD',

                    minimumFractionDigits:
                        2,

                    maximumFractionDigits:
                        2
                }
            ).format(
                value
            );
        };

    namespace.createPricing =
        ({
            editor,
            getFeatureType,
            getFeatures,
            getSelectedFeatureId,
            getSiteParentId,
            getSiteClassId,
            markDirty
        }) => {
            if (!editor) {
                throw new Error(
                    'Mapped-area pricing requires an editor element.'
                );
            }

            const rateInheritance =
                editor.querySelector(
                    '[data-rate-inheritance]'
                );


            const currentFeatureType =
                () =>
                    String(
                        getFeatureType?.()
                        || ''
                    );

            const currentFeatures =
                () =>
                    Array.isArray(
                        getFeatures?.()
                    )
                        ? getFeatures()
                        : [];

            const currentFeature =
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

            const rateFeatureSupported =
                () =>
                    [
                        'camping_area',
                        'camping_site'
                    ].includes(
                        currentFeatureType()
                    );

            const rateParentFeature =
                () => {
                    if (
                        currentFeatureType()
                        !== 'camping_site'
                    ) {
                        return null;
                    }

                    const parentId =
                        Number(
                            getSiteParentId?.()
                            || currentFeature()
                                ?.site_details
                                ?.camping_area_feature_id
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

            if (
                typeof namespace
                    .createPricingSiteClasses
                !== 'function'
            ) {
                throw new Error(
                    'Site pricing-class module is required.'
                );
            }

            const siteClasses =
                namespace.createPricingSiteClasses({
                    editor,
                    getFeatureType,
                    markDirty,
                    normalizeRates,
                    ratePayload,
                    rateField,
                    rateTypeLabels
                });

            const syncSiteClasses =
                (
                    classes = []
                ) =>
                    siteClasses.sync(
                        classes
                    );

            const renderSiteClasses =
                () =>
                    siteClasses.render();

            const siteClassesPayload =
                () =>
                    siteClasses.payload();

            const updateRateInheritance =
                () => {
                    if (!rateInheritance) {
                        return;
                    }

                    rateInheritance.hidden =
                        true;

                    rateInheritance.replaceChildren();

                    if (
                        currentFeatureType()
                        !== 'camping_site'
                    ) {
                        return;
                    }

                    const parent =
                        rateParentFeature();

                    if (!parent) {
                        return;
                    }

                    if (
                        typeof namespace
                            .renderPricingInheritance
                        !== 'function'
                    ) {
                        return;
                    }

                    namespace.renderPricingInheritance({
                        container:
                            rateInheritance,

                        parent,

                        selectedClassId:
                            Number(
                                getSiteClassId?.()
                                || 0
                            ),

                        rateMoney
                    });
                };

            if (
                typeof namespace
                    .createPricingRates
                !== 'function'
            ) {
                throw new Error(
                    'Mapped-area rate module is required.'
                );
            }

            const rates =
                namespace.createPricingRates({
                    editor,
                    getFeatureType,
                    markDirty,
                    normalizeRates,
                    ratePayload,
                    rateField,
                    rateTypeLabels,
                    updateInheritance:
                        updateRateInheritance
                });

            const syncRates =
                (
                    values = []
                ) =>
                    rates.sync(
                        values
                    );

            const renderRates =
                () =>
                    rates.render();

            const ratesPayload =
                () =>
                    rates.payload();

            return {
                syncSiteClasses,
                renderSiteClasses,
                siteClassesPayload,
                syncRates,
                renderRates,
                ratesPayload,
                rateFeatureSupported,
                rateMoney,

                clearSiteClasses:
                    () =>
                        siteClasses.clear(),

                clearRates:
                    () =>
                        rates.clear()
            };
        };
})();
