(() => {
    'use strict';

    document
        .querySelectorAll(
            '[data-delete-verification]'
        )
        .forEach((button) => {
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
        });
})();
