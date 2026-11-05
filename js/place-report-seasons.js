/* Seasonal Scout Report selection. The selected Site is preserved on reload. */
(() => {
    'use strict';

    const form = document.querySelector('[data-scout-season-form]');
    if (!form) return;

    const selectedSite = form.querySelector('[data-season-selected-site]');
    const updateSelectedSite = () => {
        if (!selectedSite) return;
        const params = new URLSearchParams(window.location.search);
        const site = params.get('site') || '';
        selectedSite.value = /^[1-9][0-9]*$/.test(site) ? site : '';
    };

    form.addEventListener('submit', updateSelectedSite);
    form.querySelector('select[name="season"]')?.addEventListener('change', () => {
        updateSelectedSite();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        }
    });
})();
