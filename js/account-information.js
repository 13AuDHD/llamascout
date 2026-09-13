(() => {
    'use strict';

    const phoneInput =
        document.querySelector(
            '[data-phone-input]'
        );

    const formatUsPhone = (
        value
    ) => {
        let digits =
            String(value || '')
                .replace(/\D+/g, '');

        if (
            digits.length === 11
            && digits.startsWith('1')
        ) {
            digits =
                digits.slice(1);
        }

        if (
            digits.length > 10
            || (
                String(value || '')
                    .trim()
                    .startsWith('+')
                && digits.length !== 10
                && digits.length !== 11
            )
        ) {
            return value;
        }

        digits =
            digits.slice(0, 10);

        if (digits.length < 4) {
            return digits;
        }

        if (digits.length < 7) {
            return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
        }

        return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
    };

    if (phoneInput) {
        phoneInput.addEventListener(
            'input',
            () => {
                phoneInput.value =
                    formatUsPhone(
                        phoneInput.value
                    );
            }
        );
    }


    const searchInput =
        document.querySelector(
            '[data-address-search]'
        );

    const resultsNode =
        document.querySelector(
            '[data-address-results]'
        );

    const statusNode =
        document.querySelector(
            '[data-address-status]'
        );

    const useLocationButton =
        document.querySelector(
            '[data-address-use-location]'
        );

    const cityInput =
        document.querySelector(
            '[data-address-city]'
        );

    const stateInput =
        document.querySelector(
            '[data-address-state]'
        );

    const postalInput =
        document.querySelector(
            '[data-address-postal]'
        );

    const countryInput =
        document.querySelector(
            '[data-address-country]'
        );

    const latitudeInput =
        document.querySelector(
            '[data-address-latitude]'
        );

    const longitudeInput =
        document.querySelector(
            '[data-address-longitude]'
        );

    let debounceTimer = null;
    let locationBias = null;

    const setStatus = (
        message,
        isError = false
    ) => {
        if (!statusNode) {
            return;
        }

        statusNode.textContent =
            message;

        statusNode.classList.toggle(
            'is-error',
            isError
        );
    };

    const applyAddress = (
        result
    ) => {
        if (
            !result
            || typeof result !== 'object'
        ) {
            return;
        }

        if (searchInput) {
            searchInput.value =
                String(
                    result.address_line_1
                    || ''
                );
        }

        if (cityInput) {
            cityInput.value =
                String(
                    result.city
                    || ''
                );
        }

        if (stateInput) {
            stateInput.value =
                String(
                    result.state
                    || ''
                );
        }

        if (postalInput) {
            postalInput.value =
                String(
                    result.postal_code
                    || ''
                );
        }

        if (countryInput) {
            countryInput.value =
                String(
                    result.country
                    || ''
                );
        }

        if (latitudeInput) {
            latitudeInput.value =
                result.latitude != null
                    ? String(
                        result.latitude
                    )
                    : '';
        }

        if (longitudeInput) {
            longitudeInput.value =
                result.longitude != null
                    ? String(
                        result.longitude
                    )
                    : '';
        }

        if (resultsNode) {
            resultsNode.hidden =
                true;

            resultsNode.innerHTML =
                '';
        }

        setStatus(
            'Address filled from lookup.'
        );
    };

    const renderResults = (
        results
    ) => {
        if (!resultsNode) {
            return;
        }

        resultsNode.innerHTML =
            '';

        if (
            !Array.isArray(results)
            || !results.length
        ) {
            resultsNode.hidden =
                true;

            return;
        }

        results.forEach(
            (result) => {
                const button =
                    document.createElement(
                        'button'
                    );

                button.type =
                    'button';

                button.className =
                    'account-information-address-result';

                button.textContent =
                    String(
                        result.label
                        || ''
                    );

                button.addEventListener(
                    'click',
                    () => {
                        applyAddress(
                            result
                        );
                    }
                );

                resultsNode
                    .appendChild(
                        button
                    );
            }
        );

        resultsNode.hidden =
            false;
    };

    const addressSearch = async (
        query
    ) => {
        const params =
            new URLSearchParams({
                q: query,
            });

        if (locationBias) {
            params.set(
                'lat',
                String(
                    locationBias.latitude
                )
            );

            params.set(
                'lon',
                String(
                    locationBias.longitude
                )
            );
        }

        const response =
            await fetch(
                '/api/address-autocomplete.php?'
                + params.toString(),
                {
                    credentials:
                        'same-origin',
                    headers: {
                        Accept:
                            'application/json',
                    },
                }
            );

        const payload =
            await response.json();

        if (
            !response.ok
            || !payload.success
        ) {
            throw new Error(
                payload.message
                || 'Address lookup failed.'
            );
        }

        renderResults(
            payload.results
        );
    };

    if (searchInput) {
        searchInput.addEventListener(
            'input',
            () => {
                const query =
                    searchInput.value
                        .trim();

                if (latitudeInput) {
                    latitudeInput.value =
                        '';
                }

                if (longitudeInput) {
                    longitudeInput.value =
                        '';
                }

                if (debounceTimer) {
                    window.clearTimeout(
                        debounceTimer
                    );
                }

                if (query.length < 3) {
                    if (resultsNode) {
                        resultsNode.hidden =
                            true;

                        resultsNode.innerHTML =
                            '';
                    }

                    return;
                }

                debounceTimer =
                    window.setTimeout(
                        async () => {
                            try {
                                await addressSearch(
                                    query
                                );
                            } catch (error) {
                                setStatus(
                                    error?.message
                                    || 'Address lookup failed.',
                                    true
                                );
                            }
                        },
                        280
                    );
            }
        );
    }


    if (useLocationButton) {
        useLocationButton.addEventListener(
            'click',
            () => {
                if (
                    !navigator.geolocation
                ) {
                    setStatus(
                        'This browser does not support location lookup.',
                        true
                    );

                    return;
                }

                useLocationButton.disabled =
                    true;

                setStatus(
                    'Finding your location...'
                );

                navigator.geolocation.getCurrentPosition(
                    async (
                        position
                    ) => {
                        const latitude =
                            position.coords
                                .latitude;

                        const longitude =
                            position.coords
                                .longitude;

                        locationBias = {
                            latitude,
                            longitude,
                        };

                        const params =
                            new URLSearchParams({
                                reverse:
                                    '1',
                                lat:
                                    String(latitude),
                                lon:
                                    String(longitude),
                            });

                        try {
                            const response =
                                await fetch(
                                    '/api/address-autocomplete.php?'
                                    + params.toString(),
                                    {
                                        credentials:
                                            'same-origin',
                                        headers: {
                                            Accept:
                                                'application/json',
                                        },
                                    }
                                );

                            const payload =
                                await response.json();

                            if (
                                !response.ok
                                || !payload.success
                            ) {
                                throw new Error(
                                    payload.message
                                    || 'Location lookup failed.'
                                );
                            }

                            if (!payload.result) {
                                throw new Error(
                                    'No street address was found for this location.'
                                );
                            }

                            applyAddress(
                                payload.result
                            );

                        } catch (error) {
                            setStatus(
                                error?.message
                                || 'Location lookup failed.',
                                true
                            );
                        } finally {
                            useLocationButton.disabled =
                                false;
                        }
                    },
                    () => {
                        setStatus(
                            'Location permission was not available.',
                            true
                        );

                        useLocationButton.disabled =
                            false;
                    },
                    {
                        enableHighAccuracy:
                            false,
                        timeout:
                            12000,
                        maximumAge:
                            300000,
                    }
                );
            }
        );
    }


    document.addEventListener(
        'click',
        (event) => {
            if (
                !resultsNode
                || resultsNode.hidden
            ) {
                return;
            }

            if (
                event.target === searchInput
                || resultsNode.contains(
                    event.target
                )
            ) {
                return;
            }

            resultsNode.hidden =
                true;
        }
    );
})();
