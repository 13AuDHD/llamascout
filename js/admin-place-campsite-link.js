(() => {
    'use strict';

    const nav =
        document.querySelector(
            '.admin-place-section-nav'
        );

    if (!nav) {
        return;
    }

    const params =
        new URLSearchParams(
            window.location.search
        );

    const placeId =
        params.get('id');

    if (!placeId) {
        return;
    }

    if (
        nav.querySelector(
            '[data-admin-campsites-link]'
        )
    ) {
        return;
    }

    const link =
        document.createElement(
            'a'
        );

    link.href =
        `/place-campsites.php?place_id=${encodeURIComponent(placeId)}`;

    link.textContent =
        'Campsites';

    link.dataset
        .adminCampsitesLink =
        '1';

    nav.insertBefore(
        link,
        nav.children[1]
        || null
    );
})();
