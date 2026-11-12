'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const tabs = read('partials/place-report/contribution-tabs.php');
const standalone = read('place-map-editor.php');
const selector = read('admin/partials/place-report-area-selector.php');
const area = read('js/admin/place-report-area.js');
const mode = read('js/contribution-tabs.js');
const shared = read('app/admin-place-areas.php');
const css = read('css/site/pages/place-map-editor.css');

for (const [file, content, pattern] of [
    ['report tabs', tabs, /data-contribution-mode="place"[\s\S]*data-contribution-mode="site"[\s\S]*data-contribution-mode="area"/],
    ['map editor tabs', standalone, /data-map-mode-button="place"[\s\S]*data-map-mode-button="site"[\s\S]*data-map-mode-button="area"/],
]) {
    assert.match(content, pattern, `${file} must use Place, Site, Area order`);
}
assert.doesNotMatch(selector, /Entire Place/, 'An Area must never mean Entire Place');
assert.match(selector, /data-admin-area-picker[\s\S]*hidden/, 'Area selector is Area-only');
assert.match(area, /selector\.value = String\(areas\[0\]\.id\)/, 'Select a real Area by default');
assert.match(mode, /adminAreaSection\.hidden = mode !== 'area'/, 'Hide selector outside Area mode');
assert.match(area, /if \(!currentMode\(\)\)/, 'Do not submit Area edits from other tabs');
assert.match(area, /write\(placeValues\)/, 'Restore Place answers when switching tabs');
assert.match(shared, /llama_report_questions_for_area_target/, 'Canonical Area questions come from central registry');
assert.match(css, /data-map-mode-button="site"\]\.is-active/, 'Site active mode uses independent styling');
assert.match(css, /data-map-mode-button="area"\]\.is-active/, 'Area active mode uses independent styling');
console.log('PASS: Place/Site/Area tab order, Area ownership and canonical registry wiring');
