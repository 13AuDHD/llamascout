(() => {
    'use strict';

    document
        .querySelectorAll(
            '.admin-badge-definition-form'
        )
        .forEach((form) => {
            const awardType =
                form.querySelector(
                    '[data-badge-award-type]'
                );

            const thresholdFields =
                form.querySelectorAll(
                    '[data-badge-threshold-field]'
                );

            if (
                !awardType
                || thresholdFields.length === 0
            ) {
                return;
            }

            const thresholdControls =
                form.querySelectorAll(
                    '[name="threshold_metric"], '
                    + '[name="threshold_value"]'
                );

            const updateThresholdVisibility =
                () => {
                    const automatic =
                        awardType.value ===
                        'automatic';

                    thresholdFields.forEach(
                        (field) => {
                            field.hidden =
                                !automatic;
                        }
                    );

                    thresholdControls.forEach(
                        (control) => {
                            control.disabled =
                                !automatic;
                        }
                    );
                };

            awardType.addEventListener(
                'change',
                updateThresholdVisibility
            );

            updateThresholdVisibility();
        });
})();
