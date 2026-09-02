document.addEventListener('DOMContentLoaded', () => {
    const productRoot =
        document.querySelector(
            '[data-public-product-url]'
        );

    const productUrl =
        productRoot?.dataset.publicProductUrl || '';

    if (!productUrl) {
        return;
    }

    [
        document.querySelector(
            '.admin-topbar-title strong'
        ),

        document.querySelector(
            '.admin-page-header h1'
        ),
    ].forEach((title) => {
        if (
            !title ||
            title.querySelector('a')
        ) {
            return;
        }

        const link =
            document.createElement('a');

        link.href =
            productUrl;

        link.target =
            '_blank';

        link.rel =
            'noopener';

        link.className =
            'admin-product-public-link';

        link.textContent =
            title.textContent;

        link.title =
            'Open public product page';

        title.replaceChildren(
            link
        );
    });
});
