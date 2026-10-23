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

    // Draft answers belong to an individual reporting target, not to the
    // reusable set of Site questions. Switching Site must never carry over
    // values from a different Site, even while answers remain unsent.
    const initialTargetKey =
        `${initialTarget.scope}:${initialTarget.source}:${initialTarget.id}`;
    const draftsByTarget = new Map();
    const initialAnswers = new Map(
        Object.entries(
            initial.answers && typeof initial.answers === 'object'
                ? initial.answers
                : {}
        ).map(([key, value]) => [key, value])
    );

    if (initialAnswers.size > 0) {
        draftsByTarget.set(initialTargetKey, new Map(initialAnswers));
    }

    let values = new Map();
    // Every control type is validated by the same canonical PHP parser.
    const supported = new Set(['tri', 'permission', 'rating', 'select', 'text', 'textarea', 'number', 'date', 'url', 'checkbox', 'multiselect']);

    // The Place form was rendered by the canonical PHP field renderer.
    // Capture its field elements before the Place section is hidden. These
    // are the ONLY source for scoped control markup, including labels,
    // options, help text and validation attributes.
    const sharedFieldTemplates = new Map();
    const named = [...placeFields.querySelectorAll('input[name], select[name], textarea[name]')];
    for (const f of fields) {
        const control = named.find(el => (el.name === f.key || el.name === `${f.key}[]`));
        const wrapper = control?.closest('.contribution-field, .contribution-check');
        if (wrapper) sharedFieldTemplates.set(f.key, wrapper.cloneNode(true));
    }
    const absentTemplates = new Set();
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

    // Native PHP radio groups are multiple inputs; all other supported
    // controls have a single editable input/select/textarea.
    function scopedControls(card) {
        return [...card.querySelectorAll('input:not([type="hidden"]), select, textarea')];
    }

    function equalValue(a, b) {
        if (Array.isArray(a) && Array.isArray(b)) {
            return JSON.stringify(a.map(String).sort()) === JSON.stringify(b.map(String).sort());
        }
        return String(a ?? '') === String(b ?? '');
    }

    function scopedValue(card, f) {
        const controls = scopedControls(card);
        if (f.type === 'multiselect') {
            if (card.querySelector('[data-report-multiselect-unknown]')?.checked) return unknown;
            return controls.filter(el => el.type === 'checkbox' && el.checked
                && !el.matches('[data-report-multiselect-unknown]')).map(el => el.value);
        }
        if (f.type === 'checkbox') return controls.find(el => el.type === 'checkbox')?.checked ? '1' : '0';
        if (card.querySelector('[data-season-picker]')) {
            const month = card.querySelector('[data-season-month]')?.value;
            const day = card.querySelector('[data-season-day]')?.value;
            return month && day ? `${month}-${day}` : '';
        }
        const radio = controls.find(el => el.type === 'radio');
        if (radio) return controls.find(el => el.type === 'radio' && el.checked)?.value || '';
        return controls[0]?.value?.trim() || '';
    }

    function setScopedValue(card, f, value) {
        const controls = scopedControls(card);
        if (f.type === 'multiselect') {
            for (const control of controls) {
                control.checked = control.matches('[data-report-multiselect-unknown]')
                    ? value === unknown : Array.isArray(value) && value.map(String).includes(control.value);
            }
            return;
        }
        if (f.type === 'checkbox') {
            const control = controls.find(el => el.type === 'checkbox');
            if (control) control.checked = value === '1' || value === 1 || value === true;
            return;
        }
        if (card.querySelector('[data-season-picker]')) {
            const parts = String(value || '').split('-');
            card.querySelector('[data-season-month]').value = parts[0] || '';
            card.querySelector('[data-season-day]').value = parts[1] || '';
            return;
        }
        const radios = controls.filter(el => el.type === 'radio');
        if (radios.length) {
            for (const radio of radios) radio.checked = radio.value === String(value);
            return;
        }
        const control = controls[0];
        if (!control) return;
        if (control.tagName === 'SELECT') {
            for (const option of control.querySelectorAll('[data-scoped-recorded-option]')) option.remove();
            if (value && ![...control.options].some(option => option.value === String(value))) {
                const numericFeet = f.key.endsWith('_feet') && /^-?\d+(?:\.\d+)?$/.test(String(value));
                const option = new Option(numericFeet ? `${Number(value)} ft` : `Recorded: ${value}`, String(value));
                option.dataset.scopedRecordedOption = '1';
                control.append(option);
            }
        }
        control.value = value ?? '';
    }

    function render() {
        questions.replaceChildren();
        absentTemplates.clear();
        const sections = new Map();
        const targetId = activeTarget.replace(/[^a-zA-Z0-9_-]/g, '-');

        // Registry controls eligible fields, scopes and order. PHP-rendered
        // field markup supplies labels, options, help and input semantics.
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

            const template = sharedFieldTemplates.get(f.key);
            if (!template) {
                // No synthetic replacement. A missing canonical PHP field is
                // a coverage defect to fix, not a reason to invent another UI.
                absentTemplates.add(f.key);
                const warning = make('p', 'place-report-answer-context',
                    `${f.label}: shared field markup missing. This question cannot be edited until the shared form is corrected.`);
                grid.append(warning);
                continue;
            }

            const card = template.cloneNode(true);
            card.dataset.scopedQuestion = f.key;
            // Scoped submissions use scoped_answers_json exclusively.
            // Never send cloned Place fields as extra POST parameters.
            for (const el of card.querySelectorAll('[name]')) el.removeAttribute('name');
            for (const el of card.querySelectorAll('[required]')) el.removeAttribute('required');
            for (const el of card.querySelectorAll('input[type="hidden"]')) el.remove();
            for (const el of card.querySelectorAll('[data-location-field]')) el.removeAttribute('data-location-field');

            // Canonical helpers have IDs used by aria-controls; make cloned
            // IDs unique across the Place form, Areas, and Sites.
            for (const el of card.querySelectorAll('[id]')) {
                const oldId = el.id;
                const newId = `scoped-${targetId}-${oldId}`;
                el.id = newId;
                for (const target of card.querySelectorAll('[aria-controls]')) {
                    if (target.getAttribute('aria-controls') === oldId) {
                        target.setAttribute('aria-controls', newId);
                    }
                }
                for (const toggle of card.querySelectorAll('[data-place-report-help-toggle]')) {
                    if (toggle.dataset.placeReportHelpToggle === oldId) {
                        toggle.dataset.placeReportHelpToggle = newId;
                    }
                }
            }

            // Help icons are wired at initial page load in the Place form.
            // Cloned Area/Site fields are created later, so bind the same
            // expandable helper behavior to each scoped clone.
            for (const toggle of card.querySelectorAll('[data-place-report-help-toggle]')) {
                const panelId = toggle.dataset.placeReportHelpToggle || '';
                const panel = [...card.querySelectorAll('[id]')]
                    .find(element => element.id === panelId);
                if (!panel) continue;
                const toggleHelp = event => {
                    if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
                    event.preventDefault();
                    event.stopPropagation();
                    const opening = !panel.classList.contains('is-open');
                    for (const other of questions.querySelectorAll('[data-place-report-help-panel].is-open')) {
                        other.classList.remove('is-open');
                    }
                    panel.classList.toggle('is-open', opening);
                    toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
                    toggle.closest('.place-report-field-help')?.classList.toggle(
                        'is-align-right', toggle.getBoundingClientRect().left > window.innerWidth / 2
                    );
                };
                toggle.addEventListener('click', toggleHelp);
                toggle.addEventListener('keydown', toggleHelp);
            }

            const controls = scopedControls(card);
            if (!controls.length) {
                absentTemplates.add(f.key);
                grid.append(make('p', 'place-report-answer-context',
                    `${f.label}: shared control unavailable for this target.`));
                continue;
            }
            for (const control of controls) {
                control.disabled = false;
                control.setAttribute('autocomplete', 'off');
                if (['radio','checkbox'].includes(control.type)) control.checked = false;
                else control.value = '';
            }

            // A changed field is sent as a proposal, not an edit to the
            // inherited Place/Area value or the published record.
            const controlTargetKey = activeTarget;
            const changed = event => {
                if (controlTargetKey !== activeTarget) return;
                if (event?.target?.type === 'radio' && event.target.checked) {
                    for (const other of scopedControls(card)) {
                        if (other.type === 'radio' && other !== event.target) other.checked = false;
                    }
                }
                if (f.type === 'multiselect' && event?.target?.type === 'checkbox') {
                    if (event.target.matches('[data-report-multiselect-unknown]') && event.target.checked) {
                        for (const option of card.querySelectorAll('[data-place-report-multiselect-option] input')) option.checked = false;
                    } else if (!event.target.matches('[data-report-multiselect-unknown]')) {
                        const unknownOption = card.querySelector('[data-report-multiselect-unknown]');
                        if (unknownOption) unknownOption.checked = false;
                    }
                }
                const value = scopedValue(card, f);
                const original = recordedValue(f);
                if (value !== '' && !equalValue(value, original)) values.set(f.key, value);
                else values.delete(f.key);
                draftsByTarget.set(activeTarget, new Map(values));
                sync();
                applyDependencies();
            };
            for (const control of controls) {
                control.addEventListener('change', changed);
                if (control.type !== 'radio') control.addEventListener('input', changed);
            }
            for (const clear of card.querySelectorAll('[data-place-report-clear]')) {
                clear.addEventListener('click', event => {
                    event.preventDefault();
                    for (const radio of scopedControls(card)) {
                        if (radio.type === 'radio') radio.checked = false;
                    }
                    changed();
                    showExisting();
                });
            }

            const existing = make('small', 'place-report-answer-context');
            existing.dataset.scopedExisting = f.key;
            card.append(existing);
            grid.append(card);
        }

        showExisting();
        applyDependencies();
        if (absentTemplates.size) {
            status.textContent = `Shared form is missing ${absentTemplates.size} field template(s). Updates for those fields are unavailable.`;
        }
    }

    function normalizedControlValue(f, value) {
        if (Array.isArray(value)) return value.map(String);
        if (value === null || value === undefined || value === '') return '';
        if (value === true) return '1';
        if (value === false) return '0';

        const raw = String(value);
        const options = optionValues(f);
        if (!options || raw === unknown) return raw;
        if (Object.prototype.hasOwnProperty.call(options, raw)) return raw;

        // RIDB numeric measurements may arrive as decimal strings (48.00).
        // Normalize the stored representation, not its precision, before
        // comparing it with our option keys. Do not round fractional feet.
        if (
            f.type === 'select'
            && /^-?\d+(?:\.\d+)?$/.test(raw.trim())
            && Object.keys(options).some(key => /^-?\d+(?:\.\d+)?$/.test(key))
        ) {
            const normalized = String(Number(raw));
            if (Object.prototype.hasOwnProperty.call(options, normalized)) {
                return normalized;
            }
            return normalized;
        }

        // Some published values use the displayed option label instead of
        // the canonical stored option key. Map only exact label matches.
        const match = Object.entries(options).find(
            ([, label]) => String(label).trim().toLowerCase() === raw.trim().toLowerCase()
        );
        return match ? match[0] : raw;
    }

    function recordedValue(f) {
        if (Object.prototype.hasOwnProperty.call(context.direct, f.key)) {
            return normalizedControlValue(f, context.direct[f.key]);
        }
        if (Object.prototype.hasOwnProperty.call(context.inherited, f.key)) {
            return normalizedControlValue(f, context.inherited[f.key]?.value);
        }
        return '';
    }

    function showExisting() {
        for (const f of fields.filter(eligible)) {
            const card = [...questions.querySelectorAll('[data-scoped-question]')]
                .find(el => el.dataset.scopedQuestion === f.key);
            if (!card) continue;
            const note = card.querySelector('[data-scoped-existing]');
            if (!note) continue;

            const visible = values.has(f.key)
                ? normalizedControlValue(f, values.get(f.key))
                : recordedValue(f);
            setScopedValue(card, f, visible);

            if (Object.prototype.hasOwnProperty.call(context.direct, f.key)) {
                note.textContent = `Recorded for this ${scope.value}`;
            } else if (Object.prototype.hasOwnProperty.call(context.inherited, f.key)) {
                note.textContent = `From ${context.inherited[f.key]?.from || 'parent'}`;
            } else {
                note.textContent = 'No recorded answer';
            }
            if (visible && scopedControls(card).some(el => el.type === 'radio')
                && !scopedControls(card).some(el => el.type === 'radio' && el.checked)) {
                // Preserve legacy data visibly without inventing a radio choice.
                note.textContent += ` · Recorded: ${visible}`;
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

    function applyDependencies() {
        // Issue #5 reconciliation mode: expose all eligible scoped questions.
        // Keep eligibility, target ownership, and answer persistence unchanged.
        for (const f of fields.filter(eligible)) {
            const card = [...questions.querySelectorAll('[data-scoped-question]')]
                .find(el => el.dataset.scopedQuestion === f.key);

            if (!card) continue;

            card.hidden = false;

            for (const input of scopedControls(card)) input.disabled = false;
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

        // Capture unsent changes for the departing Site or Area, then load
        // only the draft belonging to the new target. This also handles
        // switching back to an earlier Site without losing its draft.
        if (switched && activeTarget !== '') {
            draftsByTarget.set(activeTarget, new Map(values));
        }

        activeTarget = targetKey;
        scope.value = t.scope;
        source.value = t.source;
        id.value = String(t.id);

        const scoped = t.scope !== 'place';

        placeFields.hidden = scoped;
        scopedFields.hidden = !scoped;

        for (const card of placeFields.querySelectorAll('[data-report-field]')) {
            const field = fields.find(f => f.key === card.dataset.reportField);
            card.hidden = !scoped && field && !field.scopes.includes('place');
        }
        for (const input of placeFields.querySelectorAll('input, select, textarea, button')) {
            const card = input.closest('[data-report-field]');
            const field = card ? fields.find(f => f.key === card.dataset.reportField) : null;
            input.disabled = scoped || Boolean(field && !field.scopes.includes('place'));
        }

        const scopedHeading = scopedFields.querySelector('h3');
        if (scopedHeading) {
            scopedHeading.textContent = scoped
                ? `${t.scope === 'site' ? 'Site' : 'Area'} Scout Report: ${t.label}`
                : 'Area or Site Scout Report';
        }

        if (switched) {
            values = new Map(draftsByTarget.get(targetKey) || []);
            queueInput.value = '[]';

            if (scoped) {
                // Clear previous target's existing answers BEFORE rendering.
                // The asynchronous response may not have arrived yet.
                context = {direct: {}, inherited: {}};
                render();
                loadContext(t);
            } else {
                ++loadSequence;
                context = {direct: {}, inherited: {}};
                questions.replaceChildren();
            }
        }

        status.textContent = scoped
            ? (
                initial.locked === true
                    ? 'Revise the requested Area or Site answers below. The reporting target cannot be changed for this revision.'
                    : `Updating: ${t.label}`
            )
            : 'Updating: Entire Place';
        if (scoped && absentTemplates.size) {
            status.textContent = `${absentTemplates.size} shared question control(s) could not be loaded. Submit is disabled until they are corrected.`;
        }
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
            if (absentTemplates.size) {
                event.preventDefault();
                status.textContent = 'The shared form has missing controls. Please reload or report the missing fields before submitting.';
                return;
            }
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
