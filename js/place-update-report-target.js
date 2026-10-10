(() => {
    'use strict';
    const q = (selector) => document.querySelector(selector);
    const search = q('[data-report-target-search]');
    const scope = q('[data-report-target-scope]');
    const source = q('[data-report-target-source]');
    const id = q('[data-report-target-id]');
    const status = q('[data-report-target-status]');
    const placeFields = q('[data-place-update-place-fields]');
    const scopedFields = q('[data-place-update-scoped-fields]');
    const questions = q('[data-scoped-report-questions]');
    const queueInput = q('[data-scoped-answers-json]');
    const notice = q('[data-scoped-field-notice]');
    const targetData = q('#place-update-report-target-data');
    const fieldData = q('#place-update-scoped-fields-data');
    if (![search, scope, source, id, status, placeFields, scopedFields, questions, queueInput, targetData, fieldData].every(Boolean)) return;

    let targets, fields;
    try {
        targets = JSON.parse(targetData.textContent);
        fields = JSON.parse(fieldData.textContent);
    } catch (_) { return; }
    const labels = new Map(targets.map(t => [`${t.label} (#${t.id}, ${t.scope}, ${t.source})`, t]));
    const place = targets.find(t => t.scope === 'place');
    if (!place) return;
    const list = q('#place-update-report-target-options');
    list.replaceChildren();
    for (const label of labels.keys()) list.append(new Option('', label));
    search.value = [...labels.keys()].find(k => labels.get(k) === place) || '';
    const form = search.closest('form');
    const values = new Map();
    const supported = new Set(['tri', 'permission', 'rating', 'select', 'text', 'textarea', 'number', 'date', 'url']);
    const unknown = '__LLAMA_UNKNOWN__';
    let activeTarget = '';

    function eligible(f) {
        return !f.derived && !f.location && f.scopes.includes(scope.value) && supported.has(f.type);
    }
    function make(tag, className, text) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    }
    function optionValues(f) {
        if (f.type === 'tri') return {'1': 'Yes', '0': 'No'};
        if (f.type === 'permission') return {'1': 'Yes', '0': 'No', '2': 'Permit', '3': 'Conditional'};
        if (f.type === 'rating') return {'1':'1','2':'2','3':'3','4':'4','5':'5'};
        if (f.type === 'select') return f.options || {};
        return null;
    }
    function render() {
        questions.replaceChildren();
        const sections = new Map();
        for (const f of fields.filter(eligible)) {
            const title = f.section || 'Other';
            let grid = sections.get(title);
            if (!grid) {
                const section = make('section', 'place-report-flow-group');
                section.append(make('h3', 'place-report-flow-heading', title.replaceAll('_', ' ')));
                grid = make('div', 'contribution-grid');
                section.append(grid);
                questions.append(section);
                sections.set(title, grid);
            }
            const card = make('div', 'contribution-field');
            card.dataset.scopedQuestion = f.key;
            card.append(make('span', 'place-report-field-label', f.label));
            const options = optionValues(f);
            let control;
            if (options) {
                control = make('select', 'contribution-input');
                control.append(new Option('Not answered', ''));
                for (const [value, label] of Object.entries(options)) control.append(new Option(String(label), String(value)));
                if (f.unknown) control.append(new Option('Unknown', unknown));
            } else if (f.type === 'textarea') {
                control = make('textarea', 'contribution-input');
                control.rows = 3;
            } else {
                control = make('input', 'contribution-input');
                control.type = ['number', 'date', 'url'].includes(f.type) ? f.type : 'text';
            }
            control.dataset.scopedControl = f.key;
            control.id = `scoped-answer-${f.key}`;
            control.setAttribute('aria-label', f.label);
            control.value = values.get(f.key) || '';
            const changed = () => {
                const value = control.value.trim();
                if (value) values.set(f.key, value);
                else values.delete(f.key);
                sync();
                applyDependencies();
            };
            control.addEventListener('change', changed);
            control.addEventListener('input', changed);
            card.append(control);
            grid.append(card);
        }
        applyDependencies();
    }
    function matchesRule(rule) {
        if (!rule || typeof rule !== 'object') return true;
        if (rule.operator === 'any') return Array.isArray(rule.rules) && rule.rules.some(matchesRule);
        if (rule.operator === 'all') return Array.isArray(rule.rules) && rule.rules.every(matchesRule);
        const actual = values.get(rule.field);
        if (rule.operator === 'equals') return actual === String(rule.value);
        if (rule.operator === 'in') return Array.isArray(rule.value) && rule.value.map(String).includes(actual);
        // Do not guess at unrecognized applicability operators.
        return true;
    }
    function applyDependencies() {
        for (const f of fields.filter(eligible)) {
            const card = [...questions.querySelectorAll('[data-scoped-question]')]
                .find(el => el.dataset.scopedQuestion === f.key);
            if (!card) continue;
            const rules = f.applicable_if || [];
            // Show an unanswered parent-dependent question only after the parent is answered.
            const applicable = !rules.length || rules.every(matchesRule);
            card.hidden = !applicable;
            const input = card.querySelector('[data-scoped-control]');
            if (input) input.disabled = !applicable;
        }
        sync();
    }
    function sync() {
        const entries = [];
        for (const f of fields.filter(eligible)) {
            if (!values.has(f.key)) continue;
            const card = [...questions.querySelectorAll('[data-scoped-question]')]
                .find(el => el.dataset.scopedQuestion === f.key);
            if (card && !card.hidden) entries.push({key: f.key, value: values.get(f.key)});
        }
        queueInput.value = JSON.stringify(entries);
        if (notice) notice.textContent = entries.length > 50 ? 'This report has more than 50 answers. Please submit a maximum of 50.' : `${entries.length} answer${entries.length === 1 ? '' : 's'} ready for review. Unanswered questions are not submitted.`;
    }
    function update() {
        const t = labels.get(search.value.trim());
        if (!t) {
            status.textContent = 'Choose an exact reporting target from the suggestions.';
            scope.value = ''; source.value = ''; id.value = '';
            return;
        }
        const targetKey = `${t.scope}:${t.source}:${t.id}`;
        const switched = targetKey !== activeTarget;
        activeTarget = targetKey;
        scope.value = t.scope; source.value = t.source; id.value = String(t.id);
        const scoped = t.scope !== 'place';
        placeFields.hidden = scoped;
        scopedFields.hidden = !scoped;
        for (const input of placeFields.querySelectorAll('input, select, textarea, button')) input.disabled = scoped;
        if (switched) {
            values.clear();
            queueInput.value = '[]';
            if (scoped) render();
        }
        status.textContent = scoped
            ? 'This Area or Site report will be reviewed before any changes are published.'
            : 'Entire Place uses the existing Scout Report.';
    }
    search.addEventListener('change', update);
    search.addEventListener('input', update);
    form?.addEventListener('submit', event => {
        const t = labels.get(search.value.trim());
        if (!t) {
            event.preventDefault(); status.textContent = 'Choose a listed reporting target.'; return;
        }
        if (t.scope !== 'place') {
            sync();
            const count = JSON.parse(queueInput.value).length;
            if (count === 0 || count > 50) {
                event.preventDefault(); status.textContent = count > 50 ? 'Submit no more than 50 answers at once.' : 'Answer at least one question before submitting.';
            }
        }
    });
    update();
})();
