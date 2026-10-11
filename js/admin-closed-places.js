(() => {
    'use strict';
    const form = document.getElementById('closure-form');
    if (!form) return;
    const editor = document.getElementById('closure-editor-title');
    document.querySelectorAll('[data-closure-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            const mapping = {
                place_id: 'placeId', campsite_id: 'campsiteId',
                status: 'status', reason: 'reason', closure_began: 'began',
                expected_reopening: 'reopening', review_date: 'review',
                source_url: 'source', notes: 'notes'
            };
            for (const [name, attr] of Object.entries(mapping)) {
                const control = form.elements.namedItem(name);
                if (control) {
                    const value = button.dataset[attr] || '';
                    control.value = name === 'campsite_id' && value === '0' ? '' : value;
                }
            }
            editor?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            form.elements.namedItem('status')?.focus();
        });
    });
});
