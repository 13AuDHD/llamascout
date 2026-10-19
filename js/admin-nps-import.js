(() => {
    'use strict';

    const root = document.querySelector('[data-nps-import]');
    if (!root) return;
    const startButton = root.querySelector('[data-nps-start]');
    const pauseButton = root.querySelector('[data-nps-pause]');
    const message = root.querySelector('[data-nps-import-message]');
    const token = root.querySelector('[name="csrf_token"]');
    if (!startButton || !pauseButton || !message || !token || startButton.disabled) return;

    let summary;
    try {
        summary = JSON.parse(root.dataset.summary);
    } catch {
        message.textContent = 'Import status could not be loaded. Reload this page.';
        startButton.disabled = true;
        return;
    }
    let running = false;
    let pauseRequested = false;
    let wakeDelay = null;
    const states = {
        not_started: 'Not started', pending: 'Ready to resume',
        paused: 'Paused', error: 'Needs retry', complete: 'Complete'
    };
    const format = value => Number(value).toLocaleString();

    const delay = milliseconds => new Promise(resolve => {
        const timer = window.setTimeout(() => {
            wakeDelay = null;
            resolve();
        }, milliseconds);
        wakeDelay = () => {
            window.clearTimeout(timer);
            wakeDelay = null;
            resolve();
        };
    });

    function render(result) {
        if (result.summary) summary = result.summary;
        for (const item of summary.resources) {
            const row = Array.from(root.querySelectorAll('[data-nps-resource]'))
                .find(candidate => candidate.dataset.npsResource === item.resource);
            if (!row) continue;
            row.querySelector('[data-nps-count]').textContent = `${format(item.processed)} / ${
                item.total === null ? 'not fetched yet' : format(item.total)}`;
            row.querySelector('[data-nps-state]').textContent = states[item.status] || item.status;
            row.querySelector('[data-nps-error]').textContent = item.error || '';
            const progress = row.querySelector('[data-nps-progress]');
            progress.max = Math.max(1, item.total ?? item.processed);
            progress.value = item.status === 'complete'
                ? Math.max(1, item.processed) : item.processed;
        }
        for (const [resource, count] of Object.entries(result.counts || {})) {
            for (const element of document.querySelectorAll('[data-nps-cache-resource]')) {
                if (element.dataset.npsCacheResource === resource) element.textContent = format(count);
            }
        }
    }

    async function request(action, extra = {}) {
        const body = new URLSearchParams({ action, csrf_token: token.value, ...extra });
        let response;
        try {
            response = await window.fetch(root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json' }, body
            });
        } catch {
            throw new Error('The connection was interrupted. Reload this page and resume from the saved progress.');
        }
        if (response.redirected) {
            throw new Error('Your session needs to be reopened. Reload this page and sign in to resume.');
        }
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error('The server did not return import status. Reload this page to check the last saved batch.');
        }
        if (!response.ok || !result.ok) {
            throw new Error(result.message || 'Import stopped. Reload this page and resume from the last saved batch.');
        }
        return result;
    }

    async function command(action, extra = {}) {
        while (true) {
            const result = await request(action, extra);
            if (!result.busy) return result;
            message.textContent = pauseRequested
                ? 'Waiting for the current batch to finish before pausing.'
                : 'Waiting for the next NPS batch.';
            await delay(Math.max(1, Math.min(10, result.retry_after || 4)) * 1000);
            // Do not send another import request after a pause was requested.
            if (pauseRequested && action === 'import_next') {
                return { summary };
            }
        }
    }

    startButton.addEventListener('click', async () => {
        if (running) return;
        running = true;
        pauseRequested = false;
        startButton.disabled = true;
        pauseButton.disabled = false;
        message.textContent = 'Preparing the national NPS source import.';
        try {
            let result = await command('import_start', { refresh: summary.complete ? '1' : '0' });
            render(result);
            while (summary.pending && !pauseRequested) {
                const next = summary.resources.find(item => item.status === 'pending');
                message.textContent = `Importing ${next.label.toLowerCase()}; ${format(next.processed)} records read so far.`;
                result = await command('import_next');
                render(result);
                if (result.message) message.textContent = result.message;
                if (summary.pending && !pauseRequested) await delay(4000);
            }
            if (pauseRequested) {
                result = await command('import_pause');
                render(result);
                message.textContent = summary.complete
                    ? 'The national import completed before the pause.'
                    : 'Paused. You can close this page and resume later.';
            } else if (summary.complete) {
                message.textContent = 'National import complete for all six record types. Reload this page to inspect all imported parks.';
            } else if (summary.errors) {
                message.textContent = 'Available batches finished, but some record types need a retry. See their messages below. Saved progress is retained.';
            } else {
                message.textContent = result.message || 'The import is paused. Saved progress is retained.';
            }
        } catch (error) {
            message.textContent = error.message;
        } finally {
            running = false;
            pauseButton.disabled = true;
            startButton.disabled = false;
            startButton.textContent = summary.complete ? 'Refresh source records' : 'Resume national import';
        }
    });

    pauseButton.addEventListener('click', () => {
        if (!running) return;
        pauseRequested = true;
        pauseButton.disabled = true;
        message.textContent = 'Pausing after the current batch is saved.';
        if (wakeDelay) wakeDelay();
    });
})();
