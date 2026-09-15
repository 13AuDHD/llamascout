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
         * ADMIN BACKGROUND SAVE
         * =====================================================
         *
         * Admin Place Reports save through fetch() so the editor
         * never leaves or reloads the page. The existing server
         * POST path remains authoritative. A successful server
         * redirect to ?saved=report is treated as confirmation
         * that the database transaction completed.
         */

        const isAdminReport =
            form.id === 'place-report'
            && form.querySelector(
                '[name="place_admin_action"][value="save-report"]'
            );

        const adminSaveButton =
            isAdminReport
                ? form.querySelector(
                    '[data-place-report-admin-save]'
                )
                : null;

        let adminSaveButtonHtml = '';
        let adminSaveButtonStyle = '';
        let adminStateTimer = 0;

        const setAdminButtonState = (
            state,
            label = ''
        ) => {
            if (!adminSaveButton) {
                return;
            }

            window.clearTimeout(
                adminStateTimer
            );

            adminSaveButton.disabled =
                state === 'saving';

            adminSaveButton.style.cssText =
                adminSaveButtonStyle;

            if (state === 'saving') {
                adminSaveButton.style.borderColor =
                    '#c94a4a';

                adminSaveButton.style.background =
                    'rgba(201, 74, 74, 0.18)';

                adminSaveButton.style.color =
                    '#e26a6a';

                adminSaveButton.innerHTML = `
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

                return;
            }

            if (state === 'saved') {
                adminSaveButton.style.borderColor =
                    '#2e9d58';

                adminSaveButton.style.background =
                    'rgba(46, 157, 88, 0.18)';

                adminSaveButton.style.color =
                    '#55b978';

                adminSaveButton.innerHTML =
                    'Saved';

                adminStateTimer =
                    window.setTimeout(
                        () => {
                            setAdminButtonState(
                                'idle'
                            );
                        },
                        2200
                    );

                return;
            }

            if (state === 'error') {
                adminSaveButton.style.borderColor =
                    '#d2a62d';

                adminSaveButton.style.background =
                    'rgba(210, 166, 45, 0.18)';

                adminSaveButton.style.color =
                    '#dfb94f';

                adminSaveButton.innerHTML =
                    label || 'Not saved';

                adminStateTimer =
                    window.setTimeout(
                        () => {
                            setAdminButtonState(
                                'idle'
                            );
                        },
                        5000
                    );

                return;
            }

            adminSaveButton.disabled =
                false;

            adminSaveButton.style.cssText =
                adminSaveButtonStyle;

            adminSaveButton.innerHTML =
                adminSaveButtonHtml;
        };

        const saveRecoveryImmediately = () => {
            const data =
                serializeForm(form);

            const current =
                JSON.stringify(data);

            if (current === baseline) {
                return current;
            }

            try {
                localStorage.setItem(
                    key,
                    JSON.stringify(
                        {
                            version: 1,
                            savedAt:
                                Date.now(),
                            baseline,
                            data,
                        }
                    )
                );

                dirty = true;
            } catch (_) {
                dirty = true;
            }

            return current;
        };

        const extractAdminSaveError = (
            html
        ) => {
            if (
                typeof html !== 'string'
                || html.trim() === ''
            ) {
                return '';
            }

            try {
                const documentCopy =
                    new DOMParser()
                        .parseFromString(
                            html,
                            'text/html'
                        );

                const errorNode =
                    documentCopy.querySelector(
                        '.admin-user-notice.is-error'
                    );

                if (errorNode) {
                    return String(
                        errorNode.textContent
                        || ''
                    ).trim();
                }
            } catch (_) {
            }

            return '';
        };

        const updateAdminCsrfFromResponse = (
            html
        ) => {
            if (
                typeof html !== 'string'
                || html.trim() === ''
            ) {
                return;
            }

            try {
                const documentCopy =
                    new DOMParser()
                        .parseFromString(
                            html,
                            'text/html'
                        );

                const freshToken =
                    documentCopy.querySelector(
                        '#place-report input[name="csrf_token"]'
                    )?.value;

                const currentToken =
                    form.querySelector(
                        'input[name="csrf_token"]'
                    );

                if (
                    currentToken
                    && typeof freshToken
                        === 'string'
                    && freshToken !== ''
                ) {
                    currentToken.value =
                        freshToken;
                }
            } catch (_) {
            }
        };

        const saveAdminReport = async () => {
            if (
                !isAdminReport
                || !adminSaveButton
                || submitting
            ) {
                return;
            }

            submitting = true;

            window.clearTimeout(
                savingTimer
            );

            const submittedState =
                saveRecoveryImmediately();

            setAdminButtonState(
                'saving'
            );

            setStatus(
                'Saving Place Report to the database...'
            );

            const body =
                new FormData(form);

            const controller =
                new AbortController();

            const timeout =
                window.setTimeout(
                    () => {
                        controller.abort();
                    },
                    45000
                );

            try {
                const response =
                    await fetch(
                        form.action
                        || window.location.href,
                        {
                            method: 'POST',
                            body,
                            credentials:
                                'same-origin',
                            cache:
                                'no-store',
                            redirect:
                                'follow',
                            headers: {
                                Accept:
                                    'text/html',
                                'X-Llama-Background-Save':
                                    'place-report',
                            },
                            signal:
                                controller.signal,
                        }
                    );

                const html =
                    await response.text();

                updateAdminCsrfFromResponse(
                    html
                );

                const responseUrl =
                    new URL(
                        response.url,
                        window.location.href
                    );

                const serverConfirmed =
                    response.ok
                    && (
                        responseUrl
                            .searchParams
                            .get('saved')
                            === 'report'
                        || html.includes(
                            'Place Report saved.'
                        )
                    );

                if (!serverConfirmed) {
                    const serverMessage =
                        extractAdminSaveError(
                            html
                        );

                    throw new Error(
                        serverMessage
                        || (
                            response.ok
                                ? 'The server did not confirm that the Place Report was saved.'
                                : `The server returned HTTP ${response.status}.`
                        )
                    );
                }

                /*
                 * The server has confirmed the save. The exact
                 * submitted form state is now our new clean
                 * baseline. Future browser recovery backups only
                 * track edits made after this point.
                 */
                baseline =
                    submittedState;

                dirty = false;

                removeRecovery();

                setAdminButtonState(
                    'saved'
                );

                setStatus(
                    'Saved to the database at '
                    + new Date()
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

            } catch (error) {
                const message =
                    error?.name
                        === 'AbortError'
                        ? 'The save request took too long. Your unsaved changes are still backed up on this device.'
                        : (
                            error?.message
                            || 'The Place Report was not saved. Your unsaved changes are still backed up on this device.'
                        );

                dirty = true;

                setAdminButtonState(
                    'error',
                    'Not saved'
                );

                setStatus(
                    message,
                    true
                );

            } finally {
                window.clearTimeout(
                    timeout
                );

                submitting = false;
            }
        };

        if (
            isAdminReport
            && adminSaveButton
        ) {
            /*
             * Native validation on a very long form can prevent a
             * submit because of an off-screen field. Server-side
             * validation remains authoritative for background saves.
             */
            form.noValidate = true;

            adminSaveButtonHtml =
                adminSaveButton.innerHTML;

            adminSaveButtonStyle =
                adminSaveButton.getAttribute(
                    'style'
                )
                || '';

            form.addEventListener(
                'submit',
                (event) => {
                    event.preventDefault();

                    saveAdminReport();
                }
            );
        } else {
            /*
             * Contributor/moderator submission forms keep their
             * normal navigation-based submit behavior.
             */
            form.addEventListener(
                'submit',
                () => {
                    submitting = true;

                    window.clearTimeout(
                        savingTimer
                    );

                    saveRecoveryImmediately();
                }
            );
        }


        /*
         * A dedicated Admin save script owns the live AJAX save.
         * When that script confirms a database save, make the
         * browser-recovery copy agree with the newly saved state.
         *
         * Without this handshake, local recovery continues to use
         * the page-load state as its baseline and can later treat
         * already-saved answers as unsaved or restore the wrong
         * version after a reload/crash.
         */
        window.addEventListener(
            'llama:admin-place-report-saved',
            (event) => {
                if (!isAdminReport) {
                    return;
                }

                const formPlaceId =
                    String(
                        form.querySelector(
                            '[name="place_id"]'
                        )?.value
                        || ''
                    );

                const savedPlaceId =
                    String(
                        event?.detail?.placeId
                        || ''
                    );

                if (
                    savedPlaceId !== ''
                    && formPlaceId !== ''
                    && savedPlaceId
                        !== formPlaceId
                ) {
                    return;
                }

                window.clearTimeout(
                    savingTimer
                );

                baseline =
                    stateJson(form);

                dirty = false;
                submitting = false;

                removeRecovery();
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

                setAdminButtonState(
                    'idle'
                );
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
