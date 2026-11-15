'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const js = fs.readFileSync(path.join(root, 'js/admin/resources.js'), 'utf8');
const html = [
  '<!doctype html><html><body><form data-resource-editor>',
  '<div data-resource-services>',
  '<div data-resource-service-picker><select><option value="">Choose</option>',
  '<option value="shower">Shower</option><option value="fuel">Fuel</option></select>',
  '<button type="button" data-resource-service-add>Add service</button></div>',
  '<p data-resource-services-empty>No services yet</p>',
  '<div data-resource-service-cards hidden></div>',
  '<label data-resource-primary-service hidden>',
  '<select name="marker_service_type"><option value="">Auto</option></select></label>',
  '<template data-resource-service-template>',
  '<article data-resource-service-card="__type__">',
  '<strong data-resource-service-title></strong>',
  '<button type="button" data-resource-service-remove>Remove</button>',
  '<select name="services[__type__][status]"><option value="unknown">Unknown</option></select>',
  '</article></template></div>',
  '<div data-resource-experience>',
  '<p data-resource-experience-empty>No experiences</p>',
  '<fieldset data-resource-experience-service="shower" hidden disabled>',
  '<input name="experience[shower][general][facility_hot_water]"></fieldset>',
  '<fieldset data-resource-experience-service="fuel" hidden disabled>',
  '<input name="experience[fuel][general][service_fuel_pumps]"></fieldset>',
  '</div></form></body></html>',
].join('');
const dom = new JSDOM(html, { runScripts: 'outside-only', url: 'https://llamascout.com' });
dom.window.eval(js);
const document = dom.window.document;
const picker = document.querySelector('[data-resource-service-picker] select');
const add = document.querySelector('[data-resource-service-add]');
const shower = document.querySelector('[data-resource-experience-service="shower"]');
const fuel = document.querySelector('[data-resource-experience-service="fuel"]');
assert.equal(shower.disabled, true);
assert.equal(fuel.disabled, true);
assert.equal(document.querySelector('[data-resource-experience-empty]').hidden, false);

picker.value = 'shower';
picker.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
add.click();
assert.equal(shower.hidden, false);
assert.equal(shower.disabled, false);
assert.equal(fuel.hidden, true);

picker.value = 'fuel';
picker.dispatchEvent(new dom.window.Event('change', { bubbles: true }));
add.click();
assert.equal(fuel.hidden, false);
assert.equal(fuel.disabled, false);
assert.equal(document.querySelector('[data-resource-experience-empty]').hidden, true);

const showerCard = document.querySelector('[data-resource-service-card="shower"]');
showerCard.querySelector('[data-resource-service-remove]').click();
assert.equal(shower.hidden, true);
assert.equal(shower.disabled, true);
assert.equal(fuel.hidden, false);
assert.equal(fuel.disabled, false);

const files = [
  'partials/resources/fields.php', 'partials/resources/experience.php',
  'partials/resources/contribution.php', 'partials/resources/contributor-photos.php',
  'partials/resources/photo-uploader.php', 'css/site/features/resource-form.css',
  'css/resource-detail.css', 'tools/migrations/20261115_resource_facility_experience.sql',
];
const source = Object.fromEntries(files.map(file => [
  file, fs.readFileSync(path.join(root, file), 'utf8'),
]));
assert.match(source['partials/resources/fields.php'], /require __DIR__ \. '\/experience\.php'/);
assert.match(source['partials/resources/experience.php'], /llama_resource_experience_manifest/);
assert.match(source['partials/resources/contributor-photos.php'], /facilityLabel.*Photos/);
assert.match(source['partials/resources/photo-uploader.php'], /facilityLabel.*Photos/);
assert.match(source['css/site/features/resource-form.css'], /\.resource-facility-theme\s*\{/);
assert.match(source['css/resource-detail.css'], /\.resource-page\.is-facility/);
assert.match(source['tools/migrations/20261115_resource_facility_experience.sql'], /experience_json JSON/);

console.log('PASS: Selected Services control Experience visibility, Facility purple labels and photos.');
