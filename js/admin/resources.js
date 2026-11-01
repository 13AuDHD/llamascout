(() => {
    const mode =
        document.querySelector(
            '[data-resource-hours-mode]'
        );

    const panels =
        Array.from(
            document.querySelectorAll(
                '[data-resource-hours-panel]'
            )
        );

    if (!mode || panels.length === 0) {
        return;
    }

    const sync = () => {
        const current =
            String(mode.value || '');

        panels.forEach((panel) => {
            const active =
                panel.getAttribute(
                    'data-resource-hours-panel'
                ) === current;

            panel.hidden = !active;

            panel.querySelectorAll(
                'select, input'
            ).forEach((field) => {
                field.disabled = !active;
                field.required = active;
            });
        });
    };

    mode.addEventListener(
        'change',
        sync
    );

    sync();
})();
