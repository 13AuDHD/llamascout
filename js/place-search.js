(() => {
    'use strict';

    const iconMarkup = (name) => {
        const safeName = String(name || 'plus')
            .replace(/[^a-z0-9-]/gi, '');

        return `
            <i
                class="llama-icon-mask"
                style="--llama-icon-mask:url('/assets/icons/${safeName}.svg')"
                aria-hidden="true"
            ></i>
        `;
    };

    const clearElement = (element) => {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    };

    const createMessage = (text) => {
        const message = document.createElement('div');
        message.className = 'compare-search-empty';
        message.textContent = text;
        return message;
    };

    const createResult = (place, icon) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'compare-place-option';
        button.dataset.placeSlug = String(place.slug || '');
        button.dataset.placeName = String(place.name || 'Place');
        button.dataset.placeMeta = String(place.meta || '');

        const check = document.createElement('span');
        check.className = 'compare-place-option-check';
        check.innerHTML = iconMarkup(icon);

        const copy = document.createElement('span');
        copy.className = 'compare-place-option-copy';

        const strong = document.createElement('strong');
        strong.textContent = String(place.name || 'Place');
        copy.appendChild(strong);

        if (place.meta) {
            const small = document.createElement('small');
            small.textContent = String(place.meta);
            copy.appendChild(small);
        }

        button.append(check, copy);
        return button;
    };

    const attach = ({
        input,
        results,
        endpoint,
        onSelect,
        excludeSlugs = () => [],
        resultIcon = 'plus',
        minimumCharacters = 1,
    }) => {
        if (!input || !results || !endpoint) {
            return null;
        }

        let timer = null;
        let controller = null;
        let requestNumber = 0;

        const hide = () => {
            results.classList.remove('has-query');
            clearElement(results);
            input.setAttribute('aria-expanded', 'false');
        };

        const showMessage = (text) => {
            clearElement(results);
            results.appendChild(createMessage(text));
            results.classList.add('has-query');
            input.setAttribute('aria-expanded', 'true');
        };

        const render = (places) => {
            clearElement(results);

            const excluded = new Set(
                (excludeSlugs() || [])
                    .map((slug) => String(slug))
            );

            const available = places.filter(
                (place) =>
                    place
                    && place.slug
                    && !excluded.has(String(place.slug))
            );

            if (!available.length) {
                showMessage('No matching Places.');
                return;
            }

            available.forEach((place) => {
                const button = createResult(
                    place,
                    resultIcon
                );

                button.addEventListener(
                    'click',
                    () => {
                        if (typeof onSelect === 'function') {
                            onSelect(place);
                        }
                    }
                );

                results.appendChild(button);
            });

            results.classList.add('has-query');
            input.setAttribute('aria-expanded', 'true');
        };

        const search = async () => {
            const query = input.value.trim();

            if (query.length < minimumCharacters) {
                hide();
                return;
            }

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();
            const thisRequest = ++requestNumber;

            showMessage('Searching...');

            try {
                const url = new URL(
                    endpoint,
                    window.location.origin
                );

                url.searchParams.set('q', query);

                const response = await fetch(
                    url.toString(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                        },
                        signal: controller.signal,
                    }
                );

                if (!response.ok) {
                    throw new Error('Search request failed.');
                }

                const payload = await response.json();

                if (thisRequest !== requestNumber) {
                    return;
                }

                render(
                    Array.isArray(payload?.results)
                        ? payload.results
                        : []
                );
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                showMessage(
                    'Search is temporarily unavailable.'
                );
            }
        };

        const scheduleSearch = () => {
            window.clearTimeout(timer);

            const query = input.value.trim();

            if (query.length < minimumCharacters) {
                hide();
                return;
            }

            timer = window.setTimeout(
                search,
                180
            );
        };

        input.addEventListener(
            'input',
            scheduleSearch
        );

        input.addEventListener(
            'keydown',
            (event) => {
                if (event.key === 'Escape') {
                    input.value = '';
                    hide();
                }
            }
        );

        document.addEventListener(
            'click',
            (event) => {
                if (
                    event.target === input
                    || results.contains(event.target)
                ) {
                    return;
                }

                hide();
            }
        );

        return {
            clear() {
                input.value = '';
                hide();
            },
            refresh() {
                if (input.value.trim() !== '') {
                    scheduleSearch();
                }
            },
        };
    };

    window.LlamaPlaceSearch = {
        attach,
    };
})();
