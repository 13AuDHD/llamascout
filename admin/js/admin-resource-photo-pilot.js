(() => {
    'use strict';
    const root = document.querySelector('[data-photo-pilot]');
    if (!root) return;
    const button = root.querySelector('[data-pilot-start]');
    const status = root.querySelector('[data-pilot-status]');
    const body = root.querySelector('[data-pilot-rows]');
    const summary = root.querySelector('[data-pilot-summary]');
    let running = false;
    async function request(action) {
        const res = await fetch('/resource-photo-pilot.php', {
            method:'POST', credentials:'same-origin', cache:'no-store',
            headers:{Accept:'application/json'},
            body:new URLSearchParams({csrf_token:root.dataset.csrf,action}),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Pilot request failed.');
        return data;
    }
    function cell(row,value) {
        const td = document.createElement('td');
        td.textContent = String(value); row.append(td); return td;
    }
    function preview(td,photo) {
        const div = document.createElement('div');
        const title = document.createElement(photo.url ? 'a' : 'span');
        title.textContent = photo.title;
        if (photo.url && photo.url.startsWith('https://')) {
            title.href = photo.url; title.target = '_blank'; title.rel = 'noopener noreferrer';
        }
        div.append(title);
        if (photo.preview && photo.preview.startsWith('https://')) {
            const img = document.createElement('img');
            img.src = photo.preview; img.alt = 'Unverified candidate';
            img.width = 160; img.loading = 'lazy';
            div.append(img);
        }
        td.append(div);
    }
    button.addEventListener('click',async () => {
        if (running) return;
        running = true; button.disabled = true; body.replaceChildren(); summary.replaceChildren();
        try {
            const run = await request('start');
            const names = new Map(run.sample.map(r => [String(r.id),r.name+' ('+r.resource_type+')']));
            const totals = Object.fromEntries(Object.keys(run.providers).map(k => [k,{checked:0,raw:0,eligible:0,errors:0}]));
            const total = run.sample.length * Object.keys(run.providers).length;
            let checked = 0;
            status.textContent = 'Testing '+run.sample.length+' actual Resources…';
            while (checked < total) {
                const data = await request('step');
                if (data.done) break;
                const t = totals[data.provider], result = data.result;
                t.checked++; checked++;
                const row = document.createElement('tr');
                cell(row,names.get(String(data.id)) || data.id);
                cell(row,run.providers[data.provider]);
                if (result.status === 'error') {
                    t.errors++; cell(row,'Error'); cell(row,'Not tested'); cell(row,result.message);
                } else {
                    if (result.raw > 0) t.raw++;
                    if (result.eligible > 0) t.eligible++;
                    cell(row,result.raw); cell(row,result.eligible);
                    const td = cell(row,'');
                    for (const photo of result.photos || []) preview(td,photo);
                    for (const rejected of result.rejected || []) {
                        const p = document.createElement('p');
                        p.textContent = 'Rejected: '+rejected.title+' ('+rejected.license+')'; td.append(p);
                    }
                }
                body.append(row);
                summary.textContent = Object.entries(totals).map(([key,v]) => run.providers[key]+': '+v.eligible+'/'+(v.checked-v.errors)+' with candidates; '+v.raw+' with raw hits; '+v.errors+' errors').join(' | ');
                status.textContent = checked+' of '+total+' source requests completed.';
                if (checked < total) await new Promise(resolve=>setTimeout(resolve,1800));
            }
            status.textContent = 'Test complete. Review candidate images for actual Resource relevance before choosing a provider.';
        } catch (error) { status.textContent = error.message+' No Resource data was changed.'; }
        finally { running = false; button.disabled = false; }
    });
})();
