(() => {
    'use strict';

    // The Admin Facility selector owns two existing storage adapters:
    // mapped Facilities (historical Area IDs) and Place-linked Facilities
    // (resource table IDs). Neither route ever creates a new Facility.
    const form = document.getElementById('place-report');
    const shell = form?.closest('[data-contribution-tabs]');
    const selector = form?.querySelector('[data-admin-facility-selector]');
    const catalogNode = form?.querySelector('[data-admin-facility-map-catalog]');
    const panel = form?.querySelector('[data-admin-facility-metadata]');
    if (!form || !shell || !selector || !panel || !catalogNode) return;
    let catalog;
    try { catalog = JSON.parse(catalogNode.textContent || '{}'); }
    catch (_) { return; }

    const name = panel.querySelector('[data-admin-facility-name]');
    const kind = panel.querySelector('[data-admin-facility-kind]');
    const kindWrap = panel.querySelector('[data-admin-facility-kind-wrap]');
    const notes = panel.querySelector('[data-admin-facility-notes]');
    const coords = panel.querySelector('[data-admin-facility-coordinates]');
    const touched = panel.querySelector('[data-admin-facility-coordinates-changed]');
    const hint = panel.querySelector('[data-admin-facility-map-hint]');
    const mapLink = panel.querySelector('[data-admin-facility-map-link]');
    if (!name || !kind || !notes || !coords || !touched || !hint) return;

    let applying = false;
    const isFacilityMode = () =>
        shell.querySelector('[data-contribution-mode="facility"]')?.getAttribute('aria-pressed') === 'true';
    const current = () => catalog[selector.value] || null;
    const update = () => {
        const data = current();
        panel.hidden = !isFacilityMode() || !data;
        applying = true;
        try {
            name.value = String(data?.name || '');
            kind.value = String(data?.facility_kind || '');
            notes.value = String(data?.notes || '');
            kindWrap.hidden = data?.feature_type !== 'facility_area';
            coords.value = String(data?.coordinates || '');
            coords.disabled = !data?.coordinates_editable;
            touched.value = '0';
            coords.setCustomValidity('');
            hint.textContent = data?.coordinates_editable
                ? 'Enter coordinates as latitude, longitude. This edits the existing Facility map point.'
                : 'This Facility uses a mapped boundary. Use Edit Map to change its geometry.';
            if (mapLink) {
                const url = new URL(mapLink.href);
                if (data?.id) url.searchParams.set('feature_id', String(data.id));
                mapLink.href = url.toString();
            }
        } finally { applying = false; }
    };
    const validate = () => {
        const data = current();
        if (!isFacilityMode() || !data) return true;
        if (!name.value.trim()) {
            name.setCustomValidity('Enter a Facility name.');
            panel.hidden = false;
            name.reportValidity();
            return false;
        }
        name.setCustomValidity('');
        if (touched.value !== '1') return true;
        if (!data.coordinates_editable) {
            hint.textContent = 'This location must be edited in Edit Map.';
            return false;
        }
        const value = coords.value.trim();
        const parts = value.split(',').map(x => x.trim());
        if (parts.length !== 2 || parts.some(x => !/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(x))
            || parts.some(x => !Number.isFinite(Number(x)))
            || Math.abs(Number(parts[0])) > 90 || Math.abs(Number(parts[1])) > 180
            || (Number(parts[0]) === 0 && Number(parts[1]) === 0)) {
            coords.setCustomValidity('Enter valid latitude, longitude.');
            panel.hidden = false;
            coords.reportValidity();
            return false;
        }
        coords.setCustomValidity('');
        return true;
    };
    coords.addEventListener('input', () => {
        if (!applying && current()) {
            touched.value = '1';
            coords.setCustomValidity('');
        }
    });
    name.addEventListener('input', () => name.setCustomValidity(''));

    form.addEventListener('llama:admin-place-report-before-save', event => {
        if (isFacilityMode() && current() && !validate()) event.preventDefault();
    });
    selector.addEventListener('change', update);
    shell.addEventListener('llama:contribution-mode', update);
    form.addEventListener('llama:admin-facility-selected', update);
    window.addEventListener('llama:admin-place-report-saved', () => {
        const record = current();
        if (!record || !isFacilityMode()) return;
        record.name = name.value.trim();
        record.notes = notes.value;
        record.facility_kind = kind.value;
        if (touched.value === '1') {
            record.coordinates = coords.value;
            touched.value = '0';
        }
        hint.textContent = 'Facility details saved.';
    });
    update();
})();
