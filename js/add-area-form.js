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
    update();
});
