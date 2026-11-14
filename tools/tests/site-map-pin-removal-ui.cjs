'use strict';

// Regression for *permanent* Site deletion; the old pin-only control was
// insufficient because the Site record remained in place_campsites.
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
const deletion = read('app/place-site-delete.php');
const migration = read('sql/2026-11-14-permanent-site-deletion.sql');

for (const selector of ['data-delete-site', 'data-discard-site-point',
                        'data-save-site-point', 'data-delete-feature']) {
    assert.ok(page.includes(selector), 'Missing Site/map control: ' + selector);
}
assert.match(page, /data-delete-site hidden>Delete Site permanently/,
    'Site editor must offer a real Delete Site button');
assert.doesNotMatch(page, /data-remove-saved-site-point/,
    'The misleading pin-only removal button must be retired');

// Shared inline-flex style previously overrode hidden attributes and made
// unrelated controls appear. Verify actual computed style.
const dom = new JSDOM('<!doctype html><style>' + css + '</style>'
    + '<div class="mapped-area-editor">'
    + '<button type="button" data-discard-site-point hidden>Discard</button>'
    + '<button type="button" data-delete-site hidden>Delete Site</button>'
    + '</div>');
for (const button of dom.window.document.querySelectorAll('.mapped-area-editor button')) {
    assert.equal(dom.window.getComputedStyle(button).display, 'none');
    button.hidden = false;
    assert.notEqual(dom.window.getComputedStyle(button).display, 'none');
}
dom.window.close();

assert.match(editor, /const syncDeleteSiteButton = \(\) => \{/);
const visibility = editor.match(/const syncDeleteSiteButton = \(\) => \{([\s\S]*?)\n    \};/)?.[1];
assert.ok(visibility, 'Site delete visibility handler must exist');
assert.match(visibility, /currentSite\(\) !== null/,
    'Site delete must work even when the selected Site has no GPS coordinates');
assert.doesNotMatch(visibility, /siteHasSavedPoint/,
    'Deleting an entire Site cannot depend on it having a map pin');
assert.match(editor, /action: 'delete_site'/);
assert.match(editor, /confirmation: required/);
assert.match(editor, /DELETE SITE \$\{id\}/,
    'User must confirm the exact Site ID before irreversibly deleting it');
assert.match(editor, /syncSiteOptions\('new'\)/,
    'After deletion the Site picker must be reset without selecting a deleted record');

assert.match(endpoint, /if \(\$action === 'delete_site'\)/);
assert.match(endpoint, /if \(!\$canDeleteSite\)/,
    'Only Admins and Owner may delete Sites');
assert.match(endpoint, /\$confirmation !== 'DELETE SITE ' \. \$siteId/,
    'Server must verify confirmation and target Site');
assert.match(endpoint, /llama_site_delete_permanently\(\$db, \$placeId, \$siteId, \$userId\)/);

assert.match(deletion, /DELETE FROM place_campsites WHERE id = \? AND place_id = \?/,
    'Actual Site must be physically deleted, not have its pin cleared or is_active toggled');
assert.match(deletion, /DELETE FROM scout_reports WHERE id = \? AND place_id = \?/,
    'Own Site history must be erased through scoped deletion');
assert.match(deletion, /DELETE FROM place_pricing_reviews WHERE site_id = \? AND place_id = \?/);
assert.match(deletion, /DELETE FROM place_campsite_images|\$fields\['campsite_id'\]/,
    'Site galleries and other Site-owned records must be purged');
assert.match(deletion, /llama_photo_delete_owned_permanent_path/,
    'Unshared local Site image files must be cleaned up');
assert.match(deletion, /llama_photo_gallery_path_in_use/,
    'Shared Place/Site image paths must be protected');
assert.match(deletion, /\$db->beginTransaction\(\)/);
assert.match(deletion, /\$db->rollBack\(\)/);
assert.match(deletion, /\$db->commit\(\)/);
assert.match(deletion, /SET @llama_site_delete_report_id = NULL/,
    'History deletion session scope must always be reset');

for (const table of ['scout_reports', 'scout_report_answers',
                     'scout_report_photos', 'scout_report_moderation_events']) {
    assert.ok(migration.includes('BEFORE DELETE ON ' + table),
        'Missing scoped history deletion migration for ' + table);
}
assert.match(migration, /SIGNAL SQLSTATE '45000'/,
    'Ordinary immutable history deletes must still be rejected');

console.log('PASS: permanent Site deletion UI, per-Site authorization, confirmation, owned record cleanup, and history guards');
