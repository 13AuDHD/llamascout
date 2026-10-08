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

    const labels = {
        site_access: 'Site access',
        double_driveway: 'Double driveway',
        campfire_allowed: 'Campfire allowed',
        fire_ring: 'Fire ring',
        grill: 'Grill',
        picnic_table: 'Picnic table',
        food_storage: 'Food storage',
        toilet: 'Toilet',
        trash_collection: 'Trash collection',
        pets_allowed: 'Pets allowed',
        equipment_mandatory: 'Equipment mandatory',
        lantern_post: 'Lantern post',
        lake_access: 'Lake access',
        river_access: 'River access',
        trailhead: 'Trailhead',
        trailhead_parking: 'Trailhead parking',
        accessibility: 'Accessibility',
        accessible_occupant_message: 'Accessibility note',
        accessible_boat_ramp: 'Accessible boat ramp',
        accessible_boat_dock: 'Accessible boat dock',
        accessible_campsites: 'Accessible campsite',
        recycling: 'Recycling',
        amphitheater: 'Amphitheater',
        geological_attractions: 'Geological attractions',
        scenic_overlooks: 'Scenic overlooks',
        visitor_center: 'Visitor center',
        self_pay_station: 'Self-pay station',
        day_use_area: 'Day-use area',
        fishing_pier: 'Fishing pier',
        picnic_shelter: 'Picnic shelter',
        playground: 'Playground',
        full_hookup: 'Full hookup',
        electricity_available: 'Electricity available',
        potable_water: 'Potable water',
        drinking_water: 'Drinking water',
        flush_toilet: 'Flush toilets',
        campfire_circle: 'Campfire circle',
        paved_parking: 'Paved parking',
        platform: 'Platform',
        site_rating: 'Site rating',
        condition_rating: 'Condition rating',
        location_rating: 'Location rating',
        capacity_size_rating: 'Capacity / size',
        hike_in_distance: 'Hike-in distance'
    };

    const featureLabel = (value) => {
        const key =
            clean(value);

        if (labels[key]) {
            return labels[key];
        }

        return key
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) =>
                letter.toUpperCase()
            );
    };

    const featureValue = (
        key,
        value
    ) => {
        const normalized =
            yesNo(value);

        if (
            key === 'hike_in_distance'
            && normalized !== ''
            && !Number.isNaN(
                Number(normalized)
            )
        ) {
            return `${normalized} ft`;
        }

        return smartCase(
            normalized
        );
    };

    const timeLabel = (value) => {
        const text =
            clean(value);

        const match =
            text.match(
                /^(\d{1,2}):(\d{2})(?::\d{2})?$/
            );

        if (!match) {
            return smartCase(text);
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
                    featureValue(
                        key,
                        feature.feature_value
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

    const card = (
        label,
        value,
        icon = ''
    ) => {
        const text =
            clean(value);

        if (!text) {
            return '';
        }

        const iconMarkup =
            icon
                ? `
                    <img
                        class="place-campsite-card-icon"
                        src="/assets/icons/${escapeHtml(icon)}.svg"
                        alt=""
                        aria-hidden="true"
                    >
                `
                : '';

        return `
            <div class="place-campsite-card scout-report-item scout-report-value-item">
                <div class="place-campsite-card-content scout-report-value-content">
                    <span>${escapeHtml(label)}</span>
                    <strong>${escapeHtml(text)}</strong>
                </div>
                ${iconMarkup}
            </div>
        `;
    };

    const section = (
        title,
        cards
    ) =>
        cards
            ? `
                <section class="place-campsite-card-section">
                    <h4>${escapeHtml(title)}</h4>
                    <div class="place-campsite-card-grid">
                        ${cards}
                    </div>
                </section>
            `
            : '';

    const renderSiteDetail = (
        site,
        totalSites = 0
    ) => {
        const dimensions = [
            feet(site.site_length_ft),
            feet(site.site_width_ft)
        ].filter(Boolean);

        const tentPadDimensions = [
            feet(site.tent_pad_length_ft),
            feet(site.tent_pad_width_ft)
        ].filter(Boolean);

        const siteType =
            siteTypeLabel(
                site.site_type
            );

        const dimensionsText =
            dimensions.length === 2
                ? dimensions.join(' x ')
                : dimensions[0] || '';

        const tentPadSize =
            tentPadDimensions.length === 2
                ? tentPadDimensions.join(' x ')
                : tentPadDimensions[0] || '';

        const hikeInDistance =
            siteFeature(
                site,
                'hike_in_distance'
            );

        const overviewCards = [
            card(
                'Total sites',
                number(totalSites) !== null
                    && Number(totalSites) > 0
                    ? totalSites
                    : ''
            ),
            card(
                'Site accessible?',
                accessibleLabel(
                    site.accessible_status
                ),
                'wheelchair'
            ),
            card(
                'Site type',
                siteType
            ),
            card(
                'Site size',
                dimensionsText
            ),
            card(
                'Maximum people',
                number(site.max_people) !== null
                    ? site.max_people
                    : ''
            ),
            card(
                'Maximum vehicles',
                number(site.max_vehicles) !== null
                    ? site.max_vehicles
                    : ''
            ),
            card(
                'Maximum vehicle length',
                feet(site.max_vehicle_length_ft)
            ),
            card(
                'Tent pad',
                yesNo(site.tent_pad),
                'tent'
            ),
            card(
                'Tent pad size',
                tentPadSize,
                'ruler-measure'
            ),
            card(
                'Capacity / size rating',
                siteFeature(
                    site,
                    'capacity_size_rating'
                )
            ),
            card(
                'Site rating',
                siteFeature(
                    site,
                    'site_rating'
                )
            ),
            card(
                'Location rating',
                siteFeature(
                    site,
                    'location_rating'
                )
            ),
            card(
                'Condition rating',
                siteFeature(
                    site,
                    'condition_rating'
                )
            ),
            card(
                'Maximum horses',
                number(site.max_horses) !== null
                    ? site.max_horses
                    : ''
            )
        ].join('');

        const parkingCards = [
            card(
                'Parking style',
                parkingLabel(
                    site.parking_style
                )
            ),
            card(
                'Double driveway?',
                yesNo(
                    siteFeature(
                        site,
                        'double_driveway'
                    )
                )
            ),
            card(
                'Driveway length',
                feet(
                    site.driveway_length_ft
                )
            ),
            card(
                'Driveway surface',
                smartCase(
                    site.driveway_surface
                )
            ),
            card(
                'Driveway grade',
                smartCase(
                    site.driveway_grade
                )
            ),
            card(
                'Overhead clearance',
                feet(
                    site.overhead_clearance_ft
                )
            ),
            card(
                'Hike-in distance',
                hikeInDistance
                    ? (
                        /\bft\b/i.test(
                            hikeInDistance
                        )
                            ? hikeInDistance
                            : `${hikeInDistance} ft`
                    )
                    : ''
            )
        ].join('');

        const hookupCards = [
            card(
                'Hookups',
                hookupLabel(
                    site.hookup_status
                )
            ),
            card(
                'Electric hookup',
                yesNo(
                    site.electric_hookup
                )
            ),
            card(
                'Electric service',
                smartCase(
                    site.electric_service
                )
            ),
            card(
                'Water hookup',
                yesNo(
                    site.water_hookup
                )
            ),
            card(
                'Sewer hookup',
                yesNo(
                    site.sewer_hookup
                )
            )
        ].join('');

        const siteAmenityCards = [
            card(
                'Picnic table',
                yesNo(
                    siteFeature(
                        site,
                        'picnic_table'
                    )
                )
            ),
            card(
                'Grill / barbecue',
                yesNo(
                    siteFeature(
                        site,
                        'grill'
                    )
                )
            ),
            card(
                'Fire ring',
                yesNo(
                    siteFeature(
                        site,
                        'fire_ring'
                    )
                )
            ),
            card(
                'Pets allowed',
                yesNo(
                    siteFeature(
                        site,
                        'pets_allowed'
                    )
                )
            ),
            card(
                'Food storage',
                yesNo(
                    siteFeature(
                        site,
                        'food_storage'
                    )
                )
            ),
            card(
                'Lantern post',
                yesNo(
                    siteFeature(
                        site,
                        'lantern_post'
                    )
                )
            )
        ].join('');

        const environmentCards = [
            card(
                'Proximity to water',
                smartCase(
                    site.proximity_to_water
                )
            ),
            card(
                'Shade',
                yesNo(
                    site.shade_source_value
                )
            ),
            card(
                'Privacy',
                yesNo(
                    site.privacy_source_value
                )
            ),
            card(
                'Quiet area',
                yesNo(
                    site.quiet_area_source_value
                )
            )
        ].join('');

        const operationCards = [
            card(
                'Check-in',
                timeLabel(
                    site.checkin_time
                )
            ),
            card(
                'Checkout',
                timeLabel(
                    site.checkout_time
                )
            )
        ].join('');

        const lodgingCards = [
            card(
                'Bed type',
                smartCase(site.bed_type)
            ),
            card(
                'Beds',
                number(site.bed_count) !== null
                    ? site.bed_count
                    : ''
            ),
            card(
                'Bedrooms',
                number(site.bedroom_count) !== null
                    ? site.bedroom_count
                    : ''
            ),
            card(
                'Rooms',
                number(site.room_count) !== null
                    ? site.room_count
                    : ''
            ),
            card(
                'Shower / bath',
                smartCase(
                    site.shower_bath_type
                )
            )
        ].join('');

        return `
            <article
                class="place-campsite-detail"
                data-campsite-detail-id="${escapeHtml(site.feature_id)}"
            >
                <header class="place-campsite-detail-heading">
                    <div>
                        <p class="eyebrow">Selected campsite</p>
                        <h3>${escapeHtml(smartCase(site.display_name))}</h3>
                    </div>
                </header>

                <div class="place-campsite-detail-grid">
                    ${section(
                        'Site details',
                        overviewCards
                    )}

                    ${section(
                        'Parking & access',
                        parkingCards
                    )}

                    ${section(
                        'Hookups',
                        hookupCards
                    )}

                    ${section(
                        'Site amenities',
                        siteAmenityCards
                    )}

                    ${section(
                        'Environment',
                        environmentCards
                    )}

                    ${section(
                        'Operations',
                        operationCards
                    )}

                    ${section(
                        'Lodging',
                        lodgingCards
                    )}
                </div>
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

                    ${renderSiteDetail(sites[0], sites.length)}
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
                            every site. Choose a site to see site-specific details.
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
                renderSiteDetail(site, sites.length);

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
                                class="place-campsite-row${isSelected ? ' is-selected' : ''}"
                                data-campsite-id="${escapeHtml(site.feature_id)}"
                                aria-current="${isSelected ? 'true' : 'false'}"
                            >
                                <span class="place-campsite-row-name">
                                    ${escapeHtml(smartCase(site.display_name))}
                                </span>

                                <span class="place-campsite-row-summary">
                                    ${escapeHtml(
                                        summary
                                            .slice(0, 3)
                                            .join(' - ')
                                    )}
                                </span>

                                <span
                                    class="place-campsite-row-arrow"
                                    aria-hidden="true"
                                >&gt;</span>
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
