(() => {
    'use strict';

    const form =
        document.getElementById(
            'cell-coverage-import-form'
        );

    if (!form) {
        return;
    }

    const progressBox =
        document.getElementById(
            'cell-coverage-import-progress'
        );

    const progressBar =
        document.getElementById(
            'cell-import-progress-bar'
        );

    const statusNode =
        document.getElementById(
            'cell-import-status'
        );

    const countsNode =
        document.getElementById(
            'cell-import-counts'
        );

    const submitButton =
        form.querySelector(
            'button[type="submit"]'
        );

    let running = false;


    function text(
        node,
        value
    ) {
        if (node) {
            node.textContent =
                String(value ?? '');
        }
    }


    function setProgress(
        processed,
        total
    ) {
        if (!progressBar) {
            return;
        }

        const safeTotal =
            Math.max(
                1,
                Number(total) || 1
            );

        const percent =
            Math.min(
                100,
                Math.max(
                    0,
                    (
                        Number(processed)
                        / safeTotal
                    ) * 100
                )
            );

        progressBar.value =
            percent;
    }


    async function runBatch(
        baseFormData,
        offset
    ) {
        const payload =
            new FormData();

        baseFormData
            .forEach(
                (value, key) => {
                    payload.append(
                        key,
                        value
                    );
                }
            );

        payload.set(
            'offset',
            String(offset)
        );

        const response =
            await fetch(
                form.action,
                {
                    method: 'POST',
                    body: payload,
                    credentials:
                        'same-origin',
                    cache:
                        'no-store'
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
                || 'The import failed.'
            );
        }

        return data.result;
    }


    form.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            if (running) {
                return;
            }

            running = true;

            if (submitButton) {
                submitButton.disabled =
                    true;
            }

            if (progressBox) {
                progressBox.hidden =
                    false;
            }

            const baseFormData =
                new FormData(form);

            let offset = 0;
            let importedTotal = 0;
            let skippedTotal = 0;
            let totalRows = 0;

            text(
                statusNode,
                'Starting FCC coverage import...'
            );

            try {
                while (true) {
                    const result =
                        await runBatch(
                            baseFormData,
                            offset
                        );

                    totalRows =
                        Number(
                            result.total_rows
                        ) || totalRows;

                    importedTotal +=
                        Number(
                            result.imported
                        ) || 0;

                    skippedTotal +=
                        Number(
                            result.skipped
                        ) || 0;

                    offset =
                        Number(
                            result.next_offset
                        ) || offset;

                    setProgress(
                        offset,
                        totalRows
                    );

                    text(
                        countsNode,
                        `${offset.toLocaleString()} of ${totalRows.toLocaleString()} rows read, `
                        + `${importedTotal.toLocaleString()} imported, `
                        + `${skippedTotal.toLocaleString()} skipped`
                    );

                    if (result.done) {
                        break;
                    }

                    text(
                        statusNode,
                        'Importing coverage...'
                    );
                }

                setProgress(
                    totalRows,
                    totalRows
                );

                text(
                    statusNode,
                    'FCC coverage import complete.'
                );

            } catch (error) {
                text(
                    statusNode,
                    error?.message
                    || 'The import failed.'
                );

            } finally {
                running = false;

                if (submitButton) {
                    submitButton.disabled =
                        false;
                }
            }
        }
    );
})();
