'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'js/place-update-report-target.js'), 'utf8');

const targets = [
    {scope:'place',source:'place',id:1,label:'Entire Place'},
    {scope:'site',source:'campsite_record',id:10,label:'Rocky Camp Site'},
    {scope:'site',source:'campsite_record',id:11,label:'Pine Camp Site'},
    {scope:'area',source:'map_feature',id:20,label:'Shower House'},
];
const field = (key, group, type, label = key) => ({
    key, label, layout_section:'site', layout_group:group,
    layout_section_label:'Site', layout_group_label:group,
    scopes:['site'], type, options:type === 'select'
        ? {gravel:'Gravel', dirt:'Dirt', one:'One', two:'Two', three:'Three'} : {},
    derived:false, location:false, excluded:false
});
const fields = [
    field('site_number','selection_type','text','Site number'),
    field('parking_surface','parking_maneuvering','select','Parking surface'),
    field('daytime_noise','sensory_environment','select','Daytime noise'),
    field('connectivity_verizon','connectivity_services','select','Verizon signal'),
    field('experience_summary','experience_recommendations','textarea','Experience'),
    field('scout_note_1','site_photos','textarea','Scout note'),
];

const escape = text => JSON.stringify(text).replace(/</g, '\\u003c');
const editable = fields.map(f => {
    const input = f.type === 'select'
        ? '<select name="' + f.key + '"><option value=""></option>'
            + Object.entries(f.options).map(([k,v]) => '<option value="' + k + '">' + v + '</option>').join('')
            + '</select>'
        : f.type === 'textarea'
            ? '<textarea name="' + f.key + '"></textarea>'
            : '<input name="' + f.key + '" type="text">';
    return '<div class="contribution-field" data-report-field="' + f.key + '">' + input + '</div>';
}).join('');

const html = [
    '<!doctype html><html><body>',
    '<div data-contribution-tabs>',
    '<nav data-contribution-picker>',
    '<button data-contribution-mode="place" type="button">Place</button>',
    '<button data-contribution-mode="site" type="button">Site</button>',
    '<button data-contribution-mode="area" type="button">Facility</button>',
    '</nav>',
    '<form class="place-update-form">',
    '<section class="place-update-report-target">',
    '<label data-contribution-target-label></label>',
    '<select data-report-target-search>',
    ...targets.map(t => '<option value="' + t.scope + ':' + t.source + ':' + t.id
        + '">' + t.label + '</option>'),
    '</select>',
    '</section>',
    '<input data-report-target-scope name="report_target_scope" value="place">',
    '<input data-report-target-source name="report_target_source" value="place">',
    '<input data-report-target-id name="report_target_id" value="1">',
    '<p data-report-target-status></p>',
    '<div data-place-update-place-fields>' + editable + '</div>',
    '<details data-place-update-scoped-fields hidden>',
    '<summary data-contribution-scoped-title></summary>',
    '<div data-scoped-report-questions></div>',
    '<input data-scoped-answers-json name="scoped_answers_json" value="">',
    '<small data-scoped-field-notice></small>',
    '</details>',
    '<details data-place-update-site-identity hidden>',
    '<input data-site-identity-name><select data-site-identity-type>',
    '<option value=""></option><option value="tent_site">Tent</option><option value="rv_site">RV</option>',
    '</select><input data-site-identity-coordinates>',
    '<input type="hidden" name="site_identity_json" data-site-identity-json value="">',
    '<p data-site-identity-status></p>',
    '</details>',
    '</form>',
    '<script id="place-update-report-target-data" type="application/json">' + escape(targets) + '</script>',
    '<script id="place-update-scoped-fields-data" type="application/json">' + escape(fields) + '</script>',
    '<script id="place-update-report-initial-data" type="application/json">',
    escape({scope:'place',source:'place',id:1,answers:{}}),
    '</script>',
    '</div></body></html>',
].join('');

(async () => {
    const dom = new JSDOM(html, {
        url:'https://account.llamascout.com/update-place.php?slug=test',
        runScripts:'outside-only'
    });
    const w = dom.window;
    const d = w.document;
    const seen = [];
    w.fetch = async url => {
        const id = Number(new URL(url).searchParams.get('target_id'));
        seen.push(id);
        return {
            ok: true,
            json: async () => id === 10 ? {
                direct:{
                    site_number:'14', parking_surface:'gravel',
                    daytime_noise:'two', connectivity_verizon:'three',
                    experience_summary:'Old review', scout_note_1:'Old tip'
                },
                inherited:{},
                site_identity:{
                    name:'Rocky Camp', label:'Rocky Camp Site', type:'tent_site',
                    coordinates:'37.2522200, -107.2192000',
                    editable_coordinates:true, editable_type:true
                }
            } : {
                direct:{
                    site_number:'15', parking_surface:'dirt',
                    daytime_noise:'one', connectivity_verizon:'two'
                },
                inherited:{},
                site_identity:{
                    name:'Pine Camp', label:'Pine Camp Site', type:'rv_site',
                    coordinates:'37.3000000, -107.3000000',
                    editable_coordinates:true, editable_type:true
                }
            }
        };
    };
    w.eval(script);
    const flush = async () => { await Promise.resolve(); await new Promise(resolve=>setTimeout(resolve, 20)); };
    const get = key => d.querySelector('[data-scoped-question="' + key + '"]');
    const value = key => get(key)?.querySelector('input, select, textarea')?.value;
    const selected = d.querySelector('[data-report-target-search]');
    const form = d.querySelector('.place-update-form');
    const report = d.querySelector('[data-scoped-answers-json]');
    const identity = d.querySelector('[data-site-identity-json]');
    const name = d.querySelector('[data-site-identity-name]');
    const coordinates = d.querySelector('[data-site-identity-coordinates]');

    d.querySelector('[data-contribution-mode="site"]').dispatchEvent(
        new w.Event('click',{bubbles:true}));
    // The actual report tab shell emits this event.
    d.querySelector('[data-contribution-tabs]').dispatchEvent(
        new w.CustomEvent('llama:contribution-mode', {detail:{mode:'site'}}));
    await flush();
    assert.deepEqual(seen,[10]);
    assert.equal(value('site_number'),'14', 'Site identifier must be prefilled.');
    assert.equal(value('parking_surface'),'gravel','Site surface must be prefilled.');
    assert.equal(value('daytime_noise'),'','Daytime ratings must start fresh.');
    assert.equal(value('connectivity_verizon'),'','Connectivity must start fresh.');
    assert.equal(value('experience_summary'),'','Personal experience must start fresh.');
    assert.equal(value('scout_note_1'),'','Visit notes must start fresh.');
    assert.equal(name.value,'Rocky Camp');
    assert.equal(coordinates.value,'37.2522200, -107.2192000');
    assert.deepEqual(JSON.parse(report.value),[],
        'Prefilled physical Site facts must not be submitted until edited.');
    assert.equal(identity.value,'', 'Displayed identity must not submit without edits.');
    assert.match(get('daytime_noise').textContent, /Previous report: Two/,
        'Old sensory answers must be visible as reference, not input defaults.');

    const number = get('site_number').querySelector('input');
    number.value = '14B';
    number.dispatchEvent(new w.Event('input',{bubbles:true}));
    name.value = 'Rocky Camp East';
    name.dispatchEvent(new w.Event('input',{bubbles:true}));
    assert.deepEqual(JSON.parse(report.value),[{key:'site_number',value:'14B'}]);
    assert.deepEqual(JSON.parse(identity.value),{name:'Rocky Camp East'});

    selected.value='site:campsite_record:11';
    selected.dispatchEvent(new w.Event('change',{bubbles:true}));
    await flush();
    assert.equal(value('site_number'),'15','Different Site must load its own fact.');
    assert.equal(name.value,'Pine Camp', 'Different Site must load its own identity.');
    assert.equal(value('daytime_noise'),'');
    assert.deepEqual(JSON.parse(report.value),[], 'One Site draft must not leak to another.');
    assert.equal(identity.value,'');

    selected.value='site:campsite_record:10';
    selected.dispatchEvent(new w.Event('change',{bubbles:true}));
    await flush();
    assert.equal(value('site_number'),'14B','Returning must restore first Site draft.');
    assert.equal(name.value,'Rocky Camp East','Identity correction draft must survive Site switch.');
    assert.deepEqual(JSON.parse(identity.value),{name:'Rocky Camp East'});

    dom.window.close();
    console.log('PASS: Site facts prefilled, personal observations fresh, GPS editable, Site drafts isolated.');
})().catch(err => {console.error(err);process.exitCode=1;});
