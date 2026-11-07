(() => {
    'use strict';
    const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const choices = (items, selected) => Object.entries(items).map(([value,label]) => `<option value="${esc(value)}"${String(selected ?? '') === value ? ' selected' : ''}>${esc(label)}</option>`).join('');
    const categories = {'':'All Sites',single:'Single',double:'Double',group:'Group'};
    const layouts = {'':'Any layout',back_pull_in:'Back / pull-in',back_in:'Back-in',pull_in:'Pull-in',pull_through:'Pull-through',parallel:'Parallel',other:'Other'};
    const units = {night:'Per Site / night',week:'Per week',month:'Per month',stay:'Per stay',season:'Per season',person_night:'Per person / night',vehicle_night:'Per vehicle / night'};
    const siteCount = n => `${n} ${Number(n) === 1 ? 'Site' : 'Sites'}`;
    const keyOf = p => [p.name,p.start,p.end,!!p.recurring].join('|');
    const localDate = () => { const d = new Date(); return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-'); };
    const newRow = () => ({id:`rate-${Date.now()}-${Math.random().toString(36).slice(2,8)}`,name:'',match:{},unit:'night',price:null,weekend_price:null,holiday_price:null,seasons:[]});
    const input = (label,key,value,type='text') => `<label class="place-rate-card-field"><span>${label}</span><input type="${type}" data-detail="${key}" value="${esc(value)}"></label>`;
    const select = (label,key,value,items) => `<label class="place-rate-card-field"><span>${label}</span><select data-detail="${key}">${choices(items,value)}</select></label>`;
    document.querySelectorAll('textarea[name="place_rate_cards_json"]').forEach(textarea => {
        if (textarea.dataset.rateCardsReady) return;
        const original = textarea.closest('.contribution-field');
        if (!original) return;
        let doc;
        try {
            doc = textarea.value.trim() ? JSON.parse(textarea.value) : {version:2,currency:'USD',weekend_days:[5,6],holidays:[],rows:[]};
            if (doc.version !== 2 || !Array.isArray(doc.rows) || doc.invalid) throw Error();
        } catch (_) {
            const p = document.createElement('p'); p.textContent = 'Saved pricing could not be opened. The original data has been kept below.'; original.prepend(p); return;
        }
        textarea.dataset.rateCardsReady = '1';
        let sites = [];
        try {
            sites = JSON.parse(document.querySelector('[data-admin-place-campsites]')?.textContent || '{}').sites || [];
            sites = sites.map(s => ({id:Number(s.scope_id || String(s.id).split(':').pop()),name:String(s.label || s.display_name || s.site_code || 'Site')})).filter(s => s.id > 0);
        } catch (_) { sites = []; }
        const host = document.createElement('div'); host.className = 'contribution-field contribution-field-wide place-rate-cards-field';
        host.innerHTML = `<div class="place-rate-cards-heading"><span class="place-rate-cards-label">Prices per Site / night · USD</span><button type="button" class="place-rate-cards-add" data-add>+ Rate row</button></div>
            <div class="place-rate-toolbar"><label class="place-rate-card-field"><span>Price schedule</span><select data-schedule></select></label><label class="place-rate-card-field"><span>Weekend nights</span><select data-weekend><option value="5,6">Friday + Saturday</option><option value="0,5,6">Friday + Saturday + Sunday</option><option value="4,5,6">Thursday + Friday + Saturday</option><option value="custom">Choose nights…</option></select></label></div>
            <div data-weekdays hidden></div><p class="place-rate-schedule-note" data-schedule-note></p><p class="place-rate-warning" data-selection-warning hidden>Some saved Site selections do not match this Place. Open More options and choose Sites by name, or choose All matching Sites.</p>
            <div class="place-rate-matrix-scroll"><table class="place-rate-matrix"><thead><tr><th>Site category</th><th>Layout</th><th data-base-heading>Base $</th><th data-optional-price>Weekend $</th><th data-optional-price>Holiday $</th><th></th></tr></thead><tbody data-rows></tbody></table></div>
            <p class="place-rate-empty" data-empty>Add a rate row. Choose All Sites for one campground-wide price, or Single, Double and Group for different prices.</p>
            <p class="place-rate-hint">0 = Free. Blank optional cells use the base schedule.</p>
            <p class="place-rate-hint" data-booking-link hidden><a href="#place-seasons" data-season-booking>Set seasonal reservations or FCFS</a></p>
            <details class="place-rate-section"><summary>Seasons &amp; holiday dates</summary><div data-periods></div><div class="place-rate-actions"><button type="button" class="place-rate-cards-add" data-season-add>+ Season</button><button type="button" class="place-rate-cards-add" data-holiday-add>+ Holiday / event</button></div><div data-period-editor></div></details>
            <details class="place-rate-section"><summary>Additional fees</summary><div class="place-rate-card-grid" data-fees></div></details>
            <section class="place-rate-preview" data-preview><h3>Site price preview</h3><div class="place-rate-toolbar"><label class="place-rate-card-field"><span>Site</span><select data-preview-site></select></label><label class="place-rate-card-field"><span>Night of</span><input type="date" data-night></label></div><div aria-live="polite"><strong class="place-rate-preview-price" data-preview-price>Choose a Site</strong><p class="place-rate-hint" data-preview-reason></p><p class="place-rate-hint" data-coverage></p></div><details data-attention hidden><summary>Sites needing attention</summary><ul data-attention-list></ul></details></section>
            <div class="place-rate-footer"><span class="place-rate-cards-status" aria-live="polite">Prices save with the Place Report.</span><button type="button" class="place-rate-cards-save" data-save-prices>Save prices</button></div>`;
        textarea.classList.add('place-rate-cards-json'); host.append(textarea); original.replaceWith(host);
        const q = s => host.querySelector(s);
        const form = host.closest('form');
        const placeId = form?.querySelector('[name="place_id"]')?.value;
        const csrf = form?.querySelector('[name="csrf_token"]')?.value;
        const admin = !!placeId && form?.querySelector('[name="place_admin_action"]')?.value === 'save-report';
        let schedule = '', timer, request = 0, preview = [], lastPreview = null;
        const periods = () => [...new Map(doc.rows.flatMap(r => (r.seasons || []).map(p => [keyOf(p),p]))).values()];
        const periodName = () => schedule.startsWith('holiday:') ? doc.holidays.find(h => 'holiday:'+h.id === schedule)?.name : periods().find(p => keyOf(p) === schedule)?.name;
        const status = message => q('.place-rate-cards-status').textContent = message;
        const current = row => schedule.startsWith('holiday:') ? {price:row.holiday_prices?.[schedule.slice(8)] ?? null} : schedule ? (row.seasons || []).find(p => keyOf(p) === schedule) : row;
        const showSite = () => {
            const selected = preview.find(s => String(s.id) === q('[data-preview-site]').value);
            q('[data-preview-price]').textContent = selected?.price || (sites.length ? 'Checking price…' : 'Add Sites to preview their prices');
            q('[data-preview-reason]').textContent = selected?.reason || '';
        };
        const checkPrices = async () => {
            if (!admin || !q('[data-night]').value) return;
            const serial = ++request;
            q('[data-preview-price]').textContent = 'Checking price…';
            try {
                const body = new FormData(); body.set('csrf_token',csrf || ''); body.set('place_id',placeId); body.set('action','preview'); body.set('date',q('[data-night]').value); body.set('rate_cards_json',JSON.stringify(doc));
                const response = await fetch('/save-rate-cards.php',{method:'POST',credentials:'same-origin',body});
                const result = await response.json();
                if (serial !== request) return;
                if (!response.ok || !result.ok) throw new Error(result.error || 'Could not check prices.');
                lastPreview = result; preview = result.sites;
                q('[data-coverage]').textContent = `${result.priced} Sites priced · ${result.review} need review · ${result.unknown} without a matching price`;
                q('[data-attention]').hidden = !(result.review + result.unknown);
                q('[data-attention-list]').replaceChildren();
                result.sites.filter(s => s.state !== 'priced').forEach(s => { const li = document.createElement('li'); li.textContent = `${s.name}: ${s.reason}`; q('[data-attention-list]').append(li); });
                result.row_counts && host.querySelectorAll('[data-row-count]').forEach(el => el.textContent = siteCount(result.row_counts[doc.rows[Number(el.dataset.rowCount)]?.id] || 0));
                showSite();
            } catch (error) {
                if (serial !== request) return;
                q('[data-preview-price]').textContent = 'Preview needs attention'; q('[data-preview-reason]').textContent = error.message;
            }
        };
        const queuePreview = () => { clearTimeout(timer); ++request; timer = setTimeout(checkPrices,450); };
        const sync = () => {
            textarea.value = JSON.stringify(doc);
            textarea.dispatchEvent(new Event('input',{bubbles:true})); textarea.dispatchEvent(new Event('change',{bubbles:true}));
            status('Unsaved prices'); queuePreview();
        };
        const money = (p,key,i) => `<input type="number" min="0" max="999999.99" step="0.01" inputmode="decimal" data-price="${key}" value="${esc(p[key] ?? '')}" placeholder="${key === 'price' && !schedule ? 'Unknown' : 'Inherit'}" aria-label="${key.replaceAll('_',' ')} for rate ${i+1}">`;
        const details = (r,i) => {
            const selected = new Set(r.match?.site_ids || []);
            const specific = selected.size > 0 || r.match?.site_selection;
            const missing = [...selected].filter(id => !sites.some(s => s.id === id)).length;
            return `<details class="place-rate-row-details" data-extra-row="${i}"><summary>More options${selected.size ? ` · ${selected.size} selected Sites` : ''}${r.needs_review ? ' · Review needed' : ''}</summary><div class="place-rate-card-grid">
                ${select('Hookups','hookups',r.match?.hookups,{'':'Any hookups',none:'No hookups',electric:'Electric',full:'Full hookups'})}
                ${select('Applies to','selection',specific ? 'selected' : '',{'':'All matching Sites',selected:'Choose specific Sites'})}
                <div class="place-rate-site-picker" data-site-picker${specific ? '' : ' hidden'}><span>Choose Sites by name</span><input type="search" data-site-search placeholder="Find a Site" aria-label="Find a Site"><div class="place-rate-site-options">${sites.map(s => `<label><input type="checkbox" data-site-id="${s.id}"${selected.has(s.id) ? ' checked' : ''}>${esc(s.name)}</label>`).join('') || '<p>Site choices are available after the Place has Sites.</p>'}</div>${missing ? `<p class="place-rate-warning">${missing} saved selections do not match this Place. Choose Sites above, or select All matching Sites. Your existing selection has been kept.</p>` : ''}</div>
                ${admin && document.querySelector('[data-admin-seasons-disclosure]') ? '<p class="place-rate-hint"><a href="#place-seasons" data-season-booking>Set seasonal reservations for these Sites</a></p>' : ''}
                ${select('Billing unit','unit',r.unit,units)}${input('Label (optional)','name',r.name || '')}${input('Loop (optional)','loop',r.match?.loop || '')}${input('Imported Site type (optional)','raw_type',r.match?.raw_type || '')}
                ${input('Pricing source URL','source_url',r.source_url || '','url')}${input('Price checked','verified_on',r.verified_on || '','date')}${input('Notes','notes',r.notes || '')}
                ${r.match?.legacy_category ? input('Previous category','legacy_category',r.match.legacy_category) : ''}${r.match?.legacy_site ? input('Previous Site code','legacy_site',r.match.legacy_site) : ''}
                ${r.match?.feature_ids?.length || r.match?.area_ids?.length ? '<p>Existing mapped selection preserved.</p>' : ''}
                ${r.needs_review ? '<label><input type="checkbox" data-reviewed> I have checked this older rate and its dates.</label>' : ''}
                </div></details>`;
        };
        const render = () => {
            const holiday = schedule.startsWith('holiday:');
            q('[data-schedule]').innerHTML = '<option value="">Base prices</option>' + periods().map(p => `<option value="${esc(keyOf(p))}">${esc(p.name)} · ${esc(p.start)} to ${esc(p.end)}</option>`).join('') + doc.holidays.map(p => `<option value="holiday:${esc(p.id)}">${esc(p.name)} · ${esc(p.start)} to ${esc(p.end)}</option>`).join('');
            q('[data-schedule]').value = schedule;
            q('[data-schedule-note]').textContent = schedule ? `Editing ${periodName() || 'dated'} prices. Changes below apply only to this schedule.` : 'Editing base prices. Use the schedule above to enter seasonal prices.';
            q('[data-base-heading]').textContent = holiday ? 'Event $' : 'Base $';
            host.querySelectorAll('[data-optional-price]').forEach(el => el.hidden = holiday);
            q('[data-rows]').innerHTML = doc.rows.map((r,i) => { const p=current(r)||{}; return `<tr data-row="${i}"><td data-label="Site category"><select data-category aria-label="Site category for rate ${i+1}">${choices(categories,r.match?.category)}</select><small data-row-count="${i}">${lastPreview ? siteCount(lastPreview.row_counts?.[r.id] || 0) : ''}</small></td><td data-label="Layout"><select data-layout aria-label="Layout for rate ${i+1}">${choices(layouts,r.match?.layout)}</select></td><td data-label="${holiday ? 'Event $' : 'Base $'}">${money(p,'price',i)}</td><td data-label="Weekend $" data-optional-price${holiday ? ' hidden' : ''}>${money(p,'weekend_price',i)}</td><td data-label="Holiday $" data-optional-price${holiday ? ' hidden' : ''}>${money(p,'holiday_price',i)}</td><td><button type="button" class="place-rate-card-remove" data-remove="${i}" aria-label="Remove rate ${i+1}">×</button></td></tr><tr data-details-for="${i}"><td colspan="6">${details(r,i)}</td></tr>`; }).join('');
            q('[data-empty]').hidden = !!doc.rows.length;
            q('[data-selection-warning]').hidden = !sites.length || !doc.rows.some(r => (r.match?.site_ids || []).some(id => !sites.some(s => s.id === id)));
            q('[data-periods]').innerHTML = periods().map(p => `<div class="place-rate-period"><div><strong>${esc(p.name)}</strong><span>${esc(p.start)} to ${esc(p.end)}${p.recurring ? ' · annually' : ''}</span></div><button type="button" data-edit-season="${esc(keyOf(p))}">Edit</button><button type="button" data-remove-season="${esc(keyOf(p))}" aria-label="Remove ${esc(p.name)} season">Remove</button></div>`).join('') + doc.holidays.map(p => `<div class="place-rate-period"><div><strong>${esc(p.name)}</strong><span>${esc(p.start)} to ${esc(p.end)}</span></div><button type="button" data-edit-holiday="${esc(p.id)}">Edit</button><button type="button" data-remove-holiday="${esc(p.id)}" aria-label="Remove ${esc(p.name)} holiday">Remove</button></div>`).join('');
        };
        const updateRow = e => {
            const tr = e.target.closest('[data-row], [data-details-for]'); if (!tr) return;
            const i = Number(tr.dataset.row ?? tr.dataset.detailsFor), row = doc.rows[i], el = e.target;
            row.match ||= {};
            if (el.dataset.price) {
                if (schedule.startsWith('holiday:')) row.holiday_prices = {...(row.holiday_prices || {}),[schedule.slice(8)]:el.value === '' ? null : Number(el.value)};
                else {
                    let p=current(row);
                    if (!p) { p={...periods().find(p => keyOf(p)===schedule),price:null,weekend_price:null,holiday_price:null}; row.seasons ||= []; row.seasons.push(p); }
                    p[el.dataset.price]=el.value === '' ? null : Number(el.value);
                }
            } else if (el.hasAttribute('data-category')) row.match.category=el.value;
            else if (el.hasAttribute('data-layout')) row.match.layout=el.value;
            else if (el.hasAttribute('data-site-id')) {
                const set=new Set(row.match.site_ids || []); const id=Number(el.dataset.siteId); el.checked ? set.add(id) : set.delete(id); row.match.site_ids=[...set];
                // Keep selected-site mode visible even when its last box is unchecked.
            } else if (el.dataset.detail) {
                const key=el.dataset.detail;
                if (key==='selection') {
                    tr.querySelector('[data-site-picker]').hidden=el.value !== 'selected';
                    row.match.site_selection=el.value === 'selected';
                    if (el.value !== 'selected') { row.match.site_ids=[]; tr.querySelectorAll('[data-site-id]').forEach(c=>c.checked=false); }
                } else if (['unit','name','source_url','verified_on','notes'].includes(key)) row[key]=el.value;
                else row.match[key]=el.value;
            } else if (el.hasAttribute('data-reviewed')) row.needs_review=!el.checked;
            else return;
            sync();
        };
        q('[data-rows]').addEventListener('input',e => {
            if (e.target.hasAttribute('data-site-search')) {
                const term=e.target.value.toLowerCase(); e.target.closest('[data-site-picker]').querySelectorAll('.place-rate-site-options label').forEach(l=>l.hidden=!l.textContent.toLowerCase().includes(term)); return;
            }
            if (!e.target.matches('select,input[type="checkbox"]')) updateRow(e);
        });
        q('[data-rows]').addEventListener('change',e => { if(e.target.matches('select,input[type="checkbox"]')) updateRow(e); });
        q('[data-rows]').addEventListener('click',e => { const b=e.target.closest('[data-remove]'); if(!b)return;doc.rows.splice(Number(b.dataset.remove),1);sync();render(); });
        q('[data-add]').addEventListener('click',()=>{const r=newRow(); if(schedule && !schedule.startsWith('holiday:')) r.seasons=[{...periods().find(p=>keyOf(p)===schedule),price:null,weekend_price:null,holiday_price:null}];doc.rows.push(r);sync();render();q('[data-rows]').querySelector('[data-row]:last-of-type [data-category]')?.focus();});
        const movePreviewDate = () => {
            const p = schedule.startsWith('holiday:') ? doc.holidays.find(h=>'holiday:'+h.id===schedule) : periods().find(p=>keyOf(p)===schedule);
            if(p) q('[data-night]').value=p.recurring ? `${new Date().getFullYear()}-${p.start}` : p.start;
        };
        q('[data-schedule]').addEventListener('change',e=>{schedule=e.target.value;movePreviewDate();render();queuePreview();});
        const periodEditor = (kind, existing=null) => {
            q('[data-period-editor]').innerHTML=`<div class="place-rate-period-form"><label class="place-rate-card-field"><span>${kind==='season'?'Season':'Holiday / event'} name</span><input data-new-name value="${esc(existing?.name || '')}"></label><label class="place-rate-card-field"><span>First night</span><input data-new-start type="date" value="${esc(existing ? (existing.recurring ? '2000-' : '')+existing.start : '')}"></label><label class="place-rate-card-field"><span>Last night</span><input data-new-end type="date" value="${esc(existing ? (existing.recurring ? '2000-' : '')+existing.end : '')}"></label><label class="place-rate-repeat"><input type="checkbox" data-new-recurring${existing?.recurring?' checked':''}>Repeat annually</label><div class="place-rate-actions"><button type="button" class="place-rate-cards-add" data-new-save>${existing?'Save dates':'Add '+kind}</button><button type="button" data-new-cancel>Cancel</button></div><p role="alert" data-period-error></p></div>`;
            const area=q('[data-period-editor]'); area.querySelector('[data-new-name]').focus();
            area.querySelector('[data-new-cancel]').addEventListener('click',()=>area.replaceChildren());
            area.querySelector('[data-new-save]').addEventListener('click',()=>{
                const name=area.querySelector('[data-new-name]').value.trim(), a=area.querySelector('[data-new-start]'), b=area.querySelector('[data-new-end]'), recurring=area.querySelector('[data-new-recurring]').checked;
                if(!name || !a.value || !b.value || !a.checkValidity() || !b.checkValidity() || (!recurring && a.value>b.value)){area.querySelector('[data-period-error]').textContent='Enter a name and valid first and last nights.';return;}
                const p={name,start:recurring?a.value.slice(5):a.value,end:recurring?b.value.slice(5):b.value,recurring};
                if(kind==='season'){
                    if(!doc.rows.length)doc.rows.push(newRow());
                    const old=existing?keyOf(existing):null;
                    doc.rows.forEach(r=>{r.seasons ||= [];const found=old?r.seasons.find(s=>keyOf(s)===old):null; if(found)Object.assign(found,p);else r.seasons.push({...p,price:null,weekend_price:null,holiday_price:null});});schedule=keyOf(p);
                }else{
                    if(existing)Object.assign(existing,p);else{p.id=`holiday-${Date.now()}`;doc.holidays.push(p);}schedule='holiday:'+(existing?.id || p.id);
                }
                area.replaceChildren();movePreviewDate();sync();render();status(`${name} selected. Enter its prices in the matrix above.`);
            });
        };
        q('[data-season-add]').addEventListener('click',()=>periodEditor('season'));
        q('[data-holiday-add]').addEventListener('click',()=>periodEditor('holiday'));
        q('[data-periods]').addEventListener('click',e=>{
            const el=e.target;
            if(el.dataset.editSeason)periodEditor('season',periods().find(p=>keyOf(p)===el.dataset.editSeason));
            if(el.dataset.editHoliday)periodEditor('holiday',doc.holidays.find(p=>p.id===el.dataset.editHoliday));
            if(el.dataset.removeSeason){doc.rows.forEach(r=>r.seasons=(r.seasons||[]).filter(p=>keyOf(p)!==el.dataset.removeSeason));schedule='';sync();render();}
            if(el.dataset.removeHoliday){doc.holidays=doc.holidays.filter(p=>p.id!==el.dataset.removeHoliday);doc.rows.forEach(r=>{if(r.holiday_prices)delete r.holiday_prices[el.dataset.removeHoliday];});schedule='';sync();render();}
        });
        const weekend=doc.weekend_days.slice().sort().join(',');q('[data-weekend]').value=['5,6','0,5,6','4,5,6'].includes(weekend)?weekend:'custom';
        q('[data-weekdays]').hidden=q('[data-weekend]').value!=='custom';
        q('[data-weekdays]').innerHTML=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map((d,i)=>`<label class="place-rate-weekday"><input type="checkbox" value="${i}"${doc.weekend_days.includes(i)?' checked':''}>${d}</label>`).join('');
        q('[data-weekend]').addEventListener('change',e=>{q('[data-weekdays]').hidden=e.target.value!=='custom';if(e.target.value!=='custom'){doc.weekend_days=e.target.value.split(',').map(Number);q('[data-weekdays]').querySelectorAll('input').forEach(c=>c.checked=doc.weekend_days.includes(Number(c.value)));sync();}});
        q('[data-weekdays]').addEventListener('change',()=>{doc.weekend_days=[...q('[data-weekdays]').querySelectorAll(':checked')].map(c=>Number(c.value));sync();});
        ['reservation_fee','extra_vehicle_fee','membership_fee','parking_entrance_fee','entrance_facility_fee','parking_fee'].forEach(key=>{const el=form?.querySelector(`[name="${key}"]`)?.closest('.contribution-field');if(el&&!el.contains(host))q('[data-fees]').append(el);});
        q('[data-preview-site]').innerHTML=sites.map(s=>`<option value="${s.id}">${esc(s.name)}</option>`).join('');q('[data-night]').value=localDate();
        q('[data-preview-site]').addEventListener('change',showSite);q('[data-night]').addEventListener('input',queuePreview);
        if(!admin){q('[data-preview-price]').textContent='Site prices will appear when this Place has Sites.';q('[data-preview-site]').disabled=true;q('[data-save-prices]').hidden=true;}
        const seasonPanel = document.querySelector('[data-admin-seasons-disclosure]');
        q('[data-booking-link]').hidden = !admin || !seasonPanel;
        host.addEventListener('click', event => {
            const link = event.target.closest('[data-season-booking]');
            if (!link || !seasonPanel) return;

            seasonPanel.open = true;
            const load = seasonPanel.querySelector('[data-season-builder-load]');
            if (!load) return;
            load.querySelector('[name="season_booking"]').value = '1';
            load.querySelector('[data-season-booking-hint]').hidden = false;
            const picker = load.querySelector('[data-season-targets]');
            picker.open = true;

            const row = link.closest('[data-extra-row]');
            const ids = doc.rows[Number(row?.dataset.extraRow)]?.match?.site_ids || [];
            if (!ids.length || ids.length > 250) return;
            const tokens = new Set(ids.map(id => 'site:campsite_record:' + id));
            const options = [...picker.querySelectorAll('[name="season_targets[]"]')];
            const selected = options.filter(option => tokens.has(option.value));
            if (selected.length !== tokens.size) return;

            options.forEach(option => { option.checked = tokens.has(option.value); });
            selected[0].dispatchEvent(new Event('change', { bubbles: true }));
        });
        q('[data-save-prices]').addEventListener('click',()=>{const save=form?.querySelector('[data-place-report-admin-save]');if(save){status('Saving prices with the Place Report…');save.click();}});
        window.addEventListener('llama:admin-place-report-saved',e=>{if(String(e.detail?.placeId || '')===String(placeId)){status('Prices saved.');queuePreview();}});
        render();if(!textarea.value.trim())textarea.value=JSON.stringify(doc);queuePreview();
    });
})();
