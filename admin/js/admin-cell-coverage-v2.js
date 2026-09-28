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
    const bannerToggle = document.getElementById('cell-v2-banner-toggle');

    let polling = false;
    let snapshot = window.LLAMA_CELL_V2_INITIAL || null;

    const number = (value) => new Intl.NumberFormat().format(Number(value) || 0);

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
        if (value === 'importing') return ['is-importing', 'Importing'];
        if (value === 'error') return ['is-error', 'Error'];
        if (value === 'missing') return ['is-missing', 'Missing'];
        if (value === 'outdated') return ['is-outdated', 'Outdated'];
        if (value === 'processing' || value === 'process_queued') return ['is-working', 'Processing'];
        if (value === 'unpacking' || value === 'unpack_queued') return ['is-working', 'Unpacking'];
        if (value === 'cleanup' || value === 'cleanup_queued') return ['is-working', 'Cleanup'];
        return ['is-working', 'Preparing'];
    };

    const renderWorkers = (data) => {
        const configured = data.worker_config || {};
        const workers = Array.isArray(data.workers) ? data.workers : [];
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
                worker.role === role && ['starting', 'working'].includes(worker.status)
            ).length;
            return `${labels[role]} ${active}/${Number(configured[role]) || 0}`;
        });

        workersNode.textContent = parts.join(' · ');

        const queues = data.queues || {};
        const queueParts = Object.entries(queues)
            .filter(([, total]) => Number(total) > 0)
            .map(([stage, total]) => `${stage.replaceAll('_', ' ')}: ${number(total)}`);

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
        statusNode.textContent = String(data.sync_status || 'idle')
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
        progressNode.max = total;
        progressNode.value = Math.min(complete, total);

        if (run) {
            const percent = Math.round((complete / total) * 100);
            progressText.textContent = `${number(complete)} / ${number(total)} complete · ${percent}%`;
        } else {
            progressText.textContent = 'No V2 sync has been started';
        }

        renderWorkers(data);
        renderLog(data.events);
    };

    const setMessage = (text, isError = false) => {
        messageNode.textContent = text || '';
        messageNode.classList.toggle('is-error', Boolean(isError));
    };

    const poll = async () => {
        if (polling || document.hidden) return;
        polling = true;

        try {
            const data = await post('status');
            render(data.snapshot);
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
    window.setInterval(poll, 2000);
})();
