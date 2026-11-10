'use strict';

// Regression for parking-type Places such as Chimney Rock Pulloff: the
// record's identity determines its color, never the Place's category.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const root = path.resolve(__dirname, '../..');
const js = fs.readFileSync(path.join(root, 'js/place-explorer.js'), 'utf8');
const css = fs.readFileSync(path.join(root, 'css/site/features/place-explorer.css'), 'utf8');

const setup = (category, url = 'https://llamascout.com/place.php?slug=chimney-rock-pulloff') => {
    const dom = new JSDOM(`<!doctype html><html><body>
      <div class="place-map-card" data-explorer-name="Chimney Rock Pulloff"
           data-explorer-category="${category}" data-explorer-site-count="1"></div>
      <section class="place-overnight-options" data-stay-options>
        <div data-stay-browser-slot></div>
        <button type="button" data-stay-area-id="21"
                data-stay-area-name="Camping Area" data-stay-area-kind="camping">Camping Area</button>
        <button type="button" data-stay-area-id="22"
                data-stay-area-name="Parking Area" data-stay-area-kind="parking">Parking Area</button>
      </section>
      <div class="scout-report">
        <section class="scout-report-section scout-report-scope-place"
                 data-report-page="place"><div class="scout-report-item">Place answer</div></section>
        <section class="scout-report-section scout-report-scope-site"
                 data-report-page="site"><div class="scout-report-item">Site answer</div></section>
      </div>
    </body></html>`, {
        url,
        runScripts: 'outside-only',
    });
    const w = dom.window;
    const map = {
        eachLayer() {},
        on() {},
        closePopup() {},
        setView() {},
        fitBounds() {},
        getMaxZoom() { return 18; },
    };
    w.L = { Marker: class Marker {} };
    w.LlamaScoutMap = { map };
    w.LlamaScoutCampsiteBrowser = { clearSelection() {}, selectSite() {} };
    w.eval(js);
    return {
        dom,
        w,
        section: w.document.querySelector('[data-stay-options]'),
        report: w.document.querySelector('.scout-report'),
        overview: w.document.querySelector('.place-explorer-overview'),
    };
};
const state = ({section, report}) => ({
    sectionScope: section.dataset.explorerScope,
    sectionColor: section.dataset.explorerColor,
    reportScope: report.dataset.explorerScope,
    reportColor: report.dataset.explorerColor,
});

(async () => {
    for (const category of ['parking', 'camping', 'other']) {
        const ctx = setup(category);
        assert.deepEqual(state(ctx), {
            sectionScope: 'place',
            sectionColor: 'green',
            reportScope: 'place',
            reportColor: 'green',
        }, 'Place must remain green even when type is ' + category);
        assert.equal(ctx.overview.classList.contains('is-green'), true,
            'Place overview should keep the Place-green border');
        assert.equal(ctx.overview.textContent.includes(
            category === 'parking' ? 'Parking location' : category === 'camping'
                ? 'Campground or camping area' : 'Location overview'
        ), true, 'Category description should remain unchanged');

        ctx.w.dispatchEvent(new ctx.w.CustomEvent('llama:explorer-browser-site-selected', {
            detail: {id: '31'},
        }));
        assert.deepEqual(state(ctx), {
            sectionScope: 'site', sectionColor: 'orange',
            reportScope: 'site', reportColor: 'orange',
        }, 'Sites are orange regardless of Place type');

        ctx.w.LlamaScoutExplorer.selectPlace();
        assert.deepEqual(state(ctx), {
            sectionScope: 'place', sectionColor: 'green',
            reportScope: 'place', reportColor: 'green',
        }, 'Returning to Place must restore green, not its parking category color');

        ctx.section.querySelector('[data-stay-area-kind="camping"]').click();
        await Promise.resolve();
        assert.deepEqual(state(ctx), {
            sectionScope: 'area', sectionColor: 'purple',
            reportScope: 'area', reportColor: 'purple',
        }, 'Ordinary Areas retain purple identity');

        ctx.section.querySelector('[data-stay-area-kind="parking"]').click();
        await Promise.resolve();
        assert.deepEqual(state(ctx), {
            sectionScope: 'parking', sectionColor: 'blue',
            reportScope: 'parking', reportColor: 'blue',
        }, 'Only parking Areas are blue');
        ctx.dom.window.close();
    }

    const deepLink = setup('parking', 'https://llamascout.com/place.php?slug=chimney-rock-pulloff&site=31');
    assert.equal(deepLink.report.dataset.explorerColor, 'orange',
        'Site deep links must start orange, not blue from parent category');
    deepLink.w.LlamaScoutExplorer.selectPlace();
    assert.equal(deepLink.report.dataset.explorerColor, 'green');
    deepLink.dom.window.close();

    assert.match(css, /\.place-overnight-options\[data-explorer-scope="parking"\]/);
    assert.match(css, /\.place-overnight-options\[data-explorer-scope="area"\]/);
    assert.match(css, /\.scout-report\[data-explorer-scope="site"\]/);
    assert.match(css, /\.scout-report\[data-explorer-scope="parking"\]/);
    assert.doesNotMatch(css, /\.scout-report\[data-explorer-color/);
    assert.match(css, /\[data-stay-area-kind="camping"\]\s*\{[^}]*#9765cb/s);

    console.log('PASS: Place green, Site orange, Area purple, parking Area blue across categories and deep links');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
