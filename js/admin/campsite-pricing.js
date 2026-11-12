(() => {
    'use strict';

    const root = document.getElementById('cpr');
    if (!root) return;

    const $ = id => document.getElementById('cpr-' + id);
    const endpoint = root.dataset.cprEndpoint || '/campsite-pricing.php';
    const state = {
        place: null,
        sites: [],
        seasons: [],
        allSeasons: [],
        seasonVersion: null,
        calendarReady: false,
        paidSites: [],
        reviewReady: false,
        rateGroups: [],
        groupVersion: null,
        groupPreviewSeq: 0,
        placeClosure: null,
        site: null,
        seasonId: null,
        drafts: new Map(),
        baselines: new Map(),
        sitePayload: null,
        saving: false,
        searchTimer: null,
        searchRequest: 0,
        placeRequest: 0,
        siteRequest: 0,
    };
    const status = (text, error = false) => {
        const node = $('save-status');
        node.textContent = text;
        node.dataset.error = error ? '1' : '0';
    };
    const api = async params => {
        const url = new URL(endpoint, window.location.origin);
        for (const [key, value] of Object.entries(params)) url.searchParams.set(key, String(value));
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {Accept: 'application/json'}
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Request failed');
        return data;
    };
    const titleCase = value => String(value || '')
        .replaceAll('_', ' ').replaceAll('-', ' ')
        .replace(/\b\w/g, match => match.toUpperCase());
    const money = value => {
        if (value === '' || value == null || !Number.isFinite(Number(value))) return 'Not set';
        return '$' + Number(value).toFixed(2);
    };
    const cleanAmount = raw => {
        if (raw === '') return '';
        const amount = Number(raw);
        return Number.isFinite(amount) && amount >= 0 ? amount.toFixed(2) : '';
    };
    const dateLabel = value => {
        const text = String(value || '');
        const full = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
        if (full) {
            const date = new Date(Number(full[1]), Number(full[2]) - 1, Number(full[3]));
            return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
        }
        const recurring = /^(\d{2})-(\d{2})$/.exec(text);
        if (recurring) {
            const date = new Date(2000, Number(recurring[1]) - 1, Number(recurring[2]));
            return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
        }
        return 'Dates not set';
    };
    const formatRange = s => {
        const repeat = s.recurring !== false && s.recurring !== 0;
        const start = repeat ? s.start : s.starts_on;
        const end = repeat ? s.end : s.ends_on;
        if (!start || !end) return 'Dates not set';
        return dateLabel(start) + ' – ' + dateLabel(end) + (repeat ? ' · annually' : '');
    };
    const selected = () => state.drafts.get(String(state.seasonId)) || null;
    const updateSegments = (selector, active) => {
        root.querySelectorAll(selector).forEach(button => {
            const on = button.dataset.availability === active
                || button.dataset.capacity === active
                || button.dataset.layout === active
                || button.dataset.utility === active;
            button.classList.toggle('is-selected', on);
            button.setAttribute('aria-pressed', String(on));
        });
    };
    const clearResults = () => {
        $('place-results').replaceChildren();
        $('place-results').hidden = true;
        $('place-search').setAttribute('aria-expanded', 'false');
    };
    const showResults = places => {
        const results = $('place-results');
        results.replaceChildren();
        if (!places.length) {
            const p = document.createElement('p');
            p.textContent = 'No matching Places found.';
            results.append(p);
        }
        for (const place of places) {
            const item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'option');
            const name = document.createElement('strong');
            name.textContent = place.name;
            const detail = document.createElement('small');
            detail.textContent = [place.city, place.state, '#' + place.id].filter(Boolean).join(' · ');
            item.append(name, detail);
            item.addEventListener('click', () => loadPlace(place));
            results.append(item);
        }
        results.hidden = false;
        $('place-search').setAttribute('aria-expanded', 'true');
    };
    const search = async query => {
        const request = ++state.searchRequest;
        try {
            const result = await api({ajax: 'search', q: query});
            if (request !== state.searchRequest) return;
            showResults(result.places);
        } catch (error) {
            if (request === state.searchRequest) {
                clearResults();
                status(error.message, true);
            }
        }
    };

    $('place-search').addEventListener('input', event => {
        const value = event.target.value.trim();
        if (state.place && value !== state.place.name) {
            state.place = null;
            resetSiteSelection();
            state.seasons = [];
            state.allSeasons = [];
            state.seasonVersion = null;
            state.calendarReady = false;
            state.paidSites = [];
            state.reviewReady = false;
            state.rateGroups = [];
            state.groupVersion = null;
            state.placeClosure = null;
        }
        window.clearTimeout(state.searchTimer);
        state.searchTimer = window.setTimeout(() => search(value), 220);
    });
    $('place-search').addEventListener('focus', () => {
        if (state.place && $('place-search').value.trim() === state.place.name) return;
        search($('place-search').value.trim());
    });
    $('place-search').addEventListener('keydown', event => {
        if (event.key === 'Escape') clearResults();
        if (event.key === 'Enter' && !$('place-results').hidden) {
            const first = $('place-results').querySelector('button');
            if (first) { event.preventDefault(); first.click(); }
        }
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.cpr-place-search-wrap')) clearResults();
    });

    const setSiteSummary = () => {
        const site = state.site;
        const place = state.place;
        $('site-title').textContent = site
            ? site.label : (place ? 'Select a Site' : 'Choose a Site');
        $('site-subtitle').textContent = site
            ? [place?.name, titleCase(site.type)].filter(Boolean).join(' · ')
            : (place ? 'Choose a Site above to edit its pricing.' : 'Select a Place and Site above to see its details.');
        $('max-people').textContent = site?.max_people ?? '—';
        $('max-vehicles').textContent = site?.max_vehicles ?? '—';
        $('layout').textContent = site?.layout ? titleCase(site.layout) : '—';
        $('hookups').textContent = site?.electric
            ? titleCase(site.electric)
            : (site?.hookups ? titleCase(site.hookups) : '—');
        $('rates-title').textContent = 'Rates for ' + (site?.label || 'selected Site');
    };
    const normalizedAmount = value => value == null || value === ''
        ? '' : Number(value).toFixed(2);
    const changesForSave = () => {
        const changes = [];
        for (const [id, draft] of state.drafts) {
            const before = state.baselines.get(id);
            if (!before || !/^\d+$/.test(String(id))) continue;
            const change = {season_id: Number(id)};
            if (['weekday', 'weekend', 'holiday'].some(
                key => normalizedAmount(draft[key]) !== normalizedAmount(before[key])
            )) {
                change.rates = {
                    weekday: draft.weekday,
                    weekend: draft.weekend,
                    holiday: draft.holiday,
                };
            }
            if (draft.availability !== before.availability
                && draft.availability !== '') {
                change.availability = draft.availability;
            }
            if (Object.keys(change).length > 1) changes.push(change);
        }
        return changes;
    };
    const syncSaveButton = () => {
        $('save').disabled = state.saving || !state.sitePayload?.version
            || Boolean(state.sitePayload.rate_conflict)
            || changesForSave().length === 0;
    };
    const setEditorEnabled = enabled => {
        $('status-field').disabled = !enabled || state.saving;
        ['start','end'].forEach(id => {
            $(id).disabled = !enabled;
            $(id).readOnly = true;
            $(id).title = 'Use Edit Season above to change the season dates here in Campsite Pricing.';
        });
        root.querySelectorAll('[data-cpr-rate]').forEach(input => {
            const supported = ['weekday', 'weekend', 'holiday'].includes(input.dataset.cprRate);
            input.disabled = !enabled || !supported || state.saving;
            if (!supported) {
                input.title = 'Surcharge pricing will be connected in a later step.';
            } else {
                input.removeAttribute('title');
            }
        });
        root.querySelectorAll('[data-capacity], [data-layout], [data-utility]')
            .forEach(button => {
                button.disabled = true;
                button.title = 'Site facts are read from the existing Site record.';
            });
        $('copy-season').disabled = !enabled || state.saving || state.seasons.length < 2;
        $('edit-season').disabled = !state.place || !state.seasonId || state.saving;
        $('add-season').disabled = !state.place || state.saving;
        $('manage-seasons').disabled = !state.place || !state.allSeasons.length || state.saving;
        $('manage-holidays').disabled = !state.sitePayload?.version || state.saving;
        syncSaveButton();
    };
    const resetSiteSelection = () => {
        state.site = null;
        state.sitePayload = null;
        state.drafts = new Map();
        state.baselines = new Map();
        state.seasonId = null;
        $('site').replaceChildren();
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Select a Place first';
        $('site').append(option);
        $('site').disabled = true;
        $('add-season').disabled = true;
        $('apply-sites').disabled = true;
        state.paidSites = [];
        state.rateGroups = [];
        state.groupVersion = null;
        renderReviewQueue();
        renderRateGroups();
        setEditorEnabled(false);
        setSiteSummary();
        renderSeasons();
        renderEditor();
    };
    const loadPlace = async item => {
        clearResults();
        ++state.searchRequest;
        const request = ++state.placeRequest;
        ++state.siteRequest;
        $('place-search').value = item.name;
        resetSiteSelection();
        status('Loading Sites and seasons for ' + item.name + '...');
        try {
            const data = await api({ajax: 'place', place_id: item.id});
            if (request !== state.placeRequest) return;
            state.place = data.place;
            state.sites = data.sites || [];
            state.seasons = data.seasons || [];
            state.allSeasons = data.all_seasons || [];
            state.seasonVersion = data.season_version || null;
            state.calendarReady = Boolean(data.calendar_ready);
            state.paidSites = data.paid_sites || [];
            state.reviewReady = Boolean(data.review_queue_ready);
            state.rateGroups = data.rate_groups?.groups || [];
            state.groupVersion = data.rate_groups?.version || null;
            state.placeClosure = data.place_closure || null;
            renderRateGroups();
            renderReviewQueue();
            state.site = null;
            state.drafts = new Map();
            state.seasonId = null;
             const select = $('site');
            select.replaceChildren();
            const option = document.createElement('option');
            option.value = '';
            option.textContent = state.sites.length ? 'Choose a Site' : 'No Sites attached to this Place';
            select.append(option);
            for (const site of state.sites) {
                const row = document.createElement('option');
                row.value = String(site.id);
                row.textContent = site.label;
                select.append(row);
            }
            select.disabled = !state.sites.length;
            $('add-season').disabled = false;
            $('manage-seasons').disabled = !state.allSeasons.length;
            $('apply-sites').disabled = false;
            setSiteSummary();
            renderSeasons();
            renderEditor();
            status(state.sites.length
                ? 'Select a Site to load its actual pricing and seasons.'
                : 'This Place has no Sites. Add a Site from its Admin Place page.');
            const url = new URL(window.location.href);
            url.searchParams.set('place_id', data.place.id);
            url.searchParams.delete('site_id');
            window.history.replaceState(null, '', url);
        } catch (error) {
            if (request !== state.placeRequest) return;
            resetSiteSelection();
            status(error.message, true);
        }
    };

    const loadSelectedSite = async () => {
        const id = Number($('site').value);
        ++state.siteRequest;
        if (!id || !state.place) {
            state.site = null;
            state.sitePayload = null;
            state.drafts = new Map();
            state.baselines = new Map();
            state.seasonId = null;
            setSiteSummary();
            renderEditor();
            renderSeasons();
            return;
        }
        state.site = state.sites.find(s => Number(s.id) === id) || null;
        setSiteSummary();
        const request = state.siteRequest;
        state.sitePayload = null;
        state.drafts = new Map();
        state.baselines = new Map();
        state.seasonId = null;
        renderEditor();
        status('Loading pricing for ' + state.site.label + '...');
        try {
            const data = await api({ajax: 'site', place_id: state.place.id, site_id: id});
            if (request !== state.siteRequest) return;
            state.sitePayload = data;
            state.drafts = new Map();
            state.baselines = new Map();
            for (const season of state.seasons) {
                const effectiveRates = data.rates_by_season?.[String(season.id)] || {};
                const values = {
                    weekday: effectiveRates.weekday ?? '',
                    weekend: effectiveRates.weekend ?? '',
                    holiday: effectiveRates.holiday ?? '',
                    vehicle: '',
                    group: '',
                    availability: data.availability?.[String(season.id)] || '',
                    start: season.start,
                    end: season.end,
                    changed: false,
                };
                state.drafts.set(String(season.id), values);
                state.baselines.set(String(season.id), {...values});
            }
            state.seasonId = state.seasons[0]?.id ?? null;
            updateSegments('[data-capacity]', state.site?.type === 'group_site' ? 'group' : '');
            updateSegments('[data-layout]', state.site?.layout || '');
            updateSegments('[data-utility]', state.site?.hookups === 'full' ? 'full'
                : (state.site?.hookups?.includes('electric') ? 'electric' : ''));
            renderSeasons();
            renderEditor();
            status(data.rate_conflict
                ? 'Conflicting Site pricing rows require review before saving.'
                : (data.rate
                    ? 'Existing Site pricing loaded. Weekday, weekend, holiday and seasonal availability can be saved.'
                    : 'No Site prices yet. Set prices here and save them for this Site.'),
                Boolean(data.rate_conflict));
            const url = new URL(window.location.href);
            url.searchParams.set('site_id', id);
            window.history.replaceState(null, '', url);
        } catch (error) {
            if (request === state.siteRequest) {
                state.sitePayload = null;
                state.seasonId = null;
                state.drafts = new Map();
                state.baselines = new Map();
                renderEditor();
                status(error.message, true);
            }
        }
    };
    $('site').addEventListener('change', loadSelectedSite);

    const makeSeasonChoice = season => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cpr-season-choice';
        if (String(season.id) === String(state.seasonId)) button.classList.add('is-selected');
        button.setAttribute('aria-pressed', String(String(season.id) === String(state.seasonId)));
        const icon = document.createElement('span');
        icon.textContent = String(season.name).toLowerCase().includes('winter') ||
            String(season.name).toLowerCase().includes('off') ? '❄' : '☼';
        icon.setAttribute('aria-hidden', 'true');
        const words = document.createElement('span');
        const name = document.createElement('strong');
        name.textContent = season.name;
        const dates = document.createElement('small');
        dates.textContent = formatRange(season);
        words.append(name, dates);
        const arrow = document.createElement('span');
        arrow.textContent = '›';
        arrow.setAttribute('aria-hidden', 'true');
        button.append(icon, words, arrow);
        button.addEventListener('click', () => {
            state.seasonId = season.id;
            renderSeasons();
            renderEditor();
        });
        return button;
    };
    const renderSeasons = () => {
        const list = $('seasons-list');
        list.replaceChildren();
        if (!state.place) {
            const message = document.createElement('p');
            message.className = 'cpr-muted';
            message.textContent = 'Select a Place to load its seasons.';
            list.append(message);
            return;
        }
        if (!state.seasons.length) {
            const message = document.createElement('p');
            message.className = 'cpr-muted';
            message.textContent = 'No seasons yet. Select Add Season above to create the first one here.';
            list.append(message);
            return;
        }
        state.seasons.forEach(season => list.append(makeSeasonChoice(season)));
    };
    const renderEditor = () => {
        const season = state.seasons.find(s => String(s.id) === String(state.seasonId));
        const draft = selected();
        const enabled = Boolean(state.site && season && draft);
        setEditorEnabled(enabled);
        $('season-title').textContent = enabled ? season.name : (state.site ? 'Choose a Season' : 'Select a Site');
        $('start').value = enabled ? draft.start || '' : '';
        $('end').value = enabled ? draft.end || '' : '';
        ['weekday', 'weekend', 'holiday', 'vehicle', 'group'].forEach(key => {
            const input = $('rate-' + key);
            input.value = enabled && draft[key] !== '' && draft[key] != null
                ? Number(draft[key]).toFixed(2) : '';
        });
        updateSegments('[data-availability]', enabled ? draft.availability : '');
        renderPreview();
    };
    const renderPreview = () => {
        const season = state.seasons.find(s => String(s.id) === String(state.seasonId));
        const draft = selected();
        if (!state.site || !season || !draft) {
            $('preview').textContent = 'Select a Place, Site and season to preview rates.';
            return;
        }
        const availability = draft.availability ? titleCase(draft.availability) : 'Status not set';
        $('preview').textContent = season.name + ': ' + availability +
            '  ·  Weekday ' + money(draft.weekday) +
            '  ·  Weekend ' + money(draft.weekend) +
            '  ·  Holiday ' + money(draft.holiday);
        if (draft.holiday !== '' && !state.sitePayload?.holidays?.length) {
            $('preview').textContent += ' · No holiday dates configured for this Place';
        }
        if (state.placeClosure?.reported) {
            const begins = state.placeClosure.begins;
            const ends = state.placeClosure.ends;
            $('preview').textContent += begins && ends
                ? ' · Place closure is reported from ' + begins + ' through ' + ends
                  + ' and overrides Site availability on those dates'
                : ' · Place has a reported seasonal closure; closure dates are not provided';
        }
    };

    root.querySelectorAll('[data-cpr-rate]').forEach(input => {
        input.addEventListener('input', event => {
            const draft = selected();
            if (!draft) return;
            const key = event.target.dataset.cprRate;
            draft[key] = event.target.value;
            draft.changed = true;
            renderPreview();
            status('Unsaved pricing changes.');
            syncSaveButton();
        });
        input.addEventListener('blur', event => {
            if (event.target.value !== '') {
                event.target.value = cleanAmount(event.target.value);
                const draft = selected();
                if (draft) draft[event.target.dataset.cprRate] = event.target.value;
            }
            renderPreview();
            syncSaveButton();
        });
    });
    root.querySelectorAll('[data-availability]').forEach(button => {
        button.addEventListener('click', () => {
            const draft = selected();
            if (!draft) return;
            draft.availability = button.dataset.availability;
            draft.changed = true;
            updateSegments('[data-availability]', draft.availability);
            renderPreview();
            status('Unsaved seasonal availability changes.');
            syncSaveButton();
        });
    });
    // The Site fact pills reflect existing records and do not write pricing
    // selectors in this pass. Place-wide season dates stay in Place Admin.
    // Edit Place season definitions without leaving the pricing workspace.
    const seasonDialog = $('season-dialog');
    const seasonForm = $('season-form');
    const seasonChoice = $('season-choose');
    const seasonNotice = text => { $('season-status').textContent = text; };

    const setSeasonDateMode = (clear = false) => {
        const repeating = $('season-repeat').checked;
        $('season-start-label').textContent = repeating
            ? 'Starts (MM-DD)' : 'Starts (year included)';
        $('season-end-label').textContent = repeating
            ? 'Ends (MM-DD)' : 'Ends (year included)';
        for (const id of ['season-start', 'season-end']) {
            const el = $(id);
            const old = el.value;
            el.type = repeating ? 'text' : 'date';
            el.maxLength = repeating ? 5 : 10;
            el.placeholder = repeating ? '05-21' : 'YYYY-MM-DD';
            if (clear) el.value = '';
            else el.value = old;
        }
    };
    $('season-repeat').addEventListener('change', () => setSeasonDateMode(true));
    const fillSeasonForm = id => {
        const data = state.allSeasons.find(s => String(s.id) === String(id));
        seasonChoice.value = data ? String(data.id) : '';
        $('season-dialog-title').textContent = data ? 'Edit Season' : 'Add Season';
        $('season-name').value = data?.name || '';
        $('season-repeat').checked = data ? Boolean(data.recurring) : false;
        setSeasonDateMode(true);
        $('season-start').value = data ? (data.recurring ? data.start : data.starts_on) || '' : '';
        $('season-end').value = data ? (data.recurring ? data.end : data.ends_on) || '' : '';
        $('season-active').checked = data ? Boolean(data.active) : true;
        $('season-default').checked = data
            ? Boolean(data.default) : state.seasons.length === 0;
        seasonNotice(state.calendarReady
            ? 'Use full calendar dates for published seasons, or Repeat annually for year-independent dates.'
            : 'Year-specific seasons require the new SQL migration. Recurring MM-DD seasons can still be edited.');
    };
    const openSeasonDialog = (id = '') => {
        if (!state.place || !state.seasonVersion || state.saving) return;
        if (changesForSave().length) {
            status('Save or cancel your current Site prices before changing the Place seasons.', true);
            return;
        }
        seasonChoice.replaceChildren();
        const fresh = document.createElement('option');
        fresh.value = '';
        fresh.textContent = '+ New season';
        seasonChoice.append(fresh);
        for (const season of state.allSeasons) {
            const option = document.createElement('option');
            option.value = String(season.id);
            option.textContent = season.name + (season.active ? '' : ' (inactive)');
            seasonChoice.append(option);
        }
        fillSeasonForm(id);
        seasonDialog.showModal();
    };
    $('add-season').addEventListener('click', () => openSeasonDialog());
    $('edit-season').addEventListener('click', () => openSeasonDialog(state.seasonId));
    $('manage-seasons').addEventListener('click', () => {
        openSeasonDialog(state.seasonId || state.allSeasons[0]?.id || '');
    });
    seasonChoice.addEventListener('change', () => fillSeasonForm(seasonChoice.value));
    $('season-cancel').addEventListener('click', () => seasonDialog.close());
    seasonForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!state.place || !state.seasonVersion || state.saving) return;
        const name = $('season-name').value.trim();
        const start = $('season-start').value.trim();
        const end = $('season-end').value.trim();
        const repeating = $('season-repeat').checked;
        const validDate = value => {
            if (repeating) {
                if (!/^\d{2}-\d{2}$/.test(value)) return false;
                const [month, day] = value.split('-').map(Number);
                const date = new Date(Date.UTC(2000, month - 1, day));
                return date.getUTCMonth() + 1 === month && date.getUTCDate() === day;
            }
            if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
            const [year, month, day] = value.split('-').map(Number);
            const date = new Date(Date.UTC(year, month - 1, day));
            return date.getUTCFullYear() === year
                && date.getUTCMonth() + 1 === month && date.getUTCDate() === day;
        };
        if (!name || name.length > 100) {
            seasonNotice('Enter a season name.');
            return;
        }
        if ((!repeating && (!start || !end || end < start))
            || (start === '') !== (end === '')
            || (start !== '' && (!validDate(start) || !validDate(end)))) {
            seasonNotice(repeating
                ? 'Use valid MM-DD dates for an annually repeating season.'
                : 'Enter valid start/end dates including the year, with end after start.');
            return;
        }
        if (!repeating && !state.calendarReady) {
            seasonNotice('Apply the year-specific season SQL migration before saving these dates.');
            return;
        }
        const previous = state.allSeasons.find(
            season => String(season.id) === seasonChoice.value
        );
        const season = {
            id: previous?.id || 0, name, start, end,
            recurring: repeating,
            active: $('season-active').checked,
            default: $('season-default').checked,
            sort_order: previous?.sort_order
                ?? ((Math.max(0, ...state.allSeasons.map(s => Number(s.sort_order) || 0))) + 10),
            notes: previous?.notes || '',
            source_url: previous?.source_url || '',
        };
        const placeBefore = {...state.place};
        const selectedSiteId = state.site?.id || 0;
        state.saving = true;
        $('season-save').disabled = true;
        setEditorEnabled(Boolean(selected()));
        seasonNotice('Saving season...');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify({
                    action: 'save-season',
                    csrf_token: $('csrf').value,
                    place_id: placeBefore.id,
                    season_version: state.seasonVersion,
                    season,
                }),
            });
            const result = await response.json();
            if (!response.ok || result.success !== true) {
                throw new Error(result.message || 'Season was not saved.');
            }
            seasonDialog.close();
            await loadPlace(placeBefore);
            if (!state.place || Number(state.place.id) !== Number(placeBefore.id)) {
                throw new Error('Season saved, but Place data could not be reloaded.');
            }
            if (selectedSiteId && state.sites.some(x => Number(x.id) === Number(selectedSiteId))) {
                $('site').value = String(selectedSiteId);
                await loadSelectedSite();
                if (!state.sitePayload?.version) {
                    throw new Error('Season saved. Site pricing could not reload; refresh the page.');
                }
            }
            state.seasonId = state.seasons.find(x =>
                Number(x.id) === Number(result.season_id)
            )?.id ?? state.seasonId;
            renderSeasons();
            renderEditor();
            status('Season saved. Pricing and availability can now be edited here.');
        } catch (error) {
            seasonNotice('Unable to finish: ' + String(error.message || error));
            status('Season save needs attention: ' + String(error.message || error), true);
        } finally {
            state.saving = false;
            $('season-save').disabled = false;
            setEditorEnabled(Boolean(selected()));
        }
    });

    $('copy-season').addEventListener('click', () => {
        const draft = selected();
        if (!draft || state.seasons.length < 2) return;
        const choices = state.seasons.filter(s => String(s.id) !== String(state.seasonId));
        const raw = window.prompt('Copy displayed rates into which season?\n' +
            choices.map((s, i) => (i + 1) + '. ' + s.name).join('\n'));
        const chosen = choices[Number(raw) - 1];
        if (!chosen) return;
        state.drafts.set(String(chosen.id), {
            ...state.drafts.get(String(chosen.id)),
            weekday: draft.weekday, weekend: draft.weekend, holiday: draft.holiday,
            vehicle: draft.vehicle, group: draft.group,
            changed: true,
        });
        state.seasonId = chosen.id;
        renderSeasons();
        renderEditor();
        status('Copied rates are ready to save to the selected Site.');
        syncSaveButton();
    });
    $('cancel').addEventListener('click', () => {
        if (!state.site || !state.place) {
            $('place-search').value = '';
            state.place = null;
            resetSiteSelection();
            status('No pending changes.');
            return;
        }
        loadSelectedSite().then(() => status('Unsaved edits discarded; existing Site pricing reloaded.'));
    });
    // Reusable rate profiles. A Site matches these only if the imported
    // capacity/utility/parking facts are known. A Site-specific exception wins.
    const groupForm = $('group-form');
    const groupInput = id => $('group-' + id);
    let groupMatchCount = 0;
    const groupKey = () => 'cpr-group-' + groupInput('category').value
        + '-' + (groupInput('hookups').value || 'any')
        + '-' + (groupInput('layout').value || 'any');
    const currentGroup = () => (state.rateGroups || []).find(
        group => group.id === groupKey()
    );
    const groupSelectedSeason = () => Number(groupInput('season').value || 0);
    const fillGroupRates = () => {
        const group = currentGroup();
        const period = group?.seasons?.find(
            s => Number(s.season_id) === groupSelectedSeason()
        );
        for (const [key, source] of [
            ['weekday', 'price'], ['weekend', 'weekend_price'], ['holiday', 'holiday_price'],
        ]) {
            const value = period?.[source];
            groupInput(key).value = value === null || value === undefined
                ? '' : Number(value).toFixed(2);
        }
    };
    const renderRateGroups = () => {
        const list = $('group-list');
        list.replaceChildren();
        const seasonSelect = groupInput('season');
        const previousSeason = seasonSelect.value;
        seasonSelect.replaceChildren();
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Select season';
        seasonSelect.append(empty);
        for (const season of state.seasons || []) {
            const opt = document.createElement('option');
            opt.value = String(season.id);
            opt.textContent = season.name + ': ' + formatRange(season);
            seasonSelect.append(opt);
        }
        seasonSelect.value = state.seasons.some(
            s => String(s.id) === previousSeason
        ) ? previousSeason : String(state.seasons[0]?.id || '');
        $('group-summary').textContent = state.place
            ? (state.rateGroups.length + ' shared rate group'
                + (state.rateGroups.length === 1 ? '' : 's')
                + ' · ' + state.sites.length + ' Sites')
            : 'Select a Place';
        for (const group of state.rateGroups || []) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'cpr-group-chip';
            button.textContent = group.name + ' (' + group.matched_sites + ' Sites)';
            button.addEventListener('click', () => {
                groupInput('category').value = group.category || 'single';
                groupInput('hookups').value = group.hookups || '';
                groupInput('layout').value = group.layout || '';
                fillGroupRates();
                refreshGroupPreview();
            });
            list.append(button);
        }
        if (!state.rateGroups?.length) {
            const p = document.createElement('p');
            p.className = 'cpr-muted';
            p.textContent = 'No matching rate groups yet. Create one above to price several Sites together.';
            list.append(p);
        }
        fillGroupRates();
        refreshGroupPreview();
    };
    const refreshGroupPreview = async () => {
        const request = ++state.groupPreviewSeq;
        groupMatchCount = 0;
        groupInput('save').disabled = true;
        if (!state.place || !groupSelectedSeason()) {
            $('group-preview').textContent =
                'Choose a Place and operating season to preview matching Sites.';
            return;
        }
        $('group-preview').textContent = 'Checking matching Site attributes...';
        try {
            const result = await api({
                ajax: 'group-preview',
                place_id: state.place.id,
                category: groupInput('category').value,
                hookups: groupInput('hookups').value,
                layout: groupInput('layout').value,
            });
            if (request !== state.groupPreviewSeq) return;
            groupMatchCount = Number(result.matched_count) || 0;
            const uncertain = Number(result.uncertain_count) || 0;
            $('group-preview').textContent = groupMatchCount + ' Sites match'
                + (uncertain ? '; ' + uncertain
                    + ' Sites have missing attributes and will not be changed.' : '.');
            groupInput('save').disabled = !groupMatchCount || !state.groupVersion;
        } catch (error) {
            if (request !== state.groupPreviewSeq) return;
            $('group-preview').textContent = error.message;
        }
    };
    for (const key of ['category', 'hookups', 'layout', 'season']) {
        groupInput(key).addEventListener('change', () => {
            fillGroupRates();
            refreshGroupPreview();
        });
    }
    groupInput('apply-status').addEventListener('change', () => {
        groupInput('status').disabled = !groupInput('apply-status').checked;
    });
    $('apply-sites').addEventListener('click', () => {
        $('group-section').scrollIntoView({behavior: 'smooth', block: 'start'});
        status('Use a matching rate group below instead of copying prices into individual Site records.');
    });
    groupForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!state.place || !state.groupVersion || !groupMatchCount || !groupSelectedSeason()) return;
        const raw = {};
        for (const key of ['weekday', 'weekend', 'holiday']) {
            const value = groupInput(key).value.trim();
            if (value && (!Number.isFinite(Number(value)) || Number(value) < 0
                || Number(value) > 999999.99)) {
                $('group-preview').textContent = 'Enter a valid ' + key + ' rate.';
                return;
            }
            raw[key] = value;
        }
        if (Object.values(raw).every(value => value === '')) {
            $('group-preview').textContent = 'Enter at least one rate. Enter 0 for a free rate.';
            return;
        }
        if (changesForSave().length) {
            status('Save or cancel your individual Site changes before saving a shared rate group.', true);
            return;
        }
        const place = {...state.place};
        const previousSiteId = state.site?.id || 0;
        const applyAvailability = groupInput('apply-status').checked;
        if (applyAvailability && !window.confirm(
            'Set ' + groupInput('status').selectedOptions[0]?.textContent
            + ' for ' + groupMatchCount
            + ' matching Sites in this season? Existing individual statuses will be overwritten.'
        )) return;
        groupInput('save').disabled = true;
        $('group-preview').textContent = 'Saving matched rate group...';
        try {
            const response = await fetch(endpoint, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify({
                    action: 'save-group', csrf_token: $('csrf').value,
                    place_id: place.id, group_version: state.groupVersion,
                    group: {
                        category: groupInput('category').value,
                        hookups: groupInput('hookups').value,
                        layout: groupInput('layout').value,
                        season_id: groupSelectedSeason(),
                        rates: raw,
                        apply_availability: applyAvailability,
                        availability: applyAvailability ? groupInput('status').value : '',
                    },
                }),
            });
            const result = await response.json();
            if (!response.ok || result.success !== true) {
                throw new Error(result.message || 'The rate group was not saved.');
            }
            await loadPlace(place);
            if (previousSiteId && state.sites.some(s => Number(s.id) === Number(previousSiteId))) {
                $('site').value = String(previousSiteId);
                await loadSelectedSite();
            }
            $('group-preview').textContent = 'Rate group saved.'
                + (Number(result.sites_status_updated)
                    ? ' Updated ' + result.sites_status_updated + ' Site statuses.' : '');
            status('Shared rate saved. Matching Sites now use this rate unless they have an explicit Site override.');
        } catch (error) {
            $('group-preview').textContent = 'Not saved: ' + String(error.message || error);
            groupInput('save').disabled = false;
        }
    });

    // One place-level paid Site queue, showing both published closure alarms
    // and pricing/booking review reminders without creating false closures.
    const reviewDialog = $('review-dialog');
    const reviewForm = $('review-form');
    let editingReviewSite = null;
    const localToday = () => {
        const today = new Date();
        return today.getFullYear() + '-'
            + String(today.getMonth() + 1).padStart(2, '0') + '-'
            + String(today.getDate()).padStart(2, '0');
    };
    const textCell = (row, textValue) => {
        const cell = document.createElement('td');
        cell.textContent = String(textValue ?? '');
        row.append(cell);
        return cell;
    };
    const renderReviewQueue = () => {
        const tbody = $('review-rows');
        tbody.replaceChildren();
        if (!state.place) {
            const row = tbody.insertRow();
            textCell(row, 'Choose a Place to see its paid Sites.').colSpan = 6;
            $('review-count').textContent = 'Choose a Place to load paid Sites.';
            return;
        }
        const today = localToday();
        const mode = $('review-filter').value;
        const show = (state.paidSites || []).filter(site => {
            const next = site.next_check_on || '';
            if (mode === 'due') return next !== '' && next <= today;
            if (mode === 'upcoming') return next !== '' && next > today;
            if (mode === 'unscheduled') return next === '';
            return true;
        });
        $('review-count').textContent = show.length + ' of '
            + (state.paidSites?.length || 0) + ' paid Sites shown';
        $('review-footnote').textContent = state.reviewReady
            ? 'A closure alarm is included when present. Scheduling a pricing review does not open or close a Site.'
            : 'Review dates need the new database migration. Rates and season dates can still be inspected.';
        if (!show.length) {
            const row = tbody.insertRow();
            textCell(row, 'No paid Sites match this filter.').colSpan = 6;
            return;
        }
        for (const site of show) {
            const row = tbody.insertRow();
            textCell(row, site.label);
            const seasons = (site.season_summary || []).filter(Boolean);
            textCell(row, seasons.length ? seasons.join(' · ') : 'No operating season');
            textCell(row, money(site.price_min)
                + (Number(site.price_max) > Number(site.price_min)
                    ? ' to ' + money(site.price_max) : ''));
            const review = textCell(row, site.next_check_on || 'Not scheduled');
            if (site.closure_review_on) review.title =
                'Includes a verified closure alarm for ' + site.closure_review_on;
            const release = String(site.release_on || '');
            textCell(row, release ? release.replace(' ', ' at ').slice(0, 22) : 'Not published');
            const actions = row.insertCell();
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'cpr-ghost';
            button.textContent = 'Schedule';
            button.disabled = !state.reviewReady;
            button.addEventListener('click', () => openReviewDialog(site.id));
            actions.append(button);
            if (site.next_check_on && site.next_check_on <= today) row.classList.add('is-due');
        }
    };
    $('review-filter').addEventListener('change', renderReviewQueue);
    const openReviewDialog = id => {
        if (!state.reviewReady || !state.place || state.saving) return;
        if (changesForSave().length) {
            status('Save or cancel your unsaved Site prices before scheduling a review.', true);
            return;
        }
        editingReviewSite = state.paidSites.find(x => Number(x.id) === Number(id));
        if (!editingReviewSite) return;
        $('review-dialog-site').textContent = editingReviewSite.label;
        $('review-on').value = editingReviewSite.review_on || '';
        $('review-release').value = (editingReviewSite.release_on || '')
            .replace(' ', 'T').slice(0, 16);
        $('review-source').value = editingReviewSite.source_url || '';
        $('review-checked').checked = false;
        $('review-dialog-status').textContent = '';
        reviewDialog.showModal();
    };
    $('review-cancel').addEventListener('click', () => reviewDialog.close());
    reviewForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!editingReviewSite || !state.place || state.saving) return;
        const place = {...state.place};
        const siteId = state.site?.id || 0;
        $('review-save').disabled = true;
        $('review-dialog-status').textContent = 'Saving review date...';
        try {
            const response = await fetch(endpoint, {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify({
                    action: 'save-review', csrf_token: $('csrf').value,
                    place_id: place.id, site_id: editingReviewSite.id,
                    review: {
                        next_review_on: $('review-on').value,
                        availability_release_on: $('review-release').value,
                        source_url: $('review-source').value.trim(),
                        checked_today: $('review-checked').checked,
                    },
                }),
            });
            const result = await response.json();
            if (!response.ok || result.success !== true) {
                throw new Error(result.message || 'Unable to save review.');
            }
            reviewDialog.close();
            await loadPlace(place);
            if (siteId && state.sites.some(x => Number(x.id) === Number(siteId))) {
                $('site').value = String(siteId);
                await loadSelectedSite();
            }
            status('Review reminder saved. The Site status and prices are unchanged.');
        } catch (error) {
            $('review-dialog-status').textContent =
                'Not saved: ' + String(error.message || error);
        } finally {
            $('review-save').disabled = false;
        }
    });

    // One Place-wide holiday calendar; each Site's holiday amount remains
    // independent. This is the sole holiday date editing workflow.
    const holidayDialog = $('holiday-dialog');
    const holidayRows = $('holiday-rows');
    const holidayStatus = message => {
        $('holiday-status').textContent = message;
    };
    const addHolidayRow = (item = {}) => {
        const row = document.createElement('div');
        row.className = 'cpr-holiday-row';
        row.dataset.holidayId = String(item.id || '');

        const fields = [
            ['Name', 'name', 'Thanksgiving', String(item.name || '')],
            ['Start', 'start', item.recurring ? 'MM-DD' : 'YYYY-MM-DD', String(item.start || '')],
            ['End', 'end', item.recurring ? 'MM-DD' : 'YYYY-MM-DD', String(item.end || '')],
        ];
        for (const [label, key, placeholder, value] of fields) {
            const container = document.createElement('label');
            const title = document.createElement('span');
            title.textContent = label;
            const input = document.createElement('input');
            input.type = 'text';
            input.dataset.holidayField = key;
            input.maxLength = key === 'name' ? 100 : 10;
            input.placeholder = placeholder;
            input.value = value;
            container.append(title, input);
            row.append(container);
        }
        const recurring = document.createElement('label');
        recurring.className = 'cpr-holiday-recurring';
        const check = document.createElement('input');
        check.type = 'checkbox';
        check.dataset.holidayField = 'recurring';
        check.checked = Boolean(item.recurring);
        check.addEventListener('change', () => {
            row.querySelector('[data-holiday-field="start"]').placeholder =
                check.checked ? 'MM-DD' : 'YYYY-MM-DD';
            row.querySelector('[data-holiday-field="end"]').placeholder =
                check.checked ? 'MM-DD' : 'YYYY-MM-DD';
        });
        const checkLabel = document.createElement('span');
        checkLabel.textContent = 'Repeats yearly';
        recurring.append(check, checkLabel);
        row.append(recurring);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'cpr-ghost';
        remove.textContent = 'Remove';
        remove.setAttribute('aria-label', 'Remove holiday dates');
        remove.addEventListener('click', () => row.remove());
        row.append(remove);
        holidayRows.append(row);
        return row;
    };

    $('manage-holidays').addEventListener('click', () => {
        if (!state.place || !state.sitePayload?.version || state.saving) return;
        if (changesForSave().length) {
            status('Save or cancel your Site pricing changes before editing Place holiday dates.', true);
            return;
        }
        holidayRows.replaceChildren();
        for (const item of state.sitePayload.holidays || []) addHolidayRow(item);
        if (!holidayRows.childElementCount) addHolidayRow();
        holidayStatus('Use YYYY-MM-DD for specific dates, or MM-DD with Repeats yearly.');
        holidayDialog.showModal();
    });
    $('holiday-add').addEventListener('click', () => addHolidayRow());
    $('holiday-cancel').addEventListener('click', () => holidayDialog.close());
    $('holiday-save').addEventListener('click', async () => {
        if (!state.place || !state.site || !state.sitePayload?.version || state.saving) return;
        const holidays = [];
        for (const row of holidayRows.querySelectorAll('.cpr-holiday-row')) {
            const field = name => row.querySelector('[data-holiday-field="' + name + '"]');
            const name = field('name').value.trim();
            const start = field('start').value.trim();
            const end = field('end').value.trim();
            const recurring = field('recurring').checked;
            const format = recurring ? /^\d{2}-\d{2}$/ : /^\d{4}-\d{2}-\d{2}$/;
            if (!name || !format.test(start) || !format.test(end)) {
                holidayStatus('Enter each holiday name and valid start/end dates.');
                return;
            }
            holidays.push({
                id: row.dataset.holidayId || '',
                name, start, end, recurring,
            });
        }
        const saveButton = $('holiday-save');
        saveButton.disabled = true;
        holidayStatus('Saving holiday dates to this Place...');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify({
                    action: 'save-holidays',
                    csrf_token: $('csrf').value,
                    place_id: state.place.id,
                    site_id: state.site.id,
                    version: state.sitePayload.version,
                    holidays,
                }),
            });
            const data = await response.json();
            if (!response.ok || data.success !== true) {
                throw new Error(data.message || 'Holiday dates were not saved.');
            }
            await loadSelectedSite();
            holidayDialog.close();
            status('Saved ' + Number(data.holidays_saved) + ' holiday date period(s) for this Place.');
        } catch (error) {
            holidayStatus('Not saved: ' + String(error.message || 'Unexpected error.'));
        } finally {
            saveButton.disabled = false;
        }
    });

    $('save').addEventListener('click', async () => {
        const changes = changesForSave();
        if (!state.place || !state.site || !state.sitePayload?.version
            || !changes.length || state.saving) return;
        for (const change of changes) {
            if (!change.rates) continue;
            for (const [key, value] of Object.entries(change.rates)) {
                if (value === '') continue;
                if (!Number.isFinite(Number(value)) || Number(value) < 0
                    || Number(value) > 999999.99) {
                    status('Enter a valid ' + key + ' rate between $0 and $999,999.99.', true);
                    return;
                }
            }
        }
        state.saving = true;
        $('place-search').disabled = true;
        $('site').disabled = true;
        syncSaveButton();
        setEditorEnabled(Boolean(selected()));
        status('Saving ' + changes.length + ' season' +
            (changes.length === 1 ? '' : 's') + ' for ' + state.site.label + '...');
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save',
                    csrf_token: $('csrf').value,
                    place_id: state.place.id,
                    site_id: state.site.id,
                    version: state.sitePayload.version,
                    changes,
                })
            });
            const data = await response.json();
            if (!response.ok || data.success !== true) {
                throw new Error(data.message || 'Pricing save was not confirmed.');
            }
            await loadSelectedSite();
            if (!state.sitePayload?.version) {
                throw new Error('The save completed, but the updated pricing could not be reloaded. Refresh before changing it again.');
            }
            status('Saved ' + Number(data.seasons_saved || 0) +
                ' season(s) for ' + state.site.label + '.');
        } catch (error) {
            status('Not saved: ' + String(error.message || 'Unknown error.'), true);
        } finally {
            state.saving = false;
            $('place-search').disabled = false;
            $('site').disabled = !state.sites.length;
            setEditorEnabled(Boolean(selected()));
        }
    });
    root.querySelectorAll('[data-cpr-view]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.dataset.cprView === 'pricing') return;
            if (!state.place || !state.site) {
                status('Choose a Place and Site first.');
                return;
            }
            const fragment = button.dataset.cprView === 'photos' ? '#photos' : '#place-report';
            const url = '/place.php?id=' + encodeURIComponent(state.place.id) +
                '&report_scope=site&report_source=campsite_record&report_target=' +
                encodeURIComponent(state.site.id) + fragment;
            window.location.href = url;
        });
    });
    // Existing Admin Place links can open the new pricing editor preselected.
    const params = new URLSearchParams(window.location.search);
    const placeId = Number(params.get('place_id') || 0);
    const siteId = Number(params.get('site_id') || 0);
    if (placeId > 0) {
        api({ajax: 'search', q: String(placeId)}).then(data => {
            const place = (data.places || []).find(p => Number(p.id) === placeId);
            if (!place) return;
            return loadPlace(place).then(() => {
                if (siteId > 0 && state.sites.some(s => Number(s.id) === siteId)) {
                    $('site').value = String(siteId);
                    $('site').dispatchEvent(new Event('change'));
                }
            });
        }).catch(() => {});
    }
    renderSeasons();
    renderEditor();
})();
