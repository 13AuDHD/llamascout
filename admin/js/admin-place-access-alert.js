(() => {
    'use strict';

    const path = window.location.pathname;

    if (!path.endsWith('/moderate-report.php')) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const reportId = Number(params.get('id'));

    if (!Number.isInteger(reportId) || reportId < 1) {
        return;
    }

    const csrfInput = document.querySelector(
        'form.admin-report-status-form input[name="csrf_token"]'
    );

    const csrfToken = csrfInput?.value || '';

    if (!csrfToken) {
        return;
    }

    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = 'https://llamascout.com/css/admin/features/place-access-alert.css';
    document.head.appendChild(stylesheet);

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            ...options
        });

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (!response.ok || !payload?.ok) {
            throw new Error(
                payload?.message || 'The access warning request failed.'
            );
        }

        return payload;
    };

    const makeOption = (value, label) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    };

    const insertPanel = (payload) => {
        const anchor =
            document.querySelector('.admin-report-place-context')
            || document.querySelector('.admin-report-review-summary');

        if (!anchor) {
            return;
        }

        const panel = document.createElement('section');
        panel.className = 'admin-panel admin-place-access-alert-panel';
        panel.dataset.accessAlertPanel = '1';

        const header = document.createElement('header');
        header.className = 'admin-panel-header';

        const heading = document.createElement('div');
        const eyebrow = document.createElement('p');
        eyebrow.textContent = 'Visitor warning';
        const title = document.createElement('h2');
        title.textContent = 'Place Access Warning';
        heading.append(eyebrow, title);

        const badge = document.createElement('span');
        badge.className = 'admin-place-access-alert-state';
        header.append(heading, badge);

        const form = document.createElement('form');
        form.className = 'admin-place-access-alert-form';

        const toggleRow = document.createElement('label');
        toggleRow.className = 'admin-place-access-alert-toggle';
        const toggle = document.createElement('input');
        toggle.type = 'checkbox';
        toggle.name = 'enabled';
        const toggleCopy = document.createElement('span');
        const toggleStrong = document.createElement('strong');
        toggleStrong.textContent = 'Show a public access warning';
        const toggleSmall = document.createElement('small');
        toggleSmall.textContent =
            'Keep the Place published while warning visitors that recent information indicates access may be limited.';
        toggleCopy.append(toggleStrong, toggleSmall);
        toggleRow.append(toggle, toggleCopy);

        const fields = document.createElement('div');
        fields.className = 'admin-place-access-alert-fields';

        const stateLabel = document.createElement('label');
        stateLabel.innerHTML = '<span>Access condition</span>';
        const stateSelect = document.createElement('select');
        stateSelect.name = 'state';
        Object.entries(payload.states || {}).forEach(([value, label]) => {
            stateSelect.appendChild(makeOption(value, label));
        });
        stateLabel.appendChild(stateSelect);

        const reasonLabel = document.createElement('label');
        reasonLabel.innerHTML = '<span>Reason</span>';
        const reasonSelect = document.createElement('select');
        reasonSelect.name = 'reason';
        Object.entries(payload.reasons || {}).forEach(([value, label]) => {
            reasonSelect.appendChild(makeOption(value, label));
        });
        reasonLabel.appendChild(reasonSelect);

        const reviewLabel = document.createElement('label');
        reviewLabel.innerHTML = '<span>Review after</span>';
        const reviewInput = document.createElement('input');
        reviewInput.type = 'date';
        reviewInput.name = 'review_after';
        reviewLabel.appendChild(reviewInput);

        const publicLabel = document.createElement('label');
        publicLabel.className = 'is-wide';
        publicLabel.innerHTML = '<span>Public note (optional)</span>';
        const publicNote = document.createElement('textarea');
        publicNote.name = 'public_note';
        publicNote.rows = 3;
        publicNote.maxLength = 600;
        publicNote.placeholder =
            'Optional visitor-facing detail, such as “The east approach is gated for seasonal road work.”';
        publicLabel.appendChild(publicNote);

        const internalLabel = document.createElement('label');
        internalLabel.className = 'is-wide';
        internalLabel.innerHTML = '<span>Internal moderation note (optional)</span>';
        const internalNote = document.createElement('textarea');
        internalNote.name = 'internal_note';
        internalNote.rows = 3;
        internalNote.maxLength = 4000;
        internalNote.placeholder =
            'What was checked, what remains uncertain, or what should be verified next.';
        internalLabel.appendChild(internalNote);

        fields.append(
            stateLabel,
            reasonLabel,
            reviewLabel,
            publicLabel,
            internalLabel
        );

        const note = document.createElement('div');
        note.className = 'admin-place-access-alert-note';
        note.textContent =
            'This warning is separate from publication status. Confirmed closed does not automatically archive the Place, and a later check-in does not automatically clear the warning.';

        const footer = document.createElement('div');
        footer.className = 'admin-place-access-alert-actions';

        const save = document.createElement('button');
        save.className = 'admin-button';
        save.type = 'submit';
        save.textContent = 'Save access warning';

        const manage = document.createElement('a');
        manage.className = 'admin-button is-muted';
        manage.href = `/place.php?id=${Number(payload.report?.place_id || 0)}`;
        manage.textContent = 'Manage Place status';

        const status = document.createElement('span');
        status.className = 'admin-place-access-alert-message';
        status.setAttribute('aria-live', 'polite');

        footer.append(save, manage, status);
        form.append(toggleRow, fields, note, footer);
        panel.append(header, form);

        anchor.insertAdjacentElement('afterend', panel);

        const applyAlert = (alert) => {
            const active = Boolean(alert && Number(alert.is_active) === 1);
            toggle.checked = active;
            badge.textContent = active ? 'Public warning on' : 'No public warning';
            badge.classList.toggle('is-active', active);

            stateSelect.value = alert?.state || 'reported';
            reasonSelect.value = alert?.reason || 'unknown';
            reviewInput.value = alert?.review_after || '';
            publicNote.value = alert?.public_note || '';
            internalNote.value = alert?.internal_note || '';

            [
                stateSelect,
                reasonSelect,
                reviewInput,
                publicNote,
                internalNote
            ].forEach((control) => {
                control.disabled = !toggle.checked;
            });

            save.textContent = toggle.checked
                ? 'Save access warning'
                : (active ? 'Clear access warning' : 'Save');
        };

        applyAlert(payload.alert || null);

        toggle.addEventListener('change', () => {
            [
                stateSelect,
                reasonSelect,
                reviewInput,
                publicNote,
                internalNote
            ].forEach((control) => {
                control.disabled = !toggle.checked;
            });

            save.textContent = toggle.checked
                ? 'Save access warning'
                : 'Clear access warning';
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            save.disabled = true;
            status.textContent = 'Saving...';
            status.classList.remove('is-error', 'is-success');

            const body = new URLSearchParams();
            body.set('report_id', String(reportId));
            body.set('csrf_token', csrfToken);
            body.set('action', toggle.checked ? 'save' : 'clear');
            body.set('state', stateSelect.value);
            body.set('reason', reasonSelect.value);
            body.set('review_after', reviewInput.value);
            body.set('public_note', publicNote.value);
            body.set('internal_note', internalNote.value);

            try {
                const result = await request(
                    '/place-access-alert-api.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body: body.toString()
                    }
                );

                applyAlert(result.alert || null);
                status.textContent = result.message || 'Saved.';
                status.classList.add('is-success');
            } catch (error) {
                status.textContent = error.message;
                status.classList.add('is-error');
            } finally {
                save.disabled = false;
            }
        });
    };

    request(`/place-access-alert-api.php?report_id=${encodeURIComponent(reportId)}`)
        .then(insertPanel)
        .catch(() => {
            /* Non-access reports intentionally receive no panel. */
        });
})();
