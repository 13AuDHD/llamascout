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

            // Experience is derived from the same selected Service cards on
            // Resource and Facility editors. Hidden answers cannot be sent.
            const experience = section.closest('form')?.querySelector('[data-resource-experience]');
            if (experience) {
                for (const fieldset of experience.querySelectorAll('[data-resource-experience-service]')) {
                    const active = added.has(fieldset.dataset.resourceExperienceService);
                    fieldset.hidden = !active;
                    fieldset.disabled = !active;
                }
                const noExperience = experience.querySelector('[data-resource-experience-empty]');
                if (noExperience) noExperience.hidden = added.size > 0;
            }
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
                Object.keys(addressFields).map(name => [name, form.elements[name]?.value || ''])
            );
            try {
                const [lat, lng] = await readCoordinates();
                if (coordinates.value !== originalCoordinates) {
                    showStatus('Coordinates changed. Tap Search to look up the new location.', 'info');
                    return;
                }
                const searched = `${lat.toFixed(7)}, ${lng.toFixed(7)}`;
                coordinates.value = searched;
                // Place-owned Facilities do not have address inputs.
                // Their location is the selected coordinates plus Parent Place.
                if (!form.elements.street_address) {
                    showStatus('Facility coordinates set.', 'success');
                    return;
                }
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
                    if (field && typeof value === 'string' && value.trim() && field.value === initialFields.get(name)) {
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

    function setupResourceLookup(form) {
        const panel = form.querySelector('[data-resource-lookup]');
        if (!panel) return;
        const button = panel.querySelector('[data-resource-lookup-button]');
        const status = panel.querySelector('[data-resource-lookup-status]');
        const coordinates = form.elements.coordinates;
        const fields = {
            street_address: 'street address', city: 'city', county: 'county',
            state_region: 'state', postal_code: 'ZIP', country_code: 'country',
            phone: 'phone', website_url: 'website',
        };
        const sameCoordinates = (a, b) => {
            const parse = value => value.split(',').map(part => part.trim());
            const x = parse(a), y = parse(b);
            return x.length === 2 && y.length === 2 && x.every((part, i) =>
                part !== '' && y[i] !== '' && Number.isFinite(Number(part))
                && Math.abs(Number(part) - Number(y[i])) < 0.00000005);
        };
        button.addEventListener('click', async () => {
            if (!sameCoordinates(coordinates.value, panel.dataset.resourceCoordinates)) {
                status.textContent = 'Coordinates differ from the saved Resource. Save the location first or use the coordinate Search button.';
                return;
            }
            const original = coordinates.value;
            const blanks = Object.keys(fields).filter(name =>
                form.elements[name] && !form.elements[name].value.trim());
            button.disabled = true;
            status.textContent = 'Looking up the original OpenStreetMap record and address…';
            try {
                const response = await fetch('/resource-lookup.php', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    body: new URLSearchParams({
                        csrf_token: form.elements.csrf_token.value,
                        resource_id: panel.dataset.resourceId,
                    }),
                });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.error || 'Resource lookup temporarily unavailable.');
                }
                if (!sameCoordinates(original, coordinates.value)) {
                    status.textContent = 'Coordinates changed during lookup. No fields were filled.';
                    return;
                }
                const filled = [];
                const approximate = [];
                for (const name of blanks) {
                    const field = form.elements[name];
                    const value = result.fields?.[name];
                    if (field.value.trim() || typeof value !== 'string' || !value.trim()) continue;
                    if (field.type === 'url' && !/^https?:\/\//i.test(value)) continue;
                    if (field.maxLength > 0 && value.length > field.maxLength) continue;
                    field.value = value;
                    filled.push(fields[name]);
                    if (result.reverse_fields?.includes(name)) approximate.push(fields[name]);
                }
                const messages = [filled.length ? 'Filled: ' + filled.join(', ') + '.' : 'No additional empty fields could be filled.'];
                if (approximate.length) messages.push('Verify coordinate-based results: ' + approximate.join(', ') + '. These may describe a nearby address.');
                if (!result.osm_found) messages.push('Original OpenStreetMap record unavailable or removed.');
                if (!result.reverse_found) messages.push('Coordinate address lookup unavailable.');
                if (result.opening_hours) messages.push('OSM opening hours (review and enter in Details): ' + result.opening_hours);
                messages.push('Existing values were preserved. Review before saving.');
                status.textContent = messages.join(' ');
            } catch (error) {
                status.textContent = error instanceof SyntaxError ? 'Resource lookup temporarily unavailable.' : error.message;
            } finally {
                button.disabled = false;
            }
        });
    }

    function setupPhotoGallery(form) {
        const grid = form.querySelector('[data-resource-photo-gallery]');
        const catalog = form.querySelector('[data-resource-photo-catalog]');
        const proposal = grid?.hasAttribute('data-resource-gallery-proposal') || false;
        const output = form.querySelector(
            proposal ? '[name="resource_published_photo_order"]'
                : '[name="resource_gallery_json"]'
        );
        if (!grid || !catalog || !output || !window.LlamaPhotoGallery) return;
        let photos;
        try {
            photos = JSON.parse(catalog.textContent || '[]');
        } catch (_) {
            photos = [];
        }
        if (!Array.isArray(photos)) return;
        const resourceId = Number(grid.dataset.resourceGalleryId);
        if (!Number.isInteger(resourceId) || resourceId < 1) return;
        const owner = 'resource:resource:' + resourceId;
        const gallery = window.LlamaPhotoGallery.mount(grid, {
            removeName: proposal ? 'resource_preview_only_remove[]'
                : 'resource_remove_photo_ids[]',
            onChange: () => sync(),
        });
        if (!gallery) return;
        const sync = () => {
            const snapshot = gallery.snapshot(photos);
            output.value = proposal
                ? JSON.stringify(snapshot.items.map(item => item.id))
                : (snapshot.dirty
                    ? JSON.stringify({target: owner, items: snapshot.items}) : '');
        };
        gallery.render(photos);
        sync();
        let submitting = false;
        form.addEventListener('submit', () => {
            sync();
            submitting = true;
        });
        window.addEventListener('beforeunload', event => {
            if (submitting || !gallery.snapshot(photos).dirty) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    for (const form of document.querySelectorAll('[data-resource-editor]')) {
        // Saved photos must render even if another Resource form control
        // later fails to initialize.
        setupPhotoGallery(form);
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
        setupResourceLookup(form);
        form.addEventListener('invalid', event => {
            let details = event.target.closest('details');
            while (details) {
                details.open = true;
                details = details.parentElement.closest('details');
            }
        }, true);
    }
})();
