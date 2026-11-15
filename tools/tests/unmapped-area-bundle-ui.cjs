'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = pathName => fs.readFileSync(path.join(root,pathName), 'utf8');
const js = read('js/add-area-form.js');
const addPlace = read('add-place.php');
const runtime = read('app/place-report/runtime.php');
const moderation = read('app/moderation.php');
const review = read('admin/moderate-submission.php');

assert.match(addPlace, /id="add-place-main-form"/);
assert.match(addPlace, /name="area_bundle_json"/);
assert.match(runtime, /\$data\['_first_area'\] = llama_area_submission_prepare_bundle/);
assert.match(moderation, /llama_area_publish_unmapped\(/);
assert.match(review, /First Area included with this new Place/,
    'Moderator must see the Area being published with a new Place');

const dom = new JSDOM(`<!doctype html><body>
<input type="checkbox" data-area-bundle-new>
<p data-area-bundle-status hidden></p>
<div data-area-search-existing></div>
<form id="add-place-main-form"><input type="hidden" data-area-bundle-input
    name="area_bundle_json" value=""></form>
<form data-area-create-form>
  <label data-area-existing-parent><select required name="area_place_id">
    <option value="">Select Place</option><option value="1">Place 1</option>
  </select></label>
  <input name="area_name" required value="West Vault Toilets">
  <select name="area_feature_type" data-area-type>
    <option value="facility_area" selected>Facility</option>
    <option value="camping_area">Camping</option>
  </select>
  <label data-area-facility-selector>
    <select name="area_facility_kind" data-area-facility-kind>
      <option value="restroom" selected>Restroom</option>
    </select>
  </label>
  <details class="area-question-group"><div>
    <fieldset data-area-question data-area-types="" data-facility-kinds="restroom">
      <input name="area_answers[facility_toilet_count]" value="2">
    </fieldset>
    <fieldset data-area-question data-area-types="camping_area" data-facility-kinds="">
      <input name="area_answers[area_site_count]" value="8">
    </fieldset>
  </div></details>
  <button type="button" data-area-attach-new>Add to new Place</button>
  <button type="submit" form="add-place-main-form" data-area-submit-new hidden>Submit both</button>
  <button type="submit" data-area-submit-existing>Submit existing</button>
</form>
</body>`, {runScripts:'outside-only'});
const w = dom.window;
w.eval(js);
w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
const q = sel => w.document.querySelector(sel);
const toggle = q('[data-area-bundle-new]');
toggle.checked = true;
toggle.dispatchEvent(new w.Event('change'));
assert.equal(q('[data-area-existing-parent]').hidden,true);
assert.equal(q('[name="area_place_id"]').required,false);
assert.equal(q('[data-area-submit-existing]').hidden,true);
q('[data-area-attach-new]').click();
let bundle = JSON.parse(q('[data-area-bundle-input]').value);
assert.equal(bundle.area_name,'West Vault Toilets');
assert.equal(bundle.area_feature_type,'facility_area');
assert.equal(bundle.area_facility_kind,'restroom');
assert.equal(bundle.area_answers.facility_toilet_count,'2');
assert.equal(bundle.area_answers.area_site_count,undefined,
    'The wrong Area type must not leak answers into the new Place bundle');
assert.equal(q('[data-area-submit-new]').hidden,false);

q('[data-area-type]').value='camping_area';
q('[data-area-type]').dispatchEvent(new w.Event('change'));
q('[data-area-attach-new]').click();
bundle = JSON.parse(q('[data-area-bundle-input]').value);
assert.equal(bundle.area_feature_type,'camping_area');
assert.equal(bundle.area_facility_kind,'');
assert.equal(bundle.area_answers.area_site_count,'8');
assert.equal(bundle.area_answers.facility_toilet_count,undefined,
    'Facility questions cannot enter a camping Area report');
const submit = new w.Event('submit', {cancelable:true});
q('#add-place-main-form').dispatchEvent(submit);
assert.equal(submit.defaultPrevented,false);

toggle.checked=false;
toggle.dispatchEvent(new w.Event('change'));
assert.equal(q('[data-area-bundle-input]').value,'',
    'Switching back to an existing Place must not attach a stale Area to a new Place');
assert.equal(q('[name="area_place_id"]').required,true);
assert.equal(q('[data-area-submit-existing]').hidden,false);
dom.window.close();
console.log('PASS: First Area staged with new Place, no geometry, parent not required, type-filtered answers and safe mode switch');
