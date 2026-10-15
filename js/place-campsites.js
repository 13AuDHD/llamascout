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

    const repairMojibake = (value) => {
        let text =
            String(
                value ?? ''
            ).trim();

        if (!text) {
            return '';
        }

        if (
            /(?:Â|Ã|â€|â€™|â€œ|â€|â€“|â€”|â€¦)/.test(
                text
            )
        ) {
            try {
                const bytes =
                    Uint8Array.from(
                        Array.from(text).map(
                            (character) =>
                                character.charCodeAt(0)
                                & 0xff
                        )
                    );

                const repaired =
                    new TextDecoder(
                        'utf-8',
                        {
                            fatal: true
                        }
                    ).decode(bytes);

                if (repaired) {
                    text = repaired;
                }
            } catch (error) {
                text = text
                    .replaceAll('Â·', '-')
                    .replaceAll('Â×', 'x')
                    .replaceAll('Â', '');
            }
        }

        return text
            .replace(/\s+/g, ' ')
            .trim();
    };

    const clean = (value) =>
        repairMojibake(value);

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
            clean(value)
                .toLowerCase();

        if (
            value === true
            || value === 1
            || [
                '1',
                'yes',
                'y',
                'true',
                'available',
                'allowed'
            ].includes(normalized)
        ) {
            return 'Yes';
        }

        if (
            value === false
            || value === 0
            || [
                '0',
                'no',
                'n',
                'false',
                'none',
                'not available',
                'not allowed'
            ].includes(normalized)
        ) {
            return 'No';
        }

        return clean(value);
    };

    const smartCase = (value) => {
        const text =
            clean(value);

        if (!text) {
            return '';
        }

        const letters =
            text.replace(
                /[^A-Za-z]/g,
                ''
            );

        if (
            letters
            && letters === letters.toUpperCase()
        ) {
            return text
                .toLowerCase()
                .replace(
                    /\b[a-z]/g,
                    (letter) =>
                        letter.toUpperCase()
                )
                .replace(/\bRv\b/g, 'RV')
                .replace(/\bAda\b/g, 'ADA')
                .replace(/\bUs\b/g, 'US');
        }

        return text;
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
            || smartCase(
                clean(value)
                    .replaceAll('_', ' ')
            );
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
            || smartCase(
                clean(value)
                    .replaceAll('_', ' ')
            );
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
            || smartCase(
                clean(value)
                    .replaceAll('_', ' ')
            );
    };

    const siteFeatureValues = (
        site,
        key
    ) => {
        const features =
            Array.isArray(site.features)
                ? site.features
                : [];

        return features
            .filter(
                (feature) =>
                    clean(
                        feature.feature_key
                    ) === key
            )
            .map(
                (feature) =>
                    smartCase(
                        yesNo(
                            feature.feature_value
                        )
                    )
            )
            .filter(Boolean);
    };

    const siteFeature = (
        site,
        key
    ) =>
        siteFeatureValues(
            site,
            key
        )[0] || '';

    const accessibleLabel = (value) => {
        const status =
            clean(value)
                .toLowerCase();

        if (status === 'yes') {
            return 'Yes';
        }

        if (status === 'no') {
            return 'No';
        }

        if (status === 'unknown') {
            return 'Unknown';
        }

        return yesNo(value);
    };

    const closureStatus = (site) => {
        if (site?.is_closed !== true) {
            return '';
        }

        const status =
            String(
                site.operating_status
                || ''
            ).toLowerCase();

        return {
            temporary: 'Temporarily closed',
            seasonal: 'Seasonally closed',
            permanent: 'Permanently closed'
        }[status]
            || 'Closed';
    };

    const closureDetails = (site) => {
        const status =
            closureStatus(site);

        if (!status) {
            return '';
        }

        const reason =
            clean(
                site.closure_reason
                || ''
            ).replaceAll('_', ' ');

        const reopening =
            clean(
                site.expected_reopening
                || ''
            );

        const verified =
            clean(
                site.closure_verified_at
                || ''
            );

        return `
            <aside
                class="place-campsite-closure-notice"
                role="status"
            >
                <strong>
                    🚫 ${escapeHtml(status)}
                </strong>

                ${
                    reason
                    && reason !== 'unknown'
                        ? `<span>Reason: ${escapeHtml(smartCase(reason))}</span>`
                        : ''
                }

                ${
                    reopening
                        ? `<span>Expected reopening: ${escapeHtml(reopening)}</span>`
                        : ''
                }

                ${
                    verified
                        ? `<small>Verified: ${escapeHtml(verified)}</small>`
                        : ''
                }
            </aside>
        `;
    };

    /*
     * This renderer intentionally creates only the selected-Site shell.
     * Site facts belong in the Scout Report. The old duplicate Site Details /
     * Parking / Hookups / Amenities / Environment / Operations renderer has
     * been removed, not hidden.
     *
     * The companion rich-detail script adds only Site photos, permitted
     * equipment, and source-specific RIDB metadata.
     */
    const renderSiteDetail = (
        site
    ) => `
        <article
            class="place-campsite-detail${closureStatus(site) ? ' is-closed' : ''}"
            data-campsite-detail-id="${escapeHtml(site.feature_id)}"
        >
            <header class="place-campsite-detail-heading">
                <div>
                    <p class="eyebrow">
                        Selected Site
                    </p>

                    <h3>
                        ${escapeHtml(
                            smartCase(
                                site.display_name
                            )
                        )}
                    </h3>
                </div>
            </header>

            ${closureDetails(site)}
        </article>
    `;

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

    const siteReportValues = (
        site
    ) => {
        const values = {};

        const set = (
            key,
            value
        ) => {
            const text =
                clean(value);

            if (text !== '') {
                values[key] =
                    text;
            }
        };

        set(
            'site_accessible',
            accessibleLabel(
                site.accessible_status
            )
        );

        if (
            number(site.max_vehicles)
            !== null
        ) {
            set(
                'vehicle_capacity',
                site.max_vehicles
            );
        }

        set(
            'max_vehicle_length_feet',
            feet(
                site.max_vehicle_length_ft
            )
        );

        set(
            'parking_surface',
            smartCase(
                site.driveway_surface
            )
        );

        if (
            number(site.max_people)
            !== null
        ) {
            set(
                'max_people',
                site.max_people
            );
        }

        set(
            'overhead_clearance_feet',
            feet(
                site.overhead_clearance_ft
            )
        );

        set(
            'parking_length_feet',
            feet(
                site.driveway_length_ft
            )
        );

        set(
            'parking_grade',
            smartCase(
                site.driveway_grade
            )
        );

        set(
            'site_length_feet',
            feet(
                site.site_length_ft
            )
        );

        set(
            'site_width_feet',
            feet(
                site.site_width_ft
            )
        );

        set(
            'tent_pad',
            yesNo(
                site.tent_pad
            )
        );

        set(
            'tent_pad_length_feet',
            feet(
                site.tent_pad_length_ft
            )
        );

        set(
            'tent_pad_width_feet',
            feet(
                site.tent_pad_width_ft
            )
        );

        set(
            'double_driveway',
            yesNo(
                siteFeature(
                    site,
                    'double_driveway'
                )
            )
        );

        const hikeIn =
            siteFeature(
                site,
                'hike_in_distance'
            );

        if (hikeIn !== '') {
            set(
                'hike_in_distance_feet',
                /\bft\b/i.test(hikeIn)
                    ? hikeIn
                    : `${hikeIn} ft`
            );
        }

        set(
            'site_rating',
            siteFeature(
                site,
                'site_rating'
            )
        );

        set(
            'condition_rating',
            siteFeature(
                site,
                'condition_rating'
            )
        );

        set(
            'location_rating',
            siteFeature(
                site,
                'location_rating'
            )
        );

        set(
            'capacity_size_rating',
            siteFeature(
                site,
                'capacity_size_rating'
            )
        );

        const hookupStatus =
            clean(
                site.hookup_status
            );

        if (
            hookupStatus !== ''
            && hookupStatus
                !== 'unknown'
        ) {
            set(
                'site_hookups_available',
                hookupStatus
                    === 'none'
                    ? 'No'
                    : 'Yes'
            );
        }

        set(
            'hookup_electric',
            yesNo(
                site.electric_hookup
            )
        );

        set(
            'hookup_electric_service',
            smartCase(
                site.electric_service
            )
        );

        set(
            'hookup_water',
            yesNo(
                site.water_hookup
            )
        );

        set(
            'hookup_sewer',
            yesNo(
                site.sewer_hookup
            )
        );

        const siteType =
            clean(
                site.site_type
            );

        if (
            siteType === 'tent_site'
            || siteType === 'mixed_site'
            || yesNo(
                site.tent_pad
            ) === 'Yes'
        ) {
            set(
                'tent_camping_suitable',
                'Yes'
            );
        }

        if (
            [
                'rv_site',
                'mixed_site',
                'vehicle_site'
            ].includes(
                siteType
            )
        ) {
            set(
                'rv_suitable',
                'Yes'
            );
        }

        const parkingStyle =
            clean(
                site.parking_style
            );

        if (
            parkingStyle !== ''
            && parkingStyle
                !== 'unknown'
        ) {
            set(
                'pull_through',
                parkingStyle
                    === 'pull_through'
                    ? 'Yes'
                    : 'No'
            );

            set(
                'back_in',
                parkingStyle
                    === 'back_in'
                    ? 'Yes'
                    : 'No'
            );
        }

        return values;
    };

    const updateScoutReport = (
        site
    ) => {
        const values =
            siteReportValues(
                site
            );

        document
            .querySelectorAll(
                '[data-campsite-report-field]'
            )
            .forEach(
                (card) => {
                    const key =
                        clean(
                            card.dataset
                                .campsiteReportField
                        );

                    const value =
                        values[key]
                        || '';

                    const strong =
                        card.querySelector(
                            '.scout-report-value-content strong'
                        );

                    if (!strong) {
                        return;
                    }

                    if (value === '') {
                        card.hidden =
                            true;

                        return;
                    }

                    strong.textContent =
                        value;

                    card.hidden =
                        false;

                    card.classList.remove(
                        'is-unanswered',
                        'is-explicit-unknown'
                    );
                }
            );
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
                    sites[0]
                        .feature_id
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
                        <p class="eyebrow">
                            Sites
                        </p>

                        <h2 id="place-campsites-heading">
                            Choose a Site
                        </h2>

                        <p>
                            Choose a Site to view its details in the Scout Report below.
                        </p>
                    </div>

                    <strong class="place-campsites-count">
                        ${sites.length.toLocaleString()}
                        ${sites.length === 1 ? 'Site' : 'Sites'}
                    </strong>
                </div>

                <div class="place-campsites-tools">
                    <label class="place-campsites-search">
                        <span>
                            Find a Site
                        </span>

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
                        aria-label="Filter Sites"
                    >
                        <button
                            type="button"
                            class="is-active"
                            data-campsite-filter="all"
                        >
                            All
                        </button>

                        <button
                            type="button"
                            data-campsite-filter="electric"
                        >
                            Electric
                        </button>

                        <button
                            type="button"
                            data-campsite-filter="nonelectric"
                        >
                            Nonelectric
                        </button>

                        <button
                            type="button"
                            data-campsite-filter="tent"
                        >
                            Tent
                        </button>

                        <button
                            type="button"
                            data-campsite-filter="rv"
                        >
                            RV
                        </button>

                        <button
                            type="button"
                            data-campsite-filter="accessible"
                        >
                            Accessible
                        </button>
                    </div>
                </div>

                <div
                    class="place-campsites-list"
                    data-campsite-list
                    aria-label="Sites"
                ></div>

                <div
                    data-campsite-selected-detail
                ></div>
            </section>
        `;

        const list =
            mount.querySelector(
                '[data-campsite-list]'
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
                            candidate
                                .feature_id
                        ) === String(
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

            updateScoutReport(
                site
            );

            const detailMount =
                mount.querySelector(
                    '[data-campsite-selected-detail]'
                );

            if (detailMount) {
                detailMount.innerHTML =
                    renderSiteDetail(
                        site
                    );
            }

            mount
                .querySelectorAll(
                    '[data-campsite-id]'
                )
                .forEach(
                    (row) => {
                        const active =
                            row.dataset
                                .campsiteId
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
                    }
                );

            const selectedButton =
                mount.querySelector(
                    `[data-campsite-id="${CSS.escape(selectedId)}"]`
                );

            if (selectedButton) {
                selectedButton.scrollIntoView({
                    behavior:
                        'smooth',
                    block:
                        'nearest',
                    inline:
                        'nearest'
                });
            }

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

        const syncSelectionClasses = () => {
            mount
                .querySelectorAll(
                    '[data-campsite-id]'
                )
                .forEach(
                    (row) => {
                        const active =
                            row.dataset
                                .campsiteId
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
                    }
                );
        };

        const renderList = () => {
            const query =
                clean(
                    search.value
                ).toLowerCase();

            const visible =
                sites.filter(
                    (site) => {
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
                            site.site_name,
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
                            .map(clean)
                            .join(' ')
                            .toLowerCase();

                        return haystack.includes(
                            query
                        );
                    }
                );

            if (
                visible.length
                === 0
            ) {
                list.innerHTML = `
                    <div class="place-campsites-empty">
                        No Sites match those filters.
                    </div>
                `;

                /*
                 * Filtering the list must never rerender or expose a second
                 * Site-facts presentation. Leave the current selected detail
                 * and Scout Report untouched until the user chooses a
                 * different Site.
                 */
                return;
            }

            list.innerHTML =
                visible
                    .map(
                        (site) => {
                            const summary =
                                Array.isArray(
                                    site.summary
                                )
                                    ? site.summary
                                        .map(clean)
                                        .filter(Boolean)
                                    : [];

                            const isSelected =
                                String(
                                    site.feature_id
                                ) === selectedId;

                            return `
                                <button
                                    type="button"
                                    class="place-campsite-row${isSelected ? ' is-selected' : ''}${closureStatus(site) ? ' is-closed' : ''}"
                                    data-campsite-id="${escapeHtml(site.feature_id)}"
                                    aria-current="${isSelected ? 'true' : 'false'}"
                                >
                                    <span class="place-campsite-row-name">
                                        ${escapeHtml(
                                            smartCase(
                                                site.display_name
                                            )
                                        )}
                                    </span>

                                    ${
                                        closureStatus(site)
                                            ? '<span class="place-campsite-closed-badge">🚫 Closed</span>'
                                            : ''
                                    }

                                    <span class="place-campsite-row-summary">
                                        ${escapeHtml(
                                            summary
                                                .slice(
                                                    0,
                                                    3
                                                )
                                                .join(
                                                    ' - '
                                                )
                                        )}
                                    </span>
                                </button>
                            `;
                        }
                    )
                    .join('');

            list
                .querySelectorAll(
                    '[data-campsite-id]'
                )
                .forEach(
                    (button) => {
                        button.addEventListener(
                            'click',
                            () => {
                                selectSite(
                                    button.dataset
                                        .campsiteId
                                );
                            }
                        );
                    }
                );

            /*
             * If the active filter excludes the current Site, select the first
             * visible result. If the current Site is still visible, do not
             * rerender it merely because a filter/search changed.
             */
            if (
                !visible.some(
                    (site) =>
                        String(
                            site.feature_id
                        ) === selectedId
                )
            ) {
                selectSite(
                    visible[0]
                        .feature_id
                );
            } else {
                syncSelectionClasses();
            }
        };

        search.addEventListener(
            'input',
            renderList
        );

        filters.forEach(
            (button) => {
                button.addEventListener(
                    'click',
                    () => {
                        activeFilter =
                            button.dataset
                                .campsiteFilter
                            || 'all';

                        filters.forEach(
                            (candidate) =>
                                candidate
                                    .classList
                                    .toggle(
                                        'is-active',
                                        candidate
                                            === button
                                    )
                        );

                        renderList();
                    }
                );
            }
        );

        renderList();

        /*
         * Initial selection is the one deliberate render after the list is
         * built. Filters/search thereafter leave it alone unless selection
         * actually changes.
         */
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
                        cache:
                            'no-store',
                        credentials:
                            'same-origin'
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
                'Llama Scout Site browser error:',
                error
            );

            mount.remove();
        }
    };

    load();
})();
