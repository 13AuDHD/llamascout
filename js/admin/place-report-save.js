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

        status.className =
            'admin-place-report-save-status';

        saveBar.classList.add(
            'has-save-status'
        );

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

        status.classList.toggle(
            'is-error',
            isError
        );
    };

    const resetButton = () => {
        window.clearTimeout(
            resetTimer
        );

        button.disabled =
            false;

        button.classList.remove(
            'is-saving',
            'is-saved',
            'is-error'
        );

        button.innerHTML =
            originalHtml;
    };

    const setButtonState = (
        state
    ) => {
        window.clearTimeout(
            resetTimer
        );

        button.classList.remove(
            'is-saving',
            'is-saved',
            'is-error'
        );

        if (state === 'saving') {
            button.disabled = true;

            button.classList.add(
                'is-saving'
            );

            button.innerHTML = `
                <span
                    class="admin-place-save-spinner"
                    aria-hidden="true"
                ></span>
                Saving...
            `;

            return;
        }

        if (state === 'saved') {
            button.disabled = false;

            button.classList.add(
                'is-saved'
            );

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

            button.classList.add(
                'is-error'
            );

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


    const campsiteConfigNode =
        form.querySelector(
            '[data-admin-place-campsites]'
        );

    let campsiteConfig = {
        sites: [],
        site_field_keys: [],
        unknown_token: '__LLAMA_UNKNOWN__',
        unanswered_token: '__LLAMA_UNANSWERED__'
    };

    if (campsiteConfigNode) {
        try {
            campsiteConfig = {
                ...campsiteConfig,
                ...JSON.parse(
                    campsiteConfigNode.textContent
                    || '{}'
                )
            };
        } catch (_) {
            // Leave the campsite editor unavailable if its page data is invalid.
        }
    }

    const campsiteSites =
        Array.isArray(campsiteConfig.sites)
            ? campsiteConfig.sites
            : [];

    const campsiteFieldKeys =
        Array.isArray(campsiteConfig.site_field_keys)
            ? campsiteConfig.site_field_keys
            : [];

    const campsiteUnknown =
        String(
            campsiteConfig.unknown_token
            || '__LLAMA_UNKNOWN__'
        );

    const campsiteUnanswered =
        String(
            campsiteConfig.unanswered_token
            || '__LLAMA_UNANSWERED__'
        );

    let campsiteDirty = false;
    let activeCampsiteId = '';

    const controlsFor = (name) =>
        Array.from(
            form.querySelectorAll(
                `[name="${name}"]`
            )
        );

    const siteNumberInput =
        form.querySelector(
            '[name="site_number"]'
        );

    let campsiteSelect = null;

    if (siteNumberInput) {
        campsiteSelect =
            document.createElement(
                'select'
            );

        campsiteSelect.name =
            'selected_campsite_id';
        campsiteSelect.setAttribute(
            'data-admin-campsite-selector',
            '1'
        );

        const choose =
            document.createElement(
                'option'
            );

        choose.value = '';
        choose.textContent =
            campsiteSites.length > 0
                ? 'Select campsite...'
                : 'No campsite records available';
        campsiteSelect.appendChild(choose);

        campsiteSites.forEach(site => {
            const option =
                document.createElement(
                    'option'
                );

            option.value =
                String(site.id || '');
            option.textContent =
                String(
                    site.label
                    || `Site ${site.id}`
                );
            campsiteSelect.appendChild(option);
        });

        if (campsiteSites.length === 0) {
            campsiteSelect.disabled = true;
        }

        const wrapper =
            siteNumberInput.closest(
                '.contribution-field'
            );

        const label =
            wrapper?.querySelector(
                '.place-report-field-label > span:first-child'
            );

        if (label) {
            label.textContent =
                'Campsite';
        }

        siteNumberInput.replaceWith(
            campsiteSelect
        );
    }

    const setSiteControlsEnabled = (
        enabled
    ) => {
        campsiteFieldKeys.forEach(key => {
            controlsFor(key).forEach(control => {
                control.disabled = !enabled;
            });
        });
    };

    const importedSelectLabel = (
        key,
        value
    ) => {
        const stringValue =
            String(value).trim();

        if (stringValue === '') {
            return '';
        }

        const numericValue =
            Number(stringValue);

        const isNumeric =
            Number.isFinite(numericValue);

        const formattedNumber =
            isNumeric
                ? numericValue.toLocaleString(
                    undefined,
                    {
                        maximumFractionDigits: 2
                    }
                )
                : stringValue;

        if (key === 'max_people' && isNumeric) {
            return `${formattedNumber} ${numericValue === 1 ? 'person' : 'people'}`;
        }

        if (key === 'vehicle_capacity' && isNumeric) {
            return `${formattedNumber} ${numericValue === 1 ? 'vehicle' : 'vehicles'}`;
        }

        if (
            key.endsWith('_feet')
            && isNumeric
        ) {
            return `${formattedNumber} ft`;
        }

        return stringValue;
    };

    const ensureSelectValue = (
        select,
        key,
        value
    ) => {
        const stringValue =
            String(value);

        const found =
            Array.from(select.options)
                .some(
                    option =>
                        option.value === stringValue
                );

        if (!found && stringValue !== '') {
            const option =
                document.createElement(
                    'option'
                );

            option.value = stringValue;
            option.textContent =
                importedSelectLabel(
                    key,
                    stringValue
                );
            select.appendChild(option);
        }

        select.value = stringValue;
    };

    const setSiteFieldValue = (
        key,
        value
    ) => {
        const controls =
            controlsFor(key);

        if (controls.length === 0) {
            return;
        }

        const stringValue =
            value === null
            || value === undefined
                ? ''
                : String(value);

        const radioControls =
            controls.filter(
                control =>
                    control.type === 'radio'
            );

        if (radioControls.length > 0) {
            radioControls.forEach(control => {
                control.checked =
                    control.value === stringValue;
            });

            const hidden =
                controls.find(
                    control =>
                        control.type === 'hidden'
                );

            if (hidden) {
                hidden.value =
                    campsiteUnanswered;
            }

            return;
        }

        const control =
            controls.find(
                item =>
                    item.type !== 'hidden'
            )
            || controls[0];

        if (control.tagName === 'SELECT') {
            ensureSelectValue(
                control,
                key,
                stringValue
            );
        } else if (control.type === 'checkbox') {
            control.checked =
                stringValue === '1'
                || stringValue.toLowerCase() === 'true';
        } else {
            control.value = stringValue;
        }
    };

    const clearSiteFields = () => {
        campsiteFieldKeys.forEach(key => {
            setSiteFieldValue(
                key,
                ''
            );
        });
    };

    const currentCampsiteFormValues = () => {
        const values = {};

        campsiteFieldKeys.forEach(key => {
            const controls =
                controlsFor(key);

            if (controls.length === 0) {
                return;
            }

            const radios =
                controls.filter(
                    control =>
                        control.type === 'radio'
                );

            if (radios.length > 0) {
                const checked =
                    radios.find(
                        control =>
                            control.checked
                    );

                if (checked) {
                    values[key] =
                        checked.value;
                }

                return;
            }

            const control =
                controls.find(
                    item =>
                        item.type !== 'hidden'
                )
                || controls[0];

            if (control.type === 'checkbox') {
                values[key] =
                    control.checked
                        ? '1'
                        : '0';
                return;
            }

            values[key] =
                control.value;
        });

        return values;
    };

    const rememberSavedCampsite = () => {
        if (activeCampsiteId === '') {
            return;
        }

        const site =
            campsiteSites.find(
                item =>
                    String(item.id) ===
                    activeCampsiteId
            );

        if (!site) {
            return;
        }

        site.form_values = {
            ...(site.form_values
                && typeof site.form_values === 'object'
                    ? site.form_values
                    : {}),
            ...currentCampsiteFormValues()
        };
    };


    const loadCampsite = (
        siteId
    ) => {
        const site =
            campsiteSites.find(
                item =>
                    String(item.id) ===
                    String(siteId)
            );

        clearSiteFields();

        if (!site) {
            activeCampsiteId = '';
            setSiteControlsEnabled(false);
            campsiteDirty = false;
            form.dispatchEvent(new CustomEvent('llama:campsite-selected', {
                detail: { campsiteId: '', label: '' }
            }));
            return;
        }

        const values =
            site.form_values
            && typeof site.form_values === 'object'
                ? site.form_values
                : {};

        Object.entries(values)
            .forEach(
                ([key, value]) => {
                    if (
                        campsiteFieldKeys.includes(key)
                    ) {
                        setSiteFieldValue(
                            key,
                            value
                        );
                    }
                }
            );

        activeCampsiteId =
            String(site.id);
        setSiteControlsEnabled(true);
        campsiteDirty = false;
        // Programmatically populating fields doesn't emit input/change events.
        // Notify the section-completion view after every campsite switch.
        form.dispatchEvent(new CustomEvent('llama:campsite-selected', {
            detail: {
                campsiteId: activeCampsiteId,
                label: String(site.label || `Site ${site.id}`)
            }
        }));
    };

    campsiteFieldKeys.forEach(key => {
        controlsFor(key).forEach(control => {
            control.addEventListener(
                'change',
                () => {
                    if (activeCampsiteId !== '') {
                        campsiteDirty = true;
                    }
                }
            );
        });
    });

    if (campsiteSelect) {
        campsiteSelect.addEventListener(
            'change',
            () => {
                const next =
                    campsiteSelect.value;

                if (
                    campsiteDirty
                    && activeCampsiteId !== ''
                    && next !== activeCampsiteId
                    && !window.confirm(
                        'Switch campsites and discard unsaved changes to the current campsite?'
                    )
                ) {
                    campsiteSelect.value =
                        activeCampsiteId;
                    return;
                }

                loadCampsite(next);
            }
        );

        setSiteControlsEnabled(false);

        if (campsiteSites.length === 1) {
            campsiteSelect.value =
                String(campsiteSites[0].id);
            loadCampsite(
                campsiteSelect.value
            );
        } else {
            loadCampsite('');
        }
    }

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
            rememberSavedCampsite();
            campsiteDirty = false;
            form.dispatchEvent(new CustomEvent('llama:campsite-saved', {
                detail: { campsiteId: activeCampsiteId }
            }));

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

})();
