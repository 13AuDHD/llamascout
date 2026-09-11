(() => {
    'use strict';

    const form =
        document.querySelector(
            '.moderator-submission-editor-form'
        );

    if (!form) {
        return;
    }

    /*
     * Convert the same simple selects used by Add Place into
     * compact Yes / No / ? and 1-5 / ? controls.
     */
    const selects =
        [
            ...form.querySelectorAll(
                '.contribution-field select'
            ),
        ];

    const values = (select) =>
        [...select.options].map(
            (option) =>
                String(option.value)
        );

    const isYesNo = (select) => {
        const list = values(select);

        return (
            list.length === 3
            && list.includes('')
            && list.includes('1')
            && list.includes('0')
        );
    };

    const isRating = (select) => {
        const list = values(select);

        return (
            list.length === 6
            && list.includes('')
            && [1, 2, 3, 4, 5].every(
                (number) =>
                    list.includes(
                        String(number)
                    )
            )
        );
    };

    selects.forEach((select) => {
        const type =
            isYesNo(select)
                ? 'yes-no'
                : (
                    isRating(select)
                        ? 'rating'
                        : ''
                );

        if (!type) {
            return;
        }

        const field =
            select.closest(
                '.contribution-field'
            );

        const title =
            field?.querySelector(
                ':scope > span'
            );

        if (!field || !title) {
            return;
        }

        const name =
            select.name;

        const initial =
            String(select.value);

        const wrapper =
            document.createElement(
                'div'
            );

        wrapper.className =
            'add-place-radio-control';

        wrapper.dataset.radioType =
            type;

        const row =
            document.createElement(
                'div'
            );

        row.className =
            'add-place-radio-row';

        const grid =
            document.createElement(
                'div'
            );

        grid.className =
            'add-place-radio-options';

        [...select.options].forEach(
            (option) => {
                const value =
                    String(option.value);

                const label =
                    document.createElement(
                        'label'
                    );

                label.className =
                    'add-place-radio-option';

                if (value === '') {
                    label.classList.add(
                        'is-unknown'
                    );
                }

                const input =
                    document.createElement(
                        'input'
                    );

                input.type =
                    'radio';

                input.name =
                    name;

                input.value =
                    value;

                input.checked =
                    value === initial;

                const text =
                    document.createElement(
                        'span'
                    );

                text.textContent =
                    value === ''
                        ? '?'
                        : (
                            type === 'rating'
                                ? value
                                : String(
                                    option.textContent
                                    || value
                                ).trim()
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

        clear.addEventListener(
            'click',
            () => {
                const unknown =
                    grid.querySelector(
                        'input[value=""]'
                    );

                if (unknown) {
                    unknown.checked =
                        true;
                }
            }
        );

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
            const low =
                select.dataset.ratingLow
                || 'Low';

            const high =
                select.dataset.ratingHigh
                || 'High';

            help.innerHTML =
                `<span>1 = ${escapeHtml(low)}</span>`
                + `<span>5 = ${escapeHtml(high)}</span>`
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

        select.hidden = true;
        select.disabled = true;

        title.insertAdjacentElement(
            'afterend',
            wrapper
        );
    });

    /*
     * No amenities and named amenities are mutually exclusive,
     * matching Add Place.
     */
    const noAmenities =
        form.querySelector(
            'input[type="checkbox"][name="fields[details.warning_no_amenities]"]'
        );

    const amenityChecks =
        [
            ...form.querySelectorAll(
                '.moderator-amenities-grid input[type="checkbox"]'
            ),
        ].filter(
            (input) =>
                input !== noAmenities
        );

    if (noAmenities) {
        noAmenities.addEventListener(
            'change',
            () => {
                if (!noAmenities.checked) {
                    return;
                }

                amenityChecks.forEach(
                    (input) => {
                        input.checked =
                            false;
                    }
                );
            }
        );

        amenityChecks.forEach(
            (input) => {
                input.addEventListener(
                    'change',
                    () => {
                        if (
                            input.checked
                            && noAmenities.checked
                        ) {
                            noAmenities.checked =
                                false;
                        }
                    }
                );
            }
        );
    }

    function escapeHtml(value) {
        const node =
            document.createElement(
                'div'
            );

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    }
})();
