'use strict';

// The selected Area must be as durable and self-contained as a selected Site:
// URL -> record ID -> canonical Area API -> Area answers -> Area update form.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const root = path.resolve(__dirname, '../..');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const explorerJs = read('js/place-explorer.js');
const reportJs = read('js/place-campsites.js');
const updatePhp = read('account/update-place.php');
const areaApi = read('api/place-area-report.php');
const hero = read('partials/place/hero.php');
const saveService = read('app/admin-place-areas.php');

assert.match(hero, /data-place-suggest-update/);
assert.match(updatePhp, /llama_report_verified_target\(/,
    'The Area update link must be verified against the Place before use');
assert.match(updatePhp, /\$reportTargetScope = 'area'/);
assert.match(updatePhp, /\$reportTargetSource = 'map_feature'/);
assert.match(updatePhp, /\$reportTargetId = \(int\) \$verifiedArea\['id'\]/);
assert.match(areaApi, /llama_report_verified_target\(\s*\$db, \$placeId, 'area', 'map_feature', \$areaId/,
    'Area API must use canonical Area ownership, not the Place record');
assert.match(saveService, /admin_place_save_area_answers\(/);
assert.match(saveService, /llama_scoped_report_save\(/,
    'Area saves must be targeted at scoped canonical answers');

function setupExplorer(url) {
    const dom = new JSDOM(`<!doctype html><body>
        <div class="place-map-card" data-explorer-name="North Mills River"
             data-explorer-category="camping" data-explorer-site-count="2"></div>
        <a data-place-suggest-update
           href="https://account.llamascout.com/update-place.php?slug=north-mills-river">Suggest Update</a>
        <section data-stay-options>
            <div data-stay-browser-slot></div>
            <button type="button" data-stay-area-id="55"
                    data-stay-area-kind="facility" data-stay-area-name="West Vault Toilets">West Vault Toilets</button>
            <button type="button" data-stay-area-id="56"
                    data-stay-area-kind="facility" data-stay-area-name="East Showers">East Showers</button>
        </section>
        <section class="scout-report"></section>
    </body>`, { url, runScripts: 'outside-only' });
    const w = dom.window;
    w.CSS ||= {};
    w.CSS.escape ||= value => String(value);
    const map = {
        eachLayer() {}, on() {}, closePopup() {}, setView() {}, fitBounds() {},
        getMaxZoom() { return 18; },
    };
    w.L = { Marker: class Marker {} };
    w.LlamaScoutMap = { map };
    w.LlamaScoutCampsiteBrowser = { clearSelection() {}, selectSite() {} };
    const events = [];
    w.addEventListener('llama:explorer-selection', e => events.push(e.detail));
    w.eval(explorerJs);
    const href = () => new URL(w.location.href);
    const update = () => new URL(w.document.querySelector('[data-place-suggest-update]').href);
    return { dom, w, events, href, update,
        row: id => w.document.querySelector(`[data-stay-area-id="${id}"]`) };
}

(async () => {
    const ctx = setupExplorer(
        'https://llamascout.com/place.php?slug=north-mills-river&season=2'
    );
    assert.equal(ctx.href().searchParams.has('area'), false);
    ctx.row(55).click();
    await Promise.resolve();
    assert.equal(ctx.href().searchParams.get('area'), '55');
    assert.equal(ctx.href().searchParams.has('site'), false);
    assert.equal(ctx.href().searchParams.get('season'), '2');
    assert.equal(ctx.update().searchParams.get('report_scope'), 'area');
    assert.equal(ctx.update().searchParams.get('report_source'), 'map_feature');
    assert.equal(ctx.update().searchParams.get('report_target'), '55');

    ctx.row(56).click();
    await Promise.resolve();
    assert.equal(ctx.href().searchParams.get('area'), '56');
    assert.equal(ctx.events.at(-1).kind, 'area');
    assert.equal(ctx.update().searchParams.get('report_target'), '56');

    ctx.w.dispatchEvent(new ctx.w.CustomEvent('llama:explorer-browser-site-selected', {
        detail: { id: '2' },
    }));
    assert.equal(ctx.href().searchParams.get('site'), '2');
    assert.equal(ctx.href().searchParams.has('area'), false,
        'Selecting a Site must clear the previous Area URL');
    assert.equal(ctx.update().searchParams.has('report_scope'), false,
        'A previous Area must never leak into a later Site/Place update');
    ctx.w.LlamaScoutExplorer.selectPlace();
    assert.equal(ctx.href().searchParams.has('site'), false);
    assert.equal(ctx.href().searchParams.has('area'), false);
    ctx.dom.window.close();

    const linked = setupExplorer(
        'https://llamascout.com/place.php?slug=north-mills-river&area=55&site=2&season=3'
    );
    assert.equal(linked.w.LlamaScoutExplorer.selected().id, '55');
    assert.equal(linked.href().searchParams.get('area'), '55');
    assert.equal(linked.href().searchParams.has('site'), false,
        'Area deep links must win over stale Site query values');
    assert.equal(linked.update().searchParams.get('report_target'), '55');
    assert.equal(linked.events.at(-1).id, '55',
        'Deep-link restoration must dispatch the normal Area report load event');
    linked.w.document.dispatchEvent(new linked.w.Event('DOMContentLoaded'));
    linked.dom.window.close();

    // Simulate two distinct Areas with different answers and out-of-order
    // endpoint responses, without touching the production database.
    const reportDom = new JSDOM(`<!doctype html><body>
        <section class="scout-report">
            <header><span class="scout-report-completeness">Place 57%</span></header>
            <section data-area-report-context hidden><h3 data-area-report-name></h3>
                <p data-area-report-status></p></section>
            <section class="scout-report-section" data-report-page="place" data-report-scopes="place area">
                <div class="scout-report-grid">
                    <article class="scout-report-item" data-report-owner="place"
                        data-report-scopes="place">Parent answer</article>
                    <article class="scout-report-item" data-report-owner="area"
                        data-report-scopes="area" data-campsite-report-field="area_note">
                        <div class="scout-report-value-content"><strong>Not provided</strong></div>
                    </article>
                </div>
            </section>
        </section>
        <section data-stay-options>
          <button data-stay-area-id="55" data-stay-area-name="West Vault Toilets"></button>
          <button data-stay-area-id="56" data-stay-area-name="East Showers"></button>
        </section>
        <div data-place-campsites-mount data-place-slug="north-mills-river" data-complete-access="1"></div>
    </body>`, {
        url: 'https://llamascout.com/place.php?slug=north-mills-river&area=55',
        runScripts: 'outside-only',
    });
    const v = reportDom.window;
    const requests = [];
    let resolve55;
    v.fetch = request => {
        requests.push(String(request));
        if (String(request).includes('/api/place-area-report.php')) {
            const id = new URL(String(request), v.location.href).searchParams.get('area');
            if (id === '55') {
                return new Promise(resolve => { resolve55 = resolve; });
            }
            return Promise.resolve({ ok: true, json: async () => ({
                ok: true, area: { id: 56, report_target_scope: 'area',
                    report_effective_values: {area_note: 'Answer from East Showers'},
                    report_inapplicable_keys: [], report_answer_sources: {} }
            }) });
        }
        return Promise.resolve({ ok: true, json: async () => ({
            ok: true, sites: [], browser: { show_site_layer: false }
        }) });
    };
    v.eval(reportJs);
    const fire = id => v.dispatchEvent(new v.CustomEvent('llama:explorer-selection', {
        detail: { kind: 'area', id: String(id) }
    }));
    fire(55);
    fire(56);
    await new Promise(resolve => setImmediate(resolve));
    const value = v.document.querySelector('[data-campsite-report-field="area_note"] strong');
    assert.equal(value.textContent, 'Answer from East Showers',
        'Selected Area answers must load from the selected canonical API record');
    assert.equal(v.document.querySelector('[data-area-report-status]').textContent.includes('Loading'), false);
    resolve55({ ok: true, json: async () => ({
        ok: true, area: { id: 55, report_target_scope: 'area',
            report_effective_values: {area_note: 'Stale West answer'},
            report_inapplicable_keys: [], report_answer_sources: {} }
    }) });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(value.textContent, 'Answer from East Showers',
        'An older Area response must not overwrite the newly selected Area');
    assert.ok(requests.some(item => item.includes('area=55')));
    assert.ok(requests.some(item => item.includes('area=56')));
    reportDom.window.close();

    console.log('PASS: Area URL, reload, selection, isolated answers, stale-fetch safety, and target-specific updates');
})().catch(error => { console.error(error); process.exitCode = 1; });
