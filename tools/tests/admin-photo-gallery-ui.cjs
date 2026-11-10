'use strict';

// Use the real Admin photo controller against a simulated report page.
// Test ordering, Hero, captions, selected removal and owner isolation.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../js/admin/place-report-photos.js'), 'utf8'
);

const photo = (id, owner, alt, featured = false) => ({
    id, src: '/uploads/places/12/' + owner + '-' + id + '.jpg',
    url: 'https://llamascout.com/uploads/places/12/' + owner + '-' + id + '.jpg',
    alt, sort_order: featured ? 0 : id, is_featured: featured ? 1 : 0,
});
const placeKey = 'place:place:12';
const siteKey = 'site:campsite_record:9';
const config = {
    place_id: 12,
    targets: {
        [placeKey]: {
            label: 'Entire Place', scope: 'place', source: 'place',
            id: 12, supported: true, max: 10,
            photos: [
                photo(11, 'place', 'Entrance', true),
                photo(12, 'place', 'Road'),
                photo(13, 'place', 'Forest'),
            ],
        },
        [siteKey]: {
            label: 'Site 9', scope: 'site', source: 'campsite_record',
            id: 9, supported: true, max: 10,
            photos: [
                photo(81, 'site', 'Campfire setup', true),
                photo(82, 'site', 'Table'),
            ],
        },
    },
};
const html = `<!doctype html><html><body>
<div data-contribution-tabs>
    <button type="button" data-contribution-mode="place" aria-pressed="true">Place</button>
    <button type="button" data-contribution-mode="site" aria-pressed="false">Site</button>
    <form id="place-report">
        <input name="place_id" value="12">
        <input name="csrf_token" value="test">
        <input name="photo_stage_token">
        <input name="photos_json" value="[]">
        <select data-admin-campsite-selector><option value="">Select Site</option>
            <option value="campsite_record:9">Site 9</option></select>
        <script type="application/json" data-admin-place-photo-targets>${JSON.stringify(config)}</script>
        <section class="contribution-section">
            <div class="contribution-section-body">
                <div data-photo-uploader data-photo-context="admin-place"></div>
            </div>
        </section>
        <button type="button" data-place-report-admin-save>Save Place Report</button>
    </form>
</div></body></html>`;
const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/place.php?id=12',
    runScripts: 'outside-only',
});
const w = dom.window;
const d = w.document;
const form = d.querySelector('form');
const query = selector => d.querySelector(selector);
const cards = () => Array.from(d.querySelectorAll('[data-admin-photo-id]'));
const ids = () => cards().map(card => Number(card.dataset.adminPhotoId));
const payload = () => JSON.parse(form.querySelector('[name="admin_photo_gallery_json"]').value);
const hero = () => cards().find(card => !card.querySelector('[data-admin-photo-hero]').hidden);
let alerts = [];
w.confirm = () => false;
w.alert = text => alerts.push(text);
w.eval(source);

assert.deepEqual(ids(), [11, 12, 13], 'Initial Place order must reflect DB order');
assert.equal(hero().dataset.adminPhotoId, '11', 'First Place image must show Hero');
assert.equal(query('.admin-attached-photo-handle').getAttribute('aria-label'), 'Move photo in gallery');

// Keyboard arrows and pointer handles share the same reordering model.
const move = (card, key) => card.querySelector('.admin-attached-photo-handle')
    .dispatchEvent(new w.KeyboardEvent('keydown', {key, bubbles: true}));
move(cards()[1], 'ArrowLeft');
assert.deepEqual(ids(), [12, 11, 13]);
assert.equal(hero().dataset.adminPhotoId, '12', 'Reordering should move the Hero label');
assert.deepEqual(payload(), {
    target: placeKey,
    items: [
        {id: 12, alt: 'Road'},
        {id: 11, alt: 'Entrance'},
        {id: 13, alt: 'Forest'},
    ],
}, 'Only photo IDs and captions can enter the save payload');
let caption = cards()[0].querySelector('[data-admin-photo-caption]');
caption.value = 'Updated site road';
caption.dispatchEvent(new w.Event('input', {bubbles: true}));
assert.equal(payload().items[0].alt, 'Updated site road');
assert.equal(cards()[1].querySelector('img').getAttribute('alt'), 'Entrance',
    'Untouched image alt remains tied to its photo ID');

const checkbox = cards()[1].querySelector('[name="remove_existing_photos[]"]');
checkbox.checked = true;
checkbox.dispatchEvent(new w.Event('change', {bubbles: true}));
assert.deepEqual(payload().items.map(item => item.id), [12, 13],
    'Checked removal must exclude the photo from the reordered survivors');
assert.deepEqual(Array.from(new w.FormData(form).getAll('remove_existing_photos[]')),
    ['/uploads/places/12/place-11.jpg'],
    'Existing removal checkbox must still submit a target-owned source path');

// Saving the report serializes gallery edits without updating the original
// catalog until the server has confirmed its database transaction.
form.dispatchEvent(new w.CustomEvent('llama:admin-place-report-save-starting'));
assert.equal(payload().target, placeKey);
form.dispatchEvent(new w.CustomEvent('llama:photo-gallery-saved', {
    detail: {
        target: placeKey,
        gallery: [
            {...photo(12, 'place', 'Updated site road', true), sort_order: 0},
            {...photo(13, 'place', 'Forest'), sort_order: 1},
        ],
    },
}));
assert.deepEqual(ids(), [12, 13], 'Committed gallery replaces checked removals');
assert.equal(hero().dataset.adminPhotoId, '12');
assert.equal(query('[name="admin_photo_gallery_json"]').value, '',
    'Saved gallery must not be written again on an unrelated form save');

// Simulate iPad pointer dragging using the actual drag handle, including
// pointer capture, a hit-tested destination and release.
const touchCard = cards()[1];
const touchHandle = touchCard.querySelector('.admin-attached-photo-handle');
let capturedPointer = null;
touchHandle.setPointerCapture = id => { capturedPointer = id; };
touchHandle.hasPointerCapture = id => capturedPointer === id;
touchHandle.releasePointerCapture = () => { capturedPointer = null; };
const topCard = cards()[0];
topCard.getBoundingClientRect = () => ({
    top: 60, bottom: 160, left: 0, right: 100, height: 100, width: 100,
});
d.elementFromPoint = () => topCard.querySelector('img');
const pointer = (type, x, y) => {
    const event = new w.MouseEvent(type, {
        bubbles: true, cancelable: true, button: 0, clientX: x, clientY: y,
    });
    Object.defineProperty(event, 'pointerId', {value: 7});
    touchHandle.dispatchEvent(event);
};
pointer('pointerdown', 80, 135);
pointer('pointermove', 20, 70);
pointer('pointerup', 20, 70);
assert.deepEqual(ids(), [13, 12], 'Touch drag must move the card under the pointer');
assert.equal(hero().dataset.adminPhotoId, '13', 'Touch drag to first must set the Hero pill');
move(cards()[0], 'ArrowRight');
assert.deepEqual(ids(), [12, 13], 'Keyboard can restore the original saved order');
assert.equal(query('[name="admin_photo_gallery_json"]').value, '',
    'Restoring the saved order should not generate a false edit');

// A fresh Site selection loads its own photos, not the Place gallery.
const selector = query('[data-admin-campsite-selector]');
selector.value = 'campsite_record:9';
query('[data-contribution-mode="place"]').setAttribute('aria-pressed', 'false');
query('[data-contribution-mode="site"]').setAttribute('aria-pressed', 'true');
query('[data-contribution-tabs]').dispatchEvent(new w.CustomEvent('llama:contribution-mode'));
Promise.resolve().then(() => {
    assert.deepEqual(ids(), [81, 82], 'Site gallery must load only Site photos');
    assert.equal(hero().dataset.adminPhotoId, '81', 'Site has its own hero');
    assert.equal(query('[data-admin-photo-hero]').textContent, 'Hero');

    const siteCaption = cards()[1].querySelector('[data-admin-photo-caption]');
    siteCaption.value = 'New Site caption';
    siteCaption.dispatchEvent(new w.Event('input', {bubbles: true}));
    assert.deepEqual(payload(), {
        target: siteKey,
        items: [
            {id: 81, alt: 'Campfire setup'},
            {id: 82, alt: 'New Site caption'},
        ],
    }, 'Site caption edits cannot be written against the Place gallery');

    // Attempting to switch away with unsaved edits requires confirmation.
    const event = new w.MouseEvent('click', {bubbles: true, cancelable: true});
    const allowed = query('[data-contribution-mode="place"]').dispatchEvent(event);
    assert.equal(allowed, false, 'Unsaved Site photo edits must block unconfirmed tab switches');
    assert.equal(payload().target, siteKey);

    dom.window.close();
    console.log('PASS: shared inline Place/Site gallery order, Hero, captions, removal, scoped payloads, save confirmation and switch guard');
}).catch(error => {
    dom.window.close();
    console.error(error);
    process.exitCode = 1;
});
