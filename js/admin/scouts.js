(() => {
    'use strict';

    const picker = document.querySelector(
        '[data-scout-member-picker]'
    );

    if (!picker) {
        return;
    }

    const form = picker.closest(
        '[data-scout-invite-form]'
    );

    const searchInput = picker.querySelector(
        '[data-scout-member-search]'
    );

    const selectedInput = picker.querySelector(
        '[data-scout-member-id]'
    );

    const fallbackSelect = picker.querySelector(
        '[data-scout-member-fallback]'
    );

    const resultsBox = picker.querySelector(
        '[data-scout-member-results]'
    );

    const help = picker.querySelector(
        '[data-scout-member-help]'
    );

    const submitButton = form?.querySelector(
        '[data-scout-invite-submit]'
    );

    if (
        !form
        || !searchInput
        || !selectedInput
        || !fallbackSelect
        || !resultsBox
        || !submitButton
    ) {
        return;
    }

    const candidates = Array.from(
        fallbackSelect.options
    )
        .filter((option) => option.value !== '')
        .map((option) => ({
            id: String(
                option.dataset.userId
                || option.value
            ),
            username: (
                option.dataset.username
                || ''
            ).trim(),
            email: (
                option.dataset.email
                || ''
            ).trim(),
            displayName: (
                option.dataset.displayName
                || ''
            ).trim(),
            status: (
                option.dataset.status
                || ''
            ).trim(),
        }));

    fallbackSelect.name = '';
    fallbackSelect.required = false;
    fallbackSelect.hidden = true;

    selectedInput.name = 'candidate_user_id';

    searchInput.hidden = false;

    if (help) {
        help.hidden = false;
    }

    submitButton.disabled = true;

    let visibleCandidates = [];
    let activeIndex = -1;
    let selectedId = '';

    const normalize = (value) =>
        String(value || '')
            .trim()
            .toLowerCase();

    const resultLabel = (candidate) => {
        const pieces = [];

        if (candidate.username !== '') {
            pieces.push(`@${candidate.username}`);
        }

        if (candidate.email !== '') {
            pieces.push(candidate.email);
        }

        pieces.push(`User #${candidate.id}`);

        return pieces.join(' · ');
    };

    const selectedLabel = (candidate) => {
        const primary =
            candidate.displayName
            || (
                candidate.username !== ''
                    ? `@${candidate.username}`
                    : candidate.email
            )
            || `User #${candidate.id}`;

        return `${primary} · ${resultLabel(candidate)}`;
    };

    const statusLabel = (status) => {
        if (status === 'invited') {
            return 'Resend invitation';
        }

        if (status === 'declined') {
            return 'Previously declined';
        }

        return '';
    };

    const closeResults = () => {
        resultsBox.hidden = true;
        searchInput.setAttribute(
            'aria-expanded',
            'false'
        );
        searchInput.removeAttribute(
            'aria-activedescendant'
        );
        activeIndex = -1;
    };

    const setActiveResult = (index) => {
        const buttons = Array.from(
            resultsBox.querySelectorAll(
                '[data-scout-member-result]'
            )
        );

        if (buttons.length === 0) {
            activeIndex = -1;
            return;
        }

        activeIndex = Math.max(
            0,
            Math.min(index, buttons.length - 1)
        );

        buttons.forEach((button, buttonIndex) => {
            const isActive =
                buttonIndex === activeIndex;

            button.classList.toggle(
                'is-active',
                isActive
            );

            button.setAttribute(
                'aria-selected',
                isActive ? 'true' : 'false'
            );
        });

        const activeButton = buttons[activeIndex];

        if (activeButton) {
            searchInput.setAttribute(
                'aria-activedescendant',
                activeButton.id
            );

            activeButton.scrollIntoView({
                block: 'nearest',
            });
        }
    };

    const chooseCandidate = (candidate) => {
        selectedId = candidate.id;
        selectedInput.value = candidate.id;
        searchInput.value = selectedLabel(candidate);
        searchInput.setCustomValidity('');
        submitButton.disabled = false;
        closeResults();
    };

    const rankCandidate = (
        candidate,
        query
    ) => {
        const username = normalize(
            candidate.username
        );
        const email = normalize(
            candidate.email
        );
        const displayName = normalize(
            candidate.displayName
        );
        const id = normalize(
            candidate.id
        );

        if (
            id === query
            || username === query
            || email === query
        ) {
            return 0;
        }

        if (
            username.startsWith(query)
            || email.startsWith(query)
            || id.startsWith(query)
        ) {
            return 1;
        }

        if (displayName.startsWith(query)) {
            return 2;
        }

        return 3;
    };

    const matchingCandidates = (query) => {
        const cleanQuery = normalize(query);

        if (cleanQuery === '') {
            return candidates.slice(0, 8);
        }

        return candidates
            .filter((candidate) => {
                const fields = [
                    candidate.username,
                    candidate.email,
                    candidate.id,
                    candidate.displayName,
                ];

                return fields.some((field) =>
                    normalize(field).includes(
                        cleanQuery
                    )
                );
            })
            .sort((a, b) => {
                const rankDifference =
                    rankCandidate(a, cleanQuery)
                    - rankCandidate(b, cleanQuery);

                if (rankDifference !== 0) {
                    return rankDifference;
                }

                return resultLabel(a)
                    .localeCompare(resultLabel(b));
            })
            .slice(0, 10);
    };

    const renderResults = () => {
        visibleCandidates = matchingCandidates(
            searchInput.value
        );

        resultsBox.replaceChildren();
        activeIndex = -1;

        if (visibleCandidates.length === 0) {
            const empty = document.createElement(
                'div'
            );

            empty.className =
                'admin-scout-member-no-results';

            empty.textContent =
                'No eligible members match that search.';

            resultsBox.appendChild(empty);
        } else {
            visibleCandidates.forEach(
                (candidate, index) => {
                    const button =
                        document.createElement(
                            'button'
                        );

                    button.type = 'button';
                    button.id =
                        `scout-member-result-${candidate.id}`;
                    button.className =
                        'admin-scout-member-result';
                    button.dataset.scoutMemberResult =
                        candidate.id;
                    button.setAttribute(
                        'role',
                        'option'
                    );
                    button.setAttribute(
                        'aria-selected',
                        'false'
                    );

                    const name =
                        document.createElement(
                            'strong'
                        );

                    name.textContent =
                        candidate.displayName
                        || candidate.username
                        || candidate.email
                        || `User #${candidate.id}`;

                    const details =
                        document.createElement(
                            'span'
                        );

                    details.textContent =
                        resultLabel(candidate);

                    button.append(
                        name,
                        details
                    );

                    const status = statusLabel(
                        candidate.status
                    );

                    if (status !== '') {
                        const statusNode =
                            document.createElement(
                                'small'
                            );

                        statusNode.textContent =
                            status;

                        button.appendChild(
                            statusNode
                        );
                    }

                    button.addEventListener(
                        'mouseenter',
                        () => {
                            setActiveResult(index);
                        }
                    );

                    button.addEventListener(
                        'click',
                        () => {
                            chooseCandidate(
                                candidate
                            );
                        }
                    );

                    resultsBox.appendChild(
                        button
                    );
                }
            );
        }

        resultsBox.hidden = false;
        searchInput.setAttribute(
            'aria-expanded',
            'true'
        );
    };

    searchInput.addEventListener(
        'focus',
        () => {
            renderResults();
        }
    );

    searchInput.addEventListener(
        'input',
        () => {
            if (selectedId !== '') {
                selectedId = '';
                selectedInput.value = '';
                submitButton.disabled = true;
            }

            searchInput.setCustomValidity('');
            renderResults();
        }
    );

    searchInput.addEventListener(
        'keydown',
        (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();

                if (resultsBox.hidden) {
                    renderResults();
                }

                setActiveResult(
                    activeIndex + 1
                );

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();

                if (resultsBox.hidden) {
                    renderResults();
                }

                setActiveResult(
                    activeIndex <= 0
                        ? visibleCandidates.length - 1
                        : activeIndex - 1
                );

                return;
            }

            if (
                event.key === 'Enter'
                && !resultsBox.hidden
                && activeIndex >= 0
                && visibleCandidates[activeIndex]
            ) {
                event.preventDefault();
                chooseCandidate(
                    visibleCandidates[activeIndex]
                );
                return;
            }

            if (event.key === 'Escape') {
                closeResults();
            }
        }
    );

    document.addEventListener(
        'pointerdown',
        (event) => {
            if (!picker.contains(event.target)) {
                closeResults();
            }
        }
    );

    form.addEventListener(
        'submit',
        (event) => {
            if (selectedInput.value === '') {
                event.preventDefault();

                searchInput.setCustomValidity(
                    'Choose an eligible member from the search results.'
                );

                searchInput.reportValidity();
                searchInput.focus();
                renderResults();
            }
        }
    );
})();
