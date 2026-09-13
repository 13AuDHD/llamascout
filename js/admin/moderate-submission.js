(() => {
    'use strict';

    const buttons =
        Array.from(
            document.querySelectorAll(
                '[data-delete-submission]'
            )
        );

    if (!buttons.length) {
        return;
    }

    buttons.forEach(
        (button) => {
            let armed = false;
            let resetTimer = null;

            const originalHtml =
                button.innerHTML;

            const disarm = () => {
                armed = false;

                button.classList.remove(
                    'is-armed'
                );

                button.removeAttribute(
                    'aria-describedby'
                );

                button.innerHTML =
                    originalHtml;

                if (resetTimer) {
                    window.clearTimeout(
                        resetTimer
                    );

                    resetTimer = null;
                }
            };

            button.addEventListener(
                'click',
                (event) => {
                    if (armed) {
                        /*
                         * Second click is intentional confirmation.
                         * Allow the normal form submission to proceed.
                         */
                        return;
                    }

                    event.preventDefault();

                    armed = true;

                    button.classList.add(
                        'is-armed'
                    );

                    button.innerHTML =
                        '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>'
                        + '<span>Click Again to Delete</span>';

                    resetTimer =
                        window.setTimeout(
                            disarm,
                            6000
                        );
                }
            );

            button.addEventListener(
                'blur',
                () => {
                    /*
                     * Do not disarm immediately on touch devices.
                     * The timeout remains the safety reset.
                     */
                }
            );
        }
    );
})();
