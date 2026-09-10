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
