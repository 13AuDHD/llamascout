(() => {
    'use strict';
    const escape = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const options = (values, selected) => Object.entries(values).map(([v, label]) => `<option value="${escape(v)}"${String(selected ?? '') === v ? ' selected' : ''}>${escape(label)}</option>`).join('');
    const money = (value, key, index) => `<input type="number" min="0" max="999999.99" step="0.01" inputmode="decimal" data-price="${key}" data-index="${index}" value="${escape(value ?? '')}" placeholder="Inherit" aria-label="${key.replaceAll('_', ' ')} for rate ${index + 1}">`;
    const field = (label, key, value, type = 'text') => `<label class="place-rate-card-field"><span>${label}</span><input type="${type}" data-match="${key}" value="${escape(value)}"></label>`;
    const select = (label, key, value, choices) => `<label class="place-rate-card-field"><span>${label}</span><select data-match="${key}">${options(choices, value)}</select></label>`;
    const number = v => v === '' ? null : Number(v);
    const empty = () => ({ version: 2, currency: 'USD', weekend_days: [5, 6], holidays: [], rows: [] });
    document.querySelectorAll('textarea[name="place_rate_cards_json"]').forEach(textarea => {
        if (textarea.dataset.rateCardsReady) return;
        const original = textarea.closest('.contribution-field');
        if (!original) return;
        let doc;
        try {
            doc = textarea.value.trim() ? JSON.parse(textarea.value) : empty();
            if (doc.version !== 2 || !Array.isArray(doc.rows) || doc.invalid) throw new Error('Saved rates need review.');
        } catch (_) {
            // Never silently turn an unreadable historical value into an empty editor.
            const warning = document.createElement('p');
            warning.textContent = 'These saved rates need review before editing. The original data is shown below and has not been changed.';
            original.prepend(warning);
            return;
        }
        textarea.dataset.rateCardsReady = '1';
        const host = document.createElement('div');
        host.className = 'contribution-field contribution-field-wide place-rate-cards-field';
        host.innerHTML = `<div class="place-rate-cards-heading"><div><span class="place-rate-cards-label">Campsite rates</span><p>USD. Prices replace one another; blank optional prices inherit. Enter 0 for Free.</p></div><button type="button" class="place-rate-cards-add" data-add>+ Rate row</button></div>
            <div class="place-rate-card-grid"><label class="place-rate-card-field"><span>Price schedule</span><select data-schedule></select></label><div class="place-rate-card-field"><span>Weekend nights</span><div data-weekdays></div></div></div>
            <div class="place-rate-matrix-scroll"><table class="place-rate-matrix"><thead><tr><th>Rate category</th><th>Base $</th><th>Weekend $</th><th>Holiday $</th><th></th></tr></thead><tbody data-rows></tbody></table></div>
            <details class="place-rate-card"><summary>Seasons &amp; holiday dates</summary><div data-periods></div><div class="place-rate-cards-heading"><button type="button" class="place-rate-cards-add" data-season-add>+ Season</button><button type="button" class="place-rate-cards-add" data-holiday-add>+ Holiday / event</button></div></details>
            <details class="place-rate-card"><summary>Additional fees</summary><div class="place-rate-card-grid" data-fees></div></details>
            <div class="place-rate-card-grid" data-preview><label class="place-rate-card-field"><span>Preview night</span><input type="date" data-night></label><button type="button" class="place-rate-cards-add" data-check>Check Site prices</button></div><div data-preview-results aria-live="polite"></div>
            <span class="place-rate-cards-status" aria-live="polite">Save with the Place Report.</span>`;
        textarea.classList.add('place-rate-cards-json'); host.append(textarea); original.replaceWith(host);
        const q = s => host.querySelector(s);
        let schedule = '';
        const periods = () => {
            const map = new Map();
            doc.rows.forEach(r => (r.seasons || []).forEach(p => map.set([p.name, p.start, p.end, !!p.recurring].join('|'), p)));
            return [...map.values()];
        };
        const seasonKey = p => [p.name, p.start, p.end, !!p.recurring].join('|');
        const sync = () => {
            textarea.value = JSON.stringify(doc);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
            q('.place-rate-cards-status').textContent = 'Unsaved pricing changes. Save the Place Report.';
        };
        const current = row => schedule.startsWith('holiday:') ? {price: row.holiday_prices?.[schedule.slice(8)] ?? null} : schedule === '' ? row : (row.seasons || []).find(p => seasonKey(p) === schedule);
        const render = () => {
            q('[data-schedule]').innerHTML = `<option value="">Base prices</option>` + periods().map(p => `<option value="${escape(seasonKey(p))}"${schedule === seasonKey(p) ? ' selected' : ''}>${escape(p.name || 'Season')} · ${escape(p.start)} to ${escape(p.end)}</option>`).join('') + doc.holidays.map(p => `<option value="holiday:${escape(p.id)}"${schedule === 'holiday:' + p.id ? ' selected' : ''}>${escape(p.name || 'Holiday / event')} · ${escape(p.start)} to ${escape(p.end)}</option>`).join('');
            q('[data-rows]').innerHTML = doc.rows.map((r, i) => {
                const p = current(r) || {};
                return `<tr data-row="${i}"><td><input data-name value="${escape(r.name)}" placeholder="Rate name" aria-label="Rate name"><details><summary>${escape([r.match?.category, r.match?.layout, r.match?.hookups].filter(Boolean).join(' · ') || 'All camping Sites')} · Matching &amp; details</summary><div class="place-rate-card-grid">
                    ${select('Category', 'category', r.match?.category, { '': 'Any category', single: 'Single', double: 'Double', group: 'Group' })}
                    ${select('Layout', 'layout', r.match?.layout, { '': 'Any layout', back_pull_in: 'Back / pull-in', back_in: 'Back-in', pull_in: 'Pull-in', pull_through: 'Pull-through', parallel: 'Parallel', other: 'Other' })}
                    ${select('Hookups', 'hookups', r.match?.hookups, { '': 'Any hookups', none: 'No hookups', electric: 'Electric', full: 'Full hookups' })}
                    ${select('Rate unit', 'unit', r.unit, { night: 'Per Site / night', week: 'Per week', month: 'Per month', stay: 'Per stay', season: 'Per season', person_night: 'Per person / night', vehicle_night: 'Per vehicle / night' })}
                    ${field('Exact imported Site type (optional)', 'raw_type', r.match?.raw_type || '')}
                    ${field('Loop (optional)', 'loop', r.match?.loop || '')}
                    ${field('Individual Site IDs, comma separated', 'site_ids', (r.match?.site_ids || []).join(', '))}
                    ${field('Pricing source URL', 'source_url', r.source_url || '', 'url')}
                    ${field('Price checked', 'verified_on', r.verified_on || '', 'date')}
                    ${field('Notes', 'notes', r.notes || '')}
                    ${r.match?.legacy_category ? field('Legacy category (exact match)', 'legacy_category', r.match.legacy_category) : ''}
                    ${r.match?.legacy_site ? field('Legacy Site code (exact match)', 'legacy_site', r.match.legacy_site) : ''}
                    ${r.match?.feature_ids?.length || r.match?.area_ids?.length ? `<span>Preserved mapped targets: ${escape([...(r.match.feature_ids || []), ...(r.match.area_ids || [])].join(', '))}</span>` : ''}
                    ${r.needs_review ? '<label><input type="checkbox" data-reviewed> I have checked the matching conditions and dates</label>' : ''}
                    </div></details></td><td>${money(p.price, 'price', i)}</td><td>${schedule.startsWith('holiday:') ? '' : money(p.weekend_price, 'weekend_price', i)}</td><td>${schedule.startsWith('holiday:') ? '' : money(p.holiday_price, 'holiday_price', i)}</td><td><button type="button" class="place-rate-card-remove" data-remove="${i}" aria-label="Remove rate ${i + 1}">×</button></td></tr>`;
            }).join('');
            q('[data-periods]').innerHTML = periods().map(p => `<div class="place-rate-period" data-period="${escape(seasonKey(p))}"><strong>${escape(p.name || 'Season')}</strong><span>${escape(p.start)} to ${escape(p.end)}${p.recurring ? ' · annually' : ''}</span><button type="button" class="place-rate-card-remove" data-period-remove>Remove season</button></div>`).join('') + doc.holidays.map((p, i) => `<div class="place-rate-period" data-holiday="${i}"><input data-holiday-key="name" aria-label="Holiday name" value="${escape(p.name)}"><input type="date" data-holiday-key="start" aria-label="First holiday night" value="${escape(p.start)}"><input type="date" data-holiday-key="end" aria-label="Last holiday night" value="${escape(p.end)}"><button type="button" class="place-rate-card-remove" data-holiday-remove>Remove holiday</button></div>`).join('');
        };
        q('[data-weekdays]').innerHTML = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map((d,i) => `<label class="place-rate-weekday"><input type="checkbox" value="${i}"${doc.weekend_days.includes(i) ? ' checked' : ''}>${d}</label>`).join('');
        q('[data-weekdays]').addEventListener('change', () => { doc.weekend_days = [...q('[data-weekdays]').querySelectorAll(':checked')].map(c => Number(c.value)); sync(); });
        q('[data-schedule]').addEventListener('change', e => { schedule = e.target.value; render(); });
        q('[data-rows]').addEventListener('input', e => {
            const tr = e.target.closest('[data-row]'); if (!tr) return;
            const row = doc.rows[Number(tr.dataset.row)]; const el = e.target;
            if (el.hasAttribute('data-name')) row.name = el.value;
            if (el.dataset.price) {
                if (schedule.startsWith('holiday:')) {
                    row.holiday_prices = { ...(row.holiday_prices || {}), [schedule.slice(8)]: number(el.value) }; sync(); return;
                }
                let p = current(row);
                if (!p) { p = { ...periods().find(p => seasonKey(p) === schedule), price: null, weekend_price: null, holiday_price: null }; row.seasons ||= []; row.seasons.push(p); }
                p[el.dataset.price] = number(el.value);
            }
            if (el.dataset.match) {
                const k = el.dataset.match;
                if (['unit','source_url','verified_on','notes'].includes(k)) row[k] = el.value;
                else { row.match ||= {}; row.match[k] = k === 'site_ids' ? el.value.split(',').filter(v => v.trim() !== '').map(v => Number(v.trim())) : el.value; }
                tr.querySelector('summary').textContent = ( [row.match?.category, row.match?.layout, row.match?.hookups].filter(Boolean).join(' · ') || 'All camping Sites') + ' · Matching & details';
            }
            if (el.hasAttribute('data-reviewed')) row.needs_review = !el.checked;
            sync();
        });
        q('[data-rows]').addEventListener('click', e => { const b = e.target.closest('[data-remove]'); if (!b) return; doc.rows.splice(Number(b.dataset.remove), 1); sync(); render(); });
        q('[data-add]').addEventListener('click', () => { doc.rows.push({ id: `rate-${Date.now()}-${doc.rows.length}`, name: '', match: {}, unit: 'night', price: null, weekend_price: null, holiday_price: null, seasons: [] }); sync(); render(); });
        q('[data-season-add]').addEventListener('click', () => {
            if (!doc.rows.length) { q('.place-rate-cards-status').textContent = 'Add a rate row first.'; return; }
            const form = document.createElement('div'); form.className = 'place-rate-card-grid';
            form.innerHTML = '<label class="place-rate-card-field"><span>Season name</span><input data-new-name></label><label class="place-rate-card-field"><span>First night</span><input type="date" data-new-start></label><label class="place-rate-card-field"><span>Last night</span><input type="date" data-new-end></label><label><input type="checkbox" data-new-recurring> Repeat annually</label><button type="button" class="place-rate-cards-add" data-new-save>Add season</button>';
            q('[data-periods]').append(form);
            form.querySelector('[data-new-save]').addEventListener('click', () => {
                const name = form.querySelector('[data-new-name]').value.trim(), start = form.querySelector('[data-new-start]').value, end = form.querySelector('[data-new-end]').value, recurring = form.querySelector('[data-new-recurring]').checked;
                if (!name || !start || !end || (!recurring && start > end)) { q('.place-rate-cards-status').textContent = 'Enter a season name and valid dates.'; return; }
                const p = { name, start: recurring ? start.slice(5) : start, end: recurring ? end.slice(5) : end, recurring, price: null, weekend_price: null, holiday_price: null };
                doc.rows.forEach(r => { r.seasons ||= []; r.seasons.push({ ...p }); }); schedule = seasonKey(p); sync(); render();
            });
        });
        q('[data-holiday-add]').addEventListener('click', () => { doc.holidays.push({ id: `holiday-${Date.now()}-${doc.holidays.length}`, name: '', start: '', end: '', recurring: false }); sync(); render(); });
        q('[data-periods]').addEventListener('input', e => { const h = e.target.closest('[data-holiday]'); if (!h || !e.target.dataset.holidayKey) return; const holiday = doc.holidays[Number(h.dataset.holiday)]; holiday[e.target.dataset.holidayKey] = e.target.value; const option = [...q('[data-schedule]').options].find(o => o.value === 'holiday:' + holiday.id); if (option) option.textContent = `${holiday.name || 'Holiday / event'} · ${holiday.start} to ${holiday.end}`; sync(); });
        q('[data-periods]').addEventListener('click', e => {
            if (e.target.hasAttribute('data-holiday-remove')) { const index = Number(e.target.closest('[data-holiday]').dataset.holiday); const id = doc.holidays[index].id; doc.rows.forEach(r => { if (r.holiday_prices) delete r.holiday_prices[id]; }); doc.holidays.splice(index, 1); schedule = ''; sync(); render(); }
            if (e.target.hasAttribute('data-period-remove')) { const key = e.target.closest('[data-period]').dataset.period; doc.rows.forEach(r => r.seasons = (r.seasons || []).filter(p => seasonKey(p) !== key)); schedule = ''; sync(); render(); }
        });
        // Move existing canonical surcharge controls here; no second fee storage.
        ['reservation_fee','extra_vehicle_fee','membership_fee','parking_entrance_fee','entrance_facility_fee','parking_fee'].forEach(key => {
            const control = document.querySelector(`[name="${key}"]`); const wrapper = control?.closest('.contribution-field');
            if (wrapper && !wrapper.contains(host)) q('[data-fees]').append(wrapper);
        });
        const form = host.closest('form'); const placeId = form?.querySelector('[name="place_id"]')?.value;
        const csrf = form?.querySelector('[name="csrf_token"]')?.value;
        if (!placeId || form?.querySelector('[name="place_admin_action"]')?.value !== 'save-report') q('[data-preview]').hidden = true;
        const today = new Date();
        q('[data-night]').value = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
        q('[data-check]').addEventListener('click', async () => {
            const button = q('[data-check]'); button.disabled = true;
            try {
                const body = new FormData(); body.set('csrf_token', csrf || ''); body.set('place_id', placeId || ''); body.set('rate_cards_json', JSON.stringify(doc)); body.set('action', 'preview'); body.set('date', q('[data-night]').value);
                const response = await fetch('/save-rate-cards.php', { method: 'POST', credentials: 'same-origin', body });
                const result = await response.json(); if (!response.ok || !result.ok) throw new Error(result.error || 'Could not check pricing.');
                q('[data-preview-results]').textContent = `${result.priced} priced · ${result.review} need review · ${result.unknown} unknown. ` + result.sites.map(s => `${s.name}: ${s.price}`).join(' · ');
            } catch (error) { q('[data-preview-results]').textContent = error.message; }
            finally { button.disabled = false; }
        });
        render();
        // Newly submitted empty cards use [] explicitly, never an unanswered clear.
        if (!textarea.value.trim()) textarea.value = JSON.stringify(doc);
    });
})();
