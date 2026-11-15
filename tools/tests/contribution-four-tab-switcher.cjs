'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const js = fs.readFileSync(path.join(root, 'js/contribution-tabs.js'), 'utf8');

function mount(initial) {
    const dom = new JSDOM(`<!doctype html><html><body>
      <div data-contribution-tabs data-contribution-split data-contribution-initial="${initial}">
        <nav data-contribution-picker hidden>
          <button data-contribution-mode="place" aria-pressed="true">Place</button>
          <button data-contribution-mode="site" aria-pressed="false">Site</button>
          <button data-contribution-mode="area" aria-pressed="false">Facility</button>
          <a href="/resource-contribution.php" data-contribution-resource-link>Resource</a>
        </nav>
        <section data-contribution-intro-mode="place">Place explanation</section>
        <section data-contribution-intro-mode="site">Site explanation</section>
        <section data-contribution-intro-mode="area">Facility explanation</section>
        <div data-contribution-area-panel hidden>Facility form</div>
        <form data-contribution-report>
          <details data-report-main-section="place"></details>
          <details data-report-main-section="site"></details>
        </form>
      </div>
    </body></html>`, {
        url: 'https://llamascout.com/add-place.php?tab=' + initial,
        runScripts: 'outside-only'
    });
    dom.window.eval(js);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
    const q = selector => dom.window.document.querySelector(selector);
    return {dom, q};
}
function verify(initial, active, reportVisible, facilityVisible) {
    const {dom,q} = mount(initial);
    const all = [...dom.window.document.querySelectorAll('[data-contribution-mode]')];
    assert.equal(all.length, 3, 'Resource must remain a link to its own form');
    for (const button of all) {
        assert.equal(button.getAttribute('aria-pressed') === 'true',
            button.dataset.contributionMode === active);
    }
    assert.equal(q('[data-contribution-report]').hidden, !reportVisible);
    assert.equal(q('[data-contribution-area-panel]').hidden, !facilityVisible);
    assert.equal(q('[data-contribution-picker]').hidden, false);
    for (const intro of dom.window.document.querySelectorAll('[data-contribution-intro-mode]')) {
        assert.equal(intro.hidden, intro.dataset.contributionIntroMode !== active,
            'Only active contribution explanation must be visible');
    }
    assert.equal(q('[data-contribution-resource-link]').getAttribute('href'),
        '/resource-contribution.php');
    if (active === 'site') {
        assert.equal(q('[data-report-main-section="site"]').hasAttribute('data-contribution-inactive'), false);
    }
    dom.window.close();
}
verify('place','place',true,false);
verify('site','site',true,false);
verify('area','area',false,true);

const {dom,q} = mount('place');
q('[data-contribution-mode="site"]').click();
assert.equal(q('[data-contribution-mode="site"]').getAttribute('aria-pressed'),'true');
assert.equal(q('[data-contribution-intro-mode="site"]').hidden, false);
assert.equal(q('[data-contribution-intro-mode="place"]').hidden, true);
q('[data-contribution-mode="area"]').click();
assert.equal(q('[data-contribution-mode="area"]').getAttribute('aria-pressed'),'true');
assert.equal(q('[data-contribution-area-panel]').hidden, false);
assert.equal(q('[data-contribution-intro-mode="area"]').hidden, false);
assert.equal(q('[data-contribution-intro-mode="site"]').hidden, true);
q('[data-contribution-mode="place"]').click();
assert.equal(q('[data-contribution-mode="place"]').getAttribute('aria-pressed'),'true');
assert.equal(q('[data-contribution-report]').hidden, false);
assert.equal(q('[data-contribution-intro-mode="place"]').hidden, false);
assert.equal(q('[data-contribution-intro-mode="area"]').hidden, true);
dom.window.close();

console.log('PASS: Place, Site and Facility switch locally; Resource link stays available on all three');
