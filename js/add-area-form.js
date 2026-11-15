document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-area-create-form]');
    if (!form) return;
    const type = form.querySelector('[data-area-type]');
    const kind = form.querySelector('[data-area-facility-kind]');
    const kindRow = form.querySelector('[data-area-facility-selector]');
    const questions = Array.from(form.querySelectorAll('[data-area-question]'));
    const update = () => {
        const areaType = type.value;
        const facilityKind = kind.value;
        const facility = areaType === 'facility_area';
        kindRow.hidden = !facility;
        kind.disabled = !facility;
        kind.required = facility;
        for (const fieldset of questions) {
            const acceptedTypes = (fieldset.dataset.areaTypes || '').split(',').filter(Boolean);
            const acceptedKinds = (fieldset.dataset.facilityKinds || '').split(',').filter(Boolean);
            const applies = (!acceptedTypes.length || acceptedTypes.includes(areaType))
                && (!acceptedKinds.length || (facility && acceptedKinds.includes(facilityKind)));
            fieldset.hidden = !applies;
            fieldset.disabled = !applies;
        }
        // Hide sections without any applicable questions.
        for (const section of form.querySelectorAll('.area-question-group')) {
            section.hidden = !Array.from(section.querySelectorAll('[data-area-question]'))
                .some(fieldset => !fieldset.hidden);
        }
    };
    type.addEventListener('change', update);
    kind.addEventListener('change', update);
    form.addEventListener('click', event => {
        const clear = event.target.closest('[data-clear-area-answer]');
        if (!clear) return;
        const field = clear.closest('[data-area-question]');
        for (const radio of field.querySelectorAll('input[type="radio"]')) {
            radio.checked = false;
        }
    });
    // Explicit Unknown means the other multiselect answers were not determined.
    form.addEventListener('change', event => {
        const unknown = event.target.closest('input[name^="area_unknown["]');
        if (!unknown || !unknown.checked) return;
        const field = unknown.closest('[data-area-question]');
        field.querySelectorAll('input[type="checkbox"]:not([name^="area_unknown["])')
            .forEach(input => { input.checked = false; });
    });
    // A newly submitted Place may include its first Area in the same
    // immutable review bundle as the first Site. No preexisting Place ID,
    // coordinates or Area geometry are required for this path.
    const newPlaceToggle = document.querySelector('[data-area-bundle-new]');
    const bundleInput = document.querySelector('[data-area-bundle-input]');
    const attachButton = form.querySelector('[data-area-attach-new]');
    const submitNew = form.querySelector('[data-area-submit-new]');
    const submitExisting = form.querySelector('[data-area-submit-existing]');
    const parentRow = form.querySelector('[data-area-existing-parent]');
    const parent = form.querySelector('[name="area_place_id"]');
    const search = document.querySelector('[data-area-search-existing]');
    const status = document.querySelector('[data-area-bundle-status]');

    const captureBundle = () => {
        if (!form.reportValidity()) return false;
        const data = new FormData(form);
        const bundle = {
            area_name: String(data.get('area_name') || '').trim(),
            area_feature_type: String(data.get('area_feature_type') || ''),
            area_facility_kind: String(data.get('area_facility_kind') || ''),
            area_answers: {},
            area_unknown: {}
        };
        for (const [key, value] of data.entries()) {
            const answer = key.match(/^area_answers\[([^\]]+)\](\[\])?$/);
            const unknown = key.match(/^area_unknown\[([^\]]+)\]$/);
            if (answer) {
                if (answer[2]) {
                    (bundle.area_answers[answer[1]] ||= []).push(value);
                } else {
                    bundle.area_answers[answer[1]] = value;
                }
            } else if (unknown) {
                bundle.area_unknown[unknown[1]] = value;
            }
        }
        if (!bundle.area_name || !bundle.area_feature_type
            || (bundle.area_feature_type === 'facility_area' && !bundle.area_facility_kind)) {
            return false;
        }
        bundleInput.value = JSON.stringify(bundle);
        status.hidden = false;
        status.textContent = 'Area attached to this new Place submission. Submit the Place and Area together for review; no map point required.';
        submitNew.hidden = false;
        return true;
    };

    const showMode = () => {
        const bundled = Boolean(newPlaceToggle?.checked);
        if (search) search.hidden = bundled;
        if (parentRow) parentRow.hidden = bundled;
        if (parent) {
            parent.required = !bundled;
            parent.disabled = bundled;
        }
        if (submitExisting) submitExisting.hidden = bundled;
        if (attachButton) attachButton.hidden = !bundled;
        if (submitNew) submitNew.hidden = !bundled || !bundleInput?.value;
        if (status && !bundled) status.hidden = true;
    };

    const restoreBundle = () => {
        if (!bundleInput?.value) return;
        try {
            const previous = JSON.parse(bundleInput.value);
            if (!previous || typeof previous !== 'object') return;
            newPlaceToggle.checked = true;
            form.querySelector('[name="area_name"]').value = String(previous.area_name || '');
            type.value = String(previous.area_feature_type || previous.feature_type || 'facility_area');
            kind.value = String(previous.area_facility_kind || previous.facility_kind || '');
            update();
            const answers = previous.area_answers || previous.answers || {};
            const unknowns = previous.area_unknown || {};
            for (const field of questions) {
                for (const input of field.querySelectorAll('input, select, textarea')) {
                    const name = input.name || '';
                    const match = name.match(/^area_answers\[([^\]]+)\]/);
                    const unknown = name.match(/^area_unknown\[([^\]]+)\]/);
                    const value = match ? answers[match[1]] : undefined;
                    if (match) {
                        if (input.type === 'checkbox') {
                            input.checked = Array.isArray(value) && value.includes(input.value);
                        } else if (input.type === 'radio') {
                            input.checked = String(value ?? '') === input.value;
                        } else {
                            input.value = typeof value === 'string' || typeof value === 'number'
                                ? String(value) : '';
                        }
                    } else if (unknown) {
                        input.checked = Boolean(unknowns[unknown[1]]);
                    }
                }
            }
        } catch (_) {
            bundleInput.value = '';
        }
    };
    newPlaceToggle?.addEventListener('change', () => {
        if (!newPlaceToggle.checked && bundleInput) bundleInput.value = '';
        showMode();
    });
    attachButton?.addEventListener('click', captureBundle);
    submitNew?.addEventListener('click', event => {
        if (!captureBundle()) event.preventDefault();
    });
    const placeForm = document.querySelector('#add-place-main-form');
    placeForm?.addEventListener('submit', event => {
        // The contributor can finish the Place and Site tabs after staging
        // an Area, then submit from either tab. Capture the latest Area fields.
        if (newPlaceToggle?.checked && !captureBundle()) {
            event.preventDefault();
        }
    });
    form.addEventListener('change', () => {
        if (newPlaceToggle?.checked && bundleInput?.value) captureBundle();
    });
    restoreBundle();
    update();
    showMode();
});
