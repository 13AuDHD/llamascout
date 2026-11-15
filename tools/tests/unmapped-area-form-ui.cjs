'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const php = read('add-place.php');
const areaForm = read('partials/place-report/add-area-form.php');
const formJs = read('js/add-area-form.js');
const mapJs = read('js/place-map-editor.js');
const features = read('app/place-map-features.php');
const ownership = read('app/place-report/scoped-answers.php');
const service = read('app/area-submissions.php');

assert.match(php, /submit_area/);
assert.match(php, /!isset\(\$_POST\['submit_area'\]\)/,
    'Area submissions must never fall through into new Place submission');
assert.match(php, /partials\/place-report\/add-area-form\.php/,
    'Add Place Area tab must display working Area creation form');
assert.doesNotMatch(areaForm, /required.*(?:latitude|longitude|geometry|marker)/i,
    'Map coordinates must not be required in the Area form');
assert.match(areaForm, /name="area_name"/);
assert.match(areaForm, /name="area_place_id"/);
assert.match(areaForm, /name="area_feature_type"/);
assert.match(areaForm, /llama_report_questions_for_scope\('area', true\)/);
assert.match(areaForm, /data-area-basic-section/);
assert.match(areaForm, /data-area-location-section/);
assert.match(areaForm, /data-area-coordinate-input/);
assert.match(areaForm, /data-area-locate/);
assert.match(areaForm, /data-area-report-section=/);
assert.match(areaForm, /data-area-question-group/);
assert.doesNotMatch(areaForm, /class="contribution-section area-question-group" open/,
    'Do not open every individual Area question group by default');
assert.match(service, /llama_report_questions_for_area_target\(/,
    'Area type applicability must come from canonical question catalog');
assert.match(service, /ST_GeomFromText\(\?, 4326\)/,
    'Approved Area saves validated GPS as geometry when given');
assert.match(service, /\$wkt === null \? 'unmapped' : 'point'/,
    'Without coordinates an Area remains unmapped');
assert.match(features, /if \(!is_array\(\$geometry\) && !\$unmappedArea\)/,
    'Unmapped Areas must survive canonical feature listing');
assert.match(mapJs, /if \(!feature\.geometry\)\s*\{\s*return;/,
    'Unmapped Area must not draw fake map point');
assert.match(ownership, /\['camping_area','parking_area','facility_area'\]/,
    'Facility Areas need the same canonical answer ownership as other Areas');

const dom = new JSDOM(`<!doctype html><body>
<form data-area-create-form>
    <input data-area-coordinate-input name="area_coordinates" value="">
    <button type="button" data-area-search-coordinates>Search</button>
    <p data-area-location-status>Optional coordinates</p>
    <select data-area-type><option value="camping_area">Camping</option>
      <option value="facility_area" selected>Facility</option></select>
    <label data-area-facility-selector><select data-area-facility-kind>
      <option value="restroom" selected>Restroom</option>
      <option value="pool">Pool</option>
    </select></label>
    <details data-area-report-section="amenities"><div>
    <details data-area-question-group class="area-question-group"><div>
      <fieldset data-area-question data-area-types="" data-facility-kinds="restroom">
        <label><input type="radio" name="area_answers[facility_toilet_count]" value="2"></label>
        <button type="button" data-clear-area-answer>Clear</button>
      </fieldset>
      <fieldset data-area-question data-area-types="" data-facility-kinds="pool">
        <label><input type="radio" name="area_answers[facility_pool_heated]" value="1"></label>
      </fieldset>
    </div></details></div></details>
    <details data-area-report-section="site_vehicle"><div>
    <details data-area-question-group class="area-question-group"><div>
      <fieldset data-area-question data-area-types="camping_area" data-facility-kinds="">
        <input name="area_answers[area_site_count]" value="3">
      </fieldset>
    </div></details></div></details>
</form>
</body>`, {runScripts:'outside-only'});
const w = dom.window;
w.eval(formJs);
w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
const form = w.document.querySelector('form');
const question = index => form.querySelectorAll('[data-area-question]')[index];
const type = form.querySelector('[data-area-type]');
const kind = form.querySelector('[data-area-facility-kind]');
assert.equal(question(0).hidden, false);
assert.equal(question(1).hidden, true);
assert.equal(question(2).hidden, true);
assert.equal(question(1).disabled, true);
assert.equal(form.querySelector('[data-area-report-section="site_vehicle"]').hidden, true);
const toilet = question(0).querySelector('input');
toilet.checked = true;
question(0).querySelector('button').click();
assert.equal(toilet.checked, false, 'Clear must unanswer a question');
kind.value = 'pool';
kind.dispatchEvent(new w.Event('change'));
assert.equal(question(0).hidden, true);
assert.equal(question(1).hidden, false);
type.value = 'camping_area';
type.dispatchEvent(new w.Event('change'));
assert.equal(question(2).hidden, false);
assert.equal(form.querySelector('[data-area-report-section="site_vehicle"]').hidden, false);
assert.equal(question(0).disabled, true);
assert.equal(form.querySelector('[data-area-facility-selector]').hidden, true);
assert.equal(kind.required,false);
const coord = form.querySelector('[data-area-coordinate-input]');
const search = form.querySelector('[data-area-search-coordinates]');
assert.equal(coord.required, false, 'Coordinates must stay optional');
coord.value = '37.2522200, -107.2192000';
search.click();
assert.equal(coord.value, '37.2522200, -107.2192000');
assert.equal(form.querySelector('[data-area-location-status]').classList.contains('is-success'), true);
coord.value = '100.0000000, -107.2192000';
search.click();
assert.equal(form.querySelector('[data-area-location-status]').classList.contains('is-error'), true);
coord.value = '';
search.click();
assert.equal(coord.value, '', 'Unmapped Areas must remain valid');
dom.window.close();
console.log('PASS: Area Basic/Location/report accordions, type-restricted questions and optional validated GPS');
