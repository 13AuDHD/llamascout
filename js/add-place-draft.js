(() => {
    'use strict';

    /*
     * =====================================================
     * NO AMENITIES
     * =====================================================
     */

    const none =
        document.querySelector(
            'input[name="amenity_none"]'
        );

    if (none) {
        const amenityBoxes = [
            ...document.querySelectorAll(
                'input[name^="amenity_"]'
            ),
        ].filter(
            (input) =>
                input !== none
        );

        const syncFromNone = () => {
            if (!none.checked) {
                return;
            }

            amenityBoxes.forEach(
                (input) => {
                    input.checked = false;
                }
            );
        };

        none.addEventListener(
            'change',
            syncFromNone
        );

        amenityBoxes.forEach(
            (input) => {
                input.addEventListener(
                    'change',
                    () => {
                        if (input.checked) {
                            none.checked = false;
                        }
                    }
                );
            }
        );

        syncFromNone();
    }


    /*
     * =====================================================
     * SAVE FOR LATER
     *
     * Draft saving deliberately uses its own endpoint rather
     * than the Add a Place form's normal submission path.
     * =====================================================
     */

    const form =
        document.querySelector(
            '.add-place-form'
        );

    const saveButton =
        form?.querySelector(
            'button[name="save_for_later"]'
        );

    if (
        !form
        || !saveButton
    ) {
        return;
    }

    let saving = false;

    const originalButtonHtml =
        saveButton.innerHTML;

    let status =
        document.querySelector(
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

        status.style.marginTop =
            '0.5rem';

        status.style.fontSize =
            '0.8rem';

        status.style.width =
            '100%';

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

        status.style.color =
            isError
                ? '#ef8b8b'
                : '';
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

            saveButton.innerHTML = `
                <i
                    class="llama-icon-mask is-spinning"
                    style="--llama-icon-mask:url('/assets/icons/loader-2.svg')"
                    aria-hidden="true"
                ></i>
                Saving...
            `;

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
             * The shared report-recovery script normally learns about saves
             * through the form submit event. Save for Later bypasses that event
             * entirely, so explicitly tell it that a real server save is
             * starting. This prevents its local crash-recovery copy from racing
             * the draft save and later resurrecting a staging token that the
             * server has already consumed.
             */
            form.dispatchEvent(
                new CustomEvent(
                    'llama:place-draft-save-starting'
                )
            );

            const body =
                new FormData(form);

            /*
             * Make the draft action explicit. The normal
             * Submit for Review button is not part of this
             * request.
             */
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

                /*
                 * Clear the local crash-recovery snapshot before navigation.
                 * The saved draft in the database is now authoritative.
                 */
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
