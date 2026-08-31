(() => {
    'use strict';

    const header = document.getElementById('site-header');
    const nav = document.getElementById('site-navigation');
    const toggle = document.getElementById('site-menu-toggle');
    const toggleLabel = document.getElementById('site-menu-toggle-label');
    const mobileAccessibility = document.getElementById(
        'accessibility-button-mobile'
    );
    const desktopAccessibility = document.getElementById(
        'accessibility-button'
    );
    const accessibilityPanel = document.getElementById(
        'accessibility-panel'
    );
    const accessibilityClose = document.getElementById(
        'accessibility-close'
    );

    if (!header || !nav || !toggle) {
        return;
    }

    const mobileMedia = window.matchMedia('(max-width: 860px)');

    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'select:not([disabled])',
        'input:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    const setMenuOpen = (open, returnFocus = false) => {
        const shouldOpen = Boolean(open) && mobileMedia.matches;

        header.classList.toggle('menu-is-open', shouldOpen);
        document.body.classList.toggle(
            'mobile-menu-is-open',
            shouldOpen
        );

        toggle.setAttribute(
            'aria-expanded',
            shouldOpen ? 'true' : 'false'
        );

        toggle.setAttribute(
            'aria-label',
            shouldOpen ? 'Close menu' : 'Open menu'
        );

        if (toggleLabel) {
            toggleLabel.textContent =
                shouldOpen ? 'Close menu' : 'Open menu';
        }

        if (!shouldOpen && returnFocus) {
            toggle.focus();
        }
    };

    const isMenuOpen = () =>
        header.classList.contains('menu-is-open');

    toggle.addEventListener('click', () => {
        setMenuOpen(!isMenuOpen());
    });

    nav.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || !mobileMedia.matches) {
            return;
        }

        setMenuOpen(false);
    });

    document.addEventListener('pointerdown', (event) => {
        if (!mobileMedia.matches || !isMenuOpen()) {
            return;
        }

        if (header.contains(event.target)) {
            return;
        }

        setMenuOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (
            accessibilityPanel &&
            !accessibilityPanel.hidden
        ) {
            return;
        }

        if (isMenuOpen()) {
            setMenuOpen(false, true);
        }
    });

    nav.addEventListener('keydown', (event) => {
        if (
            event.key !== 'Tab' ||
            !mobileMedia.matches ||
            !isMenuOpen()
        ) {
            return;
        }

        const controls = [
            ...nav.querySelectorAll(focusableSelector),
            mobileAccessibility,
            toggle
        ].filter(Boolean);

        if (!controls.length) {
            return;
        }

        const first = controls[0];
        const last = controls[controls.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (
            !event.shiftKey &&
            document.activeElement === last
        ) {
            event.preventDefault();
            first.focus();
        }
    });

    const mirrorAccessibilityState = () => {
        const expanded =
            desktopAccessibility?.getAttribute('aria-expanded') ===
            'true';

        if (mobileAccessibility) {
            mobileAccessibility.setAttribute(
                'aria-expanded',
                expanded ? 'true' : 'false'
            );
        }
    };

    if (mobileAccessibility && desktopAccessibility) {
        mobileAccessibility.addEventListener('click', () => {
            /*
             * accessibility.js owns the panel behavior.
             * Use the existing desktop control as the single source
             * of truth so there are not two competing implementations.
             */
            desktopAccessibility.click();

            window.requestAnimationFrame(
                mirrorAccessibilityState
            );
        });

        desktopAccessibility.addEventListener(
            'click',
            () => {
                window.requestAnimationFrame(
                    mirrorAccessibilityState
                );
            }
        );

        accessibilityClose?.addEventListener(
            'click',
            () => {
                window.requestAnimationFrame(
                    mirrorAccessibilityState
                );
            }
        );
    }

    mobileMedia.addEventListener('change', (event) => {
        if (!event.matches) {
            setMenuOpen(false);
        }
    });

    window.addEventListener('pageshow', () => {
        setMenuOpen(false);
        mirrorAccessibilityState();
    });
})();
