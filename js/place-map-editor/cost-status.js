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

    const syncCostField =
        () => {
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
                        const current =
                            String(
                                detail.textContent
                                || ''
                            );

                        const parts =
                            current
                                .split(' ÃÂ· ')
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

                        const next =
                            parts
                                .filter(
                                    (part, index) =>
                                        index === 0
                                        || !parkingCostLabels
                                            .has(part)
                                )
                                .join(' ÃÂ· ');

                        /*
                         * Do not write the same text back into the observed
                         * subtree. Setting textContent creates a child-list
                         * mutation even when the visible text is unchanged.
                         * The old implementation did that on every observer
                         * callback and could create an endless MutationObserver
                         * feedback loop that pegged Safari and server activity.
                         */
                        if (next !== current) {
                            detail.textContent =
                                next;
                        }
                    }
                );
        };

    const refresh =
        () => {
            syncCostField();
            removeLegacyCampingCostLabels();
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
        let observer = null;

        const observe =
            () => {
                observer?.observe(
                    featureList,
                    {
                        childList:
                            true,

                        subtree:
                            true
                    }
                );
            };

        observer =
            new MutationObserver(
                () => {
                    /*
                     * Disconnect while normalizing the rendered labels so our
                     * own textContent changes cannot recursively schedule the
                     * observer again.
                     */
                    observer.disconnect();

                    removeLegacyCampingCostLabels();

                    observe();
                }
            );

        observe();
    }

    refresh();
})();
