(() => {
    'use strict';

    // The older campsite browser calls scrollIntoView for every selection.
    // Keep that movement inside its horizontal list, without scrolling the page.
    const originalScrollIntoView = Element.prototype.scrollIntoView;
    Element.prototype.scrollIntoView = function (options) {
        const list = this.closest?.('[data-campsite-list]');
        if (!list || !list.contains(this)) {
            return originalScrollIntoView.call(this, options);
        }
        const listBounds = list.getBoundingClientRect();
        const itemBounds = this.getBoundingClientRect();
        const leftEdge = listBounds.left + 8;
        const rightEdge = listBounds.right - 8;
        let next = list.scrollLeft;
        if (itemBounds.left < leftEdge) next += itemBounds.left - leftEdge;
        if (itemBounds.right > rightEdge) next += itemBounds.right - rightEdge;
        next = Math.max(0, Math.min(next, list.scrollWidth - list.clientWidth));
        if (Math.abs(next - list.scrollLeft) > 1) {
            list.scrollTo({left: next, behavior: options?.behavior === 'smooth' ? 'smooth' : 'auto'});
        }
    };

    const init = () => {
        const section = document.querySelector('[data-stay-options]');
        if (!section) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'place-stay-scroll-navigation';
        wrapper.setAttribute('aria-label', 'Scroll overnight options');
        const back = document.createElement('button');
        const next = document.createElement('button');
        back.type = next.type = 'button';
        back.textContent = '‹';
        next.textContent = '›';
        back.setAttribute('aria-label', 'Scroll to previous overnight options');
        next.setAttribute('aria-label', 'Scroll to next overnight options');
        wrapper.append(back, next);
        const slot = section.querySelector('[data-stay-browser-slot]');
        if (!slot) return;
        slot.after(wrapper);

        let activeList = null;
        let observed = null;
        const detect = () => {
            const browserList = section.querySelector('[data-campsite-list]');
            const fallbackList = section.querySelector('[data-stay-parking-list]:not([hidden])');
            const list = browserList || fallbackList;
            if (!list || list === activeList) return;
            activeList = list;
            const update = () => {
                const limit = Math.max(0, list.scrollWidth - list.clientWidth);
                back.disabled = list.scrollLeft < 2;
                next.disabled = list.scrollLeft >= limit - 2;
                wrapper.hidden = limit < 5;
            };
            back.onclick = () => list.scrollBy({left: -Math.max(170, Math.round(list.clientWidth * .82)), behavior: 'smooth'});
            next.onclick = () => list.scrollBy({left: Math.max(170, Math.round(list.clientWidth * .82)), behavior: 'smooth'});
            list.addEventListener('scroll', update, {passive: true});
            window.addEventListener('resize', update, {passive: true});
            if (observed) observed.disconnect();
            observed = new MutationObserver(() => requestAnimationFrame(update));
            observed.observe(list, {childList: true, subtree: false});
            requestAnimationFrame(update);
        };
        const observer = new MutationObserver(detect);
        observer.observe(section, {childList: true, subtree: true});
        detect();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
