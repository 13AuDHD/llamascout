'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const script = fs.readFileSync(
    path.join(__dirname, '../../js/contribution-tabs.js'), 'utf8'
);

function start({admin = false, modern = false} = {}) {
    const html = [
        '<!doctype html><body>',
        '<div data-contribution-tabs data-contribution-split',
        ' data-admin-facility-source="' + (modern ? 'resource' : 'map_feature') + '"',
        ' data-contribution-initial="place">',
        '<nav data-contribution-picker hidden>',
        '<button type="button" data-contribution-mode="place">Place</button>',
        '<button type="button" data-contribution-mode="site">Site</button>',
        admin
            ? '<button type="button" data-contribution-mode="facility">Facility</button>'
            : '<a href="/resource-contribution.php?kind=facility" data-contribution-facility-link>Facility</a>',
        '</nav>',
        admin ? '<section data-admin-area-picker hidden>Facility selection</section>' : '',
        '<form data-contribution-report>',
        '<details data-report-main-section="place"></details>',
        '<details data-report-main-section="site"></details>',
        admin
            ? '<select data-admin-campsite-selector><option value="">Select Site</option><option value="campsite_record:7">Site 7</option></select>'
            : '',
        '<section data-contribution-place-photo-panel>',
        '<input name="photos_json" value="[]"></section>',
        admin ? '' : '<section data-contribution-site-photo-panel hidden>',
        admin ? '' : '<input name="site_photos_json" value="[]"></section>',
        '</form>',
        admin ? '<section data-admin-resource-facility-editor hidden>Facility editor</section>' : '',
        '</div></body>',
    ].join('');
    const dom = new JSDOM(html, {runScripts: 'outside-only', url: 'https://llamascout.com'});
    const {window} = dom;
    window.eval(script);
    window.document.dispatchEvent(new window.Event('DOMContentLoaded'));
    const shell = window.document.querySelector('[data-contribution-tabs]');
    const button = mode => shell.querySelector('[data-contribution-mode="' + mode + '"]');
    return {
        click: mode => button(mode).click(),
        active: mode => button(mode).getAttribute('aria-pressed') === 'true',
        stage: (name, photos) => {
            shell.querySelector('[name="' + name + '"]').value = JSON.stringify(photos);
        },
        shell, dom,
        panel: shell.querySelector('[data-contribution-place-photo-panel]'),
        sitePanel: shell.querySelector('[data-contribution-site-photo-panel]'),
        report: shell.querySelector('[data-contribution-report]'),
    };
}

const member = start();
assert.equal(member.active('place'), true);
assert.equal(member.shell.querySelector('[data-contribution-mode="facility"]'), null,
    'Add a Facility stays a separate contributor workflow');
member.click('site');
assert.equal(member.active('site'), true);
assert.equal(member.panel.hidden, true);
assert.equal(member.sitePanel.hidden, false);
member.stage('site_photos_json', [{path: '/uploads/staging/site.jpg'}]);
member.click('place');
assert.equal(member.panel.hidden, false);
assert.equal(member.sitePanel.hidden, true);
member.stage('photos_json', [{path: '/uploads/staging/place.jpg'}]);
member.click('site');
assert.deepEqual(JSON.parse(member.shell.querySelector('[name="photos_json"]').value),
    [{path: '/uploads/staging/place.jpg'}],
    'Switching Place and Site must preserve separately staged Place photos');
assert.deepEqual(JSON.parse(member.shell.querySelector('[name="site_photos_json"]').value),
    [{path: '/uploads/staging/site.jpg'}],
    'Switching Place and Site must preserve separately staged Site photos');
member.dom.window.close();

const admin = start({admin: true});
admin.click('site');
assert.equal(admin.panel.hidden, false, 'Site Admin keeps target-aware photo editor');
admin.click('facility');
assert.equal(admin.report.hidden, false, 'Map-backed Facility keeps original Area gallery target');
assert.equal(admin.shell.querySelector('[data-admin-area-picker]').hidden, false);
admin.dom.window.close();

const modern = start({admin: true, modern: true});
modern.click('facility');
assert.equal(modern.report.hidden, true, 'Modern Facility uses its own photo gallery');
assert.equal(modern.shell.querySelector('[data-admin-resource-facility-editor]').hidden, false);
assert.equal(modern.shell.querySelector('[data-admin-area-picker]').hidden, false,
    'Shared selector must remain visible when editing modern Facility photos');
modern.dom.window.close();

console.log('PASS: Contributor Place/Site photos and unified Admin Facility gallery routing.');
