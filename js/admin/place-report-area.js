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

    const states = new Map(areas.map(area => [
        String(area.id), { ...(area.form_values || {}) },
    ]));
    const allowedTargets = new Set(states.keys());
    const payloadField = document.createElement('input');
    payloadField.type = 'hidden';
    payloadField.name = 'admin_area_answers_json';
    form.appendChild(payloadField);

    const read = () => {
        const values = {};
        const names = new Set(inputs.map(input => input.name));
        for (const name of names) {
            const controls = inputs.filter(input => input.name === name);
            const first = controls[0];
            if (first.type === 'radio') {
                const checked = controls.find(input => input.checked);
                // An intentionally unanswered radio clears a previous override.
                values[name] = checked ? checked.value : '';
            } else if (first.type === 'checkbox' && controls.length === 1) {
                values[name] = first.checked ? '1' : '0';
            } else if (first.type === 'checkbox') {
                values[name] = controls.filter(input => input.checked).map(input => input.value);
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
        for (const input of inputs) {
            const value = values[input.name] ?? '';
            if (input.type === 'radio') {
                input.checked = input.value === String(value);
            } else if (input.type === 'checkbox') {
                input.checked = Array.isArray(value)
                    ? value.includes(input.value)
                    : value === true || value === '1' || value === 1;
            } else if (input instanceof HTMLSelectElement && input.multiple) {
                const selected = Array.isArray(value) ? value : [];
                for (const option of input.options) option.selected = selected.includes(option.value);
            } else {
                input.value = String(value);
            }
        }
        swapping = false;
        form.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const placeValues = read();
    let selected = '';
    let dirty = false;
    let saving = false;
    let pendingAreaValues = null;

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
        if (dirty && selected && next !== selected &&
            !window.confirm('Discard unsaved Area answers?')) {
            selector.value = selected;
            return;
        }
        if (!selected) Object.assign(placeValues, read());
        selected = next;
        targetField.value = next;
        dirty = false;
        payloadField.value = '';
        write(next ? states.get(next) || {} : placeValues);
    });

    const markDirty = () => {
        if (!swapping && !saving && selected) dirty = true;
    };
    for (const input of inputs) {
        input.addEventListener('input', markDirty);
        input.addEventListener('change', markDirty);
    }

    // The existing save owner fires this synchronously before FormData(form).
    form.addEventListener('llama:admin-place-report-save-starting', () => {
        if (!selected || !allowedTargets.has(selected)) {
            payloadField.value = '';
            targetField.value = '';
            return;
        }
        pendingAreaValues = read();
        payloadField.value = JSON.stringify(pendingAreaValues);
        targetField.value = selected;
        saving = true;
        // Restore Place values so the canonical Place save cannot absorb Area data.
        write(placeValues);
    });

    window.addEventListener('llama:admin-place-report-saved', () => {
        if (!saving || !selected || !pendingAreaValues) return;
        states.set(selected, pendingAreaValues);
        dirty = false;
        saving = false;
        write(pendingAreaValues);
        pendingAreaValues = null;
        payloadField.value = '';
    });

    // Do not leave the visible editor displaying Place values after a failed
    // background request. The primary save script reports failure in its status.
    const observer = new MutationObserver(() => {
        const status = form.querySelector('[data-place-report-direct-save-status]');
        if (!saving || !status || !/Not saved\./i.test(status.textContent || '')) return;
        saving = false;
        if (pendingAreaValues && selected) write(pendingAreaValues);
        pendingAreaValues = null;
        payloadField.value = '';
    });
    observer.observe(form, { childList: true, subtree: true, characterData: true });
})();
