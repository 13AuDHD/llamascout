(() => {
    'use strict';

    const FIELD = 'place_rate_cards_json';
    const MAX_CARDS = 50;
    const clean = value => String(value ?? '').trim();
    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('\"', '&quot;')
        .replaceAll("'", '&#039;');

    const scopeOptions = {
        entire_place: 'Entire Place / flat rate',
        site_category: 'Site category',
        individual_site: 'Individual site',
        mixed_other: 'Mixed / other'
    };
    const unitOptions = {
        night: 'Per night',
        week: 'Per week',
        month: 'Per month',
        stay: 'Per stay',
        season: 'Per season'
    };
    const seasonOptions = {
        year_round: 'Year-round',
        peak: 'Peak season',
        off_peak: 'Off-season',
        seasonal: 'Seasonal rate',
        custom: 'Custom / other'
    };
    const parkingOptions = {
        '': 'Not specified',
        pull_through: 'Pull-through',
        back_in: 'Back-in',
        pull_in: 'Pull-in',
        parallel: 'Parallel',
        other: 'Other'
    };

    const options = (items, selected = '') => Object.entries(items)
        .map(([value, label]) => `<option value="${escapeHtml(value)}"${String(selected) === value ? ' selected' : ''}>${escapeHtml(label)}</option>`)
        .join('');

    const parseCards = raw => {
        const value = clean(raw);
        if (!value) return [];
        try {
            const parsed = JSON.parse(value);
            if (!Array.isArray(parsed)) return [];
            return parsed
                .filter(card => card && typeof card === 'object' && !Array.isArray(card))
                .slice(0, MAX_CARDS)
                .map(card => ({
                    name: clean(card.name),
                    scope: clean(card.scope) || 'entire_place',
                    category: clean(card.category),
                    site: clean(card.site),
                    parking_style: clean(card.parking_style),
                    price: card.price === 0 || clean(card.price) !== '' ? String(card.price) : '',
                    unit: clean(card.unit) || 'night',
                    season: clean(card.season) || 'year_round',
                    max_people: card.max_people === 0 || clean(card.max_people) !== '' ? String(card.max_people) : '',
                    max_vehicles: card.max_vehicles === 0 || clean(card.max_vehicles) !== '' ? String(card.max_vehicles) : '',
                    source_url: clean(card.source_url),
                    verified_on: clean(card.verified_on)
                }));
        } catch (_) {
            return [];
        }
    };

    const cardMarkup = (card, index) => `
        <article class="place-rate-card" data-rate-card>
            <header class="place-rate-card-header">
                <div>
                    <span class="place-rate-card-kicker">Rate ${index + 1}</span>
                    <strong data-rate-card-title>${escapeHtml(card.name || 'Unnamed rate')}</strong>
                </div>
                <button type="button" class="place-rate-card-remove" data-rate-card-remove aria-label="Remove rate ${index + 1}">Remove</button>
            </header>

            <div class="place-rate-card-grid">
                <label class="place-rate-card-field place-rate-card-span-2">
                    <span>Rate name / type</span>
                    <input type="text" maxlength="120" data-rate-key="name" value="${escapeHtml(card.name)}" placeholder="Standard, RV Electric, Group Site…">
                </label>

                <label class="place-rate-card-field">
                    <span>Applies to</span>
                    <select data-rate-key="scope">${options(scopeOptions, card.scope)}</select>
                </label>

                <label class="place-rate-card-field" data-rate-category-field>
                    <span>Site category</span>
                    <input type="text" maxlength="120" data-rate-key="category" value="${escapeHtml(card.category)}" placeholder="Standard, tent-only, electric…">
                </label>

                <label class="place-rate-card-field" data-rate-site-field>
                    <span>Individual site</span>
                    <input type="text" maxlength="120" data-rate-key="site" value="${escapeHtml(card.site)}" placeholder="Site 13">
                </label>

                <label class="place-rate-card-field">
                    <span>Price</span>
                    <div class="place-rate-card-money"><span>$</span><input type="number" min="0" step="0.01" inputmode="decimal" data-rate-key="price" value="${escapeHtml(card.price)}" placeholder="0.00"></div>
                </label>

                <label class="place-rate-card-field">
                    <span>Rate unit</span>
                    <select data-rate-key="unit">${options(unitOptions, card.unit)}</select>
                </label>

                <label class="place-rate-card-field">
                    <span>Season</span>
                    <select data-rate-key="season">${options(seasonOptions, card.season)}</select>
                </label>

                <label class="place-rate-card-field">
                    <span>Parking style</span>
                    <select data-rate-key="parking_style">${options(parkingOptions, card.parking_style)}</select>
                </label>

                <label class="place-rate-card-field">
                    <span>Maximum people</span>
                    <input type="number" min="0" max="999" step="1" inputmode="numeric" data-rate-key="max_people" value="${escapeHtml(card.max_people)}" placeholder="Optional">
                </label>

                <label class="place-rate-card-field">
                    <span>Maximum vehicles</span>
                    <input type="number" min="0" max="999" step="1" inputmode="numeric" data-rate-key="max_vehicles" value="${escapeHtml(card.max_vehicles)}" placeholder="Optional">
                </label>

                <label class="place-rate-card-field place-rate-card-span-2">
                    <span>Official pricing source</span>
                    <input type="url" maxlength="2048" data-rate-key="source_url" value="${escapeHtml(card.source_url)}" placeholder="https://…">
                </label>

                <label class="place-rate-card-field">
                    <span>Price verified</span>
                    <input type="date" data-rate-key="verified_on" value="${escapeHtml(card.verified_on)}">
                </label>
            </div>
        </article>
    `;

    const serializeCard = node => {
        const value = key => clean(node.querySelector(`[data-rate-key="${key}"]`)?.value);
        const decimal = value('price');
        const people = value('max_people');
        const vehicles = value('max_vehicles');
        const card = {
            name: value('name'),
            scope: value('scope') || 'entire_place',
            category: value('category'),
            site: value('site'),
            parking_style: value('parking_style'),
            price: decimal === '' ? null : Math.max(0, Number(decimal)),
            unit: value('unit') || 'night',
            season: value('season') || 'year_round',
            max_people: people === '' ? null : Math.max(0, parseInt(people, 10) || 0),
            max_vehicles: vehicles === '' ? null : Math.max(0, parseInt(vehicles, 10) || 0),
            source_url: value('source_url'),
            verified_on: value('verified_on')
        };
        if (card.scope !== 'site_category') card.category = '';
        if (card.scope !== 'individual_site') card.site = '';
        return card;
    };

    const hasContent = card => [
        card.name, card.category, card.site, card.parking_style,
        card.source_url, card.verified_on
    ].some(Boolean) || card.price !== null || card.max_people !== null || card.max_vehicles !== null;

    const initialize = textarea => {
        if (textarea.dataset.rateCardsReady === '1') return;
        textarea.dataset.rateCardsReady = '1';

        const original = textarea.closest('.contribution-field');
        if (!original) return;

        let cards = parseCards(textarea.value);
        let dirty = false;

        const field = document.createElement('div');
        field.className = 'contribution-field contribution-field-wide place-rate-cards-field';
        field.innerHTML = `
            <div class="place-rate-cards-heading">
                <div>
                    <span class="place-rate-cards-label">Rate cards</span>
                    <p>Use one card for each distinct price. Add multiple cards for site categories, individual sites, or seasonal rates.</p>
                </div>
                <button type="button" class="place-rate-cards-add" data-rate-card-add>Add rate</button>
            </div>
            <div class="place-rate-cards-list" data-rate-card-list></div>
            <div class="place-rate-cards-empty" data-rate-card-empty>No detailed rates added yet. The basic Camping fee field can still be used for a simple single-price Place.</div>
            <span class="place-rate-cards-status" data-rate-card-save-status aria-live="polite"></span>
        `;
        textarea.classList.add('place-rate-cards-json');
        field.appendChild(textarea);
        original.replaceWith(field);

        const list = field.querySelector('[data-rate-card-list]');
        const empty = field.querySelector('[data-rate-card-empty]');
        const status = field.querySelector('[data-rate-card-save-status]');

        const syncScopeFields = cardNode => {
            const scope = clean(cardNode.querySelector('[data-rate-key="scope"]')?.value);
            const category = cardNode.querySelector('[data-rate-category-field]');
            const site = cardNode.querySelector('[data-rate-site-field]');
            if (category) category.hidden = scope !== 'site_category';
            if (site) site.hidden = scope !== 'individual_site';
        };

        const updateTextarea = () => {
            const payload = [...list.querySelectorAll('[data-rate-card]')]
                .map(serializeCard)
                .filter(hasContent);
            textarea.value = payload.length ? JSON.stringify(payload) : '';
            dirty = true;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const bindCard = node => {
            syncScopeFields(node);
            node.querySelectorAll('input, select').forEach(control => {
                control.addEventListener('input', () => {
                    if (control.dataset.rateKey === 'name') {
                        const title = node.querySelector('[data-rate-card-title]');
                        if (title) title.textContent = clean(control.value) || 'Unnamed rate';
                    }
                    updateTextarea();
                });
                control.addEventListener('change', () => {
                    if (control.dataset.rateKey === 'scope') syncScopeFields(node);
                    updateTextarea();
                });
            });
            node.querySelector('[data-rate-card-remove]')?.addEventListener('click', () => {
                node.remove();
                renumber();
                updateTextarea();
                renderEmpty();
            });
        };

        const renumber = () => {
            [...list.querySelectorAll('[data-rate-card]')].forEach((node, index) => {
                const kicker = node.querySelector('.place-rate-card-kicker');
                if (kicker) kicker.textContent = `Rate ${index + 1}`;
                const remove = node.querySelector('[data-rate-card-remove]');
                if (remove) remove.setAttribute('aria-label', `Remove rate ${index + 1}`);
            });
        };

        const renderEmpty = () => {
            empty.hidden = list.querySelector('[data-rate-card]') !== null;
        };

        const addCard = card => {
            if (list.querySelectorAll('[data-rate-card]').length >= MAX_CARDS) {
                status.textContent = `Maximum ${MAX_CARDS} rate cards.`;
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.innerHTML = cardMarkup(card, list.querySelectorAll('[data-rate-card]').length).trim();
            const node = wrapper.firstElementChild;
            list.appendChild(node);
            bindCard(node);
            renderEmpty();
        };

        cards.forEach(addCard);
        renderEmpty();

        field.querySelector('[data-rate-card-add]')?.addEventListener('click', () => {
            addCard({
                name: '', scope: 'entire_place', category: '', site: '',
                parking_style: '', price: '', unit: 'night', season: 'year_round',
                max_people: '', max_vehicles: '', source_url: '', verified_on: ''
            });
            updateTextarea();
            list.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        const form = field.closest('form');
        const placeId = clean(form?.querySelector('[name="place_id"]')?.value);
        const csrf = clean(form?.querySelector('[name="csrf_token"]')?.value);
        const adminAction = clean(form?.querySelector('[name="place_admin_action"]')?.value);

        if (placeId && csrf && adminAction === 'save-report') {
            window.addEventListener('llama:admin-place-report-saved', async event => {
                if (!dirty || String(event.detail?.placeId || '') !== placeId) return;
                status.textContent = 'Saving rate cards…';
                try {
                    const body = new FormData();
                    body.set('csrf_token', csrf);
                    body.set('place_id', placeId);
                    body.set('rate_cards_json', textarea.value);
                    const response = await fetch('/save-rate-cards.php', {
                        method: 'POST', credentials: 'same-origin', body,
                        headers: { Accept: 'application/json' }
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || payload.ok !== true) {
                        throw new Error(payload.error || 'Rate cards were not saved.');
                    }
                    dirty = false;
                    status.textContent = payload.count === 1 ? '1 rate card saved.' : `${payload.count || 0} rate cards saved.`;
                } catch (error) {
                    status.textContent = error?.message || 'Rate cards were not saved.';
                }
            });
        }
    };

    document.querySelectorAll(`textarea[name="${FIELD}"]`).forEach(initialize);
})();
