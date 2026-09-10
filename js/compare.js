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

    if (picker) {
        const maxPlaces =
            Number(
                picker.dataset.maxPlaces
                || 4
            );

        const checkboxes = [
            ...picker.querySelectorAll(
                'input[type="checkbox"][name="places[]"]'
            )
        ];

        const syncSelection = () => {
            const selected =
                checkboxes.filter(
                    (checkbox) =>
                        checkbox.checked
                );

            checkboxes.forEach(
                (checkbox) => {
                    const option =
                        checkbox.closest(
                            '[data-compare-option]'
                        );

                    option?.classList.toggle(
                        'is-selected',
                        checkbox.checked
                    );

                    checkbox.disabled =
                        !checkbox.checked
                        && selected.length
                            >= maxPlaces;
                }
            );

            if (countLabel) {
                countLabel.textContent =
                    `${selected.length} selected`;
            }
        };

        checkboxes.forEach(
            (checkbox) => {
                checkbox.addEventListener(
                    'change',
                    syncSelection
                );
            }
        );

        syncSelection();
    }


    if (search) {
        const options = [
            ...document.querySelectorAll(
                '[data-compare-option]'
            )
        ];

        search.addEventListener(
            'input',
            () => {
                const query =
                    search.value
                        .trim()
                        .toLowerCase();

                options.forEach(
                    (option) => {
                        const haystack =
                            String(
                                option.dataset.searchText
                                || ''
                            );

                        option.hidden =
                            query !== ''
                            && !haystack.includes(
                                query
                            );
                    }
                );
            }
        );
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
