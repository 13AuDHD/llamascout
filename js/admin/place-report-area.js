(() => {
    'use strict';

    const form = document.getElementById('place-report');
    const catalogNode = form?.querySelector('[data-admin-place-areas]');
    const selector = form?.querySelector('[data-admin-area-selector]');
    const targetField = form?.querySelector('[name="selected_area_id"]');
    if (!form || !catalogNode || !selector || !targetField) return;

    let catalog;
    try {
        catalog = JSON.parse(catalogNode.textContent || '{}');
    } catch (_) {
        return;
    }

    const areas = Array.isArray(catalog.areas) ? catalog.areas : [];
    const keys = new Set(Array.isArray(catalog.field_keys) ? catalog.field_keys : []);
    const controlsByName = new Map();
    for (const input of form.elements) {
        if (!keys.has(input.name)) continue;
        if (!controlsByName.has(input.name)) controlsByName.set(input.name, []);
        controlsByName.get(input.name).push(input);
    }
    if (!controlsByName.size) return;

    const initialStates = new Map(areas.map(area => [
        String(area.id), { ...(area.form_values || {}) },
    ]));
    const allowedTargets = new Set(initialStates.keys());
    const states = new Map(initialStates);

    let payloadField = form.querySelector('[name="admin_area_answers_json"]');
    if (!payloadField) {
        payloadField = document.createElement('input');
        payloadField.type = 'hidden';
        payloadField.name = 'admin_area_answers_json';
        form.appendChild(payloadField);
    }

    const read = () => {
        const values = {};
        for (const [name, controls] of controlsByName) {
            const first = controls[0];
            if (first.type === 'radio') {
                const checked = controls.find(control => control.checked);
                values[name] = checked ? checked.value : '';
            } else if (first.type === 'checkbox' && controls.length === 1) {
                values[name] = first.checked ? '1' : '0';
            } else if (first.type === 'checkbox') {
                values[name] = controls.filter(control => control.checked).map(control => control.value);
            } else if (first instanceof HTMLSelectElement && first.multiple) {
                values[name] = [...first.selectedOptions].map(option => option.value);
            } else {
                values[name] = first.value;
            }
        }
        return values;
    };

    let swapping = false;
    const write = values => {
        swapping = true;
        try {
            for (const [name, controls] of controlsByName) {
                const value = values[name] ?? '';
                for (const input of controls) {
                    if (input.type === 'radio') {
                        input.checked = input.value === String(value);
                    } else if (input.type === 'checkbox') {
                        input.checked = Array.isArray(value)
                            ? value.includes(input.value)
                            : value === true || value === '1' || value === 1;
                    } else if (input instanceof HTMLSelectElement && input.multiple) {
                        const selected = Array.isArray(value) ? value : [];
                        for (const option of input.options) {
                            option.selected = selected.includes(option.value);
                        }
                    } else {
                        input.value = String(value);
                    }
                }
            }
        } finally {
            swapping = false;
        }
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    let placeValues = read();
    let selected = '';
    let dirty = false;
    let saving = false;
    let pendingValues = null;

    const restoreAreaAfterSave = successful => {
        if (!saving) return;
        saving = false;
        if (selected && pendingValues) {
            if (successful) {
                states.set(selected, pendingValues);
                dirty = false;
            }
            write(pendingValues);
        }
        pendingValues = null;
        payloadField.value = '';
    };

    selector.addEventListener('change', () => {
        if (saving) {
            selector.value = selected;
            return;
        }
        const next = selector.value;
        if (next && !allowedTargets.has(next)) {
            selector.value = selected;
            return;
        }
        if (next === selected) return;
        if (dirty && selected && !window.confirm('Discard unsaved Area answers?')) {
            selector.value = selected;
            return;
        }
        if (selected) {
            // Unsaved Area edits must not leak into another Area's answers.
            states.set(selected, { ...(initialStates.get(selected) || {}) });
        } else {
            placeValues = read();
        }
        selected = next;
        targetField.value = next;
        dirty = false;
        payloadField.value = '';
        write(next ? states.get(next) || {} : placeValues);
    });

    const markDirty = () => {
        if (!swapping && !saving && selected) dirty = true;
    };
    for (const controls of controlsByName.values()) {
        for (const input of controls) {
            input.addEventListener('input', markDirty);
            input.addEventListener('change', markDirty);
        }
    }

    form.addEventListener('llama:admin-place-report-save-starting', () => {
        payloadField.value = '';
        if (!selected || !allowedTargets.has(selected)) {
            targetField.value = '';
            return;
        }
        pendingValues = read();
        payloadField.value = JSON.stringify(pendingValues);
        targetField.value = selected;
        saving = true;
        // The Place save sees only Place answers. Area data uses its own payload.
        write(placeValues);
    });

    window.addEventListener('llama:admin-place-report-saved', () => {
        restoreAreaAfterSave(true);
    });

    // The existing Admin saver owns network requests and error messages.
    // Restore the selected Area after it reports a failed save.
    const observer = new MutationObserver(() => {
        if (!saving) return;
        const status = form.querySelector('[data-place-report-direct-save-status]');
        if (status && /Not saved\./i.test(status.textContent || '')) {
            restoreAreaAfterSave(false);
        }
    });
    observer.observe(form, { childList: true, subtree: true, characterData: true });
})();
