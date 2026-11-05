/* Admin: bulk seasonal Site status picker. No network or extra dependencies. */
(() => {
    'use strict';

    const form = document.querySelector('[data-season-bulk-form]');
    if (!form) return;

    const search = form.querySelector('[data-season-bulk-search]');
    const items = Array.from(form.querySelectorAll('[data-season-bulk-site]'));
    const count = form.querySelector('[data-season-bulk-count]');
    const save = form.querySelector('[data-season-bulk-save]');
    const selectVisible = form.querySelector('[data-season-bulk-select-visible]');
    const clear = form.querySelector('[data-season-bulk-clear]');
    const limit = 250;
    const boxes = items.map(item => item.querySelector('input[type="checkbox"]'))
        .filter(Boolean);

    const selected = () => boxes.filter(box => box.checked);
    const updateCount = () => {
        const n = selected().length;
        count.textContent = n + ' selected' + (n >= limit ? ' (maximum 250)' : '');
        save.disabled = n === 0 || n > limit;
    };
    const applySearch = () => {
        const term = String(search.value || '').trim().toLowerCase();
        items.forEach(item => {
            item.hidden = term !== '' && !item.textContent.toLowerCase().includes(term);
        });
    };

    search?.addEventListener('input', applySearch);
    form.addEventListener('change', event => {
        if (event.target.matches('input[name="season_site_ids[]"]')) {
            if (selected().length > limit) {
                event.target.checked = false;
            }
            updateCount();
        }
    });
    selectVisible?.addEventListener('click', () => {
        let n = selected().length;
        for (const item of items) {
            if (item.hidden) continue;
            const box = item.querySelector('input[type="checkbox"]');
            if (!box || box.checked) continue;
            if (n >= limit) break;
            box.checked = true;
            n++;
        }
        updateCount();
    });
    clear?.addEventListener('click', () => {
        boxes.forEach(box => { box.checked = false; });
        updateCount();
    });
    form.addEventListener('submit', event => {
        const n = selected().length;
        if (n === 0 || n > limit) {
            event.preventDefault();
            return;
        }
        // A real submit confirms a deliberate batch action. Server-side
        // authorization, ownership and transaction validation still apply.
        const status = form.elements.namedItem('season_bulk_status')?.value || '';
        const verb = status === 'closed' ? 'mark as normally closed'
            : status === 'open' ? 'mark as normally open'
            : 'clear the seasonal status for';
        if (!window.confirm(
            'Apply ' + verb + ' ' + n + ' selected Sites for this season?'
        )) {
            event.preventDefault();
        }
    });

    updateCount();
})();
