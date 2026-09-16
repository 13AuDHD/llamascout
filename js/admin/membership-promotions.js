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
        const value = Math.max(0, Number(cents) || 0) / 100;

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
        const numeric = Number(rawValue);

        if (!Number.isFinite(numeric) || numeric <= 0) {
            return baseCents;
        }

        if (discountType === 'percent') {
            const percent = Math.min(100, numeric);
            const discount = Math.round(
                baseCents * (percent / 100)
            );

            return Math.max(0, baseCents - discount);
        }

        if (discountType === 'amount') {
            const discount = Math.round(numeric * 100);

            return Math.max(0, baseCents - discount);
        }

        return baseCents;
    };

    const updateCardState = (card) => {
        const enabled = card.querySelector('[data-sale-enabled]');
        const controls = card.querySelectorAll(
            '[data-sale-type], [data-sale-value]'
        );
        const active = Boolean(enabled && enabled.checked);

        card.classList.toggle('is-enabled', active);

        controls.forEach((control) => {
            control.disabled = !active;
        });
    };

    const updateSummary = () => {
        cards.forEach((card) => {
            updateCardState(card);

            const interval = card.dataset.salePlan || '';
            const summary = form.querySelector(
                `[data-sale-summary="${interval}"]`
            );

            if (!summary) {
                return;
            }

            const baseCents = Number(
                card.dataset.baseCents || 0
            );
            const enabled = card.querySelector('[data-sale-enabled]');
            const type = card.querySelector('[data-sale-type]');
            const value = card.querySelector('[data-sale-value]');

            const regularNode = summary.querySelector('[data-sale-regular]');
            const priceNode = summary.querySelector('[data-sale-price]');
            const equivalentNode = summary.querySelector('[data-sale-equivalent]');

            if (regularNode) {
                regularNode.textContent =
                    interval === 'monthly'
                        ? `${money(baseCents)} / month regular`
                        : `${money(baseCents)} / year regular`;
            }

            if (!enabled || !enabled.checked) {
                summary.classList.add('is-disabled');

                if (priceNode) {
                    priceNode.textContent = 'Not included in sale';
                }

                if (equivalentNode) {
                    equivalentNode.textContent =
                        interval === 'monthly'
                            ? `${money(baseCents * 12)} / year at regular price`
                            : `${money(Math.round(baseCents / 12))} / month at regular price`;
                }

                return;
            }

            summary.classList.remove('is-disabled');

            const saleCents = discountedCents(
                baseCents,
                type ? type.value : '',
                value ? value.value : ''
            );

            if (priceNode) {
                priceNode.textContent =
                    interval === 'monthly'
                        ? `${money(saleCents)} / month sale`
                        : `${money(saleCents)} / year sale`;
            }

            if (equivalentNode) {
                equivalentNode.textContent =
                    interval === 'monthly'
                        ? `${money(saleCents * 12)} / year`
                        : `${money(Math.round(saleCents / 12))} / month`;
            }
        });
    };

    cards.forEach((card) => {
        card.addEventListener('input', updateSummary);
        card.addEventListener('change', updateSummary);
    });

    updateSummary();
})();
