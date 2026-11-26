(() => {
    'use strict';

    const form = document.getElementById('place-report');
    const shell = form?.closest('[data-contribution-tabs]');
    const catalogNode = shell?.querySelector('[data-admin-place-areas]');
    const selector = shell?.querySelector('[data-admin-area-selector]');
    const target = shell?.querySelector('[name="selected_area_id"]');
    const siteSelector = form?.querySelector('[data-admin-campsite-selector]');
    if (!form || !catalogNode || !selector || !target) return;

    let catalog;
    try {
        catalog = JSON.parse(catalogNode.textContent || '{}');
    } catch (_) {
        return;
    }

    const areas = Array.isArray(catalog.areas) ? catalog.areas : [];
    const facilityPanel = form.querySelector('[data-admin-facility-questions]');
    const facilityFields = [...form.querySelectorAll('[data-area-question]')];
    const facilityKindControl = form.querySelector('[data-admin-facility-kind]');
    const areasById = new Map(areas.map(area => [String(area.id), area]));
    const isModernFacility = value =>
        String(value) === 'new' || /^resource:[1-9][0-9]*$/.test(String(value));
    const areaShell = form.closest('[data-contribution-tabs]');
    const areaPicker = areaShell?.querySelector('[data-admin-area-picker]');
    const currentMode = () =>
        areaShell?.querySelector('[data-contribution-mode="facility"]')
            ?.getAttribute('aria-pressed') === 'true';
    let previouslyEditingArea = false;
    const areaDrafts = new Map();
    const syncAreaMode = () => {
        const isArea = currentMode();
        if (areaPicker) areaPicker.hidden = !isArea;

        // Shared form controls must never retain Area answers when switching
        // back to Place or Site. Preserve the unsaved Area draft by identity.
        if (!isArea) {
            if (previouslyEditingArea) {
                if (selected) areaDrafts.set(selected, read());
                write(placeValues);
                target.value = '';
            }
            previouslyEditingArea = false;
            return;
        }

        if (!previouslyEditingArea) {
            placeValues = read();
            previouslyEditingArea = true;
            if (selected && allowed.has(selected)) {
                write(areaDrafts.get(selected) || effectiveValues(selected));
            }
        }
        if (!areas.length || isModernFacility(selector.value)) return;
        if (!allowed.has(selector.value)) {
            selector.value = String(areas[0].id);
        }
        // A valid preselected option still needs its initial answer load.
        if (selector.value !== selected) {
            selector.dispatchEvent(new Event('change', {bubbles: true}));
        } else {
            target.value = selected;
        }
    };
    // The same server-verified Area classification controls what can be
    // edited. Facility questions and camping/parking questions never mix.
    const facilityAllowed = (key, areaId) => {
        const node = facilityFields.find(field =>
            field.getAttribute('data-report-field') === key);
        if (!node) return true; // Existing shared canonical questions.
        const area = areasById.get(String(areaId));
        if (!area) return false;
        const allowedKinds = (node.dataset.facilityKinds || '')
            .split(',').filter(Boolean);
        const allowedFeatures = (node.dataset.areaFeatureTypes || '')
            .split(',').filter(Boolean);
        if (allowedFeatures.length) {
            return allowedFeatures.includes(String(area.feature_type || ''));
        }
        // The metadata editor owns the current draft kind. Use the same
        // prospective kind that the server will verify after metadata save.
        const kind = facilityKindControl && String(areaId) === String(selector.value)
            ? facilityKindControl.value : (area.facility_kind || '');
        return area.feature_type === 'facility_area'
            && allowedKinds.includes(String(kind));
    };
    const syncFacilityQuestions = areaId => {
        const visible = currentMode() && areasById.has(String(areaId));
        if (facilityPanel) facilityPanel.hidden = !visible;
        for (const field of facilityFields) {
            const key = field.getAttribute('data-report-field') || '';
            field.hidden = !visible || !facilityAllowed(key, areaId);
        }
        for (const group of form.querySelectorAll('[data-admin-area-question-group]')) {
            group.hidden = !visible
                || !group.querySelector('[data-area-question]:not([hidden])');
        }
    };
    const fieldKeys = new Set(Array.isArray(catalog.field_keys) ? catalog.field_keys : []);
    const controls = new Map();
    for (const control of form.elements) {
        const name = control.name.replace(/\[\]$/, '');
        if (!fieldKeys.has(name)) continue;
        if (!controls.has(name)) controls.set(name, []);
        controls.get(name).push(control);
    }
    if (!controls.size) {
        // A Place with no mapped Areas still has an Admin Facility picker.
        // New and resource-backed Facilities must remain selectable.
        selector.addEventListener('change', () => {
            const next = String(selector.value || '');
            if (next === '') return;
            const url = new URL(window.location.href);
            url.searchParams.set('mode', 'facility');
            url.searchParams.set('facility', next);
            url.hash = 'place-report';
            window.location.assign(url.toString());
        });
        return;
    }

    /*
     * `original` stores only explicit Area overrides. Effective values are
     * composed from the current Place values plus those overrides when an Area
     * is selected. This keeps inheritance visible without silently turning
     * inherited Place answers into Area overrides on save.
     */
    const original = new Map(areas.map(area => [
        String(area.id), structuredClone(area.form_values || {}),
    ]));

    const allowed = new Set(original.keys());
    let answersField = form.querySelector('[name="admin_area_answers_json"]');
    if (!answersField) {
        answersField = document.createElement('input');
        answersField.type = 'hidden';
        answersField.name = 'admin_area_answers_json';
        form.appendChild(answersField);
    }

    const read = () => {
        const values = {};
        for (const [name, group] of controls) {
            const radios = group.filter(control => control.type === 'radio');
            const editable = group.filter(control => control.type !== 'hidden');
            const first = editable[0] || group[0];
            // Shared Yes/No controls include a hidden unanswered fallback.
            // Read the selected radio instead of the first hidden input.
            if (radios.length) {
                values[name] = radios.find(control => control.checked)?.value ?? '';
            } else if (first.type === 'checkbox' && editable.length === 1) {
                values[name] = first.checked ? '1' : '0';
            } else if (first.type === 'checkbox') {
                values[name] = editable.filter(control => control.checked).map(control => control.value);
            } else if (first instanceof HTMLSelectElement && first.multiple) {
                values[name] = [...first.selectedOptions].map(option => option.value);
            } else {
                values[name] = first.value;
            }
        }
        return values;
    };

    let applying = false;

    const write = values => {
        applying = true;
        try {
            for (const [name, group] of controls) {
                const value = values[name] ?? '';
                for (const control of group) {
                    if (control.type === 'hidden') {
                        // The shared form's unanswered fallback is not the
                        // editable value and must never be overwritten.
                        continue;
                    }
                    if (control.type === 'radio') {
                        control.checked = control.value === String(value);
                    } else if (control.type === 'checkbox') {
                        control.checked = Array.isArray(value)
                            ? value.includes(control.value)
                            : value === true || value === '1' || value === 1;
                    } else if (control instanceof HTMLSelectElement && control.multiple) {
                        for (const option of control.options) {
                            option.selected = Array.isArray(value) && value.includes(option.value);
                        }
                    } else {
                        control.value = String(value);
                    }
                }
            }
        } finally {
            applying = false;
        }

        form.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles: true
                }
            )
        );
    };

    const equal =
        (a, b) =>
            JSON.stringify(a)
            === JSON.stringify(b);

    let placeValues =
        read();

    const effectiveValues =
        (areaId) => ({
            ...placeValues,
            ...(
                original.get(areaId)
                || {}
            ),
        });

    /*
     * Calculate only the explicit Area changes that need persistence.
     *
     * If the displayed value still equals the inherited Place value, no Area
     * override is created. If an existing Area override is changed back to the
     * Place value, submit an empty value so the override is cleared and normal
     * inheritance resumes.
     */
    const changes =
        (
            beforeOverrides,
            after
        ) => {
            const result = {};

            for (
                const key
                of controls.keys()
            ) {
                if (!facilityAllowed(key, selected)) continue;
                const inherited =
                    Object.prototype.hasOwnProperty.call(
                        placeValues,
                        key
                    )
                        ? placeValues[key]
                        : '';

                const hadOverride =
                    Object.prototype.hasOwnProperty.call(
                        beforeOverrides,
                        key
                    );

                const beforeEffective =
                    hadOverride
                        ? beforeOverrides[key]
                        : inherited;

                const value =
                    after[key];

                if (
                    equal(
                        beforeEffective,
                        value
                    )
                ) {
                    continue;
                }

                if (
                    hadOverride
                    && equal(
                        inherited,
                        value
                    )
                ) {
                    result[key] =
                        '';
                    continue;
                }

                if (
                    !hadOverride
                    && equal(
                        inherited,
                        value
                    )
                ) {
                    continue;
                }

                result[key] =
                    value;
            }

            return result;
        };

    let selected = '';
    let dirty = false;
    let saving = false;
    let pending = null;
    let pendingChanges = null;

    const finish =
        (success) => {
            if (!saving) return;

            saving = false;

            if (
                selected
                && pending
            ) {
                if (success) {
                    const newBaseline = {
                        ...(
                            original.get(
                                selected
                            )
                            || {}
                        )
                    };

                    for (
                        const [key, value]
                        of Object.entries(
                            pendingChanges
                            || {}
                        )
                    ) {
                        if (
                            value === ''
                            || value === null
                            || value === '__LLAMA_UNANSWERED__'
                        ) {
                            delete newBaseline[key];
                        } else {
                            newBaseline[key] =
                                value;
                        }
                    }

                    original.set(
                        selected,
                        newBaseline
                    );

                    dirty =
                        false;
                    const area = areasById.get(selected);
                    if (area && facilityKindControl && area.feature_type === 'facility_area') {
                        area.facility_kind = facilityKindControl.value;
                    }
                }

                write(
                    success
                        ? effectiveValues(
                            selected
                        )
                        : pending
                );
            }

            pending = null;
            pendingChanges = null;
            answersField.value = '';
            target.value = selected;
            syncFacilityQuestions(selected);
        };

    selector.addEventListener(
        'change',
        () => {
            if (saving) {
                selector.value =
                    selected;
                return;
            }

            const next =
                selector.value;

            // The entire resource-backed editor uses a distinct, existing
            // Facility save adapter. Rebind on a fresh page when switching
            // from one storage owner to the other, never leave the wrong
            // editor visible for the selected Facility.
            if (areaShell?.dataset.adminFacilitySource === 'resource'
                || isModernFacility(next)) {
                if (dirty && selected && !window.confirm('Discard unsaved Facility answers?')) {
                    selector.value = selected;
                    return;
                }
                const url = new URL(window.location.href);
                url.searchParams.set('mode', 'facility');
                url.searchParams.set('facility', next);
                url.hash = 'place-report';
                window.location.assign(url.toString());
                return;
            }
            if (
                (
                    next
                    && !allowed.has(next)
                )
                || next === selected
            ) {
                selector.value =
                    selected;
                return;
            }

            if (
                dirty
                && selected
                && !window.confirm(
                    'Discard unsaved Facility answers?'
                )
            ) {
                selector.value =
                    selected;
                return;
            }

            if (!selected) {
                placeValues =
                    read();
            }

            selected =
                next;

            dirty =
                false;

            answersField.value =
                '';

            target.value =
                selected;

            write(
                selected
                    ? effectiveValues(
                        selected
                    )
                    : placeValues
            );
            syncFacilityQuestions(selected);
            form.dispatchEvent(new CustomEvent('llama:admin-facility-selected', {
                detail: {facilityId: selected}
            }));
        }
    );

    for (
        const group
        of controls.values()
    ) {
        for (
            const control
            of group
        ) {
            const markDirty =
                () => {
                    if (
                        !applying
                        && !saving
                        && selected
                    ) {
                        dirty =
                            true;
                    }
                };

            control.addEventListener(
                'input',
                markDirty
            );

            control.addEventListener(
                'change',
                markDirty
            );
        }
    }

    form.addEventListener(
        'llama:admin-place-report-save-starting',
        (event) => {
            answersField.value =
                '';

            if (!currentMode()) {
                // The Area selector is not an alternate way to save Place
                // values. Place and Site modes have their own save targets.
                target.value = '';
                return;
            }

            // An old Site dropdown selection may remain when the user
            // switches to Area. The active tab, not that stale value,
            // determines which target is saved.

            if (
                !selected
                || !allowed.has(
                    selected
                )
            ) {
                target.value =
                    '';
                return;
            }

            pending =
                read();

            pendingChanges =
                changes(
                    original.get(
                        selected
                    )
                    || {},
                    pending
                );

            for (const key of event.detail?.bulkSiteFields ?? []) {
                delete pendingChanges[key];
            }

            answersField.value =
                JSON.stringify(
                    pendingChanges
                );

            target.value =
                selected;

            saving =
                true;

            /*
             * The canonical Place save must receive Place values, not the
             * Area's effective/inherited display values.
             */
            write(
                placeValues
            );
        }
    );

    window.addEventListener(
        'llama:admin-place-report-saved',
        () =>
            finish(
                true
            )
    );

    const observer =
        new MutationObserver(
            () => {
                if (!saving) {
                    return;
                }

                const status =
                    form.querySelector(
                        '[data-place-report-direct-save-status]'
                    );

                if (
                    status
                    && /Not saved\./i.test(
                        status.textContent
                        || ''
                    )
                ) {
                    finish(
                        false
                    );
                }
            }
        );

    observer.observe(
        form,
        {
            childList: true,
            subtree: true,
            characterData: true
        }
    );

    /*
     * Problem-report links can open this editor directly on the affected
     * Area or Site. Reuse the existing selectors so all normal loading,
     * inheritance, photo, and save behavior still runs through the same code.
     */
    const openRequestedReportTarget = () => {
        const params =
            new URLSearchParams(
                window.location.search
            );

        const scope =
            String(
                params.get(
                    'report_scope'
                )
                || ''
            ).trim();

        const source =
            String(
                params.get(
                    'report_source'
                )
                || ''
            ).trim();

        const id =
            String(
                params.get(
                    'report_target'
                )
                || ''
            ).trim();

        if (
            id === ''
            || ![
                'area',
                'site'
            ].includes(
                scope
            )
        ) {
            return;
        }

        if (scope === 'area') {
            const area =
                areas.find(
                    (candidate) =>
                        String(
                            candidate.scope_id
                            || candidate.id
                            || ''
                        ) === id
                        && (
                            source === ''
                            || String(
                                candidate.scope_source
                                || 'map_feature'
                            ) === source
                        )
                );

            if (!area) {
                return;
            }

            selector.value =
                String(
                    area.id
                );

            selector.dispatchEvent(
                new Event(
                    'change',
                    {
                        bubbles: true
                    }
                )
            );

            form.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            return;
        }

        if (!siteSelector) {
            return;
        }

        const siteOption =
            Array.from(
                siteSelector.options
            ).find(
                (option) =>
                    option.value === id
            );

        if (!siteOption) {
            return;
        }

        siteSelector.value =
            id;

        siteSelector.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles: true
                }
            )
        );

        form.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    };

    // Register the mapped-Area listeners only after all state is initialized.
    // Resource-only pages return earlier and must not call the legacy reader.
    areaShell?.addEventListener('llama:contribution-mode', () => {
        syncAreaMode();
        syncFacilityQuestions(selector.value);
    });
    facilityKindControl?.addEventListener('change', () => {
        syncFacilityQuestions(selector.value);
    });

    queueMicrotask(
        openRequestedReportTarget
    );
    syncAreaMode();
    syncFacilityQuestions(selector.value);
})();
