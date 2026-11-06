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
        {field: 'hookup_electric', operator: 'equals', value: '1'},
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
    'General Hookups No does not hide independently verified Electric');
change('hookup_electric', '1');
assert.equal(field('hookup_electric_service').hidden, false,
    'Electric Yes reveals service details');
change('hookup_electric', '0');
assert.equal(field('hookup_electric_service').hidden, true,
    'Electric No hides service details');

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

console.log('PASS: parent-child fields hide/reveal, nested stale values never activate, independent questions remain visible, saved answers preserved');
