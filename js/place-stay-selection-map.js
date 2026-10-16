(() => {
    'use strict';

    const section =
        document.querySelector(
            '[data-stay-options]'
        );

    if (!section) {
        return;
    }

    const mapElement =
        document.getElementById(
            'llama-map'
        );

    const mapCard =
        mapElement?.closest(
            '.map-card'
        );

    const slug =
        document.querySelector(
            '[data-place-campsites-mount]'
        )?.dataset.placeSlug
        || '';

    let canonicalDataPromise = null;
    let mappedDataPromise = null;
    let highlight = null;
    let lastSelection = null;

    /*
     * Preserve the server-rendered Place-level Scout Report. Site switching
     * has its own effective-value payload, while Area switching applies only
     * direct Area overrides after restoring this baseline.
     */
    const reportCards =
        Array.from(
            document.querySelectorAll(
                '[data-campsite-report-field]'
            )
        );

    const reportBaseline =
        new Map(
            reportCards.map(
                (card) => [
                    card,
                    {
                        hidden:
                            card.hidden,
                        className:
                            card.className,
                        html:
                            card.innerHTML,
                    },
                ]
            )
        );

    const restorePlaceReport =
        () => {
            reportBaseline.forEach(
                (
                    state,
                    card
                ) => {
                    card.hidden =
                        state.hidden;

                    card.className =
                        state.className;

                    card.innerHTML =
                        state.html;
                }
            );
        };

    const applyAreaReport =
        (row) => {
            restorePlaceReport();

            let values = {};

            try {
                values =
                    JSON.parse(
                        row.dataset
                            .stayReportValues
                        || '{}'
                    );
            } catch (error) {
                console.error(
                    'Llama Scout Area report payload error:',
                    error
                );

                values = {};
            }

            if (
                !values
                || typeof values
                    !== 'object'
                || Array.isArray(values)
            ) {
                return;
            }

            for (
                const card
                of reportCards
            ) {
                const key =
                    String(
                        card.dataset
                            .campsiteReportField
                        || ''
                    ).trim();

                if (
                    key === ''
                    || !Object.prototype
                        .hasOwnProperty
                        .call(
                            values,
                            key
                        )
                ) {
                    continue;
                }

                const value =
                    String(
                        values[key]
                        ?? ''
                    ).trim();

                if (value === '') {
                    continue;
                }

                const ratingStrong =
                    card.querySelector(
                        '.scout-rating-content strong'
                    );

                const valueStrong =
                    card.querySelector(
                        '.scout-report-value-content strong'
                    );

                const strong =
                    ratingStrong
                    || valueStrong;

                if (!strong) {
                    continue;
                }

                const unknown =
                    value.toLowerCase()
                    === 'unknown';

                const existingLink =
                    strong.querySelector(
                        'a'
                    );

                if (
                    existingLink
                    && /^https?:\/\//i.test(
                        value
                    )
                ) {
                    existingLink.href =
                        value;

                    existingLink.textContent =
                        value;
                } else {
                    strong.textContent =
                        value;
                }

                card.hidden = false;

                card.classList.remove(
                    'is-unanswered'
                );

                card.classList.toggle(
                    'is-explicit-unknown',
                    unknown
                );

                if (ratingStrong) {
                    const match =
                        value.match(
                            /^([1-5])(?:\/5)?$/
                        );

                    const rating =
                        match
                            ? Number(
                                match[1]
                            )
                            : 0;

                    card
                        .querySelectorAll(
                            '.scout-rating-dot'
                        )
                        .forEach(
                            (
                                dot,
                                index
                            ) => {
                                dot.classList.toggle(
                                    'is-filled',
                                    rating > 0
                                    && index < rating
                                );
                            }
                        );

                    const dots =
                        card.querySelector(
                            '.scout-rating-dots'
                        );

                    if (dots) {
                        dots.setAttribute(
                            'aria-label',
                            rating > 0
                                ? `${rating} out of 5`
                                : value
                        );
                    }
                }
            }
        };

    const getJson =
        async (url) => {
            const response =
                await fetch(
                    url,
                    {
                        credentials:
                            'same-origin',
                        cache:
                            'no-store',
                        headers: {
                            Accept:
                                'application/json',
                        },
                    }
                );

            if (!response.ok) {
                throw new Error(
                    'Unable to retrieve mapped campsite information.'
                );
            }

            return response.json();
        };

    const canonicalSites =
        () => {
            if (!slug) {
                return Promise.resolve(
                    []
                );
            }

            if (!canonicalDataPromise) {
                canonicalDataPromise =
                    getJson(
                        `/api/place-campsites.php?slug=${encodeURIComponent(slug)}`
                    )
                        .then(
                            (data) =>
                                data.ok
                                && Array.isArray(
                                    data.sites
                                )
                                    ? data.sites
                                    : []
                        )
                        .catch(
                            () => []
                        );
            }

            return canonicalDataPromise;
        };

    const mappedFeatures =
        () => {
            const url =
                String(
                    mapCard?.dataset
                        .placeMapFeaturesApi
                    || ''
                );

            if (!url) {
                return Promise.resolve(
                    []
                );
            }

            if (!mappedDataPromise) {
                mappedDataPromise =
                    getJson(url)
                        .then(
                            (data) =>
                                data.ok
                                && Array.isArray(
                                    data.features
                                )
                                    ? data.features
                                    : []
                        )
                        .catch(
                            () => []
                        );
            }

            return mappedDataPromise;
        };

    const reportContext =
        () => {
            const header =
                document.querySelector(
                    '.scout-report-header'
                );

            if (!header) {
                return null;
            }

            let element =
                header.querySelector(
                    '[data-selected-campsite-context]'
                );

            if (!element) {
                element =
                    document.createElement(
                        'p'
                    );

                element.className =
                    'place-selected-campsite-context';

                element.dataset
                    .selectedCampsiteContext =
                    '';

                const container =
                    header.querySelector(
                        'div'
                    )
                    || header;

                container.appendChild(
                    element
                );
            }

            return element;
        };

    const showContext =
        (
            name,
            kind,
            selection
        ) => {
            const context =
                reportContext();

            if (!context) {
                return;
            }

            context.replaceChildren();

            const label =
                document.createElement(
                    'span'
                );

            label.textContent =
                kind === 'parking'
                    ? 'Selected parking area: '
                    : 'Viewing campsite: ';

            const strong =
                document.createElement(
                    'strong'
                );

            strong.textContent =
                name;

            context.append(
                label,
                strong
            );

            if (mapElement) {
                const view =
                    document.createElement(
                        'a'
                    );

                view.href =
                    '#place-map-heading';

                view.textContent =
                    'View on map';

                view.addEventListener(
                    'click',
                    () =>
                        focusSelection(
                            selection
                        )
                );

                context.appendChild(
                    view
                );
            }
        };

    const buildPopup =
        (
            name,
            lines,
            mapped
        ) => {
            const wrapper =
                document.createElement(
                    'div'
                );

            wrapper.className =
                'place-map-feature-popup';

            const strong =
                document.createElement(
                    'strong'
                );

            strong.textContent =
                name;

            wrapper.append(
                strong
            );

            for (const line of lines) {
                if (!line) {
                    continue;
                }

                const span =
                    document.createElement(
                        'span'
                    );

                span.textContent =
                    line;

                wrapper.append(
                    span
                );
            }

            if (!mapped) {
                const caution =
                    document.createElement(
                        'span'
                    );

                caution.textContent =
                    'Individual campsite location not mapped. Map shows the campground, not this site.';

                wrapper.append(
                    caution
                );
            }

            return wrapper;
        };

    const focusSelection =
        async (selection) => {
            const handle =
                window.LlamaScoutMap;

            if (
                !handle?.map
                || typeof L === 'undefined'
            ) {
                return;
            }

            const map =
                handle.map;

            let featureId =
                selection.featureId
                || 0;

            const lines = [
                ...(
                    selection.lines
                    || []
                ),
            ];

            if (
                selection.kind
                === 'canonical'
            ) {
                const sites =
                    await canonicalSites();

                const site =
                    sites.find(
                        (item) =>
                            String(
                                item.feature_id
                            )
                            === String(
                                selection.id
                            )
                    );

                featureId =
                    Number(
                        site?.map_feature_id
                    )
                    || 0;

                if (
                    site
                    && Array.isArray(
                        site.summary
                    )
                ) {
                    lines.push(
                        ...site.summary.slice(
                            0,
                            3
                        )
                    );
                }
            }

            if (highlight) {
                map.removeLayer(
                    highlight
                );

                highlight = null;
            }

            if (featureId) {
                const features =
                    await mappedFeatures();

                const feature =
                    features.find(
                        (item) =>
                            Number(
                                item.id
                            )
                            === featureId
                    );

                if (feature?.geometry) {
                    highlight =
                        L.geoJSON(
                            feature.geometry,
                            {
                                style: {
                                    color:
                                        '#f8d028',
                                    weight:
                                        4,
                                    opacity:
                                        1,
                                    fillOpacity:
                                        .24,
                                },
                                pointToLayer:
                                    (
                                        _,
                                        latLng
                                    ) =>
                                        L.circleMarker(
                                            latLng,
                                            {
                                                radius:
                                                    11,
                                                color:
                                                    '#f8d028',
                                                weight:
                                                    3,
                                            }
                                        ),
                            }
                        )
                            .addTo(
                                map
                            );

                    const bounds =
                        highlight
                            .getBounds();

                    if (
                        bounds.isValid()
                    ) {
                        if (
                            bounds
                                .getNorthEast()
                                .equals(
                                    bounds
                                        .getSouthWest()
                                )
                        ) {
                            map.setView(
                                bounds
                                    .getCenter(),
                                Math.min(
                                    17,
                                    map.getMaxZoom()
                                ),
                                {
                                    animate:
                                        true,
                                }
                            );
                        } else {
                            map.fitBounds(
                                bounds.pad(
                                    .8
                                ),
                                {
                                    maxZoom:
                                        Math.min(
                                            17,
                                            map.getMaxZoom()
                                        ),
                                    animate:
                                        true,
                                }
                            );
                        }

                        L.popup({
                            maxWidth:
                                280,
                        })
                            .setLatLng(
                                bounds
                                    .getCenter()
                            )
                            .setContent(
                                buildPopup(
                                    selection.name,
                                    lines,
                                    true
                                )
                            )
                            .openOn(
                                map
                            );

                        return;
                    }
                }
            }

            const lat =
                Number(
                    mapElement?.dataset
                        .placeLatitude
                );

            const lng =
                Number(
                    mapElement?.dataset
                        .placeLongitude
                );

            if (
                Number.isFinite(lat)
                && Number.isFinite(lng)
            ) {
                map.setView(
                    [
                        lat,
                        lng,
                    ],
                    Math.min(
                        15,
                        map.getMaxZoom()
                    ),
                    {
                        animate:
                            true,
                    }
                );

                L.popup({
                    maxWidth:
                        280,
                })
                    .setLatLng([
                        lat,
                        lng,
                    ])
                    .setContent(
                        buildPopup(
                            selection.name,
                            lines,
                            false
                        )
                    )
                    .openOn(
                        map
                    );
            }
        };

    const choose =
        (
            row,
            shouldFocus = true
        ) => {
            if (!row) {
                return;
            }

            const isCanonical =
                row.hasAttribute(
                    'data-campsite-id'
                );

            const isMapSite =
                row.hasAttribute(
                    'data-stay-map-site-id'
                );

            const isParking =
                row.hasAttribute(
                    'data-stay-parking-id'
                );

            if (
                !isCanonical
                && !isMapSite
                && !isParking
            ) {
                return;
            }

            const name =
                isCanonical
                    ? row.querySelector(
                        '.place-campsite-row-name'
                    )?.textContent.trim()
                        || 'Campsite'
                    : isMapSite
                        ? row.dataset
                            .stayMapSiteName
                            || 'Campsite'
                        : row.dataset
                            .stayParkingName
                            || 'Parking area';

            const lines =
                isCanonical
                    ? []
                    : [
                        row.dataset
                            .stayMapSiteSummary
                        || row.dataset
                            .stayParkingCost
                        || '',
                        row.dataset
                            .stayMapSiteArea
                        || row.dataset
                            .stayParkingStatus
                        || '',
                    ].filter(
                        Boolean
                    );

            lastSelection = {
                row,
                name,
                lines,
                kind:
                    isCanonical
                        ? 'canonical'
                        : (
                            isMapSite
                                ? 'mapped'
                                : 'parking'
                        ),
                id:
                    row.dataset
                        .campsiteId
                    || '',
                featureId:
                    Number(
                        row.dataset
                            .stayMapSiteId
                        || row.dataset
                            .stayParkingId
                    )
                    || 0,
            };

            /*
             * Canonical Site clicks are handled by place-campsites.js, which
             * already applies Place -> Area -> Site effective answers.
             *
             * Parking rows represent Areas, so restore the original Place
             * report and then apply only that Area's direct overrides.
             */
            if (isParking) {
                applyAreaReport(
                    row
                );
            } else if (isMapSite) {
                restorePlaceReport();
            }

            showContext(
                name,
                isParking
                    ? 'parking'
                    : 'campsite',
                lastSelection
            );

            if (shouldFocus) {
                void focusSelection(
                    lastSelection
                );
            }
        };

    section.addEventListener(
        'click',
        (event) => {
            const row =
                event.target.closest(
                    '[data-campsite-id], [data-stay-map-site-id], [data-stay-parking-id]'
                );

            if (
                row
                && section.contains(
                    row
                )
            ) {
                choose(
                    row,
                    true
                );
            }
        }
    );

    /*
     * The canonical browser auto-selects a Site on load and after filters.
     * Update the report context without moving the map or page.
     */
    let selectedKey = '';

    const reflectSelection =
        () => {
            const row =
                section.querySelector(
                    '[data-campsite-id].is-selected'
                );

            if (!row) {
                return;
            }

            const key =
                row.dataset
                    .campsiteId
                || '';

            if (
                key
                && selectedKey
                    !== key
            ) {
                selectedKey =
                    key;

                choose(
                    row,
                    false
                );
            }
        };

    const observer =
        new MutationObserver(
            () =>
                requestAnimationFrame(
                    reflectSelection
                )
        );

    observer.observe(
        section,
        {
            subtree:
                true,
            childList:
                true,
            attributes:
                true,
            attributeFilter: [
                'class',
            ],
        }
    );

    reflectSelection();
})();
