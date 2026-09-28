(() => {
    'use strict';

    const root = document.getElementById('cell-v2-root');
    if (!root) return;

    const endpoint = root.dataset.endpoint || '/cell-coverage-v2-api.php';
    const csrf = root.dataset.csrf || '';

    const latestNode = document.getElementById('cell-v2-fcc-latest');
    const cellsNode = document.getElementById('cell-v2-coverage-cells');
    const dbNode = document.getElementById('cell-v2-db-size');
    const statusNode = document.getElementById('cell-v2-sync-status');
    const messageNode = document.getElementById('cell-v2-message');
    const progressNode = document.getElementById('cell-v2-progress');
    const progressText = document.getElementById('cell-v2-progress-text');
    const workersNode = document.getElementById('cell-v2-workers');
    const queuesNode = document.getElementById('cell-v2-queues');
    const completenessNode = document.getElementById('cell-v2-completeness');
    const logNode = document.getElementById('cell-v2-log');
    const latestButton = document.getElementById('cell-v2-latest-button');
    const syncButton = document.getElementById('cell-v2-sync-button');
    const errorsButton = document.getElementById('cell-v2-errors-button');
    const bannerToggle = document.getElementById('cell-v2-banner-toggle');

    let polling = false;
    let snapshot = window.LLAMA_CELL_V2_INITIAL || null;
    const startingWorkers = new Set();

    const number = (value) => new Intl.NumberFormat().format(Number(value) || 0);

    const dateTimeMs = (value) => {
        const raw = String(value || '').trim();
        if (!raw) return 0;
        const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T') + 'Z';
        const stamp = Date.parse(normalized);
        return Number.isFinite(stamp) ? stamp : 0;
    };

    const duration = (seconds) => {
        let remaining = Math.max(0, Math.round(Number(seconds) || 0));
        const days = Math.floor(remaining / 86400);
        remaining %= 86400;
        const hours = Math.floor(remaining / 3600);
        remaining %= 3600;
        const minutes = Math.floor(remaining / 60);
        const parts = [];
        if (days) parts.push(`${days}d`);
        if (hours || days) parts.push(`${hours}h`);
        parts.push(`${minutes}m`);
        return parts.join(' ');
    };

    const bytes = (value) => {
        const amount = Math.max(0, Number(value) || 0);
        if (amount >= 1073741824) return `${(amount / 1073741824).toFixed(1)} GB`;
        if (amount >= 1048576) return `${(amount / 1048576).toFixed(1)} MB`;
        if (amount >= 1024) return `${(amount / 1024).toFixed(1)} KB`;
        return `${amount} B`;
    };

    const post = async (action, extra = {}) => {
        const body = new URLSearchParams({
            action,
            csrf_token: csrf,
            ...extra,
        });

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            credentials: 'same-origin',
            body: body.toString(),
        });

        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error(`V2 returned HTTP ${response.status} without valid JSON.`);
        }

        if (!response.ok || !data.ok) {
            throw new Error(data.error || `V2 request failed with HTTP ${response.status}.`);
        }

        return data;
    };

    const pillInfo = (state) => {
        const value = String(state || 'missing');

        if (value === 'current') return ['is-current', 'Current'];
        if (value === 'downloading') return ['is-downloading', 'Downloading'];
        if (value === 'importing' || value === 'import_queued' || value === 'import_waiting') {
            return ['is-importing', 'Importing'];
        }
        if (value === 'error') return ['is-error', 'Error'];
        if (value === 'missing') return ['is-missing', 'Missing'];
        if (value === 'outdated') return ['is-outdated', 'Outdated'];
        if (value === 'processing' || value === 'process_queued' || value === 'process_waiting') {
            return ['is-working', 'Processing'];
        }
        if (value === 'unpacking' || value === 'unpack_queued' || value === 'unpack_waiting') {
            return ['is-working', 'Unpacking'];
        }
        if (value === 'cleanup' || value === 'cleanup_queued' || value === 'cleanup_waiting') {
            return ['is-working', 'Cleanup'];
        }
        return ['is-working', 'Preparing'];
    };

    const heartbeatTime = (worker) => {
        const raw = String(worker?.heartbeat_at || '').trim();
        if (!raw) return 0;
        const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T') + 'Z';
        const stamp = Date.parse(normalized);
        return Number.isFinite(stamp) ? stamp : 0;
    };

    const workerAlive = (worker) => {
        if (!worker || !['starting', 'working', 'waiting'].includes(String(worker.status || ''))) {
            return false;
        }

        const heartbeat = heartbeatTime(worker);
        if (!heartbeat) return false;

        return (Date.now() - heartbeat) < 45000;
    };

    const renderWorkers = (data) => {
        const configured = data.worker_config || {};
        const workers = Array.isArray(data.workers) ? data.workers : [];
        const runId = Number(data.run?.id || 0);
        const roles = ['download', 'process', 'unpack', 'import', 'cleanup'];
        const labels = {
            download: 'Download',
            process: 'Process',
            unpack: 'Unpack',
            import: 'Import',
            cleanup: 'Cleanup',
        };

        const parts = roles.map((role) => {
            const active = workers.filter((worker) =>
                Number(worker.run_id || 0) === runId
                && worker.role === role
                && workerAlive(worker)
            ).length;
            return `${labels[role]} ${active}/${Number(configured[role]) || 0}`;
        });

        workersNode.textContent = parts.join(' · ');

        const queues = data.queues || {};
        const queueParts = Object.entries(queues)
            .filter(([, total]) => Number(total) > 0)
            .map(([stage, total]) => {
                const label = stage
                    .replaceAll('_', ' ')
                    .replace(/\b\w/g, (char) => char.toUpperCase());
                return `${label}: ${number(total)}`;
            });

        queuesNode.textContent = queueParts.length
            ? queueParts.join(' · ')
            : 'No queued V2 jobs';
    };

    const renderLog = (events) => {
        logNode.replaceChildren();
        const rows = Array.isArray(events) ? events : [];

        if (!rows.length) {
            const empty = document.createElement('p');
            empty.className = 'cell-v2-log-empty';
            empty.textContent = 'No V2 activity yet. Use FCC Latest to run the first 306-slot catalog audit.';
            logNode.append(empty);
            return;
        }

        for (const event of rows) {
            const row = document.createElement('div');
            row.className = `cell-v2-log-row is-${event.level || 'info'}`;

            const time = document.createElement('time');
            time.textContent = String(event.created_at || '');

            const message = document.createElement('span');
            message.textContent = String(event.message || '');

            row.append(time, message);
            logNode.append(row);
        }
    };

    const render = (data) => {
        if (!data) return;
        snapshot = data;

        latestNode.textContent = data.fcc_latest || 'Not checked';
        cellsNode.textContent = number(data.coverage_cells);
        dbNode.textContent = bytes(data.database_bytes);

        const runStatus = String(data.sync_status || 'idle');
        statusNode.textContent = runStatus
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());

        if (bannerToggle) {
            bannerToggle.checked = Boolean(data.banner_enabled);
        }

        const slots = Array.isArray(data.slots) ? data.slots : [];
        for (const slot of slots) {
            const node = root.querySelector(
                `[data-cell-v2-slot="${CSS.escape(String(slot.slot_key || ''))}"]`
            );
            if (!node) continue;

            const [className, label] = pillInfo(slot.display_state);
            node.className = `cell-v2-pill ${className}`;
            node.textContent = label;
            node.title = String(slot.last_error || slot.last_diagnostic || '');
        }

        const counts = data.slot_counts || {};
        completenessNode.textContent = [
            `${number(counts.current)} Current`,
            `${number(counts.error)} Error`,
            `${number(counts.missing)} Missing`,
            `${number(counts.outdated)} Outdated`,
        ].join(' · ');

        const run = data.run || null;
        const total = run ? Math.max(1, Number(run.expected_jobs) || 306) : 306;
        const complete = run ? Math.max(0, Number(run.completed_jobs) || 0) : 0;
        const errors = run ? Math.max(0, Number(run.error_jobs) || 0) : 0;
        const missing = run ? Math.max(0, Number(run.missing_jobs) || 0) : 0;
        const finished = Math.min(total, complete + errors + missing);
        progressNode.max = total;
        progressNode.value = finished;

        if (run) {
            const percent = Math.round((finished / total) * 100);
            const work = data.work_progress || {};
            const workTotal = Math.max(0, Number(work.work_total) || 0);
            const workFinished = Math.max(0, Number(work.work_complete) || 0)
                + Math.max(0, Number(work.work_errors) || 0);
            const started = dateTimeMs(run.started_at);
            const elapsedSeconds = started > 0
                ? Math.max(0, (Date.now() - started) / 1000)
                : 0;
            let timing = '';

            if (started > 0 && ['planning', 'running'].includes(runStatus)) {
                if (workFinished > 0 && workTotal > workFinished) {
                    const etaSeconds = (elapsedSeconds / workFinished)
                        * (workTotal - workFinished);
                    timing = ` · Elapsed ${duration(elapsedSeconds)} · ETA ${duration(etaSeconds)}`;
                } else {
                    timing = ` · Elapsed ${duration(elapsedSeconds)} · ETA estimating`;
                }
            } else if (started > 0) {
                const ended = dateTimeMs(run.completed_at) || Date.now();
                timing = ` · Time ${duration(Math.max(0, (ended - started) / 1000))}`;
            }

            const progressParts = [
                `${number(complete)} / ${number(total)} current`,
            ];

            if (errors > 0) {
                progressParts.push(`${number(errors)} error${errors === 1 ? '' : 's'}`);
            }

            if (missing > 0) {
                progressParts.push(`${number(missing)} missing`);
            }

            progressParts.push(`${percent}% checked`);
            progressText.textContent = progressParts.join(' · ') + timing;
        } else {
            progressText.textContent = 'No V2 sync has been started';
        }

        if (syncButton) {
            syncButton.disabled = ['planning', 'running'].includes(runStatus);
        }

        if (errorsButton) {
            errorsButton.disabled = errors <= 0 && missing <= 0;
        }

        renderWorkers(data);
        renderLog(data.events);
    };

    const setMessage = (text, isError = false) => {
        messageNode.textContent = text || '';
        messageNode.classList.toggle('is-error', Boolean(isError));
    };

    const startWorker = async (runId, role, workerSlot, quiet = true) => {
        const key = `${runId}:${role}:${workerSlot}`;
        if (startingWorkers.has(key)) return;

        startingWorkers.add(key);

        try {
            await post('worker', {
                run_id: String(runId),
                role,
                worker_slot: String(workerSlot),
            });
        } catch (error) {
            if (!quiet) {
                setMessage(error.message || `Could not start ${role} worker ${workerSlot}.`, true);
            }
        } finally {
            window.setTimeout(() => startingWorkers.delete(key), 2500);
        }
    };

    const ensureWorkers = async (data, quiet = true) => {
        if (!data || document.hidden) return;

        const run = data.run || null;
        if (!run || String(run.status || '') !== 'running') return;

        const runId = Number(run.id || 0);
        if (!runId) return;

        const configured = data.worker_config || {};
        const workers = Array.isArray(data.workers) ? data.workers : [];
        const starts = [];

        for (const role of ['download', 'process', 'unpack', 'import', 'cleanup']) {
            const limit = Math.max(0, Number(configured[role]) || 0);

            for (let slot = 1; slot <= limit; slot++) {
                const existing = workers.find((worker) =>
                    Number(worker.run_id || 0) === runId
                    && worker.role === role
                    && Number(worker.worker_slot || 0) === slot
                );

                if (!workerAlive(existing)) {
                    starts.push(startWorker(runId, role, slot, quiet));
                }
            }
        }

        if (starts.length) {
            await Promise.allSettled(starts);
        }
    };

    const poll = async () => {
        if (polling || document.hidden) return;
        polling = true;

        try {
            const data = await post('status');
            render(data.snapshot);
            await ensureWorkers(data.snapshot, true);
        } catch (error) {
            setMessage(error.message || 'Unable to refresh V2 status.', true);
        } finally {
            polling = false;
        }
    };

    latestButton?.addEventListener('click', async () => {
        latestButton.disabled = true;
        setMessage('Checking the FCC latest filing and auditing all 306 expected datasets...');

        try {
            const data = await post('latest');
            render(data.snapshot);

            const counts = data.check?.counts || {};
            setMessage(
                `FCC Latest: ${data.check?.as_of_date || 'unknown'}. `
                + `${number(counts.catalog_available)} of 306 expected datasets recognized; `
                + `${number(counts.catalog_missing)} require investigation.`
            );
        } catch (error) {
            setMessage(error.message || 'FCC Latest check failed.', true);
        } finally {
            latestButton.disabled = false;
        }
    });

    syncButton?.addEventListener('click', async () => {
        syncButton.disabled = true;
        setMessage('Building the V2 sync run and starting the background factory...');

        try {
            const data = await post('sync');
            render(data.snapshot);
            await ensureWorkers(data.snapshot, false);

            const run = data.snapshot?.run || {};
            const already = Boolean(data.run_result?.already_running);
            setMessage(
                already
                    ? `V2 run ${run.id || ''} was already running. Missing workers have been restarted.`
                    : `V2 run ${run.id || ''} started. The background workers now own the sync; this page may be closed.`
            );
        } catch (error) {
            setMessage(error.message || 'V2 sync could not be started.', true);
        } finally {
            if (snapshot) render(snapshot);
        }
    });

    errorsButton?.addEventListener('click', async () => {
        errorsButton.disabled = true;
        setMessage('Checking failed, stale, and unresolved V2 datasets...');

        try {
            const data = await post('check_errors');
            render(data.snapshot);
            await ensureWorkers(data.snapshot, false);

            const result = data.recovery || {};
            setMessage(
                `Recovered ${number(result.recovered)} dataset(s), `
                + `requeued ${number(result.stale_requeued)} stale worker assignment(s), `
                + `${number(result.still_missing)} FCC catalog slot(s) remain unresolved.`
            );
        } catch (error) {
            setMessage(error.message || 'V2 error recovery failed.', true);
        } finally {
            if (snapshot) render(snapshot);
        }
    });

    bannerToggle?.addEventListener('change', async () => {
        bannerToggle.disabled = true;

        try {
            const data = await post('toggle_banner', {
                enabled: bannerToggle.checked ? '1' : '0',
            });
            render(data.snapshot);
            setMessage(
                data.snapshot.banner_enabled
                    ? 'Map update banner setting enabled.'
                    : 'Map update banner setting disabled.'
            );
        } catch (error) {
            bannerToggle.checked = !bannerToggle.checked;
            setMessage(error.message || 'Could not change the banner setting.', true);
        } finally {
            bannerToggle.disabled = false;
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });

    render(snapshot);
    ensureWorkers(snapshot, true);
    window.setInterval(poll, 2000);
})();
