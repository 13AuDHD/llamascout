/* Optional Admin Seasons panel: native details, with anchor-aware opening. */
(() => {
    'use strict';

    const disclosure = document.querySelector('[data-admin-seasons-disclosure]');
    if (!disclosure) return;

    const openSeasonLink = () => {
        if (window.location.hash === '#place-seasons') {
            disclosure.open = true;
        }
    };
    openSeasonLink();
    window.addEventListener('hashchange', openSeasonLink);

    // Anchors to a closed details element can land on the summary without
    // exposing its forms. Open before the same-hash navigation is processed.
    document.querySelectorAll('a[href$="#place-seasons"]').forEach(link => {
        link.addEventListener('click', () => {
            disclosure.open = true;
        });
    });
})();
