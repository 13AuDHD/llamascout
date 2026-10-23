(() => {
    'use strict';

    document.querySelectorAll('[data-campsite-amenity]').forEach(field => {
        field.querySelector('[data-place-report-clear]')?.addEventListener('click', () => {
            field.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.checked = false;
            });
            field.querySelector('input[type="radio"]')?.dispatchEvent(
                new Event('change', {bubbles: true})
            );
        });
    });
})();
