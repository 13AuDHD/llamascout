(() => {
    'use strict';

    const form = document.querySelector('[data-pad-us-sync-form]');
    if (!form) return;

    const stateSelect = form.querySelector('[data-pad-us-state]');
    const startButton = form.querySelector('[data-pad-us-start]');
    const stopButton = form.querySelector('[data-pad-us-stop]');
    const progressBox = form.querySelector('[data-pad-us-progress]');
    const progressTitle = form.querySelector('[data-pad-us-progress-title]');
    const progressBar = form.querySelector('[data-pad-us-progress-bar]');
    const progressText = form.querySelector('[data-pad-us-progress-text]');
    const errorBox = form.querySelector('[data-pad-us-error]');
    const successBox = form.querySelector('[data-pad-us-success]');
    const csrf = String(form.querySelector('[name="csrf_token"]')?.value || '');

    const states = [
        'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA',
        'HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
        'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
        'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
        'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'
    ];

    const batchDelayMs = 1200;
    const stateDelayMs = 5000;
    const retryDelays = [10000, 20000, 40000, 60000, 60000];

    let running = false;
    let stopRequested = false;

    const showError = (message) => {
        errorBox.hidden = false;
        errorBox.textContent = message;
        successBox.hidden = true;
        successBox.textContent = '';
    };

    const showSuccess = (message) => {
        successBox.hidden = false;
        successBox.textContent = message;
        errorBox.hidden = true;
        errorBox.textContent = '';
    };

    const setRunning = (value) => {
        running = value;
        startButton.disabled = value;
        stateSelect.disabled = value;
        stopButton.hidden = !value;
        if (!value) stopRequested = false;
    };

    const sleep = (ms) => new Promise(resolve => window.setTimeout(resolve, ms));

    const waitWithStatus = async (ms, message = '') => {
        let remaining = ms;
        while (remaining > 0 && !stopRequested) {
            if (message) {
                progressText.textContent =
                    message + ' ' + Math.ceil(remaining / 1000) + 's';
            }
            const step = Math.min(1000, remaining);
            await sleep(step);
            remaining -= step;
        }
    };

    const isRateLimited = (message) => {
        const text = String(message || '').toLowerCase();
        return text.includes('too many requests')
            || text.includes('rate limit')
            || text.includes('http 429');
    };

    const postBatch = async (stateCode, runId = 0, offset = null) => {
        const body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('state_code', stateCode);

        if (runId > 0) body.set('run_id', String(runId));
        if (offset !== null) body.set('offset', String(offset));

        const response = await fetch('/pad-us-sync-run.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'Accept': 'application/json'
            },
            body: body.toString(),
            credentials: 'same-origin'
        });

        let payload;
        try {
            payload = await response.json();
        } catch (_) {
            throw new Error('The PAD-US sync endpoint returned an unreadable response.');
        }

        if (!response.ok || !payload?.ok) {
            throw new Error(String(payload?.error || 'PAD-US synchronization failed.'));
        }

        return payload.result;
    };

    const postBatchWithRetry = async (stateCode, runId = 0, offset = null) => {
        for (let attempt = 0; ; attempt++) {
            try {
                return await postBatch(stateCode, runId, offset);
            } catch (error) {
                const message = error instanceof Error ? error.message : String(error);

                if (!isRateLimited(message) || attempt >= retryDelays.length) {
                    throw error;
                }

                await waitWithStatus(
                    retryDelays[attempt],
                    'PAD-US is rate limiting requests. Retrying automatically in'
                );

                if (stopRequested) {
                    throw new Error('Synchronization stopped.');
                }
            }
        }
    };

    const runState = async (stateCode, existingRunId = 0, allStateIndex = null) => {
        let runId = existingRunId;
        let offset = null;

        while (true) {
            if (stopRequested) return { stopped: true, stateCode, runId };

            const result = await postBatchWithRetry(stateCode, runId, offset);

            runId = Number(result.run_id || runId || 0);
            offset = Number(result.next_offset || 0);

            const sourceRows = Math.max(0, Number(result.source_rows || 0));
            const processed = Math.max(0, Number(result.rows_processed || 0));
            const percent = sourceRows > 0
                ? Math.min(100, Math.round(processed / sourceRows * 100))
                : 0;

            if (allStateIndex !== null) {
                progressTitle.textContent =
                    `Synchronizing ${stateCode} (${allStateIndex + 1} of ${states.length})`;

                progressBar.value = Math.min(
                    100,
                    (allStateIndex + (sourceRows > 0 ? processed / sourceRows : 0))
                    / states.length
                    * 100
                );
            } else {
                progressTitle.textContent = `Synchronizing ${stateCode}`;
                progressBar.value = percent;
            }

            progressText.textContent =
                `${processed.toLocaleString()} of ${sourceRows.toLocaleString()} PAD-US rows, `
                + `${Number(result.units_created || 0).toLocaleString()} new reference units, `
                + `${Number(result.units_updated || 0).toLocaleString()} updated, `
                + `${Number(result.rows_skipped || 0).toLocaleString()} skipped.`;

            if (result.done) return { stopped: false, stateCode, runId, result };

            await waitWithStatus(batchDelayMs);
        }
    };

    const begin = async (selectedState, resumeRunId = 0) => {
        if (running) return;

        errorBox.hidden = true;
        successBox.hidden = true;
        progressBox.hidden = false;
        progressBar.value = 0;
        stopRequested = false;
        setRunning(true);

        try {
            if (selectedState === 'ALL') {
                for (let index = 0; index < states.length; index++) {
                    if (stopRequested) break;

                    await runState(states[index], 0, index);

                    if (!stopRequested && index < states.length - 1) {
                        await waitWithStatus(
                            stateDelayMs,
                            'Waiting before the next state.'
                        );
                    }
                }

                if (stopRequested) {
                    showSuccess('Synchronization stopped.');
                } else {
                    progressBar.value = 100;
                    showSuccess('PAD-US reference synchronization completed for all 50 states.');
                }
            } else {
                const result = await runState(selectedState, resumeRunId);

                if (result.stopped || stopRequested) {
                    showSuccess('Synchronization stopped.');
                } else {
                    progressBar.value = 100;
                    showSuccess(`${selectedState} PAD-US reference synchronization completed.`);
                }
            }
        } catch (error) {
            const message = error instanceof Error
                ? error.message
                : 'PAD-US synchronization failed.';

            if (stopRequested && message === 'Synchronization stopped.') {
                showSuccess('Synchronization stopped.');
            } else {
                showError(message);
            }
        } finally {
            setRunning(false);
        }
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        const selected = String(stateSelect.value || '');

        if (!selected) {
            showError('Choose a state or All 50 states.');
            return;
        }

        begin(selected);
    });

    stopButton.addEventListener('click', () => {
        stopRequested = true;
        stopButton.disabled = true;
        progressText.textContent = 'Stopping...';

        window.setTimeout(() => {
            stopButton.disabled = false;
        }, 1500);
    });

    document.querySelectorAll('[data-pad-us-resume]').forEach(button => {
        button.addEventListener('click', () => {
            const stateCode = String(button.dataset.stateCode || '');
            const runId = Number(button.dataset.runId || 0);

            if (!stateCode || runId < 1) return;

            stateSelect.value = stateCode;
            begin(stateCode, runId);
        });
    });
})();
