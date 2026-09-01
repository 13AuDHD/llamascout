(() => {
    const form = document.getElementById('payment-method-form');
    const mount = document.getElementById('payment-element');
    const submit = document.getElementById('payment-method-submit');
    const message = document.getElementById('payment-method-message');
    const loading = document.getElementById('payment-method-loading');

    if (!form || !mount || !submit || typeof Stripe !== 'function') {
        return;
    }

    const publishableKey = mount.dataset.publishableKey || '';
    const clientSecret = mount.dataset.clientSecret || '';

    const showMessage = (text) => {
        if (!message) return;
        message.textContent = text;
        message.hidden = !text;
    };

    const setWorking = (working) => {
        submit.disabled = working;
        const label = submit.querySelector('.payment-method-submit-label');
        const spinner = submit.querySelector('.payment-method-submit-working');
        if (label) label.hidden = working;
        if (spinner) spinner.hidden = !working;
    };

    if (!publishableKey || !clientSecret) {
        showMessage('Secure payment fields could not be initialized. Return to Billing and try again.');
        return;
    }

    const stripe = Stripe(publishableKey);
    const elements = stripe.elements({
        clientSecret,
        appearance: {
            theme: 'night'
        }
    });

    const paymentElement = elements.create('payment', {
        layout: 'tabs'
    });

    paymentElement.mount('#payment-element');

    paymentElement.on('ready', () => {
        if (loading) loading.remove();
        submit.disabled = false;
    });

    paymentElement.on('loaderror', () => {
        if (loading) loading.remove();
        showMessage('Secure payment fields could not load. Refresh and try again.');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showMessage('');
        setWorking(true);

        const returnUrl = `${window.location.origin}/payment-method-return.php`;

        try {
            const result = await stripe.confirmSetup({
                elements,
                confirmParams: {
                    return_url: returnUrl
                },
                redirect: 'if_required'
            });

            if (result.error) {
                showMessage(result.error.message || 'The payment method could not be saved.');
                setWorking(false);
                return;
            }

            const setupIntent = result.setupIntent;
            if (setupIntent && setupIntent.id) {
                window.location.assign(`${returnUrl}?setup_intent=${encodeURIComponent(setupIntent.id)}`);
                return;
            }

            showMessage('Stripe did not return a completed payment-method setup. Please try again.');
            setWorking(false);
        } catch (error) {
            showMessage('Secure payment processing could not complete. Please try again.');
            setWorking(false);
        }
    });
})();
