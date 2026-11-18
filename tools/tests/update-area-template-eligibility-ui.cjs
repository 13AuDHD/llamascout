'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const js = fs.readFileSync(path.join(root, 'js/place-update-report-target.js'), 'utf8');
const targets = [
    {scope:'place',source:'place',id:1,label:'Entire Place'},
    {scope:'area',source:'map_feature',id:20,label:'North Restroom',
     feature_type:'facility_area',facility_kind:'restroom'},
    {scope:'area',source:'map_feature',id:21,label:'Forest Loop',
     feature_type:'camping_area',facility_kind:''},
];
const base = (key, label, metadata = {}) => ({
    key,label,type:'text',layout_section:'site',
    layout_group:'selection_type',layout_section_label:'Site',
    layout_group_label:'Selection',scopes:['area'],options:{},
    derived:false,location:false,excluded:false,...metadata
});
const fields = [
    base('area_site_count','Number of Sites',{
        area_feature_types:['camping_area','parking_area']}),
    base('facility_cleanliness','Cleanliness',{facility_kinds:['restroom']}),
    base('area_notes','Area Notes')
];
const encode = v => JSON.stringify(v).replace(/</g,'\\u003c');
const markup = `<!doctype html><html><body><form class="place-update-form">
    <div class="place-update-report-target">
        <select data-report-target-search>
            <option value="place:place:1">Entire Place</option>
            <option value="area:map_feature:20">North Restroom</option>
            <option value="area:map_feature:21">Forest Loop</option>
        </select>
    </div>
    <input data-report-target-scope value="place">
    <input data-report-target-source value="place">
    <input data-report-target-id value="1">
    <p data-report-target-status></p>
    <div data-place-update-place-fields>
        <fieldset hidden disabled data-scoped-field-templates>
            ${fields.map(f=>`<div class="contribution-field" data-report-field="${f.key}">
                <label>${f.label}<input type="text" name="${f.key}"></label>
            </div>`).join('')}
        </fieldset>
    </div>
    <details data-place-update-scoped-fields>
        <div data-scoped-report-questions></div>
        <input name="scoped_answers_json" data-scoped-answers-json value="">
        <p data-scoped-field-notice></p>
    </details>
    </form>
    <script id="place-update-report-target-data" type="application/json">${encode(targets)}</script>
    <script id="place-update-scoped-fields-data" type="application/json">${encode(fields)}</script>
    <script id="place-update-report-initial-data" type="application/json">${encode({
        scope:'area',source:'map_feature',id:20,answers:{}
    })}</script>
</body></html>`;

(async()=>{
    const dom = new JSDOM(markup,{
        url:'https://account.llamascout.com/update-place.php?slug=test',
        runScripts:'outside-only'
    });
    const w=dom.window;
    const d=w.document;
    w.fetch=async()=>({ok:true,json:async()=>({direct:{},inherited:{}})});
    w.eval(js);
    await new Promise(resolve=>setTimeout(resolve,20));
    const active=()=>[...d.querySelectorAll('[data-scoped-question]')]
        .map(el=>el.dataset.scopedQuestion);
    assert.deepEqual(active(),['facility_cleanliness','area_notes'],
        'A restroom Facility must show its applicable fields, not camping Area site counts.');
    assert.equal(new w.FormData(d.querySelector('form')).has('area_site_count'),false,
        'Hidden template controls must not be included in POST data.');
    const select=d.querySelector('[data-report-target-search]');
    select.value='area:map_feature:21';
    select.dispatchEvent(new w.Event('change',{bubbles:true}));
    await new Promise(resolve=>setTimeout(resolve,20));
    assert.deepEqual(active(),['area_site_count','area_notes'],
        'A camping Area must show canonical Area questions, not restroom-only fields.');

    const count=d.querySelector('[data-scoped-question="area_site_count"] input');
    count.value='12';
    count.dispatchEvent(new w.Event('input',{bubbles:true}));
    assert.deepEqual(JSON.parse(d.querySelector('[data-scoped-answers-json]').value),
        [{key:'area_site_count',value:'12'}],
        'One selected Area gets exactly its own pending answer.');
    dom.window.close();
    console.log('PASS: Update clones canonical Site/Area templates and applies verified Area feature eligibility.');
})().catch(e=>{console.error(e);process.exitCode=1;});
