'use strict';

/*
 * The public map applies approved partner branding as scoped stylesheet rules.
 * Do the same for Admin thumbnails; no raw partner colors in inline HTML/CSS.
 * Red verified-closure state is rendered separately and always wins.
 */
document.addEventListener('DOMContentLoaded', () => {
    const images = document.querySelectorAll(
        '.admin-place-list-image.is-partner[data-partner-id][data-partner-color]'
    );
    if (!images.length) return;

    const style = document.createElement('style');
    style.id = 'llama-admin-place-partner-colors';
    document.head.appendChild(style);
    const sheet = style.sheet;
    if (!sheet) return;

    const installed = new Set();
    for (const image of images) {
        const id = Number(image.dataset.partnerId);
        const color = String(image.dataset.partnerColor || '');
        if (!Number.isSafeInteger(id) || id < 1
            || !/^#[0-9A-Fa-f]{6}$/.test(color)
            || installed.has(id)) continue;
        installed.add(id);

        sheet.insertRule(
            '.admin-place-list-image.is-partner[data-partner-id="' + id
            + '"] { --admin-place-partner-color: ' + color + '; }',
            sheet.cssRules.length
        );
    }
});
