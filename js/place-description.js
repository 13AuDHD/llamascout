(() => {
    'use strict';

    const section =
        document.querySelector(
            '[data-place-description-section]'
        );

    if (!section) {
        return;
    }

    const copy =
        section.querySelector(
            '[data-place-description-copy]'
        );

    const toggle =
        section.querySelector(
            '[data-place-description-toggle]'
        );

    const label =
        section.querySelector(
            '[data-place-description-toggle-label]'
        );

    if (
        !copy
        || !toggle
        || !label
    ) {
        return;
    }

    toggle.addEventListener(
        'click',
        () => {
            const expanded =
                toggle.getAttribute(
                    'aria-expanded'
                ) === 'true';

            const nextExpanded =
                !expanded;

            toggle.setAttribute(
                'aria-expanded',
                nextExpanded
                    ? 'true'
                    : 'false'
            );

            copy.classList.toggle(
                'is-collapsed',
                !nextExpanded
            );

            section.classList.toggle(
                'is-expanded',
                nextExpanded
            );

            label.textContent =
                nextExpanded
                    ? 'Collapse description'
                    : 'Expand description';
        }
    );
})();
