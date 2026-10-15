(() => {
    'use strict';

    const editor =
        document.querySelector(
            '[data-mapped-area-editor]'
        );

    if (!editor) {
        return;
    }

    const featureType =
        editor.querySelector(
            '[data-feature-type]'
        );

    const feeStatus =
        editor.querySelector(
            '[data-fee-status]'
        );

    const feeStatusField =
        editor.querySelector(
            '[data-fee-status-field]'
        );

    const featureList =
        editor.querySelector(
            '[data-feature-list]'
        );

    const parkingCostLabels =
        new Set([
            'Free',
            'Paid',
            'Varies',
            'Unknown'
        ]);

    const syncCostField = () => {
        const parking =
            featureType?.value
            === 'parking_area';

        if (feeStatusField) {
            feeStatusField.hidden =
                !parking;
        }

        if (
            !parking
            && feeStatus
        ) {
            feeStatus.value =
                '';
        }
    };

    const removeLegacyCampingCostLabels =
        () => {
            if (!featureList) {
                return;
            }

            featureList
                .querySelectorAll(
                    'button small'
                )
                .forEach(
                    (detail) => {
                        const parts =
                            String(
                                detail.textContent
                                || ''
                            )
                                .split(' Â· ')
                                .map(
                                    (part) =>
                                        part.trim()
                                )
                                .filter(Boolean);

                        if (
                            parts[0]
                            !== 'Camping area'
                        ) {
                            return;
                        }

                        detail.textContent =
                            parts
                                .filter(
                                    (part, index) =>
                                        index === 0
                                        || !parkingCostLabels
                                            .has(part)
                                )
                                .join(' Â· ');
                    }
                );
        };

    const refresh = () => {
        syncCostField();

        queueMicrotask(
            removeLegacyCampingCostLabels
        );
    };

    featureType
        ?.addEventListener(
            'change',
            refresh
        );

    editor.addEventListener(
        'click',
        (event) => {
            if (
                event.target.closest(
                    '[data-feature-list], [data-new-feature]'
                )
            ) {
                queueMicrotask(
                    refresh
                );
            }
        }
    );

    if (featureList) {
        new MutationObserver(
            removeLegacyCampingCostLabels
        ).observe(
            featureList,
            {
                childList: true,
                subtree: true
            }
        );
    }

    refresh();
})();
