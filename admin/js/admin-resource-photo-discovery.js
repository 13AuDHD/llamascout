(() => {
    'use strict';
    const root = document.querySelector('[data-photo-discovery]');
    if (!root) return;
    const start = root.querySelector('[data-discovery-start]');
    const pause = root.querySelector('[data-discovery-pause]');
    const status = root.querySelector('[data-discovery-status]');
    let running = false, pending = false, timer = null, count = 0, attached = 0, review = 0, unusable = 0;
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
            const response = await fetch(root.dataset.endpoint || '/resource-photo-discovery-step.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json' },
                body: new URLSearchParams({ csrf_token: root.dataset.csrf, state: root.dataset.state }),
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Photo discovery unavailable.');
            if (result.limit && result.retry_after && running) {
                const wait = Math.max(5, Number(result.retry_after));
                status.textContent = 'KartaView hourly limit reached. Automatically resuming in '+Math.ceil(wait/60)+' min. Keep this page open.';
                timer = setTimeout(step,wait*1000); return;
            }
            if (result.done) { stop('All pending Resources in this selection have been searched. Refresh results.'); return; }
            count++;
            if (result.status === 'attached') attached++;
            if (result.status === 'review') review++;
            if (result.status === 'unusable') unusable++;
            status.textContent = result.name + ': ' + result.status + '. ' + count + ' searched, ' + attached + ' attached, ' + review + ' to review, ' + unusable + ' unusable this session.' + (result.detail ? ' Last image: ' + result.detail : '');
            if (!running) { stop('Paused after saving the current result.'); return; }
            timer = setTimeout(step, 3000);
        } catch (error) { stop(error.message + ' Previous results are saved.'); }
        finally { pending = false; controls(); }
    }
    start.addEventListener('click', () => {
        if (running || pending) return;
        running = true; count = attached = review = unusable = 0; controls(); step();
    });
    pause.addEventListener('click', () => stop(pending ? 'Pausing after the current search…' : 'Paused. Results saved.'));
    controls();
})();
