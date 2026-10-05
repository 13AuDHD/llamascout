(() => {
    'use strict';

    const picker =
        document.querySelector(
            '[data-partner-place-picker]'
        );

    if (!picker) {
        return;
    }

    const input =
        picker.querySelector(
            '[data-partner-place-search]'
        );

    const results =
        picker.querySelector(
            '[data-partner-place-results]'
        );

    const placeId =
        picker.querySelector(
            '[data-partner-place-id]'
        );

    const selected =
        picker.querySelector(
            '[data-partner-place-selected]'
        );

    const selectedName =
        picker.querySelector(
            '[data-partner-place-selected-name]'
        );

    const selectedMeta =
        picker.querySelector(
            '[data-partner-place-selected-meta]'
        );

    const submit =
        picker.querySelector(
            '[data-partner-place-submit]'
        );

    if (
        !input
        || !results
        || !placeId
        || !selected
        || !selectedName
        || !selectedMeta
        || !submit
    ) {
        return;
    }

    let controller = null;
    let timer = 0;

    const clearResults = () => {
        results.innerHTML = '';
        results.hidden = true;
    };

    const choose = (place) => {
        placeId.value =
            String(
                place.id
                || ''
            );

        selectedName.textContent =
            String(
                place.name
                || 'Place'
            );

        selectedMeta.textContent =
            String(
                place.meta
                || ''
            );

        selected.hidden = false;
        submit.disabled = !placeId.value;

        input.value =
            String(
                place.name
                || ''
            );

        clearResults();
    };

    const render = (places) => {
        results.innerHTML = '';

        if (!places.length) {
            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'admin-partner-place-result';

            empty.textContent =
                'No matching Places found.';

            results.appendChild(
                empty
            );

            results.hidden = false;
            return;
        }

        places.forEach((place) => {
            const button =
                document.createElement(
                    'button'
                );

            button.type = 'button';
            button.className =
                'admin-partner-place-result';

            const strong =
                document.createElement(
                    'strong'
                );

            strong.textContent =
                String(
                    place.name
                    || 'Place'
                );

            const small =
                document.createElement(
                    'small'
                );

            small.textContent =
                String(
                    place.meta
                    || ''
                );

            button.append(
                strong,
                small
            );

            button.addEventListener(
                'click',
                () => choose(place)
            );

            results.appendChild(
                button
            );
        });

        results.hidden = false;
    };

    const search = async () => {
        const query =
            String(
                input.value
                || ''
            ).trim();

        placeId.value = '';
        selected.hidden = true;
        submit.disabled = true;

        if (query.length < 2) {
            clearResults();
            return;
        }

        if (controller) {
            controller.abort();
        }

        controller =
            new AbortController();

        try {
            const response =
                await fetch(
                    '/partner-place-search.php?q='
                    + encodeURIComponent(
                        query
                    ),
                    {
                        credentials:
                            'same-origin',
                        headers: {
                            Accept:
                                'application/json',
                        },
                        signal:
                            controller.signal,
                    }
                );

            const payload =
                await response.json();

            if (
                !response.ok
                || !payload?.ok
            ) {
                throw new Error(
                    String(
                        payload?.error
                        || 'Place search failed.'
                    )
                );
            }

            render(
                Array.isArray(
                    payload.results
                )
                    ? payload.results
                    : []
            );

        } catch (error) {
            if (
                error?.name
                === 'AbortError'
            ) {
                return;
            }

            results.innerHTML = '';

            const message =
                document.createElement(
                    'div'
                );

            message.className =
                'admin-partner-place-result';

            message.textContent =
                error instanceof Error
                    ? error.message
                    : 'Place search failed.';

            results.appendChild(
                message
            );

            results.hidden = false;
        }
    };

    input.addEventListener(
        'input',
        () => {
            window.clearTimeout(
                timer
            );

            timer =
                window.setTimeout(
                    search,
                    220
                );
        }
    );

    document.addEventListener(
        'click',
        (event) => {
            if (
                !picker.contains(
                    event.target
                )
            ) {
                clearResults();
            }
        }
    );
})();
