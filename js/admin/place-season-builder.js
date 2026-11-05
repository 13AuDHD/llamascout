/* Compact seasonal Scout Report builder, using canonical server-supplied questions. */
(() => {
    'use strict';

    const form = document.querySelector('[data-season-builder-form]');
    const load = document.querySelector('[data-season-builder-load]');
    if (!form) return;

    const configNode = form.querySelector('[data-season-builder-config]');
    let config;
    try {
        config = JSON.parse(configNode?.textContent || '{}');
    } catch {
        return;
    }

    const questions = Array.isArray(config.questions) ? config.questions : [];
    const definitions = new Map(questions.map(q => [String(q.key), q]));
    const initialRows = Array.isArray(config.rows) ? config.rows : [];
    const list = form.querySelector('[data-season-builder-rows]');
    const save = form.querySelector('[data-season-builder-save]');
    const counter = form.querySelector('[data-season-builder-change-count]');
    const add = form.querySelector('[data-season-add-row]');
    const hint = form.querySelector('[data-season-builder-hint]');
    const normalHint = hint?.textContent || '';
    const loadedTargets = Array.from(form.querySelectorAll('input[name="season_targets[]"]'))
        .map(node => node.value).sort().join('|');
    const selectedTargetsChanged = () => {
        if (!load) return false;
        const checked = Array.from(load.querySelectorAll('input[name="season_targets[]"]:checked'))
            .map(node => node.value).sort().join('|');
        return checked !== loadedTargets;
    };
    const removed = new Set();
    let sequence = 0;

    const textValue = value => {
        if (value === true) return '1';
        if (value === false) return '0';
        if (value == null) return '';
        if (Array.isArray(value)) return value.join(', ');
        return String(value);
    };

    const option = (value, label) => {
        const item = document.createElement('option');
        item.value = String(value);
        item.textContent = String(label);
        return item;
    };

    const buildQuestionSelect = currentKey => {
        const select = document.createElement('select');
        select.className = 'admin-place-season-question';
        select.setAttribute('aria-label', 'Seasonal question');
        select.append(option('', 'What changes?'));

        const groups = new Map();
        for (const q of questions) {
            const name = String(q.group || 'Seasonal conditions');
            if (!groups.has(name)) {
                const group = document.createElement('optgroup');
                group.label = name;
                groups.set(name, group);
                select.append(group);
            }
            groups.get(name).append(option(q.key, q.label));
        }
        select.value = currentKey || '';
        return select;
    };

    const buildAnswerInput = (q, value, mixed) => {
        const opts = q && q.options && typeof q.options === 'object'
            ? Object.entries(q.options) : [];
        let control;
        const usesChoices = q && ['select', 'tri', 'checkbox', 'permission', 'rating'].includes(q.type);
        if (usesChoices && opts.length) {
            control = document.createElement('select');
            control.append(option('', mixed ? 'Mixed values; choose an answer' : 'Choose an answer'));
            for (const [key, label] of opts) {
                control.append(option(key, label));
            }
            control.value = mixed ? '' : textValue(value);
        } else if (q && q.type === 'textarea') {
            control = document.createElement('textarea');
            control.rows = 2;
            control.placeholder = mixed ? 'Mixed values; enter a shared answer'
                : (q?.options?.__UNKNOWN__ ? 'Seasonal answer, or type Unknown' : 'Seasonal answer');
            control.value = mixed ? '' : textValue(value) === '__UNKNOWN__'
                ? 'Unknown' : textValue(value);
        } else {
            control = document.createElement('input');
            control.type = q?.type === 'number' ? 'number' : 'text';
            control.placeholder = mixed ? 'Mixed values; enter a shared answer'
                : (q?.options?.__UNKNOWN__ ? 'Seasonal answer, or type Unknown' : 'Seasonal answer');
            control.value = mixed ? '' : textValue(value) === '__UNKNOWN__'
                ? 'Unknown' : textValue(value);
        }
        control.className = 'admin-place-season-value';
        control.setAttribute('aria-label', q ? 'Answer for ' + q.label : 'Seasonal answer');
        if (!q) control.disabled = true;
        return control;
    };

    const makeRow = (data = {}, insertAfter = null) => {
        const initialKey = String(data.key || '');
        const initialValue = data.mixed ? null : data.value;
        const row = document.createElement('div');
        row.className = 'admin-place-season-builder-row';
        row.dataset.sequence = String(++sequence);
        row._originalKey = initialKey;
        row._originalValue = textValue(initialValue);
        row._mixed = !!data.mixed;
        row._dirty = false;

        const question = buildQuestionSelect(initialKey);
        const answerSlot = document.createElement('div');
        answerSlot.className = 'admin-place-season-answer-slot';

        const plus = document.createElement('button');
        plus.type = 'button';
        plus.className = 'admin-place-season-row-action';
        plus.textContent = '+';
        plus.title = 'Add another seasonal change';
        plus.setAttribute('aria-label', 'Add seasonal change below');

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'admin-place-season-row-action is-remove';
        remove.textContent = '×';
        remove.title = 'Remove this seasonal change';
        remove.setAttribute('aria-label', 'Remove seasonal change');

        const actions = document.createElement('div');
        actions.className = 'admin-place-season-row-actions';
        actions.append(plus, remove);

        const replaceAnswer = (value, mixed) => {
            const q = definitions.get(question.value);
            answerSlot.replaceChildren(buildAnswerInput(q, value, mixed));
        };
        replaceAnswer(initialValue, row._mixed);
        row.append(question, answerSlot, actions);

        question.addEventListener('change', () => {
            row._dirty = true;
            row._mixed = false;
            replaceAnswer(null, false);
            refresh();
        });
        answerSlot.addEventListener('input', () => {
            row._dirty = true;
            row._mixed = false;
            refresh();
        });
        answerSlot.addEventListener('change', () => {
            row._dirty = true;
            row._mixed = false;
            refresh();
        });
        plus.addEventListener('click', () => makeRow({}, row));
        remove.addEventListener('click', () => {
            if (row._originalKey) removed.add(row._originalKey);
            row.remove();
            if (!list.children.length) makeRow();
            refresh();
        });

        if (insertAfter && insertAfter.parentNode === list) {
            insertAfter.after(row);
        } else {
            list.append(row);
        }
        refresh();
        return row;
    };

    const pending = () => {
        const changes = [];
        const deletions = new Set(removed);
        for (const row of list.querySelectorAll('.admin-place-season-builder-row')) {
            const question = row.querySelector('.admin-place-season-question');
            const control = row.querySelector('.admin-place-season-value');
            const key = String(question?.value || '');
            const value = String(control?.value || '').trim();
            if (row._originalKey && row._originalKey !== key) {
                deletions.add(row._originalKey);
            }
            if (!key) continue;
            if (row._originalKey === key && !row._dirty) continue;
            if (!value) {
                changes.push({key, value: '', missing: true});
            } else {
                changes.push({
                    key,
                    value: value === 'Unknown' && definitions.get(key)?.options?.__UNKNOWN__
                        ? '__UNKNOWN__' : value,
                });
            }
            deletions.delete(key);
        }
        return {changes, deletions};
    };

    function refresh() {
        const {changes, deletions} = pending();
        const total = changes.length + deletions.size;
        counter.textContent = total + (total === 1 ? ' change' : ' changes');
        const selectionChanged = selectedTargetsChanged();
        save.disabled = total === 0 || selectionChanged;
        if (hint) {
            hint.textContent = selectionChanged
                ? 'Your Report for selection changed. Click Load conditions before saving so these are applied to the correct Sites.'
                : normalHint;
        }
    }

    add?.addEventListener('click', () => makeRow());

    form.addEventListener('submit', event => {
        if (selectedTargetsChanged()) {
            event.preventDefault();
            window.alert('Click Load conditions to apply your new Place/Area/Site selection before saving.');
            return;
        }
        form.querySelectorAll('[data-season-builder-payload]').forEach(el => el.remove());
        const {changes, deletions} = pending();
        if (changes.some(c => c.missing)) {
            event.preventDefault();
            window.alert('Choose an answer for each question you changed.');
            return;
        }
        const keys = changes.map(c => c.key);
        if (new Set(keys).size !== keys.length) {
            event.preventDefault();
            window.alert('Each seasonal question may appear only once. Remove the duplicate row.');
            return;
        }
        if (changes.length === 0 && deletions.size === 0) {
            event.preventDefault();
            return;
        }

        const addHidden = (name, value) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = String(value);
            hidden.dataset.seasonBuilderPayload = '1';
            form.append(hidden);
        };
        changes.forEach((change, i) => {
            addHidden('season_changes[' + i + '][key]', change.key);
            addHidden('season_changes[' + i + '][value]', change.value);
        });
        Array.from(deletions).forEach(key => addHidden('season_removals[]', key));
    });

    if (initialRows.length) {
        initialRows.forEach(row => {
            if (definitions.has(String(row.key || ''))) makeRow(row);
        });
    }
    if (!list.children.length) makeRow();

    if (load) {
        const targetPanel = load.querySelector('[data-season-targets]');
        const search = load.querySelector('[data-season-target-search]');
        const targetCount = load.querySelector('[data-season-target-count]');
        const summary = load.querySelector('[data-season-target-summary]');
        const options = Array.from(load.querySelectorAll('[data-season-target-option]'));
        const boxes = options.map(el => el.querySelector('input[type="checkbox"]')).filter(Boolean);
        const updateTargets = () => {
            const checked = boxes.filter(x => x.checked);
            const n = checked.length;
            targetCount.textContent = n + ' selected';
            if (n === 1) {
                const box = checked[0];
                const label = box.closest('label')?.querySelector('span')?.textContent || '';
                summary.textContent = (box.dataset.seasonTargetScope === 'site' ? 'Site: '
                    : box.dataset.seasonTargetScope === 'area' ? 'Area: ' : 'Place: ') + label;
            } else {
                summary.textContent = n + ' Sites selected';
            }
        };
        boxes.forEach(box => box.addEventListener('change', () => {
            if (box.checked && box.dataset.seasonTargetScope !== 'site') {
                boxes.forEach(other => { if (other !== box) other.checked = false; });
            } else if (box.checked) {
                boxes.forEach(other => {
                    if (other.dataset.seasonTargetScope !== 'site') other.checked = false;
                });
            }
            if (boxes.filter(x => x.checked).length > 250) {
                box.checked = false;
                window.alert('Select no more than 250 Sites at once.');
            }
            updateTargets();
            refresh();
        }));
        search?.addEventListener('input', () => {
            const q = search.value.toLowerCase().trim();
            options.forEach(item => {
                item.hidden = q && !item.textContent.toLowerCase().includes(q);
            });
        });
        load.addEventListener('submit', event => {
            if (!boxes.some(x => x.checked)) {
                event.preventDefault();
                window.alert('Select a Place, Area, or Site.');
                targetPanel.open = true;
                return;
            }
            const draft = pending();
            if ((draft.changes.length || draft.deletions.size) && !window.confirm(
                'Discard unsaved seasonal changes and load another selection?'
            )) {
                event.preventDefault();
            }
        });
        updateTargets();
    }
})();
