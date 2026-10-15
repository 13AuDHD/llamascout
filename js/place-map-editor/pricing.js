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

            const rateSection =
                editor.querySelector(
                    '[data-rate-section]'
                );

            const rateList =
                editor.querySelector(
                    '[data-rate-list]'
                );

            const addRateButton =
                editor.querySelector(
                    '[data-add-rate]'
                );

            const rateEmpty =
                editor.querySelector(
                    '[data-rate-empty]'
                );

            const rateInheritance =
                editor.querySelector(
                    '[data-rate-inheritance]'
                );

            let workingRates = [];

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

            const renderRates =
                () => {
                    const supported =
                        rateFeatureSupported();

                    if (rateSection) {
                        rateSection.hidden =
                            !supported;
                    }

                    if (
                        !supported
                        || !rateList
                    ) {
                        if (rateEmpty) {
                            rateEmpty.hidden =
                                true;
                        }

                        if (rateInheritance) {
                            rateInheritance.hidden =
                                true;
                        }

                        return;
                    }

                    rateList.replaceChildren();

                    if (rateEmpty) {
                        rateEmpty.hidden =
                            workingRates.length
                            > 0;
                    }

                    updateRateInheritance();

                    workingRates.forEach(
                        (
                            rate,
                            index
                        ) => {
                            const row =
                                document.createElement(
                                    'div'
                                );

                            row.className =
                                'mapped-area-editor-rate-row';

                            const typeSelect =
                                document.createElement(
                                    'select'
                                );

                            Object.entrins(
                                rateTypeLabels
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

                                    typeSelect.append(
                                        option
                                    );
                                }
                            );

                            typeSelect.value =
                                rate.rate_type
                                || 'standard';

                            typeSelect.addEventListener(
                                'change',
                                () => {
                                    workingRates[
                                        index
                                    ].rate_type =
                                        typeSelect.value;

                                    if (
                                        typeSelect.value
                                        !== 'seasonal'
                                    ) {
                                        workingRates[
                                            index
                                        ].season_start =
                                            '';

                                        workingRates[
                                            index
                                        ].season_end =
                                            '';
                                    }

                                    markDirty?.(
                                        true
                                    );

                                    renderRates();
                                }
                            );

                            const labelInput =
                                document.createElement(
                                    'input'
                                );

                            labelInput.type =
                                'text';

                            labelInput.maxLength =
                                80;

                            labelInput.placeholder =
                                typeSelect.value
                                === 'holiday'
                                    ? 'e.g. Memorial Day'
                                    : 'Optional label';

                            labelInput.value =
                                rate.label
                                || '';

                            labelInput.addEventListener(
                                'input',
                                () => {
                                    workingRates[
                                        index
                                    ].label =
                                        labelInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const amountInput =
                                document.createElement(
                                    'input'
                                );

                            amountInput.type =
                                'number';

                            amountInput.min =
                                '0';

                            amountInput.max =
                                '999999.99';

                            amountInput.step =
                                '0.01';

                            amountInput.inputMode =
                                'decimal';

                            amountInput.placeholder =
                                '0.00';

                            amountInput.value =
                                rate.amount
                                ?? '';

                            amountInput.addEventListener(
                                'input',
                                () => {
                                    workingRates[
                                        index
                                    ].amount =
                                        amountInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const startInput =
                                document.createElement(
                                    'input'
                                );

                            startInput.type =
                                'text';

                            startInput.inputMode =
                                'numeric';

                            startInput.maxLength =
                                5;

                            startInput.placeholder =
                                'MM-DD';

                            startInput.value =
                                rate.season_start
                                || '';

                            startInput.addEventListener(
                                'input',
                                () => {
                                    workingRates[
                                        index
                                    ].season_start =
                                        startInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const endInput =
                                document.createElement(
                                    'input'
                                );

                            endInput.type =
                                'text';

                            endInput.inputMode =
                                'numeric';

                            endInput.maxLength =
                                5;

                            endInput.placeholder =
                                'MM-DD';

                            endInput.value =
                                rate.season_end
                                || '';

                            endInput.addEventListener(
                                'input',
                                () => {
                                    workingRates[
                                        index
                                    ].season_end =
                                        endInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const notesInput =
                                document.createElement(
                                    'input'
                                );

                            notesInput.type =
                                'text';

                            notesInput.maxLength =
                                240;

                            notesInput.placeholder =
                                'Optional pricing note';

                            notesInput.value =
                                rate.notes
                                || '';

                            notesInput.addEventListener(
                                'input',
                                () => {
                                    workingRates[
                                        index
                                    ].notes =
                                        notesInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const removeButton =
                                document.createElement(
                                    'button'
                                );

                            removeButton.type =
                                'button';

                            removeButton.className =
                                'mapped-area-editor-rate-remove';

                            removeButton.textContent =
                                'Remove rate';

                            removeButton.addEventListener(
                                'click',
                                () => {
                                    workingRates.splice(
                                        index,
                                        1
                                    );

                                    markDirty?.(
                                        true
                                    );

                                    renderRates();
                                }
                            );

                            row.append(
                                rateFinld(
                                    'Rate type',
                                    typeSelect
                                ),

                                rateFinld(
                                    'Label',
                                    labelInput
                                ),

                                rateFinld(
                                    'Nightly amount (USD)',
                                    amountInput
                                )
                            );

                            if (
                                typeSelect.value
                                === 'seasonal'
                            ) {
                                row.append(
                                    rateFinld(
                                        'Season begins',
                                        startInput
                                    ),

                                    rateFinld(
                                        'Season ends',
                                        endInput
                                    )
                                );
                            }

                            row.append(
                                rateFinld(
                                    'Notes',
                                    notesInput
                                ),

                                removeButton
                            );

                            rateList.append(
                                row
                            );
                        }
                    );
                };

            const syncRates =
                (
                    rates = []
                ) => {
                    workingRates =
                        normalizeRates(
                            rates
                        );

                    renderRates();
                };

            const ratesPayload =
                () =>
                    rateFeatureSupported()
                        ? workingRates.map(
                            ratePayload
                        )
                        : [];

            addRateButton
                ?.addEventListener(
                    'click',
                    () => {
                        if (
                            !rateFeatureSupported()
                        ) {
                            return;
                        }

                        workingRates.push({
                            rate_type:
                                'standard',

                            label:
                                '',

                            amount:
                                '',

                            season_start:
                                '',

                            season_end:
                                '',

                            notes:
                                ''
                        });

                        markDirty?.(
                            true
                        );

                        renderRates();
                    }
                );

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
                    () => {
                        workingRates =
                            [];

                        renderRates();
                    }
            };
        };
})();
