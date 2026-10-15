(() => {
    'use strict';

    const form = document.getElementById('place-report');
    const catalogNode = form?.querySelector('[data-admin-place-areas]');
    const selector = form?.querySelector('[data-admin-area-selector]');
    if (!form || !catalogNode || !selector) return;

    let catalog;
    try {
        catalog = JSON.parse(catalogNode.textContent || '{}');
    } catch (_) {
        return;
    }

    const areas = Array.isArray(catalog.areas) ? catalog.areas : [];
    const keys = new Set(Array.isArray(catalog.field_keys) ? catalog.field_keys : []);
    const inputs = [...form.elements].filter(input => keys.has(input.name));
    const areaId = form.querySelector('[name="selected_area_id"]');
    if (!areaId || !inputs.length) return;

    // This is a field-state controller only. The PHP action remains the save owner.
    const state = new Map(areas.map(area => [String(area.id), { ...area.form_values }]));
    let selected = '';
    let dirty = false;
    // Preserve Place answers independently of Area answers.
    const placeValues = read();

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

    const write = values => {
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
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    selector.addEventListener('change', () => {
        const next = selector.value;
        if (dirty && selected && next !== selected &&
            !window.confirm('Discard unsaved Area answers?')) {
            selector.value = selected;
            return;
        }
        if (!selected) {
            Object.assign(placeValues, read());
        } else {
            state.set(selected, read());
        }
        selected = next;
        areaId.value = selected;
        dirty = false;
        write(selected ? state.get(selected) || {} : placeValues);
    });

    for (const input of inputs) {
        const markDirty = () => {
            if (!selected) return;
            dirty = true;
            state.set(selected, read());
        };
        input.addEventListener('input', markDirty);
        input.addEventListener('change', markDirty);
    }

    // The save owner fires this event before constructing FormData. Prevent
    // Area answers from being submitted as Place-level answer values.
    form.addEventListener('llama:admin-place-report-save-starting', () => {
        if (!selected) return;
        state.set(selected, read());
        write(placeValues);
        // Do not send the Area target with Place answers until the backend
        // supports a separate, atomic Area-answer payload.
        areaId.value = '';
        selector.value = '';
        selected = '';
        dirty = false;
    });

    window.addEventListener('llama:admin-place-report-saved', () => {
        if (selected) {
            state.set(selected, read());
            dirty = false;
        }
    });
})();
