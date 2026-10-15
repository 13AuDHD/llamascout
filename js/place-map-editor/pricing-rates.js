(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor =
            window.LlamaPlaceMapEditor || {};

    namespace.createPricingRates =
        ({
            editor,
            getFeatureType,
            markDirty,
            normalizeRates,
            ratePayload,
            rateField,
            rateTypeLabels,
            updateInheritance
        }) => {
            if (!editor) {
                throw new Error(
                    'Mapped-area rates require an editor element.'
                );
            }

            const section =
                editor.querySelector(
                    '[data-rate-section]'
                );

            const list =
                editor.querySelector(
                    '[data-rate-list]'
                );

            const addButton =
                editor.querySelector(
                    '[data-add-rate]'
                );

            const empty =
                editor.querySelector(
                    '[data-rate-empty]'
                );

            const inheritance =
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

            const supported =
                () =>
                    [
                        'camping_area',
                        'camping_site'
                    ].includes(
                        currentFeatureType()
                    );

            const newRate =
                () => ({
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

            const render =
                () => {
                    const canUseRates =
                        supported();

                    if (section) {
                        section.hidden =
                            !canUseRates;
                    }

                    if (
                        !canUseRates
                        || !list
                    ) {
                        if (empty) {
                            empty.hidden =
                                true;
                        }

                        if (inheritance) {
                            inheritance.hidden =
                                true;
                        }

                        return;
                    }

                    list.replaceChildren();

                    if (empty) {
                        empty.hidden =
                            workingRates.length
                            > 0;
                    }

                    updateInheritance?.();

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

                            Object.entries(
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

                                    render();
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

                            row.append(
                                rateField(
                                    'Rate type',
                                    typeSelect
                                ),

                                rateField(
                                    'Label',
                                    labelInput
                                ),

                                rateField(
                                    'Nightly amount (USD)',
                                    amountInput
                                )
                            );

                            if (
                                typeSelect.value
                                === 'seasonal'
                            ) {
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

                                row.append(
                                    rateField(
                                        'Season begins',
                                        startInput
                                    ),

                                    rateField(
                                        'Season ends',
                                        endInput
                                    )
                                );
                            }

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

                                    render();
                                }
                            );

                            row.append(
                                rateField(
                                    'Notes',
                                    notesInput
                                ),

                                removeButton
                            );

                            list.append(
                                row
                            );
                        }
                    );
                };

            const sync =
                (
                    rates = []
                ) => {
                    workingRates =
                        normalizeRates(
                            rates
                        );

                    render();
                };

            const payload =
                () =>
                    supported()
                        ? workingRates.map(
                            ratePayload
                        )
                        : [];

            const clear =
                () => {
                    workingRates =
                        [];

                    render();
                };

            addButton
                ?.addEventListener(
                    'click',
                    () => {
                        if (!supported()) {
                            return;
                        }

                        workingRates.push(
                            newRate()
                        );

                        markDirty?.(
                            true
                        );

                        render();
                    }
                );

            return {
                sync,
                render,
                payload,
                clear,
                supported
            };
        };
})();
