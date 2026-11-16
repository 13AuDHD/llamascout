'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const script = fs.readFileSync(
    path.join(__dirname, '../../js/admin/complimentary-invitations.js'), 'utf8'
);
const markup = [
    '<!doctype html><html><body>',
    '<form id="create" data-complimentary-send-form>',
    '<input name="email" type="email" required value="sample@example.com">',
    '<button type="submit"><span data-invite-submit-label>Create + Send Invitation</span></button>',
    '</form>',
    '<form id="resend" data-complimentary-send-form>',
    '<button type="submit"><span data-invite-submit-label>Send Fresh Invitation</span></button>',
    '</form>',
    '<form id="revoke"><button type="submit">Revoke</button></form>',
    '</body></html>',
].join('');
const dom = new JSDOM(markup, {
    url: 'https://admin.llamascout.com/complimentary-invitations.php',
    runScripts: 'outside-only',
});
const w = dom.window;
w.eval(script);
w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
const form = id => w.document.getElementById(id);
const submit = id => {
    const event = new w.Event('submit', {bubbles: true, cancelable: true});
    form(id).dispatchEvent(event);
    return event.defaultPrevented;
};

assert.equal(submit('create'), false, 'First create must be allowed.');
assert.equal(form('create').querySelector('button').disabled, true);
assert.equal(form('create').querySelector('button').getAttribute('aria-busy'), 'true');
assert.match(form('create').textContent, /Sending Invitation/);
assert.equal(submit('create'), true, 'Second rapid create must be cancelled.');
assert.equal(submit('resend'), false, 'First resend must be allowed.');
assert.equal(submit('resend'), true, 'Repeated resend must be cancelled.');
assert.equal(form('revoke').querySelector('button').disabled, false,
    'Unrelated revoke form must not be affected.');

const restore = new w.Event('pageshow');
Object.defineProperty(restore, 'persisted', {value: true});
w.dispatchEvent(restore);
assert.equal(form('create').querySelector('button').disabled, false,
    'Safari back-forward cache must restore the submit button.');
assert.match(form('create').textContent, /Create \+ Send Invitation/);
assert.equal(submit('create'), false, 'Restored form may submit again.');

dom.window.close();
console.log('PASS: Create/Resend double-taps blocked and Safari page restoration works.');
