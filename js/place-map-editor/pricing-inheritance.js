(() => {
    'use strict';

    const namespace =
        window.LlamaPlaceMapEditor =
            window.LlamaPlaceMapEditor || {};

    const normalizeLabel =
        (value) =>
            String(value || '')
                .trim()
                .replace(/\s+/g, ' ')
                .toLowerCase();

    const rateIdentity =
        (rate = {}) => {
            const type =
                String(
                    rate.rate_type
                    || ''
                )
                    .trim()
                    .toLowerCase();

            if (!type) {
                return '';
            }

            if (type === 'seasonal') {
                return [
                    type,
                    String(
                        rate.season_start
                        || ''
                    ).trim(),
                    String(
                        rate.season_end
                        || ''
                    ).trim()
                ].join('|');
            }

            if (type === 'holiday') {
                return [
                    type,
                    normalizeLabel(
                        rate.label
                    )
                ].join('|');
            }

            return type;
        };

    const mergeRateLayers =
        (...layers) => {
            const result = [];

            layers.forEach(
                (layer) => {
                    (
                        Array.isArray(layer)
                            ? layer
                            : []
                    ).forEach(
                        (rate) => {
                            if (
                                !rate
                                || typeof rate
                                    !== 'object'
                            ) {
                                return;
                            }

                            const identity =
                                rateIdentity(
                                    rate
                                );

                            if (!identity) {
                                return;
                            }

                            for (
                                let index =
                                    result.length - 1;
                                index >= 0;
                                index--
                            ) {
                                if (
                                    rateIdentity(
                                        result[index]
                                    )
                                    === identity
                                ) {
                                    result.splice(
                                        index,
                                        1
                                    );
                                }
                            }

                            result.push(
                                rate
                            );
                        }
                    );
                }
            );

            return result;
        };

    const inheritedRatesFor =
        (
            parent,
            selectedClassId
        ) => {
            const parentRates =
                Array.isArray(
                    parent?.rates
                )
                    ? parent.rates
                    : [];

            const selectedClass =
                (
                    Array.isArray(
                        parent?.site_classes
                    )
                        ? parent.site_classes
                        : []
                ).find(
                    (item) =>
                        Number(
                            item.id
                        )
                        === Number(
                            selectedClassId
                            || 0
                        )
                )
                || null;

            const classRates =
                Array.isArray(
                    selectedClass?.rates
                )
                    ? selectedClass.rates
                    : [];

            return {
                selectedClass,
                parentRates,
                classRates,
                effective:
                    mergeRateLayers(
                        parentRates,
                        classRates
                    )
            };
        };

    const renderPricingInheritance =
        ({
            container,
            parent,
            selectedClassId = 0,
            rateMoney
        }) => {
            if (!container) {
                return;
            }

            container.hidden =
                true;

            container.replaceChildren();

            if (!parent) {
                return;
            }

            const {
                selectedClass,
                parentRates,
                classRates,
                effective
            } =
                inheritedRatesFor(
                    parent,
                    selectedClassId
                );

            if (
                parentRates.length === 0
                && classRates.length === 0
            ) {
                return;
            }

            const money =
                typeof rateMoney
                    === 'function'
                    ? rateMoney
                    : (amount) =>
                        String(amount);

            const title =
                document.createElement(
                    'strong'
                );

            title.textContent =
                selectedClass
                    ? `Inherited pricing: ${selectedClass.name}`
                    : `Inherited pricing: ${parent.label || 'parent Camping area'}`;

            const summary =
                document.createElement(
                    'span'
                );

            const amounts =
                effective
                    .map(
                        (rate) =>
                            Number(
                                rate.amount
                            )
                    )
                    .filter(
                        Number.isFinite
                    );

            if (amounts.length > 0) {
                const minimum =
                    Math.min(
                        ...amounts
                    );

                const maximum =
                    Math.max(
                        ...amounts
                    );

                summary.textContent =
                    minimum === maximum
                        ? `${money(minimum)} / night`
                        : `${money(minimum)}-${money(maximum)} / night`;
            } else {
                summary.textContent =
                    `${effective.length} inherited rate${effective.length === 1 ? '' : 's'}`;
            }

            const note =
                document.createElement(
                    'small'
                );

            note.textContent =
                selectedClass
                    ? 'Camping-area rates apply first. The pricing class replaces only matching rate identities; Site-specific rates can then override those same identities.'
                    : 'Camping-area rates inherit unless this Site has a matching Site-specific rate identity.';

            container.append(
                title,
                summary,
                note
            );

            container.hidden =
                false;
        };

    namespace.pricingRateIdentity =
        rateIdentity;

    namespace.mergePricingRateLayers =
        mergeRateLayers;

    namespace.renderPricingInheritance =
        renderPricingInheritance;
})();
