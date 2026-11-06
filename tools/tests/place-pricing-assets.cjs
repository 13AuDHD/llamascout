'use strict';

// Requires jsdom 26.1.0 (also pinned in the CI check).
// Reproduce the Admin subdomain: pricing assets belong to the main website.
// This checks actual shared-form output, not an invented script URL.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {JSDOM, ResourceLoader} = require('jsdom');
const root = path.resolve(__dirname, '../..');
const php = process.env.PHP_BIN || 'php';
const args = JSON.parse(process.env.PHP_TEST_ARGS || '[]');
const fixture = `require 'app/place-report.php';
function llama_icon(string $name): string { return ''; }
$placeReportValues = ['name' => 'Pricing test', 'place_rate_cards_json' => '{"version":2,"currency":"USD","weekend_days":[5,6],"holidays":[],"rows":[]}'];
$placeReportShowPhotos = false; $placeReportShowLocate = false;
$placeReportMode = 'moderator'; require 'partials/place-report/form.php';`;
const rendered = spawnSync(php, [...args, '-r', fixture], {cwd:root, encoding:'utf8'});
assert.equal(rendered.status, 0, rendered.stderr || rendered.stdout);
const parsed = new JSDOM(rendered.stdout).window.document;
const script = parsed.querySelector('script[data-place-rate-cards-script]');
const style = parsed.querySelector('link[data-place-rate-cards-style]');
assert.ok(script, 'Shared form must directly include the pricing editor');
assert.ok(style, 'Shared form must directly include the pricing stylesheet');
for (const element of [script, style]) {
    const url = new URL(element.getAttribute(element.tagName === 'SCRIPT' ? 'src' : 'href'));
    assert.equal(url.origin, 'https://llamascout.com');
    assert.match(url.searchParams.get('v'), /^\d+$/, 'Assets must be versioned');
}
assert.equal(fs.readFileSync(path.join(root,'js/place-report-road-hazards.js'),'utf8').includes('data-place-rate-cards-script'), false, 'Pricing must not depend on another feature loader');
// Retain real form markup but isolate this feature from unrelated JS.
parsed.querySelectorAll('script').forEach(el => el.remove());
class Assets extends ResourceLoader {
    fetch(url) {
        const asset = new URL(url);
        assert.equal(asset.origin, 'https://llamascout.com', 'Never request assets from Admin');
        return Promise.resolve(fs.readFileSync(path.join(root, asset.pathname)));
    }
}
(async () => {
    const dom = new JSDOM(`<form>${parsed.body.innerHTML}</form>${style.outerHTML}${script.outerHTML}`, {
        url:'https://admin.llamascout.com/place.php?id=17',
        runScripts:'dangerously', resources:new Assets(),
    });
    await new Promise((resolve, reject) => {
        dom.window.addEventListener('load',resolve,{once:true});
        setTimeout(()=>reject(new Error('Pricing assets did not load')),5000).unref();
    });
    const d = dom.window.document;
    assert.equal(d.querySelectorAll('.place-rate-cards-field').length,1);
    assert.ok(d.querySelector('textarea[name="place_rate_cards_json"]').classList.contains('place-rate-cards-json'));
    d.querySelector('[data-add]').click();
    assert.equal(d.querySelectorAll('[data-row]').length,1);
    assert.equal(JSON.parse(new dom.window.FormData(d.querySelector('form')).get('place_rate_cards_json')).rows.length,1);
    dom.window.close();
    console.log('PASS: actual shared form loads versioned pricing assets on Admin and displays the working editor');
})().catch(error=>{console.error(error);process.exitCode=1;});
