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

    /*
     * This file is the single owner of Admin Place Report saving.
     * The report is intentionally allowed to submit without browser
     * constraint validation because server-side validation is authoritative
     * and hidden/off-screen fields must not block an Admin background save.
     */
    form.noValidate = true;

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

    const stagedPhotoCount = () => {
        const raw =
            form.querySelector(
                '[name="photos_json"]'
            )?.value
            || '[]';

        try {
            const parsed =
                JSON.parse(raw);

            return Array.isArray(parsed)
                ? parsed.length
                : 0;
        } catch (_) {
            return 0;
        }
    };

    const syncPhotoUploader = () => {
        form.dispatchEvent(
            new CustomEvent(
                'llama:photo-uploader-sync'
            )
        );
    };

    const save = async () => {
        if (busy) {
            return;
        }

        busy = true;

        syncPhotoUploader();

        form.dispatchEvent(
            new CustomEvent(
                'llama:admin-place-report-save-starting'
            )
        );

        const expectedPhotos =
            stagedPhotoCount();

        setButtonState(
            'saving'
        );

        setStatus(
            expectedPhotos > 0
                ? `Saving Place Report and ${expectedPhotos} photo${expectedPhotos === 1 ? '' : 's'}...`
                : 'Saving Place Report to the database...'
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
                                'application/json',
                            'X-Llama-Admin-Save':
                                'place-report',
                        },
                        signal:
                            controller.signal,
                    }
                );

            const raw =
                await response.text();

            let payload = null;

            try {
                payload =
                    JSON.parse(raw);
            } catch (_) {
                throw new Error(
                    'The server returned an unexpected response while saving.'
                );
            }

            if (
                !response.ok
                || payload?.success !== true
            ) {
                const reference =
                    payload?.reference
                        ? ` Error reference: ${payload.reference}`
                        : '';

                throw new Error(
                    (
                        payload?.message
                        || `Server returned HTTP ${response.status}.`
                    )
                    + reference
                );
            }

            const photosAdded =
                Number(
                    payload.photos_added
                    || 0
                );

            if (
                expectedPhotos > 0
                && photosAdded
                    !== expectedPhotos
            ) {
                throw new Error(
                    `The Place Report saved, but only ${photosAdded} of ${expectedPhotos} staged photos were attached.`
                );
            }

            const csrfToken =
                form.querySelector(
                    'input[name="csrf_token"]'
                );

            if (
                csrfToken
                && typeof payload.csrf_token
                    === 'string'
                && payload.csrf_token !== ''
            ) {
                csrfToken.value =
                    payload.csrf_token;
            }

            clearRecoveryCopies();

            form.dispatchEvent(
                new CustomEvent(
                    'llama:photo-uploader-committed',
                    {
                        detail: {
                            context:
                                'add-place',
                            count:
                                photosAdded,
                            total:
                                Number(
                                    payload.photo_total
                                    || 0
                                ),
                        },
                    }
                )
            );

            setButtonState(
                'saved'
            );

            const savedAt =
                new Date()
                    .toLocaleTimeString(
                        [],
                        {
                            hour:
                                'numeric',
                            minute:
                                '2-digit',
                        }
                    );

            setStatus(
                photosAdded > 0
                    ? `Saved to the database at ${savedAt}. ${photosAdded} photo${photosAdded === 1 ? '' : 's'} attached to this Place.`
                    : `Saved to the database at ${savedAt}.`
            );

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
                            photosAdded,
                            photoTotal:
                                Number(
                                    payload.photo_total
                                    || 0
                                ),
                        },
                    }
                )
            );

        } catch (error) {
            const message =
                error?.name
                    === 'AbortError'
                    ? 'Not saved. The server did not respond within 45 seconds. Your browser backup and staged photos are still intact.'
                    : (
                        error?.message
                        || 'Not saved. Your browser backup and staged photos are still intact.'
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
     * This is the only live Admin save handler. Capture phase remains
     * intentional so a stale cached copy of an older shared script
     * cannot compete with the authoritative save path.
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

    /*
     * Safari can restore the page from its back-forward cache while the
     * button still looks busy. The dedicated saver owns that UI state too.
     */
    window.addEventListener(
        'pageshow',
        (event) => {
            if (!event.persisted) {
                return;
            }

            busy = false;
            resetButton();
        }
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
