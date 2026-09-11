(() => {
    'use strict';

    document
        .querySelectorAll('[data-update-field-toggle]')
        .forEach((toggle) => {
            const path = toggle.dataset.updateFieldToggle;

            const card = document.querySelector(
                `[data-update-card="${CSS.escape(path)}"]`
            );

            const control = document.querySelector(
                `[data-update-field-control="${CSS.escape(path)}"]`
            );

            if (!card || !control) {
                return;
            }

            const sync = () => {
                const enabled = toggle.checked;

                card.classList.toggle(
                    'is-selected',
                    enabled
                );

                control.classList.toggle(
                    'is-disabled',
                    !enabled
                );

                control
                    .querySelectorAll('input, select, textarea')
                    .forEach((field) => {
                        field.disabled = !enabled;
                    });
            };

            toggle.addEventListener('change', sync);
            sync();
        });
})();
