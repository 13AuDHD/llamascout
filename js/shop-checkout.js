(() => {
    'use strict';

    const root =
        document.getElementById(
            'shop-checkout'
        );

    const mount =
        document.getElementById(
            'stripe-shop-checkout'
        );

    if (!root || !mount) {
        return;
    }

    const publishableKey =
        root.dataset.stripePublishableKey || '';

    const clientSecret =
        root.dataset.checkoutClientSecret || '';

    const loading =
        document.getElementById(
            'shop-checkout-loading'
        );

    const errorBox =
        document.getElementById(
            'shop-checkout-mount-error'
        );

    const showError = () => {
        if (loading) {
            loading.hidden = true;
        }

        if (errorBox) {
            errorBox.hidden = false;
        }
    };

    if (
        !publishableKey ||
        !clientSecret ||
        typeof Stripe !== 'function'
    ) {
        showError();
        return;
    }

    (async () => {
        try {
            const stripe =
                Stripe(
                    publishableKey
                );

            const checkout =
                await stripe
                    .createEmbeddedCheckoutPage({
                        fetchClientSecret:
                            async () =>
                                clientSecret,
                    });

            if (loading) {
                loading.remove();
            }

            checkout.mount(
                '#stripe-shop-checkout'
            );
        } catch (error) {
            console.error(
                'Llama Scout Shop Checkout:',
                error
            );

            showError();
        }
    })();
})();
