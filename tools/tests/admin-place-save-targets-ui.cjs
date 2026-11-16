'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const code = fs.readFileSync(path.join(__dirname, '../../js/admin/place-report-save.js'), 'utf8');
const site = {id: 'campsite_record:12', label: 'Site 12', imported_values: {}, override_values: {}};
const config = {sites: [site], site_field_keys: [], place_field_keys: [],
    unknown_token: '__LLAMA_UNKNOWN__', unanswered_token: '__LLAMA_UNANSWERED__'};
const html = [
    '<!doctype html><body><div data-contribution-tabs>',
    '<nav data-contribution-picker>',
    '<button type="button" data-contribution-mode="place" aria-pressed="true">Place</button>',
    '<button type="button" data-contribution-mode="site" aria-pressed="false">Site</button>',
    '<button type="button" data-contribution-mode="facility" aria-pressed="false">Facility</button>',
    '</nav>',
    '<form id="place-report" action="/place.php?id=42">',
    '<input name="place_id" value="42">',
    '<input name="csrf_token" value="csrf">',
    '<input name="place_admin_action" value="save-report">',
    '<input name="photos_json" value="[]">',
    '<input name="selected_area_id" value="map_feature:34">',
    '<input name="admin_area_answers_json" value="{}">',
    '<input name="site_coordinates" value="37.25222, -107.21920">',
    '<input name="admin_site_coordinates_changed" value="1">',
    '<script type="application/json" data-admin-place-campsites>',
    JSON.stringify(config), '</script>',
    '<details data-report-main-section="site">',
    '<select name="selected_campsite_id" data-admin-campsite-selector>',
    '<option value="">Choose Site</option>',
    '<option value="campsite_record:12" selected>Site 12</option>',
    '</select></details>',
    '<div class="admin-place-report-savebar">',
    '<button type="button" data-place-report-admin-save>',
    '<span data-admin-save-report-label>Save Place Report</span>',
    '</button></div></form></div></body>',
].join('');
const dom = new JSDOM(html, {
    runScripts: 'outside-only',
    url: 'https://admin.llamascout.com/place.php?id=42'
});
const {window: w} = dom;
const doc = w.document;
const form = doc.querySelector('#place-report');
const buttons = [...doc.querySelectorAll('[data-contribution-mode]')];
const requests = [];
const settle = () => new Promise(resolve => setTimeout(resolve, 80));

w.fetch = async (url, {body, headers}) => {
    assert.equal(headers['X-Llama-Admin-Save'], 'place-report');
    requests.push(Object.fromEntries(body.entries()));
    return {
        ok: true,
        text: async () => JSON.stringify({
            success: true,
            photos_added: 0,
            photo_total: 0,
            bulk_sites_updated: 0,
            bulk_answers_saved: 0
        })
    };
};
w.eval(code);
const save = async mode => {
    for (const button of buttons) {
        button.setAttribute('aria-pressed', String(button.dataset.contributionMode === mode));
    }
    doc.querySelector('[data-place-report-admin-save]').click();
    await settle();
    assert.equal(requests.length, mode === 'place' ? 1 : (mode === 'site' ? 2 : 3));
    return requests[requests.length - 1];
};

(async () => {
    let payload = await save('place');
    assert.equal(payload.admin_report_mode, 'place');
    assert.equal(payload.selected_campsite_id, undefined, 'Place save cannot target old Site');
    assert.equal(payload.selected_area_id, undefined, 'Place save cannot target old Area');
    assert.equal(payload.admin_area_answers_json, undefined);
    assert.equal(payload.site_coordinates, undefined);
    assert.equal(payload.admin_site_coordinates_changed, undefined);

    payload = await save('site');
    assert.equal(payload.admin_report_mode, 'site');
    assert.equal(payload.selected_campsite_id, 'campsite_record:12');
    assert.equal(payload.admin_area_answers_json, undefined);
    assert.equal(payload.selected_area_id, undefined);
    assert.equal(payload.admin_site_coordinates_changed, '1');
    assert.equal(payload.site_coordinates, '37.25222, -107.21920');

    payload = await save('facility');
    assert.equal(payload.admin_report_mode, 'facility');
    assert.equal(payload.selected_campsite_id, undefined);
    assert.equal(payload.admin_site_coordinates_changed, undefined);
    assert.equal(payload.site_coordinates, undefined);
    assert.equal(payload.selected_area_id, 'map_feature:34');
    assert.equal(payload.admin_area_answers_json, '{}');

    dom.window.close();
    console.log('PASS: Admin Place/Site/Facility saves isolate targets and keep GPS in Site-only submissions.');
})().catch(error => {
    dom.window.close();
    console.error(error);
    process.exitCode = 1;
});
