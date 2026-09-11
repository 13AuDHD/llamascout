(() => {
    'use strict';

    const form =
        document.querySelector(
            '.add-place-form'
        );

    if (!form) {
        return;
    }


    /*
     * =====================================================
     * STATE
     * =====================================================
     */

    const states = [
        ['AL','Alabama'],['AK','Alaska'],['AZ','Arizona'],['AR','Arkansas'],
        ['CA','California'],['CO','Colorado'],['CT','Connecticut'],['DE','Delaware'],
        ['FL','Florida'],['GA','Georgia'],['HI','Hawaii'],['ID','Idaho'],
        ['IL','Illinois'],['IN','Indiana'],['IA','Iowa'],['KS','Kansas'],
        ['KY','Kentucky'],['LA','Louisiana'],['ME','Maine'],['MD','Maryland'],
        ['MA','Massachusetts'],['MI','Michigan'],['MN','Minnesota'],['MS','Mississippi'],
        ['MO','Missouri'],['MT','Montana'],['NE','Nebraska'],['NV','Nevada'],
        ['NH','New Hampshire'],['NJ','New Jersey'],['NM','New Mexico'],['NY','New York'],
        ['NC','North Carolina'],['ND','North Dakota'],['OH','Ohio'],['OK','Oklahoma'],
        ['OR','Oregon'],['PA','Pennsylvania'],['RI','Rhode Island'],['SC','South Carolina'],
        ['SD','South Dakota'],['TN','Tennessee'],['TX','Texas'],['UT','Utah'],
        ['VT','Vermont'],['VA','Virginia'],['WA','Washington'],['WV','West Virginia'],
        ['WI','Wisconsin'],['WY','Wyoming'],['DC','District of Columbia'],['PR','Puerto Rico']
    ];

    const stateAliases = new Map();

    states.forEach(([abbr, name]) => {
        stateAliases.set(
            abbr.toLowerCase(),
            name
        );

        stateAliases.set(
            name.toLowerCase(),
            name
        );
    });

    const normalizeState = (value) => {
        const clean =
            String(value ?? '')
                .trim();

        if (!clean) {
            return '';
        }

        return (
            stateAliases.get(
                clean.toLowerCase()
            )
            || clean
        );
    };

    const stateField =
        form.querySelector(
            '[data-location-field="state"], input[name="state"], select[name="state"]'
        );

    if (stateField) {
        const currentState =
            normalizeState(
                stateField.value
            );

        const stateSelect =
            document.createElement(
                'select'
            );

        stateSelect.name =
            'state';

        stateSelect.setAttribute(
            'data-location-field',
            'state'
        );

        const blank =
            document.createElement(
                'option'
            );

        blank.value = '';
        blank.textContent =
            'Select...';

        stateSelect.appendChild(
            blank
        );

        states.forEach(
            ([, name]) => {
                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    name;

                option.textContent =
                    name;

                if (
                    currentState === name
                ) {
                    option.selected =
                        true;
                }

                stateSelect.appendChild(
                    option
                );
            }
        );

        if (
            currentState
            && !states.some(
                ([, name]) =>
                    name === currentState
            )
        ) {
            const legacy =
                document.createElement(
                    'option'
                );

            legacy.value =
                currentState;

            legacy.textContent =
                `${currentState} (previous entry)`;

            legacy.selected =
                true;

            stateSelect.appendChild(
                legacy
            );
        }

        stateField.replaceWith(
            stateSelect
        );
    }


    /*
     * =====================================================
     * COUNTY LABEL
     * =====================================================
     */

    const countyField =
        form.querySelector(
            'input[name="county"], select[name="county"]'
        );

    if (countyField) {
        const countyLabel =
            countyField
                .closest(
                    '.contribution-field'
                )
                ?.querySelector(
                    ':scope > span'
                );

        if (countyLabel) {
            countyLabel.textContent =
                'County / Parish / Municipality';
        }
    }


    /*
     * =====================================================
     * NEARBY SERVICES
     *
     * Every nearby field means straight-forward approximate
     * distance from the Place, not a business or town name.
     * =====================================================
     */

    const nearbyFields = {
        nearest_town:
            'Distance to nearest town',

        nearest_fuel:
            'Distance to nearest fuel',

        nearest_grocery:
            'Distance to nearest grocery',

        nearest_water:
            'Distance to nearest potable water',

        nearest_toilet:
            'Distance to nearest public toilet',

        nearest_hospital:
            'Distance to nearest hospital / emergency care',
    };

    const distanceOptions =
        Array.from(
            { length: 20 },
            (_, index) => {
                const miles =
                    index + 1;

                return (
                    `${miles} mile`
                    + (
                        miles === 1
                            ? ''
                            : 's'
                    )
                );
            }
        );

    distanceOptions.push(
        'Over 20 miles'
    );

    Object.entries(
        nearbyFields
    ).forEach(
        ([name, labelText]) => {
            const field =
                form.querySelector(
                    `input[name="${name}"], select[name="${name}"]`
                );

            if (!field) {
                return;
            }

            const current =
                String(
                    field.value
                    || ''
                ).trim();

            const label =
                field
                    .closest(
                        '.contribution-field'
                    )
                    ?.querySelector(
                        ':scope > span'
                    );

            if (label) {
                label.textContent =
                    labelText;
            }

            const select =
                document.createElement(
                    'select'
                );

            select.name =
                name;

            const blank =
                document.createElement(
                    'option'
                );

            blank.value = '';
            blank.textContent =
                'Select...';

            select.appendChild(
                blank
            );

            distanceOptions.forEach(
                (value) => {
                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        value;

                    option.textContent =
                        value;

                    if (
                        current === value
                    ) {
                        option.selected =
                            true;
                    }

                    select.appendChild(
                        option
                    );
                }
            );

            /*
             * Preserve old draft values from before these
             * fields were standardized.
             */
            if (
                current
                && !distanceOptions.includes(
                    current
                )
            ) {
                const legacy =
                    document.createElement(
                        'option'
                    );

                legacy.value =
                    current;

                legacy.textContent =
                    `${current} (previous entry)`;

                legacy.selected =
                    true;

                select.appendChild(
                    legacy
                );
            }

            field.replaceWith(
                select
            );
        }
    );


    /*
     * =====================================================
     * FORM STATE
     * =====================================================
     */

    let initialKeys = [];

    try {
        const parsed =
            JSON.parse(
                form.dataset.formKeys
                || '[]'
            );

        if (Array.isArray(parsed)) {
            initialKeys =
                parsed.map(
                    (value) =>
                        String(value)
                );
        }
    } catch (_) {
        initialKeys = [];
    }

    const initialKeySet =
        new Set(initialKeys);

    const excludedNames =
        new Set([
            'land_manager',
            'land_type',
            'state',
            ...Object.keys(
                nearbyFields
            ),
        ]);


    /*
     * =====================================================
     * RADIO CONTROL HELPERS
     * =====================================================
     */

    const selectElements =
        [
            ...form.querySelectorAll(
                '.contribution-field select'
            ),
        ];

    const optionValues = (select) =>
        [...select.options].map(
            (option) =>
                String(option.value)
        );

    const isYesNoUnknown = (select) => {
        const values =
            optionValues(select);

        return (
            values.length === 3
            && values.includes('')
            && values.includes('1')
            && values.includes('0')
        );
    };

    const isRating = (select) => {
        const values =
            optionValues(select);

        if (
            values.length !== 6
            || !values.includes('')
        ) {
            return false;
        }

        return [1, 2, 3, 4, 5]
            .every(
                (number) =>
                    values.includes(
                        String(number)
                    )
            );
    };

    const visibleOptionLabel = (
        option,
        type
    ) => {
        const value =
            String(option.value);

        if (value === '') {
            return '?';
        }

        if (type === 'rating') {
            return value;
        }

        return String(
            option.textContent
            || value
        ).trim();
    };

    const ratingHelp = (select) => {
        const one =
            [...select.options]
                .find(
                    (option) =>
                        String(option.value)
                        === '1'
                );

        const five =
            [...select.options]
                .find(
                    (option) =>
                        String(option.value)
                        === '5'
                );

        const endpoint = (
            option,
            fallback
        ) => {
            const text =
                String(
                    option?.textContent
                    || ''
                )
                .replace(/\s+/g, ' ')
                .trim();

            const pieces =
                text.split(/\s+-\s+/);

            return (
                pieces[1]
                || fallback
            ).trim();
        };

        return {
            low:
                endpoint(
                    one,
                    'Low'
                ),

            high:
                endpoint(
                    five,
                    'High'
                ),
        };
    };

    const currentHidden = (name) =>
        form.querySelector(
            `input[data-radio-control-value="${CSS.escape(name)}"]`
        );

    const setHiddenValue = (
        name,
        value
    ) => {
        let hidden =
            currentHidden(name);

        if (!hidden) {
            hidden =
                document.createElement(
                    'input'
                );

            hidden.type =
                'hidden';

            hidden.name =
                name;

            hidden.dataset
                .radioControlValue =
                name;

            form.appendChild(
                hidden
            );
        }

        hidden.value =
            value;
    };

    const clearHiddenValue = (
        name
    ) => {
        currentHidden(name)
            ?.remove();
    };

    const escapeHtml = (value) => {
        const node =
            document.createElement(
                'div'
            );

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    };


    /*
     * =====================================================
     * RADIO CONTROL BUILDER
     *
     * Only Yes / No / Unknown and 1-5 ratings become radio
     * rows. Written-choice questions remain dropdowns.
     * =====================================================
     */

    const buildRadioControl = (
        select
    ) => {
        const name =
            String(
                select.name
                || ''
            ).trim();

        if (
            !name
            || excludedNames.has(name)
        ) {
            return;
        }

        const options =
            [...select.options];

        const type =
            isYesNoUnknown(select)
                ? 'yes-no'
                : isRating(select)
                    ? 'rating'
                    : 'choice';

        if (type === 'choice') {
            const firstOption =
                select.options[0];

            if (
                firstOption
                && String(
                    firstOption.value
                ) === ''
            ) {
                firstOption.textContent =
                    'Select...';
            }

            return;
        }

        const field =
            select.closest(
                '.contribution-field'
            );

        if (!field) {
            return;
        }

        const title =
            field.querySelector(
                ':scope > span'
            );

        if (!title) {
            return;
        }

        const initialValue =
            String(
                select.value
            );

        const hasInitialAnswer =
            initialValue !== ''
            || initialKeySet.has(name);

        select.disabled = true;
        select.hidden = true;

        const wrapper =
            document.createElement(
                'div'
            );

        wrapper.className =
            'add-place-radio-control';

        wrapper.dataset.radioType =
            type;

        wrapper.dataset.radioName =
            name;

        const grid =
            document.createElement(
                'div'
            );

        grid.className =
            'add-place-radio-options';

        options.forEach(
            (option) => {
                const value =
                    String(
                        option.value
                    );

                const label =
                    document.createElement(
                        'label'
                    );

                label.className =
                    'add-place-radio-option';

                const input =
                    document.createElement(
                        'input'
                    );

                input.type =
                    'radio';

                input.name =
                    `ui_${name}`;

                input.value =
                    value;

                input.setAttribute(
                    'aria-label',
                    value === ''
                        ? 'Unknown'
                        : String(
                            option.textContent
                            || value
                        ).trim()
                );

                if (
                    hasInitialAnswer
                    && value === initialValue
                ) {
                    input.checked =
                        true;
                }

                const text =
                    document.createElement(
                        'span'
                    );

                text.textContent =
                    visibleOptionLabel(
                        option,
                        type
                    );

                if (value === '') {
                    label.classList.add(
                        'is-unknown'
                    );
                }

                input.addEventListener(
                    'change',
                    () => {
                        if (!input.checked) {
                            return;
                        }

                        setHiddenValue(
                            name,
                            value
                        );

                        wrapper.classList.add(
                            'has-answer'
                        );
                    }
                );

                label.append(
                    input,
                    text
                );

                grid.appendChild(
                    label
                );
            }
        );

        const clear =
            document.createElement(
                'button'
            );

        clear.type =
            'button';

        clear.className =
            'add-place-radio-clear';

        clear.textContent =
            'Clear';

        clear.setAttribute(
            'aria-label',
            `Clear ${title.textContent.trim()}`
        );

        clear.addEventListener(
            'click',
            () => {
                grid
                    .querySelectorAll(
                        'input[type="radio"]'
                    )
                    .forEach(
                        (input) => {
                            input.checked =
                                false;
                        }
                    );

                clearHiddenValue(
                    name
                );

                wrapper.classList.remove(
                    'has-answer'
                );
            }
        );

        const row =
            document.createElement(
                'div'
            );

        row.className =
            'add-place-radio-row';

        row.append(
            grid,
            clear
        );

        wrapper.appendChild(
            row
        );

        const help =
            document.createElement(
                'div'
            );

        help.className =
            'add-place-radio-help';

        if (type === 'rating') {
            const ends =
                ratingHelp(select);

            help.innerHTML =
                `<span>1 = ${escapeHtml(ends.low)}</span>`
                + `<span>5 = ${escapeHtml(ends.high)}</span>`
                + '<span>? = Unknown</span>';
        } else {
            help.classList.add(
                'is-simple'
            );

            help.textContent =
                '? = Unknown / could not confidently determine';
        }

        wrapper.appendChild(
            help
        );

        title.insertAdjacentElement(
            'afterend',
            wrapper
        );

        if (hasInitialAnswer) {
            setHiddenValue(
                name,
                initialValue
            );

            wrapper.classList.add(
                'has-answer'
            );
        }
    };

    selectElements.forEach(
        buildRadioControl
    );
})();
