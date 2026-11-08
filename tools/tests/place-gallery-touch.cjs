'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const dom = new JSDOM(`
    <div data-place-gallery>
        <button data-gallery-open><img src="/one.jpg" alt="One"></button>
        <button data-gallery-open><img src="/two.jpg" alt="Two"></button>
        <button data-gallery-open><img src="/three.jpg" alt="Three"></button>
    </div>
    <dialog id="place-gallery-lightbox">
        <img id="place-gallery-large-image" alt="">
        <span id="place-gallery-counter"></span>
        <button id="place-gallery-previous">Previous</button>
        <button id="place-gallery-next">Next</button>
        <div id="place-gallery-lightbox-thumbs"></div>
    </dialog>
`, { runScripts: 'outside-only' });
const { window } = dom;
const { document } = window;
window.Element.prototype.scrollIntoView = () => {};
window.eval(fs.readFileSync(path.resolve(__dirname, '../../js/place-gallery.js'), 'utf8'));

const next = document.getElementById('place-gallery-next');
const previous = document.getElementById('place-gallery-previous');
const counter = document.getElementById('place-gallery-counter');
const gesture = (target, start, end) => {
    target.dispatchEvent(new window.TouchEvent('touchstart', {
        bubbles: true, touches: [{ clientX: start }],
    }));
    target.dispatchEvent(new window.TouchEvent('touchend', {
        bubbles: true, changedTouches: [{ clientX: end }],
    }));
};

document.querySelector('[data-gallery-open]').click();
gesture(next, 280, 280);
next.click();
assert.equal(counter.textContent, '2 of 3', 'A tap on Next advances once');

gesture(previous, 58, 58);
previous.click();
assert.equal(counter.textContent, '1 of 3', 'A tap on Previous goes back once');

const image = document.getElementById('place-gallery-large-image');
gesture(image, 250, 140);
assert.equal(counter.textContent, '2 of 3', 'A left swipe advances');
gesture(image, 140, 250);
assert.equal(counter.textContent, '1 of 3', 'A right swipe goes back');
window.close();
console.log('PASS: Gallery taps and swipes move exactly one photo in the correct direction.');
