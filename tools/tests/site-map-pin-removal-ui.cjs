'use strict';

// Regression for Site pin removal. The map Site is a canonical campsite
// record, so removing its GPS location must not delete the record itself.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const page = read('place-map-editor.php');
const css = read('css/site/pages/place-map-editor.css');
const editor = read('js/place-map-editor.js');
const endpoint = read('app/place-map-features-endpoint.php');

for (const selector of ['data-remove-saved-site-point', 'data-discard-site-point',
                        'data-save-site-point', 'data-delete-feature']) {
    assert.ok(page.includes(selector), 'Missing Site/map control: ' + selector);
}
assert.match(page, /data-remove-saved-site-point hidden/,
    'Saved pin removal must start hidden');
assert.match(page, /data-discard-site-point hidden/,
    'Unsaved preview discard must start hidden');

// The real CSS had display:inline-flex on every editor button, overriding the
// native hidden attribute. Verify computed style, not just presence of [hidden].
const dom = new JSDOM('<!doctype html><style>' + css + '</style>'
    + '<div class="mapped-area-editor">'
    + '<button type="button" data-discard-site-point hidden>Discard</button>'
    + '<button type="button" data-remove-saved-site-point hidden>Remove</button>'
    + '</div>');
const doc = dom.window.document;
for (const button of doc.querySelectorAll('.mapped-area-editor button')) {
    assert.equal(dom.window.getComputedStyle(button).display, 'none',
        'The hidden button must not be visible');
    button.hidden = false;
    assert.notEqual(dom.window.getComputedStyle(button).display, 'none',
        'Visible Site controls must appear when enabled');
}
dom.window.close();

assert.ok(editor.includes("action: 'clear_site_point'"),
    'Client must call the saved-point action, not the Area delete action');
assert.ok(editor.includes('site_id: Number(site.id)'),
    'Saved Site pin action must target selected canonical Site ID');
assert.ok(editor.includes('expected_latitude: Number(site.latitude)')
    && editor.includes('expected_longitude: Number(site.longitude)'),
    'Removal must provide previously loaded coordinates for stale-write checks');
assert.ok(editor.includes('renderExistingLayers();')
    && editor.includes('syncSiteOptions(Number(site.id))'),
    'Removal must refresh map markers and preserve selected Site');
assert.ok(editor.includes('canRemoveSavedSitePoint')
    && editor.includes('syncSavedSitePointButton'),
    'Saved pin removal must respect permission and selected Site');

assert.match(endpoint, /if \(\$action === 'clear_site_point'\)/,
    'Backend must handle saved Site pin removal separately');
assert.match(endpoint, /if \(!\$canRemoveSavedSitePoint\)/,
    'Clearing an existing Site location requires the elevated permission');
assert.match(endpoint, /SELECT latitude, longitude FROM place_campsites[\s\S]*?FOR UPDATE/,
    'Saved Site coordinates must be locked and checked');
assert.match(endpoint, /SET latitude = NULL, longitude = NULL/,
    'Removal must clear only the Site map coordinates');
assert.match(endpoint, /\$db->rollBack\(\)/,
    'A failed coordinate change must roll back');
assert.doesNotMatch(endpoint.match(/if \(\$action === 'clear_site_point'\)([\s\S]*?)if \(\$action === 'save_site_point'\)/)?.[1] || '',
    /DELETE FROM place_campsites|SET is_active = 0/,
    'Removing a Site pin must never delete or deactivate the Site record');

console.log('PASS: hidden map editor buttons, selected Site pin removal and coordinate-only server write');
