(() => {
    'use strict';

    const picker = document.querySelector(
        '[data-member-picker]'
    );

    const form = document.querySelector(
        '[data-points-adjustment-form]'
    );

    if (!picker || !form) {
        return;
    }

    const search = picker.querySelector(
        '[data-member-search]'
    );

    const userId = picker.querySelector(
        '[data-member-id]'
    );

    const results = picker.querySelector(
        '[data-member-results]'
    );

    const selected = picker.querySelector(
        '[data-member-selected]'
    );

    const options = Array.from(
        picker.querySelectorAll(
            '[data-member-option]'
        )
    );

    if (
        !search
        || !userId
        || !results
        || !selected
    ) {
        return;
    }

    let visibleOptions = [];
    let activeIndex = -1;

    const normalize = (value) =>
        String(value || '')
            .trim()
            .toLowerCase();

    const setExpanded = (expanded) => {
        search.setAttribute(
            'aria-expanded',
            expanded ? 'true' : 'false'
        );

        results.hidden = !expanded;
    };

    const clearActive = () => {
        options.forEach((option) => {
            option.classList.remove('is-active');
        });

        activeIndex = -1;
    };

    const setActive = (index) => {
        clearActive();

        if (
            index < 0
            || index >= visibleOptions.length
        ) {
            return;
        }

        activeIndex = index;

        const option =
            visibleOptions[activeIndex];

        option.classList.add('is-active');
        option.scrollIntoView({
            block: 'nearest',
        });
    };

    const render = () => {
        const query = normalize(search.value);
        visibleOptions = [];
        clearActive();

        if (query === '') {
            options.forEach((option) => {
                option.hidden = true;
            });

            setExpanded(false);
            return;
        }

        let shown = 0;

        options.forEach((option) => {
            const haystack = normalize(
                option.dataset.memberSearchText
            );

            const matches =
                shown < 10
                && haystack.includes(query);

            option.hidden = !matches;

            if (matches) {
                visibleOptions.push(option);
                shown++;
            }
        });

        setExpanded(
            visibleOptions.length > 0
        );
    };

    const choose = (option) => {
        const id =
            option.dataset.memberId || '';

        const label =
            option.dataset.memberLabel || '';

        const meta =
            option.dataset.memberMeta || '';

        userId.value = id;
        search.value = label;

        selected.textContent =
            meta !== ''
                ? meta
                : `Selected member #${id}`;

        selected.classList.remove('is-error');
        selected.classList.add('is-selected');

        options.forEach((item) => {
            item.hidden = true;
        });

        setExpanded(false);
        clearActive();
    };

    search.addEventListener('input', () => {
        userId.value = '';
        selected.textContent =
            'Search for a member, then select the correct account.';
        selected.classList.remove(
            'is-selected',
            'is-error'
        );

        render();
    });

    search.addEventListener('focus', () => {
        render();
    });

    options.forEach((option) => {
        option.addEventListener('click', () => {
            choose(option);
        });
    });

    search.addEventListener('keydown', (event) => {
        if (
            event.key === 'ArrowDown'
            || event.key === 'ArrowUp'
        ) {
            if (!visibleOptions.length) {
                render();
            }

            if (!visibleOptions.length) {
                return;
            }

            event.preventDefault();

            const direction =
                event.key === 'ArrowDown'
                    ? 1
                    : -1;

            let next =
                activeIndex + direction;

            if (next < 0) {
                next = visibleOptions.length - 1;
            }

            if (next >= visibleOptions.length) {
                next = 0;
            }

            setActive(next);
            return;
        }

        if (
            event.key === 'Enter'
            && activeIndex >= 0
            && visibleOptions[activeIndex]
        ) {
            event.preventDefault();
            choose(
                visibleOptions[activeIndex]
            );
            return;
        }

        if (event.key === 'Escape') {
            setExpanded(false);
            clearActive();
        }
    });

    document.addEventListener('click', (event) => {
        if (!picker.contains(event.target)) {
            setExpanded(false);
            clearActive();
        }
    });

    form.addEventListener('submit', (event) => {
        if (String(userId.value).trim() !== '') {
            return;
        }

        event.preventDefault();

        selected.textContent =
            'Select a member from the search results before recording points.';
        selected.classList.remove('is-selected');
        selected.classList.add('is-error');

        search.focus();
        render();
    });
})();
