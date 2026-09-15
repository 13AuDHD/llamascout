(() => {
    'use strict';

    const forms = [
        ...document.querySelectorAll(
            '.place-report-form'
        ),
    ];

    if (!forms.length) {
        return;
    }

    const RECOVERY_MAX_AGE =
        48 * 60 * 60 * 1000;

    const AUTOSAVE_DELAY = 700;

    const blockedNames = new Set([
        'csrf_token',
        'photo_stage_token',
        'photos_json',
        'draft_save_token',
        'place_admin_action',
        'submit_for_review',
        'save_for_later',
    ]);

    const randomId = () => {
        if (
            window.crypto
            && typeof window.crypto.randomUUID
                === 'function'
        ) {
            return window.crypto.randomUUID();
        }

        return (
            Date.now().toString(36)
            + '-'
            + Math.random()
                .toString(36)
                .slice(2)
        );
    };

    const contributorInstanceId = () => {
        const state =
            window.history.state
            && typeof window.history.state
                === 'object'
                ? window.history.state
                : {};

        if (
            typeof state.llamaPlaceRecoveryId
                === 'string'
            && state.llamaPlaceRecoveryId !== ''
        ) {
            return state.llamaPlaceRecoveryId;
        }

        const id = randomId();

        try {
            window.history.replaceState(
                {
                    ...state,
                    llamaPlaceRecoveryId: id,
                },
                '',
                window.location.href
            );
        } catch (_) {
            return id;
        }

        return id;
    };

    const formRecoveryKey = (form) => {
        const csrf =
            String(
                form.querySelector(
                    '[name="csrf_token"]'
                )?.value
                || ''
            );

        const sessionPart =
            csrf.slice(-16) || 'session';

        const placeId =
            String(
                form.querySelector(
                    '[name="place_id"]'
                )?.value
                || ''
            ).trim();

        if (
            form.id === 'place-report'
            && placeId !== ''
        ) {
            return [
                'llama',
                'place-report-recovery',
                'admin',
                sessionPart,
                placeId,
            ].join(':');
        }

        const draftId =
            String(
                form.querySelector(
                    '[name="draft_id"]'
                )?.value
                || ''
            ).trim();

        if (draftId !== '') {
            return [
                'llama',
                'place-report-recovery',
                'draft',
                sessionPart,
                draftId,
            ].join(':');
        }

        const submissionId =
            String(
                form.querySelector(
                    '[name="submission_id"]'
                )?.value
                || ''
            ).trim();

        if (submissionId !== '') {
            return [
                'llama',
                'place-report-recovery',
                'submission',
                sessionPart,
                submissionId,
            ].join(':');
        }

        return [
            'llama',
            'place-report-recovery',
            'new',
            sessionPart,
            contributorInstanceId(),
        ].join(':');
    };

    const groupedControls = (form) => {
        const groups = new Map();

        [
            ...form.elements,
        ].forEach((control) => {
            if (
                !(control instanceof HTMLElement)
                || !('name' in control)
            ) {
                return;
            }

            const name =
                String(control.name || '');

            if (
                name === ''
                || blockedNames.has(name)
                || control instanceof HTMLButtonElement
                || (
                    control instanceof HTMLInputElement
                    && (
                        control.type === 'submit'
                        || control.type === 'button'
                        || control.type === 'file'
                        || control.type === 'reset'
                    )
                )
            ) {
                return;
            }

            if (!groups.has(name)) {
                groups.set(name, []);
            }

            groups.get(name).push(control);
        });

        return groups;
    };

    const serializeForm = (form) => {
        const state = {};

        groupedControls(form).forEach(
            (controls, name) => {
                const first = controls[0];

                if (
                    first
                    instanceof HTMLInputElement
                    && first.type === 'radio'
                ) {
                    state[name] = {
                        kind: 'radio',
                        value:
                            controls.find(
                                (control) =>
                                    control.checked
                            )?.value
                            ?? null,
                    };

                    return;
                }

                if (
                    first
                    instanceof HTMLInputElement
                    && first.type === 'checkbox'
                ) {
                    state[name] = {
                        kind: 'checkbox',
                        values:
                            controls
                                .filter(
                                    (control) =>
                                        control.checked
                                )
                                .map(
                                    (control) =>
                                        control.value
                                ),
                    };

                    return;
                }

                if (
                    first
                    instanceof HTMLSelectElement
                    && first.multiple
                ) {
                    state[name] = {
                        kind: 'multiple',
                        values:
                            [
                                ...first
                                    .selectedOptions,
                            ].map(
                                (option) =>
                                    option.value
                            ),
                    };

                    return;
                }

                state[name] = {
                    kind: 'value',
                    value:
                        'value' in first
                            ? String(
                                first.value
                                ?? ''
                            )
                            : '',
                };
            }
        );

        return state;
    };

    const stateJson = (form) =>
        JSON.stringify(
            serializeForm(form)
        );

    const restoreForm = (
        form,
        storedState
    ) => {
        const groups =
            groupedControls(form);

        Object.entries(
            storedState || {}
        ).forEach(
            ([name, saved]) => {
                const controls =
                    groups.get(name);

                if (
                    !controls
                    || !controls.length
                    || !saved
                    || typeof saved !== 'object'
                ) {
                    return;
                }

                if (saved.kind === 'radio') {
                    controls.forEach(
                        (control) => {
                            control.checked =
                                saved.value !== null
                                && control.value
                                    === saved.value;
                        }
                    );

                    return;
                }

                if (
                    saved.kind
                    === 'checkbox'
                ) {
                    const values =
                        Array.isArray(
                            saved.values
                        )
                            ? saved.values
                            : [];

                    controls.forEach(
                        (control) => {
                            control.checked =
                                values.includes(
                                    control.value
                                );
                        }
                    );

                    return;
                }

                if (
                    saved.kind
                    === 'multiple'
                ) {
                    const first =
                        controls[0];

                    if (
                        !(
                            first
                            instanceof HTMLSelectElement
                        )
                    ) {
                        return;
                    }

                    const values =
                        Array.isArray(
                            saved.values
                        )
                            ? saved.values
                            : [];

                    [
                        ...first.options,
                    ].forEach(
                        (option) => {
                            option.selected =
                                values.includes(
                                    option.value
                                );
                        }
                    );

                    return;
                }

                const first =
                    controls[0];

                if (
                    'value' in first
                    && saved.kind
                        === 'value'
                ) {
                    first.value =
                        String(
                            saved.value
                            ?? ''
                        );
                }
            }
        );
    };

    const recoveryStatus = (form) => {
        let status =
            form.querySelector(
                '[data-place-report-recovery-status]'
            );

        if (status) {
            return status;
        }

        status =
            document.createElement(
                'span'
            );

        status.setAttribute(
            'data-place-report-recovery-status',
            '1'
        );

        status.setAttribute(
            'role',
            'status'
        );

        status.setAttribute(
            'aria-live',
            'polite'
        );

        status.style.display = 'block';
        status.style.width = '100%';
        status.style.color =
            'var(--text-muted)';
        status.style.fontSize = '.68rem';
        status.style.lineHeight = '1.35';

        const adminBar =
            form.querySelector(
                '.admin-place-report-savebar'
            );

        const contributionBar =
            form.querySelector(
                '.add-place-submit-bar'
            );

        if (adminBar) {
            adminBar.style.flexWrap =
                'wrap';
            adminBar.style.gap = '7px';
            adminBar.appendChild(status);
        } else if (contributionBar) {
            status.style.gridColumn =
                '1 / -1';
            contributionBar.appendChild(
                status
            );
        } else {
            form.appendChild(status);
        }

        return status;
    };

    forms.forEach((form) => {
        /*
         * =====================================================
         * EXISTING PLACE REPORT CONTROL BEHAVIOR
         * =====================================================
         */

        form
            .querySelectorAll(
                '[data-place-report-clear]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        const name =
                            button.getAttribute(
                                'data-place-report-clear'
                            );

                        if (!name) {
                            return;
                        }

                        form
                            .querySelectorAll(
                                `input[type="radio"][name="${CSS.escape(name)}"]`
                            )
                            .forEach(
                                (radio) => {
                                    radio.checked =
                                        false;
                                }
                            );

                        form.dispatchEvent(
                            new Event(
                                'change',
                                {
                                    bubbles:
                                        true,
                                }
                            )
                        );
                    }
                );
            });

        const noAmenities =
            form.querySelector(
                '[data-place-report-no-amenities]'
            );

        const amenities = [
            ...form.querySelectorAll(
                '[data-place-report-amenity]'
            ),
        ];

        if (noAmenities) {
            noAmenities.addEventListener(
                'change',
                () => {
                    if (
                        !noAmenities.checked
                    ) {
                        return;
                    }

                    amenities.forEach(
                        (input) => {
                            input.checked =
                                false;
                        }
                    );
                }
            );

            amenities.forEach(
                (input) => {
                    input.addEventListener(
                        'change',
                        () => {
                            if (
                                input.checked
                                && noAmenities
                                    .checked
                            ) {
                                noAmenities.checked =
                                    false;
                            }
                        }
                    );
                }
            );
        }


        /*
         * =====================================================
         * LOCAL RECOVERY BACKUP
         * =====================================================
         */

        const key =
            formRecoveryKey(form);

        const status =
            recoveryStatus(form);

        const initialBaseline =
            stateJson(form);

        let baseline =
            initialBaseline;

        let dirty = false;
        let restoring = false;
        let savingTimer = 0;
        let submitting = false;

        const setStatus = (
            message,
            isError = false
        ) => {
            status.textContent =
                message;

            status.style.color =
                isError
                    ? '#d96a62'
                    : 'var(--text-muted)';
        };

        const removeRecovery = () => {
            try {
                localStorage.removeItem(
                    key
                );
            } catch (_) {
            }
        };

        const saveRecovery = () => {
            if (
                restoring
                || submitting
            ) {
                return;
            }

            const data =
                serializeForm(form);

            const current =
                JSON.stringify(data);

            if (current === baseline) {
                dirty = false;
                removeRecovery();
                setStatus('');
                return;
            }

            const payload = {
                version: 1,
                savedAt: Date.now(),
                baseline,
                data,
            };

            try {
                localStorage.setItem(
                    key,
                    JSON.stringify(
                        payload
                    )
                );

                dirty = true;

                setStatus(
                    'Unsaved changes backed up on this device.'
                );
            } catch (_) {
                dirty = true;

                setStatus(
                    'Unsaved changes could not be backed up in this browser.',
                    true
                );
            }
        };

        const queueRecovery = () => {
            if (
                restoring
                || submitting
            ) {
                return;
            }

            window.clearTimeout(
                savingTimer
            );

            savingTimer =
                window.setTimeout(
                    saveRecovery,
                    AUTOSAVE_DELAY
                );
        };

        try {
            const raw =
                localStorage.getItem(
                    key
                );

            if (raw) {
                const payload =
                    JSON.parse(raw);

                const age =
                    Date.now()
                    - Number(
                        payload?.savedAt
                        || 0
                    );

                if (
                    payload?.version === 1
                    && age >= 0
                    && age
                        <= RECOVERY_MAX_AGE
                    && payload.baseline
                        === initialBaseline
                    && payload.data
                    && typeof payload.data
                        === 'object'
                ) {
                    const recovered =
                        JSON.stringify(
                            payload.data
                        );

                    if (
                        recovered
                        !== initialBaseline
                    ) {
                        restoring = true;

                        restoreForm(
                            form,
                            payload.data
                        );

                        restoring = false;
                        dirty = true;

                        const recoveredAt =
                            new Date(
                                Number(
                                    payload.savedAt
                                )
                            );

                        setStatus(
                            'Recovered unsaved changes from '
                            + recoveredAt
                                .toLocaleTimeString(
                                    [],
                                    {
                                        hour:
                                            'numeric',
                                        minute:
                                            '2-digit',
                                    }
                                )
                            + '.'
                        );
                    } else {
                        removeRecovery();
                    }
                } else {
                    /*
                     * The server-side form has changed since this
                     * recovery copy was made, or the copy is old.
                     * Do not overwrite newer server data.
                     */
                    removeRecovery();
                }
            }
        } catch (_) {
            removeRecovery();
        }

        form.addEventListener(
            'input',
            queueRecovery
        );

        form.addEventListener(
            'change',
            queueRecovery
        );


        /*
         * =====================================================
         * ADMIN SAVE RELIABILITY + FEEDBACK
         * =====================================================
         *
         * The Admin editor is an existing Place. Let the server
         * perform authoritative validation instead of allowing
         * native Safari validation somewhere far up the long form
         * to silently block the sticky Save button.
         */

        const isAdminReport =
            form.id === 'place-report'
            && form.querySelector(
                '[name="place_admin_action"][value="save-report"]'
            );

        const adminSaveButton =
            isAdminReport
                ? form.querySelector(
                    '.admin-place-report-savebar button[type="submit"]'
                )
                : null;

        let adminSaveButtonHtml = '';

        if (
            isAdminReport
            && adminSaveButton
        ) {
            /*
             * Keep Admin saving native. The button already uses
             * formnovalidate, so Safari cannot silently block it on an
             * off-screen required field. Do not cancel the click or
             * re-trigger submission from JavaScript.
             */
            form.noValidate = true;

            adminSaveButtonHtml =
                adminSaveButton.innerHTML;
        }

        form.addEventListener(
            'submit',
            () => {
                submitting = true;

                window.clearTimeout(
                    savingTimer
                );

                /*
                 * Capture the latest state before navigation. We
                 * deliberately leave this recovery copy in place.
                 * After a successful save the server form changes,
                 * so its baseline no longer matches and the stale
                 * recovery is discarded automatically.
                 */
                const current =
                    JSON.stringify(
                        serializeForm(form)
                    );

                if (
                    current !== baseline
                ) {
                    try {
                        localStorage.setItem(
                            key,
                            JSON.stringify(
                                {
                                    version: 1,
                                    savedAt:
                                        Date.now(),
                                    baseline,
                                    data:
                                        JSON.parse(
                                            current
                                        ),
                                }
                            )
                        );
                    } catch (_) {
                    }
                }

                if (
                    isAdminReport
                    && adminSaveButton
                ) {
                    adminSaveButton.disabled =
                        true;

                    adminSaveButton.innerHTML =
                        `
                        <span
                            aria-hidden="true"
                            style="
                                width:14px;
                                height:14px;
                                display:inline-block;
                                box-sizing:border-box;
                                border:2px solid currentColor;
                                border-right-color:transparent;
                                border-radius:50%;
                                animation:llama-place-report-save-spin .75s linear infinite;
                            "
                        ></span>
                        Saving...
                        `;

                    setStatus(
                        'Saving Place Report...'
                    );
                }
            }
        );

        /*
         * Back-forward cache can restore the page after Safari
         * navigates away. Never leave the save button stuck.
         */
        window.addEventListener(
            'pageshow',
            (event) => {
                if (
                    !event.persisted
                    || !isAdminReport
                    || !adminSaveButton
                ) {
                    return;
                }

                submitting = false;
                adminSaveButton.disabled =
                    false;

                if (adminSaveButtonHtml) {
                    adminSaveButton.innerHTML =
                        adminSaveButtonHtml;
                }
            }
        );

        window.addEventListener(
            'beforeunload',
            (event) => {
                if (
                    !dirty
                    || submitting
                ) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            }
        );
    });

    if (
        !document.getElementById(
            'llama-place-report-save-animation'
        )
    ) {
        const style =
            document.createElement(
                'style'
            );

        style.id =
            'llama-place-report-save-animation';

        style.textContent = `
            @keyframes llama-place-report-save-spin {
                to {
                    transform: rotate(360deg);
                }
            }
        `;

        document.head.appendChild(
            style
        );
    }
})();
