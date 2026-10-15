(() => {
    'use strict';

    const form = document.getElementById('place-report');
    const catalogNode = form?.querySelector('[data-admin-place-areas]');
    const selector = form?.querySelector('[data-admin-area-selector]');
    const target = form?.querySelector('[name="selected_area_id"]');
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
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const equal = (a, b) => JSON.stringify(a) === JSON.stringify(b);
    const changes = (before, after) => {
        const result = {};
        for (const key of controls.keys()) {
            const value = after[key];
            const existed = Object.prototype.hasOwnProperty.call(before, key);
            // Empty without a prior Area override means unanswered/inherited.
            if (!existed && (value === '' || value === null ||
                (Array.isArray(value) && value.length === 0))) continue;
            if (!existed && value === '0' && controls.get(key)?.[0]?.type === 'checkbox') continue;
            if (existed && equal(before[key], value)) continue;
            result[key] = value;
        }
        return result;
    };

    let placeValues = read();
    let selected = '';
    let dirty = false;
    let saving = false;
    let pending = null;
    let pendingChanges = null;

    const finish = success => {
        if (!saving) return;
        saving = false;
        if (selected && pending) {
            if (success) {
                const newBaseline = { ...(original.get(selected) || {}) };
                for (const [key, value] of Object.entries(pendingChanges || {})) {
                    if (value === '' || value === null) delete newBaseline[key];
                    else newBaseline[key] = value;
                }
                original.set(selected, newBaseline);
                dirty = false;
            }
            write(pending);
        }
        pending = null;
        pendingChanges = null;
        answersField.value = '';
        target.value = selected;
    };

    selector.addEventListener('change', () => {
        if (saving) {
            selector.value = selected;
            return;
        }
        const next = selector.value;
        if ((next && !allowed.has(next)) || next === selected) {
            selector.value = selected;
            return;
        }
        if (dirty && selected && !window.confirm('Discard unsaved Area answers?')) {
            selector.value = selected;
            return;
        }
        if (!selected) placeValues = read();
        selected = next;
        dirty = false;
        answersField.value = '';
        target.value = selected;
        write(selected ? original.get(selected) || {} : placeValues);
    });

    for (const group of controls.values()) {
        for (const control of group) {
            const markDirty = () => {
                if (!applying && !saving && selected) dirty = true;
            };
            control.addEventListener('input', markDirty);
            control.addEventListener('change', markDirty);
        }
    }

    form.addEventListener('llama:admin-place-report-save-starting', () => {
        answersField.value = '';
        if (!selected || !allowed.has(selected)) {
            target.value = '';
            return;
        }
        pending = read();
        pendingChanges = changes(original.get(selected) || {}, pending);
        answersField.value = JSON.stringify(pendingChanges);
        target.value = selected;
        saving = true;
        // The canonical Place save must receive Place values, not Area values.
        write(placeValues);
    });

    window.addEventListener('llama:admin-place-report-saved', () => finish(true));
    const observer = new MutationObserver(() => {
        if (!saving) return;
        const status = form.querySelector('[data-place-report-direct-save-status]');
        if (status && /Not saved\./i.test(status.textContent || '')) finish(false);
    });
    observer.observe(form, { childList: true, subtree: true, characterData: true });
})();
