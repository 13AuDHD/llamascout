'use strict';

/*
 * A quick second tap must not issue another CREATE or RESEND POST.
 * Server-side validation still protects separate tabs and overlapping requests.
 */
document.addEventListener('DOMContentLoaded', () => {
    for (const form of document.querySelectorAll('[data-complimentary-send-form]')) {
        const submit = form.querySelector('button[type="submit"]');
        if (!submit) continue;

        form.addEventListener('submit', event => {
            if (form.dataset.invitationSubmitting === '1') {
                event.preventDefault();
                return;
            }
            if (!form.checkValidity()) return;

            form.dataset.invitationSubmitting = '1';
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            submit.dataset.originalText = submit.textContent.trim();
            const label = submit.querySelector('[data-invite-submit-label]');
            if (label) label.textContent = 'Sending Invitation…';
        });
    }

    // Safari can restore an old page from the back-forward cache.
    // A previously submitted form should not stay permanently disabled there.
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        for (const form of document.querySelectorAll('[data-complimentary-send-form]')) {
            delete form.dataset.invitationSubmitting;
            const submit = form.querySelector('button[type="submit"]');
            if (!submit) continue;
            submit.disabled = false;
            submit.removeAttribute('aria-busy');
            const label = submit.querySelector('[data-invite-submit-label]');
            if (label && submit.dataset.originalText) {
                label.textContent = submit.dataset.originalText;
            }
        }
    });
});
