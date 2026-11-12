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
        site: null,
        seasonId: null,
        drafts: new Map(),
        sitePayload: null,
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
        const match = /^(\d{2})-(\d{2})$/.exec(String(value || ''));
        if (!match) return 'Dates not set';
        const date = new Date(2000, Number(match[1]) - 1, Number(match[2]));
        return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
    };
    const formatRange = s => {
        if (!s.start || !s.end) return 'Dates not set';
        return dateLabel(s.start) + ' – ' + dateLabel(s.end);
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
            $('place-seasons-link').hidden = true;
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
    const setEditorEnabled = enabled => {
        $('status-field').disabled = !enabled;
        ['start','end'].forEach(id => { $(id).disabled = !enabled; });
        root.querySelectorAll('[data-cpr-rate]').forEach(input => { input.disabled = !enabled; });
        $('copy-season').disabled = !enabled || state.seasons.length < 2;
        // The first pass has no approved per-Site write adapter.
        $('save').disabled = true;
    };
    const resetSiteSelection = () => {
        state.site = null;
        state.sitePayload = null;
        state.drafts = new Map();
        state.seasonId = null;
        $('site').replaceChildren();
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Select a Place first';
        $('site').append(option);
        $('site').disabled = true;
        $('add-season').disabled = true;
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
            state.site = null;
            state.drafts = new Map();
            state.seasonId = null;
            $('place-seasons-link').hidden = false;
            $('place-seasons-link').href = '/place.php?id=' + data.place.id + '#place-seasons';
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

    $('site').addEventListener('change', async event => {
        const id = Number(event.target.value);
        ++state.siteRequest;
        if (!id || !state.place) {
            state.site = null;
            state.sitePayload = null;
            state.drafts = new Map();
            state.seasonId = null;
            setSiteSummary();
            renderEditor();
            renderSeasons();
            return;
        }
        state.site = state.sites.find(s => Number(s.id) === id) || null;
        setSiteSummary();
        const request = state.siteRequest;
        status('Loading pricing for ' + state.site.label + '...');
        try {
            const data = await api({ajax: 'site', place_id: state.place.id, site_id: id});
            if (request !== state.siteRequest) return;
            state.sitePayload = data;
            state.drafts = new Map();
            for (const season of state.seasons) {
                const seasonOverride = (data.rate?.seasons || []).find(
                    value => Number(value.season_id) === Number(season.id)
                );
                state.drafts.set(String(season.id), {
                    weekday: seasonOverride?.price ?? data.rate?.weekday ?? '',
                    weekend: seasonOverride?.weekend_price ?? data.rate?.weekend ?? '',
                    holiday: seasonOverride?.holiday_price ?? data.rate?.holiday ?? '',
                    vehicle: '',
                    group: '',
                    availability: data.availability?.[String(season.id)] || '',
                    start: season.start,
                    end: season.end,
                    changed: false,
                });
            }
            state.seasonId = state.seasons[0]?.id ?? null;
            updateSegments('[data-capacity]', state.site?.type === 'group_site' ? 'group' : '');
            updateSegments('[data-layout]', state.site?.layout || '');
            updateSegments('[data-utility]', state.site?.hookups === 'full' ? 'full'
                : (state.site?.hookups?.includes('electric') ? 'electric' : ''));
            renderSeasons();
            renderEditor();
            status(data.rate
                ? 'Existing pricing loaded. Edits below are preview-only in this first pass.'
                : 'No matching published rate found. You can preview proposed prices without changing production data.');
            const url = new URL(window.location.href);
            url.searchParams.set('site_id', id);
            window.history.replaceState(null, '', url);
        } catch (error) {
            if (request === state.siteRequest) {
                state.sitePayload = null;
                state.seasonId = null;
                state.drafts = new Map();
                renderEditor();
                status(error.message, true);
            }
        }
    });

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
        dates.textContent = formatRange(state.drafts.get(String(season.id)) || season);
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
            message.textContent = 'No Place seasons defined. Add one here for a preview, or configure seasons from the Place editor.';
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
    };

    root.querySelectorAll('[data-cpr-rate]').forEach(input => {
        input.addEventListener('input', event => {
            const draft = selected();
            if (!draft) return;
            const key = event.target.dataset.cprRate;
            draft[key] = event.target.value;
            draft.changed = true;
            renderPreview();
            status('Preview changed; these values have not been saved.');
        });
        input.addEventListener('blur', event => {
            if (event.target.value !== '') {
                event.target.value = cleanAmount(event.target.value);
                const draft = selected();
                if (draft) draft[event.target.dataset.cprRate] = event.target.value;
            }
            renderPreview();
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
            status('Preview changed; availability has not been saved.');
        });
    });
    for (const [selector, attr] of [
        ['[data-capacity]', 'capacity'],
        ['[data-layout]', 'layout'],
        ['[data-utility]', 'utility'],
    ]) {
        root.querySelectorAll(selector).forEach(button => {
            button.addEventListener('click', () => {
                if (!state.site) return;
                updateSegments(selector, button.dataset[attr]);
                status('Rate type selection changed in preview only.');
            });
        });
    }
    for (const [id, key] of [['start','start'],['end','end']]) {
        $(id).addEventListener('change', event => {
            const draft = selected();
            if (!draft) return;
            draft[key] = event.target.value;
            draft.changed = true;
            renderSeasons();
            status('Season date preview changed. Place season definitions are unchanged.');
        });
    }
    $('add-season').addEventListener('click', () => {
        if (!state.place) return;
        const name = window.prompt('Name the preview season (not yet saved to the Place)', 'New Season');
        if (!name?.trim()) return;
        const id = 'draft-' + Date.now();
        state.seasons.push({id, name: name.trim().slice(0,80), start: '', end: '', draft: true});
        if (state.site) state.drafts.set(String(id), {
            weekday: '', weekend: '', holiday: '', vehicle: '', group: '', availability: '',
            start: '', end: '', changed: true,
        });
        state.seasonId = id;
        renderSeasons();
        renderEditor();
        status('Season created for preview only. Production Place seasons have not changed.');
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
        status('Copied rates for preview only; not saved to the database.');
    });
    $('cancel').addEventListener('click', () => {
        if (!state.site || !state.place) {
            $('place-search').value = '';
            state.place = null;
            resetSiteSelection();
            status('No pending changes.');
            return;
        }
        // Cancel removes unsaved seasons as well as unsaved rate changes.
        state.seasons = state.seasons.filter(season => !season.draft);
        $('site').dispatchEvent(new Event('change'));
    });
    $('save').addEventListener('click', () => status(
        'Saving is not connected yet. This first pass never changes live pricing.', true
    ));
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
