(() => {
    'use strict';

    const form =
        document.querySelector(
            '.add-place-form'
        );

    if (!form) {
        return;
    }

    /*
     * Pressing Enter or Return in an ordinary Add a Place
     * control must never submit the report.
     *
     * Textareas and contenteditable controls keep their normal
     * newline behavior. The Submit for Review button must be
     * activated deliberately instead of through implicit form
     * submission.
     */
    form.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key !== 'Enter'
                || event.isComposing
            ) {
                return;
            }

            const target =
                event.target;

            if (
                target instanceof HTMLTextAreaElement
                || (
                    target instanceof HTMLElement
                    && target.isContentEditable
                )
            ) {
                return;
            }

            event.preventDefault();
        }
    );

    /*
     * Save for Later deliberately uses its own endpoint rather
     * than the Add Place form's normal submission path.
     */
    const saveButton =
        form.querySelector(
            'button[name="save_for_later"]'
        );

    if (!saveButton) {
        return;
    }

    let saving = false;

    const originalButtonHtml =
        saveButton.innerHTML;

    let status =
        form.querySelector(
            '[data-draft-save-status]'
        );

    if (!status) {
        status =
            document.createElement(
                'div'
            );

        status.setAttribute(
            'data-draft-save-status',
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

        status.className =
            'add-place-draft-save-status';

        const actionBar =
            saveButton.closest(
                '.add-place-submit-bar'
            );

        if (actionBar) {
            actionBar.appendChild(
                status
            );
        }
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

        status.classList.toggle(
            'is-error',
            isError
        );
    };

    const resetButton = () => {
        saving = false;

        saveButton.removeAttribute(
            'aria-disabled'
        );

        saveButton.classList.remove(
            'is-saving'
        );

        saveButton.innerHTML =
            originalButtonHtml;
    };

    saveButton.addEventListener(
        'click',
        async (event) => {
            event.preventDefault();

            if (saving) {
                return;
            }

            saving = true;

            saveButton.setAttribute(
                'aria-disabled',
                'true'
            );

            saveButton.classList.add(
                'is-saving'
            );

            saveButton.textContent =
                'Saving...';

            setStatus(
                'Saving this Place for later...'
            );

            /*
             * Save for Later is intercepted on click, so the form's normal
             * submit event never fires. Force the shared photo uploader to
             * flush its current token/photo list into the hidden fields before
             * FormData is captured.
             */
            form.dispatchEvent(
                new CustomEvent(
                    'llama:photo-uploader-sync'
                )
            );

            const busyUploader =
                form.querySelector(
                    '[data-photo-uploader][aria-busy="true"]'
                );

            if (busyUploader) {
                setStatus(
                    'Please wait for the current photo upload to finish before saving.',
                    true
                );
                resetButton();
                return;
            }

            /*
             * Save for Later bypasses the normal submit event, so explicitly
             * tell shared crash recovery that a server save is starting.
             */
            form.dispatchEvent(
                new CustomEvent(
                    'llama:place-draft-save-starting'
                )
            );

            const body =
                new FormData(form);

            body.delete(
                'submit_for_review'
            );

            body.set(
                'save_for_later',
                '1'
            );

            const controller =
                new AbortController();

            const timeout =
                window.setTimeout(
                    () => {
                        controller.abort();
                    },
                    30000
                );

            try {
                const response =
                    await fetch(
                        '/api/save-place-draft.php',
                        {
                            method:
                                'POST',

                            body,

                            credentials:
                                'same-origin',

                            cache:
                                'no-store',

                            headers: {
                                Accept:
                                    'application/json',
                            },

                            signal:
                                controller.signal,
                        }
                    );

                const raw =
                    await response.text();

                let payload;

                try {
                    payload =
                        JSON.parse(raw);
                } catch (_) {
                    throw new Error(
                        'The draft save service returned an unexpected response.'
                    );
                }

                if (
                    !response.ok
                    || payload?.success
                        !== true
                ) {
                    throw new Error(
                        payload?.message
                        || 'The Place could not be saved for later.'
                    );
                }

                const redirect =
                    String(
                        payload.redirect
                        || ''
                    ).trim();

                if (!redirect) {
                    throw new Error(
                        'The Place was saved, but the return address was missing.'
                    );
                }

                setStatus(
                    'Saved. Opening Saved for Later...'
                );

                form.dispatchEvent(
                    new CustomEvent(
                        'llama:place-draft-saved',
                        {
                            detail: {
                                draftId:
                                    Number(
                                        payload.draft_id
                                        || 0
                                    ),
                            },
                        }
                    )
                );

                window.location.assign(
                    redirect
                );

            } catch (error) {
                const message =
                    error?.name
                        === 'AbortError'
                        ? 'The save request took too long and was stopped. Please try again.'
                        : (
                            error?.message
                            || 'The Place could not be saved for later.'
                        );

                form.dispatchEvent(
                    new CustomEvent(
                        'llama:place-draft-save-failed'
                    )
                );

                setStatus(
                    message,
                    true
                );

                resetButton();

            } finally {
                window.clearTimeout(
                    timeout
                );
            }
        }
    );

    window.addEventListener(
        'pageshow',
        () => {
            if (saving) {
                resetButton();
            }
        }
    );
})();
