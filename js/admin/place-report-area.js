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
    const controls = new Map();
    for (const input of form.elements) {
        if (!keys.has(input.name)) continue;
        if (!controls.has(input.name)) controls.set(input.name, []);
        controls.get(input.name).push(input);
    }
    if (!controls.size) return;

    const initial = new Map(areas.map(area => [
        String(area.id), { ...(area.form_values || {}) },
    ]));
    const current = new Map([...initial].map(([id, values]) => [id, { ...values }]));
    const allowed = new Set(initial.keys());

    let payload = form.querySelector('[name="admin_area_answers_json"]');
    if (!payload) {
        payload = document.createElement('input');
        payload.type = 'hidden';
        payload.name = 'admin_area_answers_json';
        form.appendChild(payload);
    }

    const read = () => {
        const values = {};
        for (const [name, group] of controls) {
            const first = group[0];
            if (first.type === 'radio') {
                values[name] = group.find(input => input.checked)?.value ?? '';
            } else if (first.type === 'checkbox' && group.length === 1) {
                values[name] = first.checked ? '1' : '0';
            } else if (first.type === 'checkbox') {
                values[name] = group.filter(input => input.checked).map(input => input.value);
            } else if (first instanceof HTMLSelectElement && first.multiple) {
                values[name] = [...first.selectedOptions].map(option => option.value);
            } else {
                values[name] = first.value;
            }
        }
        return values;
    };

    let changing = false;
    const write = values => {
        changing = true;
        try {
            for (const [name, group] of controls) {
                const value = values[name] ?? '';
                for (const input of group) {
                    if (input.type === 'radio') {
                        input.checked = input.value === String(value);
                    } else if (input.type === 'checkbox') {
                        input.checked = Array.isArray(value)
                            ? value.includes(input.value)
                            : value === true || value === '1' || value === 1;
                    } else if (input instanceof HTMLSelectElement && input.multiple) {
                        for (const option of input.options) {
                            option.selected = Array.isArray(value) && value.includes(option.value);
                        }
                    } else {
                        input.value = String(value);
                    }
                }
            }
        } finally {
            changing = false;
        }
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    let place = read();
    let selected = '';
    let dirty = false;
    let saving = false;
    let pending = null;

    const finish = success => {
        if (!saving) return;
        saving = false;
        if (selected && pending) {
            if (success) {
                current.set(selected, { ...pending });
                dirty = false;
            }
            write(pending);
        }
        pending = null;
        payload.value = '';
        targetField.value = selected;
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
        if (selected) {
            // A discarded edit must not silently become another Area's data.
            current.set(selected, { ...(initial.get(selected) || {}) });
        } else {
            place = read();
        }
        selected = next;
        targetField.value = selected;
        payload.value = '';
        dirty = false;
        write(selected ? current.get(selected) || {} : place);
    });

    for (const group of controls.values()) {
        for (const input of group) {
            const markDirty = () => {
                if (!changing && !saving && selected) dirty = true;
            };
            input.addEventListener('input', markDirty);
            input.addEventListener('change', markDirty);
        }
    }

    form.addEventListener('llama:admin-place-report-save-starting', () => {
        payload.value = '';
        if (!selected || !allowed.has(selected)) {
            targetField.value = '';
            return;
        }
        pending = read();
        payload.value = JSON.stringify(pending);
        targetField.value = selected;
        saving = true;
        // Restore Place answers before the canonical saver builds FormData.
        write(place);
    });

    window.addEventListener('llama:admin-place-report-saved', () => finish(true));

    const observer = new MutationObserver(() => {
        if (!saving) return;
        const status = form.querySelector('[data-place-report-direct-save-status]');
        if (status && /Not saved\./i.test(status.textContent || '')) {
            finish(false);
        }
    });
    observer.observe(form, { childList: true, subtree: true, characterData: true });
})();
