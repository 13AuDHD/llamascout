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

    const reportShell =
        form.closest(
            '.admin-place-report-shell'
        );

    const reportHeader =
        reportShell?.querySelector(
            '.admin-place-report-header'
        );

    let showInapplicable =
        false;

    let applicabilityToggle =
        null;

    let applicabilityNotice =
        null;

    const syncModeratorApplicability =
        () => {
            const hiddenFields =
                Array.from(
                    form.querySelectorAll(
                        '.contribution-field.is-applicability-hidden, .contribution-check.is-applicability-hidden'
                    )
                );

            if (showInapplicable) {
                hiddenFields.forEach(
                    (wrapper) => {
                        wrapper.hidden =
                            false;

                        wrapper.dataset.adminApplicabilityOverride =
                            '1';
                    }
                );
            } else {
                form
                    .querySelectorAll(
                        '[data-admin-applicability-override="1"]'
                    )
                    .forEach(
                        (wrapper) => {
                            wrapper.hidden =
                                true;

                            delete wrapper.dataset
                                .adminApplicabilityOverride;
                        }
                    );
            }

            if (applicabilityToggle) {
                applicabilityToggle.setAttribute(
                    'aria-pressed',
                    showInapplicable
                        ? 'true'
                        : 'false'
                );

                applicabilityToggle.textContent =
                    showInapplicable
                        ? 'Hide normally inapplicable questions'
                        : 'Show normally inapplicable questions';
            }

            if (applicabilityNotice) {
                applicabilityNotice.hidden =
                    !showInapplicable;
            }
        };

    if (reportHeader) {
        applicabilityToggle =
            document.createElement(
                'button'
            );

        applicabilityToggle.type =
            'button';

        applicabilityToggle.className =
            'admin-button is-muted';

        applicabilityToggle.setAttribute(
            'aria-pressed',
            'false'
        );

        applicabilityToggle.textContent =
            'Show normally inapplicable questions';

        applicabilityNotice =
            document.createElement(
                'span'
            );

        applicabilityNotice.className =
            'admin-place-muted';

        applicabilityNotice.hidden =
            true;

        applicabilityNotice.textContent =
            'Admin override is active. Normally inapplicable questions are visible and can be edited, but they still do not count toward completion.';

        applicabilityToggle.addEventListener(
            'click',
            () => {
                showInapplicable =
                    !showInapplicable;

                syncModeratorApplicability();
            }
        );

        reportHeader.append(
            applicabilityToggle,
            applicabilityNotice
        );

        form.addEventListener(
            'input',
            () => {
                if (!showInapplicable) {
                    return;
                }

                queueMicrotask(
                    syncModeratorApplicability
                );
            }
        );

        form.addEventListener(
            'change',
            () => {
                if (!showInapplicable) {
                    return;
                }

                queueMicrotask(
                    syncModeratorApplicability
                );
            }
        );
    }

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


    const photoUploader =
        form.querySelector(
            '[data-photo-uploader]'
        );

    const photoContext =
        String(
            photoUploader?.dataset.photoContext
            || 'admin-place'
        );

    const campsiteConfigNode =
        form.querySelector(
            '[data-admin-place-campsites]'
        );

    let campsiteConfig = {
        sites: [],
        site_field_keys: [],
        place_field_keys: [],
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
            // Leave the Site editor unavailable if its page data is invalid.
        }
    }

    const campsiteSites =
        Array.isArray(campsiteConfig.sites)
            ? campsiteConfig.sites
            : [];

    const areaConfigNode =
        form.querySelector(
            '[data-admin-place-areas]'
        );

    let areaCatalog = {
        areas: []
    };

    if (areaConfigNode) {
        try {
            areaCatalog =
                JSON.parse(
                    areaConfigNode.textContent
                    || '{}'
                );
        } catch (_) {
            areaCatalog = {
                areas: []
            };
        }
    }

    const campsiteAreas =
        Array.isArray(areaCatalog.areas)
            ? areaCatalog.areas
            : [];

    const areaByScopeId =
        new Map(
            campsiteAreas
                .map(
                    (area) => [
                        Number(
                            area.scope_id
                            || 0
                        ),
                        area
                    ]
                )
                .filter(
                    ([id]) =>
                        id > 0
                )
        );

    const campsiteFieldKeys =
        Array.isArray(campsiteConfig.site_field_keys)
            ? campsiteConfig.site_field_keys
            : [];

    const placeSiteFieldKeys =
        Array.isArray(campsiteConfig.place_field_keys)
            ? campsiteConfig.place_field_keys
            : [];

    const placeSiteFieldLookup =
        new Set(placeSiteFieldKeys);

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

    let siteAnswersField =
        form.querySelector(
            '[name="admin_site_answers_json"]'
        );

    if (!siteAnswersField) {
        siteAnswersField =
            document.createElement(
                'input'
            );

        siteAnswersField.type =
            'hidden';

        siteAnswersField.name =
            'admin_site_answers_json';

        form.appendChild(
            siteAnswersField
        );
    }

    let pendingSiteChanges =
        null;

    // Bulk editing is explicitly opt-in and only available for the Place target.
    // Track user edits, not the values loaded when switching targets.
    const bulkTouched = new Set();
    let bulkConfirmedPayload = '';
    const bulkField = document.createElement('input');
    bulkField.type = 'hidden';
    bulkField.name = 'admin_bulk_site_answers_json';
    form.appendChild(bulkField);
    const bulkPanel = document.createElement('div');
    bulkPanel.className = 'contribution-field';
    bulkPanel.hidden = true;
    const bulkLabel = document.createElement('label');
    const bulkToggle = document.createElement('input');
    bulkToggle.type = 'checkbox';
    bulkToggle.dataset.adminBulkSiteToggle = '1';
    bulkLabel.append(bulkToggle, document.createTextNode(' Apply changed Site answers to all Sites'));
    const bulkHelp = document.createElement('p');
    bulkHelp.textContent = 'Only questions you change after enabling this option will be applied. Existing Site answers are protected unless you choose overwrite.';
    const overwriteLabel = document.createElement('label');
    const overwriteToggle = document.createElement('input');
    overwriteToggle.type = 'checkbox';
    overwriteLabel.append(overwriteToggle, document.createTextNode(' Overwrite existing Site answers'));
    bulkPanel.append(bulkLabel, bulkHelp, overwriteLabel);
    const resetBulk = () => {
        bulkTouched.clear();
        bulkConfirmedPayload = '';
        bulkField.value = '';
        bulkToggle.checked = false;
        overwriteToggle.checked = false;
        form.dispatchEvent(new CustomEvent('llama:admin-bulk-site-mode-changed'));
    };
    bulkToggle.addEventListener('change', () => {
        bulkTouched.clear();
        bulkConfirmedPayload = '';
        form.dispatchEvent(new CustomEvent('llama:admin-bulk-site-mode-changed'));
    });


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
                ? 'Place'
                : 'Place (no Sites yet)';
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
                'Site';
        }

        siteNumberInput.replaceWith(
            campsiteSelect
        );
        // Outside the field wrapper so scope/applicability hiding cannot hide the bulk controls.
        (wrapper || campsiteSelect).insertAdjacentElement('afterend', bulkPanel);
    }

    const setSiteControlMode = (
        mode
    ) => {
        // Reconciliation keeps every Site question interactive.
        // Ownership is enforced when constructing the save payload.
        campsiteFieldKeys.forEach(key => {
            controlsFor(key).forEach(control => {
                control.disabled = false;
            });
        });
    };

    const selectedCampsite =
        () => {
            const selected =
                String(
                    campsiteSelect?.value
                    || ''
                ).trim();

            if (selected === '') {
                return null;
            }

            return (
                campsiteSites.find(
                    (site) =>
                        String(
                            site.id
                            || ''
                        ) === selected
                )
                || null
            );
        };

    const syncSiteControlModeFromSelection =
        () => {
            const site =
                selectedCampsite();

            if (site) {
                activeCampsiteId =
                    String(
                        site.id
                    );

                setSiteControlMode(
                    'site'
                );

                return;
            }

            if (
                String(
                    campsiteSelect?.value
                    || ''
                ).trim() === ''
            ) {
                activeCampsiteId =
                    '';

                setSiteControlMode(
                    'place'
                );
            }
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

    const currentPlaceSiteSectionValues = () => {
        const values =
            currentCampsiteFormValues();

        return Object.fromEntries(
            Object.entries(values)
                .filter(
                    ([key]) =>
                        placeSiteFieldLookup.has(key)
                )
        );
    };

    let placeSiteSectionValues =
        currentPlaceSiteSectionValues();

    let placeSiteDirty = false;

    const rememberSavedPlaceSiteSection = () => {
        placeSiteSectionValues =
            currentPlaceSiteSectionValues();

        placeSiteDirty = false;
    };

    const valuesEqual =
        (left, right) =>
            JSON.stringify(left)
            === JSON.stringify(right);

    const siteAreaOverrides =
        (site) => {
            const areaId =
                Number(
                    site?.parent_area_id
                    || 0
                );

            if (areaId < 1) {
                return {};
            }

            const area =
                areaByScopeId.get(
                    areaId
                );

            return (
                area?.form_values
                && typeof area.form_values === 'object'
                    ? area.form_values
                    : {}
            );
        };

    const siteBaseValues =
        (site) => {
            const values = {};

            campsiteFieldKeys.forEach(
                (key) => {
                    if (
                        placeSiteFieldLookup.has(
                            key
                        )
                        && Object.prototype.hasOwnProperty.call(
                            placeSiteSectionValues,
                            key
                        )
                    ) {
                        values[key] =
                            placeSiteSectionValues[key];
                    }
                }
            );

            const areaValues =
                siteAreaOverrides(
                    site
                );

            Object.entries(
                areaValues
            ).forEach(
                ([key, value]) => {
                    if (
                        placeSiteFieldLookup.has(
                            key
                        )
                        && campsiteFieldKeys.includes(
                            key
                        )
                    ) {
                        values[key] =
                            value;
                    }
                }
            );

            const imported =
                site?.imported_values
                && typeof site.imported_values === 'object'
                    ? site.imported_values
                    : {};

            Object.entries(
                imported
            ).forEach(
                ([key, value]) => {
                    if (
                        campsiteFieldKeys.includes(
                            key
                        )
                    ) {
                        values[key] =
                            value;
                    }
                }
            );

            return values;
        };

    const siteOverrideValues =
        (site) =>
            (
                site?.override_values
                && typeof site.override_values === 'object'
                    ? site.override_values
                    : {}
            );

    const siteEffectiveValues =
        (site) => ({
            ...siteBaseValues(
                site
            ),
            ...siteOverrideValues(
                site
            ),
        });

    const siteChanges =
        (
            site,
            current
        ) => {
            const result = {};
            const base =
                siteBaseValues(
                    site
                );
            const overrides =
                siteOverrideValues(
                    site
                );

            campsiteFieldKeys.forEach(
                (key) => {
                    if (
                        controlsFor(key).length
                        === 0
                    ) {
                        return;
                    }

                    const baseValue =
                        Object.prototype.hasOwnProperty.call(
                            base,
                            key
                        )
                            ? base[key]
                            : '';

                    const hasOverride =
                        Object.prototype.hasOwnProperty.call(
                            overrides,
                            key
                        );

                    const beforeEffective =
                        hasOverride
                            ? overrides[key]
                            : baseValue;

                    const value =
                        Object.prototype.hasOwnProperty.call(
                            current,
                            key
                        )
                            ? current[key]
                            : '';

                    if (
                        valuesEqual(
                            beforeEffective,
                            value
                        )
                    ) {
                        return;
                    }

                    if (
                        hasOverride
                        && valuesEqual(
                            baseValue,
                            value
                        )
                    ) {
                        result[key] =
                            campsiteUnanswered;
                        return;
                    }

                    if (
                        !hasOverride
                        && valuesEqual(
                            baseValue,
                            value
                        )
                    ) {
                        return;
                    }

                    result[key] =
                        value;
                }
            );

            return result;
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

        const overrides = {
            ...siteOverrideValues(
                site
            )
        };

        Object.entries(
            pendingSiteChanges
            || {}
        ).forEach(
            ([key, value]) => {
                if (
                    value === ''
                    || value === campsiteUnanswered
                ) {
                    delete overrides[key];
                } else {
                    overrides[key] =
                        value;
                }
            }
        );

        site.override_values =
            overrides;

        site.form_values =
            siteEffectiveValues(
                site
            );

        pendingSiteChanges =
            null;

        siteAnswersField.value =
            '';
    };


    const loadPlaceSiteSection = () => {
        clearSiteFields();

        Object.entries(
            placeSiteSectionValues
        ).forEach(
            ([key, value]) => {
                if (
                    placeSiteFieldLookup.has(key)
                ) {
                    setSiteFieldValue(
                        key,
                        value
                    );
                }
            }
        );

        activeCampsiteId = '';
        bulkPanel.hidden = campsiteSites.length === 0;
        resetBulk();
        setSiteControlMode('place');
        form.dispatchEvent(new CustomEvent('llama:admin-bulk-site-mode-changed'));
        campsiteDirty = false;
        placeSiteDirty = false;

        form.dispatchEvent(
            new CustomEvent(
                'llama:campsite-selected',
                {
                    detail: {
                        campsiteId: '',
                        label:
                            'Place',
                        scope:
                            'place',
                    },
                }
            )
        );
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

        if (!site) {
            loadPlaceSiteSection();
            return;
        }

        clearSiteFields();

        const values =
            siteEffectiveValues(
                site
            );

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

        bulkPanel.hidden = true;
        resetBulk();
        activeCampsiteId =
            String(site.id);
        setSiteControlMode('site');
        campsiteDirty = false;
        placeSiteDirty = false;
        // Programmatically populating fields doesn't emit input/change events.
        // Notify the section-completion view after every Site switch.
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
                    if (activeCampsiteId === '' && bulkToggle.checked) {
                        bulkTouched.add(key);
                    }
                    if (activeCampsiteId !== '') {
                        campsiteDirty = true;
                        return;
                    }

                    if (
                        placeSiteFieldLookup.has(key)
                    ) {
                        placeSiteDirty = true;
                    }
                }
            );
        });
    });

    campsiteFieldKeys.forEach(key => {
        controlsFor(key).forEach(control => {
            control.addEventListener('input', () => {
                if (activeCampsiteId === '' && bulkToggle.checked) bulkTouched.add(key);
            });
        });
    });

    if (campsiteSelect) {
        campsiteSelect.addEventListener(
            'change',
            () => {
                const next =
                    campsiteSelect.value;

                if (
                    activeCampsiteId === ''
                    && placeSiteDirty
                    && next !== ''
                    && !window.confirm(
                        'Switch to a Site and discard unsaved Place Site-section changes?'
                    )
                ) {
                    campsiteSelect.value = '';
                    return;
                }

                if (
                    campsiteDirty
                    && activeCampsiteId !== ''
                    && next !== activeCampsiteId
                    && !window.confirm(
                        'Switch Sites and discard unsaved changes to the current Site?'
                    )
                ) {
                    campsiteSelect.value =
                        activeCampsiteId;
                    return;
                }
                if (
                    activeCampsiteId === ''
                    && next !== ''
                ) {
                    placeSiteDirty = false;
                }

                loadCampsite(next);
            }
        );

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

    form.addEventListener(
        'llama:admin-place-report-save-starting',
        () => {
            siteAnswersField.value =
                '';

            pendingSiteChanges =
                null;

            if (
                activeCampsiteId === ''
            ) {
                return;
            }

            const site =
                campsiteSites.find(
                    (item) =>
                        String(
                            item.id
                            || ''
                        ) === activeCampsiteId
                );

            if (!site) {
                return;
            }

            pendingSiteChanges =
                siteChanges(
                    site,
                    currentCampsiteFormValues()
                );

            siteAnswersField.value =
                JSON.stringify(
                    pendingSiteChanges
                );
        }
    );

    /*
     * Place / Area / Site context can also be changed by the embedded map or
     * the shared context controller. Always derive enabled Site controls from
     * the actual Site selector after those changes instead of relying only on
     * this file's internal activeCampsiteId state.
     */
    form.addEventListener(
        'llama:place-context-changed',
        () => {
            queueMicrotask(
                syncSiteControlModeFromSelection
            );
        }
    );

    form.addEventListener(
        'llama:campsite-selected',
        () => {
            queueMicrotask(
                syncSiteControlModeFromSelection
            );
        }
    );

    window.addEventListener(
        'llama:place-context-changed',
        () => {
            queueMicrotask(
                syncSiteControlModeFromSelection
            );
        }
    );

    const save = async () => {
        bulkField.value = '';
        if (activeCampsiteId === '' && bulkToggle.checked) {
            const values = currentCampsiteFormValues();
            const answers = {};
            bulkTouched.forEach(key => {
                if (!campsiteFieldKeys.includes(key)) return;
                const value = values[key];
                if (value !== undefined && value !== '' && value !== campsiteUnanswered) {
                    answers[key] = value;
                }
            });
            const keys = Object.keys(answers);
            if (!keys.length) {
                window.alert('No Site answers were changed for bulk editing.');
                return;
            }
            const mode = overwriteToggle.checked ? 'OVERWRITE existing answers' : 'fill unanswered fields only';
            const preview = keys.map(key => `${key}: ${String(answers[key])}`).join('\n');
            if (!window.confirm(`Apply ${keys.length} answer(s) to up to ${campsiteSites.length} Sites?\nMode: ${mode}\n\n${preview}\n\nThis action updates individual Site records.`)) {
                return;
            }
            bulkConfirmedPayload = JSON.stringify({answers, overwrite: overwriteToggle.checked});
            bulkField.value = bulkConfirmedPayload;
        }
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
            const saveData = new FormData(form);
            if (bulkConfirmedPayload) saveData.set('admin_bulk_site_answers_json', bulkConfirmedPayload);

            // Previously disabled Site-only fields were not submitted on
            // the Place target. Keep that persistence boundary intact while
            // exposing the controls for reconciliation.
            if (!selectedCampsite()) {
                campsiteFieldKeys.forEach(key => {
                    if (!placeSiteFieldLookup.has(key)) {
                        saveData.delete(key);
                        saveData.delete(`${key}[]`);
                    }
                });
            }

            const response =
                await fetch(
                    form.action
                    || window.location.href,
                    {
                        method: 'POST',
                        body:
                            saveData,
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
            if (bulkConfirmedPayload) {
                resetBulk();
                window.alert(`Bulk update saved: ${Number(payload.bulk_sites_updated || 0)} Sites, ${Number(payload.bulk_answers_saved || 0)} answers.`);
            }

            if (activeCampsiteId === '') {
                rememberSavedPlaceSiteSection();
            } else {
                rememberSavedCampsite();
                campsiteDirty = false;
            }

            form.dispatchEvent(new CustomEvent('llama:campsite-saved', {
                detail: {
                    campsiteId:
                        activeCampsiteId,
                    scope:
                        activeCampsiteId === ''
                            ? 'place'
                            : 'site',
                }
            }));

            form.dispatchEvent(
                new CustomEvent(
                    'llama:photo-uploader-committed',
                    {
                        detail: {
                            context:
                                photoContext,
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
