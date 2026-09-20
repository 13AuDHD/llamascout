(() => {
    'use strict';

    const page =
        document.querySelector('[data-checkin-page]');

    if (!page) {
        return;
    }

    const locateButton =
        page.querySelector('[data-checkin-locate]');
    const locationCard =
        page.querySelector('[data-checkin-location-card]');
    const statusBox =
        page.querySelector('[data-checkin-location-status]');
    const confirmation =
        page.querySelector('[data-checkin-confirmation]');
    const tooFar =
        page.querySelector('[data-checkin-too-far]');
    const tooFarMessage =
        page.querySelector('[data-checkin-too-far-message]');
    const proofInput =
        page.querySelector('[data-checkin-proof]');

    if (
        !locateButton
        || !locationCard
        || !statusBox
        || !confirmation
        || !tooFar
        || !proofInput
    ) {
        return;
    }

    const placeSlug =
        page.dataset.placeSlug || '';
    const csrf =
        locateButton.dataset.csrf || '';

    function setStatus(message, state = '') {
        statusBox.textContent = message;
        statusBox.dataset.state = state;
    }

    function locationErrorMessage(error) {
        if (!error) {
            return 'Your location could not be read. Try again.';
        }

        if (error.code === 1) {
            return 'Location permission is required for a Place check-in. Allow location access for Llama Scout, then try again.';
        }

        if (error.code === 2) {
            return 'Your device could not determine its location. Move somewhere with a clearer GPS signal and try again.';
        }

        if (error.code === 3) {
            return 'The location request timed out. Try again when your device has a stronger GPS signal.';
        }

        return 'Your location could not be read. Try again.';
    }

    async function verifyPosition(position) {
        const form = new FormData();

        form.set('checkin_action', 'locate');
        form.set('csrf_token', csrf);
        form.set('place_slug', placeSlug);
        form.set(
            'latitude',
            String(position.coords.latitude)
        );
        form.set(
            'longitude',
            String(position.coords.longitude)
        );
        form.set(
            'accuracy',
            String(position.coords.accuracy)
        );

        const response = await fetch(
            window.location.href,
            {
                method: 'POST',
                body: form,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        );

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (
            !response.ok
            || !payload
            || payload.ok !== true
        ) {
            throw new Error(
                payload && payload.message
                    ? payload.message
                    : 'The location check could not be completed.'
            );
        }

        return payload.result || {};
    }

    async function locate() {
        if (!navigator.geolocation) {
            setStatus(
                'This browser does not provide device location. A Place check-in requires browser location access.',
                'error'
            );
            return;
        }

        locateButton.disabled = true;
        confirmation.hidden = true;
        tooFar.hidden = true;
        proofInput.value = '';

        setStatus(
            'Checking your device location...',
            'working'
        );

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    setStatus(
                        'Comparing your location with this Place...',
                        'working'
                    );

                    const result =
                        await verifyPosition(position);

                    if (result.status === 'allowed') {
                        proofInput.value =
                            result.proof_token || '';

                        setStatus(
                            'Location confirmed. You are close enough to complete this Place check-in.',
                            'success'
                        );

                        locateButton.hidden = true;
                        confirmation.hidden = false;
                        confirmation.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        return;
                    }

                    if (result.status === 'too_far') {
                        locationCard.hidden = true;
                        confirmation.hidden = true;
                        tooFar.hidden = false;

                        const meters =
                            Number(result.distance_meters || 0);

                        if (
                            tooFarMessage
                            && Number.isFinite(meters)
                            && meters > 0
                        ) {
                            const distanceText =
                                meters >= 1609
                                    ? `${(meters / 1609.344).toFixed(1)} miles`
                                    : `${Math.round(meters)} meters`;

                            tooFarMessage.textContent =
                                `Your device appears to be about ${distanceText} from the stored campsite coordinates.`;
                        }

                        tooFar.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        return;
                    }

                    if (result.status === 'retry') {
                        const accuracy =
                            Number(result.accuracy_meters || 0);

                        setStatus(
                            accuracy > 0
                                ? `Your GPS reading is only accurate to about ${Math.round(accuracy)} meters. Wait for a stronger location lock and try again.`
                                : 'Your GPS reading is not accurate enough yet. Wait for a stronger location lock and try again.',
                            'error'
                        );

                        locateButton.disabled = false;
                        return;
                    }

                    if (result.status === 'cooldown') {
                        window.location.reload();
                        return;
                    }

                    throw new Error(
                        'The location check returned an unexpected result.'
                    );

                } catch (error) {
                    setStatus(
                        error instanceof Error
                            ? error.message
                            : 'The location check could not be completed.',
                        'error'
                    );

                    locateButton.disabled = false;
                }
            },
            (error) => {
                setStatus(
                    locationErrorMessage(error),
                    'error'
                );
                locateButton.disabled = false;
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    }

    locateButton.addEventListener(
        'click',
        locate
    );
})();
