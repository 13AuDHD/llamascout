(() => {
    'use strict';

    const section =
        document.querySelector(
            '[data-stay-options]'
        );

    if (!section) {
        return;
    }

    const mount =
        document.querySelector(
            '[data-place-campsites-mount]'
        );

    const slot =
        section.querySelector(
            '[data-stay-browser-slot]'
        );

    const fallbackList =
        section.querySelector(
            '[data-stay-options-list]'
        );

    if (
        !slot
        || !fallbackList
    ) {
        return;
    }

    /*
     * The server has already removed mapped Site polygons that are associated
     * with canonical campsite records. Rows remaining here are either genuine
     * map-only Sites or mapped overnight Areas.
     */
    const mappedRows = [
        ...fallbackList.querySelectorAll(
            '[data-stay-map-site-id]'
        ),
    ];

    const areaRows = [
        ...fallbackList.querySelectorAll(
            '[data-stay-area-id]'
        ),
    ];

    const extraRows = [
        ...mappedRows,
        ...areaRows,
    ];

    if (mount) {
        slot.appendChild(
            mount
        );
    }

    let listObserver = null;
    let browser = null;

    const refresh = () => {
        browser =
            mount?.querySelector(
                '[data-place-campsites]'
            )
            || null;

        if (!browser) {
            fallbackList.hidden =
                extraRows.length === 0;

            return;
        }

        browser.classList.add(
            'is-stay-browser'
        );

        const list =
            browser.querySelector(
                '[data-campsite-list]'
            );

        if (!list) {
            return;
        }

        const filter =
            browser.querySelector(
                '[data-campsite-filter].is-active'
            );

        const query =
            String(
                browser.querySelector(
                    '[data-campsite-search]'
                )?.value
                || ''
            )
                .trim()
                .toLowerCase();

        const all =
            !filter
            || filter.dataset.campsiteFilter
                === 'all';

        for (const row of extraRows) {
            if (row.parentElement !== list) {
                list.appendChild(
                    row
                );
            }

            const text =
                row.textContent
                    .toLowerCase();

            row.hidden =
                !all
                || (
                    query !== ''
                    && !text.includes(
                        query
                    )
                );
        }

        fallbackList.hidden = true;

        if (!listObserver) {
            listObserver =
                new MutationObserver(
                    refresh
                );

            listObserver.observe(
                list,
                {
                    childList: true,
                }
            );

            browser.addEventListener(
                'input',
                () =>
                    requestAnimationFrame(
                        refresh
                    )
            );

            browser.addEventListener(
                'click',
                () =>
                    requestAnimationFrame(
                        refresh
                    )
            );
        }
    };

    if (mount) {
        new MutationObserver(
            refresh
        ).observe(
            mount,
            {
                childList: true,
            }
        );
    }

    refresh();

    const clearSelection = () => {
        for (const row of extraRows) {
            row.classList.remove(
                'is-selected'
            );

            row.setAttribute(
                'aria-current',
                'false'
            );
        }
    };

    for (const row of extraRows) {
        row.addEventListener(
            'click',
            () => {
                clearSelection();

                row.classList.add(
                    'is-selected'
                );

                row.setAttribute(
                    'aria-current',
                    'true'
                );
            }
        );
    }

    mount?.addEventListener(
        'click',
        (event) => {
            if (
                event.target.closest(
                    '[data-campsite-id]'
                )
            ) {
                clearSelection();
            }
        }
    );
})();
