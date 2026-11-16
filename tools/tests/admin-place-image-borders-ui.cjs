'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(
    path.join(root, 'js/admin/place-image-borders.js'), 'utf8'
);
const html = [
    '<!doctype html><html><head></head><body>',
    '<div class="admin-place-list">',
    '<div class="admin-place-list-image is-closed">',
    '<img alt=""><span class="admin-place-image-badge">Closed</span></div>',
    '<div class="admin-place-list-image is-featured">',
    '<img alt=""><span class="admin-place-image-badge">Featured</span></div>',
    '<div class="admin-place-list-image is-paid">',
    '<img alt=""><span class="admin-place-image-badge">Paid</span></div>',
    '<div class="admin-place-list-image is-partner"',
    ' data-partner-id="7" data-partner-color="#A0B1C2">',
    '<img alt=""><span class="admin-place-image-badge">Affiliate</span></div>',
    '<div class="admin-place-list-image is-partner"',
    ' data-partner-id="8" data-partner-color="red;position:absolute">',
    '<img alt=""><span class="admin-place-image-badge">Bad value</span></div>',
    '<div class="admin-place-list-image"><img alt=""></div>',
    '</div></body></html>',
].join('');

const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/places.php',
    runScripts: 'outside-only',
});
const {window} = dom;
window.eval(script);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
const style = window.document.getElementById('llama-admin-place-partner-colors');
assert.ok(style, 'Partner stylesheet must be installed for approved branding.');
const rules = [...style.sheet.cssRules].map(rule => rule.cssText);
assert.equal(rules.length, 1, 'Only valid partner colors should create CSS rules.');
assert.match(rules[0], /data-partner-id="7"/);
assert.match(rules[0], /#A0B1C2/i);
assert.ok(!rules[0].includes('position:'), 'Invalid partner CSS must not be inserted.');
assert.equal(
    window.document.querySelectorAll('.admin-place-list-image.is-closed').length, 1
);
assert.equal(
    window.document.querySelectorAll('.admin-place-list-image.is-paid').length, 1
);
assert.equal(
    window.document.querySelectorAll('.admin-place-list-image:not([class*=" is-"])').length, 1
);
dom.window.close();
console.log('PASS: Admin map-color thumbnails and safe custom partner card accents.');
