'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');

const shared = fs.readFileSync(path.resolve(__dirname, '../../js/photo-gallery-editor.js'), 'utf8');
const resources = fs.readFileSync(path.resolve(__dirname, '../../js/admin/resources.js'), 'utf8');
const photos = [1, 2, 3, 4].map((index) => ({
    id: 100 + index,
    src: '/uploads/resources/77/pic-' + index + '.jpg',
    url: 'https://llamascout.com/uploads/resources/77/pic-' + index + '.jpg',
    alt: 'Resource photo ' + index,
    is_featured: index === 1 ? 1 : 0,
    sort_order: index - 1,
}));
const html = '<!doctype html><html><body><form data-resource-editor>'
    + '<script type="application/json" data-resource-photo-catalog>'
    + JSON.stringify(photos) + '</script>'
    + '<input name="resource_gallery_json" type="hidden" value="">'
    + '<div class="add-place-existing-photo-grid" data-resource-photo-gallery'
    + ' data-resource-gallery-id="77"></div>'
    + '<button type="submit">Save Resource</button>'
    + '</form></body></html>';
const dom = new JSDOM(html, {
    url: 'https://admin.llamascout.com/resources.php?id=77',
    runScripts: 'outside-only',
});
const w = dom.window;
const d = w.document;
const form = d.querySelector('form');
const cards = () => Array.from(d.querySelectorAll('[data-admin-photo-id]'));
const ids = () => cards().map(card => Number(card.dataset.adminPhotoId));
const hero = () => cards().find(card => !card.querySelector('[data-admin-photo-hero]').hidden);
const raw = () => form.querySelector('[name="resource_gallery_json"]').value;

w.eval(shared);
w.eval(resources);
assert.deepEqual(ids(), [101, 102, 103, 104], 'Resources use the same gallery as Places and Sites');
assert.equal(raw(), '', 'Opening Resource must not create artificial photo changes');
assert.equal(hero().dataset.adminPhotoId, '101');
assert.equal(cards()[0].querySelector('.admin-attached-photo-handle').title,
    'Hold and drag anywhere in the gallery to reorder');

// One continuous long drag from last to first. The old algorithm effectively
// required several pickup-and-drop cycles on iPad.
const bottom = cards()[3];
const handle = bottom.querySelector('.admin-attached-photo-handle');
const top = cards()[0];
const rect = {
    top: 50, bottom: 160, left: 0, right: 260, width: 260, height: 110,
};
bottom.getBoundingClientRect = () => ({...rect, top: 460, bottom: 570});
top.getBoundingClientRect = () => rect;
d.elementFromPoint = () => top.querySelector('img');
const pointer = (type, x, y) => {
    const event = new w.MouseEvent(type, {
        bubbles: true, cancelable: true, button: 0, clientX: x, clientY: y,
    });
    Object.defineProperty(event, 'pointerId', {value: 19});
    Object.defineProperty(event, 'pointerType', {value: 'touch'});
    (type === 'pointerdown' ? handle : w).dispatchEvent(event);
};
pointer('pointerdown', 230, 520);
pointer('pointermove', 40, 55);
assert.ok(d.querySelector('.admin-photo-drag-ghost'),
    'A floating preview should remain visible during the entire gesture');
pointer('pointerup', 40, 55);
assert.deepEqual(ids(), [104, 101, 102, 103],
    'One long drag must reach the first position without picking up the tile repeatedly');
assert.equal(hero().dataset.adminPhotoId, '104', 'First Resource photo must become Hero');
assert.equal(d.querySelector('.admin-photo-drag-ghost'), null);

const caption = cards()[0].querySelector('[data-admin-photo-caption]');
caption.value = 'Potable water station';
caption.dispatchEvent(new w.Event('input', {bubbles: true}));
const remove = cards()[2].querySelector('[data-admin-photo-remove]');
remove.checked = true;
remove.dispatchEvent(new w.Event('change', {bubbles: true}));
assert.equal(hero().dataset.adminPhotoId, '104');
assert.deepEqual(Array.from(new w.FormData(form).getAll('resource_remove_photo_ids[]')), ['102']);
assert.deepEqual(JSON.parse(raw()), {
    target: 'resource:resource:77',
    items: [
        {id: 104, alt: 'Potable water station'},
        {id: 101, alt: 'Resource photo 1'},
        {id: 103, alt: 'Resource photo 3'},
    ],
});
form.dispatchEvent(new w.Event('submit', {bubbles: true, cancelable: true}));
assert.equal(JSON.parse(raw()).target, 'resource:resource:77');

dom.window.close();
console.log('PASS: Resource shared gallery, long-distance touch drag, Hero, captions, checked removals and Resource-owned save');
