(function () {
    'use strict';

    const app = document.querySelector('.admin-app');
    const sidebar = document.getElementById('admin-sidebar');
    const openButton = document.querySelector('[data-admin-menu-open]');
    const closeButtons = document.querySelectorAll('[data-admin-menu-close]');

    if (!app || !sidebar || !openButton) {
        return;
    }

    function setMenu(open) {
        app.classList.toggle('is-menu-open', open);
        openButton.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );

        document.body.style.overflow = open ? 'hidden' : '';

        if (open) {
            const firstFocusable = sidebar.querySelector(
                'a[href], button:not([disabled])'
            );

            if (firstFocusable) {
                firstFocusable.focus();
            }
        }
    }

    openButton.addEventListener('click', function () {
        setMenu(true);
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setMenu(false);
            openButton.focus();
        });
    });

    sidebar.addEventListener('click', function (event) {
        const link = event.target.closest('a[href]');

        if (
            link &&
            window.matchMedia('(max-width: 860px)').matches
        ) {
            setMenu(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            app.classList.contains('is-menu-open')
        ) {
            setMenu(false);
            openButton.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (
            !window.matchMedia('(max-width: 860px)').matches &&
            app.classList.contains('is-menu-open')
        ) {
            setMenu(false);
        }
    });
})();
