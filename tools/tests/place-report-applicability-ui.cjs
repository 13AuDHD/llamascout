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
        {field: 'site_hookups_available', operator: 'equals', value: '1'},
    ],
    hookup_electric_service: [
        {field: 'site_hookups_available', operator: 'equals', value: '1'},
        {field: 'hookup_electric', operator: 'equals', value: '1'},
    ],
    tent_pad_width_feet: [
        {field: 'tent_pad', operator: 'equals', value: '1'},
    ],
};
const keys = [
    'type', 'site_hookups_available', 'hookup_electric',
    'hookup_electric_service', 'tent_pad', 'tent_pad_width_feet',
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

assert.equal(field('hookup_electric').hidden, true,
    'Unanswered hookup parent should hide the electric child');
assert.equal(field('hookup_electric_service').hidden, true,
    'Unanswered hookup grandparent should hide the electric-service grandchild');
assert.equal(field('tent_pad_width_feet').hidden, true,
    'Tent Pad Width must remain hidden without a Tent Pad Yes');
assert.equal(field('independent_site_question').hidden, false,
    'Independent Site question should remain visible');

change('type', '1');
// This fixture uses the canonical Place type slug, not a Yes/No surrogate.
{
    const typeControl = select('type');
    const opt = window.document.createElement('option');
    opt.value = 'developed-campground';
    opt.textContent = 'Developed Campground';
    typeControl.append(opt);
}
change('type', 'developed-campground');
assert.equal(field('site_hookups_available').hidden, false,
    'Campground should show its hookup parent question');
change('site_hookups_available', '1');
assert.equal(field('hookup_electric').hidden, false,
    'Hookup Yes should reveal Electric');
assert.equal(field('hookup_electric_service').hidden, true,
    'Electric Service remains hidden until Electric itself is Yes');
change('hookup_electric', '1');
assert.equal(field('hookup_electric_service').hidden, false,
    'Nested electric-service question must reveal when both parents are Yes');

change('site_hookups_available', '0');
assert.equal(field('hookup_electric').hidden, true,
    'Hookup No must hide electric detail');
assert.equal(field('hookup_electric_service').hidden, true,
    'A stale Electric Yes must NOT reveal grandchild after Hookup No');
assert.equal(select('hookup_electric').value, '1',
    'Hide must preserve historical/draft answer rather than deleting it');

change('site_hookups_available', '__LLAMA_UNKNOWN__');
assert.equal(field('hookup_electric').hidden, true,
    'Unknown parent must not activate a Yes-only child');

change('tent_pad', '1');
assert.equal(field('tent_pad_width_feet').hidden, false,
    'Tent Pad Yes must reveal dimensions');
change('tent_pad', '0');
assert.equal(field('tent_pad_width_feet').hidden, true,
    'Tent Pad No must hide dimensions again');

change('type', '0');
assert.equal(field('site_hookups_available').hidden, true,
    'Non-campground Place type hides campground hookup parent');
assert.equal(field('hookup_electric').hidden, true,
    'Type-inapplicable hookup parent cannot reveal electric details');

console.log('PASS: parent-child fields hide/reveal, nested stale values never activate, independent questions remain visible, saved answers preserved');
