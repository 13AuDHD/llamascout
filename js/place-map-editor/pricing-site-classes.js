(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor =
            window.LlamaPlaceMapEditor || {};

    namespace.createPricingSiteClasses =
        ({
            editor,
            getFeatureType,
            markDirty,
            normalizeRates,
            ratePayload,
            rateField,
            rateTypeLabels
        }) => {
            if (!editor) {
                throw new Error(
                    'Site pricing classes require an editor element.'
                );
            }

            const section =
                editor.querySelector(
                    '[data-site-classes-section]'
                );

            const list =
                editor.querySelector(
                    '[data-site-class-list]'
                );

            const addButton =
                editor.querySelector(
                    '[data-add-site-class]'
                );

            const empty =
                editor.querySelector(
                    '[data-site-class-empty]'
                );

            let workingClasses = [];

            const currentFeatureType =
                () =>
                    String(
                        getFeatureType?.()
                        || ''
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

            const renderRateRow =
                (
                    item,
                    rate,
                    rateIndex,
                    rerender
                ) => {
                    const row =
                        document.createElement(
                            'div'
                        );

                    row.className =
                        'mapped-area-editor-site-class-rate';

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

                    if (
                        typeSelect.value
                        === 'seasonal'
                    ) {
                        row.classList.add(
                            'is-seasonal'
                        );
                    }

                    typeSelect.addEventListener(
                        'change',
                        () => {
                            rate.rate_type =
                                typeSelect.value;

                            if (
                                typeSelect.value
                                !== 'seasonal'
                            ) {
                                rate.season_start =
                                    '';

                                rate.season_end =
                                    '';
                            }

                            markDirty?.(
                                true
                            );

                            rerender();
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
                            rate.label =
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
                            rate.amount =
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

                        startInput.maxLength =
                            5;

                        startInput.inputMode =
                            'numeric';

                        startInput.placeholder =
                            'MM-DD';

                        startInput.value =
                            rate.season_start
                            || '';

                        startInput.addEventListener(
                            'input',
                            () => {
                                rate.season_start =
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

                        endInput.maxLength =
                            5;

                        endInput.inputMode =
                            'numeric';

                        endInput.placeholder =
                            'MM-DD';

                        endInput.value =
                            rate.season_end
                            || '';

                        endInput.addEventListener(
                            'input',
                            () => {
                                rate.season_end =
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
                            rate.notes =
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
                        'mapped-area-editor-site-class-rate-remove';

                    removeButton.textContent =
                        'Remove rate';

                    removeButton.addEventListener(
                        'click',
                        () => {
                            item.rates.splice(
                                rateIndex,
                                1
                            );

                            markDirty?.(
                                true
                            );

                            rerender();
                        }
                    );

                    const notesField =
                        rateField(
                            'Notes',
                            notesInput
                        );

                    notesField.className =
                        'mapped-area-editor-site-class-rate-notes';

                    row.append(
                        notesField,
                        removeButton
                    );

                    return row;
                };

            const render =
                () => {
                    const supported =
                        currentFeatureType()
                        === 'camping_area';

                    if (section) {
                        section.hidden =
                            !supported;
                    }

                    if (
                        !supported
                        || !list
                    ) {
                        if (empty) {
                            empty.hidden =
                                true;
                        }

                        return;
                    }

                    list.replaceChildren();

                    if (empty) {
                        empty.hidden =
                            workingClasses.length
                            > 0;
                    }

                    workingClasses.forEach(
                        (
                            item,
                            classIndex
                        ) => {
                            const card =
                                document.createElement(
                                    'div'
                                );

                            card.className =
                                'mapped-area-editor-site-class';

                            const heading =
                                document.createElement(
                                    'div'
                                );

                            heading.className =
                                'mapped-area-editor-site-class-heading';

                            const nameInput =
                                document.createElement(
                                    'input'
                                );

                            nameInput.type =
                                'text';

                            nameInput.maxLength =
                                80;

                            nameInput.placeholder =
                                'e.g. Standard Back-In';

                            nameInput.value =
                                item.name
                                || '';

                            nameInput.addEventListener(
                                'input',
                                () => {
                                    workingClasses[
                                        classIndex
                                    ].name =
                                        nameInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const descriptionInput =
                                document.createElement(
                                    'input'
                                );

                            descriptionInput.type =
                                'text';

                            descriptionInput.maxLength =
                                240;

                            descriptionInput.placeholder =
                                'Optional description';

                            descriptionInput.value =
                                item.description
                                || '';

                            descriptionInput.addEventListener(
                                'input',
                                () => {
                                    workingClasses[
                                        classIndex
                                    ].description =
                                        descriptionInput.value;

                                    markDirty?.(
                                        true
                                    );
                                }
                            );

                            const removeClassButton =
                                document.createElement(
                                    'button'
                                );

                            removeClassButton.type =
                                'button';

                            removeClassButton.className =
                                'mapped-area-editor-site-class-remove';

                            removeClassButton.textContent =
                                'Remove class';

                            removeClassButton.addEventListener(
                                'click',
                                () => {
                                    workingClasses.splice(
                                        classIndex,
                                        1
                                    );

                                    markDirty?.(
                                        true
                                    );

                                    render();
                                }
                            );

                            heading.append(
                                rateField(
                                    'Class name',
                                    nameInput
                                ),

                                rateField(
                                    'Description',
                                    descriptionInput
                                ),

                                removeClassButton
                            );

                            const rates =
                                document.createElement(
                                    'div'
                                );

                            rates.className =
                                'mapped-area-editor-site-class-rates';

                            item.rates.forEach(
                                (
                                    rate,
                                    rateIndex
                                ) => {
                                    rates.append(
                                        renderRateRow(
                                            item,
                                            rate,
                                            rateIndex,
                                            render
                                        )
                                    );
                                }
                            );

                            const footer =
                                document.createElement(
                                    'div'
                                );

                            footer.className =
                                'mapped-area-editor-site-class-footer';

                            const note =
                                document.createElement(
                                    'p'
                                );

                            note.textContent =
                                item.rates.length > 0
                                    ? `${item.rates.length} class rate${item.rates.length === 1 ? '' : 's'}`
                                    : 'No rates in this class yet.';

                            const addRate =
                                document.createElement(
                                    'button'
                                );

                            addRate.type =
                                'button';

                            addRate.className =
                                'mapped-area-editor-site-class-add-rate';

                            addRate.textContent =
                                'Add class rate';

                            addRate.addEventListener(
                                'click',
                                () => {
                                    item.rates.push(
                                        newRate()
                                    );

                                    markDirty?.(
                                        true
                                    );

                                    render();
                                }
                            );

                            footer.append(
                                note,
                                addRate
                            );

                            card.append(
                                heading,
                                rates,
                                footer
                            );

                            list.append(
                                card
                            );
                        }
                    );
                };

            const sync =
                (
                    classes = []
                ) => {
                    workingClasses =
                        Array.isArray(
                            classes
                        )
                            ? classes.map(
                                (item) => ({
                                    id:
                                        Number(
                                            item.id
                                            || 0
                                        ),

                                    name:
                                        String(
                                            item.name
                                            || ''
                                        ),

                                    description:
                                        String(
                                            item.description
                                            || ''
                                        ),

                                    rates:
                                        normalizeRates(
                                            item.rates
                                        )
                                })
                            )
                            : [];

                    render();
                };

            const payload =
                () =>
                    currentFeatureType()
                    === 'camping_area'
                        ? workingClasses.map(
                            (item) => ({
                                id:
                                    Number(
                                        item.id
                                        || 0
                                    ),

                                name:
                                    item.name
                                    || '',

                                description:
                                    item.description
                                    || '',

                                rates:
                                    item.rates.map(
                                        ratePayload
                                    )
                            })
                        )
                        : [];

            const clear =
                () => {
                    workingClasses =
                        [];

                    render();
                };

            addButton
                ?.addEventListener(
                    'click',
                    () => {
                        if (
                            currentFeatureType()
                            !== 'camping_area'
                        ) {
                            return;
                        }

                        workingClasses.push({
                            id:
                                0,

                            name:
                                '',

                            description:
                                '',

                            rates:
                                []
                        });

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
                clear
            };
        };
})();
