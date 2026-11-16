'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'js/admin/place-site-location.js'), 'utf8');
const sites = [
    {id: 'campsite_record:12', label: 'Site 12', latitude: 37.25, longitude: -107.22, map_feature_id: null},
    {id: 'campsite_record:13', label: 'Site 13', latitude: 37.28, longitude: -107.29, map_feature_id: 77},
];
const html = [
    '<!doctype html><html><body>',
    '<a data-admin-place-map-edit href="/place-map-editor.php?slug=test">Edit map</a>',
    '<form id="place-report">',
    '<input name="latitude" value="37.1111"><input name="longitude" value="-107.1111">',
    '<script type="application/json" data-admin-place-campsites>',
    JSON.stringify({sites}), '</script>',
    '<select data-admin-campsite-selector name="selected_campsite_id">',
    '<option value="">Choose Site</option>',
    '<option value="all-sites">All Sites</option>',
    '<option value="campsite_record:12">Site 12</option>',
    '<option value="campsite_record:13">Site 13</option></select>',
    '<details data-site-location-section>',
    '<input data-site-coordinate-input name="site_coordinates" disabled>',
    '<button type="button" data-site-locate disabled>Locate me</button>',
    '<button type="button" data-site-coordinate-search disabled>Search</button>',
    '<input type="hidden" name="admin_site_coordinates_changed"',
    ' data-admin-site-coordinates-changed value="0">',
    '<p data-admin-site-location-note></p>',
    '<div data-site-location-status></div></details></form></body></html>',
].join('');

const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/place.php?id=1',
    runScripts: 'outside-only'
});
const {window: w} = dom;
w.eval(script);
w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
const form = w.document.getElementById('place-report');
const selection = form.querySelector('[data-admin-campsite-selector]');
const input = form.querySelector('[data-site-coordinate-input]');
const touched = form.querySelector('[data-admin-site-coordinates-changed]');
const note = form.querySelector('[data-admin-site-location-note]');
const lat = form.querySelector('[name="latitude"]');
const lng = form.querySelector('[name="longitude"]');
let dirtyEvents = 0;
form.addEventListener('llama:admin-site-location-dirty', () => { dirtyEvents++; });
const select = id => {
    selection.value = id;
    form.dispatchEvent(new w.CustomEvent('llama:campsite-selected'));
};
assert.equal(input.disabled, true, 'No Site = no coordinate editor');
select('campsite_record:12');
assert.equal(input.disabled, false, 'Unmapped Site is editable');
assert.equal(input.value, '37.2500000, -107.2200000');
assert.equal(touched.value, '0');

input.value = '37.25222, -107.2192';
input.dispatchEvent(new w.Event('input', {bubbles: true}));
assert.equal(touched.value, '1');
assert.equal(dirtyEvents, 1);
const beforeSave = () => new w.CustomEvent('llama:admin-place-report-before-save', {cancelable: true});
let event = beforeSave();
form.dispatchEvent(event);
assert.equal(event.defaultPrevented, false, 'Valid Site coordinates must be savable');
form.dispatchEvent(new w.CustomEvent('llama:campsite-saved'));
assert.equal(touched.value, '0', 'Successful save clears the dirty marker');
assert.equal(lat.value, '37.1111', 'Place latitude must not change');
assert.equal(lng.value, '-107.1111', 'Place longitude must not change');

select('campsite_record:13');
assert.equal(input.disabled, true, 'Mapped Site must use Edit Map');
assert.match(note.textContent, /Edit Map/);
assert.ok(note.querySelector('a[href*="place-map-editor.php"]'));
select('all-sites');
assert.equal(input.disabled, true, 'No mass Site coordinate edits');
select('campsite_record:12');
assert.equal(input.value, '37.2522200, -107.2192000', 'Saved Site GPS restores from local state');
input.value = '999, -107';
input.dispatchEvent(new w.Event('input', {bubbles: true}));
input.reportValidity = () => false;
event = beforeSave();
form.dispatchEvent(event);
assert.equal(event.defaultPrevented, true, 'Invalid Site GPS must cancel a save');
assert.equal(lat.value, '37.1111');
assert.equal(lng.value, '-107.1111');

dom.window.close();
console.log('PASS: Admin Site GPS target changes, map pin safety, save validation and parent isolation.');
