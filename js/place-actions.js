(() => {
    'use strict';

    const navigationRoots = [
        ...document.querySelectorAll('[data-place-navigation]')
    ];

    const closeNavigation = (root, returnFocus = false) => {
        const toggle = root?.querySelector(
            '[data-place-navigation-toggle]'
        );
        const menu = root?.querySelector(
            '[data-place-navigation-menu]'
        );

        if (!toggle || !menu || menu.hidden) {
            return;
        }

        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        root.closest('.place-fact')?.classList.remove(
            'is-navigation-open'
        );

        if (returnFocus) {
            toggle.focus();
        }
    };

    navigationRoots.forEach((root) => {
        const toggle = root.querySelector(
            '[data-place-navigation-toggle]'
        );
        const menu = root.querySelector(
            '[data-place-navigation-menu]'
        );

        if (!toggle || !menu) {
            return;
        }

        toggle.addEventListener('click', () => {
            const opening = menu.hidden;

            navigationRoots.forEach((otherRoot) => {
                if (otherRoot !== root) {
                    closeNavigation(otherRoot);
                }
            });

            menu.hidden = !opening;
            toggle.setAttribute(
                'aria-expanded',
                opening ? 'true' : 'false'
            );
            root.closest('.place-fact')?.classList.toggle(
                'is-navigation-open',
                opening
            );

            if (opening) {
                menu.querySelector('a')?.focus({
                    preventScroll: true
                });
            }
        });
    });

    document.addEventListener('click', (event) => {
        navigationRoots.forEach((root) => {
            if (!root.contains(event.target)) {
                closeNavigation(root);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        navigationRoots.forEach((root) => {
            closeNavigation(root, true);
        });
    });

    const copyText = async (text) => {
        if (
            navigator.clipboard &&
            typeof navigator.clipboard.writeText === 'function'
        ) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        textarea.style.pointerEvents = 'none';

        document.body.appendChild(textarea);
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);

        const copied = document.execCommand('copy');
        textarea.remove();

        if (!copied) {
            throw new Error('Copy command was not successful.');
        }
    };

    document
        .querySelectorAll('[data-copy-place-coordinates]')
        .forEach((button) => {
            let feedbackTimer = null;

            button.addEventListener('click', async () => {
                const coordinates =
                    button.dataset.copyPlaceCoordinates || '';
                const wrapper = button.closest('.place-coordinate-copy');
                const feedback = wrapper?.querySelector(
                    '[data-place-copy-feedback]'
                );

                if (!coordinates) {
                    return;
                }

                window.clearTimeout(feedbackTimer);

                try {
                    await copyText(coordinates);

                    wrapper?.classList.add('is-copied');
                    button.setAttribute(
                        'aria-label',
                        'GPS coordinates copied'
                    );
                    button.title = 'GPS coordinates copied';

                    if (feedback) {
                        feedback.textContent = 'Copied';
                        feedback.hidden = false;
                    }

                    feedbackTimer = window.setTimeout(() => {
                        wrapper?.classList.remove('is-copied');
                        button.setAttribute(
                            'aria-label',
                            'Copy GPS coordinates'
                        );
                        button.title = 'Copy GPS coordinates';

                        if (feedback) {
                            feedback.hidden = true;
                        }
                    }, 1800);
                } catch (error) {
                    console.error(
                        'Llama Scout coordinate copy:',
                        error
                    );

                    if (feedback) {
                        feedback.textContent = 'Copy failed';
                        feedback.hidden = false;
                    }
                }
            });
        });
})();
