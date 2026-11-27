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

async function boot(scope, mobile = true) {
    const dom = new JSDOM(fixtures[scope],{runScripts:'outside-only',
        url:'https://llamascout.com/add-'+scope+'.php'});
    const w = dom.window, d = w.document;
    w.CSS = w.CSS || {};
    w.CSS.escape = value => String(value).replace(/[^a-zA-Z0-9_-]/g,'\\$&');
    w.matchMedia = () => ({matches:mobile,addEventListener(){}});
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
        const fullOrder = visibleKeys(false);
        const expected = [...fullOrder].sort();
        toggle.click();
        await settle();
        let count=0;
        while (!back.disabled && count++<450) {back.click(); await settle();}
        assert.ok(back.disabled,'Reached first card');
        const visited = new Set();
        const ordered = [];
        count=0;
        while (true) {
            for (const key of visibleKeys(true)) {
                if (!visited.has(key)) ordered.push(key);
                visited.add(key);
            }
            if (next.disabled) break;
            assert.ok(count++<450,'Guided navigation got stuck');
            next.click(); await settle();
        }
        assert.equal(heading.textContent,'Review and submit');
        assert.deepEqual([...visited].sort(),expected,label+' missing or extra question(s)');
        if (scope === 'place') {
            assert.deepEqual(ordered,fullOrder,label+' must preserve the exact full form question order');
            const road = [...form.querySelectorAll('[data-report-main-section="getting_there"] [data-report-field]')]
                .filter(node=>eligible(node,false)).map(node=>node.dataset.reportField);
            assert.ok(road.length>0,label+' must include Getting There');
            assert.equal(ordered[0],road[0]);
            for (const key of road) {
                assert.ok(ordered.indexOf(key)<ordered.indexOf('type'),
                    label+' Getting There field '+key+' must precede Place Type');
            }
            assert.ok(ordered.indexOf('type')<ordered.indexOf('name'));
            assert.ok(ordered.indexOf('visited_at')<ordered.indexOf('description'),'Date visited stays before Description');
        }
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
    const p=await boot('place',false);
    try {
        assert.ok(!p.form.classList.contains('is-scout-card-view'),'Tablet starts in full questionnaire');
        p.toggle.click();
        assert.ok(p.form.classList.contains('is-scout-card-view'),'Manual Card View uses the same order as mobile');
        assert.match(p.q('[data-scout-card-context]').textContent,/^Getting There.*Road Conditions/,'Getting There starts before Place Type even for a new Place');
        const firstPass = new Set();
        let guard=0;
        while (!p.visibleKeys(true).includes('type') && !p.next.disabled && guard++<450) {
            for (const key of p.visibleKeys(true)) firstPass.add(key);
            if (p.visibleKeys(true).includes('sedan_accessible')) p.change('sedan_accessible','0');
            p.next.click();
            await p.settle();
        }
        assert.ok(p.visibleKeys(true).includes('type'),'Place Type remains in Basic');
        assert.match(p.q('[data-scout-card-context]').textContent,/^Place.*Basic/);
        assert.ok(firstPass.size>0);
        assert.ok(!firstPass.has('name'));
        assert.equal(p.next.disabled,true,'Existing required Place Type must be answered here');
        p.chooseCard('type','developed-campground');
        await p.settle();
        assert.equal(p.next.disabled,false);
        const newlyEligible = [...p.form.querySelectorAll('[data-report-main-section="getting_there"] [data-report-field]')]
            .filter(node=>p.eligible(node,false)&&!firstPass.has(node.dataset.reportField))
            .map(node=>node.dataset.reportField);
        assert.ok(newlyEligible.includes('approach_road_hazards'),'Road hazards become applicable after type selection');
        const returned = new Set();
        guard=0;
        p.next.click();
        await p.settle();
        while (!p.visibleKeys(true).includes('name') && !p.next.disabled && guard++<450) {
            for (const key of p.visibleKeys(true)) {
                assert.ok(!firstPass.has(key),'Earlier visited question should not be repeated: '+key);
                returned.add(key);
            }
            if (p.visibleKeys(true).includes('approach_road_hazards')) {
                const rocks=p.q('[name="approach_road_hazards[]"][value="rocks"]');
                assert.ok(rocks && p.eligible(rocks,true),'Road Hazards shows the actual options');
                rocks.click();
                await p.settle();
            }
            if (p.visibleKeys(true).includes('sedan_accessible')) p.change('sedan_accessible','0');
            p.next.click();
            await p.settle();
        }
        assert.ok(p.visibleKeys(true).includes('name'),'Resume at Place Name after newly applicable Getting There cards');
        for (const key of newlyEligible) assert.ok(returned.has(key),'Newly applicable question was skipped: '+key);
        assert.ok(returned.has('rocks'),'Selecting rocks reveals its severity question during the return pass');
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
