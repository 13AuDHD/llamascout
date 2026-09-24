(() => {
    'use strict';

    const form = document.querySelector(
        '.admin-membership-promotion-form'
    );

    if (!form) {
        return;
    }

    const cards = Array.from(
        form.querySelectorAll('[data-sale-plan]')
    );


    const money = (cents) => {
        const value =
            Math.max(
                0,
                Number(cents) || 0
            ) / 100;

        return new Intl.NumberFormat(
            'en-US',
            {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }
        ).format(value);
    };


    const discountedCents = (
        baseCents,
        discountType,
        rawValue
    ) => {
        const numeric =
            Number(rawValue);

        if (
            !Number.isFinite(numeric)
            || numeric <= 0
        ) {
            return baseCents;
        }


        if (
            discountType === 'percent'
        ) {
            const percent =
                Math.min(
                    100,
                    numeric
                );

            const discount =
                Math.round(
                    baseCents
                    * (
                        percent
                        / 100
                    )
                );

            return Math.max(
                0,
                baseCents
                - discount
            );
        }


        if (
            discountType === 'amount'
        ) {
            const discount =
                Math.round(
                    numeric
                    * 100
                );

            return Math.max(
                0,
                baseCents
                - discount
            );
        }


        if (
            discountType === 'promotional_price'
        ) {
            return Math.max(
                0,
                Math.round(
                    numeric
                    * 100
                )
            );
        }


        return baseCents;
    };


    const monthlyDuration = (
        card
    ) => {
        const duration =
            card.querySelector(
                '[data-sale-duration]'
            );

        if (!duration) {
            /*
             * Compatibility with the existing Admin page until
             * the PHP replacement is installed.
             *
             * The old campaign system always used 12 months.
             */
            return 12;
        }


        const months =
            Number(
                duration.value
            );


        if (
            ![
                1,
                2,
                3,
                6,
                9,
                12,
            ].includes(
                months
            )
        ) {
            return 1;
        }


        return months;
    };


    const updateCardState = (
        card
    ) => {
        const enabled =
            card.querySelector(
                '[data-sale-enabled]'
            );

        const controls =
            card.querySelectorAll(
                [
                    '[data-sale-type]',
                    '[data-sale-value]',
                    '[data-sale-duration]',
                ].join(', ')
            );

        const active =
            Boolean(
                enabled
                && enabled.checked
            );


        card.classList.toggle(
            'is-enabled',
            active
        );


        controls.forEach(
            (control) => {
                control.disabled =
                    !active;
            }
        );
    };


    const updateDiscountValueField = (
        card
    ) => {
        const interval =
            card.dataset.salePlan
            || '';

        const type =
            card.querySelector(
                '[data-sale-type]'
            );

        const value =
            card.querySelector(
                '[data-sale-value]'
            );

        const label =
            card.querySelector(
                '[data-sale-value-label]'
            );

        const help =
            card.querySelector(
                '[data-sale-value-help]'
            );


        if (
            !type
            || !value
        ) {
            return;
        }


        /*
         * Promotional price is a Monthly-only rule.
         */
        if (
            interval !== 'monthly'
            && type.value ===
                'promotional_price'
        ) {
            type.value =
                'percent';
        }


        if (
            type.value ===
            'promotional_price'
        ) {
            if (label) {
                label.textContent =
                    'Promotional monthly price';
            }

            value.min =
                '0.01';

            value.step =
                '0.01';

            value.placeholder =
                '4.99';


            if (help) {
                help.textContent =
                    'Enter the monthly price the customer pays during the promotion.';
            }

            return;
        }


        if (
            type.value ===
            'amount'
        ) {
            if (label) {
                label.textContent =
                    'Dollar amount off';
            }

            value.min =
                '0.01';

            value.step =
                '0.01';

            value.placeholder =
                '2.00';


            if (help) {
                help.textContent =
                    'Enter the amount deducted from the regular price.';
            }

            return;
        }


        if (label) {
            label.textContent =
                'Percent off';
        }

        value.min =
            '1';

        value.step =
            '1';

        value.placeholder =
            '25';


        if (help) {
            help.textContent =
                'Enter the percentage deducted from the regular price.';
        }
    };


    const updateSummary = () => {
        cards.forEach(
            (card) => {

                updateCardState(
                    card
                );

                updateDiscountValueField(
                    card
                );


                const interval =
                    card.dataset.salePlan
                    || '';

                const summary =
                    form.querySelector(
                        `[data-sale-summary="${interval}"]`
                    );

                if (!summary) {
                    return;
                }


                const baseCents =
                    Number(
                        card.dataset.baseCents
                        || 0
                    );

                const enabled =
                    card.querySelector(
                        '[data-sale-enabled]'
                    );

                const type =
                    card.querySelector(
                        '[data-sale-type]'
                    );

                const value =
                    card.querySelector(
                        '[data-sale-value]'
                    );


                const regularNode =
                    summary.querySelector(
                        '[data-sale-regular]'
                    );

                const priceNode =
                    summary.querySelector(
                        '[data-sale-price]'
                    );

                const equivalentNode =
                    summary.querySelector(
                        '[data-sale-equivalent]'
                    );

                const durationNode =
                    summary.querySelector(
                        '[data-sale-duration-summary]'
                    );


                if (regularNode) {
                    regularNode.textContent =
                        interval === 'monthly'
                            ? `${money(baseCents)} / month regular`
                            : `${money(baseCents)} / year regular`;
                }


                if (
                    !enabled
                    || !enabled.checked
                ) {
                    summary.classList.add(
                        'is-disabled'
                    );


                    if (priceNode) {
                        priceNode.textContent =
                            'Not included in sale';
                    }


                    if (durationNode) {
                        durationNode.textContent =
                            '';
                    }


                    if (equivalentNode) {
                        equivalentNode.textContent =
                            interval === 'monthly'
                                ? `${money(baseCents * 12)} first-year total at regular price`
                                : `${money(Math.round(baseCents / 12))} / month equivalent at regular price`;
                    }

                    return;
                }


                summary.classList.remove(
                    'is-disabled'
                );


                const saleCents =
                    discountedCents(
                        baseCents,
                        type
                            ? type.value
                            : '',
                        value
                            ? value.value
                            : ''
                    );


                if (
                    interval === 'monthly'
                ) {
                    const months =
                        monthlyDuration(
                            card
                        );


                    if (priceNode) {
                        priceNode.textContent =
                            `${money(saleCents)} / month sale`;
                    }


                    if (durationNode) {
                        durationNode.textContent =
                            months === 1
                                ? 'For 1 month'
                                : `For ${months} months`;
                    }


                    /*
                     * First-year cost accounts for the promotion
                     * ending before month 12.
                     *
                     * Example:
                     * $4.99 for 3 months
                     * $6.99 for the remaining 9 months
                     */
                    const promotionalTotal =
                        saleCents
                        * months;

                    const regularMonths =
                        Math.max(
                            0,
                            12 - months
                        );

                    const regularTotal =
                        baseCents
                        * regularMonths;

                    const firstYearTotal =
                        promotionalTotal
                        + regularTotal;


                    if (equivalentNode) {
                        equivalentNode.textContent =
                            `${money(firstYearTotal)} first-year total`;
                    }


                    return;
                }


                /*
                 * Annual promotions always cover one annual
                 * billing period.
                 */
                if (priceNode) {
                    priceNode.textContent =
                        `${money(saleCents)} / year sale`;
                }


                if (durationNode) {
                    durationNode.textContent =
                        'For first annual billing period';
                }


                if (equivalentNode) {
                    equivalentNode.textContent =
                        `${money(Math.round(saleCents / 12))} / month equivalent`;
                }
            }
        );
    };


    cards.forEach(
        (card) => {

            card.addEventListener(
                'input',
                updateSummary
            );

            card.addEventListener(
                'change',
                updateSummary
            );
        }
    );


    updateSummary();
})();
