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

async function testAdminSiteEditing() {
    const schema = {
        ...config,
        fields: [
            ...config.fields,
            {
                key: 'site_number',
                applicable_if: [{field: 'type', operator: 'equals', value: 'developed-campground'}],
            },
        ],
    };
    const site = {id: 'campsite_record:5', site_id: 5, label: 'Site 5', imported_values: {tent_pad: '1'}};
    const catalog = {
        sites: [site],
        site_field_keys: ['tent_pad', 'tent_pad_width_feet', 'independent_site_question'],
        place_field_keys: ['generator_restrictions'],
    };
    const context = {place: {place_id: 17}, sites: {[site.id]: site}};
    const admin = new JSDOM(
        '<form id="place-report" class="place-report-form">'
        + '<script type="application/json" data-place-report-schema-config>' + JSON.stringify(schema) + '</script>'
        + '<script type="application/json" data-admin-place-campsites>' + JSON.stringify(catalog) + '</script>'
        + '<script type="application/json" data-admin-place-context>' + JSON.stringify(context) + '</script>'
        + '<details class="contribution-section" open>'
        + fieldHtml('site_number') + keys.map(fieldHtml).join('')
        + '</details><button type="button" data-place-report-admin-save>Save Place Report</button></form>',
        {url: 'https://admin.llamascout.com/place.php?id=17', runScripts: 'outside-only'}
    );
    const w = admin.window;
    try {
        w.CSS = {escape: window.CSS.escape};
        w.LlamaPlaceReportForm = {setupRecovery() {}};
        let saved;
        w.fetch = async (_url, options) => {
            saved = options.body;
            return {ok: true, text: async () => '{"success":true}'};
        };
        w.eval(source);
        for (const script of ['place-report-save.js', 'place-context.js']) {
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
        assert.equal(question('site_number').hidden, false, 'An existing Site stays selectable on a legacy Place type');
        assert.equal(question('independent_site_question').hidden, true, 'Site-only questions wait for a target');
        assert.equal(input('independent_site_question').disabled, false, 'Questions are hidden without being locked');

        const bulk = get('[data-admin-bulk-site-toggle]');
        bulk.checked = true;
        bulk.dispatchEvent(new w.Event('change', {bubbles: true}));
        assert.equal(question('independent_site_question').hidden, false, 'Bulk editing reveals Site questions');

        update(picker, site.id);
        await Promise.resolve();
        assert.equal(question('independent_site_question').hidden, false, 'A selected Site reveals its own questions');
        assert.equal(input('tent_pad').disabled, false, 'Imported Site answers are editable');
        assert.equal(input('generator_restrictions').disabled, false, 'Place answers stay editable with a Site selected');
        assert.equal(question('tent_pad_width_feet').hidden, false, 'Imported parent answer reveals its child');
        update(input('tent_pad'), '0');
        assert.equal(question('tent_pad_width_feet').hidden, true, 'Parent change still hides its child');
        update(input('generator_restrictions'), '1');
        get('[data-place-report-admin-save]').click();
        assert.equal(saved.get('selected_campsite_id'), site.id);
        assert.deepEqual(JSON.parse(saved.get('admin_site_answers_json')), {tent_pad: '0'});
        assert.equal(saved.get('generator_restrictions'), '1', 'Place answer remains in the Place payload');
        await new Promise(resolve => setImmediate(resolve));
        update(picker, '');
        assert.equal(question('independent_site_question').hidden, true, 'Clearing the Site hides its questions again');
    } finally {
        w.close();
    }
}

testAdminSiteEditing().then(() => {
    console.log('PASS: parent-child visibility, editable Admin Site and Place questions, and scoped save payloads');
}).catch(error => {
    console.error(error);
    process.exitCode = 1;
});
