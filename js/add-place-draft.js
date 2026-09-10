(() => {
    'use strict';

    /*
     * =====================================================
     * NO AMENITIES
     * =====================================================
     */

    const none =
        document.querySelector(
            'input[name="amenity_none"]'
        );

    if (none) {
        const amenityBoxes = [
            ...document.querySelectorAll(
                'input[name^="amenity_"]'
            ),
        ].filter(
            (input) =>
                input !== none
        );

        const syncFromNone = () => {
            if (!none.checked) {
                return;
            }

            amenityBoxes.forEach(
                (input) => {
                    input.checked = false;
                }
            );
        };

        none.addEventListener(
            'change',
            syncFromNone
        );

        amenityBoxes.forEach(
            (input) => {
                input.addEventListener(
                    'change',
                    () => {
                        if (input.checked) {
                            none.checked = false;
                        }
                    }
                );
            }
        );

        syncFromNone();
    }


    /*
     * =====================================================
     * SAVE FOR LATER
     *
     * Preserve the server action before disabling the visible
     * submit button. Disabled submit buttons are not guaranteed
     * to be included in submitted form data.
     * =====================================================
     */

    const form =
        document.querySelector(
            '.add-place-form'
        );

    const saveButton =
        form?.querySelector(
            'button[name="save_for_later"]'
        );

    if (
        !form
        || !saveButton
    ) {
        return;
    }

    let saving = false;

    form.addEventListener(
        'submit',
        (event) => {
            const submitter =
                event.submitter;

            if (
                submitter !== saveButton
            ) {
                return;
            }

            if (saving) {
                event.preventDefault();
                return;
            }

            saving = true;

            /*
             * Preserve the action explicitly before disabling
             * the button so PHP always receives:
             * save_for_later=1
             */
            let actionInput =
                form.querySelector(
                    'input[data-save-for-later-action]'
                );

            if (!actionInput) {
                actionInput =
                    document.createElement(
                        'input'
                    );

                actionInput.type = 'hidden';
                actionInput.name =
                    'save_for_later';
                actionInput.value = '1';

                actionInput.setAttribute(
                    'data-save-for-later-action',
                    '1'
                );

                form.appendChild(
                    actionInput
                );
            }

            saveButton.disabled = true;

            saveButton.innerHTML = `
                <i
                    class="fa-solid fa-spinner fa-spin"
                    aria-hidden="true"
                ></i>
                Saving...
            `;
        }
    );
})();
