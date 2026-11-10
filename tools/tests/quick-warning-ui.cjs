'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'js/place-report-warnings.js'), 'utf8');
const css = fs.readFileSync(
    path.join(root, 'css/site/features/scout-warning-compact.css'),
    'utf8'
);
const dom = new JSDOM(`<!doctype html><html><body>
<section class="scout-report-warning-section" data-report-warnings hidden>
  <p data-report-warning-summary>Important conditions reported for this Site.</p>
  <div class="scout-report-grid" data-report-warning-grid></div>
</section></body></html>`, {
    url: 'https://llamascout.com/place.php?slug=bad-bear',
    runScripts: 'outside-only',
});
const w = dom.window;
w.eval(script);
const section = w.document.querySelector('[data-report-warnings]');
const grid = section.querySelector('[data-report-warning-grid]');
const summary = section.querySelector('[data-report-warning-summary]');

w.LlamaScoutReportWarnings.render([
    {label: 'No cell service', icon: 'antenna-bars-off'},
], 'Site');

assert.equal(section.hidden, false, 'Site warnings should appear when present');
assert.equal(grid.children.length, 1, 'One warning must create one card');
const card = grid.firstElementChild;
assert.ok(card.classList.contains('scout-report-warning-item'));
assert.equal(card.querySelector('.scout-report-value-content > span').textContent,
    'No cell service');
assert.equal(card.querySelector('strong').textContent, 'Warning');
const icon = card.querySelector('span.scout-report-warning-icon');
assert.ok(icon, 'Warnings must use theme-aware mask icons, not unstyleable external img elements');
assert.equal(icon.getAttribute('aria-hidden'), 'true');
assert.equal(icon.style.getPropertyValue('--scout-warning-icon-image'),
    'url("/assets/icons/antenna-bars-off.svg")');
assert.equal(card.querySelector('img'), null, 'Icon should no longer be an unthemed SVG image');

// Multiple warnings must remain independent cards in the same grid.
w.LlamaScoutReportWarnings.render([
    {label: 'No cell service', icon: 'antenna-bars-off'},
    {label: 'Flood risk', icon: 'alert-triangle'},
], 'Site');
assert.equal(grid.children.length, 2, 'Warnings must not collapse into one full-width card');
assert.ok(grid.children[1].querySelector('.scout-report-warning-icon'));

// Malformed source names should not generate arbitrary image URLs.
w.LlamaScoutReportWarnings.render([{label: 'Warning', icon: '../bad"};'}]);
assert.equal(grid.firstElementChild.querySelector('.scout-report-warning-icon')
    .style.getPropertyValue('--scout-warning-icon-image'),
    'url("/assets/icons/alert-triangle.svg")');

w.LlamaScoutReportWarnings.render([]);
assert.equal(section.hidden, true, 'Empty warnings should hide the warning section');

// CSS contract: the normal card grid uses auto-fit; warnings must retain
// unoccupied columns with auto-fill, including a single warning on tablet.
assert.match(css, /\.scout-report-warning-section\s+\.scout-report-grid\s*\{[^}]*repeat\(auto-fill,/s);
assert.match(css, /\.scout-report-warning-section\s+\.scout-report-warning-item\s*\{[^}]*border:\s*2px solid var\(--scout-warning-color\)/s);
assert.match(css, /:root\[data-theme="dark"\][^{]+\{\s*--scout-warning-color:/);
assert.match(css, /:root:not\(\[data-theme="light"\]\)/);
assert.match(css, /mask-image:\s*var\(--scout-warning-icon-image\)/);
assert.match(css, /background-color:\s*var\(--scout-warning-color\)/);
assert.match(css, /@media \(max-width: 600px\)/);

dom.window.close();
console.log('PASS: Quick warnings stay compact with theme-aware icons, red borders and valid dynamic rendering');
