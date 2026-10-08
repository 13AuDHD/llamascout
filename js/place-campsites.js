(() => {
    'use strict';

    const mount =
        document.querySelector(
            '[data-place-campsites-mount]'
        );

    if (!mount) {
        return;
    }

    const slug =
        String(
            mount.dataset.placeSlug
            || ''
        ).trim();

    if (!slug) {
        return;
    }

    const escapeHtml = (value) => {
        const node =
            document.createElement(
                'div'
            );

        node.textContent =
            value ?? '';

        return node.innerHTML;
    };

    const clean = (value) =>
        String(
            value ?? ''
        ).trim();

    const number = (value) => {
        const parsed =
            Number(value);

        return Number.isFinite(parsed)
            ? parsed
            : null;
    };

    const feet = (value) => {
        const parsed =
            number(value);

        if (
            parsed === null
            || parsed <= 0
        ) {
            return '';
        }

        const text =
            Number.isInteger(parsed)
                ? String(parsed)
                : parsed
                    .toFixed(1)
                    .replace(/\.0$/, '');

        return `${text} ft`;
    };

    const yesNo = (value) => {
        if (
            value === null
            || value === undefined
            || value === ''
        ) {
            return '';
        }

        const normalized =
            String(value)
                .toLowerCase()
                .trim();

        if (
            value === true
            || value === 1
            || normalized === '1'
            || normalized === 'yes'
            || normalized === 'y'
            || normalized === 'true'
        ) {
            return 'Yes';
        }

        if (
            value === false
            || value === 0
            || normalized === '0'
            || normalized === 'no'
            || normalized === 'n'
            || normalized === 'false'
        ) {
            return 'No';
        }

        return clean(value);
    };

    const siteTypeLabel = (value) => {
        const labels = {
            rv_site: 'RV site',
            tent_site: 'Tent site',
            mixed_site: 'Tent / RV',
            vehicle_site: 'Vehicle site',
            group_site: 'Group site',
            other: 'Other'
        };

        return labels[value]
            || clean(value)
                .replaceAll('_', ' ');
    };

    const parkingLabel = (value) => {
        const labels = {
            pull_through: 'Pull-through',
            back_in: 'Back-in',
            pull_in: 'Pull-in',
            parallel: 'Parallel',
            other: 'Other',
            unknown: 'Unknown'
        };

        return labels[value]
            || clean(value)
                .replaceAll('_', ' ');
    };

    const hookupLabel = (value) => {
        const labels = {
            full: 'Full hookups',
            electric_water: 'Electric + water',
            electric_only: 'Electric only',
            water_only: 'Water only',
            none: 'No hookups',
            varies: 'Varies',
            unknown: 'Unknown'
        };

        return labels[value]
            || clean(value)
                .replaceAll('_', ' ');
    };

    const featureLabel = (value) =>
        clean(value)
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) =>
                letter.toUpperCase()
            );

    const timeLabel = (value) => {
        const text =
            clean(value);

        const match =
            text.match(
                /^(\d{1,2}):(\d{2})(?::\d{2})?$/
            );

        if (!match) {
            return text;
        }

        let hour =
            Number(match[1]);

        const minute =
            match[2];

        const suffix =
            hour >= 12
                ? 'PM'
                : 'AM';

        hour %= 12;

        if (hour === 0) {
            hour = 12;
        }

        return `${hour}:${minute} ${suffix}`;
    };

    const fact = (
        label,
        value
    ) => {
        const text =
            clean(value);

        if (!text) {
            return '';
        }

        return `
            <div class="place-campsite-fact">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(text)}</strong>
            </div>
        `;
    };

    const featureRows = (features) => {
        if (
            !Array.isArray(features)
            || features.length === 0
        ) {
            return '';
        }

        const rows =
            features
                .map((feature) => {
                    const value =
                        clean(
                            feature.feature_value
                        );

                    const qualifier =
                        clean(
                            feature.qualifier
                        );

                    const display =
                        [
                            value,
                            qualifier
                        ]
                            .filter(Boolean)
                            .join(' Â· ');

                    return fact(
                        featureLabel(
                            feature.feature_key
                        ),
                        display || 'Available'
                    );
                })
                .join('');

        return rows
            ? `
                <div class="place-campsite-detail-group">
                    <h4>Site features</h4>
                    <div class="place-campsite-facts">
                        ${rows}
                    </div>
                </div>
            `
            : '';
    };

    const renderSiteDetail = (site) => {
        const dimensions = [
            feet(site.site_length_ft),
            feet(site.site_width_ft)
        ].filter(Boolean);

        const tentPadDimensions = [
            feet(site.tent_pad_length_ft),
            feet(site.tent_pad_width_ft)
        ].filter(Boolean);

        const capacityFacts = [
            fact(
                'Maximum people',
                number(site.max_people) !== null
                    ? site.max_people
                    : ''
            ),
            fact(
                'Maximum vehicles',
                number(site.max_vehicles) !== null
                    ? site.max_vehicles
                    : ''
            ),
            fact(
                'Maximum vehicle length',
                feet(site.max_vehicle_length_ft)
            ),
            fact(
                'Maximum horses',
                number(site.max_horses) !== null
                    ? site.max_horses
                    : ''
            )
        ].join('');

        const parkingFacts = [
            fact(
                'Parking style',
                parkingLabel(
                    site.parking_style
                )
            ),
            fact(
                'Driveway length',
                feet(
                    site.driveway_length_ft
                )
            ),
            fact(
                'Driveway surface',
                site.driveway_surface
            ),
            fact(
                'Driveway grade',
                site.driveway_grade
            ),
            fact(
                'Overhead clearance',
                feet(
                    site.overhead_clearance_ft
                )
            )
        ].join('');

        const hookupFacts = [
            fact(
                'Hookups',
                hookupLabel(
                    site.hookup_status
                )
            ),
            fact(
                'Electric hookup',
                yesNo(
                    site.electric_hookup
                )
            ),
            fact(
                'Electric service',
                site.electric_service
            ),
            fact(
                'Water hookup',
                yesNo(
                    site.water_hookup
                )
            ),
            fact(
                'Sewer hookup',
                yesNo(
                    site.sewer_hookup
                )
            )
        ].join('');

        const tentFacts = [
            fact(
                'Tent pad',
                yesNo(site.tent_pad)
            ),
            fact(
                'Tent pad size',
                tentPadDimensions.length === 2
                    ? tentPadDimensions.join(' Ã ')
                    : tentPadDimensions[0] || ''
            )
        ].join('');

        const environmentFacts = [
            fact(
                'Proximity to water',
                site.proximity_to_water
            ),
            fact(
                'Shade',
                site.shade_source_value
            ),
            fact(
                'Privacy',
                site.privacy_source_value
            ),
            fact(
                'Quiet area',
                site.quiet_area_source_value
            )
        ].join('');

        const operationFacts = [
            fact(
                'Check-in',
                timeLabel(
                    site.checkin_time
                )
            ),
            fact(
                'Checkout',
                timeLabel(
                    site.checkout_time
                )
            ),
            fact(
                'Accessibility',
                clean(
                    site.accessible_status
                ) === 'yes'
                    ? 'Accessible'
                    : (
                        clean(
                            site.accessible_status
                        ) === 'no'
                            ? 'Not designated accessible'
                            : (
                                clean(
                                    site.accessible_status
                                ) === 'unknown'
                                    ? 'Unknown'
                                    : ''
                            )
                    )
            )
        ].join('');

        const lodgingFacts = [
            fact(
                'Bed type',
                site.bed_type
            ),
            fact(
                'Beds',
                number(site.bed_count) !== null
                    ? site.bed_count
                    : ''
            ),
            fact(
                'Bedrooms',
                number(site.bedroom_count) !== null
                    ? site.bedroom_count
                    : ''
            ),
            fact(
                'Rooms',
                number(site.room_count) !== null
                    ? site.room_count
                    : ''
            ),
            fact(
                'Shower / bath',
                site.shower_bath_type
            )
        ].join('');

        const section = (
            title,
            rows
        ) =>
            rows
                ? `
                    <div class="place-campsite-detail-group">
                        <h4>${escapeHtml(title)}</h4>
                        <div class="place-campsite-facts">
                            ${rows}
                        </div>
                    </div>
                `
                : '';

        const siteType =
            siteTypeLabel(
                site.site_type
            );

        const dimensionsText =
            dimensions.length === 2
                ? dimensions.join(' Ã ')
                : dimensions[0] || '';

        const source =
            clean(
                site.source_provider
            );

        return `
            <article
                class="place-campsite-detail"
                data-campsite-detail-id="${escapeHtml(site.feature_id)}"
            >
                <header class="place-campsite-detail-heading">
                    <div>
                        <p class="eyebrow">Selected campsite</p>
                        <h3>${escapeHtml(site.display_name)}</h3>
                    </div>

                    <div class="place-campsite-detail-summary">
                        ${siteType
                            ? `<span>${escapeHtml(siteType)}</span>`
                            : ''}
                        ${dimensionsText
                            ? `<span>${escapeHtml(dimensionsText)}</span>`
                            : ''}
                    </div>
                </header>

                <div class="place-campsite-detail-grid">
                    ${section(
                        'Capacity & fit',
                        capacityFacts
                    )}

                    ${section(
                        'Parking & access',
                        parkingFacts
                    )}

                    ${section(
                        'Hookups',
                        hookupFacts
                    )}

                    ${section(
                        'Tent setup',
                        tentFacts
                    )}

                    ${section(
                        'Environment',
                        environmentFacts
                    )}

                    ${section(
                        'Operations',
                        operationFacts
                    )}

                    ${section(
                        'Lodging',
                        lodgingFacts
                    )}

                    ${featureRows(
                        site.features
                    )}
                </div>

                ${source
                    ? `
                        <p class="place-campsite-source">
                            Structured site information from
                            ${escapeHtml(source)}.
                            Scout observations remain separate.
                        </p>
                    `
                    : ''}
            </article>
        `;
    };

    const siteMatchesFilter = (
        site,
        filter
    ) => {
        if (filter === 'all') {
            return true;
        }

        if (filter === 'accessible') {
            return clean(
                site.accessible_status
            ) === 'yes';
        }

        if (filter === 'electric') {
            return (
                clean(
                    site.hookup_status
                ).includes('electric')
                || clean(
                    site.hookup_status
                ) === 'full'
                || yesNo(
                    site.electric_hookup
                ) === 'Yes'
            );
        }

        if (filter === 'nonelectric') {
            return (
                clean(
                    site.hookup_status
                ) === 'none'
                || yesNo(
                    site.electric_hookup
                ) === 'No'
            );
        }

        if (filter === 'tent') {
            return [
                'tent_site',
                'mixed_site'
            ].includes(
                clean(site.site_type)
            );
        }

        if (filter === 'rv') {
            return [
                'rv_site',
                'mixed_site',
                'vehicle_site'
            ].includes(
                clean(site.site_type)
            );
        }

        return true;
    };

    const renderBrowser = (
        payload
    ) => {
        const sites =
            Array.isArray(
                payload.sites
            )
                ? payload.sites
                : [];

        const browser =
            payload.browser
            || {};

        if (
            !browser.show_site_layer
            || sites.length === 0
        ) {
            mount.remove();
            return;
        }

        if (
            browser.show_single_site
            && sites.length === 1
        ) {
            mount.innerHTML = `
                <section
                    class="place-section place-campsites place-campsites-single"
                    aria-labelledby="place-campsites-heading"
                >
                    <div class="place-campsites-heading">
                        <div>
                            <p class="eyebrow">Campsite</p>
                            <h2 id="place-campsites-heading">
                                Site details
                            </h2>
                        </div>
                    </div>

                    ${renderSiteDetail(sites[0])}
                </section>
            `;

            return;
        }

        let selectedId =
            String(
                new URLSearchParams(
                    window.location.search
                ).get('site')
                || ''
            );

        if (
            !sites.some(
                (site) =>
                    String(
                        site.feature_id
                    ) === selectedId
            )
        ) {
            selectedId =
                String(
                    sites[0].feature_id
                );
        }

        mount.innerHTML = `
            <section
                class="place-section place-campsites"
                aria-labelledby="place-campsites-heading"
                data-place-campsites
            >
                <div class="place-campsites-heading">
                    <div>
                        <p class="eyebrow">Campsites</p>
                        <h2 id="place-campsites-heading">
                            Choose a campsite
                        </h2>
                        <p>
                            Shared campground information above applies to
                            every site. Choose a site to see what changes.
                        </p>
                    </div>

                    <strong class="place-campsites-count">
                        ${sites.length.toLocaleString()}
                        ${sites.length === 1 ? 'site' : 'sites'}
                    </strong>
                </div>

                <div class="place-campsites-tools">
                    <label class="place-campsites-search">
                        <span>Find a site</span>
                        <input
                            type="search"
                            placeholder="Site number or name"
                            autocomplete="off"
                            data-campsite-search
                        >
                    </label>

                    <div
                        class="place-campsites-filters"
                        role="group"
                        aria-label="Filter campsites"
                    >
                        <button
                            type="button"
                            class="is-active"
                            data-campsite-filter="all"
                        >All</button>

                        <button
                            type="button"
                            data-campsite-filter="electric"
                        >Electric</button>

                        <button
                            type="button"
                            data-campsite-filter="nonelectric"
                        >Nonelectric</button>

                        <button
                            type="button"
                            data-campsite-filter="tent"
                        >Tent</button>

                        <button
                            type="button"
                            data-campsite-filter="rv"
                        >RV</button>

                        <button
                            type="button"
                            data-campsite-filter="accessible"
                        >Accessible</button>
                    </div>
                </div>

                <div class="place-campsites-layout">
                    <div
                        class="place-campsites-list"
                        data-campsite-list
                    ></div>

                    <div
                        class="place-campsite-selected"
                        data-campsite-selected
                        aria-live="polite"
                    ></div>
                </div>
            </section>
        `;

        const list =
            mount.querySelector(
                '[data-campsite-list]'
            );

        const detail =
            mount.querySelector(
                '[data-campsite-selected]'
            );

        const search =
            mount.querySelector(
                '[data-campsite-search]'
            );

        const filters =
            Array.from(
                mount.querySelectorAll(
                    '[data-campsite-filter]'
                )
            );

        let activeFilter =
            'all';

        const selectSite = (
            featureId,
            updateUrl = true
        ) => {
            const site =
                sites.find(
                    (candidate) =>
                        String(
                            candidate.feature_id
                        )
                        === String(
                            featureId
                        )
                );

            if (!site) {
                return;
            }

            selectedId =
                String(
                    site.feature_id
                );

            detail.innerHTML =
                renderSiteDetail(site);

            mount
                .querySelectorAll(
                    '[data-campsite-id]'
                )
                .forEach((row) => {
                    const active =
                        row.dataset.campsiteId
                        === selectedId;

                    row.classList.toggle(
                        'is-selected',
                        active
                    );

                    row.setAttribute(
                        'aria-current',
                        active
                            ? 'true'
                            : 'false'
                    );
                });

            if (updateUrl) {
                const url =
                    new URL(
                        window.location.href
                    );

                url.searchParams.set(
                    'site',
                    selectedId
                );

                window.history.replaceState(
                    {},
                    '',
                    url
                );
            }
        };

        const renderList = () => {
            const query =
                clean(
                    search.value
                ).toLowerCase();

            const visible =
                sites.filter((site) => {
                    if (
                        !siteMatchesFilter(
                            site,
                            activeFilter
                        )
                    ) {
                        return false;
                    }

                    if (!query) {
                        return true;
                    }

                    const haystack = [
                        site.display_name,
                        site.site_code,
                        site.map_label,
                        siteTypeLabel(
                            site.site_type
                        ),
                        parkingLabel(
                            site.parking_style
                        ),
                        hookupLabel(
                            site.hookup_status
                        )
                    ]
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(
                        query
                    );
                });

            if (visible.length === 0) {
                list.innerHTML = `
                    <div class="place-campsites-empty">
                        No campsites match those filters.
                    </div>
                `;

                return;
            }

            list.innerHTML =
                visible
                    .map((site) => {
                        const summary =
                            Array.isArray(
                                site.summary
                            )
                                ? site.summary
                                : [];

                        const isSelected =
                            String(
                                site.feature_id
                            ) === selectedId;

                        return `
                            <button
                                type="button"
                                class="place-campsite-row${isSelected ? ' is-selected' : ''}"
                                data-campsite-id="${escapeHtml(site.feature_id)}"
                                aria-current="${isSelected ? 'true' : 'false'}"
                            >
                                <span class="place-campsite-row-name">
                                    ${escapeHtml(site.display_name)}
                                </span>

                                <span class="place-campsite-row-summary">
                                    ${escapeHtml(
                                        summary
                                            .slice(0, 3)
                                            .join(' Â· ')
                                    )}
                                </span>

                                <span
                                    class="place-campsite-row-arrow"
                                    aria-hidden="true"
                                >âº</span>
                            </button>
                        `;
                    })
                    .join('');

            list
                .querySelectorAll(
                    '[data-campsite-id]'
                )
                .forEach((button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            selectSite(
                                button.dataset
                                    .campsiteId
                            );
                        }
                    );
                });

            if (
                !visible.some(
                    (site) =>
                        String(
                            site.feature_id
                        ) === selectedId
                )
            ) {
                selectSite(
                    visible[0].feature_id
                );
            }
        };

        search.addEventListener(
            'input',
            renderList
        );

        filters.forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    activeFilter =
                        button.dataset
                            .campsiteFilter
                        || 'all';

                    filters.forEach(
                        (candidate) =>
                            candidate.classList.toggle(
                                'is-active',
                                candidate
                                    === button
                            )
                    );

                    renderList();
                }
            );
        });

        renderList();
        selectSite(
            selectedId,
            false
        );
    };

    const load = async () => {
        try {
            const response =
                await fetch(
                    `/api/place-campsites.php?slug=${encodeURIComponent(slug)}`,
                    {
                        cache: 'no-store',
                        credentials: 'same-origin'
                    }
                );

            if (!response.ok) {
                mount.remove();
                return;
            }

            const payload =
                await response.json();

            if (!payload.ok) {
                mount.remove();
                return;
            }

            renderBrowser(
                payload
            );
        } catch (error) {
            console.error(
                'Llama Scout campsite browser error:',
                error
            );

            mount.remove();
        }
    };

    load();
})();
