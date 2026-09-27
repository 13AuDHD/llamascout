(() => {
    'use strict';

    const box =
        document.getElementById(
            'cell-sync-progress'
        );

    const startButton =
        document.getElementById(
            'cell-sync-start'
        );

    const resumeButton =
        document.getElementById(
            'cell-sync-resume'
        );

    if (!box || !startButton) {
        return;
    }

    const endpoint =
        box.dataset.endpoint
        || '/cell-coverage-sync.php';

    const csrf =
        box.dataset.csrf
        || '';

    const message =
        document.getElementById(
            'cell-sync-message'
        );

    const count =
        document.getElementById(
            'cell-sync-dataset-count'
        );

    const progress =
        document.getElementById(
            'cell-sync-progress-bar'
        );

    const current =
        document.getElementById(
            'cell-sync-current'
        );

    const rowProgress =
        document.getElementById(
            'cell-sync-row-progress'
        );

    const errorNode =
        document.getElementById(
            'cell-sync-error'
        );

    const summaryStatus =
        document.getElementById(
            'cell-sync-summary-status'
        );

    let running = false;
    let lastSync = null;


    function number(value) {
        return (
            Number(value) || 0
        ).toLocaleString();
    }


    function bytes(value) {
        const amount =
            Math.max(
                0,
                Number(value) || 0
            );

        if (amount >= 1073741824) {
            return `${(amount / 1073741824).toFixed(1)} GB`;
        }

        if (amount >= 1048576) {
            return `${(amount / 1048576).toFixed(1)} MB`;
        }

        if (amount >= 1024) {
            return `${(amount / 1024).toFixed(1)} KB`;
        }

        return `${number(amount)} B`;
    }


    function sleep(milliseconds) {
        return new Promise(
            (resolve) =>
                window.setTimeout(
                    resolve,
                    milliseconds
                )
        );
    }


    async function requestOnce(action) {
        const payload =
            new FormData();

        payload.set(
            'csrf_token',
            csrf
        );

        payload.set(
            'action',
            action
        );

        const response =
            await fetch(
                endpoint,
                {
                    method: 'POST',
                    credentials:
                        'same-origin',
                    cache:
                        'no-store',
                    body:
                        payload
                }
            );

        const raw =
            await response.text();

        let data = null;

        try {
            data =
                JSON.parse(raw);
        } catch (error) {
            throw new Error(
                `Sync endpoint returned HTTP ${response.status} instead of JSON.`
            );
        }

        if (
            !response.ok
            || data?.ok !== true
        ) {
            throw new Error(
                data?.error
                || 'FCC sync request failed.'
            );
        }

        return data.sync;
    }


    async function request(
        action,
        attempts = 4
    ) {
        let lastError = null;

        for (
            let attempt = 1;
            attempt <= attempts;
            attempt++
        ) {
            try {
                return await requestOnce(
                    action
                );

            } catch (error) {
                lastError = error;

                if (attempt < attempts) {
                    await sleep(
                        Math.min(
                            4000,
                            750 * attempt
                        )
                    );
                }
            }
        }

        throw lastError
            || new Error(
                'FCC sync request failed.'
            );
    }


    function render(sync) {
        if (!sync) {
            return;
        }

        lastSync = sync;
        box.hidden = false;

        if (message) {
            message.textContent =
                sync.message
                || 'Working...';
        }

        if (count) {
            count.textContent =
                `${number(sync.completed)} / ${number(sync.total)}`;
        }

        if (progress) {
            progress.max =
                Math.max(
                    1,
                    Number(sync.total)
                    || 1
                );

            progress.value =
                Math.min(
                    Number(sync.completed)
                    || 0,
                    progress.max
                );
        }

        if (summaryStatus) {
            const status =
                String(
                    sync.status
                    || 'idle'
                );

            summaryStatus.textContent =
                status.charAt(0)
                    .toUpperCase()
                + status.slice(1);
        }

        if (current) {
            if (sync.current) {
                current.textContent =
                    [
                        sync.current.state_name,
                        sync.current.provider_label,
                        String(
                            sync.current.technology
                            || ''
                        ).toUpperCase(),
                        sync.phase
                            ? `(${sync.phase})`
                            : ''
                    ]
                        .filter(Boolean)
                        .join(' \u00B7 ');
            } else {
                current.textContent = '';
            }
        }

        if (rowProgress) {
            const downloadTotal =
                Number(
                    sync.download_total_bytes
                ) || 0;

            const downloaded =
                Number(
                    sync.downloaded_bytes
                ) || 0;

            const retryAfterMs =
                Math.max(
                    0,
                    Number(
                        sync.retry_after_ms
                    ) || 0
                );

            if (
                sync.phase === 'download'
                && downloadTotal > 0
                && downloaded >= downloadTotal
            ) {
                rowProgress.textContent =
                    sync.download_worker_active
                        ? 'Download complete. Finalizing the file...'
                        : 'Download complete. Preparing the next step...';

            } else if (
                sync.phase === 'download'
                && downloaded > 0
            ) {
                rowProgress.textContent =
                    downloadTotal > 0
                        ? `${bytes(downloaded)} of ${bytes(downloadTotal)} downloaded`
                        : `${bytes(downloaded)} downloaded`;

            } else if (
                sync.phase === 'download'
                && retryAfterMs > 0
            ) {
                rowProgress.textContent =
                    `Retrying this FCC file in about ${Math.max(1, Math.ceil(retryAfterMs / 1000))} seconds`;

            } else if (
                sync.phase === 'download'
            ) {
                rowProgress.textContent =
                    sync.download_worker_active
                        ? 'Downloading in the background...'
                        : 'Preparing the download...';

            } else if (
                sync.phase === 'import'
            ) {
                const total =
                    Number(
                        sync.current_total_rows
                    ) || 0;

                const imported =
                    Number(
                        sync.current_imported_rows
                    ) || 0;

                rowProgress.textContent =
                    total > 0
                        ? `${number(imported)} of ${number(total)} source rows processed`
                        : 'Preparing the downloaded file for import...';

            } else {
                rowProgress.textContent = '';
            }
        }

        if (errorNode) {
            const error =
                String(
                    sync.error
                    || ''
                );

            errorNode.textContent =
                error;

            errorNode.hidden =
                error === '';
        }

        if (resumeButton) {
            resumeButton.hidden =
                ![
                    'running',
                    'error'
                ].includes(
                    String(
                        sync.status
                        || ''
                    )
                );
        }
    }


    async function pollStatus() {
        const sync =
            await request(
                'status'
            );

        render(sync);

        return sync;
    }


    async function runLoop(initialAction) {
        if (running) {
            return;
        }

        running = true;
        startButton.disabled = true;

        if (resumeButton) {
            resumeButton.disabled = true;
        }

        try {
            let sync =
                await request(
                    initialAction
                );

            render(sync);

            while (
                sync
                && sync.status
                    === 'running'
            ) {
                if (
                    sync.phase
                    === 'download'
                ) {
                    const retryAfter =
                        Math.max(
                            0,
                            Number(
                                sync.retry_after_ms
                            ) || 0
                        );

                    if (retryAfter > 0) {
                        await sleep(
                            Math.min(
                                retryAfter,
                                5000
                            )
                        );

                        sync =
                            await pollStatus();

                        continue;
                    }

                    if (
                        !sync.download_worker_active
                    ) {
                        /*
                         * Launch once. If Safari loses this short
                         * response, do not stop the sync. The
                         * LiteSpeed noabort rule lets the server job
                         * continue, so recover by polling status.
                         */
                        try {
                            sync =
                                await requestOnce(
                                    'download'
                                );

                            render(sync);

                        } catch (error) {
                            await sleep(1500);

                            sync =
                                await pollStatus();
                        }
                    }

                    await sleep(2000);

                    sync =
                        await pollStatus();

                    continue;
                }

                if (
                    sync.phase
                    === 'import'
                ) {
                    sync =
                        await request(
                            'step'
                        );

                    render(sync);

                    await sleep(200);

                    continue;
                }

                sync =
                    await request(
                        'step'
                    );

                render(sync);

                await sleep(250);
            }

            if (
                sync?.status
                === 'complete'
            ) {
                window.setTimeout(
                    () => {
                        window.location.reload();
                    },
                    900
                );
            }

        } catch (error) {
            if (errorNode) {
                errorNode.textContent =
                    error?.message
                    || 'FCC sync paused because the server could not be reached. Reloading the page will continue from the saved state.';

                errorNode.hidden =
                    false;
            }

        } finally {
            running = false;
            startButton.disabled = false;

            if (resumeButton) {
                resumeButton.disabled =
                    false;
            }
        }
    }


    function continueRunningSync() {
        if (running) {
            return;
        }

        const status =
            String(
                summaryStatus?.textContent
                || ''
            )
                .trim()
                .toLowerCase();

        if (status === 'running') {
            runLoop('status');
        }
    }


    startButton.addEventListener(
        'click',
        () => {
            runLoop('plan');
        }
    );

    resumeButton?.addEventListener(
        'click',
        () => {
            runLoop('resume');
        }
    );

    /*
     * A running sync should continue automatically after a page
     * reload. Returning to the Safari tab also restarts polling if
     * iPadOS suspended the page in the background.
     */
    window.addEventListener(
        'pageshow',
        continueRunningSync
    );

    document.addEventListener(
        'visibilitychange',
        () => {
            if (!document.hidden) {
                continueRunningSync();
            }
        }
    );

    continueRunningSync();
})();
