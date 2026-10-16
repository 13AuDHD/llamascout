(() => {
    'use strict';

    const form = document.getElementById('place-report');
    const catalogNode = form?.querySelector('[data-admin-place-areas]');
    const selector = form?.querySelector('[data-admin-area-selector]');
    const target = form?.querySelector('[name="selected_area_id"]');
    const siteSelector = form?.querySelector('[data-admin-campsite-selector]');
    if (!form || !catalogNode || !selector || !target) return;

    let catalog;
    try {
        catalog = JSON.parse(catalogNode.textContent || '{}');
    } catch (_) {
        return;
    }

    const areas = Array.isArray(catalog.areas) ? catalog.areas : [];
    const fieldKeys = new Set(Array.isArray(catalog.field_keys) ? catalog.field_keys : []);
    const controls = new Map();
    for (const control of form.elements) {
        if (!fieldKeys.has(control.name)) continue;
        if (!controls.has(control.name)) controls.set(control.name, []);
        controls.get(control.name).push(control);
    }
    if (!controls.size) return;

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
            const first = group[0];
            if (first.type === 'radio') {
                values[name] = group.find(control => control.checked)?.value ?? '';
            } else if (first.type === 'checkbox' && group.length === 1) {
                values[name] = first.checked ? '1' : '0';
            } else if (first.type === 'checkbox') {
                values[name] = group.filter(control => control.checked).map(control => control.value);
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
                            || (
                                Array.isArray(value)
                                && value.length === 0
                            )
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
                    'Discard unsaved Area answers?'
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
        () => {
            answersField.value =
                '';

            if (
                String(
                    siteSelector?.value
                    || ''
                ).trim() !== ''
            ) {
                target.value =
                    '';
                return;
            }

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

    queueMicrotask(
        openRequestedReportTarget
    );
})();
