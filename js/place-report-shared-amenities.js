(() => {
    'use strict';

    const names = [
        'amenity_campground_host',
        'amenity_camp_store',
        'amenity_water_filling_station',
        'amenity_dishwashing_station',
        'amenity_accessible_showers',
        'shared_accessible_facilities',
        'shared_accessible_facility_types'
    ];

    const clean = value => String(value ?? '').trim();

    const escapeName = name =>
        typeof CSS !== 'undefined' && CSS.escape
            ? CSS.escape(name)
            : name.replace(/[^a-zA-Z0-9_-]/g, '\\$&');

    const readValue = (form, name) => {
        const escaped = escapeName(name);
        const arrayControls = [
            ...form.querySelectorAll(`[name="${escaped}[]"]`)
        ];

        if (arrayControls.length) {
            return arrayControls
                .filter(control => control.checked)
                .map(control => control.value);
        }

        const controls = [
            ...form.querySelectorAll(`[name="${escaped}"]`)
        ];

        if (!controls.length) {
            return '';
        }

        const radios = controls.filter(control => control.type === 'radio');

        if (radios.length) {
            return radios.find(control => control.checked)?.value
                ?? controls.find(control => control.type === 'hidden')?.value
                ?? '';
        }

        const checkbox = controls.find(control => control.type === 'checkbox');

        if (checkbox) {
            return checkbox.checked ? '1' : '0';
        }

        return controls.find(control => control.type !== 'hidden')?.value
            ?? controls[0].value
            ?? '';
    };

    const initialize = form => {
        const placeId = clean(
            form.querySelector('[name="place_id"]')?.value
        );
        const csrf = clean(
            form.querySelector('[name="csrf_token"]')?.value
        );
        const adminAction = clean(
            form.querySelector('[name="place_admin_action"]')?.value
        );

        if (!placeId || !csrf || adminAction !== 'save-report') {
            return;
        }

        const watched = names.flatMap(name => [
            ...form.querySelectorAll(`[name="${escapeName(name)}"]`),
            ...form.querySelectorAll(`[name="${escapeName(name)}[]"]`)
        ]);

        if (!watched.length) {
            return;
        }

        let dirty = false;

        watched.forEach(control => {
            control.addEventListener('input', () => {
                dirty = true;
            });
            control.addEventListener('change', () => {
                dirty = true;
            });
        });

        window.addEventListener(
            'llama:admin-place-report-saved',
            async event => {
                if (
                    !dirty
                    || String(event.detail?.placeId || '') !== placeId
                ) {
                    return;
                }

                const status = form.querySelector(
                    '[data-place-report-direct-save-status]'
                );

                try {
                    if (status) {
                        status.textContent = 'Saving shared facilities…';
                    }

                    const body = new FormData();
                    body.set('csrf_token', csrf);
                    body.set('place_id', placeId);

                    names.forEach(name => {
                        const value = readValue(form, name);

                        if (Array.isArray(value)) {
                            value.forEach(item => {
                                body.append(`${name}[]`, item);
                            });
                        } else {
                            body.set(name, value);
                        }
                    });

                    const response = await fetch(
                        '/save-shared-amenities.php',
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            body,
                            headers: {
                                Accept: 'application/json'
                            }
                        }
                    );

                    const payload = await response
                        .json()
                        .catch(() => ({}));

                    if (!response.ok || payload.ok !== true) {
                        throw new Error(
                            payload.error
                            || 'Shared facilities were not saved.'
                        );
                    }

                    dirty = false;

                    if (status) {
                        status.textContent = 'Shared facilities saved.';
                    }
                } catch (error) {
                    if (status) {
                        status.textContent =
                            error?.message
                            || 'Shared facilities were not saved.';
                    }
                }
            }
        );
    };

    document
        .querySelectorAll('form.place-report-form')
        .forEach(initialize);
})();
