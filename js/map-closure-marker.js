(() => {
    'use strict';

    const STYLE_ID =
        'llama-map-closure-marker-css';

    const STYLE_URL =
        '/css/map-closure-marker.css';

    const mapState =
        window.LlamaScoutMap;

    const mapElement =
        document.getElementById(
            'llama-map'
        );

    if (
        !mapState?.map
        || !mapElement
        || typeof L === 'undefined'
    ) {
        return;
    }

    if (
        !document.getElementById(
            STYLE_ID
        )
    ) {
        const link =
            document.createElement(
                'link'
            );

        link.id = STYLE_ID;
        link.rel = 'stylesheet';
        link.href = STYLE_URL;

        document.head.appendChild(
            link
        );
    }

    const isClosed = (
        place
    ) =>
        place?.is_closed === true
        || place?.is_closed === 1
        || place?.is_closed === '1';

    const isPaid = (
        place
    ) => {
        const status =
            String(
                place?.camping_fee_status
                ?? ''
            )
                .trim()
                .toLowerCase();

        return (
            status === 'paid'
            || status === 'mixed'
        );
    };

    const placesBySlug =
        new Map();

    let loaded = false;
    let scheduled = false;

    const popupSlug = (
        layer
    ) => {
        const popup =
            typeof layer.getPopup === 'function'
                ? layer.getPopup()
                : null;

        if (!popup) {
            return '';
        }

        const content =
            popup.getContent();

        if (
            typeof content
            !== 'string'
        ) {
            return '';
        }

        const match =
            content.match(
                /place\.php\?slug=([^"'&<\s]+)/
            );

        if (!match) {
            return '';
        }

        try {
            return decodeURIComponent(
                match[1]
            );
        } catch (_) {
            return match[1];
        }
    };

    const applyMarkerState = (
        layer
    ) => {
        if (
            !(layer instanceof L.Marker)
        ) {
            return;
        }

        const slug =
            popupSlug(
                layer
            );

        if (slug === '') {
            return;
        }

        const place =
            placesBySlug.get(
                slug
            );

        if (
            !place
            || !isClosed(place)
        ) {
            return;
        }

        const element =
            layer.getElement?.();

        const marker =
            element?.querySelector(
                '.map-place-marker'
            );

        if (!marker) {
            return;
        }

        /*
         * Closure is a color/status modifier, not a replacement symbol.
         * Closed free locations stay a red bullet. Closed paid/mixed
         * locations stay a red dollar marker.
         */
        marker.classList.remove(
            'is-featured',
            'is-mixed',
            'is-paid'
        );

        marker.classList.add(
            'is-closed'
        );

        if (isPaid(place)) {
            marker.classList.add(
                'is-paid'
            );
        }
    };

    const applyAll = () => {
        scheduled = false;

        if (!loaded) {
            return;
        }

        mapState.map.eachLayer(
            applyMarkerState
        );
    };

    const schedule = () => {
        if (scheduled) {
            return;
        }

        scheduled = true;

        window.requestAnimationFrame(
            applyAll
        );
    };

    mapState.map.on(
        'layeradd',
        schedule
    );

    mapState.map.on(
        'moveend',
        schedule
    );

    fetch(
        '/api/places.php',
        {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept:
                    'application/json',
            },
        }
    )
        .then(
            async (
                response
            ) => {
                if (!response.ok) {
                    throw new Error(
                        'Unable to load map marker status.'
                    );
                }

                return response.json();
            }
        )
        .then(
            (
                payload
            ) => {
                const places =
                    Array.isArray(
                        payload?.places
                    )
                        ? payload.places
                        : [];

                places.forEach(
                    (
                        place
                    ) => {
                        const slug =
                            String(
                                place?.slug
                                ?? ''
                            ).trim();

                        if (slug !== '') {
                            placesBySlug.set(
                                slug,
                                place
                            );
                        }
                    }
                );

                loaded = true;
                schedule();
            }
        )
        .catch(
            (
                error
            ) => {
                console.error(
                    'Llama Scout closure marker enhancement:',
                    error
                );
            }
        );
})();
