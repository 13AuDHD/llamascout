(() => {
    'use strict';

    const form = document.querySelector(
        '[data-points-adjustment-form]'
    );

    if (!form) {
        return;
    }

    const picker = form.querySelector(
        '[data-member-picker]'
    );

    const searchInput = form.querySelector(
        '[data-member-search]'
    );

    const selectedInput = form.querySelector(
        '[data-member-id]'
    );

    const resultsBox = form.querySelector(
        '[data-member-results]'
    );

    const submitButton = form.querySelector(
        'button[type="submit"]'
    );

    if (
        !picker
        || !searchInput
        || !selectedInput
        || !resultsBox
        || !submitButton
    ) {
        return;
    }

    const options = Array.from(
        resultsBox.querySelectorAll(
            '[data-member-option]'
        )
    );

    let visibleOptions = [];
    let activeIndex = -1;

    const normalize = (value) =>
        String(value || '')
            .trim()
            .toLowerCase();

    const selectedId = () =>
        String(
            selectedInput.value || ''
        ).trim();

    const closeResults = () => {
        resultsBox.hidden = true;

        searchInput.setAttribute(
            'aria-expanded',
            'false'
        );

        searchInput.removeAttribute(
            'aria-activedescendant'
        );

        activeIndex = -1;

        options.forEach((option) => {
            option.classList.remove(
                'is-active'
            );

            option.setAttribute(
                'aria-selected',
                'false'
            );
        });
    };

    const clearSelection = () => {
        selectedInput.value = '';
        submitButton.disabled = true;
    };

    const chooseOption = (option) => {
        const id =
            String(
                option.dataset.memberId
                || ''
            ).trim();

        const label =
            String(
                option.dataset.memberLabel
                || ''
            ).trim();

        const meta =
            String(
                option.dataset.memberMeta
                || ''
            ).trim();

        if (id === '') {
            return;
        }

        selectedInput.value = id;

        searchInput.value =
            meta !== ''
                ? `${label} · ${meta}`
                : label;

        searchInput.setCustomValidity('');

        submitButton.disabled = false;

        closeResults();
    };

    const rankOption = (
        option,
        query
    ) => {
        const id =
            normalize(
                option.dataset.memberId
            );

        const label =
            normalize(
                option.dataset.memberLabel
            );

        const searchText =
            normalize(
                option.dataset.memberSearchText
            );

        if (
            id === query
            || label === query
        ) {
            return 0;
        }

        if (
            id.startsWith(query)
            || label.startsWith(query)
        ) {
            return 1;
        }

        if (
            searchText
                .split(/\s+/)
                .some((part) =>
                    part.startsWith(query)
                )
        ) {
            return 2;
        }

        return 3;
    };

    const matchingOptions = () => {
        const query =
            normalize(
                searchInput.value
            );

        if (query === '') {
            return options.slice(0, 10);
        }

        return options
            .filter((option) =>
                normalize(
                    option.dataset.memberSearchText
                ).includes(query)
            )
            .sort((a, b) => {
                const difference =
                    rankOption(a, query)
                    - rankOption(b, query);

                if (difference !== 0) {
                    return difference;
                }

                return normalize(
                    a.dataset.memberLabel
                ).localeCompare(
                    normalize(
                        b.dataset.memberLabel
                    )
                );
            })
            .slice(0, 12);
    };

    const renderResults = () => {
        visibleOptions =
            matchingOptions();

        options.forEach((option) => {
            option.hidden =
                !visibleOptions.includes(
                    option
                );

            option.classList.remove(
                'is-active'
            );

            option.setAttribute(
                'aria-selected',
                'false'
            );
        });

        activeIndex = -1;

        if (
            visibleOptions.length
            === 0
        ) {
            resultsBox.hidden = true;

            searchInput.setAttribute(
                'aria-expanded',
                'false'
            );

            return;
        }

        resultsBox.hidden = false;

        searchInput.setAttribute(
            'aria-expanded',
            'true'
        );
    };

    const setActiveOption = (
        index
    ) => {
        if (
            visibleOptions.length
            === 0
        ) {
            activeIndex = -1;
            return;
        }

        if (index < 0) {
            index =
                visibleOptions.length - 1;
        }

        if (
            index
            >= visibleOptions.length
        ) {
            index = 0;
        }

        activeIndex = index;

        visibleOptions.forEach(
            (option, optionIndex) => {
                const active =
                    optionIndex
                    === activeIndex;

                option.classList.toggle(
                    'is-active',
                    active
                );

                option.setAttribute(
                    'aria-selected',
                    active
                        ? 'true'
                        : 'false'
                );
            }
        );

        const activeOption =
            visibleOptions[
                activeIndex
            ];

        if (activeOption) {
            if (!activeOption.id) {
                activeOption.id =
                    'admin-points-member-option-'
                    + (
                        activeOption
                            .dataset
                            .memberId
                        || activeIndex
                    );
            }

            searchInput.setAttribute(
                'aria-activedescendant',
                activeOption.id
            );

            activeOption.scrollIntoView({
                block: 'nearest',
            });
        }
    };

    options.forEach((option) => {
        option.addEventListener(
            'click',
            () => {
                chooseOption(option);
            }
        );

        option.addEventListener(
            'mouseenter',
            () => {
                const index =
                    visibleOptions.indexOf(
                        option
                    );

                if (index >= 0) {
                    setActiveOption(
                        index
                    );
                }
            }
        );
    });

    searchInput.addEventListener(
        'focus',
        () => {
            renderResults();
        }
    );

    searchInput.addEventListener(
        'input',
        () => {
            clearSelection();

            searchInput.setCustomValidity(
                ''
            );

            renderResults();
        }
    );

    searchInput.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key
                === 'ArrowDown'
            ) {
                event.preventDefault();

                if (resultsBox.hidden) {
                    renderResults();
                }

                setActiveOption(
                    activeIndex + 1
                );

                return;
            }

            if (
                event.key
                === 'ArrowUp'
            ) {
                event.preventDefault();

                if (resultsBox.hidden) {
                    renderResults();
                }

                setActiveOption(
                    activeIndex - 1
                );

                return;
            }

            if (
                event.key
                    === 'Enter'
                && !resultsBox.hidden
                && activeIndex >= 0
                && visibleOptions[
                    activeIndex
                ]
            ) {
                event.preventDefault();

                chooseOption(
                    visibleOptions[
                        activeIndex
                    ]
                );

                return;
            }

            if (
                event.key
                === 'Escape'
            ) {
                closeResults();
            }
        }
    );

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (
                !picker.contains(
                    event.target
                )
            ) {
                closeResults();
            }
        }
    );

    form.addEventListener(
        'submit',
        (event) => {
            if (
                selectedId() !== ''
            ) {
                return;
            }

            event.preventDefault();

            searchInput.setCustomValidity(
                'Choose a member from the live search results.'
            );

            searchInput.reportValidity();
            searchInput.focus();
            renderResults();
        }
    );

    if (
        selectedId() === ''
    ) {
        submitButton.disabled = true;
    }
})();
