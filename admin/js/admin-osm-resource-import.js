(() => {
    'use strict';
    const root = document.querySelector('[data-osm-import-controls]');
    if (!root) return;
    const resume = root.querySelector('[data-osm-resume]');
    const pause = root.querySelector('[data-osm-pause]');
    const status = root.querySelector('[data-osm-status]');
    const progress = root.querySelector('[data-osm-progress]');
    const rows = new Map([...document.querySelectorAll('[data-osm-region]')].map(row => [row.dataset.osmRegion,row]));
    let running = false;
    let pending = false;
    let timer = null;
    let retries = 0;
    function controls() {
        resume.disabled = running || pending;
        pause.disabled = !running;
    }
    function stop(message) {
        running = false;
        if (timer !== null) clearTimeout(timer);
        timer = null;
        status.textContent = message;
        controls();
    }
    function schedule(ms) {
        if (running) timer = setTimeout(step,ms);
    }
    async function step() {
        timer = null;
        if (!running || pending) return;
        pending = true;
        controls();
        try {
            const response = await fetch('/resource-map-data-step.php',{
                method:'POST',credentials:'same-origin',cache:'no-store',
                body:new URLSearchParams({csrf_token:root.dataset.csrf}),
                headers:{Accept:'application/json'}
            });
            let payload;
            try { payload = await response.json(); }
            catch (_) { throw new Error('Import response unavailable. Reload Admin or resume.'); }
            if (!response.ok || !payload.ok) {
                if (payload.retry && running && retries < 4) {
                    retries++;
                    status.textContent = payload.error + ' Retrying...';
                    schedule(5000 * retries);
                    return;
                }
                throw new Error(payload.error || 'Import failed. Resume or restart the affected state.');
            }
            retries = 0;
            if (payload.done) {
                progress.value = 100;
                stop('No more queued browser imports. Refresh status to view the updated inventory.');
                return;
            }
            const job = payload.job;
            progress.value = job.percent;
            const row = rows.get(job.region);
            if (row) {
                row.querySelector('[data-osm-job-status]').textContent =
                    job.status === 'complete' ? 'Complete (points)' : job.status + ' (' + job.percent + '%)';
                row.querySelector('[data-osm-received]').textContent = Number(job.rows).toLocaleString();
            }
            status.textContent = job.name + ': ' + (job.status === 'complete' ? 'complete' : job.percent + '% scanned')
                + ' · ' + Number(job.rows).toLocaleString() + ' service records. '
                + (job.message || '');
            if (!running) {
                stop('Paused after saving the current step. Resume when ready.');
                return;
            }
            schedule(2500);
        } catch (error) {
            stop(error.message + ' Previously imported data remains available.');
        } finally {
            pending = false;
            controls();
        }
    }
    function start() {
        if (running || pending) return;
        running = true;
        retries = 0;
        status.textContent = 'Starting the next queued state...';
        controls();
        step();
    }
    resume.addEventListener('click',start);
    pause.addEventListener('click',() => stop(pending
        ? 'Pausing after the current step finishes...' : 'Paused. Progress is saved.'));
    if (root.dataset.autoRun === '1') start();
    else controls();
})();
