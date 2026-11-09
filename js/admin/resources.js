(() => {
    function setupServices(section) {
        if (!section) return;

        const picker = section.querySelector('[data-resource-service-picker]');
        const choices = picker.querySelector('select');
        const add = picker.querySelector('[data-resource-service-add]');
        const cards = section.querySelector('[data-resource-service-cards]');
        const template = section.querySelector('[data-resource-service-template]');
        const empty = section.querySelector('[data-resource-services-empty]');
        const primaryField = section.querySelector('[data-resource-primary-service]');
        const primary = primaryField.querySelector('select');
        const serviceOptions = [...choices.options].filter(option => option.value);
        const removed = new Map();

        function sync() {
            const added = new Set([...cards.children].map(card => card.dataset.resourceServiceCard));
            const previousPrimary = primary.value;
            primary.length = 1;
            choices.length = 1;
            for (const option of serviceOptions) {
                if (added.has(option.value)) {
                    primary.add(new Option(option.text, option.value));
                } else {
                    choices.add(option);
                }
            }

            primary.value = added.has(previousPrimary) ? previousPrimary : '';
            primaryField.hidden = added.size === 0;
            empty.hidden = added.size > 0;
            cards.hidden = added.size === 0;
            choices.value = '';
            add.disabled = true;
            picker.hidden = added.size === serviceOptions.length;
        }

        choices.addEventListener('change', () => {
            add.disabled = !choices.value;
        });
        add.addEventListener('click', () => {
            const option = choices.selectedOptions[0];
            if (!option?.value) return;

            const type = option.value;
            let card = removed.get(type);
            if (card) {
                removed.delete(type);
            } else {
                card = template.content.firstElementChild.cloneNode(true);
                card.dataset.resourceServiceCard = type;
                card.querySelector('[data-resource-service-title]').textContent = option.text;
                const remove = card.querySelector('[data-resource-service-remove]');
                remove.setAttribute('aria-label', `Remove ${option.text}`);
                remove.hidden = false;
                for (const field of card.querySelectorAll('[name]')) {
                    field.name = field.name.replace('__type__', type);
                }
            }

            cards.append(card);
            sync();
            card.querySelector('select').focus();
        });

        cards.addEventListener('click', event => {
            const button = event.target.closest('[data-resource-service-remove]');
            if (!button) return;

            const card = button.closest('[data-resource-service-card]');
            removed.set(card.dataset.resourceServiceCard, card);
            card.remove();
            sync();
            choices.focus();
        });

        for (const button of cards.querySelectorAll('[data-resource-service-remove]')) {
            button.hidden = false;
        }
        sync();
    }

    function setupLocation(form) {
        const locate = form.querySelector('[data-resource-locate]');
        const search = form.querySelector('[data-resource-search]');
        const status = form.querySelector('[data-resource-location-status]');
        if (!locate || !search || !status) return;

        const coordinates = form.elements.coordinates;
        const addressFields = {
            street_address: 'address_line_1',
            city: 'city',
            county: 'county',
            state_region: 'state_name',
            postal_code: 'postal_code',
            country_code: 'country_code',
        };

        function showStatus(message, kind) {
            status.textContent = message;
            status.classList.remove('is-info', 'is-warning', 'is-success', 'is-error');
            status.classList.add(`is-${kind}`);
        }

        function parseCoordinates(value) {
            const parts = value.trim().split(',').map(part => part.trim());
            const [lat, lng] = parts.map(Number);
            if (parts.length !== 2 || parts.some(part => !/^[+-]?(?:\d+\.?\d*|\.\d+)$/.test(part))
                || Math.abs(lat) > 90 || Math.abs(lng) > 180 || (lat === 0 && lng === 0)) {
                throw new Error('Enter valid latitude and longitude separated by a comma.');
            }
            return [lat, lng];
        }

        async function lookup(readCoordinates) {
            locate.disabled = search.disabled = true;
            const originalCoordinates = coordinates.value;
            const initialFields = new Map(
                Object.keys(addressFields).map(name => [name, form.elements[name].value])
            );
            try {
                const [lat, lng] = await readCoordinates();
                if (coordinates.value !== originalCoordinates) {
                    showStatus('Coordinates changed. Tap Search to look up the new location.', 'info');
                    return;
                }
                const searched = `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
                coordinates.value = searched;
                showStatus('Looking up the address and county…', 'info');
                const params = new URLSearchParams({ mode: 'address', lat, lng });
                const response = await fetch(`/api/location-lookup.php?${params}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const result = await response.json();
                if (coordinates.value !== searched) {
                    showStatus('Coordinates changed. Tap Search to look up the new location.', 'info');
                    return;
                }
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Address lookup is temporarily unavailable.');
                }
                const address = result.address;
                for (const [name, key] of Object.entries(addressFields)) {
                    const field = form.elements[name];
                    const value = address?.[key];
                    if (typeof value === 'string' && value.trim() && field.value === initialFields.get(name)) {
                        field.value = field.maxLength > 0 ? value.slice(0, field.maxLength) : value;
                    }
                }
                if (address?.address_line_1 && address?.county) {
                    showStatus('Address and county found. Check the details before saving.', 'success');
                } else {
                    showStatus('The lookup returned limited details. Fill in any missing address or county before saving.', 'warning');
                }
            } catch (error) {
                showStatus(error instanceof SyntaxError ? 'Address lookup is temporarily unavailable.' : error.message, 'error');
            } finally {
                search.disabled = false;
                locate.disabled = !navigator.geolocation;
            }
        }

        search.disabled = false;
        locate.disabled = !navigator.geolocation;
        search.addEventListener('click', () => lookup(() => parseCoordinates(coordinates.value)));
        coordinates.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (!search.disabled) search.click();
            }
        });
        locate.addEventListener('click', () => {
            showStatus('Finding your location…', 'info');
            lookup(() => new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    ({ coords }) => resolve([coords.latitude, coords.longitude]),
                    () => reject(new Error('Location unavailable. Try again or enter coordinates and tap Search.')),
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            }));
        });
    }

    for (const form of document.querySelectorAll('[data-resource-editor]')) {
        setupServices(form.querySelector('[data-resource-services]'));

        const mode = form.querySelector('[data-resource-hours-mode]');
        const panels = form.querySelectorAll('[data-resource-hours-panel]');
        const sync = () => {
            for (const panel of panels) {
                const active = panel.dataset.resourceHoursPanel === mode.value;
                panel.hidden = !active;
                for (const field of panel.querySelectorAll('select, input')) {
                    field.disabled = !active;
                    field.required = active;
                }
            }
        };
        mode?.addEventListener('change', sync);
        if (mode) sync();

        setupLocation(form);
        form.addEventListener('invalid', event => {
            let details = event.target.closest('details');
            while (details) {
                details.open = true;
                details = details.parentElement.closest('details');
            }
        }, true);
    }
})();
