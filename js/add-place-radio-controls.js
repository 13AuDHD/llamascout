(() => {
    'use strict';

    const form =
        document.querySelector(
            '.add-place-form'
        );

    if (!form) {
        return;
    }

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
        ]);

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

        const text =
            String(
                option.textContent
                || ''
            ).trim();

        if (type === 'rating') {
            return value === ''
                ? '?'
                : value;
        }

        if (
            type === 'yes-no'
            && value === ''
        ) {
            return '?';
        }

        return text;
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

        const cleanEndpoint = (
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

            const parts =
                text.split(/\s+-\s+/);

            return (
                parts[1]
                || fallback
            ).trim();
        };

        return {
            low:
                cleanEndpoint(
                    one,
                    'Low'
                ),
            high:
                cleanEndpoint(
                    five,
                    'High'
                ),
        };
    };

    const makeHiddenValue = (
        name,
        value
    ) => {
        const hidden =
            document.createElement(
                'input'
            );

        hidden.type =
            'hidden';

        hidden.name =
            name;

        hidden.value =
            value;

        hidden.dataset
            .radioControlValue =
            name;

        return hidden;
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
                makeHiddenValue(
                    name,
                    value
                );

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

        if (
            options.length < 2
            || options.length > 12
        ) {
            return;
        }

        const type =
            isYesNoUnknown(select)
                ? 'yes-no'
                : isRating(select)
                    ? 'rating'
                    : 'choice';

        /*
         * Longer written-choice questions stay as native
         * dropdowns. The first empty option is the untouched
         * state and reads "Select...".
         */
        if (type === 'choice') {
            const firstOption =
                select.options[0];

            if (
                firstOption
                && String(firstOption.value) === ''
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

        const hadInitialKey =
            initialKeySet.has(name);

        const initialValue =
            String(select.value);

        /*
         * A non-empty server-selected value is intentional.
         * Empty is intentional only when the field existed in
         * POST/draft data. Otherwise it is untouched.
         */
        const hasInitialAnswer =
            initialValue !== ''
            || hadInitialKey;

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

        if (type === 'rating') {
            const helpValues =
                ratingHelp(select);

            const help =
                document.createElement(
                    'div'
                );

            help.className =
                'add-place-radio-help';

            help.innerHTML =
                `<span>1 = ${escapeHtml(helpValues.low)}</span>`
                + `<span>5 = ${escapeHtml(helpValues.high)}</span>`
                + '<span>? = Unknown</span>';

            wrapper.appendChild(
                help
            );
        } else if (
            type === 'yes-no'
        ) {
            const help =
                document.createElement(
                    'div'
                );

            help.className =
                'add-place-radio-help is-simple';

            help.textContent =
                '? = Unknown / could not confidently determine';

            wrapper.appendChild(
                help
            );
        }

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

    const escapeHtml = (value) => {
        const node =
            document.createElement(
                'div'
            );

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    };

    selectElements.forEach(
        buildRadioControl
    );
})();
