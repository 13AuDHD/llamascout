'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const tabs = fs.readFileSync(path.join(root, 'js/contribution-tabs.js'), 'utf8');
const legacyAreas = fs.readFileSync(path.join(root, 'js/admin/place-report-area.js'), 'utf8');

const markup = [
    '<!doctype html><html><body>',
    '<div data-contribution-tabs data-contribution-split',
    ' data-admin-facility-source="resource" data-contribution-initial="facility">',
    '<nav data-contribution-picker hidden>',
    '<button type="button" data-contribution-mode="place">Place</button>',
    '<button type="button" data-contribution-mode="site">Site</button>',
    '<button type="button" data-contribution-mode="facility">Facility</button>',
    '</nav>',
    '<section data-admin-area-picker hidden>',
    '<a href="/place.php?id=1&mode=facility&facility=new" data-admin-add-facility>Add Facility</a>',
    '<select data-admin-area-selector data-admin-facility-selector>',
    '<option value="new" selected>+ New Facility</option>',
    '</select>',
    '<input name="selected_area_id" form="place-report" value="">',
    '<script type="application/json" data-admin-place-areas>',
    '{"areas":[],"field_keys":[]}</script>',
    '</section>',
    '<form id="place-report" data-contribution-report>',
    '<select data-admin-campsite-selector><option value="">Site</option></select>',
    '<details data-report-main-section="place"></details>',
    '<details data-report-main-section="site"></details>',
    '</form>',
    '<section data-admin-resource-facility-editor hidden>',
    '<form data-resource-editor>',
    '<input name="parent_place_id" value="1">',
    '<input name="facility_resource_id" value="0">',
    '<input name="selected_facility_id" value="new">',
    '<input name="name" required>',
    '<input name="coordinates" required>',
    '<button type="submit">Create Facility</button>',
    '</form>',
    '</section>',
    '</div></body></html>',
].join('');

const dom = new JSDOM(markup, {
    url: 'https://admin.llamascout.com/place.php?id=1&mode=facility&facility=new',
    runScripts: 'outside-only'
});
const {window} = dom;
window.eval(legacyAreas);
window.eval(tabs);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
const document = window.document;
const picker = document.querySelector('[data-contribution-picker]');
const editor = document.querySelector('[data-admin-resource-facility-editor]');
const report = document.querySelector('[data-contribution-report]');
const facilityTab = document.querySelector('[data-contribution-mode="facility"]');
const facilityPicker = document.querySelector('[data-admin-facility-selector]');

assert.equal(picker.hidden, false);
assert.equal(facilityTab.getAttribute('aria-pressed'), 'true');
assert.equal(facilityPicker.value, 'new');
assert.equal(editor.hidden, false, 'New Facility editor must show without mapped Areas.');
assert.equal(report.hidden, true, 'Place Report cannot overlap the new Facility form.');
assert.equal(document.querySelector('[data-admin-area-picker]').hidden, false);
assert.ok(document.querySelector('[data-admin-add-facility]'));
assert.equal(document.querySelectorAll('[data-contribution-mode]').length, 3);
assert.equal(document.querySelector('[name="parent_place_id"]').value, '1');
assert.equal(document.querySelector('[name="facility_resource_id"]').value, '0');

document.querySelector('[data-contribution-mode="site"]').click();
assert.equal(editor.hidden, true);
assert.equal(report.hidden, false);
document.querySelector('[data-contribution-mode="facility"]').click();
assert.equal(editor.hidden, false);
assert.equal(document.querySelector('[name="selected_facility_id"]').value, 'new');

dom.window.close();
console.log('PASS: A Place without Areas opens direct Add Facility with coordinates and 3 working tabs.');
