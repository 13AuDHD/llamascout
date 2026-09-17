(() => {
    'use strict';

    const picker =
        document.querySelector(
            '[data-compare-picker]'
        );

    if (picker) {
        const search =
            picker.querySelector(
                '[data-compare-search]'
            );

        const countLabel =
            picker.querySelector(
                '[data-compare-count]'
            );

        const headingCount =
            picker.querySelector(
                '[data-compare-heading-count]'
            );

        const selectedPanel =
            picker.querySelector(
                '[data-compare-selected]'
            );

        const selectedList =
            picker.querySelector(
                '[data-compare-selected-list]'
            );

        const hiddenInputs =
            picker.querySelector(
                '[data-compare-hidden-inputs]'
            );

        const optionsPanel =
            picker.querySelector(
                '[data-compare-options]'
            );

        const submitButton =
            picker.querySelector(
                '[data-compare-submit]'
            );

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

        const selectedInputs = () => [
            ...picker.querySelectorAll(
                '[data-compare-selected-input]'
            )
        ];

        const renderSelected = () => {
            const selected =
                selectedInputs();

            if (
                !selectedPanel
                || !selectedList
            ) {
                return;
            }

            selectedPanel.hidden =
                selected.length === 0;

            selectedList.innerHTML = '';

            selected.forEach(
                (input) => {
                    const slug =
                        String(
                            input.value
                            || ''
                        );

                    const name =
                        String(
                            input.dataset.placeName
                            || 'Place'
                        );

                    const meta =
                        String(
                            input.dataset.placeMeta
                            || ''
                        );

                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type = 'button';
                    button.className =
                        'compare-selected-chip';
                    button.dataset.removeComparePlace =
                        slug;
                    button.setAttribute(
                        'aria-label',
                        `Remove ${name} from comparison`
                    );

                    const copy =
                        document.createElement(
                            'span'
                        );

                    const strong =
                        document.createElement(
                            'strong'
                        );

                    strong.textContent = name;
                    copy.appendChild(strong);

                    if (meta) {
                        const small =
                            document.createElement(
                                'small'
                            );

                        small.textContent = meta;
                        copy.appendChild(small);
                    }

                    const icon =
                        document.createElement(
                            'i'
                        );

                    icon.className =
                        'llama-icon-mask';
                    icon.style.setProperty(
                        '--llama-icon-mask',
                        "url('/assets/icons/x.svg')"
                    );
                    icon.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    button.append(
                        copy,
                        icon
                    );

                    selectedList.appendChild(
                        button
                    );
                }
            );
        };

        const syncSelection = () => {
            const selected =
                selectedInputs();

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

            if (search) {
                search.disabled =
                    selected.length
                    >=
                    maxPlaces;

                search.placeholder =
                    selected.length >= maxPlaces
                        ? 'Maximum of four Places selected'
                        : 'Start typing a Place, town, or type...';
            }

            renderSelected();
        };

        const addPlace = (place) => {
            if (!hiddenInputs) {
                return;
            }

            const selected =
                selectedInputs();

            if (
                selected.length >= maxPlaces
                || selected.some(
                    (input) =>
                        input.value
                        ===
                        String(place.slug || '')
                )
            ) {
                return;
            }

            const input =
                document.createElement(
                    'input'
                );

            input.type = 'hidden';
            input.name = 'places[]';
            input.value = String(place.slug || '');
            input.dataset.compareSelectedInput = '';
            input.dataset.placeName =
                String(place.name || 'Place');
            input.dataset.placeMeta =
                String(place.meta || '');

            hiddenInputs.appendChild(
                input
            );

            syncSelection();
            searchController?.clear();
            search?.focus();
        };

        const searchController =
            window.LlamaPlaceSearch?.attach({
                input: search,
                results: optionsPanel,
                endpoint:
                    picker.dataset.placeSearchEndpoint
                    || '/api/compare-place-search.php',
                excludeSlugs: () =>
                    selectedInputs().map(
                        (input) => input.value
                    ),
                onSelect: addPlace,
                resultIcon: 'plus',
            });

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

                const input =
                    selectedInputs().find(
                        (item) =>
                            item.value === slug
                    );

                input?.remove();
                syncSelection();
                searchController?.refresh();
            }
        );

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
