'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const script = fs.readFileSync(path.resolve(__dirname,
    '../../js/admin/place-report-save.js'), 'utf8');

const config = {
    sites: [
        {id:'campsite_record:1', label:'Site 1', imported_values:{},
            override_values:{pets_allowed:'0'}},
        {id:'campsite_record:2', label:'Site 2', imported_values:{},
            override_values:{}}
    ],
    site_field_keys:['pets_allowed','generator_prohibited','amenity_fire_ring'],
    site_shared_field_keys:['pets_allowed','generator_prohibited'],
    place_shared_answers:{pets_allowed:'1',generator_prohibited:'0'},
    place_field_keys:[],
    unknown_token:'__LLAMA_UNKNOWN__',
    unanswered_token:'__LLAMA_UNANSWERED__'
};
const radios=(name)=>`<div class="contribution-field" data-report-field="${name}">
    <input type="hidden" name="${name}" value="__LLAMA_UNANSWERED__">
    <label><input type="radio" name="${name}" value="1">Yes</label>
    <label><input type="radio" name="${name}" value="0">No</label>
    <label><input type="radio" name="${name}" value="__LLAMA_UNKNOWN__">?</label>
</div>`;
const html=`<!doctype html><html><body>
<div data-contribution-tabs>
    <button data-contribution-mode="place" aria-pressed="false">Place</button>
    <button data-contribution-mode="site" aria-pressed="true">Site</button>
    <form id="place-report" method="post">
        <input name="csrf_token" value="csrf">
        <input name="place_admin_action" value="save-report">
        <input name="photos_json" value="[]">
        <script type="application/json" data-admin-place-campsites>${JSON.stringify(config)}</script>
        <section data-report-main-section="place">
            ${radios('pets_allowed')}
            ${radios('generator_prohibited')}
        </section>
        <details data-report-main-section="site">
            <select name="selected_campsite_id" data-admin-campsite-selector>
                <option value="">Select Site</option>
                <option value="campsite_record:1" selected>Site 1</option>
                <option value="campsite_record:2">Site 2</option>
            </select>
            <label data-admin-overwrite-site-panel hidden>
                <input type="checkbox" data-admin-overwrite-site-answers>
            </label>
            ${radios('amenity_fire_ring')}
        </details>
        <fieldset data-admin-site-shared-questions disabled hidden>
            ${radios('admin_site_pets_allowed')}
            ${radios('admin_site_generator_prohibited')}
        </fieldset>
        <div class="admin-place-report-savebar">
            <button type="button" data-place-report-admin-save>Save Site Report</button>
        </div>
    </form>
</div></body></html>`;

(async()=>{
    const dom=new JSDOM(html,{
        url:'https://admin.llamascout.com/place.php?id=42',
        runScripts:'outside-only'
    });
    const w=dom.window, d=w.document;
    const q=s=>d.querySelector(s);
    const selected=name=>q(`input[name="${name}"]:checked`)?.value || '';
    const choose=(name,value)=>{
        const node=q(`input[name="${name}"][value="${value}"]`);
        assert.ok(node, 'Missing field '+name);
        node.checked=true;
        node.dispatchEvent(new w.Event('change',{bubbles:true}));
    };
    const switchSite=id=>{
        const select=q('[data-admin-campsite-selector]');
        select.value=id;
        select.dispatchEvent(new w.Event('change',{bubbles:true}));
    };
    const requests=[];
    w.confirm=()=>true;
    w.alert=()=>{};
    w.fetch=async(url,{body})=>{
        requests.push({
            mode:body.get('admin_report_mode'),
            site:body.get('selected_campsite_id'),
            answers:JSON.parse(body.get('admin_site_answers_json')||'{}'),
            parentValue:body.getAll('pets_allowed').at(-1)
        });
        return {ok:true,text:async()=>JSON.stringify({success:true,photos_added:0,photo_total:0})};
    };

    // The parent Place radios remain a separate group from each Site's radios.
    choose('pets_allowed','1');
    choose('generator_prohibited','0');
    w.eval(script);
    assert.equal(q('[data-admin-site-shared-questions]').disabled,false,
        'Selecting Site must enable Site-specific controls.');
    assert.equal(selected('admin_site_pets_allowed'),'0','Site 1 override must prefill No.');
    assert.equal(selected('admin_site_generator_prohibited'),'0',
        'Site 1 inherits parent generator policy.');
    assert.equal(selected('pets_allowed'),'1','Site controls must not uncheck Place Yes.');
    assert.equal(selected('generator_prohibited'),'0','Place generator policy must be intact.');

    choose('admin_site_generator_prohibited','1');
    assert.equal(selected('generator_prohibited'),'0',
        'Changing Site generator policy must not touch the Place form.');
    q('[data-place-report-admin-save]').click();
    await new Promise(r=>setTimeout(r,65));
    assert.equal(requests.length,1);
    assert.deepEqual(requests[0],{
        mode:'site',site:'campsite_record:1',answers:{generator_prohibited:'1'},parentValue:'1'
    },'Only changed Site override must be saved, not inherited/prefilled rules.');

    switchSite('campsite_record:2');
    assert.equal(selected('admin_site_pets_allowed'),'1',
        'Another Site must inherit Place Yes instead of Site 1 No.');
    assert.equal(selected('admin_site_generator_prohibited'),'0',
        'Another Site must inherit Place generator No.');
    assert.equal(selected('pets_allowed'),'1','Parent Place policy must remain Yes.');
    choose('admin_site_pets_allowed','0');
    q('[data-place-report-admin-save]').click();
    await new Promise(r=>setTimeout(r,65));
    assert.equal(requests.length,2);
    assert.deepEqual(requests[1].answers,{pets_allowed:'0'},
        'Site 2 must save only its own changed pet policy.');

    switchSite('campsite_record:1');
    assert.equal(selected('admin_site_pets_allowed'),'0',
        'Site 1 direct pet override must survive switching away and back.');
    assert.equal(selected('admin_site_generator_prohibited'),'1',
        'Saved Site 1 generator override must survive switching away and back.');

    q('[data-contribution-mode="site"]').setAttribute('aria-pressed','false');
    q('[data-contribution-mode="place"]').setAttribute('aria-pressed','true');
    q('[data-contribution-tabs]').dispatchEvent(
        new w.CustomEvent('llama:contribution-mode',{detail:{mode:'place'}})
    );
    assert.equal(q('[data-admin-site-shared-questions]').hidden,true);
    assert.equal(q('[data-admin-site-shared-questions]').disabled,true);
    assert.equal(selected('pets_allowed'),'1',
        'Returning to Place must show the untouched parent pet policy.');
    assert.equal(new w.FormData(q('#place-report')).has('admin_site_pets_allowed'),false,
        'Site-only controls must be excluded from a Place save.');

    dom.window.close();
    console.log('PASS: 29 Site-policy controls isolated from Place answers, prefilled, saved and switched.');
})().catch(error=>{console.error(error);process.exitCode=1;});
