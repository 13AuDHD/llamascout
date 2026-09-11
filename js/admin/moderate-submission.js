(() => {
    'use strict';

    const button =
        document.querySelector(
            '[data-delete-submission]'
        );

    if (!button) {
        return;
    }

    let armed = false;

    button.addEventListener(
        'click',
        (event) => {
            if (armed) {
                return;
            }

            event.preventDefault();

            armed = true;

            button.classList.add(
                'is-armed'
            );
        }
    );
})();
