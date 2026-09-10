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
     * Important:
     * Do NOT disable the submit button during Safari's native
     * submit event. iOS/iPadOS Safari can interfere with the
     * form POST when the active submitter becomes disabled.
     *
     * The first click proceeds normally. Later clicks are
     * blocked in JavaScript while the page is navigating.
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

    saveButton.addEventListener(
        'click',
        (event) => {
            if (saving) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            saving = true;

            saveButton.setAttribute(
                'aria-disabled',
                'true'
            );

            saveButton.classList.add(
                'is-saving'
            );

            saveButton.innerHTML = `
                <i
                    class="fa-solid fa-spinner fa-spin"
                    aria-hidden="true"
                ></i>
                Saving...
            `;

            /*
             * Do not call preventDefault().
             * Do not set disabled=true.
             *
             * The original button remains the native submitter,
             * so its name/value:
             *
             * save_for_later=1
             *
             * is included naturally in the POST.
             */
        }
    );

    /*
     * If Safari restores this page from its back-forward cache,
     * restore the button so it is usable again.
     */
    window.addEventListener(
        'pageshow',
        (event) => {
            if (!event.persisted) {
                return;
            }

            saving = false;

            saveButton.removeAttribute(
                'aria-disabled'
            );

            saveButton.classList.remove(
                'is-saving'
            );

            saveButton.innerHTML = `
                <i
                    class="fa-solid fa-floppy-disk"
                    aria-hidden="true"
                ></i>
                Save for Later
            `;
        }
    );
})();
