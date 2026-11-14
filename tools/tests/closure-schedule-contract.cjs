'use strict';

// Regression contract for the public closure pipeline and admin Edit fallback.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const core = read('app/operating-closures.php');
const admin = read('admin/closed-places.php');
const banner = read('partials/place/access-alert.php');
const map = read('app/closure-map-status.php');
const site = read('app/campsite-closure-display.php');
const publicSeasons = read('app/place-report/season-public.php');
const list = read('api/place-campsites.php');
const detail = read('api/place-campsite-detail.php');
const css = read('css/admin/features/closed-places.css');

assert.match(core, /function llama_closure_phase\(/);
assert.match(core, /\$start > \$localToday/);
assert.match(core, /\$localToday >= \$end/);
assert.match(core, /function llama_closure_local_today\(/);
const phaseBody = core.split('function llama_closure_phase(')[1].split('function llama_closure_is_active(')[0];
assert.ok(!phaseBody.includes('review_date'),
    'Review date must not activate or reopen a closure');
assert.match(core, /\$reopens <= \$began/,
    'A reopening date at or before the start must be rejected');

assert.match(banner, /\$closurePhase === 'scheduled'/,
    'Future closure must be shown as upcoming, not a red closed banner');
assert.match(banner, /is-scheduled-closure/);
assert.match(banner, /\$closurePhase === 'active'/,
    'Actual closed banner must be conditional on the active date interval');
assert.match(map, /llama_closure_is_active\(/,
    'Map closure status must respect date window');
assert.match(site, /llama_closure_is_active\(/,
    'Individual Site closures must respect start and end dates');
assert.match(publicSeasons, /llama_public_place_active_closure\(/);
assert.match(publicSeasons, /llama_closure_is_active\(/);
assert.match(list, /\$site\['is_closed'\] = true/,
    'Place-wide active closure must mark all Site API results closed');
assert.match(list, /\$placeClosure = llama_public_place_active_closure\(/);
assert.match(detail, /\$site\['is_closed'\] = true/,
    'Site detail must reflect active parent Place closure');
assert.match(detail, /\$placeClosure = llama_public_place_active_closure\(/);

assert.match(admin, /\$_GET\['edit'\]/,
    'Admin must load edit records server-side');
assert.match(admin, /SELECT \* FROM place_operating_closures WHERE id = \?/,
    'Admin must fetch the saved record by ID');
assert.match(admin, /href="\/closed-places\.php\?edit=/,
    'Verified record Edit action must navigate even without JavaScript');
assert.match(admin, /\$fieldValue\('expected_reopening'\)/,
    'Edit must populate the saved reopening date');
assert.match(admin, /\$fieldValue\('review_date'\)/,
    'Edit must populate review reminder separately');
assert.match(admin, /js\/admin-closed-places\.js\?v=/,
    'The optional admin script must load from the main site with cache versioning');
assert.match(admin, /Automatically reopened/);
assert.match(css, /\.closure-review-card\.is-ended/);
assert.match(css, /\.closure-review-card\.is-scheduled/);

console.log('PASS: scheduled/active/ended public visibility and functional server-side Admin Edit');
