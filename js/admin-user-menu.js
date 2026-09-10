(() => {
    'use strict';

    const menus =
        document.querySelectorAll(
            '[data-admin-user-menu]'
        );

    menus.forEach(
        (menu) => {
            const trigger =
                menu.querySelector(
                    '[data-admin-user-menu-trigger]'
                );

            const dropdown =
                menu.querySelector(
                    '[data-admin-user-menu-dropdown]'
                );

            if (
                !trigger
                || !dropdown
            ) {
                return;
            }

            const closeMenu = (
                restoreFocus = false
            ) => {
                menu.classList.remove(
                    'is-open'
                );

                trigger.setAttribute(
                    'aria-expanded',
                    'false'
                );

                dropdown.hidden = true;

                if (restoreFocus) {
                    trigger.focus();
                }
            };

            const openMenu = () => {
                menu.classList.add(
                    'is-open'
                );

                trigger.setAttribute(
                    'aria-expanded',
                    'true'
                );

                dropdown.hidden = false;
            };

            trigger.addEventListener(
                'click',
                (event) => {
                    event.stopPropagation();

                    if (
                        menu.classList.contains(
                            'is-open'
                        )
                    ) {
                        closeMenu();
                    } else {
                        openMenu();
                    }
                }
            );

            document.addEventListener(
                'click',
                (event) => {
                    if (
                        !menu.contains(
                            event.target
                        )
                    ) {
                        closeMenu();
                    }
                }
            );

            document.addEventListener(
                'keydown',
                (event) => {
                    if (
                        event.key === 'Escape'
                        && menu.classList.contains(
                            'is-open'
                        )
                    ) {
                        closeMenu(true);
                    }
                }
            );

            dropdown.addEventListener(
                'keydown',
                (event) => {
                    if (
                        event.key !== 'ArrowDown'
                        && event.key !== 'ArrowUp'
                    ) {
                        return;
                    }

                    const items = [
                        ...dropdown.querySelectorAll(
                            'a[href]'
                        )
                    ];

                    if (!items.length) {
                        return;
                    }

                    event.preventDefault();

                    const current =
                        items.indexOf(
                            document.activeElement
                        );

                    const direction =
                        event.key === 'ArrowDown'
                            ? 1
                            : -1;

                    const next =
                        current < 0
                            ? 0
                            : (
                                current
                                + direction
                                + items.length
                            )
                            % items.length;

                    items[next].focus();
                }
            );
        }
    );
})();
