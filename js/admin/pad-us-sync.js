(() => {
    'use strict';

    const form =
        document.querySelector(
            '[data-pad-us-sync-form]'
        );

    if (!form) {
        return;
    }

    const stateSelect =
        form.querySelector(
            '[data-pad-us-state]'
        );

    const startButton =
        form.querySelector(
            '[data-pad-us-start]'
        );

    const stopButton =
        form.querySelector(
            '[data-pad-us-stop]'
        );

    const progressBox =
        form.querySelector(
            '[data-pad-us-progress]'
        );

    const progressTitle =
        form.querySelector(
            '[data-pad-us-progress-title]'
        );

    const progressBar =
        form.querySelector(
            '[data-pad-us-progress-bar]'
        );

    const progressText =
        form.querySelector(
            '[data-pad-us-progress-text]'
        );

    const errorBox =
        form.querySelector(
            '[data-pad-us-error]'
        );

    const successBox =
        form.querySelector(
            '[data-pad-us-success]'
        );

    const csrf =
        String(
            form.querySelector(
                '[name="csrf_token"]'
            )?.value
            || ''
        );

    const states = [
        'AL', 'AK', 'AZ', 'AR', 'CA',
        'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA',
        'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO',
        'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH',
        'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT',
        'VA', 'WA', 'WV', 'WI', 'WY',
    ];

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

        if (!value) {
            stopRequested = false;
        }
    };

    const postBatch = async (
        stateCode,
        runId = 0,
        offset = null
    ) => {
        const body =
            new URLSearchParams();

        body.set(
            'csrf_token',
            csrf
        );

        body.set(
            'state_code',
            stateCode
        );

        if (runId > 0) {
            body.set(
                'run_id',
                String(runId)
            );
        }

        if (
            offset !== null
            && Number.isFinite(
                Number(offset)
            )
        ) {
            body.set(
                'offset',
                String(offset)
            );
        }

        const response =
            await fetch(
                '/pad-us-sync-run.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded;charset=UTF-8',
                        'Accept':
                            'application/json',
                    },
                    body:
                        body.toString(),
                    credentials:
                        'same-origin',
                }
            );

        let payload = null;

        try {
            payload =
                await response.json();
        } catch (_) {
            throw new Error(
                'The PAD-US sync endpoint returned an unreadable response.'
            );
        }

        if (
            !response.ok
            || !payload?.ok
        ) {
            throw new Error(
                String(
                    payload?.error
                    || 'PAD-US synchronization failed.'
                )
            );
        }

        return payload.result;
    };

    const runState = async (
        stateCode,
        existingRunId = 0,
        allStateIndex = null
    ) => {
        let runId =
            existingRunId;

        let offset = null;

        while (true) {
            if (stopRequested) {
                return {
                    stopped: true,
                    stateCode,
                    runId,
                };
            }

            const result =
                await postBatch(
                    stateCode,
                    runId,
                    offset
                );

            runId =
                Number(
                    result.run_id
                    || runId
                    || 0
                );

            offset =
                Number(
                    result.next_offset
                    || 0
                );

            const sourceRows =
                Math.max(
                    0,
                    Number(
                        result.source_rows
                        || 0
                    )
                );

            const processed =
                Math.max(
                    0,
                    Number(
                        result.rows_processed
                        || 0
                    )
                );

            let percent = 0;

            if (sourceRows > 0) {
                percent =
                    Math.min(
                        100,
                        Math.round(
                            processed
                            / sourceRows
                            * 100
                        )
                    );
            }

            if (
                allStateIndex !== null
            ) {
                progressTitle.textContent =
                    'Synchronizing '
                    + stateCode
                    + ' ('
                    + (allStateIndex + 1)
                    + ' of '
                    + states.length
                    + ')';

                const overall =
                    (
                        allStateIndex
                        + (
                            sourceRows > 0
                                ? processed
                                    / sourceRows
                                : 0
                        )
                    )
                    / states.length
                    * 100;

                progressBar.value =
                    Math.min(
                        100,
                        overall
                    );
            } else {
                progressTitle.textContent =
                    'Synchronizing '
                    + stateCode;

                progressBar.value =
                    percent;
            }

            progressText.textContent =
                processed.toLocaleString()
                + ' of '
                + sourceRows.toLocaleString()
                + ' source rows, '
                + Number(
                    result.locations_created
                    || 0
                ).toLocaleString()
                + ' new named units, '
                + Number(
                    result.locations_updated
                    || 0
                ).toLocaleString()
                + ' units updated, '
                + Number(
                    result.warning_count
                    || 0
                ).toLocaleString()
                + ' warnings.';

            if (result.done) {
                return {
                    stopped: false,
                    stateCode,
                    runId,
                    result,
                };
            }
        }
    };

    const begin = async (
        selectedState,
        resumeRunId = 0
    ) => {
        if (running) {
            return;
        }

        errorBox.hidden = true;
        successBox.hidden = true;

        progressBox.hidden = false;
        progressBar.value = 0;

        stopRequested = false;
        setRunning(true);

        try {
            if (
                selectedState === 'ALL'
            ) {
                for (
                    let index = 0;
                    index < states.length;
                    index++
                ) {
                    if (stopRequested) {
                        break;
                    }

                    await runState(
                        states[index],
                        0,
                        index
                    );
                }

                if (stopRequested) {
                    showSuccess(
                        'Synchronization stopped after the current batch. Any incomplete state can be resumed from Recent PAD-US Runs.'
                    );
                } else {
                    progressBar.value = 100;

                    showSuccess(
                        'PAD-US synchronization completed for all 50 states. Reload this page to see the final run history.'
                    );
                }
            } else {
                const result =
                    await runState(
                        selectedState,
                        resumeRunId
                    );

                if (
                    result.stopped
                    || stopRequested
                ) {
                    showSuccess(
                        'Synchronization stopped after the current batch. Use Resume in Recent PAD-US Runs to continue.'
                    );
                } else {
                    progressBar.value = 100;

                    showSuccess(
                        selectedState
                        + ' PAD-US synchronization completed. Reload this page to refresh the history and unresolved issue count.'
                    );
                }
            }
        } catch (error) {
            showError(
                error instanceof Error
                    ? error.message
                    : 'PAD-US synchronization failed.'
            );
        } finally {
            setRunning(false);
        }
    };

    form.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const selected =
                String(
                    stateSelect.value
                    || ''
                );

            if (!selected) {
                showError(
                    'Choose a state or All 50 states.'
                );

                return;
            }

            begin(
                selected
            );
        }
    );

    stopButton.addEventListener(
        'click',
        () => {
            stopRequested = true;

            stopButton.disabled = true;

            progressText.textContent =
                'Stopping after the current batch finishes...';

            window.setTimeout(
                () => {
                    stopButton.disabled =
                        false;
                },
                1500
            );
        }
    );

    document
        .querySelectorAll(
            '[data-pad-us-resume]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const stateCode =
                        String(
                            button.dataset
                                .stateCode
                            || ''
                        );

                    const runId =
                        Number(
                            button.dataset
                                .runId
                            || 0
                        );

                    if (
                        !stateCode
                        || runId < 1
                    ) {
                        return;
                    }

                    stateSelect.value =
                        stateCode;

                    begin(
                        stateCode,
                        runId
                    );
                }
            );
        });
})();
