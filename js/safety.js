(() => {
    'use strict';

    const backButton =
        document.querySelector(
            '[data-safety-back]'
        );

    if (!backButton) {
        return;
    }

    backButton.addEventListener(
        'click',
        () => {
            /*
             * Browser history is the closest match to "return to what I was
             * doing". If this tab has no usable history, return home instead.
             */
            if (
                window.history.length > 1
            ) {
                window.history.back();
                return;
            }

            window.location.assign(
                'https://llamascout.com/'
            );
        }
    );
})();
