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


    function number(value) {
        return (
            Number(value) || 0
        ).toLocaleString();
    }


    async function request(action) {
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

        const data =
            await response.json();

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


    function render(sync) {
        if (!sync) {
            return;
        }

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
                        .join(' · ');
            } else {
                current.textContent = '';
            }
        }

        if (rowProgress) {
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
                    : '';
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
                sync =
                    await request(
                        'step'
                    );

                render(sync);

                /*
                 * Yield briefly so Safari can repaint the progress
                 * UI between local import batches.
                 */
                await new Promise(
                    (resolve) =>
                        window.setTimeout(
                            resolve,
                            80
                        )
                );
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
                    || 'FCC sync failed.';

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
})();
