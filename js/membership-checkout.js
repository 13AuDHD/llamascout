(() => {
    'use strict';

    const container = document.getElementById('llama-embedded-checkout');
    const errorBox = document.getElementById('checkout-load-error');

    if (!container) {
        return;
    }

    const publishableKey = container.dataset.publishableKey || '';
    const clientSecret = container.dataset.clientSecret || '';

    const fail = () => {
        container.innerHTML = '';
        if (errorBox) {
            errorBox.hidden = false;
        }
    };

    if (!publishableKey || !clientSecret || typeof window.Stripe !== 'function') {
        fail();
        return;
    }

    try {
        const stripe = window.Stripe(publishableKey);

        stripe.initEmbeddedCheckout({
            clientSecret,
        }).then((checkout) => {
            container.innerHTML = '';
            checkout.mount('#llama-embedded-checkout');
        }).catch(fail);
    } catch (error) {
        fail();
    }
})();
