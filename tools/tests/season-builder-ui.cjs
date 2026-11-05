'use strict';

/**
 * Browser-DOM regression for Admin seasonal changes.
 * Run: NODE_PATH=<path containing jsdom> node tools/tests/season-builder-ui.cjs
 *
 * Covers the real production JS, a matching form shape, a Save POST payload,
 * mixed existing Site answers, removal, and changed target protection.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../js/admin/place-season-builder.js'), 'utf8'
);
const questions = [
    {
        key: 'seasonal_site_closed',
        label: 'Normally closed this season?',
        type: 'tri',
        group: 'Site operating status',
        options: {'1': 'Yes, normally closed', '0': 'No, normally open'},
    },
    {
        key: 'site_reservation_method',
        label: 'Site reservation method',
        type: 'select',
        group: 'Site reservations & services',
        options: {
            reservable: 'Reservable',
            first_come: 'First-come, first-served',
            __UNKNOWN__: 'Unknown',
        },
    },
];

function startFixture(rows = [], removals = [], targetIds = [101, 102]) {
    const tokens = targetIds.map(id => 'site:campsite_record:' + id);
    const inputChoices = [101, 102, 103].map(id => {
        const token = 'site:campsite_record:' + id;
        return '<label data-season-target-option>'
            + '<input type="checkbox" name="season_targets[]"'
            + ' data-season-target-scope="site" value="' + token + '"'
            + (tokens.includes(token) ? ' checked' : '') + '>'
            + '<span>Site: ' + id + '</span></label>';
    }).join('');

    const config = JSON.stringify({questions, rows, targets: tokens.length, removals})
        .replace(/</g, '\\u003c');

    const html = '<!doctype html><html><body>'
        + '<form data-season-builder-load>'
        + '<select name="season_view"><option value="2" selected>Winter</option></select>'
        + '<details data-season-targets><summary data-season-target-summary></summary>'
        + '<input type="search" data-season-target-search>'
        + inputChoices
        + '<small data-season-target-count></small></details>'
        + '<button type="submit">Load conditions</button></form>'
        + '<form data-season-builder-form method="post">'
        + '<input type="hidden" name="place_admin_action" value="save-season-builder">'
        + '<input type="hidden" name="season_id" value="2">'
        + tokens.map(t => '<input type="hidden" name="season_targets[]" value="' + t + '">').join('')
        + '<button type="button" data-season-add-row>+ Add change</button>'
        + '<div data-season-builder-rows></div>'
        + '<p data-season-builder-hint>Choose a question and answer.</p>'
        + '<span data-season-builder-change-count>Loading…</span>'
        + '<button type="submit" data-season-builder-save disabled>Save seasonal changes</button>'
        + '<script type="application/json" data-season-builder-config>'
        + config + '</script></form>'
        + '</body></html>';

    const dom = new JSDOM(html, {
        url: 'https://admin.llamascout.com/place.php?id=17&season_view=2',
        runScripts: 'outside-only',
    });
    const {window} = dom;
    const alerts = [];
    window.alert = message => alerts.push(String(message));
    window.confirm = () => true;
    window.eval(source);
    const form = window.document.querySelector('[data-season-builder-form]');
    // Prevent jsdom from trying to actually navigate on native form submit.
    form.addEventListener('submit', event => event.preventDefault());
    return {window, form, alerts};
}

const change = (window, node, value) => {
    node.value = value;
    node.dispatchEvent(new window.Event('change', {bubbles: true}));
};

const values = form => new form.ownerDocument.defaultView.FormData(form);

{
    const {window, form, alerts} = startFixture();
    const add = form.querySelector('[data-season-add-row]');
    const rows = () => [...form.querySelectorAll('.admin-place-season-builder-row')];
    const save = form.querySelector('[data-season-builder-save]');

    assert.equal(rows().length, 1, 'a blank row should be present on first load');
    assert.equal(save.disabled, true, 'Save must not submit when no changes are made');
    add.click();
    assert.equal(rows().length, 2, '+ Add Change must create a row');

    change(window, rows()[0].querySelector('.admin-place-season-question'), 'seasonal_site_closed');
    change(window, rows()[0].querySelector('.admin-place-season-value'), '1');
    assert.equal(save.disabled, false, 'Save must enable after choosing seasonal closed = Yes');

    form.dispatchEvent(new window.Event('submit', {bubbles: true, cancelable: true}));
    let data = values(form);
    assert.equal(data.get('place_admin_action'), 'save-season-builder');
    assert.equal(data.get('season_changes[0][key]'), 'seasonal_site_closed');
    assert.equal(data.get('season_changes[0][value]'), '1');
    assert.deepEqual(data.getAll('season_targets[]'), [
        'site:campsite_record:101', 'site:campsite_record:102',
    ]);
    assert.deepEqual(alerts, [], 'valid seasonal answer should not raise an alert');

    change(window, rows()[0].querySelector('.admin-place-season-value'), '0');
    form.dispatchEvent(new window.Event('submit', {bubbles: true, cancelable: true}));
    data = values(form);
    assert.equal(data.get('season_changes[0][value]'), '0',
        'No, normally open must be saved as explicit 0 rather than treated as missing');

    const otherTarget = window.document.querySelector(
        'input[data-season-target-scope="site"][value="site:campsite_record:103"]'
    );
    otherTarget.checked = true;
    otherTarget.dispatchEvent(new window.Event('change', {bubbles: true}));
    assert.equal(save.disabled, true,
        'Changing selected Sites must require Load conditions before saving');
}

{
    const {window, form} = startFixture([
        {key: 'site_reservation_method', value: null, mixed: true},
    ]);
    const row = form.querySelector('.admin-place-season-builder-row');
    const save = form.querySelector('[data-season-builder-save]');
    assert.equal(save.disabled, true, 'Mixed existing answers must not be overwritten automatically');
    change(window, row.querySelector('.admin-place-season-value'), 'first_come');
    assert.equal(save.disabled, false, 'Explicit FCFS selection must enable Save');
    form.dispatchEvent(new window.Event('submit', {bubbles: true, cancelable: true}));
    const data = values(form);
    assert.equal(data.get('season_changes[0][key]'), 'site_reservation_method');
    assert.equal(data.get('season_changes[0][value]'), 'first_come');
}

{
    const {window, form} = startFixture([
        {key: 'seasonal_site_closed', value: '1', mixed: false},
    ]);
    const save = form.querySelector('[data-season-builder-save]');
    assert.equal(save.disabled, true, 'Preloaded unchanged answers are not unsaved changes');
    form.querySelector('[aria-label="Remove seasonal change"]').click();
    assert.equal(save.disabled, false, 'Removing a saved override enables Save');
    form.dispatchEvent(new window.Event('submit', {bubbles: true, cancelable: true}));
    const data = values(form);
    assert.deepEqual(data.getAll('season_removals[]'), ['seasonal_site_closed']);
}

{
    const {window, form} = startFixture([
        {key: 'seasonal_site_closed', value: '1', mixed: false, dirty: true},
    ]);
    const save = form.querySelector('[data-season-builder-save]');
    assert.equal(save.disabled, false,
        'Rejected PHP submission must restore an editable unsaved change');
    form.dispatchEvent(new window.Event('submit', {bubbles: true, cancelable: true}));
    assert.equal(values(form).get('season_changes[0][value]'), '1');
}

console.log('PASS: Admin seasonal Add Change, Save, No, FCFS, mixed values, removals, target protections, and rejected draft restore');
