(() => {
    'use strict';
    const q = (selector) => document.querySelector(selector);
    let search = q('[data-report-target-search]');
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
    const initialData = q('#place-update-report-initial-data');
    if (![search, scope, source, id, status, placeFields, scopedFields, questions, queueInput, targetData, fieldData].every(Boolean)) return;

    let targets, fields, initial;
    try {
        targets = JSON.parse(targetData.textContent);
        fields = JSON.parse(fieldData.textContent);
        initial = initialData
            ? JSON.parse(initialData.textContent || '{}')
            : {};
    } catch (_) { return; }

    // PHP renders the native select, grouping Place, Areas, and Sites.
    // There must be no second search control or dynamic replacement.
    const labels = new Map(
        targets.map(t => [`${t.scope}:${t.source}:${t.id}`, t])
    );
    const place = targets.find(t => t.scope === 'place');
    if (!place) return;

    const initialTarget =
        targets.find(target =>
            target.scope === String(initial.scope || 'place')
            && target.source === String(initial.source || 'place')
            && Number(target.id) === Number(initial.id || place.id)
        ) || place;

    search.value = `${initialTarget.scope}:${initialTarget.source}:${initialTarget.id}`;
    if (initial.locked === true) search.disabled = true;

    const form = search.closest('form');
    const values =
        new Map(
            Object.entries(
                initial.answers
                && typeof initial.answers === 'object'
                    ? initial.answers
                    : {}
            ).map(([key, value]) => [key, String(value)])
        );
    const supported = new Set(['tri', 'permission', 'rating', 'select', 'text', 'textarea', 'number', 'date', 'url']);
    const unknown = '__LLAMA_UNKNOWN__';
    let activeTarget = '';
    let context = {direct: {}, inherited: {}};
    let loadSequence = 0;

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

    function normalizeRuleValue(value) {
        if (value === true) return '1';
        if (value === false) return '0';
        if (value === null || value === undefined) return '';
        if (Array.isArray(value)) return value.map(item => String(item));
        return String(value);
    }

    function effectiveRuleValue(fieldKey) {
        if (values.has(fieldKey)) {
            return normalizeRuleValue(values.get(fieldKey));
        }

        if (Object.prototype.hasOwnProperty.call(context.direct, fieldKey)) {
            return normalizeRuleValue(context.direct[fieldKey]);
        }

        if (Object.prototype.hasOwnProperty.call(context.inherited, fieldKey)) {
            return normalizeRuleValue(context.inherited[fieldKey]?.value);
        }

        return '';
    }

    function render() {
        questions.replaceChildren();
        const sections = new Map();

        // The PHP registry has already applied the canonical field order.
        // Use its final section and group metadata without re-sorting fields.
        for (const f of fields.filter(eligible)) {
            const sectionKey = f.layout_section || 'legacy_review';
            const groupKey = f.layout_group || 'review';
            let sectionState = sections.get(sectionKey);

            if (!sectionState) {
                const section = make('section', 'place-report-flow-group');
                section.append(make(
                    'h3',
                    'place-report-flow-heading',
                    f.layout_section_label || 'Legacy Review'
                ));
                questions.append(section);
                sectionState = {element: section, groups: new Map()};
                sections.set(sectionKey, sectionState);
            }

            let grid = sectionState.groups.get(groupKey);

            if (!grid) {
                const group = make('section', 'place-report-flow-group');
                group.append(make(
                    'h4',
                    'place-report-flow-heading',
                    f.layout_group_label || 'Questions for Review'
                ));
                grid = make('div', 'contribution-grid');
                group.append(grid);
                sectionState.element.append(group);
                sectionState.groups.set(groupKey, grid);
            }

            const card = make('div', 'contribution-field');
            card.dataset.scopedQuestion = f.key;
            card.append(make('span', 'place-report-field-label', f.label));

            const options = optionValues(f);
            let control;

            if (options) {
                control = make('select', 'contribution-input');
                control.append(new Option('Not answered', ''));
                for (const [value, label] of Object.entries(options)) {
                    control.append(new Option(String(label), String(value)));
                }
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

                if (value) {
                    values.set(f.key, value);
                } else {
                    values.delete(f.key);
                }

                sync();
                applyDependencies();
            };

            control.addEventListener('change', changed);
            control.addEventListener('input', changed);
            card.append(control);

            const existing = make('small', 'place-report-answer-context');
            existing.dataset.scopedExisting = f.key;
            card.append(existing);

            grid.append(card);
        }

        showExisting();
        applyDependencies();
    }

    function valueLabel(f, value) {
        if (value === '__LLAMA_UNKNOWN__') return 'Unknown';
        if (value === null || value === undefined) return 'Not recorded';

        const options = optionValues(f);

        return options?.[String(value)]
            ?? (
                typeof value === 'object'
                    ? JSON.stringify(value)
                    : String(value)
            );
    }

    function showExisting() {
        for (const f of fields.filter(eligible)) {
            const note = [...questions.querySelectorAll('[data-scoped-existing]')]
                .find(el => el.dataset.scopedExisting === f.key);

            if (!note) continue;

            if (Object.prototype.hasOwnProperty.call(context.direct, f.key)) {
                note.textContent = `Recorded for this ${scope.value}: ${valueLabel(f, context.direct[f.key])}`;
            } else if (Object.prototype.hasOwnProperty.call(context.inherited, f.key)) {
                const info = context.inherited[f.key];
                note.textContent = `Inherited from ${info.from}: ${valueLabel(f, info.value)}`;
            } else {
                note.textContent = 'No recorded answer';
            }
        }
    }

    async function loadContext(target) {
        const sequence = ++loadSequence;
        context = {direct: {}, inherited: {}};
        showExisting();

        const url = new URL(location.href);
        url.searchParams.set('scoped_report_context', '1');
        url.searchParams.set('scope', target.scope);
        url.searchParams.set('source', target.source);
        url.searchParams.set('target_id', String(target.id));

        try {
            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('Context unavailable');

            const data = await response.json();

            if (sequence !== loadSequence) return;

            context = {
                direct: data.direct || {},
                inherited: data.inherited || {}
            };

            showExisting();
            applyDependencies();
        } catch (_) {
            if (sequence === loadSequence) {
                status.textContent = 'Existing answers could not be loaded. Please retry before submitting.';
                context = {direct: {}, inherited: {}};
                applyDependencies();
            }
        }
    }

    function matchesRule(rule) {
        if (!rule || typeof rule !== 'object') return true;

        if (rule.operator === 'any') {
            return Array.isArray(rule.rules) && rule.rules.some(matchesRule);
        }

        if (rule.operator === 'all') {
            return Array.isArray(rule.rules) && rule.rules.every(matchesRule);
        }

        const actual = effectiveRuleValue(rule.field);

        if (rule.operator === 'equals') {
            return !Array.isArray(actual)
                && actual === String(rule.value);
        }

        if (rule.operator === 'in') {
            if (!Array.isArray(rule.value)) return false;

            const allowed = rule.value.map(String);

            if (Array.isArray(actual)) {
                return actual.some(value => allowed.includes(String(value)));
            }

            return allowed.includes(actual);
        }

        // Do not guess at unrecognized applicability operators.
        return true;
    }

    function applyDependencies() {
        // Issue #5 reconciliation mode: expose all eligible scoped questions.
        // Keep eligibility, target ownership, and answer persistence unchanged.
        for (const f of fields.filter(eligible)) {
            const card = [...questions.querySelectorAll('[data-scoped-question]')]
                .find(el => el.dataset.scopedQuestion === f.key);

            if (!card) continue;

            card.hidden = false;

            const input = card.querySelector('[data-scoped-control]');
            if (input) input.disabled = false;
        }

        sync();
    }

    function sync() {
        const entries = [];

        for (const f of fields.filter(eligible)) {
            if (!values.has(f.key)) continue;

            const card = [...questions.querySelectorAll('[data-scoped-question]')]
                .find(el => el.dataset.scopedQuestion === f.key);

            if (card && !card.hidden) {
                entries.push({
                    key: f.key,
                    value: values.get(f.key)
                });
            }
        }

        queueInput.value = JSON.stringify(entries);

        if (notice) {
            notice.textContent = entries.length > 50
                ? 'This report has more than 50 answers. Please submit a maximum of 50.'
                : `${entries.length} answer${entries.length === 1 ? '' : 's'} ready for review. Unanswered questions are not submitted.`;
        }
    }

    function update() {
        const t = labels.get(search.value);

        if (!t) {
            status.textContent = 'Choose a reporting target.';
            scope.value = '';
            source.value = '';
            id.value = '';
            return;
        }

        const targetKey = `${t.scope}:${t.source}:${t.id}`;

        if (
            initial.locked === true
            && activeTarget !== ''
            && targetKey !== activeTarget
        ) {
            search.value = `${initialTarget.scope}:${initialTarget.source}:${initialTarget.id}`;
            return;
        }

        const switched = targetKey !== activeTarget;

        activeTarget = targetKey;
        scope.value = t.scope;
        source.value = t.source;
        id.value = String(t.id);

        const scoped = t.scope !== 'place';

        placeFields.hidden = scoped;
        scopedFields.hidden = !scoped;

        for (const input of placeFields.querySelectorAll('input, select, textarea, button')) {
            input.disabled = scoped;
        }

        if (switched) {
            const initialKey =
                `${initialTarget.scope}:${initialTarget.source}:${initialTarget.id}`;

            if (
                targetKey !== initialKey
                || initial.locked !== true
            ) {
                values.clear();
            }

            queueInput.value = '[]';

            if (scoped) {
                render();
                loadContext(t);
            } else {
                ++loadSequence;
            }
        }

        status.textContent = scoped
            ? (
                initial.locked === true
                    ? 'Revise the requested Area or Site answers below. The reporting target cannot be changed for this revision.'
                    : `Updating: ${t.label}`
            )
            : 'Updating: Entire Place';
    }

    search.addEventListener('change', update);

    form?.addEventListener('submit', event => {
        const t = labels.get(search.value);

        if (!t) {
            event.preventDefault();
            status.textContent = 'Choose a reporting target.';
            return;
        }

        if (t.scope !== 'place') {
            sync();

            const count = JSON.parse(queueInput.value).length;

            if (count === 0 || count > 50) {
                event.preventDefault();
                status.textContent = count > 50
                    ? 'Submit no more than 50 answers at once.'
                    : 'Answer at least one question before submitting.';
            }
        }
    });

    update();
})();
