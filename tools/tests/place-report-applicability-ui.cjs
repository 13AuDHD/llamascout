'use strict';

// Actual browser-DOM regression for shared Add/Update/Admin visibility.
// The source under test is the production js/place-report-form.js file.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../js/place-report-form.js'), 'utf8'
);

const rules = {
    site_hookups_available: [
        {field: 'type', operator: 'in', value: ['developed-campground']},
    ],
    hookup_electric: [
        {field: 'type', operator: 'in', value: ['developed-campground']},
    ],
    hookup_electric_service: [
        {field: 'site_hookups_available', operator: 'equals', value: '1'},
    ],
    generator_prohibited: [
        {field: 'generator_restrictions', operator: 'equals', value: '1'},
    ],
    generator_quiet_hours: [
        {field: 'generator_restrictions', operator: 'equals', value: '1'},
        {field: 'generator_prohibited', operator: 'equals', value: '0'},
    ],
    generator_quiet_hours_begin: [
        {field: 'generator_restrictions', operator: 'equals', value: '1'},
        {field: 'generator_prohibited', operator: 'equals', value: '0'},
        {field: 'generator_quiet_hours', operator: 'equals', value: '1'},
    ],
    tent_pad_width_feet: [
        {field: 'tent_pad', operator: 'equals', value: '1'},
    ],
};
const keys = [
    'type', 'site_hookups_available', 'hookup_electric',
    'hookup_electric_service', 'generator_restrictions',
    'generator_prohibited', 'generator_quiet_hours',
    'generator_quiet_hours_begin', 'tent_pad', 'tent_pad_width_feet',
    'independent_site_question',
];
const config = {
    unknown_token: '__LLAMA_UNKNOWN__',
    unanswered_token: '__LLAMA_UNANSWERED__',
    fields: keys.map(key => ({
        key,
        derived: false,
        type: key === 'type' ? 'select' : 'tri',
        counts_toward_completion: true,
        applicable_if: rules[key] || [],
    })),
};

const fieldHtml = key => (
    '<label class="contribution-field" data-report-field="' + key + '">'
    + '<select name="' + key + '">'
    + '<option value="__LLAMA_UNANSWERED__">Unanswered</option>'
    + '<option value="0">No</option>'
    + '<option value="1">Yes</option>'
    + '<option value="__LLAMA_UNKNOWN__">Unknown</option>'
    + '</select></label>'
);
const rawConfig = JSON.stringify(config).replace(/</g, '\\u003c');
const html = '<!doctype html><html><body>'
    + '<form class="place-report-form">'
    + '<script type="application/json" data-place-report-schema-config'
    + ' data-inventory-review="0">' + rawConfig + '</script>'
    + '<details class="contribution-section" open>'
    + '<div class="contribution-section-body">'
    + keys.map(fieldHtml).join('')
    + '</div></details>'
    + '</form>'
    + '</body></html>';
const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/place.php?id=17',
    runScripts: 'outside-only',
});
const {window} = dom;
window.CSS = window.CSS || {};
window.CSS.escape = value => String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
window.LlamaPlaceReportForm = {setupRecovery() {}};
window.eval(source);

const form = window.document.querySelector('.place-report-form');
const field = key => form.querySelector('[data-report-field="' + key + '"]');
const select = key => form.querySelector('select[name="' + key + '"]');
const change = (key, value) => {
    select(key).value = value;
    select(key).dispatchEvent(new window.Event('change', {bubbles: true}));
};

assert.equal(field('hookup_electric_service').hidden, true,
    'Unanswered Electric must hide Electric Service');
assert.equal(field('generator_prohibited').hidden, true,
    'Generator Restrictions unanswered must hide Prohibited');
assert.equal(field('generator_quiet_hours_begin').hidden, true,
    'Generator Quiet Hours start must remain hidden until parents match');
assert.equal(field('tent_pad_width_feet').hidden, true,
    'Tent Pad Width must remain hidden without Tent Pad Yes');
assert.equal(field('independent_site_question').hidden, false,
    'Independent question stays visible');

const typeControl = select('type');
const option = window.document.createElement('option');
option.value = 'developed-campground';
option.textContent = 'Developed Campground';
typeControl.append(option);
change('type', 'developed-campground');
assert.equal(field('hookup_electric').hidden, false,
    'Utility question is independent of the general hookup parent');
change('site_hookups_available', '0');
assert.equal(field('hookup_electric').hidden, false,
    'Broad Hookups No does not change independent Electric status');
assert.equal(field('hookup_electric_service').hidden, true,
    'Broad Hookups No hides approved Site Equipment details');
change('site_hookups_available', '1');
assert.equal(field('hookup_electric_service').hidden, false,
    'Broad Hookups Yes reveals Electric Service');
change('site_hookups_available', '__LLAMA_UNKNOWN__');
assert.equal(field('hookup_electric_service').hidden, true,
    'Unknown Hookups does not activate a Yes-only detail');

change('generator_restrictions', '1');
assert.equal(field('generator_prohibited').hidden, false,
    'Generator Restrictions Yes reveals the Prohibited follow-up');
change('generator_prohibited', '0');
assert.equal(field('generator_quiet_hours').hidden, false,
    'Generators not prohibited reveals Quiet Hours');
change('generator_quiet_hours', '1');
assert.equal(field('generator_quiet_hours_begin').hidden, false,
    'Matching nested generator parents reveal the start-time question');
change('generator_restrictions', '0');
assert.equal(field('generator_prohibited').hidden, true,
    'Restrictions No hides Prohibited question');
assert.equal(field('generator_quiet_hours').hidden, true,
    'Stale Prohibited No cannot reveal Quiet Hours when restrictions are hidden');
assert.equal(field('generator_quiet_hours_begin').hidden, true,
    'Stale Quiet Hours Yes cannot reveal a grandchild');
assert.equal(select('generator_quiet_hours').value, '1',
    'Hidden answers remain stored for future review');

change('generator_restrictions', '__LLAMA_UNKNOWN__');
assert.equal(field('generator_prohibited').hidden, true,
    'Unknown Restrictions does not trigger a Yes-only child');

change('tent_pad', '1');
assert.equal(field('tent_pad_width_feet').hidden, false,
    'Tent Pad Yes reveals dimensions');
change('tent_pad', '0');
assert.equal(field('tent_pad_width_feet').hidden, true,
    'Tent Pad No hides dimensions');

change('type', '0');
assert.equal(field('hookup_electric').hidden, true,
    'Inapplicable Place type hides Site utility questions');
assert.equal(field('hookup_electric_service').hidden, true,
    'An inapplicable parent cannot activate its child');

// A standalone Site has no editable Place-type or shared-amenity control.
// Read-only context must drive the same visibility and never enter FormData.
const contextual = new JSDOM('<form class="place-report-form" data-report-context=\'{"type":"developed-campground","amenity_wifi":"1"}\'>'
    + '<script type="application/json" data-place-report-schema-config>'
    + JSON.stringify({...config, fields: [
        {key:'type', type:'select', counts_toward_completion:false, applicable_if:[]},
        {key:'amenity_wifi', type:'tri', counts_toward_completion:false, applicable_if:[{field:'type',operator:'in',value:['developed-campground']}]},
        {key:'site_wifi_rating', type:'rating', applicable_if:[{field:'amenity_wifi',operator:'equals',value:'1'}]},
    ]}) + '</script>' + fieldHtml('site_wifi_rating') + '</form>', {runScripts:'outside-only'});
const cw = contextual.window;
cw.CSS = window.CSS;
cw.LlamaPlaceReportForm = {setupRecovery() {}};
cw.eval(source);
const cf = cw.document.querySelector('form');
const child = cf.querySelector('[data-report-field="site_wifi_rating"]');
assert.equal(child.hidden, false, 'Read-only inherited Yes reveals child');
cf.dataset.reportContext = JSON.stringify({type:'public-parking',amenity_wifi:'1'});
cf.dispatchEvent(new cw.Event('change',{bubbles:true}));
assert.equal(child.hidden, true, 'Stale inherited Yes is ignored on inapplicable type');
cf.dataset.reportContext = '{}';
cf.dispatchEvent(new cw.Event('change',{bubbles:true}));
assert.equal(child.hidden, true, 'Clearing selected parent clears its context');
assert.equal(new cw.FormData(cf).has('amenity_wifi'), false, 'Context is not submitted');
assert.equal(new cw.FormData(cf).has('type'), false, 'Parent type is not submitted');
cw.close();
window.close();

// Entire Place Update has a read-only type and must retain scope and
// applicability on target switches without sending hidden observations.
const placeUpdateDom = new JSDOM('<form class="place-report-form place-update-form" data-report-context=\'{"type":"developed-campground"}\'>'
    + '<input name="report_target_scope" value="place">'
    + '<div data-place-update-place-fields>'
    + '<script type="application/json" data-place-report-schema-config>'
    + JSON.stringify({...config, mode:'update-report', fields:[
        {key:'type', type:'select', report_scopes:['place'], applicable_if:[]},
        {key:'place_parent', type:'tri', report_scopes:['place'], applicable_if:[{field:'type',operator:'in',value:['developed-campground']}]},
        {key:'place_child', type:'tri', report_scopes:['place'], applicable_if:[{field:'place_parent',operator:'equals',value:'1'}]},
        {key:'site_only', type:'tri', report_scopes:['site'], applicable_if:[]},
    ]}) + '</script>'
    + fieldHtml('place_parent') + fieldHtml('place_child') + fieldHtml('site_only') + '</div></form>', {runScripts:'outside-only'});
const pw = placeUpdateDom.window;
pw.CSS = {escape:value=>String(value)};
pw.LlamaPlaceReportForm = {setupRecovery() {}};
pw.eval(source);
const pf = pw.document.querySelector('form');
const pc = key => pf.querySelector('[data-report-field="' + key + '"]');
const ps = key => pf.querySelector('[name="' + key + '"]');
const changePlace = (key,value) => {
    ps(key).value = value;
    ps(key).dispatchEvent(new pw.Event('change',{bubbles:true}));
};
assert.equal(pc('place_parent').hidden,false,'Published Place type drives visibility');
assert.equal(pc('place_child').hidden,true,'Unanswered parent hides child');
assert.equal(pc('site_only').hidden,true,'Site-only questions stay out of Entire Place');
changePlace('place_parent','1');
changePlace('place_child','1');
assert.equal(pc('place_child').hidden,false);
changePlace('place_parent','0');
assert.equal(pc('place_child').hidden,true);
assert.equal(new pw.FormData(pf).has('place_child'),false,'Hidden child must not post');
ps('report_target_scope').value = 'site';
pf.dispatchEvent(new pw.Event('llama:place-update-target-changed'));
ps('report_target_scope').value = 'place';
pf.dispatchEvent(new pw.Event('llama:place-update-target-changed'));
assert.equal(pc('place_child').hidden,true,'Returning to Place preserves dependencies');
changePlace('place_parent','1');
assert.equal(ps('place_child').value,'1','Visible child retains draft');
assert.equal(new pw.FormData(pf).get('place_child'),'1');
pw.close();

const siteLists = {
    landscape_details: ['rocky', 'wooded'],
    landscape_views: ['forest-woodland', 'mountain'],
    connectivity_services_tested: ['starlink', 'amazon_leo', 'other'],
};

async function testAdminSiteEditing(overrides = null) {
    const site = {id: 'campsite_record:5', site_id: 5, label: 'Site 5', imported_values: {tent_pad: '1'}, override_values: overrides};
    const catalog = {
        sites: [site],
        site_field_keys: ['tent_pad', 'tent_pad_width_feet', 'independent_site_question', 'pets_allowed', 'dogs_required_to_be_leashed', 'site_wifi_rating', ...Object.keys(siteLists)],
        site_shared_field_keys: ['pets_allowed', 'dogs_required_to_be_leashed'],
        place_shared_answers: {pets_allowed:'1'},
        place_context: {type:'developed-campground', amenity_wifi:'1'},
        place_field_keys: ['generator_restrictions'],
    };
    const adminConfig = {
        ...config,
        fields: [...config.fields, ...Object.keys(siteLists).map(key => ({key, type: 'multiselect'})),
            {key:'pets_allowed', type:'tri', applicable_if:[]},
            {key:'dogs_required_to_be_leashed', type:'tri', applicable_if:[{field:'pets_allowed',operator:'equals',value:'1'}]},
            {key:'amenity_wifi', type:'tri', applicable_if:[{field:'type',operator:'in',value:['developed-campground']}]},
            {key:'site_wifi_rating', type:'tri', applicable_if:[{field:'amenity_wifi',operator:'equals',value:'1'}]},
        ],
    };
    const listFields = Object.entries(siteLists).map(([key, options]) =>
        `<div data-report-field="${key}" data-place-report-multiselect>`
        + options.map(value => `<label data-place-report-multiselect-option><input type="checkbox" name="${key}[]" value="${value}"></label>`).join('')
        + '</div>'
    ).join('');
    const context = {place: {place_id: 17}, sites: {[site.id]: site}};
    const mapData = {
        place: {slug: 'test-place'},
        features: [],
        sites: [{id: site.id, label: site.label, latitude: 37.25, longitude: -107.75}],
    };
    const admin = new JSDOM(
        '<div data-admin-place-map-panel><div data-admin-place-map></div>'
        + '<a data-admin-place-map-edit></a>'
        + '<script type="application/json" data-admin-place-map-data>' + JSON.stringify(mapData) + '</script></div>'
        + '<form id="place-report" class="place-report-form">'
        + '<script type="application/json" data-place-report-schema-config>' + JSON.stringify(adminConfig) + '</script>'
        + '<script type="application/json" data-admin-place-campsites>' + JSON.stringify(catalog) + '</script>'
        + '<script type="application/json" data-admin-place-context>' + JSON.stringify(context) + '</script>'
        + '<details class="contribution-section" data-report-main-section="site">'
        + '<label data-admin-site-picker><select name="selected_campsite_id" data-admin-campsite-selector>'
        + '<option value="all-sites">All Sites</option><option value="" selected>Select a Site</option>'
        + '<option value="campsite_record:5"' + (overrides ? ' selected' : '') + '>Site 5</option>'
        + '</select></label><label data-admin-overwrite-site-panel hidden>'
        + '<input type="checkbox" role="switch" data-admin-overwrite-site-answers></label>'
        + keys.map(fieldHtml).join('') + listFields + fieldHtml('site_wifi_rating')
        + '</details>' + fieldHtml('pets_allowed') + fieldHtml('amenity_wifi')
        + '<fieldset data-admin-site-shared-questions>'
        + fieldHtml('pets_allowed').replace('name="pets_allowed"', 'name="admin_site_pets_allowed"')
        + fieldHtml('dogs_required_to_be_leashed').replace('name="dogs_required_to_be_leashed"', 'name="admin_site_dogs_required_to_be_leashed"')
        + '</fieldset><button type="button" data-place-report-admin-save>Save Place Report</button></form>',
        {url: 'https://admin.llamascout.com/place.php?id=17', runScripts: 'outside-only'}
    );
    const w = admin.window;
    try {
        w.CSS = {escape: window.CSS.escape};
        w.LlamaPlaceReportForm = {setupRecovery() {}};
        const points = [];
        const pins = [];
        const views = [];
        const map = {
            createPane() {},
            getPane: () => ({style: {}}),
            fitBounds() {},
            setView(point) { views.push(point); return map; },
            invalidateSize() {},
        };
        w.requestAnimationFrame = callback => callback();
        w.L = {
            map: () => map,
            control: {zoom: () => ({addTo() {}})},
            tileLayer: () => ({addTo() {}, bringToBack() {}}),
            latLngBounds: () => ({extend: point => points.push(point), isValid: () => points.length > 0}),
            circleMarker: point => {
                const pin = {
                    addTo: () => pin,
                    bindTooltip() {},
                    on: (event, handler) => { pin[event] = handler; },
                    getLatLng: () => point,
                    setStyle() {},
                    setRadius() {},
                };
                pins.push(pin);
                return pin;
            },
        };
        let saved;
        w.confirm = () => true;
        w.alert = () => {};
        w.fetch = async (_url, options) => {
            saved = options.body;
            return {ok: true, text: async () => '{"success":true}'};
        };
        w.eval(source);
        for (const script of ['place-report-save.js', 'place-map.js', 'place-context.js']) {
            w.eval(fs.readFileSync(path.resolve(__dirname, '../../js/admin', script), 'utf8'));
        }
        const get = selector => w.document.querySelector(selector);
        const question = key => get(`[data-report-field="${key}"]`);
        const input = key => get(`[name="${key}"]`);
        const update = (control, value) => {
            control.value = value;
            control.dispatchEvent(new w.Event('change', {bubbles: true}));
        };
        const picker = get('[data-admin-campsite-selector]');
        assert.equal(get('[data-admin-site-picker]').hidden, false, 'The Site picker is independent of questions and Place type');
        assert.equal(pins.length, 1, 'A saved Site point renders without any map feature geometry');
        if (overrides) {
            assert.equal(picker.value, site.id, 'Opening a direct Site link keeps the Site selected');
            assert.equal(input('tent_pad').value, '0', 'Direct Site links load existing answers');
            assert.equal(question('tent_pad_width_feet').hidden, true, 'Reload respects the saved parent answer');
            for (const [key, options] of Object.entries(siteLists)) {
                assert.deepEqual(Array.from(w.document.querySelectorAll(`[name="${key}[]"]:checked`), control => control.value), options,
                    `${key} restores every saved choice`);
            }
            get('[data-place-report-admin-save]').click();
            assert.deepEqual(JSON.parse(saved.get('admin_site_answers_json')), {}, 'Unchanged lists are not rewritten');
            await new Promise(resolve => setImmediate(resolve));
            return;
        }
        assert.equal(question('independent_site_question').hidden, true, 'Site-only questions wait for a target');
        assert.equal(input('independent_site_question').disabled, false, 'Questions are hidden without being locked');

        const overwritePanel = get('[data-admin-overwrite-site-panel]');
        const overwrite = get('[data-admin-overwrite-site-answers]');
        assert.equal(overwritePanel.hidden, true);
        update(picker, 'all-sites');
        assert.equal(question('independent_site_question').hidden, false, 'Bulk editing reveals Site questions');
        assert.equal(overwritePanel.hidden, false);
        assert.equal(overwrite.checked, false, 'Existing answers are protected by default');
        update(input('tent_pad'), '1');
        get('[data-place-report-admin-save]').click();
        assert.equal(saved.has('selected_campsite_id'), false, 'All Sites is not a Site ID');
        assert.deepEqual(JSON.parse(saved.get('admin_bulk_site_answers_json')), {answers: {tent_pad: '1'}, overwrite: false});
        await new Promise(resolve => setImmediate(resolve));
        overwrite.checked = true;
        update(input('tent_pad'), '0');
        get('[data-place-report-admin-save]').click();
        assert.deepEqual(JSON.parse(saved.get('admin_bulk_site_answers_json')), {answers: {tent_pad: '0'}, overwrite: true});
        await new Promise(resolve => setImmediate(resolve));

        pins[0].click();
        await Promise.resolve();
        assert.equal(picker.value, site.id, 'Tapping a point-only Site selects its canonical report');
        assert.equal(overwritePanel.hidden, true, 'Single-Site editing has no overwrite toggle');
        assert.deepEqual(Array.from(views.at(-1)), [37.25, -107.75], 'Map centers on the selected Site point');
        assert.equal(new URL(get('[data-admin-place-map-edit]').href).searchParams.get('site_id'), '5');
        assert.equal(get('[data-report-main-section="site"]').open, true, 'Selecting a map Site opens its report section');
        assert.equal(question('independent_site_question').hidden, false, 'A selected Site reveals its own questions');
        assert.equal(input('tent_pad').disabled, false, 'Imported Site answers are editable');
        const sitePets = input('admin_site_pets_allowed');
        const leash = input('admin_site_dogs_required_to_be_leashed');
        const leashQuestion = leash.closest('[data-report-field]');
        assert.equal(sitePets.value,'1','Site uses inherited pets policy');
        assert.equal(leashQuestion.hidden,false,'Site inherited Yes reveals leash policy');
        assert.equal(question('site_wifi_rating').hidden,false,'Published WiFi context reveals Site rating');
        update(sitePets,'0');
        assert.equal(leashQuestion.hidden,true,'Site No hides child independently of Place control');
        update(sitePets,'1');
        assert.equal(leashQuestion.hidden,false,'Site Yes restores dependent question');
        update(leash,'1');
        update(sitePets,'0');
        assert.equal(leashQuestion.hidden,true);
        // Restore the original parent and clear the draft before other tests.
        update(sitePets,'1');
        update(leash,'');
        assert.equal(input('pets_allowed').value,'__LLAMA_UNANSWERED__','Site does not edit Place control');
        assert.equal(input('generator_restrictions').disabled, false, 'Place answers stay editable with a Site selected');
        assert.equal(input('tent_pad').value, '0', 'Successful bulk overwrite stays visible when reopening an individual Site');
        assert.equal(question('tent_pad_width_feet').hidden, true, 'Bulk-updated parent No keeps its child hidden');
        update(input('tent_pad'), '1');
        assert.equal(question('tent_pad_width_feet').hidden, false, 'Changing the parent back to Yes reveals its child');
        update(input('tent_pad'), '0');
        assert.equal(question('tent_pad_width_feet').hidden, true, 'Parent change still hides its child');
        update(input('generator_restrictions'), '1');
        for (const key of Object.keys(siteLists)) {
            for (const checkbox of w.document.querySelectorAll(`[name="${key}[]"]`)) checkbox.click();
        }
        get('[data-place-report-admin-save]').click();
        assert.equal(saved.get('selected_campsite_id'), site.id);
        const savedAnswers = JSON.parse(saved.get('admin_site_answers_json'));
        assert.deepEqual(savedAnswers, {...siteLists},
            'A previously bulk-saved Site answer is not rewritten during an unrelated individual Site edit');
        assert.equal(saved.get('generator_restrictions'), '1', 'Place answer remains in the Place payload');
        await new Promise(resolve => setImmediate(resolve));
        update(picker, '');
        assert.equal(question('independent_site_question').hidden, true, 'Clearing the Site hides its questions again');
        // The mock response represents a successful bulk write without a
        // real database between the two DOM sessions. Carry that approved
        // bulk override into the next direct-link fixture as persisted state.
        return {tent_pad: '0', ...savedAnswers};
    } finally {
        w.close();
    }
}

async function testClosureDates() {
    const keys = ['season_begins', 'season_ends'];
    const dates = ['12-15', '10-31'];
    const options = values => '<option value="">Select</option>' + values.map(value => `<option>${value}</option>`).join('');
    const dom = new JSDOM('<form>' + keys.map((key, index) =>
        `<div data-season-picker><select data-season-month>${options(['02', '10', '12'])}</select>`
        + `<select data-season-day>${options(['15', '29', '31'])}</select>`
        + `<input type="hidden" name="${key}" value="${dates[index]}" data-season-value></div>`
    ).join('') + '</form>', {runScripts: 'outside-only'});
    const w = dom.window;
    try {
        w.eval(fs.readFileSync(path.resolve(__dirname, '../../js/place-report-season-picker.js'), 'utf8'));
        await new Promise(resolve => w.document.addEventListener('DOMContentLoaded', resolve, {once: true}));
        const form = w.document.querySelector('form');
        assert.deepEqual(Array.from(new w.FormData(form).values()), dates, 'Opening the report preserves both dates');
        const month = form.querySelector('[data-season-month]');
        const day = form.querySelector('[data-season-day]');
        assert.equal(month.value, '12');
        assert.equal(day.value, '15');
        form.elements.season_begins.value = '02-29';
        form.dispatchEvent(new w.Event('change'));
        assert.equal(month.value, '02', 'Scoped date changes update the picker');
        assert.equal(day.value, '29');
        day.value = '15';
        day.dispatchEvent(new w.Event('change', {bubbles: true}));
        assert.equal(new w.FormData(form).get('season_begins'), '02-15', 'Changing a picker submits the new date');
    } finally {
        w.close();
    }
}


async function testGuidedPlaceCards() {
    const dom = new JSDOM(`<form id="add-place-main-form">
        <div data-scout-card-navigation hidden>
          <button type="button" data-scout-card-toggle></button><div data-scout-card-progress-wrap><progress data-scout-card-meter max="100"></progress><span data-scout-card-progress></span></div>
          <h2 data-scout-card-heading tabindex="-1"></h2>
        </div>
        <details data-report-main-section="place"><summary>Place</summary><details data-report-subsection="basic"><summary>Basic</summary>
          <div class="contribution-grid">
            <label data-report-field="visited_at"><span class="place-report-field-label">Date visited</span><input name="visited_at" value="2026-11-26"></label>
            <div data-report-field="parent"><span class="place-report-field-label"><span>Parent</span></span><select name="parent"><option value="">Select...</option><option value="1">Yes</option><option value="0">No</option></select></div>
            <div data-report-field="child" hidden><input name="child" value="retained"></div>
            <div data-scout-card-group="Location"><input name="coordinates" value="37,-107"></div><div data-scout-card-group="Location">Helper</div>
          </div>
        </details></details>
        <details data-contribution-place-photo-panel open><summary>Photos</summary><input name="photos_json" value="[]"></details>
        <div data-scout-card-step="Review and submit"><button type="submit">Submit</button></div>
        <div data-scout-card-controls hidden>
          <button type="button" data-scout-card-back></button><span data-scout-card-gesture-help></span><button type="button" data-scout-card-next></button>
        </div>
    </form>`, {runScripts:'outside-only'});
    const w = dom.window, d = w.document;
    try {
        // Exercise the production presentation adapter independently of the
        // shared questionnaire engine (covered by the tests above).
        const marker = '// Guided Add Place presentation is deliberately separate';
        const start = source.indexOf(marker);
        assert.ok(start >= 0);
        w.eval(source.slice(start));
        await new Promise(resolve => d.addEventListener('DOMContentLoaded',resolve,{once:true}));
        const q = s => d.querySelector(s), form = q('form');
        const toggle = q('[data-scout-card-toggle]'), next = q('[data-scout-card-next]');
        const child = q('[data-report-field="child"]'), parent = q('[data-report-field="parent"]');
        const heading = q('[data-scout-card-heading]'), progress = q('[data-scout-card-progress]');
        assert.equal(q('[data-scout-card-navigation]').hidden,false);
        assert.equal(form.classList.contains('is-scout-card-view'),true,'Guided view opens by default');
        assert.equal(toggle.textContent,'Full questionnaire');
        assert.equal(heading.textContent,'Date visited');
        assert.equal(progress.textContent,'0%');
        assert.ok(q('[data-scout-card-controls]').compareDocumentPosition(parent) & w.Node.DOCUMENT_POSITION_PRECEDING,'Navigation follows questions');
        const initial = Array.from(new w.FormData(form).entries());
        next.click();
        assert.equal(heading.textContent,'Parent');
        assert.equal(parent.classList.contains('is-scout-card-offstage'),false);
        assert.deepEqual(Array.from(new w.FormData(form).entries()),initial,'Card display must not change serialization');
        const select = parent.querySelector('select');
        assert.equal(select.classList.contains('is-scout-choice-source'),true);
        assert.equal(parent.querySelectorAll('.scout-card-choice').length,3,'All choices visible without dropdown');
        const yes = [...parent.querySelectorAll('.scout-card-choice')].find(button => button.textContent === 'Yes');
        yes.click();
        assert.equal(new w.FormData(form).get('parent'),'1','Visible choices write to original select');
        assert.equal(yes.getAttribute('aria-pressed'),'true');
        const pointer = (target, name, x, y) => {
            const event = new w.MouseEvent(name,{bubbles:true,cancelable:true,clientX:x,clientY:y,button:0});
            Object.defineProperties(event,{pointerId:{value:1},isPrimary:{value:true}});
            target.dispatchEvent(event);
        };
        const swipe = (target, x, y) => {
            pointer(target,'pointerdown',100,100);
            pointer(target,'pointermove',x,y);
            pointer(target,'pointerup',x,y);
        };
        swipe(yes,100,30);
        assert.equal(select.value,'1','Vertical swipe never changes the choice');
        assert.equal(heading.textContent,'Parent','Vertical swipe never changes the card');
        swipe(yes,80,100);
        assert.equal(heading.textContent,'Parent','Short drags snap back without navigating');
        const no = [...parent.querySelectorAll('.scout-card-choice')].find(button => button.textContent === 'No');
        pointer(no,'pointerdown',100,100);
        pointer(no,'pointermove',170,100);
        assert.equal(form.style.getPropertyValue('--scout-card-drag-x'),'70px','The entire card visibly follows the pointer');
        pointer(no,'pointerup',170,100);
        no.dispatchEvent(new w.MouseEvent('click',{bubbles:true,cancelable:true,detail:1}));
        assert.equal(select.value,'1','Dragging from an answer cannot select it');
        assert.equal(heading.textContent,'Date visited','Right swipe returns to previous card');
        assert.equal(form.style.getPropertyValue('--scout-card-drag-x'),'','Card returns to resting position');
        next.click();
        const freshNo = [...parent.querySelectorAll('.scout-card-choice')].find(button => button.textContent === 'No');
        const freshYes = [...parent.querySelectorAll('.scout-card-choice')].find(button => button.textContent === 'Yes');
        swipe(freshNo,120,100);
        freshNo.dispatchEvent(new w.MouseEvent('click',{bubbles:true,cancelable:true,detail:1}));
        assert.equal(select.value,'1','A short drag from a choice suppresses the trailing click');
        pointer(freshNo,'pointerdown',100,100);
        pointer(freshNo,'pointerup',100,100);
        freshNo.dispatchEvent(new w.MouseEvent('click',{bubbles:true,cancelable:true,detail:1}));
        assert.equal(select.value,'0','A normal answer tap still works');
        freshYes.click();
        child.hidden = false;
        await Promise.resolve();
        assert.equal(progress.textContent,'20%');
        assert.equal(q('[data-scout-card-meter]').value,20);
        next.click();
        assert.equal(child.classList.contains('is-scout-card-offstage'),false);
        child.hidden = true;
        await Promise.resolve();
        assert.equal(heading.textContent,'Location');
        assert.ok([...d.querySelectorAll('[data-scout-card-group]')].every(node => !node.classList.contains('is-scout-card-offstage')));
        const location = q('[data-scout-card-group]');
        swipe(location,25,100);
        assert.equal(heading.textContent,'Photos','Left swipe from card content advances');
        swipe(q('[data-contribution-place-photo-panel]'),170,100);
        assert.equal(heading.textContent,'Location','Right swipe from card content goes back');
        pointer(location,'pointerdown',100,100);
        pointer(location,'pointermove',25,100);
        pointer(location,'pointercancel',25,100);
        assert.equal(heading.textContent,'Location','Cancelled drag never navigates');
        assert.equal(form.classList.contains('is-scout-card-dragging'),false);
        toggle.click();
        assert.equal(form.classList.contains('is-scout-card-view'),false);
        assert.equal(child.hidden,true,'Full view preserves canonical eligibility');
        assert.equal(select.classList.contains('is-scout-choice-source'),false,'Full view restores original dropdown');
        assert.equal(new w.FormData(form).get('parent'),'1','Full view retains choice');
        assert.equal(q('[name="child"]').value,'retained');
        assert.equal(q('[data-report-main-section]').open,false,'Restore expanded sections');
        toggle.click();
        q('[name="visited_at"]').dispatchEvent(new w.Event('invalid'));
        assert.equal(form.classList.contains('is-scout-card-view'),false,'Native validation returns to full view');
    } finally { w.close(); }
}

async function testGuidedLocateMe() {
    const dom = new JSDOM(`<form id="add-place-main-form">
        <div data-scout-card-navigation hidden>
            <button type="button" data-scout-card-toggle></button>
            <div data-scout-card-progress-wrap><progress data-scout-card-meter max="100"></progress><span data-scout-card-progress></span></div>
            <h2 data-scout-card-heading tabindex="-1"></h2>
        </div>
        <details data-report-main-section="place" open><summary>Place</summary>
            <details data-report-subsection="location" open><summary>Location</summary>
                <div class="contribution-grid">
                    <div data-scout-card-group="Location"><button type="button" data-locate-place>Locate me</button></div>
                    <div data-scout-card-group="Location">
                        <input data-coordinate-input name="coordinates">
                        <input type="hidden" data-location-field="latitude" name="latitude">
                        <input type="hidden" data-location-field="longitude" name="longitude">
                        <button type="button" data-search-coordinates>Search</button>
                    </div>
                    <div data-scout-card-group="Location" data-location-status>Tap Locate me.</div>
                    <div data-report-field="city"><input data-location-field="city" name="city"></div>
                    <div data-report-field="elevation_feet"><input data-location-field="elevation_feet" name="elevation_feet"></div>
                </div>
            </details>
        </details>
        <div data-scout-card-controls hidden>
            <button type="button" data-scout-card-back></button><span data-scout-card-gesture-help></span><button type="button" data-scout-card-next></button>
        </div>
    </form>`, {runScripts:'outside-only',url:'https://llamascout.com/add-place.php'});
    const w = dom.window, d = w.document;
    try {
        const requests = [], lookups = [];
        const results = [];
        const position = (latitude, longitude, accuracy) => ({coords:{latitude,longitude,accuracy}});
        let lookupFails = false, elevation = 6562;
        Object.defineProperty(w.navigator, 'geolocation', {value:{
            getCurrentPosition(success, failure, options) {
                requests.push(options);
                const result = results.shift();
                if (result?.code) failure(result);
                else success(result);
            }
        }});
        w.fetch = async url => {
            lookups.push(url);
            if (lookupFails) throw new Error('Offline');
            return {ok:true,text:async()=>JSON.stringify({success:true,location:{city:'Example city',elevation_feet:elevation}})};
        };
        w.eval(fs.readFileSync(path.resolve(__dirname,'../../js/add-place-location.js'),'utf8'));
        w.eval(source.slice(source.indexOf('// Guided Add Place presentation is deliberately separate')));
        await new Promise(resolve=>d.addEventListener('DOMContentLoaded',resolve,{once:true}));
        const q = s=>d.querySelector(s), form = q('form');
        const locate = q('[data-locate-place]'), status = q('[data-location-status]');
        const settle = () => new Promise(resolve=>setImmediate(resolve));
        assert.equal(form.classList.contains('is-scout-card-view'),true);
        assert.equal(q('[data-scout-card-heading]').textContent,'Location');
        results.push(position(37.25,-107.12,12));
        locate.click();
        assert.equal(locate.disabled,true,'Location controls are busy while resolving');
        await settle();
        assert.equal(locate.disabled,false);
        assert.equal(q('[data-coordinate-input]').value,'37.2500000, -107.1200000');
        assert.equal(new w.FormData(form).get('latitude'),'37.2500000');
        assert.equal(new w.FormData(form).get('longitude'),'-107.1200000');
        assert.equal(q('[name="city"]').value,'Example city');
        assert.equal(q('[name="elevation_feet"]').value,'6562');
        assert.equal(status.classList.contains('is-success'),true);
        assert.equal(requests.length,1);
        assert.equal(requests[0].enableHighAccuracy,true);
        assert.equal(lookups.length,1);
        q('[data-scout-card-next]').click();
        q('[data-scout-card-back]').click();
        assert.equal(q('[data-coordinate-input]').value,'37.2500000, -107.1200000','Coordinates survive card navigation');
        elevation = null;
        results.push({code:3},position(37.26,-107.13,850));
        locate.click();
        await settle();
        assert.equal(requests.length,3);
        assert.equal(requests[2].enableHighAccuracy,false,'Timeout retries standard location');
        assert.match(status.textContent,/850 m/);
        assert.match(status.textContent,/Elevation is unavailable/);
        assert.equal(q('[name="elevation_feet"]').value,'','Missing elevation remains blank');
        assert.equal(status.classList.contains('is-warning'),true);
        assert.equal(new w.FormData(form).get('latitude'),'37.2600000');
        results.push({code:1});
        locate.click();
        await settle();
        assert.equal(requests.length,4,'Permission denial is never retried');
        assert.match(status.textContent,/permission was denied/);
        assert.equal(locate.disabled,false);
        assert.equal(new w.FormData(form).get('latitude'),'37.2600000','Failed GPS does not erase existing coordinates');
        assert.equal(lookups.length,2);
        lookupFails = true;
        results.push({code:2},position(37.27,-107.14,20));
        locate.click();
        await settle();
        assert.equal(requests.length,6,'Unavailable position retries standard location');
        assert.equal(status.classList.contains('is-warning'),true,'Lookup failure does not imply GPS failure');
        assert.equal(new w.FormData(form).get('latitude'),'37.2700000');
        assert.equal(locate.disabled,false);
        q('[data-scout-card-toggle]').click();
        lookupFails = false;
        elevation = 0;
        results.push(position(37.28,-107.15,10));
        locate.click();
        await settle();
        assert.equal(form.classList.contains('is-scout-card-view'),false);
        assert.equal(new w.FormData(form).get('latitude'),'37.2800000','Full view uses the same location handler');
        assert.equal(q('[name="elevation_feet"]').value,'0','Sea level is a valid elevation');
        assert.equal(status.classList.contains('is-success'),true);
    } finally { w.close(); }
}

testAdminSiteEditing().then(testAdminSiteEditing).then(testClosureDates).then(testGuidedPlaceCards).then(testGuidedLocateMe).then(() => {
    console.log('PASS: closure dates and Site selections survive reload, parent-child visibility and scoped saves');
}).catch(error => {
    console.error(error);
    process.exitCode = 1;
});
