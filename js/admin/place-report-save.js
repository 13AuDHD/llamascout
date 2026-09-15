(() => {
    'use strict';

    const form =
        document.getElementById(
            'place-report'
        );

    if (!form) {
        return;
    }

    const button =
        form.querySelector(
            '[data-place-report-admin-save]'
        );

    if (!button) {
        return;
    }

    let busy = false;
    let resetTimer = 0;

    const originalHtml =
        button.innerHTML;

    const originalStyle =
        button.getAttribute('style')
        || '';

    const saveBar =
        button.closest(
            '.admin-place-report-savebar'
        );

    let status =
        form.querySelector(
            '[data-place-report-recovery-status]'
        );

    if (!status && saveBar) {
        status =
            document.createElement(
                'span'
            );

        status.setAttribute(
            'data-place-report-direct-save-status',
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

        status.style.display =
            'block';

        status.style.width =
            '100%';

        status.style.fontSize =
            '.68rem';

        status.style.lineHeight =
            '1.35';

        status.style.color =
            'var(--text-muted)';

        saveBar.style.flexWrap =
            'wrap';

        saveBar.style.gap =
            '7px';

        saveBar.appendChild(
            status
        );
    }

    const setStatus = (
        message,
        isError = false
    ) => {
        if (!status) {
            return;
        }

        status.textContent =
            message;

        status.style.color =
            isError
                ? '#dfb94f'
                : 'var(--text-muted)';
    };

    const resetButton = () => {
        window.clearTimeout(
            resetTimer
        );

        button.disabled =
            false;

        button.style.cssText =
            originalStyle;

        button.innerHTML =
            originalHtml;
    };

    const setButtonState = (
        state
    ) => {
        window.clearTimeout(
            resetTimer
        );

        button.style.cssText =
            originalStyle;

        if (state === 'saving') {
            button.disabled = true;

            button.style.borderColor =
                '#c94a4a';

            button.style.background =
                'rgba(201, 74, 74, .18)';

            button.style.color =
                '#e26a6a';

            button.innerHTML = `
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
                        animation:llama-admin-place-save-spin .75s linear infinite;
                    "
                ></span>
                Saving...
            `;

            return;
        }

        if (state === 'saved') {
            button.disabled = false;

            button.style.borderColor =
                '#2e9d58';

            button.style.background =
                'rgba(46, 157, 88, .18)';

            button.style.color =
                '#55b978';

            button.innerHTML =
                'Saved';

            resetTimer =
                window.setTimeout(
                    resetButton,
                    2400
                );

            return;
        }

        if (state === 'error') {
            button.disabled = false;

            button.style.borderColor =
                '#d2a62d';

            button.style.background =
                'rgba(210, 166, 45, .18)';

            button.style.color =
                '#dfb94f';

            button.innerHTML =
                'Not saved';

            resetTimer =
                window.setTimeout(
                    resetButton,
                    5000
                );
        }
    };

    const extractError = (
        html
    ) => {
        if (
            typeof html !== 'string'
            || html.trim() === ''
        ) {
            return '';
        }

        try {
            const parsed =
                new DOMParser()
                    .parseFromString(
                        html,
                        'text/html'
                    );

            const node =
                parsed.querySelector(
                    '.admin-user-notice.is-error'
                );

            if (node) {
                return String(
                    node.textContent
                    || ''
                ).trim();
            }
        } catch (_) {
        }

        return '';
    };

    const refreshCsrf = (
        html
    ) => {
        if (
            typeof html !== 'string'
            || html.trim() === ''
        ) {
            return;
        }

        try {
            const parsed =
                new DOMParser()
                    .parseFromString(
                        html,
                        'text/html'
                    );

            const fresh =
                parsed.querySelector(
                    '#place-report input[name="csrf_token"]'
                )?.value;

            const current =
                form.querySelector(
                    'input[name="csrf_token"]'
                );

            if (
                current
                && typeof fresh === 'string'
                && fresh !== ''
            ) {
                current.value =
                    fresh;
            }
        } catch (_) {
        }
    };

    const clearRecoveryCopies = () => {
        const placeId =
            String(
                form.querySelector(
                    '[name="place_id"]'
                )?.value
                || ''
            ).trim();

        if (placeId === '') {
            return;
        }

        try {
            const remove = [];

            for (
                let i = 0;
                i < localStorage.length;
                i++
            ) {
                const key =
                    localStorage.key(i);

                if (
                    typeof key === 'string'
                    && key.startsWith(
                        'llama:place-report-recovery:admin:'
                    )
                    && key.endsWith(
                        `:${placeId}`
                    )
                ) {
                    remove.push(key);
                }
            }

            remove.forEach(
                (key) =>
                    localStorage.removeItem(
                        key
                    )
            );
        } catch (_) {
        }
    };

    const save = async () => {
        if (busy) {
            return;
        }

        busy = true;

        setButtonState(
            'saving'
        );

        setStatus(
            'Saving Place Report to the database...'
        );

        const controller =
            new AbortController();

        const timeout =
            window.setTimeout(
                () => controller.abort(),
                45000
            );

        try {
            const response =
                await fetch(
                    form.action
                    || window.location.href,
                    {
                        method: 'POST',
                        body:
                            new FormData(form),
                        credentials:
                            'same-origin',
                        cache:
                            'no-store',
                        redirect:
                            'follow',
                        headers: {
                            Accept:
                                'text/html',
                            'X-Llama-Admin-Save':
                                'place-report',
                        },
                        signal:
                            controller.signal,
                    }
                );

            const html =
                await response.text();

            refreshCsrf(html);

            const finalUrl =
                new URL(
                    response.url,
                    window.location.href
                );

            const confirmed =
                response.ok
                && (
                    finalUrl
                        .searchParams
                        .get('saved')
                        === 'report'
                    || html.includes(
                        'Place Report saved.'
                    )
                );

            if (!confirmed) {
                const serverError =
                    extractError(html);

                throw new Error(
                    serverError
                    || (
                        response.ok
                            ? 'The server did not confirm that the Place Report was saved.'
                            : `Server returned HTTP ${response.status}.`
                    )
                );
            }

            clearRecoveryCopies();

            setButtonState(
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

            /*
             * Tell the shared recovery script, if the current
             * version is loaded, that the database save succeeded.
             */
            window.dispatchEvent(
                new CustomEvent(
                    'llama:admin-place-report-saved',
                    {
                        detail: {
                            placeId:
                                form.querySelector(
                                    '[name="place_id"]'
                                )?.value
                                || '',
                        },
                    }
                )
            );

        } catch (error) {
            const message =
                error?.name
                    === 'AbortError'
                    ? 'Not saved. The server did not respond within 45 seconds. Your browser backup is still intact.'
                    : (
                        error?.message
                        || 'Not saved. Your browser backup is still intact.'
                    );

            setButtonState(
                'error'
            );

            setStatus(
                message,
                true
            );

        } finally {
            window.clearTimeout(
                timeout
            );

            busy = false;
        }
    };

    /*
     * Capture phase is intentional. Older cached versions of the
     * shared Place Report script may also have attached handlers
     * to this button. This handler owns the Admin save action and
     * stops those older handlers before they can interfere.
     */
    button.addEventListener(
        'click',
        (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();

            save();
        },
        true
    );

    /*
     * Also own keyboard-based form submission.
     */
    form.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();

            save();
        },
        true
    );

    if (
        !document.getElementById(
            'llama-admin-place-save-animation'
        )
    ) {
        const style =
            document.createElement(
                'style'
            );

        style.id =
            'llama-admin-place-save-animation';

        style.textContent = `
            @keyframes llama-admin-place-save-spin {
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
