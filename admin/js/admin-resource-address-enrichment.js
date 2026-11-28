(() => {
    'use strict';
    const root = document.querySelector('[data-address-enrichment]');
    if (!root) return;
    const start = root.querySelector('[data-address-start]');
    const pause = root.querySelector('[data-address-pause]');
    const status = root.querySelector('[data-address-status]');
    let running = false, pending = false, timer = null, checked = 0, fields = 0;
    function controls() { start.disabled = running || pending; pause.disabled = !running; }
    function stop(message) {
        running = false;
        if (timer !== null) clearTimeout(timer);
        timer = null; status.textContent = message; controls();
    }
    async function step() {
        timer = null;
        if (!running || pending) return;
        pending = true; controls();
        try {
            const response = await fetch('/resource-address-enrichment.php', {
                method:'POST', credentials:'same-origin', cache:'no-store',
                headers:{Accept:'application/json'},
                body:new URLSearchParams({action:'step',csrf_token:root.dataset.csrf,state:root.dataset.state}),
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Address lookup failed.');
            if (result.done) {
                stop(result.limit ? 'Daily bulk request limit reached. Resume after the next UTC day.' : 'All pending addresses checked. Refresh queue for remaining missing fields.');
                return;
            }
            checked += result.processed || 1; fields += result.field_count ?? result.applied.length;
            status.textContent = result.name + ': ' + result.status + '. ' + checked + ' checked, ' + fields + ' fields saved this session.';
            if (!running) { stop('Paused after saving the current result.'); return; }
            timer = setTimeout(step,result.processed ? 100 : 1500);
        } catch(error) { stop(error.message+' Previous results are saved.'); }
        finally { pending = false; controls(); }
    }
    start.addEventListener('click',()=>{ if (running || pending) return; running = true; checked = fields = 0; controls(); step(); });
    pause.addEventListener('click',()=>stop(pending?'Pausing after current lookup…':'Paused. Results saved.'));
    controls();
})();
