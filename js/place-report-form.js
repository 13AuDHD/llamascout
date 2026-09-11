(() => {
    'use strict';

    document
        .querySelectorAll('.place-report-form')
        .forEach((form) => {
            form
                .querySelectorAll('[data-place-report-clear]')
                .forEach((button) => {
                    button.addEventListener(
                        'click',
                        () => {
                            const name =
                                button.getAttribute(
                                    'data-place-report-clear'
                                );

                            if (!name) {
                                return;
                            }

                            form
                                .querySelectorAll(
                                    `input[type="radio"][name="${CSS.escape(name)}"]`
                                )
                                .forEach((radio) => {
                                    radio.checked = false;
                                });
                        }
                    );
                });

            const noAmenities =
                form.querySelector(
                    '[data-place-report-no-amenities]'
                );

            const amenities =
                [
                    ...form.querySelectorAll(
                        '[data-place-report-amenity]'
                    ),
                ];

            if (noAmenities) {
                noAmenities.addEventListener(
                    'change',
                    () => {
                        if (!noAmenities.checked) {
                            return;
                        }

                        amenities.forEach((input) => {
                            input.checked = false;
                        });
                    }
                );

                amenities.forEach((input) => {
                    input.addEventListener(
                        'change',
                        () => {
                            if (
                                input.checked
                                && noAmenities.checked
                            ) {
                                noAmenities.checked = false;
                            }
                        }
                    );
                });
            }
        });
})();
