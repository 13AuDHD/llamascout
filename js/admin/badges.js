(() => {
    'use strict';

    document
        .querySelectorAll(
            '.admin-badge-definition-form'
        )
        .forEach((form) => {
            const awardType =
                form.querySelector(
                    '[data-badge-award-type]'
                );

            const thresholdFields =
                form.querySelectorAll(
                    '[data-badge-threshold-field]'
                );

            const eligibilityScope =
                form.querySelector(
                    '[name="eligibility_scope"]'
                );

            const recognitionMode =
                form.querySelector(
                    '[name="recognition_mode"]'
                );

            if (
                !awardType
                || thresholdFields.length === 0
            ) {
                return;
            }

            const thresholdControls =
                form.querySelectorAll(
                    '[name="threshold_metric"], '
                    + '[name="threshold_value"]'
                );

            const thresholdMetric =
                form.querySelector(
                    '[data-badge-threshold-metric]'
                );

            const thresholdHelp =
                form.querySelector(
                    '[data-badge-threshold-help]'
                );

            const updateThresholdHelp =
                () => {
                    if (
                        !thresholdMetric
                        || !thresholdHelp
                    ) {
                        return;
                    }

                    const option =
                        thresholdMetric
                            .selectedOptions[0];

                    thresholdHelp.textContent =
                        option?.dataset.description
                        || 'Choose the activity this automatic badge measures.';
                };

            const updateThresholdVisibility =
                () => {
                    const automatic =
                        awardType.value ===
                        'automatic';

                    thresholdFields.forEach(
                        (field) => {
                            field.hidden =
                                !automatic;
                        }
                    );

                    thresholdControls.forEach(
                        (control) => {
                            control.disabled =
                                !automatic;
                            control.required =
                                automatic;
                        }
                    );

                    if (eligibilityScope) {
                        const credential =
                            awardType.value === 'credential';

                        if (credential) {
                            eligibilityScope.value = 'credential';
                            eligibilityScope.disabled = true;

                            if (recognitionMode) {
                                recognitionMode.value = 'permanent';
                            }
                        } else {
                            eligibilityScope.disabled = false;

                            if (eligibilityScope.value === 'credential') {
                                eligibilityScope.value = 'all-members';
                            }
                        }
                    }

                    updateThresholdHelp();
                };

            awardType.addEventListener(
                'change',
                updateThresholdVisibility
            );

            thresholdMetric?.addEventListener(
                'change',
                updateThresholdHelp
            );

            updateThresholdVisibility();
        });
})();

(() => {
    'use strict';

    const picker = document.querySelector(
        '[data-badge-member-picker]'
    );

    if (!picker) {
        return;
    }

    const form = picker.closest(
        '.admin-badge-award-form'
    );

    const searchInput = picker.querySelector(
        '[data-badge-member-search]'
    );

    const selectedInput = picker.querySelector(
        '[data-badge-member-id]'
    );

    const fallbackInput = picker.querySelector(
        '[data-badge-member-fallback]'
    );

    const resultsBox = picker.querySelector(
        '[data-badge-member-results]'
    );

    const help = picker.querySelector(
        '[data-badge-member-help]'
    );

    const submitButton = form?.querySelector(
        'button[type="submit"]'
    );

    const endpoint = (
        picker.dataset.searchEndpoint
        || ''
    ).trim();

    const badgeId = (
        picker.dataset.badgeId
        || ''
    ).trim();

    if (
        !form
        || !searchInput
        || !selectedInput
        || !fallbackInput
        || !resultsBox
        || !submitButton
        || endpoint === ''
        || badgeId === ''
    ) {
        return;
    }

    fallbackInput.name = '';
    fallbackInput.required = false;
    fallbackInput.hidden = true;

    selectedInput.name = 'user_id';
    searchInput.hidden = false;

    if (help) {
        help.hidden = false;
    }

    submitButton.disabled = true;

    let selectedId = '';
    let activeIndex = -1;
    let requestTimer = 0;
    let requestController = null;

    const normalize = (value) =>
        String(value || '').trim();

    const detailsLabel = (member) => {
        const details = [];

        if (member.username) {
            details.push(`@${member.username}`);
        }

        if (member.email) {
            details.push(member.email);
        }

        details.push(`User #${member.id}`);

        return details.join(' · ');
    };

    const primaryLabel = (member) =>
        normalize(member.display_name)
        || (
            normalize(member.username) !== ''
                ? `@${member.username}`
                : ''
        )
        || normalize(member.email)
        || `User #${member.id}`;

    const selectedLabel = (member) =>
        `${primaryLabel(member)} · ${detailsLabel(member)}`;

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

    const selectableButtons = () =>
        Array.from(
            resultsBox.querySelectorAll(
                '[data-badge-member-result]:not(:disabled)'
            )
        );

    const setActiveResult = (index) => {
        const buttons = selectableButtons();

        if (buttons.length === 0) {
            activeIndex = -1;
            return;
        }

        activeIndex = Math.max(
            0,
            Math.min(index, buttons.length - 1)
        );

        buttons.forEach((button, buttonIndex) => {
            const active =
                buttonIndex === activeIndex;

            button.classList.toggle(
                'is-active',
                active
            );

            button.setAttribute(
                'aria-selected',
                active ? 'true' : 'false'
            );
        });

        const button = buttons[activeIndex];

        if (button) {
            searchInput.setAttribute(
                'aria-activedescendant',
                button.id
            );

            button.scrollIntoView({
                block: 'nearest',
            });
        }
    };

    const chooseMember = (member) => {
        if (
            member.already_has_badge
            || member.eligible === false
        ) {
            return;
        }

        selectedId = String(member.id);
        selectedInput.value = selectedId;
        searchInput.value = selectedLabel(member);
        searchInput.setCustomValidity('');
        submitButton.disabled = false;
        closeResults();
    };

    const renderMessage = (message) => {
        resultsBox.replaceChildren();

        const node = document.createElement('div');
        node.className =
            'admin-badge-member-message';
        node.textContent = message;

        resultsBox.appendChild(node);
        resultsBox.hidden = false;
        searchInput.setAttribute(
            'aria-expanded',
            'true'
        );
        activeIndex = -1;
    };

    const renderResults = (members) => {
        resultsBox.replaceChildren();
        activeIndex = -1;

        if (members.length === 0) {
            renderMessage(
                'No members match that search.'
            );
            return;
        }

        members.forEach((member) => {
            const button =
                document.createElement('button');

            button.type = 'button';
            button.id =
                `badge-member-result-${member.id}`;
            button.className =
                'admin-badge-member-result';
            button.dataset.badgeMemberResult =
                String(member.id);
            button.setAttribute(
                'role',
                'option'
            );
            button.setAttribute(
                'aria-selected',
                'false'
            );

            if (
                member.already_has_badge
                || member.eligible === false
            ) {
                button.disabled = true;
                button.classList.add(
                    'is-unavailable'
                );
            }

            const name =
                document.createElement('strong');
            name.textContent =
                primaryLabel(member);

            const details =
                document.createElement('span');
            details.textContent =
                detailsLabel(member);

            button.append(name, details);

            if (member.already_has_badge) {
                const status =
                    document.createElement('small');
                status.textContent =
                    'Already has this badge';
                button.appendChild(status);
            } else if (member.eligible === false) {
                const status =
                    document.createElement('small');
                status.textContent =
                    `Not eligible for ${member.eligibility_label || 'this badge track'}`;
                button.appendChild(status);
            }

            button.addEventListener(
                'click',
                () => chooseMember(member)
            );

            resultsBox.appendChild(button);
        });

        resultsBox.hidden = false;
        searchInput.setAttribute(
            'aria-expanded',
            'true'
        );
    };

    const requestResults = async () => {
        const query = searchInput.value.trim();
        const matchQuery = query.startsWith('@')
            ? query.slice(1).trim()
            : query;

        if (matchQuery === '') {
            closeResults();
            return;
        }

        if (
            !/^\d+$/.test(matchQuery)
            && matchQuery.length < 2
        ) {
            renderMessage(
                'Type at least 2 characters, or enter a user ID.'
            );
            return;
        }

        if (requestController) {
            requestController.abort();
        }

        requestController =
            new AbortController();

        resultsBox.setAttribute(
            'aria-busy',
            'true'
        );

        try {
            const url = new URL(
                endpoint,
                window.location.origin
            );
            url.searchParams.set('q', query);
            url.searchParams.set(
                'badge_id',
                badgeId
            );

            const response = await fetch(
                url.toString(),
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                    },
                    signal: requestController.signal,
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Search failed (${response.status})`
                );
            }

            const payload = await response.json();

            if (!payload || payload.ok !== true) {
                throw new Error(
                    payload?.message
                    || 'Member search failed.'
                );
            }

            renderResults(
                Array.isArray(payload.results)
                    ? payload.results
                    : []
            );
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            renderMessage(
                'Member search is temporarily unavailable.'
            );
        } finally {
            resultsBox.removeAttribute(
                'aria-busy'
            );
        }
    };

    const queueSearch = () => {
        window.clearTimeout(requestTimer);
        requestTimer = window.setTimeout(
            requestResults,
            180
        );
    };

    searchInput.addEventListener(
        'input',
        () => {
            if (requestController) {
                requestController.abort();
                requestController = null;
            }

            if (selectedId !== '') {
                selectedId = '';
                selectedInput.value = '';
                submitButton.disabled = true;
            }

            searchInput.setCustomValidity('');
            queueSearch();
        }
    );

    searchInput.addEventListener(
        'focus',
        () => {
            if (
                selectedId === ''
                && searchInput.value.trim() !== ''
            ) {
                queueSearch();
            }
        }
    );

    searchInput.addEventListener(
        'keydown',
        (event) => {
            const buttons = selectableButtons();

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActiveResult(activeIndex + 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveResult(
                    activeIndex <= 0
                        ? buttons.length - 1
                        : activeIndex - 1
                );
                return;
            }

            if (
                event.key === 'Enter'
                && !resultsBox.hidden
                && activeIndex >= 0
                && buttons[activeIndex]
            ) {
                event.preventDefault();
                buttons[activeIndex].click();
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
                    'Choose a member from the search results.'
                );
                searchInput.reportValidity();
                searchInput.focus();
            }
        }
    );
})();
