(() => {
    'use strict';

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
