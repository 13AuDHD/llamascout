'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const script = fs.readFileSync(
    path.resolve(__dirname, '../../js/admin/place-report-save.js'), 'utf8'
);
const unknown = '__LLAMA_UNKNOWN__';
const unanswered = '__LLAMA_UNANSWERED__';
const keys = [
    'amenity_fire_ring',
    'amenity_grill',
    'amenity_picnic_table',
    'amenity_bear_box',
    'site_potable_water',
    'amenity_lantern_post',
];
const source = {
    amenity_fire_ring: '1',
    amenity_grill: '1',
    amenity_picnic_table: '1',
    amenity_bear_box: '0',
};
const sites = [
    {id: 'campsite_record:1', label: 'Site 1', imported_values: {...source, site_potable_water: '1'}, override_values: {}},
    {id: 'campsite_record:2', label: 'Site 2', imported_values: {...source, site_potable_water: '0'}, override_values: {}},
    {id: 'campsite_record:3', label: 'Site 3', imported_values: {...source, amenity_grill: '0'}, override_values: {amenity_grill: '1', amenity_lantern_post: unknown}},
];
const config = {
    sites,
    site_field_keys: keys,
    place_field_keys: [],
    unknown_token: unknown,
    unanswered_token: unanswered,
};
const field = (key) => (
    '<label class="contribution-field" data-report-field="' + key + '">'
    + '<span>' + key + '</span>'
    + '<input type="hidden" name="' + key + '" value="' + unanswered + '">'
    + '<input type="radio" name="' + key + '" value="' + unknown + '">'
    + '<input type="radio" name="' + key + '" value="1">'
    + '<input type="radio" name="' + key + '" value="0">'
    + '</label>'
);
const html = '<!doctype html><html><body>'
    + '<form id="place-report" method="post">'
    + '<input type="hidden" name="place_id" value="42">'
    + '<input type="hidden" name="csrf_token" value="csrf">'
    + '<input type="hidden" name="place_admin_action" value="save-report">'
    + '<input type="hidden" name="photos_json" value="[]">'
    + '<script type="application/json" data-admin-place-campsites>'
    + JSON.stringify(config) + '</script>'
    + '<details data-report-main-section="site">'
    + '<label data-admin-site-picker>'
    + '<select name="selected_campsite_id" data-admin-campsite-selector>'
    + '<option value="" selected>Select a Site</option>'
    + '<option value="all-sites">All Sites</option>'
    + sites.map(s => '<option value="' + s.id + '">' + s.label + '</option>').join('')
    + '</select></label>'
    + '<label data-admin-overwrite-site-panel hidden>'
    + '<input type="checkbox" data-admin-overwrite-site-answers>'
    + 'Overwrite existing Site answers</label>'
    + keys.map(field).join('')
    + '</details>'
    + '<div class="admin-place-report-savebar">'
    + '<button type="button" data-place-report-admin-save>Save Place Report</button>'
    + '</div></form></body></html>';

const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/place.php?id=42',
    runScripts: 'outside-only',
});
const w = dom.window;
const d = w.document;
const form = d.querySelector('#place-report');
const query = selector => d.querySelector(selector);
const selected = key => {
    const radio = d.querySelector('input[type="radio"][name="' + key + '"]:checked');
    return radio?.value ?? '';
};
const choose = (key, value) => {
    const radio = query('input[type="radio"][name="' + key + '"][value="' + value + '"]');
    assert.ok(radio, 'Missing test control for ' + key);
    radio.checked = true;
    radio.dispatchEvent(new w.Event('change', {bubbles: true}));
};
const changeTarget = value => {
    const select = query('[data-admin-campsite-selector]');
    select.value = value;
    select.dispatchEvent(new w.Event('change', {bubbles: true}));
};
const settled = () => new Promise(resolve => setTimeout(resolve, 70));

let requests = [];
let alerts = [];
w.confirm = () => true;
w.alert = message => alerts.push(String(message));
w.fetch = async (url, {body, method}) => {
    assert.equal(method, 'POST');
    assert.ok(body instanceof w.FormData);
    const payload = JSON.parse(body.get('admin_bulk_site_answers_json') || '{}');
    requests.push({url, payload, siteTarget: body.get('selected_campsite_id')});
    return {
        ok: true,
        text: async () => JSON.stringify({
            success: true,
            bulk_sites_updated: payload.overwrite ? 3 : 1,
            bulk_answers_saved: payload.overwrite ? 3 : 1,
            photo_total: 0,
        }),
    };
};
w.eval(script);

(async () => {
    changeTarget('all-sites');
    assert.equal(query('[data-admin-overwrite-site-panel]').hidden, false,
        'All Sites must expose the overwrite control');
    for (const key of ['amenity_fire_ring', 'amenity_grill', 'amenity_picnic_table']) {
        assert.equal(selected(key), '1', key + ' should prefill Yes on All Sites');
    }
    assert.equal(selected('amenity_bear_box'), '0', 'Shared No must be selected, not blank');
    assert.equal(selected('site_potable_water'), '', 'Mixed Site answers must not claim one answer');
    assert.ok(query('[data-admin-bulk-mixed-note="site_potable_water"]'),
        'Mixed Site answers need a visible explanation');
    assert.equal(selected('amenity_lantern_post'), '', 'Unanswered and Unknown together are mixed');
    assert.ok(query('[data-admin-bulk-mixed-note="amenity_lantern_post"]'));
    assert.ok(query('[data-admin-bulk-summary]').textContent.includes('3 Sites'));

    // Switching to one Site must show its own value, with no leaked mixed label.
    changeTarget('campsite_record:2');
    assert.equal(selected('site_potable_water'), '0');
    assert.equal(query('[data-admin-bulk-mixed-note]'), null);
    changeTarget('all-sites');
    assert.equal(selected('amenity_fire_ring'), '1');

    // Overwrite a shared Yes; submit ONLY that explicitly changed answer.
    choose('amenity_fire_ring', '0');
    assert.equal(query('[data-admin-bulk-mixed-note="amenity_fire_ring"]'), null);
    query('[data-admin-overwrite-site-answers]').checked = true;
    query('[data-place-report-admin-save]').click();
    await settled();
    assert.equal(requests.length, 1, 'Bulk editor should submit exactly once');
    assert.deepEqual(requests[0].payload, {
        answers: {amenity_fire_ring: '0'},
        overwrite: true,
    }, 'Untouched prefilled answers must never be included in bulk payload');
    assert.equal(requests[0].siteTarget, null, 'All Sites must not be sent as one Site ID');
    assert.equal(query('[data-admin-campsite-selector]').value, 'all-sites',
        'Bulk save must keep the All Sites selection');
    assert.equal(selected('amenity_fire_ring'), '0',
        'Shared No must remain visible immediately after save');
    assert.equal(selected('amenity_grill'), '1', 'Unchanged facts must remain visible');
    changeTarget('campsite_record:1');
    assert.equal(selected('amenity_fire_ring'), '0', 'Saved change must be reflected for individual Sites');
    changeTarget('all-sites');

    // With overwrite off, existing imported/approved values stay protected.
    choose('amenity_fire_ring', '1');
    query('[data-place-report-admin-save]').click();
    await settled();
    assert.equal(requests.length, 2);
    assert.equal(requests[1].payload.overwrite, false);
    assert.deepEqual(requests[1].payload.answers, {amenity_fire_ring: '1'});
    assert.equal(selected('amenity_fire_ring'), '0',
        'Fill-unanswered mode must not silently overwrite known Site answers');

    // Mixed answers can be filled only where no value currently exists.
    choose('site_potable_water', '1');
    assert.equal(query('[data-admin-bulk-mixed-note="site_potable_water"]'), null,
        'Changing a mixed answer should clear its old mixed-state warning');
    query('[data-place-report-admin-save]').click();
    await settled();
    assert.equal(requests.length, 3);
    assert.deepEqual(requests[2].payload, {
        answers: {site_potable_water: '1'},
        overwrite: false,
    });
    assert.equal(selected('site_potable_water'), '',
        'After fill-only save, genuine differences must still appear as mixed');
    assert.ok(query('[data-admin-bulk-mixed-note="site_potable_water"]'));

    // All prefilled inputs now reflect saved values, but none is marked as an edit.
    query('[data-place-report-admin-save]').click();
    await settled();
    assert.equal(requests.length, 3, 'Saving unchanged prefilled answers must not write');
    assert.ok(alerts.some(x => x.includes('No Site answers were changed')));

    dom.window.close();
    console.log('PASS: All Sites common Yes/No prefill, mixed/Unknown, per-Site switching, '
        + 'explicit-only bulk writes, overwrite rules and persistence after save');
})().catch(error => {
    dom.window.close();
    console.error(error);
    process.exitCode = 1;
});
