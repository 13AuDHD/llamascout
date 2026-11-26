'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '../..');
const tabs = fs.readFileSync(path.join(root, 'js/contribution-tabs.js'), 'utf8');
const metadataJs = fs.readFileSync(path.join(root, 'js/admin/place-facility-editor.js'), 'utf8');
const areaJs = fs.readFileSync(path.join(root, 'js/admin/place-report-area.js'), 'utf8');

function setup(source) {
    const html = [
        '<!doctype html><body><div data-contribution-tabs data-contribution-split',
        ' data-admin-facility-source="' + source + '" data-contribution-initial="facility">',
        '<nav data-contribution-picker hidden>',
        '<button type="button" data-contribution-mode="place" aria-pressed="true">Place</button>',
        '<button type="button" data-contribution-mode="site" aria-pressed="false">Site</button>',
        '<button type="button" data-contribution-mode="facility" aria-pressed="false">Facility</button>',
        '</nav>',
        '<section data-admin-area-picker hidden>',
        '<select data-admin-area-selector data-admin-facility-selector><option value="map_feature:20">Bath House</option>',
        '<option value="resource:7">Laundry Room</option></select>',
        '</section>',
        '<form id="place-report" data-contribution-report>',
        '<select data-admin-campsite-selector><option value="">Choose Site</option></select>',
        '<details data-report-main-section="place"></details>',
        '<details data-report-main-section="site"></details>',
        '<section data-admin-facility-metadata hidden>',
        '<input data-admin-facility-name name="facility_name">',
        '<select data-admin-facility-kind><option value="shower">Shower</option></select>',
        '<div data-admin-facility-kind-wrap></div>',
        '<textarea data-admin-facility-notes></textarea>',
        '<input data-admin-facility-coordinates>',
        '<input data-admin-facility-coordinates-changed value="0">',
        '<p data-admin-facility-map-hint></p><a data-admin-facility-map-link href="/edit-map.php"></a>',
        '</section>',
        '<script type="application/json" data-admin-facility-map-catalog>',
        JSON.stringify({
            'map_feature:20': {
                id: 20, name: 'Bath House', facility_kind: 'shower',
                notes: 'Original details', feature_type: 'facility_area',
                coordinates: '37.2500000, -107.2200000', coordinates_editable: true
            },
            'map_feature:21': {
                id: 21, name: 'Parking Loop', facility_kind: '',
                feature_type: 'parking_area', coordinates: '',
                coordinates_editable: false
            }
        }),
        '</script></form>',
        '<section data-admin-resource-facility-editor hidden>Modern editor</section>',
        '</div></body>'
    ].join('');
    const dom = new JSDOM(html, {
        url: 'https://admin.llamascout.com/place.php?id=1&mode=facility',
        runScripts: 'outside-only'
    });
    dom.window.eval(metadataJs);
    dom.window.eval(tabs);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
    return {dom, w: dom.window, d: dom.window.document};
}

{
    const {dom, d} = setup('resource');
    assert.equal(d.querySelectorAll('[data-contribution-mode]').length, 3);
    assert.equal(d.querySelector('[data-contribution-picker]').hidden, false);
    assert.equal(d.querySelector('[data-contribution-report]').hidden, true);
    assert.equal(d.querySelector('[data-admin-resource-facility-editor]').hidden, false);
    assert.equal(d.querySelector('[data-admin-area-picker]').hidden, false,
        'The Facility selector must stay available while the modern editor is visible');
    d.querySelector('[data-contribution-mode="place"]').click();
    assert.equal(d.querySelector('[data-contribution-report]').hidden, false);
    assert.equal(d.querySelector('[data-admin-resource-facility-editor]').hidden, true);
    d.querySelector('[data-contribution-mode="facility"]').click();
    assert.equal(d.querySelector('[data-contribution-report]').hidden, true);
    dom.window.close();
}

{
    const {dom, w, d} = setup('map_feature');
    const meta = d.querySelector('[data-admin-facility-metadata]');
    assert.equal(d.querySelector('[data-contribution-report]').hidden, false);
    assert.equal(d.querySelector('[data-admin-resource-facility-editor]').hidden, true);
    assert.equal(meta.hidden, false);
    assert.equal(d.querySelector('[data-admin-facility-name]').value, 'Bath House');
    assert.equal(d.querySelector('[data-admin-facility-coordinates]').value,
        '37.2500000, -107.2200000');
    const coords = d.querySelector('[data-admin-facility-coordinates]');
    coords.value = '37.2522, -107.2192';
    coords.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.equal(d.querySelector('[data-admin-facility-coordinates-changed]').value, '1');
    const event = new w.CustomEvent('llama:admin-place-report-before-save', {cancelable: true});
    d.querySelector('#place-report').dispatchEvent(event);
    assert.equal(event.defaultPrevented, false);
    dom.window.close();
}
// Real mapped Facility controller: a preselected deep link initializes
// answers immediately, and the edited kind controls the next payload.
{
    const dom = new JSDOM(`<div data-contribution-tabs data-admin-facility-source="map_feature">
        <button data-contribution-mode="facility" aria-pressed="true"></button>
        <select data-admin-area-selector><option value="map_feature:20" selected>Bath House</option></select>
        <input name="selected_area_id" form="place-report">
        <script type="application/json" data-admin-place-areas>${JSON.stringify({
            areas:[{id:'map_feature:20',feature_type:'facility_area',facility_kind:'shower',
                form_values:{facility_shower_stalls:'2'}}],
            field_keys:['facility_shower_stalls','facility_toilet_count']
        })}</script>
        <form id="place-report">
            <select data-admin-facility-kind><option value="shower">Shower</option><option value="restroom">Restroom</option></select>
            <section data-admin-facility-questions hidden>
                <div data-admin-area-question-group>
                    <div data-area-question data-report-field="facility_shower_stalls" data-facility-kinds="shower"><input name="facility_shower_stalls"></div>
                    <div data-area-question data-report-field="facility_toilet_count" data-facility-kinds="restroom"><input name="facility_toilet_count"></div>
                </div>
            </section>
        </form></div>`, {url:'https://admin.llamascout.com/place.php?id=1&mode=facility&facility=map_feature:20',runScripts:'outside-only'});
    const w = dom.window;
    const d = w.document;
    w.structuredClone = value => JSON.parse(JSON.stringify(value));
    w.eval(areaJs);
    const q = selector => d.querySelector(selector);
    assert.equal(q('[name="selected_area_id"]').value,'map_feature:20');
    assert.equal(q('[name="facility_shower_stalls"]').value,'2','Direct link loads the selected Facility');
    assert.equal(q('[data-report-field="facility_shower_stalls"]').hidden,false);
    assert.equal(q('[data-report-field="facility_toilet_count"]').hidden,true);
    q('[name="facility_shower_stalls"]').value='3';
    q('[data-admin-facility-kind]').value='restroom';
    q('[data-admin-facility-kind]').dispatchEvent(new w.Event('change',{bubbles:true}));
    assert.equal(q('[data-report-field="facility_shower_stalls"]').hidden,true);
    assert.equal(q('[data-report-field="facility_toilet_count"]').hidden,false);
    q('[name="facility_toilet_count"]').value='4';
    q('#place-report').dispatchEvent(new w.CustomEvent('llama:admin-place-report-save-starting'));
    assert.deepEqual(JSON.parse(q('[name="admin_area_answers_json"]').value),{facility_toilet_count:'4'},
        'The prospective kind excludes hidden old-kind answers');
    w.dispatchEvent(new w.CustomEvent('llama:admin-place-report-saved'));
    assert.equal(q('[name="facility_toilet_count"]').value,'4');
    assert.equal(q('[data-report-field="facility_toilet_count"]').hidden,false);
    dom.window.close();
}
console.log('PASS: Unified Facility tabs, visible selector, map-backed GPS, direct links, kind changes and independent modern editor.');
