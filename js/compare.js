(() => {
    'use strict';

    const picker =
        document.querySelector(
            '[data-compare-picker]'
        );

    const search =
        document.querySelector(
            '[data-compare-search]'
        );

    const countLabel =
        document.querySelector(
            '[data-compare-count]'
        );

    const headingCount =
        document.querySelector(
            '[data-compare-heading-count]'
        );

    const selectedPanel =
        document.querySelector(
            '[data-compare-selected]'
        );

    const selectedList =
        document.querySelector(
            '[data-compare-selected-list]'
        );

    const optionsPanel =
        document.querySelector(
            '[data-compare-options]'
        );

    const emptySearch =
        document.querySelector(
            '[data-compare-search-empty]'
        );

    const submitButton =
        document.querySelector(
            '[data-compare-submit]'
        );

    if (picker) {
        const maxPlaces =
            Number(
                picker.dataset.maxPlaces
                || 4
            );

        const minPlaces =
            Number(
                picker.dataset.minPlaces
                || 2
            );

        const checkboxes = [
            ...picker.querySelectorAll(
                'input[type="checkbox"][name="places[]"]'
            )
        ];

        const escapeHtml = (value) => {
            const div =
                document.createElement(
                    'div'
                );

            div.textContent =
                String(
                    value
                    ?? ''
                );

            return div.innerHTML;
        };

        const optionForCheckbox = (
            checkbox
        ) =>
            checkbox.closest(
                '[data-compare-option]'
            );

        const renderSelected = (
            selected
        ) => {
            if (
                !selectedPanel
                ||
                !selectedList
            ) {
                return;
            }

            selectedPanel.hidden =
                selected.length === 0;

            selectedList.innerHTML =
                selected.map(
                    (checkbox) => {
                        const option =
                            optionForCheckbox(
                                checkbox
                            );

                        const slug =
                            String(
                                option?.dataset.placeSlug
                                || checkbox.value
                            );

                        const name =
                            String(
                                option?.dataset.placeName
                                || 'Place'
                            );

                        const meta =
                            String(
                                option?.dataset.placeMeta
                                || ''
                            );

                        return `
                            <button
                                type="button"
                                class="compare-selected-chip"
                                data-remove-compare-place="${escapeHtml(slug)}"
                                aria-label="Remove ${escapeHtml(name)} from comparison"
                            >
                                <span>
                                    <strong>${escapeHtml(name)}</strong>
                                    ${meta ? `<small>${escapeHtml(meta)}</small>` : ''}
                                </span>

                                <i
                                    class="fa-solid fa-xmark"
                                    aria-hidden="true"
                                ></i>
                            </button>
                        `;
                    }
                ).join('');
        };

        const updateSearchResults = () => {
            if (
                !search
                ||
                !optionsPanel
            ) {
                return;
            }

            const query =
                search.value
                    .trim()
                    .toLowerCase();

            let visibleCount = 0;

            checkboxes.forEach(
                (checkbox) => {
                    const option =
                        optionForCheckbox(
                            checkbox
                        );

                    if (!option) {
                        return;
                    }

                    const haystack =
                        String(
                            option.dataset.searchText
                            || ''
                        );

                    const show =
                        query !== ''
                        &&
                        !checkbox.checked
                        &&
                        haystack.includes(
                            query
                        );

                    option.hidden =
                        !show;

                    if (show) {
                        visibleCount++;
                    }
                }
            );

            optionsPanel.classList.toggle(
                'has-query',
                query !== ''
            );

            if (emptySearch) {
                emptySearch.hidden =
                    query === ''
                    ||
                    visibleCount > 0;
            }
        };

        const syncSelection = () => {
            const selected =
                checkboxes.filter(
                    (checkbox) =>
                        checkbox.checked
                );

            checkboxes.forEach(
                (checkbox) => {
                    const option =
                        optionForCheckbox(
                            checkbox
                        );

                    option?.classList.toggle(
                        'is-selected',
                        checkbox.checked
                    );

                    checkbox.disabled =
                        !checkbox.checked
                        &&
                        selected.length
                            >= maxPlaces;
                }
            );

            if (countLabel) {
                countLabel.textContent =
                    `${selected.length} selected`;
            }

            if (headingCount) {
                headingCount.textContent =
                    `${selected.length} of ${maxPlaces} selected`;
            }

            if (submitButton) {
                submitButton.disabled =
                    selected.length
                    <
                    minPlaces;
            }

            renderSelected(
                selected
            );

            updateSearchResults();
        };

        picker.addEventListener(
            'change',
            (event) => {
                const checkbox =
                    event.target.closest(
                        'input[type="checkbox"][name="places[]"]'
                    );

                if (!checkbox) {
                    return;
                }

                if (checkbox.checked) {
                    if (search) {
                        search.value = '';
                        search.focus();
                    }
                }

                syncSelection();
            }
        );

        picker.addEventListener(
            'click',
            (event) => {
                const removeButton =
                    event.target.closest(
                        '[data-remove-compare-place]'
                    );

                if (!removeButton) {
                    return;
                }

                const slug =
                    String(
                        removeButton.dataset.removeComparePlace
                        || ''
                    );

                const checkbox =
                    checkboxes.find(
                        (item) =>
                            item.value
                            ===
                            slug
                    );

                if (!checkbox) {
                    return;
                }

                checkbox.checked =
                    false;

                syncSelection();
            }
        );

        if (search) {
            search.addEventListener(
                'input',
                updateSearchResults
            );
        }

        syncSelection();
    }

    const copyButton =
        document.querySelector(
            '[data-copy-compare-link]'
        );

    if (copyButton) {
        const label =
            copyButton.querySelector(
                '[data-copy-label]'
            );

        copyButton.addEventListener(
            'click',
            async () => {
                const url =
                    String(
                        copyButton.dataset.compareUrl
                        || window.location.href
                    );

                try {
                    await navigator.clipboard.writeText(
                        url
                    );

                    if (label) {
                        label.textContent =
                            'Link Copied';
                    }

                    window.setTimeout(
                        () => {
                            if (label) {
                                label.textContent =
                                    'Copy Comparison Link';
                            }
                        },
                        1800
                    );
                } catch (error) {
                    window.prompt(
                        'Copy this comparison link:',
                        url
                    );
                }
            }
        );
    }
})();
