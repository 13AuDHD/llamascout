'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const reportJs = read('js/place-campsites.js');
const explorerJs = read('js/place-explorer.js');
const areaMapJs = read('js/place-stay-selection-map.js');
const reportHtml = read('partials/place/scout-report/index.php');
const reportCardPhp = read('partials/place-report/read-only.php');
const stayPhp = read('partials/place/overnight-options.php');
const colorCss = read('css/site/features/place-explorer.css');
const mapCss = read('css/site/features/place-map.css');
const editorJs = read('js/place-map-editor.js');

assert.match(reportHtml, /data-area-report-context hidden/);
assert.match(reportCardPhp, /data-report-owner=/,
    'Every canonical report card must identify its owning question scope');
assert.doesNotMatch(stayPhp, /data-stay-facility-report|data-stay-facility-question-catalog/,
    'A legacy Facility Scout Report must not duplicate the canonical Area report');
assert.doesNotMatch(areaMapJs, /applyAreaReport\(|renderFacilityDetail\(/,
    'The old Area-on-Place answer overlay must not be invoked');
assert.match(areaMapJs, /selectedColor = selection.kind === 'area' \? '#9765cb'/);
assert.doesNotMatch(areaMapJs, /#f8d028/,
    'The Area selection outline must not turn yellow');
assert.match(explorerJs, /if \(kind === 'parking'\) return 'purple'/,
    'Parking Areas must also remain purple');
assert.match(mapCss, /\.place-map-feature--parking-area \{\s*stroke: #9765cb;/);
assert.match(mapCss, /\.place-map-feature--facility-area \{\s*stroke: #9765cb;/);
assert.match(editorJs, /parking_area:\s*\{\s*color:\s*'#9765cb'/);

const markup = `<!doctype html>
<style>${colorCss}</style>
<section class="scout-report" data-question-inventory-review="1">
    <header class="scout-report-header">
        <div class="scout-report-completeness">Place 57/191</div>
        <a class="scout-report-history-link">Place history</a>
    </header>
    <section data-area-report-context hidden>
        <h3 data-area-report-name></h3><p data-area-report-status></p>
    </section>
    <section class="scout-report-section" data-report-page="place" data-report-scopes="place area">
        <h3>Amenities</h3>
        <div class="scout-report-grid">
            <div class="scout-report-item" data-report-scopes="place area"
                 data-report-owner="place">Parent Place answer</div>
            <div class="scout-report-item" data-report-scopes="area"
                 data-report-owner="area" hidden>Area-only question</div>
        </div>
    </section>
    <section class="scout-report-section" data-report-page="site" data-report-scopes="site">
        <div class="scout-report-grid">
            <div class="scout-report-item" data-report-scopes="site"
                 data-report-owner="site">Site-only question</div>
        </div>
    </section>
</section>
<section data-stay-options><button data-stay-area-id="55" data-stay-area-name="South Restrooms"></button></section>`;
const dom = new JSDOM(markup, {
    runScripts: 'outside-only',
    url: 'https://llamascout.com/place.php?slug=north-mills-river',
});
dom.window.eval(reportJs); // The full Site browser returns early without its mount.

const q = selector => dom.window.document.querySelector(selector);
const placeCard = q('[data-report-owner="place"]');
const areaCard = q('[data-report-owner="area"]');
const siteCard = q('[data-report-owner="site"]');
const context = q('[data-area-report-context]');
const placeSection = q('[data-report-page="place"]');
const siteSection = q('[data-report-page="site"]');
const event = (kind, id = '') => dom.window.dispatchEvent(
    new dom.window.CustomEvent('llama:explorer-selection', { detail: { kind, id } })
);

event('area', '55');
assert.equal(context.hidden, false, 'Area report identity must be visible');
assert.equal(q('[data-area-report-name]').textContent, 'South Restrooms');
assert.equal(q('.scout-report-completeness').hidden, true,
    'Place completeness must not be mislabeled as Area completeness');
assert.equal(q('.scout-report-history-link').hidden, true);
assert.equal(placeSection.hidden, false, 'Area-owned field group is present');
assert.equal(areaCard.hidden, false, 'Area-owned question should be shown');
assert.equal(placeCard.hidden, true, 'Place question must disappear in Area mode');
assert.equal(siteSection.hidden, true, 'Site section must disappear in Area mode');
assert.equal(siteCard.hidden, true, 'Site question must disappear in Area mode');
assert.equal(dom.window.getComputedStyle(placeCard).display, 'none',
    'CSS must not override the hidden attribute on Place cards');

event('place');
assert.equal(context.hidden, true);
assert.equal(q('.scout-report-completeness').hidden, false);
assert.equal(placeCard.hidden, false);
assert.equal(areaCard.hidden, true);

event('site');
assert.equal(siteSection.hidden, false);
assert.equal(siteCard.hidden, false);
assert.equal(placeSection.hidden, true);
dom.window.close();
console.log('PASS: Area purple identity, scope-only questions, hidden Place cards, no duplicate Facility report');
