'use strict';

// Contract smoke test for the active Admin > Campsite Pricing workflow.
// Legacy Place Scout Report pricing and seasonal builder UI tests do not
// represent the current production editor.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const has = (source, text, message) =>
    assert.ok(source.includes(text), message);
const controller = read('admin/campsite-pricing.php');
const service = read('app/admin-campsite-pricing.php');
const browser = read('js/admin/campsite-pricing.js');
const summary = read('admin/partials/place-summary-report.php');
const actions = read('app/admin-place-actions.php');
const approved = read('app/place-report/approved-questions.php');

const authAt = controller.indexOf('moderation_require_admin()');
const postAt = controller.indexOf("if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')");
assert.ok(authAt >= 0 && postAt > authAt,
    'Admin authentication must happen before pricing reads and writes');
has(controller, "moderation_verify_csrf(", 'Every write must verify Admin CSRF');
has(controller, 'id="cpr-csrf"', 'Pricing page must render a CSRF token');
has(browser, "csrf_token: $('csrf').value", 'Pricing JS must submit CSRF');
has(controller, 'https://llamascout.com/js/admin/campsite-pricing.js?v=',
    'Active pricing JavaScript must be versioned on the website asset host');
has(summary, '/campsite-pricing.php?place_id=',
    'Place Admin report must link to Campsite Pricing');
assert.ok(!summary.includes('place-report-seasons.php'),
    'Retired seasonal controls must not be embedded in the Place Report');

for (const action of ['save', 'save-group', 'save-season', 'save-holidays', 'save-review']) {
    has(browser, "action: '" + action + "'", 'Missing browser save action: ' + action);
    has(controller, "'" + action + "'", 'Missing controller save action: ' + action);
}
for (const control of ['cpr-season-form', 'cpr-season-save', 'cpr-seasons-list',
                       'cpr-site', 'cpr-availability', 'cpr-save']) {
    has(controller, 'id="' + control + '"', 'Missing active editor control ' + control);
}
for (const value of ['available', 'reservable', 'fcfs', 'unavailable']) {
    has(controller, 'data-availability="' + value + '"',
        'Missing Site availability button: ' + value);
    has(service, "'" + value + "'", 'Missing backend availability: ' + value);
}
for (const functionName of ['admin_cpr_save(', 'admin_cpr_save_rate_group(',
                            'admin_cpr_save_season(', 'admin_cpr_save_holidays(',
                            'admin_cpr_save_review(']) {
    has(controller, functionName, 'Controller is not wired to ' + functionName);
}
has(service, "hash_equals($before['version'], $expectedVersion)",
    'Site pricing must reject stale saves');
has(service, 'hash_equals(admin_cpr_season_version(', 'Season edit must reject stale saves');
has(service, 'SELECT id FROM places WHERE id = ? FOR UPDATE',
    'Pricing updates must lock the Place');
has(service, 'beginTransaction()', 'Pricing saves must use a transaction');
has(service, 'rollBack()', 'Failed pricing saves must roll back');
has(service, 'llama_season_save_target_answers(',
    'Site seasonal status must use canonical seasonal persistence');
has(service, 'llama_season_save(', 'Place seasons must use validated season definitions');
has(service, 'UPDATE place_rules SET rate_cards_json = ?',
    'Existing Place pricing document remains the single price store');
has(actions, 'Site availability is now managed in Campsite Pricing.',
    'Stale Site bulk availability editor must be blocked');
has(actions, 'Season definitions are now edited under Admin',
    'Stale Place Report season writer must be blocked');
assert.ok(!approved.includes("'pricing' => ['Campsite Rates', ['place_rate_cards_json']]"),
    'Retired pricing JSON must not appear as an approved Scout Report question');

console.log('PASS: dedicated Campsite Pricing controller, assets, authorization, canonical storage, and seasonal controls');
