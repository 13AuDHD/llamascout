(() => {
    'use strict';

    const names = [
        'place_open_year_round',
        'place_operating_start_month',
        'place_operating_start_day',
        'place_operating_end_month',
        'place_operating_end_day',
        'place_open_24_hours',
        'place_opening_time',
        'place_closing_time',
        'place_entrance_gate',
        'place_gate_access_type',
        'place_gate_opening_time',
        'place_gate_closing_time',
        'place_reported_operating_status',
        'place_closure_reason',
        'place_closure_began',
        'place_expected_reopening',
        'place_closure_source_url',
        'place_closure_review_date',
        'place_closure_notes'
    ];

    const clean = value =>
        String(value ?? '').trim();

    const escapeName = name =>
        typeof CSS !== 'undefined'
        && CSS.escape
            ? CSS.escape(name)
            : name.replace(
                /[^a-zA-Z0-9_-]/g,
                '\\$&'
            );

    const readValue = (
        form,
        name
    ) => {
        const controls = [
            ...form.querySelectorAll(
                `[name="${escapeName(name)}"]`
            )
        ];

        if (!controls.length) {
            return '';
        }

        const radios =
            controls.filter(
                control =>
                    control.type === 'radio'
            );

        if (radios.length) {
            return (
                radios.find(
                    control =>
                        control.checked
                )?.value
                ?? controls.find(
                    control =>
                        control.type === 'hidden'
                )?.value
                ?? ''
            );
        }

        const checkbox =
            controls.find(
                control =>
                    control.type === 'checkbox'
            );

        if (checkbox) {
            return checkbox.checked
                ? '1'
                : '0';
        }

        return (
            controls.find(
                control =>
                    control.type !== 'hidden'
            )?.value
            ?? controls[0].value
            ?? ''
        );
    };

    const initialize = form => {
        const placeId =
            clean(
                form.querySelector(
                    '[name="place_id"]'
                )?.value
            );

        const csrf =
            clean(
                form.querySelector(
                    '[name="csrf_token"]'
                )?.value
            );

        const adminAction =
            clean(
                form.querySelector(
                    '[name="place_admin_action"]'
                )?.value
            );

        if (
            !placeId
            || !csrf
            || adminAction !== 'save-report'
        ) {
            return;
        }

        const watched =
            names.flatMap(
                name => [
                    ...form.querySelectorAll(
                        `[name="${escapeName(name)}"]`
                    )
                ]
            );

        if (!watched.length) {
            return;
        }

        let dirty = false;

        watched.forEach(control => {
            control.addEventListener(
                'input',
                () => {
                    dirty = true;
                }
            );

            control.addEventListener(
                'change',
                () => {
                    dirty = true;
                }
            );
        });

        window.addEventListener(
            'llama:admin-place-report-saved',
            async event => {
                if (
                    !dirty
                    || String(
                        event.detail?.placeId
                        || ''
                    ) !== placeId
                ) {
                    return;
                }

                const status =
                    form.querySelector(
                        '[data-place-report-direct-save-status]'
                    );

                try {
                    if (status) {
                        status.textContent =
                            'Saving operating informationâ¦';
                    }

                    const body =
                        new FormData();

                    body.set(
                        'csrf_token',
                        csrf
                    );

                    body.set(
                        'place_id',
                        placeId
                    );

                    names.forEach(name => {
                        body.set(
                            name,
                            readValue(
                                form,
                                name
                            )
                        );
                    });

                    const response =
                        await fetch(
                            '/save-operating-report.php',
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                body,
                                headers: {
                                    Accept: 'application/json'
                                }
                            }
                        );

                    const payload =
                        await response
                            .json()
                            .catch(() => ({}));

                    if (
                        !response.ok
                        || payload.ok !== true
                    ) {
                        throw new Error(
                            payload.error
                            || 'Operating information was not saved.'
                        );
                    }

                    dirty = false;

                    if (status) {
                        status.textContent =
                            payload.needs_closure_review
                                ? 'Operating information saved. Closure report is ready for verification.'
                                : 'Operating information saved.';
                    }
                } catch (error) {
                    if (status) {
                        status.textContent =
                            error?.message
                            || 'Operating information was not saved.';
                    }
                }
            }
        );
    };

    document
        .querySelectorAll(
            'form.place-report-form'
        )
        .forEach(initialize);
})();
