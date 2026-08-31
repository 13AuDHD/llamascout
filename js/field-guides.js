(() => {
    'use strict';

    const cards = Array.from(
        document.querySelectorAll('[data-field-guide-card]')
    );

    if (!cards.length) {
        return;
    }

    const search = document.getElementById('field-guide-search');
    const category = document.getElementById('field-guide-category');
    const status = document.getElementById('field-guides-status');
    const empty = document.getElementById('field-guides-empty');

    const render = () => {
        const query =
            String(search?.value || '')
                .trim()
                .toLowerCase();

        const selectedCategory =
            String(category?.value || '');

        let shown = 0;

        cards.forEach((card) => {
            const searchable =
                String(card.dataset.search || '');

            const cardCategory =
                String(card.dataset.category || '');

            const matchesSearch =
                !query ||
                searchable.includes(query);

            const matchesCategory =
                !selectedCategory ||
                cardCategory === selectedCategory;

            const visible =
                matchesSearch &&
                matchesCategory;

            card.hidden = !visible;

            if (visible) {
                shown++;
            }
        });

        if (status) {
            status.textContent =
                `${shown} Field Guide${shown === 1 ? '' : 's'}`;
        }

        if (empty) {
            empty.hidden = shown !== 0;
        }
    };

    search?.addEventListener('input', render);
    category?.addEventListener('change', render);

    render();
})();
