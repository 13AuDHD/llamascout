'use strict';

// Protect Place answers from appearing to turn into Area questions.
// Use actual shared-report selection JS and the always-loaded public CSS.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const js = read('js/place-campsites.js');
const css = read('css/site/features/place-report-form.css');
const php = read('partials/place-report/read-only.php');

assert.match(php, /\$hiddenOnPlace\s*=/,
    'Server-side Place view must hide child-only questions before JS loads');
assert.match(php, /!in_array\('place', \(array\) \(\$field\['report_scopes'\]/);
assert.match(css, /data-active-report-scope="place"/,
    'All Place pages need a scope guard in the always-loaded stylesheet');
assert.match(css, /not\(\[data-report-scopes~="place"\]\)/,
    'Area-only and Site-only cards must not be able to appear in Place mode');

(async () => {
const dom = new JSDOM(`<!doctype html><body>
<style>${css}</style>
<section class="scout-report" data-question-inventory-review="1">
  <header>
    <div class="scout-report-completeness">Place 91 / 196</div>
    <a class="scout-report-history-link">Compare Reports</a>
  </header>
  <section data-area-report-context hidden>
    <h3 data-area-report-name></h3>
    <p data-area-report-status></p>
  </section>
  <section class="scout-report-section" data-report-page="place" data-report-scopes="place area" data-report-target-section="1" data-report-section-label="Amenities" data-report-fixed-applicable="2" data-report-fixed-missing="1">
    <h3>Amenities</h3>
    <div data-target-report-prompt hidden><strong></strong><p></p></div>
    <div class="scout-report-grid">
      <div class="scout-report-item" data-report-scopes="place"
           data-report-owner="place">
        <div class="scout-report-value-content"><strong>Toilets at campground: Yes</strong></div>
      </div>
      <div class="scout-report-item" data-report-scopes="place area"
           data-report-owner="place_area">
        <div class="scout-report-value-content"><strong>Shared campground rule</strong></div>
      </div>
      <div class="scout-report-item" data-report-scopes="area"
           data-report-owner="area" data-campsite-report-field="facility_toilet_count"
           hidden>
        <div class="scout-report-value-content"><strong>Not provided</strong></div>
      </div>
      <div class="scout-report-item" data-report-scopes="area"
           data-report-owner="area" data-campsite-report-field="facility_shower_stalls" hidden>
        <div class="scout-report-value-content"><strong>Not provided</strong></div>
      </div>
    </div>
  </section>
  <section class="scout-report-section" data-report-page="site" data-report-scopes="site">
    <div class="scout-report-grid">
      <div class="scout-report-item" data-report-scopes="site"
           data-report-owner="site" data-campsite-report-field="site_length_feet"
           hidden>
        <div class="scout-report-value-content"><strong>Not provided</strong></div>
      </div>
    </div>
  </section>
</section>
<section data-stay-options>
   <button data-stay-area-id="55" data-stay-area-name="West Vault Toilets"></button>
</section>
<div data-place-campsites-mount data-place-slug="target-tree-campground" data-complete-access="1"></div>
</body>`, {
    url: 'https://llamascout.com/place.php?slug=target-tree-campground',
    runScripts: 'outside-only',
});
const w = dom.window;
const requests = [];
w.fetch = request => {
    requests.push(String(request));
    if (String(request).includes('place-area-report.php')) {
        return Promise.resolve({
            ok: true,
            json: async () => ({ ok: true, area: {
                report_target_scope: 'area',
                report_effective_values: { facility_toilet_count: '3' },
                report_answer_sources: {},
                report_inapplicable_keys: ['facility_shower_stalls'],
            }}),
        });
    }
    return Promise.resolve({
        ok: true, json: async () => ({ok:true,sites:[],browser:{show_site_layer:false}}),
    });
};
const q = selector => w.document.querySelector(selector);
const place = q('[data-report-owner="place"]');
const shared = q('[data-report-owner="place_area"]');
const area = q('[data-report-owner="area"]');
const site = q('[data-report-owner="site"]');
const areaCtx = q('[data-area-report-context]');
const event = (kind,id='') => w.dispatchEvent(new w.CustomEvent(
    'llama:explorer-selection', {detail:{kind,id}}
));
const isShown = node => !node.hidden && w.getComputedStyle(node).display !== 'none';
w.eval(js);

// GREEN Place: original Place answers, including valid shared Place/Area
// questions, must display; Area facility questions must never show here.
assert.equal(q('.scout-report').dataset.activeReportScope, 'place');
assert.ok(isShown(place), 'Original Place amenities missing');
assert.ok(isShown(shared), 'Valid Place/Area shared questions missing');
assert.ok(!isShown(area), 'Area-only facility question leaked into Place');
assert.ok(!isShown(site), 'Site question leaked into Place');

// Simulate another component explicitly restoring an Area card's old hidden
// property. Scope CSS still prevents it from leaking into the green Place.
area.hidden = false;
assert.equal(w.getComputedStyle(area).display, 'none',
    'A separate renderer must not be able to re-show Area cards on Place');
area.hidden = true;

event('area','55');
assert.ok(!isShown(area), 'Do not show unverified Facility inventory while loading');
assert.equal(q('[data-target-report-prompt]').hidden,true,'Loading must not advertise missing answers');
await new Promise(resolve => setImmediate(resolve));
assert.ok(!isShown(place));
assert.ok(isShown(area), 'Selected Area question should be visible');
assert.equal(q('[data-area-report-name]').textContent, 'West Vault Toilets');
assert.equal(area.querySelector('strong').textContent, '3');
assert.ok(!isShown(q('[data-campsite-report-field="facility_shower_stalls"]')), 'Wrong-kind question stays hidden after response');
assert.equal(q('[data-target-report-prompt]').hidden,true,'Hidden wrong-kind questions and fixed Place counts must not create Facility missing prompts');
assert.equal(areaCtx.hidden, false);
assert.ok(requests.some(v=>v.includes('area=55')));

// Returning to the GREEN Place restores the original Place data and hides
// the Area, not a purple-bordered copy of an Area questionnaire.
event('place');
assert.ok(isShown(place), 'Place questions must return after Area');
assert.equal(place.querySelector('strong').textContent,'Toilets at campground: Yes');
assert.ok(isShown(shared));
assert.ok(!isShown(area));
assert.ok(!isShown(site));
assert.equal(areaCtx.hidden,true);
assert.equal(q('[data-target-report-prompt]').hidden,false,'Place fixed missing count returns with Place');
assert.match(q('[data-target-report-prompt] p').textContent,/1 applicable detail/);

// ORANGE Site still has its own inventory; a Site-only card that was
// initially hidden on Place must not remain permanently hidden.
event('site','2');
assert.ok(isShown(site), 'Site-only field must work after server initial hide');
assert.ok(!isShown(place));
assert.ok(!isShown(area));
event('place');
assert.ok(isShown(place));
assert.ok(!isShown(site));
dom.window.close();

// Reproduce a real Site browser selection, with a deferred authorized
// report response. Inspect ancestors, not just the card's own hidden flag.
const detail = new JSDOM(`<!doctype html><body><style>${css}</style>
<section class="scout-report">
  <p data-site-report-status hidden></p>
  <section class="scout-report-section" data-report-page="place" data-report-scopes="place">
    <div class="scout-report-grid"><div class="scout-report-item" data-report-scopes="place" data-report-owner="place"><strong>Campground</strong></div></div>
  </section>
  <section class="scout-report-section" data-report-page="site" data-report-scopes="site" data-report-warnings hidden>
    <p data-report-warning-summary></p><div class="scout-report-grid" data-report-warning-grid></div>
  </section>
  <section class="scout-report-section" data-report-page="site" data-report-scopes="site" data-report-target-section="1">
    <div class="scout-report-subsection"><div class="scout-report-grid">
      <div class="scout-report-item" data-report-scopes="site" data-report-owner="site" data-campsite-report-field="site_length_feet" hidden><div class="scout-report-value-content"><strong>Not provided</strong></div></div>
      <div class="scout-report-item" data-report-scopes="site" data-report-owner="site" data-campsite-report-field="site_potable_water" hidden><div class="scout-report-value-content"><strong>Not provided</strong></div></div>
    </div></div>
    <div data-target-report-prompt hidden><strong></strong><p></p></div>
  </section>
</section>
<div data-place-campsites-mount data-place-slug="example" data-complete-access="1"></div></body>`,{url:'https://llamascout.com/place.php?slug=example',runScripts:'outside-only'});
const win = detail.window;
const find = selector => win.document.querySelector(selector);
const wait = () => new Promise(resolve=>setImmediate(resolve));
const shown = node => {
    for (let el=node; el; el=el.parentElement) {
        if (el.hidden || win.getComputedStyle(el).display==='none') return false;
    }
    return true;
};
win.CSS = win.CSS || {};
win.CSS.escape = String;
win.HTMLElement.prototype.scrollTo = () => {};
win.matchMedia = () => ({matches:false});
const pending = [];
win.fetch = url => String(url).includes('mode=report')
    ? new Promise(resolve=>pending.push(resolve))
    : Promise.resolve({ok:true,json:async()=>({ok:true,browser:{show_site_layer:true},sites:[
        {feature_id:2,display_name:'Site 2',summary:[]},
        {feature_id:3,display_name:'Site 3',summary:[]},
        {feature_id:4,display_name:'Site 4',summary:[]},
    ]})});
win.eval(read('js/place-report-warnings.js'));
win.eval(js);
await wait();
const choose = id => find('[data-campsite-id="'+id+'"]').click();
const length = find('[data-campsite-report-field="site_length_feet"]');
const water = find('[data-campsite-report-field="site_potable_water"]');
const warnings = find('[data-report-warnings]');
const status = find('[data-site-report-status]');
choose(2);
assert.equal(pending.length,1);
assert.ok(!shown(length),'While loading, do not show previous or placeholder answers');
assert.ok(!shown(warnings),'No empty Quick Warnings heading');
assert.ok(shown(status),'Visible loading state');
pending.shift()({ok:true,json:async()=>({ok:true,site:{
    id:2,display_name:'Site 2',report_effective_values:{site_length_feet:'35 ft',site_potable_water:'Seasonal'},
    report_inapplicable_keys:[],report_warnings:{hazard:{label:'Steep access',icon:'alert-triangle'}},
}})});
await wait();
assert.ok(shown(length),'Site answers and every enclosing section/grid must be visible after asynchronous load');
assert.ok(shown(water),'Seasonal Site water answer is visible');
assert.equal(length.querySelector('strong').textContent,'35 ft');
assert.equal(water.querySelector('strong').textContent,'Seasonal');
assert.ok(shown(find('.scout-report-warning-item')),'Actual warning survives scope guard and grid visibility');
assert.ok(!shown(status));
choose(3);
assert.ok(!shown(length),'Next Site clears prior answers');
pending.shift()({ok:true,json:async()=>({ok:true,site:{
    id:3,display_name:'Site 3',report_effective_values:{site_length_feet:'18 ft'},
    report_inapplicable_keys:['site_potable_water'],report_warnings:{},
}})});
await wait();
assert.ok(shown(length));
assert.equal(length.querySelector('strong').textContent,'18 ft');
assert.ok(!shown(water),'Inapplicable Site field remains hidden');
assert.ok(!shown(warnings),'No warnings means no warning heading');
choose(2);
assert.ok(shown(length),'Cached report restores the enclosing section too');
assert.ok(shown(find('.scout-report-warning-item')));
choose(4);
pending.shift()({ok:false});
await wait();
assert.match(status.textContent,/could not be loaded/);
assert.ok(shown(status),'Failed API response must not silently leave blank report');
win.dispatchEvent(new win.CustomEvent('llama:explorer-selection',{detail:{kind:'place'}}));
assert.ok(shown(find('[data-report-owner="place"]')));
assert.ok(!shown(length));
assert.ok(!shown(status));
assert.ok(!shown(warnings));
win.close();
console.log('PASS: Place and Facility isolation, delayed Site report, cached Site, warnings, API errors and return to Place');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
