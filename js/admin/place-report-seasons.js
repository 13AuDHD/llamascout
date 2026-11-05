/* Admin Place Report seasonal editor. Progressive enhancement only. */
(() => {
    'use strict';

    const editor = document.querySelector('[data-season-answers-form]');
    const switchForm = document.querySelector('[data-season-switch]');

    let dirty = false;

    if (editor) {
        editor.addEventListener('input', () => {
            dirty = true;
        });
        editor.addEventListener('change', event => {
            const row = event.target.closest('[data-season-question]');
            if (!row) {
                dirty = true;
                return;
            }
            const mode = row.querySelector('[data-season-mode]');
            if (event.target.matches('[data-season-value]') && mode) {
                mode.value = 'answer';
            }
            dirty = true;
        });
    }

    if (!switchForm) {
        return;
    }

    switchForm.querySelectorAll('select').forEach(select => {
        let loadedValue = select.value;

        select.addEventListener('change', () => {
            if (dirty && !window.confirm(
                'Discard unsaved seasonal answers and load another season or Site?'
            )) {
                select.value = loadedValue;
                return;
            }
            loadedValue = select.value;
            switchForm.requestSubmit();
        });
    });
})();
