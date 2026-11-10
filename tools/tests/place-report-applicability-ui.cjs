'use strict';

// Actual browser-DOM regression for shared Add/Update/Admin visibility.
// The source under test is the production js/place-report-form.js file.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../js/place-report-form.js'), 'utf8'
);

const rules = {
    site_hookups_available: [
        {field: 'type', operator: 'in', value: ['developed-campground']},
    ],
    hookup_electric: [
        {field: 'type', operator: 'in', value: ['developed-campground']},
    ],
    hookup_electric_service: [
        {field: 'site_hookups_available', operator: 'equals', value: '1'},
    ],
    generator_prohibited: [
        {field: 'generator_restrictions', operator: 'equals', value: '1'},
    ],
    generator_quiet_hours: [
        {field: 'generator_restrictions', operator: 'equals', value: '1'},
        {field: 'generator_prohibited', operator: 'equals', value: '0'},
    ],
    generator_quiet_hours_begin: [
        {field: 'generator_restrictions', operator: 'equals', value: '1'},
        {field: 'generator_prohibited', operator: 'equals', value: '0'},
        {field: 'generator_quiet_hours', operator: 'equals', value: '1'},
    ],
    tent_pad_width_feet: [
        {field: 'tent_pad', operator: 'equals', value: '1'},
    ],
};
const keys = [
    'type', 'site_hookups_available', 'hookup_electric',
    'hookup_electric_service', 'generator_restrictions',
    'generator_prohibited', 'generator_quiet_hours',
    'generator_quiet_hours_begin', 'tent_pad', 'tent_pad_width_feet',
    'independent_site_question',
];
const config = {
    unknown_token: '__LLAMA_UNKNOWN__',
    unanswered_token: '__LLAMA_UNANSWERED__',
    fields: keys.map(key => ({
        key,
        derived: false,
        type: key === 'type' ? 'select' : 'tri',
        counts_toward_completion: true,
        applicable_if: rules[key] || [],
    })),
};

const fieldHtml = key => (
    '<label class="contribution-field" data-report-field="' + key + '">'
    + '<select name="' + key + '">'
    + '<option value="__LLAMA_UNANSWERED__">Unanswered</option>'
    + '<option value="0">No</option>'
    + '<option value="1">Yes</option>'
    + '<option value="__LLAMA_UNKNOWN__">Unknown</option>'
    + '</select></label>'
);
const rawConfig = JSON.stringify(config).replace(/</g, '\\u003c');
const html = '<!doctype html><html><body>'
    + '<form class="place-report-form">'
    + '<script type="application/json" data-place-report-schema-config'
    + ' data-inventory-review="0">' + rawConfig + '</script>'
    + '<details class="contribution-section" open>'
    + '<div class="contribution-section-body">'
    + keys.map(fieldHtml).join('')
    + '</div></details>'
    + '</form>'
    + '</body></html>';
const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/place.php?id=17',
    runScripts: 'outside-only',
});
const {window} = dom;
window.CSS = window.CSS || {};
window.CSS.escape = value => String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
window.LlamaPlaceReportForm = {setupRecovery() {}};
window.eval(source);

const form = window.document.querySelector('.place-report-form');
const field = key => form.querySelector('[data-report-field="' + key + '"]');
const select = key => form.querySelector('select[name="' + key + '"]');
const change = (key, value) => {
    select(key).value = value;
    select(key).dispatchEvent(new window.Event('change', {bubbles: true}));
};

assert.equal(field('hookup_electric_service').hidden, true,
    'Unanswered Electric must hide Electric Service');
assert.equal(field('generator_prohibited').hidden, true,
    'Generator Restrictions unanswered must hide Prohibited');
assert.equal(field('generator_quiet_hours_begin').hidden, true,
    'Generator Quiet Hours start must remain hidden until parents match');
assert.equal(field('tent_pad_width_feet').hidden, true,
    'Tent Pad Width must remain hidden without Tent Pad Yes');
assert.equal(field('independent_site_question').hidden, false,
    'Independent question stays visible');

const typeControl = select('type');
const option = window.document.createElement('option');
option.value = 'developed-campground';
option.textContent = 'Developed Campground';
typeControl.append(option);
change('type', 'developed-campground');
assert.equal(field('hookup_electric').hidden, false,
    'Utility question is independent of the general hookup parent');
change('site_hookups_available', '0');
assert.equal(field('hookup_electric').hidden, false,
    'Broad Hookups No does not change independent Electric status');
assert.equal(field('hookup_electric_service').hidden, true,
    'Broad Hookups No hides approved Site Equipment details');
change('site_hookups_available', '1');
assert.equal(field('hookup_electric_service').hidden, false,
    'Broad Hookups Yes reveals Electric Service');
change('site_hookups_available', '__LLAMA_UNKNOWN__');
assert.equal(field('hookup_electric_service').hidden, true,
    'Unknown Hookups does not activate a Yes-only detail');

change('generator_restrictions', '1');
assert.equal(field('generator_prohibited').hidden, false,
    'Generator Restrictions Yes reveals the Prohibited follow-up');
change('generator_prohibited', '0');
assert.equal(field('generator_quiet_hours').hidden, false,
    'Generators not prohibited reveals Quiet Hours');
change('generator_quiet_hours', '1');
assert.equal(field('generator_quiet_hours_begin').hidden, false,
    'Matching nested generator parents reveal the start-time question');
change('generator_restrictions', '0');
assert.equal(field('generator_prohibited').hidden, true,
    'Restrictions No hides Prohibited question');
assert.equal(field('generator_quiet_hours').hidden, true,
    'Stale Prohibited No cannot reveal Quiet Hours when restrictions are hidden');
assert.equal(field('generator_quiet_hours_begin').hidden, true,
    'Stale Quiet Hours Yes cannot reveal a grandchild');
assert.equal(select('generator_quiet_hours').value, '1',
    'Hidden answers remain stored for future review');

change('generator_restrictions', '__LLAMA_UNKNOWN__');
assert.equal(field('generator_prohibited').hidden, true,
    'Unknown Restrictions does not trigger a Yes-only child');

change('tent_pad', '1');
assert.equal(field('tent_pad_width_feet').hidden, false,
    'Tent Pad Yes reveals dimensions');
change('tent_pad', '0');
assert.equal(field('tent_pad_width_feet').hidden, true,
    'Tent Pad No hides dimensions');

change('type', '0');
assert.equal(field('hookup_electric').hidden, true,
    'Inapplicable Place type hides Site utility questions');
assert.equal(field('hookup_electric_service').hidden, true,
    'An inapplicable parent cannot activate its child');

window.close();

const siteLists = {
    landscape_details: ['rocky', 'wooded'],
    landscape_views: ['forest-woodland', 'mountain'],
    connectivity_services_tested: ['starlink', 'amazon_leo', 'other'],
};

async function testAdminSiteEditing(overrides = null) {
    const site = {id: 'campsite_record:5', site_id: 5, label: 'Site 5', imported_values: {tent_pad: '1'}, override_values: overrides};
    const catalog = {
        sites: [site],
        site_field_keys: ['tent_pad', 'tent_pad_width_feet', 'independent_site_question', ...Object.keys(siteLists)],
        place_field_keys: ['generator_restrictions'],
    };
    const adminConfig = {
        ...config,
        fields: [...config.fields, ...Object.keys(siteLists).map(key => ({key, type: 'multiselect'}))],
    };
    const listFields = Object.entries(siteLists).map(([key, options]) =>
        `<div data-report-field="${key}" data-place-report-multiselect>`
        + options.map(value => `<label data-place-report-multiselect-option><input type="checkbox" name="${key}[]" value="${value}"></label>`).join('')
        + '</div>'
    ).join('');
    const context = {place: {place_id: 17}, sites: {[site.id]: site}};
    const mapData = {
        place: {slug: 'test-place'},
        features: [],
        sites: [{id: site.id, label: site.label, latitude: 37.25, longitude: -107.75}],
    };
    const admin = new JSDOM(
        '<div data-admin-place-map-panel><div data-admin-place-map></div>'
        + '<a data-admin-place-map-edit></a>'
        + '<script type="application/json" data-admin-place-map-data>' + JSON.stringify(mapData) + '</script></div>'
        + '<form id="place-report" class="place-report-form">'
        + '<script type="application/json" data-place-report-schema-config>' + JSON.stringify(adminConfig) + '</script>'
        + '<script type="application/json" data-admin-place-campsites>' + JSON.stringify(catalog) + '</script>'
        + '<script type="application/json" data-admin-place-context>' + JSON.stringify(context) + '</script>'
        + '<details class="contribution-section" data-report-main-section="site">'
        + '<label data-admin-site-picker><select name="selected_campsite_id" data-admin-campsite-selector>'
        + '<option value="all-sites">All Sites</option><option value="" selected>Select a Site</option>'
        + '<option value="campsite_record:5"' + (overrides ? ' selected' : '') + '>Site 5</option>'
        + '</select></label><label data-admin-overwrite-site-panel hidden>'
        + '<input type="checkbox" role="switch" data-admin-overwrite-site-answers></label>'
        + keys.map(fieldHtml).join('') + listFields
        + '</details><button type="button" data-place-report-admin-save>Save Place Report</button></form>',
        {url: 'https://admin.llamascout.com/place.php?id=17', runScripts: 'outside-only'}
    );
    const w = admin.window;
    try {
        w.CSS = {escape: window.CSS.escape};
        w.LlamaPlaceReportForm = {setupRecovery() {}};
        const points = [];
        const pins = [];
        const views = [];
        const map = {
            createPane() {},
            getPane: () => ({style: {}}),
            fitBounds() {},
            setView: point => views.push(point),
            invalidateSize() {},
        };
        w.requestAnimationFrame = callback => callback();
        w.L = {
            map: () => map,
            control: {zoom: () => ({addTo() {}})},
            tileLayer: () => ({addTo() {}, bringToBack() {}}),
            latLngBounds: () => ({extend: point => points.push(point), isValid: () => points.length > 0}),
            circleMarker: point => {
                const pin = {
                    addTo: () => pin,
                    bindTooltip() {},
                    on: (event, handler) => { pin[event] = handler; },
                    getLatLng: () => point,
                    setStyle() {},
                    setRadius() {},
                };
                pins.push(pin);
                return pin;
            },
        };
        let saved;
        w.confirm = () => true;
        w.alert = () => {};
        w.fetch = async (_url, options) => {
            saved = options.body;
            return {ok: true, text: async () => '{"success":true}'};
        };
        w.eval(source);
        for (const script of ['place-report-save.js', 'place-map.js', 'place-context.js']) {
            w.eval(fs.readFileSync(path.resolve(__dirname, '../../js/admin', script), 'utf8'));
        }
        const get = selector => w.document.querySelector(selector);
        const question = key => get(`[data-report-field="${key}"]`);
        const input = key => get(`[name="${key}"]`);
        const update = (control, value) => {
            control.value = value;
            control.dispatchEvent(new w.Event('change', {bubbles: true}));
        };
        const picker = get('[data-admin-campsite-selector]');
        assert.equal(get('[data-admin-site-picker]').hidden, false, 'The Site picker is independent of questions and Place type');
        assert.equal(pins.length, 1, 'A saved Site point renders without any map feature geometry');
        if (overrides) {
            assert.equal(picker.value, site.id, 'Opening a direct Site link keeps the Site selected');
            assert.equal(input('tent_pad').value, '0', 'Direct Site links load existing answers');
            assert.equal(question('tent_pad_width_feet').hidden, true, 'Reload respects the saved parent answer');
            for (const [key, options] of Object.entries(siteLists)) {
                assert.deepEqual(Array.from(w.document.querySelectorAll(`[name="${key}[]"]:checked`), control => control.value), options,
                    `${key} restores every saved choice`);
            }
            get('[data-place-report-admin-save]').click();
            assert.deepEqual(JSON.parse(saved.get('admin_site_answers_json')), {}, 'Unchanged lists are not rewritten');
            await new Promise(resolve => setImmediate(resolve));
            return;
        }
        assert.equal(question('independent_site_question').hidden, true, 'Site-only questions wait for a target');
        assert.equal(input('independent_site_question').disabled, false, 'Questions are hidden without being locked');

        const overwritePanel = get('[data-admin-overwrite-site-panel]');
        const overwrite = get('[data-admin-overwrite-site-answers]');
        assert.equal(overwritePanel.hidden, true);
        update(picker, 'all-sites');
        assert.equal(question('independent_site_question').hidden, false, 'Bulk editing reveals Site questions');
        assert.equal(overwritePanel.hidden, false);
        assert.equal(overwrite.checked, false, 'Existing answers are protected by default');
        update(input('tent_pad'), '1');
        get('[data-place-report-admin-save]').click();
        assert.equal(saved.has('selected_campsite_id'), false, 'All Sites is not a Site ID');
        assert.deepEqual(JSON.parse(saved.get('admin_bulk_site_answers_json')), {answers: {tent_pad: '1'}, overwrite: false});
        await new Promise(resolve => setImmediate(resolve));
        overwrite.checked = true;
        update(input('tent_pad'), '0');
        get('[data-place-report-admin-save]').click();
        assert.deepEqual(JSON.parse(saved.get('admin_bulk_site_answers_json')), {answers: {tent_pad: '0'}, overwrite: true});
        await new Promise(resolve => setImmediate(resolve));

        pins[0].click();
        await Promise.resolve();
        assert.equal(picker.value, site.id, 'Tapping a point-only Site selects its canonical report');
        assert.equal(overwritePanel.hidden, true, 'Single-Site editing has no overwrite toggle');
        assert.deepEqual(Array.from(views.at(-1)), [37.25, -107.75], 'Map centers on the selected Site point');
        assert.equal(new URL(get('[data-admin-place-map-edit]').href).searchParams.get('site_id'), '5');
        assert.equal(get('[data-report-main-section="site"]').open, true, 'Selecting a map Site opens its report section');
        assert.equal(question('independent_site_question').hidden, false, 'A selected Site reveals its own questions');
        assert.equal(input('tent_pad').disabled, false, 'Imported Site answers are editable');
        assert.equal(input('generator_restrictions').disabled, false, 'Place answers stay editable with a Site selected');
        assert.equal(input('tent_pad').value, '0', 'Successful bulk overwrite stays visible when reopening an individual Site');
        assert.equal(question('tent_pad_width_feet').hidden, true, 'Bulk-updated parent No keeps its child hidden');
        update(input('tent_pad'), '1');
        assert.equal(question('tent_pad_width_feet').hidden, false, 'Changing the parent back to Yes reveals its child');
        update(input('tent_pad'), '0');
        assert.equal(question('tent_pad_width_feet').hidden, true, 'Parent change still hides its child');
        update(input('generator_restrictions'), '1');
        for (const key of Object.keys(siteLists)) {
            for (const checkbox of w.document.querySelectorAll(`[name="${key}[]"]`)) checkbox.click();
        }
        get('[data-place-report-admin-save]').click();
        assert.equal(saved.get('selected_campsite_id'), site.id);
        const savedAnswers = JSON.parse(saved.get('admin_site_answers_json'));
        assert.deepEqual(savedAnswers, {...siteLists},
            'A previously bulk-saved Site answer is not rewritten during an unrelated individual Site edit');
        assert.equal(saved.get('generator_restrictions'), '1', 'Place answer remains in the Place payload');
        await new Promise(resolve => setImmediate(resolve));
        update(picker, '');
        assert.equal(question('independent_site_question').hidden, true, 'Clearing the Site hides its questions again');
        return savedAnswers;
    } finally {
        w.close();
    }
}

async function testClosureDates() {
    const keys = ['season_begins', 'season_ends'];
    const dates = ['12-15', '10-31'];
    const options = values => '<option value="">Select</option>' + values.map(value => `<option>${value}</option>`).join('');
    const dom = new JSDOM('<form>' + keys.map((key, index) =>
        `<div data-season-picker><select data-season-month>${options(['02', '10', '12'])}</select>`
        + `<select data-season-day>${options(['15', '29', '31'])}</select>`
        + `<input type="hidden" name="${key}" value="${dates[index]}" data-season-value></div>`
    ).join('') + '</form>', {runScripts: 'outside-only'});
    const w = dom.window;
    try {
        w.eval(fs.readFileSync(path.resolve(__dirname, '../../js/place-report-season-picker.js'), 'utf8'));
        await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, {once: true}));
        const form = w.document.querySelector('form');
        assert.deepEqual(Array.from(new w.FormData(form).values()), dates, 'Opening the report preserves both dates');
        const month = form.querySelector('[data-season-month]');
        const day = form.querySelector('[data-season-day]');
        assert.equal(month.value, '12');
        assert.equal(day.value, '15');
        form.elements.season_begins.value = '02-29';
        form.dispatchEvent(new w.Event('change'));
        assert.equal(month.value, '02', 'Scoped date changes update the picker');
        assert.equal(day.value, '29');
        day.value = '15';
        day.dispatchEvent(new w.Event('change', {bubbles: true}));
        assert.equal(new w.FormData(form).get('season_begins'), '02-15', 'Changing a picker submits the new date');
    } finally {
        w.close();
    }
}

testAdminSiteEditing().then(testAdminSiteEditing).then(testClosureDates).then(() => {
    console.log('PASS: closure dates and Site selections survive reload, parent-child visibility and scoped saves');
}).catch(error => {
    console.error(error);
    process.exitCode = 1;
});
