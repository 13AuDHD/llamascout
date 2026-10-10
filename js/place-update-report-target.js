/* Reporting target selector for the existing update form.
 * Area/Site submission is deliberately blocked until reviewed persistence
 * is connected; never submit their answers as Place-wide changes.
 */
(() => {
    'use strict';
    const search = document.querySelector('[data-report-target-search]');
    const source = document.querySelector('[data-report-target-source]');
    const scope = document.querySelector('[data-report-target-scope]');
    const id = document.querySelector('[data-report-target-id]');
    const status = document.querySelector('[data-report-target-status]');
    const data = document.getElementById('place-update-report-target-data');
    if (!search || !source || !scope || !id || !status || !data) return;
    let targets;
    try { targets = JSON.parse(data.textContent); }
    catch (_) { return; }
    const options = new Map();
    for (const target of targets) {
        options.set(`${target.label} (#${target.id}, ${target.scope})`, target);
    }
    const place = targets.find(t => t.scope === 'place');
    if (!place) return;
    const form = search.closest('form');
    const submitButtons = form ? [...form.querySelectorAll('button[type="submit"]')] : [];
    const update = () => {
        const found = options.get(search.value.trim());
        const selected = found || place;
        scope.value = selected.scope;
        source.value = selected.source;
        id.value = String(selected.id);
        const blocked = !found || selected.scope !== 'place';
        for (const button of submitButtons) button.disabled = blocked;
        status.textContent = !found
            ? 'Select an option from the suggestions before submitting.'
            : blocked
                ? 'Area/Site editing is not enabled yet. No scoped answers will be saved. Select Entire Place to continue.'
                : 'Entire Place updates use the existing review process.';
    };
    search.addEventListener('input', update);
    search.addEventListener('change', update);
    if (form) form.addEventListener('submit', event => {
        if (scope.value !== 'place' || !options.has(search.value.trim())) {
            event.preventDefault();
            update();
        }
    });
    update();
})();
