(() => {
    'use strict';
    const root = document.querySelector('[data-enrichment-controls]');
    if (!root) return;
    const start = root.querySelector('[data-enrichment-start]');
    const pause = root.querySelector('[data-enrichment-pause]');
    const restart = root.querySelector('[data-enrichment-restart]');
    const region = root.querySelector('[data-enrichment-region]');
    const status = root.querySelector('[data-enrichment-status]');
    const progress = root.querySelector('[data-enrichment-progress]');
    let running = false, pending = false, timer = null, retries = 0, selected = '', matches = 0;
    function controls() {
        start.disabled = running || pending;
        pause.disabled = !running;
        restart.disabled = running || pending || !region.value;
        region.disabled = running || pending;
    }
    function stop(message) {
        running = false;
        if (timer !== null) clearTimeout(timer);
        timer = null;
        status.textContent = message;
        controls();
    }
    async function request(action) {
        const response = await fetch('/resource-enrichment-step.php', {
            method: 'POST', credentials: 'same-origin', cache: 'no-store',
            headers: { Accept: 'application/json' },
            body: new URLSearchParams({ csrf_token: root.dataset.csrf, region: selected, action }),
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            if (data.retry) return data;
            throw new Error(data.error || 'Enrichment request failed.');
        }
        return data;
    }
    function schedule(ms) { if (running) timer = setTimeout(step, ms); }
    async function step() {
        timer = null;
        if (!running || pending) return;
        pending = true; controls();
        try {
            const data = await request('step');
            if (data.retry) {
                if (++retries > 4) throw new Error(data.error);
                status.textContent = data.error + ' Retrying…';
                schedule(5000 * retries);
                return;
            }
            retries = 0;
            if (data.done) {
                progress.value = 100;
                stop('Selected scans complete. Refresh totals to see matched and enriched Resources.');
                return;
            }
            matches += Number(data.matches);
            progress.value = data.percent;
            status.textContent = data.region + ': ' + data.percent + '% scanned. ' + matches.toLocaleString() + ' source matches this session.';
            if (!running) { stop('Paused after saving the current batch.'); return; }
            schedule(1500);
        } catch (error) { stop(error.message + ' Previously committed batches are saved.'); }
        finally { pending = false; controls(); }
    }
    start.addEventListener('click', () => {
        if (running || pending) return;
        selected = region.value; matches = 0; retries = 0; running = true;
        status.textContent = 'Starting or resuming selected state scans…'; controls(); step();
    });
    pause.addEventListener('click', () => stop(pending ? 'Pausing after current batch…' : 'Paused. Progress is saved.'));
    restart.addEventListener('click', async () => {
        if (running || pending || !region.value) return;
        selected = region.value; pending = true; controls();
        try {
            const data = await request('restart');
            if (!data.ok) throw new Error(data.error);
            progress.value = 0;
            status.textContent = 'State scan reset. Existing enrichment is preserved. Click Enrich / resume.';
        } catch (error) { status.textContent = error.message; }
        finally { pending = false; controls(); }
    });
    region.addEventListener('change', controls);
    controls();
})();
