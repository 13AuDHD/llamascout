(() => {
    'use strict';

    const placeSearchWrap =
        document.querySelector(
            '[data-report-place-search-wrap]'
        );

    if (placeSearchWrap) {
        const input =
            placeSearchWrap.querySelector(
                '[data-report-place-search]'
            );

        const results =
            placeSearchWrap.querySelector(
                '[data-report-place-results]'
            );

        window.LlamaPlaceSearch?.attach({
            input,
            results,
            endpoint:
                placeSearchWrap.dataset.placeSearchEndpoint
                || '/api/compare-place-search.php',
            onSelect(place) {
                const slug =
                    String(
                        place.slug
                        || ''
                    );

                if (!slug) {
                    return;
                }

                const url =
                    new URL(
                        '/compare.php',
                        window.location.origin
                    );

                url.searchParams.set(
                    'mode',
                    'reports'
                );

                url.searchParams.set(
                    'place',
                    slug
                );

                window.location.assign(
                    url.toString()
                );
            },
            resultIcon: 'history',
        });
    }

    // Site history reuses the same two-to-four selection contract.
    const sitePicker = document.querySelector('[data-compare-site-report-picker]');
    if (sitePicker) {
        const boxes = [...sitePicker.querySelectorAll('input[name="siteReports[]"]')];
        const count = sitePicker.querySelector('[data-compare-site-report-count]');
        const button = sitePicker.querySelector('[data-compare-site-report-submit]');
        const syncSitePicker = () => {
            const selected = boxes.filter((box) => box.checked);
            boxes.forEach((box) => {
                box.disabled = !box.checked && selected.length >= 4;
                box.closest('label')?.classList.toggle('is-selected', box.checked);
            });
            if (count) count.textContent = `${selected.length} selected (2 to 4)`;
            if (button) button.disabled = selected.length < 2;
        };
        sitePicker.addEventListener('change', syncSitePicker);
        sitePicker.addEventListener('submit', (event) => {
            const selected = boxes.filter((box) => box.checked).length;
            if (selected < 2 || selected > 4) event.preventDefault();
        });
        syncSitePicker();
    }

    const picker =
        document.querySelector(
            '[data-compare-report-picker]'
        );

    if (!picker) {
        return;
    }

    const minReports =
        Number(
            picker.dataset.minReports
            || 2
        );

    const maxReports =
        Number(
            picker.dataset.maxReports
            || 4
        );

    const checkboxes = [
        ...picker.querySelectorAll(
            'input[type="checkbox"][name="reports[]"]'
        )
    ];

    const countLabel =
        picker.querySelector(
            '[data-compare-report-count]'
        );

    const submitButton =
        picker.querySelector(
            '[data-compare-report-submit]'
        );

    const sync = () => {
        const selected =
            checkboxes.filter(
                (checkbox) =>
                    checkbox.checked
            );

        checkboxes.forEach(
            (checkbox) => {
                checkbox.disabled =
                    !checkbox.checked
                    &&
                    selected.length
                        >= maxReports;

                checkbox
                    .closest(
                        '.compare-report-option'
                    )
                    ?.classList.toggle(
                        'is-selected',
                        checkbox.checked
                    );
            }
        );

        if (countLabel) {
            countLabel.textContent =
                `${selected.length} selected`;
        }

        if (submitButton) {
            submitButton.disabled =
                selected.length
                <
                minReports;
        }
    };

    picker.addEventListener(
        'change',
        (event) => {
            if (
                !event.target.matches(
                    'input[type="checkbox"][name="reports[]"]'
                )
            ) {
                return;
            }

            sync();
        }
    );

    sync();
})();
