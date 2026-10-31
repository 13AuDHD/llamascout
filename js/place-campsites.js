(() => {
    'use strict';

    // Public Scout Report: show only the selected record's question groups.
    // Reuse the existing explorer/browser selection events; no second selector.
    const report = document.querySelector('.scout-report');
    if (report) {
        const placeGroups = '.scout-report-scope-place, .scout-report-scope-other-place';
        const siteGroups = '.scout-report-scope-site, .scout-report-scope-space';
        const showReportScope = (scope) => {
            const siteSelected = scope !== 'place';
            report.dataset.activeReportScope = siteSelected ? 'site' : 'place';
            report.querySelectorAll(placeGroups).forEach((group) => {
                group.hidden = siteSelected;
            });
            report.querySelectorAll(siteGroups).forEach((group) => {
                group.hidden = !siteSelected;
            });
        };
        const initialSite = new URLSearchParams(window.location.search).get('site');
        showReportScope(initialSite && /^\d+$/.test(initialSite) ? 'site' : 'place');
        window.addEventListener('llama:explorer-selection', (event) => {
            const kind = String(event.detail?.kind || 'place');
            showReportScope(kind === 'place' || kind === 'area' ? 'place' : 'site');
        });
        window.addEventListener('llama:explorer-browser-site-selected', () => {
            showReportScope('site');
        });
    }

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

    // Keep the existing Compare Reports link in sync with Site selection.
    const historyLink = document.querySelector('.scout-report-history-link');
    const placeHistoryUrl = historyLink?.getAttribute('href') || '';
    const syncHistoryLink = (siteId = '') => {
        if (!historyLink || !placeHistoryUrl) return;
        const url = new URL(placeHistoryUrl, window.location.origin);
        if (/^\\d+$/.test(String(siteId)) && Number(siteId) > 0) {
            url.searchParams.set('site', String(siteId));
        } else {
            url.searchParams.delete('site');
        }
        historyLink.href = url.pathname + url.search;
    };

    // Other map/explorer controls can switch back to the Place without
    // calling the campsite browser's clearSelection method.
    window.addEventListener('llama:explorer-selection', (event) => {
        const kind = String(event.detail?.kind || 'place');
        if (kind === 'place' || kind === 'area') {
            syncHistoryLink();
        } else {
            const siteId = event.detail?.id ?? event.detail?.siteId;
            if (siteId != null) syncHistoryLink(String(siteId));
        }
    });


    if (!slug) {
        return;
    }

    /*
     * Issue #41: everyone uses the same Site browser. This flag controls
     * whether selecting a Site may request Complete Access report data.
     * The catalog endpoint remains responsible for field-level disclosure.
     */
    const hasCompleteAccess =
        mount.dataset.completeAccess === '1';

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
        )
            .replace(/\s+/g, ' ')
            .trim();

    const updateTargetContributionPrompts =
        () => {
            document
                .querySelectorAll(
                    '[data-report-target-section]'
                )
                .forEach(
                    (reportSection) => {
                        const prompt =
                            reportSection.querySelector(
                                '[data-target-report-prompt]'
                            );

                        if (!prompt) {
                            return;
                        }

                        reportSection
                            .querySelectorAll(
                                '.scout-report-contribution-prompt:not([data-target-report-prompt])'
                            )
                            .forEach(
                                (originalPrompt) => {
                                    originalPrompt.hidden =
                                        true;
                                }
                            );

                        const fixedMissing =
                            Number(
                                reportSection.dataset
                                    .reportFixedMissing
                                || 0
                            );

                        const applicableCount =
                            Number(
                                reportSection.dataset
                                    .reportApplicableCount
                                || 0
                            );

                        const switchableCards =
                            Array.from(
                                reportSection
                                    .querySelectorAll(
                                        '[data-campsite-report-field]'
                                    )
                            );

                        const switchableMissing =
                            switchableCards
                                .filter(
                                    (card) =>
                                        card.classList.contains(
                                            'is-unanswered'
                                        )
                                )
                                .length;

                        const missingCount =
                            Math.max(
                                0,
                                fixedMissing
                                + switchableMissing
                            );

                        if (missingCount < 1) {
                            prompt.hidden =
                                true;

                            return;
                        }

                        const empty =
                            applicableCount > 0
                            && missingCount
                                >= applicableCount;

                        prompt.classList.toggle(
                            'is-empty',
                            empty
                        );

                        prompt.classList.toggle(
                            'is-partial',
                            !empty
                        );

                        const heading =
                            prompt.querySelector(
                                'strong'
                            );

                        const copy =
                            prompt.querySelector(
                                'p'
                            );

                        const sectionLabel =
                            String(
                                reportSection.dataset
                                    .reportSectionLabel
                                || 'this section'
                            );

                        if (heading) {
                            heading.textContent =
                                empty
                                    ? 'Be the first to add this'
                                    : 'Know something we are missing?';
                        }

                        if (copy) {
                            copy.textContent =
                                empty
                                    ? `No one has reported ${sectionLabel.toLowerCase()} information here yet.`
                                    : `${missingCount} applicable detail${missingCount === 1 ? '' : 's'} in this section ${missingCount === 1 ? 'has' : 'have'} not been reported yet.`;
                        }

                        prompt.hidden =
                            false;
                    }
                );
        };

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

        return labels[value]             || smartCase(
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

    // The selected Site name already appears in the horizontal selector.
    // Keep closure details, but do not repeat the Site number above photos.
    // Keep the detail mount for the independent photo/equipment renderer.
    // Only the redundant selected-site heading is removed.
    const renderSiteDetail = (site) => `
        <article
            class="place-campsite-detail${closureStatus(site) ? ' is-closed' : ''}"
            data-campsite-detail-id="${escapeHtml(site.feature_id)}"
        >
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

        const mergeReportValues = (
            source
        ) => {
            if (
                !source
                || typeof source !== 'object'
                || Array.isArray(source)
            ) {
                return;
            }

            Object.entries(
                source
            ).forEach(
                ([
                    key,
                    value
                ]) => {
                    const text =
                        clean(value);

                    if (text !== '') {
                        values[key] =
                            text;
                    }
                }
            );
        };

        mergeReportValues(
            site.report_inherited_values
        );

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

        mergeReportValues(
            site.report_override_values
        );

        // The canonical model lists unanswered keys as well as answers.
        // Clear any legacy-column fallback for those questions before
        // overlaying the final published values. A stale legacy column
        // must never turn a canonical "Not provided" into a false answer.
        if (site.report_answer_states && typeof site.report_answer_states === 'object') {
            Object.entries(site.report_answer_states).forEach(([key, state]) => {
                if (state === 'unanswered') {
                    delete values[key];
                }
            });
        }

        mergeReportValues(
            site.report_effective_values
        );

        // The selected Site identifier is the first Scout Report answer.
        // Use the catalog's displayed name, not an optional report field.
        if (!values.site_number) set('site_number', smartCase(site.display_name));

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
                    /*
                     * Locked canonical cards keep their server-rendered access
                     * state. Public catalog selection must never overwrite a
                     * lock with a value derived from Site data.
                     */
                    if (
                        card.classList.contains(
                            'is-access-locked'
                        )
                    ) {
                        return;
                    }

                    const key =
                        clean(
                            card.dataset
                                .campsiteReportField
                        );

                    const value =
                        Object.prototype.hasOwnProperty.call(
                            values,
                            key
                        )
                            ? values[key]
                            : '';

                    // Reconciliation mode keeps unreported Site questions
                    // visible rather than removing them from the report.
                    const isUnanswered = value === '';
                    const displayValue = isUnanswered
                        ? 'Not provided'
                        : value;

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
                        return;
                    }

                    const isUnknown =
                        clean(value)
                            .toLowerCase()
                        === 'unknown';

                    const fieldType =
                        clean(
                            card.dataset
                                .campsiteReportType
                        );

                    if (
                        fieldType === 'url'
                        && /^https?:\/\//i.test(
                            value
                        )
                    ) {
                        let link =
                            strong.querySelector(
                                'a'
                            );

                        if (!link) {
                            link =
                                document.createElement(
                                    'a'
                                );

                            link.target =
                                '_blank';

                            link.rel =
                                'noopener noreferrer';

                            strong.replaceChildren(
                                link
                            );
                        }

                        link.href =
                            value;

                        link.textContent =
                            value;
                    } else {
                        strong.textContent =
                            displayValue;
                    }

                    // Refresh attribution with each selected Site. Never
                    // leave the previous Site's contributor on a new card.
                    let sourceLine = card.querySelector(
                        '.scout-report-answer-source'
                    );
                    const source = site.report_answer_sources?.[key];
                    if (source && !isUnanswered) {
                        if (!sourceLine) {
                            sourceLine = document.createElement('small');
                            sourceLine.className = 'scout-report-answer-source';
                            const valueContent = card.querySelector(
                                '.scout-report-value-content, .scout-rating-content'
                            );
                            valueContent?.append(sourceLine);
                        }
                        const dateLabel = clean(source.dateLabel);
                        const sourceLabel = clean(source.label);
                        if (sourceLine) {
                            sourceLine.textContent = [
                                dateLabel,
                                sourceLabel
                            ].filter(Boolean).join(' · ');
                            sourceLine.hidden = !sourceLine.textContent;
                        }
                    } else if (sourceLine) {
                        sourceLine.textContent = '';
                        sourceLine.hidden = true;
                    }

                    card.hidden =
                        false;

                    card.classList.toggle(
                        'is-unanswered',
                        isUnanswered
                    );

                    card.classList.toggle(
                        'is-explicit-unknown',
                        isUnknown
                    );

                    if (ratingStrong) {
                        const ratingMatch =
                            String(value)
                                .match(
                                    /^([1-5])(?:\/5)?$/
                                );

                        const rating =
                            ratingMatch
                                ? Number(
                                    ratingMatch[1]
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
                                    : displayValue
                            );
                        }
                    }
                }
            );

        updateTargetContributionPrompts();

        const warningState = {
            warnings:
                site.report_warnings
                || {},
            target:
                'Site',
        };

        window.LlamaScoutReportWarningsState =
            warningState;

        window.dispatchEvent(
            new CustomEvent(
                'llama:report-warnings-change',
                {
                    detail:
                        warningState,
                }
            )
        );
    };

    // Issue #41: the old public-only Site renderer was removed. The shared
    // browser below receives either the narrow public catalog or the Complete
    // Access catalog from the server and renders the same structure for both.

    const renderBrowser = (
        payload
    ) => {
        const sites =
            Array.isArray(
                payload.sites
            )
                ? payload.sites
                : [];

        const siteDetails = new Map();
        let selectionRequest = 0;

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

        // A Place starts on its own overview. Only explicit Site links
        // restore a selected Site when the browser first loads.
        if (!sites.some((site) => String(site.feature_id) === selectedId)) {
            selectedId = '';
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

            const request = ++selectionRequest;
            const resolved = siteDetails.get(selectedId);

            // The catalog deliberately contains no full Scout Report.
            // Never overwrite it with incomplete catalog answers.
            if (!hasCompleteAccess) {
                /*
                 * The public catalog already contains the approved Site facts.
                 * Feed those into public canonical cards only; locked cards are
                 * protected by updateScoutReport() and remain untouched.
                 */
                updateScoutReport(site);
            } else if (resolved) {
                updateScoutReport(resolved);
            } else {
                // Clear the previous Site while its replacement loads.
                updateScoutReport({
                    display_name: site.display_name,
                    report_warnings: {},
                });
                const activeId = selectedId;
                fetch(
                    `/api/place-campsite-detail.php?slug=${encodeURIComponent(slug)}&site=${encodeURIComponent(activeId)}&mode=report`,
                    { cache: 'no-store', credentials: 'same-origin' }
                )
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Unable to load Site details');
                        }
                        return response.json();
                    })
                    .then((payload) => {
                        if (!payload.ok || !payload.site) {
                            throw new Error('Site details unavailable');
                        }
                        siteDetails.set(activeId, payload.site);
                        if (selectionRequest === request && selectedId === activeId) {
                            updateScoutReport(payload.site);
                            const selectedRow = sites.find(
                                (candidate) => String(candidate.feature_id) === activeId
                            );
                            if (selectedRow) {
                                selectedRow.summary = payload.site.summary || selectedRow.summary;
                            }
                        }
                    })
                    .catch((error) => {
                        console.error('Llama Scout Site detail error:', error);
                    });
            }

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
                const track = selectedButton.closest('[data-campsite-list]');
                if (track) {
                    const target = selectedButton.offsetLeft - track.offsetLeft
                        - Math.max(0, (track.clientWidth - selectedButton.offsetWidth) / 2);
                    track.scrollTo({
                        left: Math.max(0, target),
                        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                            ? 'instant' : 'smooth',
                    });
                }
            }

            if (updateUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('site', selectedId);
                window.history.replaceState({}, '', url);
            }

            syncHistoryLink(selectedId);

            window.dispatchEvent(new CustomEvent('llama:explorer-browser-site-selected', {
                detail: { id: selectedId },
            }));
        };

        const clearSiteSelection = () => {
            selectedId = '';
            syncHistoryLink();
            // An old Site request must not overwrite Place answers on completion.
            selectionRequest++;
            const detail = mount.querySelector('[data-campsite-selected-detail]');
            if (detail) detail.replaceChildren();
            mount.querySelectorAll('[data-campsite-id].is-selected').forEach((row) => {
                row.classList.remove('is-selected');
                row.setAttribute('aria-current', 'false');
            });
        };

        window.LlamaScoutCampsiteBrowser = Object.freeze({
            selectSite: (id) => selectSite(id, true),
            clearSelection: clearSiteSelection,
            selectedSite: () => selectedId,
        });

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

                return;
            }

            list.innerHTML =
                visible
                    .map(
                        (site) => {
                            let summary =
                                Array.isArray(
                                    site.summary
                                )
                                    ? site.summary
                                        .map(clean)
                                        .filter(Boolean)
                                    : [];

                            if (!summary.length) {
                                summary = [
                                    siteTypeLabel(site.site_type),
                                    parkingLabel(site.parking_style),
                                    hookupLabel(site.hookup_status),
                                    clean(site.accessible_status).toLowerCase() === 'yes'
                                        ? 'Accessible'
                                        : '',
                                    yesNo(site.electric_hookup) === 'Yes'
                                        ? 'Electric hookup'
                                        : '',
                                ]
                                    .map(clean)
                                    .filter((value) =>
                                        value
                                        && value.toLowerCase() !== 'unknown'
                                    );
                            }

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
                                        ${summary
                                            .slice(
                                                0,
                                                3
                                            )
                                            .map(
                                                (part) =>
                                                    `<span>${escapeHtml(part)}</span>`
                                            )
                                            .join(
                                                '<img width="10" height="10" src="/assets/icons/separator-dot.svg" alt="" aria-hidden="true">'
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

            // Filters only change visible options, never the active report.
            syncSelectionClasses();
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

        syncHistoryLink(selectedId);
        if (selectedId !== '') {
            selectSite(selectedId, false);
        }
        window.dispatchEvent(new CustomEvent('llama:explorer-browser-ready', {
            detail: { selectedSite: selectedId },
        }));
    };

    const showLoading = () => {
        mount.innerHTML = `
            <section class="place-section place-campsites" aria-labelledby="place-campsites-heading" data-place-campsites>
                <div class="place-campsites-heading">
                    <div>
                        <p class="eyebrow">Sites</p>
                        <h2 id="place-campsites-heading">Choose a Site</h2>
                    </div>
                </div>
                <div class="place-campsites-empty" role="status" aria-live="polite">
                    Loading Sites…
                </div>
            </section>
        `;
    };

    const showLoadError = () => {
        mount.innerHTML = `
            <section class="place-section place-campsites" aria-labelledby="place-campsites-heading" data-place-campsites>
                <div class="place-campsites-heading">
                    <div>
                        <p class="eyebrow">Sites</p>
                        <h2 id="place-campsites-heading">Choose a Site</h2>
                    </div>
                </div>
                <div class="place-campsites-empty" role="alert">
                    Sites couldn't be loaded right now.
                    <button type="button" data-campsites-retry>Try again</button>
                </div>
            </section>
        `;
        mount.querySelector('[data-campsites-retry]')?.addEventListener('click', load);
    };

    const load = async () => {
        showLoading();

        try {
            const response =
                await fetch(
                    `/api/place-campsites.php?slug=${encodeURIComponent(slug)}&mode=catalog`,
                    {
                        cache:
                            'no-store',
                        credentials:
                            'same-origin'
                    }
                );

            if (!response.ok) {
                showLoadError();
                return;
            }

            const payload =
                await response.json();

            if (!payload.ok) {
                showLoadError();
                return;
            }

            renderBrowser(payload);

        } catch (error) {
            console.error(
                'Llama Scout Site browser error:',
                error
            );

            showLoadError();
        }
    };

    load();
})();
