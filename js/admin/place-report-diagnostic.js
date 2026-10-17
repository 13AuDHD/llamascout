(() => {
    'use strict';

    const form =
        document.getElementById(
            'place-report'
        );

    if (
        !form
        || !form.classList.contains(
            'admin-place-report-form'
        )
    ) {
        return;
    }

    const configNode =
        form.querySelector(
            '[data-place-report-schema-config]'
        );

    let derivedKeys =
        new Set();

    if (configNode) {
        try {
            const config =
                JSON.parse(
                    configNode.textContent
                    || '{}'
                );

            derivedKeys =
                new Set(
                    (
                        Array.isArray(
                            config.fields
                        )
                            ? config.fields
                            : []
                    )
                        .filter(
                            (field) =>
                                field
                                && field.derived
                        )
                        .map(
                            (field) =>
                                String(
                                    field.key
                                    || ''
                                )
                        )
                        .filter(Boolean)
                );
        } catch (_) {
            derivedKeys =
                new Set();
        }
    }

    let syncing =
        false;

    let queued =
        false;

    const fieldKeyFor =
        (wrapper) => {
            const control =
                wrapper.querySelector(
                    'input[name], select[name], textarea[name]'
                );

            if (!control) {
                return '';
            }

            return String(
                control.name
                || ''
            )
                .replace(
                    /\[\]$/,
                    ''
                )
                .trim();
        };

    const exposeWrapper =
        (wrapper) => {
            const key =
                fieldKeyFor(
                    wrapper
                );

            if (key === '') {
                return;
            }

            const isDerived =
                derivedKeys.has(
                    key
                );

            wrapper.hidden =
                false;

            wrapper.classList.remove(
                'is-applicability-hidden'
            );

            delete wrapper.dataset
                .placeReportApplicabilityHidden;

            if (!isDerived) {
                return;
            }

            wrapper.dataset
                .adminDiagnosticDerived =
                '1';

            wrapper
                .querySelectorAll(
                    'input, select, textarea, button'
                )
                .forEach(
                    (control) => {
                        control.disabled =
                            true;
                    }
                );
        };

    const exposeSection =
        (section) => {
            if (
                section.dataset
                    .placeReportApplicabilityHidden
                !== '1'
                && !section.classList.contains(
                    'is-applicability-hidden'
                )
            ) {
                return;
            }

            section.hidden =
                false;

            section.classList.remove(
                'is-applicability-hidden'
            );

            delete section.dataset
                .placeReportApplicabilityHidden;
        };

    const sync =
        () => {
            queued =
                false;

            if (syncing) {
                return;
            }

            syncing =
                true;

            try {
                form
                    .querySelectorAll(
                        '.contribution-field, .contribution-check'
                    )
                    .forEach(
                        exposeWrapper
                    );

                form
                    .querySelectorAll(
                        '.contribution-section'
                    )
                    .forEach(
                        exposeSection
                    );
            } finally {
                syncing =
                    false;
            }
        };

    const queueSync =
        () => {
            if (queued) {
                return;
            }

            queued =
                true;

            queueMicrotask(
                sync
            );
        };

    form.addEventListener(
        'input',
        queueSync
    );

    form.addEventListener(
        'change',
        queueSync
    );

    form.addEventListener(
        'llama:campsite-selected',
        queueSync
    );

    form.addEventListener(
        'llama:place-context-changed',
        queueSync
    );

    window.addEventListener(
        'llama:place-context-changed',
        queueSync
    );

    const observer =
        new MutationObserver(
            (records) => {
                if (syncing) {
                    return;
                }

                if (
                    records.some(
                        (record) =>
                            record.type
                            === 'attributes'
                            || record.type
                                === 'childList'
                    )
                ) {
                    queueSync();
                }
            }
        );

    observer.observe(
        form,
        {
            attributes:
                true,

            childList:
                true,

            subtree:
                true,

            attributeFilter: [
                'hidden',
                'class',
                'data-place-report-applicability-hidden'
            ]
        }
    );

    /*
     * Dependency metadata remains untouched in the shared schema.
     * Admin only overrides dependency-based presentation so every real
     * question and stored answer can be inspected while Issue #5 is
     * reconciled field by field.
     */
    sync();
})();
