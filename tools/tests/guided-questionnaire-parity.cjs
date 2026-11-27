'use strict';

// Test actual PHP markup, actual schema and actual visibility engine together.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname,'../..');
const source = fs.readFileSync(path.join(root,'js/place-report-form.js'),'utf8');
const hazards = fs.readFileSync(path.join(root,'js/place-report-road-hazards.js'),'utf8');
const renderFixture = scope => execFileSync('php',[
    path.join(__dirname,'guided-questionnaire-render-fixture.php'),scope
],{encoding:'utf8',maxBuffer:16*1024*1024});
const fixtures = {place:renderFixture('place'),site:renderFixture('site')};

async function boot(scope) {
    const dom = new JSDOM(fixtures[scope],{runScripts:'outside-only',
        url:'https://llamascout.com/add-'+scope+'.php'});
    const w = dom.window, d = w.document;
    w.CSS = w.CSS || {};
    w.CSS.escape = value => String(value).replace(/[^a-zA-Z0-9_-]/g,'\\$&');
    w.matchMedia = () => ({matches:true,addEventListener(){}});
    w.LlamaPlaceReportForm = {setupRecovery(){}};
    const errors = [];
    w.addEventListener('error',event=>errors.push(event.message));
    w.eval(source);
    w.eval(hazards);
    await new Promise(resolve=>d.addEventListener('DOMContentLoaded',resolve,{once:true}));
    const form = d.querySelector('form');
    const q = selector => form.querySelector(selector);
    const field = key => q('[data-report-field="'+key+'"]');
    const next = q('[data-scout-card-next]');
    const back = q('[data-scout-card-back]');
    const heading = q('[data-scout-card-heading]');
    const toggle = q('[data-scout-card-toggle]');
    const settle = () => new Promise(resolve=>setImmediate(resolve));
    const eligible = (node, guided) => {
        for (let el=node;el&&el!==form;el=el.parentElement) {
            if (el.hidden || el.classList.contains('is-applicability-hidden')
                || el.classList.contains('is-force-hidden')
                || (guided && el.classList.contains('is-scout-card-offstage'))) return false;
        }
        return true;
    };
    const visibleKeys = guided => [...new Set([...form.querySelectorAll('[data-report-field]')]
        .filter(node=>eligible(node,guided)).map(node=>node.dataset.reportField))];
    const change = (key,value) => {
        const controls = [...form.querySelectorAll('[name="'+key+'"]')];
        const input = controls.find(el=>el.type==='radio' && el.value===value)
            || controls.find(el=>el.type!=='hidden');
        assert.ok(input,'Missing native control '+key);
        if (input.type==='radio') input.checked=true;
        else input.value=value;
        input.dispatchEvent(new w.Event('input',{bubbles:true}));
        input.dispatchEvent(new w.Event('change',{bubbles:true}));
    };
    const goTo = async key => {
        let count=0;
        while (!visibleKeys(true).includes(key) && !next.disabled && count++<450) {
            next.click(); await settle();
        }
        assert.ok(visibleKeys(true).includes(key),'Card never reached '+key+' ('+heading.textContent+')');
    };
    const chooseCard = (key,value) => {
        const native = q('select[name="'+key+'"]');
        const option = [...native.options].find(opt=>opt.value===value);
        assert.ok(option,'Missing native option '+value);
        const button = [...field(key).querySelectorAll('.scout-card-choice')]
            .find(el=>el.textContent.trim()===option.textContent.trim());
        assert.ok(button,'Missing card option '+value);
        button.click();
        assert.equal(native.value,value,'Card choice must update the original select');
    };
    const compareWalk = async label => {
        // Compare full questionnaire eligibility with actual Back/Next
        // traversal. Ignore closed accordion state, not eligibility.
        if (form.classList.contains('is-scout-card-view')) toggle.click();
        await settle();
        const expected = visibleKeys(false).sort();
        toggle.click();
        await settle();
        let count=0;
        while (!back.disabled && count++<450) {back.click(); await settle();}
        assert.ok(back.disabled,'Reached first card');
        const visited = new Set();
        count=0;
        while (true) {
            for (const key of visibleKeys(true)) visited.add(key);
            if (next.disabled) break;
            assert.ok(count++<450,'Guided navigation got stuck');
            next.click(); await settle();
        }
        assert.equal(heading.textContent,'Review and submit');
        assert.deepEqual([...visited].sort(),expected,label+' missing or extra question(s)');
        assert.equal(q('[data-scout-card-progress]').textContent,'100%');
        toggle.click();
        await settle();
        assert.deepEqual(visibleKeys(false).sort(),expected,'Full view restored without changing applicability');
        assert.deepEqual(errors,[],'No browser errors');
        console.log(label+': '+expected.length+' applicable fields reached in both views');
        return expected;
    };
    return {dom,w,form,q,field,next,toggle,settle,eligible,visibleKeys,change,goTo,chooseCard,compareWalk};
}

(async()=>{
    const p=await boot('place');
    try {
        assert.ok(p.form.classList.contains('is-scout-card-view'));
        await p.goTo('type');
        p.chooseCard('type','developed-campground');
        await p.settle();
        await p.goTo('approach_road_hazards');
        const rocks=p.q('[name="approach_road_hazards[]"][value="rocks"]');
        assert.ok(rocks && p.eligible(rocks,true),'Road Hazards card must show actual options');
        rocks.click();
        await p.settle();
        await p.goTo('rocks');
        assert.match(p.q('[data-scout-card-context]').textContent,/Getting There.*Road Conditions/);
        await p.goTo('sedan_accessible');
        p.change('sedan_accessible','0');
        await p.settle();
        await p.goTo('high_clearance_recommended');
        await p.goTo('four_wheel_drive_recommended');
        const camp=await p.compareWalk('Developed campground with hazards and restricted vehicle access');
        assert.ok(camp.includes('road_surface')&&camp.includes('rocks')&&camp.includes('high_clearance_recommended'));
        // Same native Place type controls, different approved eligibility.
        p.change('type','retail-parking');
        await p.settle();
        const retail=await p.compareWalk('Retail parking');
        assert.notDeepEqual(retail,camp,'Place type must actually change the eligible inventory');
        assert.ok(!retail.includes('rocks'),'Parking type does not retain campground hazards');
        p.change('type','dispersed-camping');
        p.change('sedan_accessible','1');
        await p.settle();
        const dispersed=await p.compareWalk('Dispersed camping with sedan access');
        assert.ok(dispersed.includes('road_surface')&&!dispersed.includes('high_clearance_recommended'));
    } finally {p.dom.window.close();}
    const s=await boot('site');
    try {
        const developed=await s.compareWalk('Site under developed campground');
        s.form.dataset.reportContext=JSON.stringify({type:'retail-parking',amenity_wifi:'0'});
        s.form.dispatchEvent(new s.w.Event('change',{bubbles:true}));
        await s.settle();
        const parking=await s.compareWalk('Site under retail parking');
        assert.notDeepEqual(parking,developed,'Parent Place type changes Site eligibility');
    } finally {s.dom.window.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
