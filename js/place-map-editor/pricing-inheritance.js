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

    namespace.pricingRateIdentity =
        rateIdentity;

    namespace.mergePricingRateLayers =
        mergeRateLayers;

    const originalCreatePricing =
        namespace.createPricing;

    if (
        typeof originalCreatePricing
        !== 'function'
    ) {
        return;
    }

    namespace.createPricing =
        (options) => {
            const pricing =
                originalCreatePricing(
                    options
                );

            if (
                !pricing
                || typeof pricing
                    !== 'object'
            ) {
                return pricing;
            }

            const editor =
                options?.editor;

            const inheritance =
                editor?.querySelector(
                    '[data-rate-inheritance]'
                );

            const rateMoney =
                typeof pricing.rateMoney
                    === 'function'
                    ? pricing.rateMoney
                    : (amount) =>
                        String(amount);

            const currentFeatureType =
                () =>
                    String(
                        options
                            ?.getFeatureType
                            ?.()
                        || ''
                    );

            const currentFeatures =
                () => {
                    const features =
                        options
                            ?.getFeatures
                            ?.();

                    return Array.isArray(
                        features
                    )
                        ? features
                        : [];
                };

            const currentFeature =
                () =>
                    currentFeatures()
                        .find(
                            (feature) =>
                                Number(
                                    feature.id
                                )
                                === Number(
                                    options
                                        ?.getSelectedFeatureId
                                        ?.()
                                    || 0
                                )
                        )
                    || null;

            const parentFeature =
                () => {
                    if (
                        currentFeatureType()
                        !== 'camping_site'
                    ) {
                        return null;
                    }

                    const parentId =
                        Number(
                            options
                                ?.getSiteParentId
                                ?.()
                            || currentFeature()
                                ?.site_details
                                ?.camping_area_feature_id
                            || 0
                        );

                    if (parentId < 1) {
                        return null;
                    }

                    return (
                        currentFeatures()
                            .find(
                                (feature) =>
                                    Number(
                                        feature.id
                                    )
                                    === parentId
                                    && feature.feature_type
                                        === 'camping_area'
                            )
                        || null
                    );
                };

            const refreshInheritance =
                () => {
                    if (!inheritance) {
                        return;
                    }

                    inheritance.hidden =
                        true;

                    inheritance
                        .replaceChildren();

                    if (
                        currentFeatureType()
                        !== 'camping_site'
                    ) {
                        return;
                    }

                    const parent =
                        parentFeature();

                    if (!parent) {
                        return;
                    }

                    const parentRates =
                        Array.isArray(
                            parent.rates
                        )
                            ? parent.rates
                            : [];

                    const selectedClassId =
                        Number(
                            options
                                ?.getSiteClassId
                                ?.()
                            || 0
                        );

                    const selectedClass =
                        (
                            Array.isArray(
                                parent.site_classes
                            )
                                ? parent
                                    .site_classes
                                : []
                        ).find(
                            (item) =>
                                Number(
                                    item.id
                                )
                                === selectedClassId
                        )
                        || null;

                    const classRates =
                        Array.isArray(
                            selectedClass
                                ?.rates
                        )
                            ? selectedClass
                                .rates
                            : [];

                    if (
                        parentRates.length
                        === 0
                        && classRates.length
                            === 0
                    ) {
                        return;
                    }

                    const effective =
                        mergeRateLayers(
                            parentRates,
                            classRates
                        );

                    const title =
                        document
                            .createElement(
                                'strong'
                            );

                    title.textContent =
                        selectedClass
                            ? `Inherited pricing: ${selectedClass.name}`
                            : `Inherited pricing: ${parent.label || 'parent Camping area'}`;

                    const summary =
                        document
                            .createElement(
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

                    if (
                        amounts.length
                        > 0
                    ) {
                        const min =
                            Math.min(
                                ...amounts
                            );

                        const max =
                            Math.max(
                                ...amounts
                            );

                        summary.textContent =
                            min === max
                                ? `${rateMoney(min)} / night`
                                : `${rateMoney(min)}-${rateMoney(max)} / night`;
                    } else {
                        summary.textContent =
                            `${effective.length} inherited rate${effective.length === 1 ? '' : 's'}`;
                    }

                    const note =
                        document
                            .createElement(
                                'small'
                            );

                    note.textContent =
                        selectedClass
                            ? 'Camping-area rates apply first. Pricing-class and Site rates replace only matching rate identities, so unrelated holiday and seasonal rates continue to inherit.'
                            : 'Camping-area rates inherit unless this Site has a matching rate identity.';

                    inheritance.append(
                        title,
                        summary,
                        note
                    );

                    inheritance.hidden =
                        false;
                };

            const wrap =
                (name) => {
                    const original =
                        pricing[name];

                    if (
                        typeof original
                        !== 'function'
                    ) {
                        return;
                    }

                    pricing[name] =
                        (...args) => {
                            const result =
                                original(
                                    ...args
                                );

                            refreshInheritance();

                            return result;
                        };
                };

            [
                'syncSiteClasses',
                'renderSiteClasses',
                'syncRates',
                'renderRates',
                'clearSiteClasses',
                'clearRates'
            ].forEach(
                wrap
            );

            refreshInheritance();

            return pricing;
        };
})();
