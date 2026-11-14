(() => {
    'use strict';

    const form = document.querySelector('[data-support-reply]');
    if (!form) return;

    const select = form.querySelector('[data-support-canned]');
    const body = form.querySelector('textarea[name="reply_body"]');
    const visibility = form.querySelector('[data-support-visibility]');
    const attachments = form.querySelector('[data-support-attachments]');
    const submit = form.querySelector('button[type="submit"]');

    const updateVisibility = () => {
        const internal = visibility.value === 'internal';
        attachments.disabled = internal;
        attachments.classList.toggle('is-hidden', internal);
        if (internal) attachments.value = '';
        submit.textContent = internal ? 'Save private note' : 'Save and send reply';
    };

    select.addEventListener('change', () => {
        const option = select.options[select.selectedIndex];
        if (!option || !option.dataset.body) return;

        const fields = {
            customer_name: body.dataset.customerName || '',
            ticket_number: body.dataset.ticketNumber || '',
            ticket_subject: body.dataset.ticketSubject || '',
        };
        const expanded = option.dataset.body.replace(
            /\{\{\s*(customer_name|ticket_number|ticket_subject)\s*\}\}/g,
            (match, key) => fields[key] || match
        );
        const existing = body.value.trim();
        body.value = existing ? existing + '\n\n' + expanded : expanded;
        body.focus();
    });

    visibility.addEventListener('change', updateVisibility);
    updateVisibility();
})();
