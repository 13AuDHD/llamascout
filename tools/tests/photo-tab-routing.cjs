'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const script = fs.readFileSync(path.join(__dirname, '../../js/contribution-tabs.js'), 'utf8');

function start({ admin = false } = {}) {
    const sitePicker = admin
        ? '<select data-admin-campsite-selector><option value="">Select a Site</option><option value="campsite_record:7">Site 7</option></select>'
        : '';
    const dom = new JSDOM(`
    <div data-contribution-tabs data-contribution-split data-contribution-initial="place">
      <div data-contribution-picker hidden>
        <button data-contribution-mode="place">Place</button>
        <button data-contribution-mode="site">Site</button>
        <button data-contribution-mode="area">Area</button>
      </div>
      <section data-contribution-area-panel hidden>Save the Place first</section>
      <form data-contribution-report>
        <details data-report-main-section="place"></details>
        <details data-report-main-section="site"></details>
        ${sitePicker}
        <section data-contribution-place-photo-panel><input type="hidden" name="photos_json" value="[]"></section>
        ${admin ? '' : '<section data-contribution-site-photo-panel hidden><input type="hidden" name="site_photos_json" value="[]"></section>'}
      </form>
    </div>`, { runScripts: 'outside-only' });
    const { window } = dom;
    let alerts = 0;
    window.alert = () => { alerts += 1; };
    window.eval(script);
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    const shell = window.document.querySelector('[data-contribution-tabs]');
    const button = mode => shell.querySelector(`[data-contribution-mode="${mode}"]`);
    return {
        click: mode => button(mode).click(),
        active: mode => button(mode).getAttribute('aria-pressed') === 'true',
        stage: (name, photos) => {
            shell.querySelector(`[name="${name}"]`).value = JSON.stringify(photos);
        },
        panel: shell.querySelector('[data-contribution-place-photo-panel]'),
        sitePanel: shell.querySelector('[data-contribution-site-photo-panel]'),
        areaPanel: shell.querySelector('[data-contribution-area-panel]'),
        report: shell.querySelector('[data-contribution-report]'),
        alertCount: () => alerts,
    };
}
const member = start();
assert.equal(member.active('place'), true);
member.click('site');
assert.equal(member.active('site'), true);
assert.equal(member.panel.hidden, true);
assert.equal(member.sitePanel.hidden, false);
member.click('place');
assert.equal(member.panel.hidden, false);
assert.equal(member.sitePanel.hidden, true);
member.stage('photos_json', [{path: '/uploads/staging/place.jpg'}]);
member.click('site');
assert.equal(member.active('site'), true, 'Place and Site photo staging remains separate');
member.stage('site_photos_json', [{path: '/uploads/staging/site.jpg'}]);
member.click('place');
assert.equal(member.active('place'), true, 'Staged evidence survives Place/Site switching');
member.click('area');
assert.equal(member.active('place'), true, 'Switching to Area cannot hide unsaved photos');
assert.equal(member.alertCount(), 1);
member.stage('photos_json', []);
member.stage('site_photos_json', []);
member.click('area');
assert.equal(member.active('area'), true);
assert.equal(member.areaPanel.hidden, false);
assert.equal(member.report.hidden, true);
member.click('place');
assert.equal(member.active('place'), true);
assert.equal(member.report.hidden, false);
const admin = start({admin: true});
admin.click('site');
assert.equal(admin.panel.hidden, false, 'Admin Site reports retain target-aware photo editor');
assert.equal(admin.sitePanel, null);
console.log('PASS: Place/Site staged photos are protected and Area tab has no duplicate Resource form');
