'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const js = fs.readFileSync(path.join(__dirname, '../../js/contribution-tabs.js'), 'utf8');

function mount(initial) {
    const dom = new JSDOM(`<!doctype html><body>
        <div data-contribution-tabs data-contribution-split data-contribution-initial="${initial}">
            <nav data-contribution-picker hidden>
                <button data-contribution-mode="place" aria-pressed="true">Place</button>
                <button data-contribution-mode="site" aria-pressed="false">Site</button>
                <a href="/resource-contribution.php?kind=facility" data-contribution-facility-link>Facility</a>
                <a href="/resource-contribution.php" data-contribution-resource-link>Resource</a>
            </nav>
            <section data-contribution-intro-mode="place">Place</section>
            <section data-contribution-intro-mode="site">Site</section>
            <form data-contribution-report>
                <details data-report-main-section="place"></details>
                <details data-report-main-section="site"></details>
            </form>
        </div>
    </body>`, { runScripts: 'outside-only', url: 'https://llamascout.com/add-place.php' });
    dom.window.eval(js);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
    const q = selector => dom.window.document.querySelector(selector);
    return { dom, q };
}
for (const initial of ['place', 'site']) {
    const {dom, q} = mount(initial);
    const buttons = [...dom.window.document.querySelectorAll('[data-contribution-mode]')];
    assert.equal(buttons.length, 2, 'Facility and Resource navigate to independent forms');
    assert.equal(q('[data-contribution-picker]').hidden, false);
    for (const button of buttons) {
        assert.equal(button.getAttribute('aria-pressed') === 'true',
            button.dataset.contributionMode === initial);
    }
    assert.equal(q('[data-contribution-report]').hidden, false);
    assert.equal(q('[data-contribution-intro-mode="' + initial + '"]').hidden, false);
    assert.equal(q('[data-contribution-facility-link]').getAttribute('href'),
        '/resource-contribution.php?kind=facility');
    dom.window.close();
}
const {dom, q} = mount('place');
q('[data-contribution-mode="site"]').click();
assert.equal(q('[data-contribution-mode="site"]').getAttribute('aria-pressed'), 'true');
assert.equal(q('[data-contribution-intro-mode="site"]').hidden, false);
q('[data-contribution-mode="place"]').click();
assert.equal(q('[data-contribution-mode="place"]').getAttribute('aria-pressed'), 'true');
assert.equal(q('[data-contribution-report]').hidden, false);
dom.window.close();
console.log('PASS: local Place/Site tabs and independent Resource/Facility links');
