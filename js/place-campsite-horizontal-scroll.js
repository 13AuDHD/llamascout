(() => {
    'use strict';

    // The legacy campsite selector calls scrollIntoView after loading its
    // initial selection. That moves the document vertically on some devices.
    // Constrain that specific request to the horizontal campsite scroller.
    // All unrelated scrollIntoView calls keep their native behavior.
    const nativeScrollIntoView = Element.prototype.scrollIntoView;

    Element.prototype.scrollIntoView = function (options) {
        const list = this.closest?.('[data-campsite-list]');
        if (!list || !list.contains(this)) {
            return nativeScrollIntoView.call(this, options);
        }

        const itemRect = this.getBoundingClientRect();
        const listRect = list.getBoundingClientRect();
        let distance = 0;

        if (itemRect.left < listRect.left) {
            distance = itemRect.left - listRect.left;
        } else if (itemRect.right > listRect.right) {
            distance = itemRect.right - listRect.right;
        }

        if (distance !== 0) {
            list.scrollBy({
                left: distance,
                behavior: options?.behavior === 'smooth' ? 'smooth' : 'auto'
            });
        }
    };
})();
