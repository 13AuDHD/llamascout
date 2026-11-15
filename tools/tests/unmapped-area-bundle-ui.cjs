'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');

const addPlace = read('add-place.php');
const facilityPage = read('resource-contribution.php');
const formPartial = read('partials/resources/fields.php');
const service = read('app/resource-submissions.php');
const saver = read('app/admin-resources.php');
const linked = read('partials/place/facilities.php');
const migration = read('tools/migrations/20261115_facility_parent_place.sql');

assert.match(addPlace, /id="add-place-main-form"/);
assert.doesNotMatch(addPlace, /name="area_bundle_json"/,
    'A new Place must no longer submit a Facility as part of its Place form');
assert.doesNotMatch(addPlace, /require __DIR__ \. '\/partials\/place-report\/add-area-form\.php'/,
    'Facility no longer uses the embedded Area submission form');
assert.match(addPlace, /resource-contribution\.php\?/,
    'Old Facility links should be routed to the standalone form');
assert.match(facilityPage, /llama_resource_submit\(/,
    'Each Facility should submit through its own moderated contribution');
assert.match(formPartial, /data-facility-parent-query/);
assert.match(formPartial, /name="parent_place_id"/);
assert.match(formPartial, /if \(!\$facilityForm\)/,
    'Facility must not ask for a duplicate postal address');
assert.match(service, /'parent_place_id'/,
    'Each submission must save its Parent Place selection');
assert.match(saver, /SELECT id FROM places WHERE id = \?/,
    'Parent Place must be validated by the database, not browser text');
assert.match(linked, /llama_place_facilities\(/,
    'Published Facilities should be displayed on their Parent Place');
assert.match(migration, /ADD COLUMN parent_place_id/);

const script = read('js/facility-parent-search.js');
const dom = new JSDOM(`<!doctype html><body>
    <form>
      <div data-facility-parent-picker data-search-url="/api/facility-parent-places.php">
        <input type="search" data-facility-parent-query>
        <input type="hidden" name="parent_place_id" data-facility-parent-id value="">
        <div data-facility-parent-results hidden></div>
        <p data-facility-parent-selected hidden><strong></strong></p>
      </div>
      <button type="submit">Submit Facility</button>
    </form>
</body>`, { url: 'https://llamascout.com/resource-contribution.php?kind=facility', runScripts: 'outside-only' });
const window = dom.window;
const requests = [];
window.fetch = async url => {
    requests.push(String(url));
    return {
        ok: true,
        json: async () => ({ ok: true, results: [
            { id: 13, name: 'Cedar Canyon', location: 'Durango, CO' },
            { id: 27, name: 'Cedar Valley', location: 'Silverton, CO' }
        ] })
    };
};
window.eval(script);
window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
const q = selector => window.document.querySelector(selector);
const search = q('[data-facility-parent-query]');
const id = q('[data-facility-parent-id]');
const form = q('form');
const results = q('[data-facility-parent-results]');
const tick = ms => new Promise(resolve => setTimeout(resolve, ms));

(async () => {
    search.value = 'cedar';
    search.dispatchEvent(new window.Event('input', { bubbles: true }));
    await tick(290);
    assert.equal(requests.length, 1);
    assert.ok(requests[0].includes('q=cedar'));
    assert.equal(results.querySelectorAll('button').length, 2);
    results.querySelector('button').click();
    assert.equal(id.value, '13', 'Selected Parent Place ID must be persisted');
    assert.equal(search.value, 'Cedar Canyon');
    assert.equal(q('[data-facility-parent-selected] strong').textContent, 'Cedar Canyon');
    search.value = 'different place';
    search.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.equal(id.value, '', 'Changing query must clear previously selected Place');
    const submission = new window.Event('submit', { cancelable: true, bubbles: true });
    form.dispatchEvent(submission);
    assert.equal(submission.defaultPrevented, true,
        'A Facility cannot submit without selecting a Parent Place');
    window.close();
    console.log('PASS: Facilities submit separately with database-owned Parent Places and live search selection');
})().catch(err => { console.error(err); process.exitCode = 1; });
