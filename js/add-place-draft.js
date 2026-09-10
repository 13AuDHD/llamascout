(() => {
    'use strict';

    const none = document.querySelector('input[name="amenity_none"]');
    if (!none) return;

    const amenityBoxes = [...document.querySelectorAll('input[name^="amenity_"]')]
        .filter((input) => input !== none);

    const syncFromNone = () => {
        if (!none.checked) return;
        amenityBoxes.forEach((input) => { input.checked = false; });
    };

    none.addEventListener('change', syncFromNone);

    amenityBoxes.forEach((input) => {
        input.addEventListener('change', () => {
            if (input.checked) none.checked = false;
        });
    });

    syncFromNone();
})();
