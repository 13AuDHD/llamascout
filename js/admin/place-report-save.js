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
                        '.contribution-field.is-applicability-hidden:not(.is-force-hidden), .contribution-check.is-applicability-hidden:not(.is-force-hidden)'
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
        const initialSiteId = form.dataset.adminSiteReportId;
        const recoverySuffix = initialSiteId ? `:${placeId}:site-${initialSiteId}` : `:${placeId}`;

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
                        recoverySuffix
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

    const bulkTouched = new Set();
    const overwritePanel = form.querySelector('[data-admin-overwrite-site-panel]');
    const overwriteToggle = form.querySelector('[data-admin-overwrite-site-answers]');
    let editingAllSites = false;
    const clearBulkConsensusNotes = () => {
        form.querySelectorAll('[data-admin-bulk-mixed-note], [data-admin-bulk-summary]')
            .forEach(note => note.remove());
    };

    const resetBulk = () => {
        bulkTouched.clear();
        clearBulkConsensusNotes();
        if (overwriteToggle) overwriteToggle.checked = false;
        if (overwritePanel) overwritePanel.hidden = !editingAllSites;
    };

    const controlsFor = (name) =>
        Array.from(
            form.querySelectorAll(
                `[name="${name}"], [name="${name}[]"], [name="_report_unknown[${name}]"]`
            )
        );

    const campsiteSelect = form.querySelector('[data-admin-campsite-selector]');

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
        if (key === 'site_type_of_use') {
            select.querySelector('[data-previous-site-use]')?.remove();
        }

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
            if (key === 'site_type_of_use') {
                option.dataset.previousSiteUse = '1';
                option.textContent += ' (previous entry)';
            }
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

        const choices = controls.filter(control => control.name === `${key}[]`);
        if (choices.length) {
            const selected = Array.isArray(value) ? value : [];
            choices.forEach(control => {
                control.checked = selected.includes(control.value);
            });
            const unknown = controls.find(control => control.name === `_report_unknown[${key}]`);
            if (unknown) unknown.checked = value === campsiteUnknown;
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

            const choices = controls.filter(control => control.name === `${key}[]`);
            if (choices.length) {
                const unknown = controls.find(control => control.name === `_report_unknown[${key}]`);
                values[key] = unknown?.checked
                    ? campsiteUnknown
                    : choices.filter(control => control.checked).map(control => control.value);
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
        (left, right) => {
            if (Array.isArray(left) && Array.isArray(right)) {
                return JSON.stringify([...left].sort()) === JSON.stringify([...right].sort());
            }
            return left === right;
        };

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

    // A bulk editing value is not an answer of its own. It is a projection
    // of the current effective answer on EVERY Site, including imported,
    // inherited and locally overridden answers. An unanswered Site differs
    // from one with an explicit No or Unknown.
    const comparableSiteValue = (value) => {
        if (value === null || value === undefined
            || value === '' || value === campsiteUnanswered) {
            return 'unanswered';
        }
        if (Array.isArray(value)) {
            return 'list:' + JSON.stringify(value.map(String).sort());
        }
        if (typeof value === 'boolean') {
            return value ? 'value:1' : 'value:0';
        }
        return 'value:' + String(value);
    };

    const sharedSiteAnswers = () => {
        const common = {};
        const mixed = new Set();
        if (!campsiteSites.length) {
            return {common, mixed};
        }
        const allValues = campsiteSites.map(siteEffectiveValues);
        campsiteFieldKeys.forEach(key => {
            const firstValue = Object.prototype.hasOwnProperty.call(allValues[0], key)
                ? allValues[0][key] : undefined;
            const reference = comparableSiteValue(firstValue);
            const identical = allValues.every(values =>
                comparableSiteValue(Object.prototype.hasOwnProperty.call(values, key)
                    ? values[key] : undefined) === reference
            );
            if (!identical) {
                mixed.add(key);
            } else if (reference !== 'unanswered'
                && reference !== 'list:[]') {
                common[key] = firstValue;
            }
        });
        return {common, mixed};
    };

    const showMixedSiteNote = (key) => {
        const container = controlsFor(key)[0]?.closest('[data-report-field]');
        if (!container) return;
        const note = document.createElement('small');
        note.className = 'place-report-answer-context';
        note.dataset.adminBulkMixedNote = key;
        note.textContent = 'Mixed across Sites. Select an answer to change it.';
        container.appendChild(note);
    };

    // Apply the exact same fill-unanswered/overwrite rule used by PHP to
    // the browser snapshot only AFTER the transaction succeeded. Recomputing
    // consensus immediately then matches what a fresh server load will show.
    const rememberSavedBulkAnswers = (answers, overwrite) => {
        campsiteSites.forEach(site => {
            const overrides = {...siteOverrideValues(site)};
            const imported = site?.imported_values || {};
            Object.entries(answers).forEach(([key, value]) => {
                const hasImported = Object.prototype.hasOwnProperty.call(imported, key)
                    && imported[key] !== null && imported[key] !== ''
                    && imported[key] !== campsiteUnanswered;
                if (!overwrite
                    && (Object.prototype.hasOwnProperty.call(overrides, key)
                        || hasImported)) {
                    return;
                }
                if (Object.prototype.hasOwnProperty.call(overrides, key)
                    && comparableSiteValue(overrides[key]) === comparableSiteValue(value)) {
                    return;
                }
                overrides[key] = value;
            });
            site.override_values = overrides;
            site.form_values = siteEffectiveValues(site);
        });
    };

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
                    const controls = controlsFor(key);
                    if (!controls.length) {
                        return;
                    }
                    const unanswered = controls.some(control => control.name === `${key}[]`) ? [] : '';

                    const baseValue =
                        Object.prototype.hasOwnProperty.call(
                            base,
                            key
                        )
                            ? base[key]
                            : unanswered;

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
                            : unanswered;

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
        editingAllSites = campsiteSelect?.value === 'all-sites';
        resetBulk();
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

    const loadAllSitesSection = () => {
        // Keep Place-section answers cached, but do not display them as the
        // answer of every Site. Those Site questions need actual consensus.
        loadPlaceSiteSection();
        if (!campsiteSites.length) return;

        clearSiteFields();
        const {common, mixed} = sharedSiteAnswers();
        Object.entries(common).forEach(([key, value]) =>
            setSiteFieldValue(key, value)
        );
        mixed.forEach(showMixedSiteNote);

        const sitePicker = campsiteSelect?.closest('[data-admin-site-picker]');
        if (sitePicker) {
            const summary = document.createElement('small');
            summary.className = 'place-report-answer-context';
            summary.dataset.adminBulkSummary = '1';
            summary.textContent = 'Common answers are prefilled from all '
                + campsiteSites.length + ' Sites. Mixed answers are left blank. '
                + 'Only answers you change will be saved in bulk.';
            sitePicker.appendChild(summary);
        }

        editingAllSites = true;
        form.dispatchEvent(new CustomEvent('llama:campsite-selected', {
            detail: {campsiteId: '', label: 'All Sites', scope: 'all-sites'}
        }));
    };

    const loadCampsite = (
        siteId
    ) => {
        if (String(siteId) === 'all-sites') {
            loadAllSitesSection();
            return;
        }
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

        editingAllSites = false;
        resetBulk();
        activeCampsiteId =
            String(site.id);
        const siteSection = campsiteSelect.closest('[data-report-main-section="site"]');
        if (siteSection) siteSection.open = true;
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
                    if (editingAllSites) {
                        bulkTouched.add(key);
                        form.querySelectorAll('[data-admin-bulk-mixed-note]')
                            .forEach(note => {
                                if (note.dataset.adminBulkMixedNote === key) note.remove();
                            });
                        return;
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
                if (editingAllSites) {
                    bulkTouched.add(key);
                    form.querySelectorAll('[data-admin-bulk-mixed-note]')
                        .forEach(note => {
                            if (note.dataset.adminBulkMixedNote === key) note.remove();
                        });
                }
            });
        });
    });

    if (campsiteSelect) {
        campsiteSelect.addEventListener(
            'change',
            () => {
                const next =
                    campsiteSelect.value;

                if (editingAllSites && bulkTouched.size > 0 && next !== 'all-sites'
                    && !window.confirm('Discard unsaved All Sites changes?')) {
                    campsiteSelect.value = 'all-sites';
                    return;
                }

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

        // The server selects a Site only when an explicit, valid Site link was opened.
        loadCampsite(campsiteSelect.value);
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

    const save = async () => {
        let bulkPayload = '';
        if (editingAllSites) {
            const values = currentCampsiteFormValues();
            const answers = {};
            bulkTouched.forEach(key => {
                if (!campsiteFieldKeys.includes(key)) return;
                const value = values[key];
                if (value !== undefined && value !== '' && value !== campsiteUnanswered
                    && (!Array.isArray(value) || value.length > 0)) {
                    answers[key] = value;
                }
            });
            const keys = Object.keys(answers);
            if (!keys.length) {
                window.alert('No Site answers were changed for bulk editing.');
                return;
            }
            const mode = overwriteToggle?.checked ? 'OVERWRITE existing answers' : 'fill unanswered fields only';
            const preview = keys.map(key => `${key}: ${String(answers[key])}`).join('\n');
            if (!window.confirm(`Apply ${keys.length} answer(s) to up to ${campsiteSites.length} Sites?\nMode: ${mode}\n\n${preview}\n\nThis action updates individual Site records.`)) {
                return;
            }
            bulkPayload = JSON.stringify({answers, overwrite: Boolean(overwriteToggle?.checked)});
        }
        if (busy) {
            return;
        }

        busy = true;

        syncPhotoUploader();

        form.dispatchEvent(
            new CustomEvent(
                'llama:admin-place-report-save-starting',
                {detail: {bulkSiteFields: bulkPayload ? campsiteFieldKeys : []}}
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
            if (bulkPayload) {
                saveData.delete('selected_campsite_id');
                saveData.set('admin_bulk_site_answers_json', bulkPayload);
            }

            // Site-only answers need an individual Site or the bulk payload.
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
            if (bulkPayload) {
                const applied = JSON.parse(bulkPayload);
                rememberSavedBulkAnswers(applied.answers, applied.overwrite);
                loadAllSitesSection();
                window.alert(`Bulk update saved: ${Number(payload.bulk_sites_updated || 0)} Sites, ${Number(payload.bulk_answers_saved || 0)} answers.`);
            }

            if (activeCampsiteId === '') {
                if (!bulkPayload) rememberSavedPlaceSiteSection();
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
