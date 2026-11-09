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

        const locate = form.querySelector('[data-resource-locate]');
        const status = form.querySelector('[data-resource-location-status]');
        if (!locate || !status || !navigator.geolocation) continue;

        locate.hidden = false;
        locate.addEventListener('click', () => {
            locate.disabled = true;
            status.textContent = 'Finding your location…';
            navigator.geolocation.getCurrentPosition(({ coords }) => {
                form.elements.coordinates.value = `${coords.latitude.toFixed(7)}, ${coords.longitude.toFixed(7)}`;
                status.textContent = `Estimated GPS accuracy ±${Math.round(coords.accuracy)} m`;
                locate.disabled = false;
            }, () => {
                status.textContent = 'Location unavailable. Try again or enter coordinates.';
                locate.disabled = false;
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        });
    }
})();
