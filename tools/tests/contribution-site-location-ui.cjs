'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'js/add-site-location.js'), 'utf8');
const html = [
    '<!doctype html><html><body><form id="new-place">',
    '<details data-site-location-section><summary>Location</summary>',
    '<input name="site_coordinates" data-site-coordinate-input>',
    '<button type="button" data-site-locate>Locate me</button>',
    '<button type="button" data-site-coordinate-search>Search</button>',
    '<div data-site-location-status>At the Site?</div></details>',
    '<input name="latitude" data-location-field="latitude" value="37.10000">',
    '<input name="longitude" data-location-field="longitude" value="-107.10000">',
    '</form></body></html>',
].join('');
const dom = new JSDOM(html, {url: 'https://llamascout.com', runScripts: 'outside-only'});
const {window} = dom;
window.fetch = async () => ({
    ok: true,
    json: async () => ({success: true, location: {city: 'Durango', state: 'CO'}}),
});
window.eval(script);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

const siteInput = window.document.querySelector('[data-site-coordinate-input]');
const placeLat = window.document.querySelector('[name="latitude"]');
const placeLng = window.document.querySelector('[name="longitude"]');
const search = window.document.querySelector('[data-site-coordinate-search]');
const status = window.document.querySelector('[data-site-location-status]');

const flush = () => new Promise(resolve => setTimeout(resolve, 0));
(async () => {
    siteInput.value = '37.25222, -107.2192';
    search.click();
    await flush();
    await flush();

    assert.equal(siteInput.value, '37.2522200, -107.2192000');
    assert.equal(placeLat.value, '37.10000');
    assert.equal(placeLng.value, '-107.10000');
    assert.match(status.textContent, /Site coordinates confirmed/);

    siteInput.value = '999, -107';
    search.click();
    assert.match(siteInput.validationMessage, /Site latitude and longitude/);
    assert.equal(placeLat.value, '37.10000');
    assert.equal(placeLng.value, '-107.10000');

    console.log('PASS: Site Location validates and searches independently without touching Place coordinates.');
})().catch(error => { console.error(error); process.exitCode = 1; });
