'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'js/place-update-report-target.js'), 'utf8');

const targets = [
    {scope:'place',source:'place',id:1,label:'Entire Place'},
    {scope:'site',source:'campsite_record',id:22,label:'Site 22'}
];
const fields = [
    {key:'site_allowed_setups',label:'Site setups',type:'multiselect',
     layout_section:'site',layout_group:'selection_type',
     layout_section_label:'Site',layout_group_label:'Selection',
     scopes:['site'],options:{car:'Car',tent:'Tent'},
     derived:false,location:false,excluded:false},
    {key:'site_number',label:'Site number',type:'text',
     layout_section:'site',layout_group:'selection_type',
     layout_section_label:'Site',layout_group_label:'Selection',
     scopes:['site'],options:{},
     derived:false,location:false,excluded:false},
    {key:'pets_allowed',label:'Pets',type:'tri',
     layout_section:'place',layout_group:'place_rules',
     layout_section_label:'Place',layout_group_label:'Rules',
     scopes:['place','site'],options:{},
     derived:false,location:false,excluded:false}
];
const initial = {
    scope:'site',source:'campsite_record',id:22,locked:true,
    answers:{site_allowed_setups:['car','tent'],site_number:'0',pets_allowed:'0'}
};
const encode = data => JSON.stringify(data).replace(/</g,'\\u003c');
const html = `<!doctype html><html><body>
    <form class="place-update-form">
        <section class="place-update-report-target">
            <select data-report-target-search>
                <option value="place:place:1">Entire Place</option>
                <option value="site:campsite_record:22">Site 22</option>
            </select>
        </section>
        <input data-report-target-scope value="place">
        <input data-report-target-source value="place">
        <input data-report-target-id value="1">
        <p data-report-target-status></p>
        <div data-place-update-place-fields>
            <div class="contribution-field" data-report-field="site_allowed_setups">
                <label data-place-report-multiselect-option>
                    <input type="checkbox" name="site_allowed_setups[]" value="car">Car
                </label>
                <label data-place-report-multiselect-option>
                    <input type="checkbox" name="site_allowed_setups[]" value="tent">Tent
                </label>
                <label><input type="checkbox" data-report-multiselect-unknown
                    value="__LLAMA_UNKNOWN__">?</label>
            </div>
            <div class="contribution-field" data-report-field="site_number">
                <input type="text" name="site_number">
            </div>
            <div class="contribution-field" data-report-field="pets_allowed">
                <label><input type="radio" name="pets_allowed" value="1">Yes</label>
                <label><input type="radio" name="pets_allowed" value="0">No</label>
                <label><input type="radio" name="pets_allowed" value="__LLAMA_UNKNOWN__">?</label>
            </div>
        </div>
        <details data-place-update-scoped-fields>
            <div data-scoped-report-questions></div>
            <input name="scoped_answers_json" data-scoped-answers-json value="">
            <p data-scoped-field-notice></p>
        </details>
    </form>
    <script type="application/json" id="place-update-report-target-data">${encode(targets)}</script>
    <script type="application/json" id="place-update-scoped-fields-data">${encode(fields)}</script>
    <script type="application/json" id="place-update-report-initial-data">${encode(initial)}</script>
</body></html>`;

(async () => {
    const dom = new JSDOM(html, {
        url:'https://account.llamascout.com/update-place.php?slug=test',
        runScripts:'outside-only'
    });
    const w = dom.window;
    w.fetch = async () => ({ok:true,json:async () => ({
        direct:{site_allowed_setups:['car'],site_number:'1',pets_allowed:'1'},
        inherited:{}
    })});
    w.eval(script);
    await new Promise(resolve=>setTimeout(resolve,25));
    const d = w.document;
    const setups = [...d.querySelectorAll('[data-scoped-question="site_allowed_setups"] input[type="checkbox"]')]
        .filter(el=>!el.matches('[data-report-multiselect-unknown]'));
    assert.equal(setups.length,2);
    assert.deepEqual(setups.map(el=>el.checked),[true,true],
        'Needs-changes multiselect selections must be restored, not discarded.');
    const num = d.querySelector('[data-scoped-question="site_number"] input');
    assert.equal(num.value,'0','An explicit zero must remain in the editable control.');
    assert.equal(d.querySelector('[data-scoped-question="pets_allowed"] input:checked')?.value,'0',
        'An explicit No must be checked, not treated as missing.');
    const report = d.querySelector('[data-scoped-answers-json]');
    assert.deepEqual(JSON.parse(report.value),[
        {key:'site_allowed_setups',value:['car','tent']},
        {key:'site_number',value:'0'},
        {key:'pets_allowed',value:'0'}
    ],'All proposed answer types must remain in the same Site-only draft.');

    // Removing the last selected multiselect choice is a real proposed
    // cleared set, not an unanswered field or a change to another target.
    for (const control of setups) {
        control.checked = false;
        control.dispatchEvent(new w.Event('change',{bubbles:true}));
    }
    assert.deepEqual(JSON.parse(report.value)[0],
        {key:'site_allowed_setups',value:[]},
        'Clearing a recorded multiselect must retain the explicit empty set.');
    dom.window.close();
    console.log('PASS: Site Update revisions restore multiselect, explicit empty set, No and 0.');
})().catch(error=>{console.error(error);process.exitCode=1;});
