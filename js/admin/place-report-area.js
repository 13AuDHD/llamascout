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
    const inputs = [...form.elements].filter(input => keys.has(input.name));
    if (!inputs.length) return;

    const state = new Map(areas.map(area => [String(area.id), { ...(area.form_values || {}) }]));
    let selected = '';
    let dirty = false;
    let swapping = false;

    const read = () => {
        const values = {};
        for (const input of inputs) {
            if (input.type === 'radio') {
                if (input.checked) values[input.name] = input.value;
            } else if (input.type === 'checkbox') {
                values[input.name] = input.checked ? '1' : '0';
            } else {
                values[input.name] = input.value;
            }
        }
        return values;
    };

    const placeValues = read();
    const write = values => {
        swapping = true;
        for (const input of inputs) {
            const value = values[input.name] ?? '';
            if (input.type === 'radio') {
                input.checked = input.value === String(value);
            } else if (input.type === 'checkbox') {
                input.checked = value === true || value === '1' || value === 1;
            } else {
                input.value = String(value);
            }
        }
        swapping = false;
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // The field values for an Area are never sent as Place answers.
    // The server receives a separate JSON payload with its own verified target.
    let payloadField = form.querySelector('[name="admin_area_answers_json"]');
    if (!payloadField) {
        payloadField = document.createElement('input');
        payloadField.type = 'hidden';
        payloadField.name = 'admin_area_answers_json';
        form.appendChild(payloadField);
    }

    selector.addEventListener('change', () => {
        const next = selector.value;
        if (dirty && selected && next !== selected &&
            !window.confirm('Discard unsaved Area answers?')) {
            selector.value = selected;
            return;
        }
        if (!selected) Object.assign(placeValues, read());
        selected = next;
        targetField.value = selected;
        dirty = false;
        write(selected ? state.get(selected) || {} : placeValues);
    });

    for (const input of inputs) {
        const markDirty = () => {
            if (swapping || !selected) return;
            dirty = true;
        };
        input.addEventListener('input', markDirty);
        input.addEventListener('change', markDirty);
    }

    form.addEventListener('llama:admin-place-report-save-starting', () => {
        payloadField.value = '';
        if (!selected) return;
        const answers = read();
        payloadField.value = JSON.stringify(answers);
        // Restore Place field values prior to the existing save owner's FormData call.
        write(placeValues);
        // Preserve the selected Area's target for the backend's separate save.
        targetField.value = selected;
    });

    window.addEventListener('llama:admin-place-report-saved', () => {
        if (!selected) return;
        state.set(selected, JSON.parse(payloadField.value || '{}'));
        dirty = false;
        write(state.get(selected));
    });
})();
