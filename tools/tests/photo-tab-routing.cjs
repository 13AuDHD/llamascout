'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const script = fs.readFileSync(
    path.join(__dirname, '../../js/contribution-tabs.js'),
    'utf8'
);

function start({ admin = false } = {}) {
    const selector = admin
        ? '<select data-admin-campsite-selector><option value="">Select a Site</option><option value="campsite_record:7">Site 7</option></select>'
        : '';
    const dom = new JSDOM(`
    <div data-contribution-tabs data-contribution-split data-contribution-initial="place">
        <div data-contribution-picker hidden>
            <button data-contribution-mode="place">Place</button>
            <button data-contribution-mode="site">Site</button>
            <button data-contribution-mode="resource">Resource</button>
        </div>
        <form data-contribution-report>
            <details data-report-main-section="place"></details>
            <details data-report-main-section="site"></details>
            ${selector}
            <section data-contribution-place-photo-panel><input type="hidden" name="photos_json" value="[]"></section>
            ${admin ? '' : '<section data-contribution-site-photo-panel hidden><input type="hidden" name="site_photos_json" value="[]"></section>'}
        </form>
        <div data-contribution-resource hidden>
            <form data-resource-editor>
                <input type="hidden" name="resource_photos_json" value="[]">
            </form>
        </div>
    </div>`, { runScripts: 'outside-only' });
    const { window } = dom;
    let alerts = 0;
    window.alert = () => { alerts += 1; };
    window.eval(script);
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    const shell = window.document.querySelector('[data-contribution-tabs]');
    const button = (name) => shell.querySelector(`[data-contribution-mode="${name}"]`);
    const click = (name) => button(name).click();
    const active = (name) => button(name).getAttribute('aria-pressed') === 'true';
    const stage = (name, photos) => {
        shell.querySelector(`[name="${name}"]`).value = JSON.stringify(photos);
    };
    return {
        window, shell, click, active, stage,
        panel: shell.querySelector('[data-contribution-place-photo-panel]'),
        sitePanel: shell.querySelector('[data-contribution-site-photo-panel]'),
        report: shell.querySelector('[data-contribution-report]'),
        resource: shell.querySelector('[data-contribution-resource]'),
        alertCount: () => alerts,
    };
}

const member = start();
assert.equal(member.active('place'), true, 'Place should initially own new-Place photos');
member.click('site');
assert.equal(member.active('site'), true);
assert.equal(member.panel.hidden, true, 'Place uploader must not appear as a Site uploader on Add Place');
assert.equal(member.sitePanel.hidden, false, 'New Site must have its own visible uploader');
member.click('place');
assert.equal(member.panel.hidden, false);
assert.equal(member.sitePanel.hidden, true);
member.stage('photos_json', [{ path: '/uploads/staging/place.jpg' }]);
member.click('site');
assert.equal(member.active('site'), true, 'Place and Site staging are separate');
member.stage('site_photos_json', [{ path: '/uploads/staging/site.jpg' }]);
member.click('place');
assert.equal(member.active('place'), true, 'Place and Site uploads survive tab switches');
assert.equal(member.sitePanel.hidden, true);
member.click('resource');
assert.equal(member.active('place'), true, 'Switching to another form cannot abandon Place/Site photos');
assert.equal(member.alertCount(), 1);
member.stage('photos_json', []);
member.stage('site_photos_json', []);
member.click('resource');
assert.equal(member.resource.hidden, false);
assert.equal(member.report.hidden, true);
member.stage('resource_photos_json', [{ path: '/uploads/staging/resource.jpg' }]);
member.click('place');
assert.equal(member.active('resource'), true, 'Cannot leave staged Resource photos behind');
member.stage('resource_photos_json', []);
member.click('place');
assert.equal(member.active('place'), true);

const admin = start({ admin: true });
admin.click('site');
assert.equal(admin.panel.hidden, false, 'Saved Admin Site reports share the photo uploader');
assert.equal(admin.sitePanel, null, 'Admin Site uses its existing target-aware gallery');

console.log('PASS: contribution photo tabs keep staged files bound to the intended form');
