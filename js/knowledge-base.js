(() => {
    'use strict';

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const articleUrl = (slug) =>
        `/help-article.php?slug=${encodeURIComponent(slug)}`;

    function initializeSearch() {
        const shells =
            document.querySelectorAll(
                '[data-kb-search-shell]'
            );

        shells.forEach((shell) => {
            const input =
                shell.querySelector(
                    '[data-kb-search]'
                );

            const results =
                shell.querySelector(
                    '[data-kb-search-results]'
                );

            const clear =
                shell.querySelector(
                    '[data-kb-search-clear]'
                );

            if (!input || !results || !clear) {
                return;
            }

            let timer = null;
            let controller = null;
            let lastQuery = '';
            let lastCompletedQuery = '';

            const setOpen = (open) => {
                results.hidden = !open;

                input.setAttribute(
                    'aria-expanded',
                    open ? 'true' : 'false'
                );
            };

            const showMessage = (
                message,
                className = ''
            ) => {
                results.innerHTML =
                    `<div class="kb-search-message ${className}">${escapeHtml(message)}</div>`;

                setOpen(true);
            };

            const recordClick = (
                searchId,
                articleId
            ) => {
                if (
                    !Number.isInteger(searchId)
                    || searchId <= 0
                    || !Number.isInteger(articleId)
                    || articleId <= 0
                ) {
                    return;
                }

                const body =
                    new URLSearchParams({
                        search_id: String(searchId),
                        article_id: String(articleId)
                    });

                fetch(
                    '/api/knowledge-base-search-click.php',
                    {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded;charset=UTF-8',
                            Accept: 'application/json'
                        }
                    }
                ).catch(() => {
                    // Analytics must never interfere with navigation.
                });
            };

            const renderResults = (
                query,
                rows,
                searchId
            ) => {
                if (input.value.trim() !== query) {
                    return;
                }

                if (!rows.length) {
                    results.innerHTML = [
                        '<div class="kb-search-empty">',
                        '<strong>No answer found yet.</strong>',
                        '<span>Try another word, browse a Topic, or ask Support. The llama may simply not have written this one down yet.</span>',
                        '</div>'
                    ].join('');

                    setOpen(true);
                    return;
                }

                const html =
                    rows.map((row) => {
                        const title =
                            escapeHtml(
                                row.title || ''
                            );

                        const summary =
                            escapeHtml(
                                row.summary || ''
                            );

                        const category =
                            escapeHtml(
                                row.category_name || ''
                            );

                        const href =
                            articleUrl(
                                row.slug || ''
                            );

                        const articleId =
                            Number.parseInt(
                                row.id,
                                10
                            ) || 0;

                        return [
                            `<a class="kb-search-result" href="${href}" data-kb-search-result data-search-id="${searchId}" data-article-id="${articleId}">`,
                            '<span class="kb-search-result-topic">',
                            category,
                            '</span>',
                            `<strong>${title}</strong>`,
                            summary
                                ? `<p>${summary}</p>`
                                : '',
                            '<span class="kb-search-result-link">Read article →</span>',
                            '</a>'
                        ].join('');
                    }).join('');

                results.innerHTML =
                    `<div class="kb-search-result-list">${html}</div>`;

                results
                    .querySelectorAll(
                        '[data-kb-search-result]'
                    )
                    .forEach((link) => {
                        link.addEventListener(
                            'click',
                            () => {
                                recordClick(
                                    Number.parseInt(
                                        link.dataset.searchId || '0',
                                        10
                                    ),
                                    Number.parseInt(
                                        link.dataset.articleId || '0',
                                        10
                                    )
                                );
                            }
                        );
                    });

                setOpen(true);
            };

            const runSearch = async () => {
                const query =
                    input.value.trim();

                lastQuery = query;
                clear.hidden = query === '';

                if (query.length < 2) {
                    results.replaceChildren();
                    setOpen(false);
                    lastCompletedQuery = '';
                    return;
                }

                if (
                    query === lastCompletedQuery
                    && results.childNodes.length
                ) {
                    setOpen(true);
                    return;
                }

                if (controller) {
                    controller.abort();
                }

                controller =
                    new AbortController();

                results.innerHTML =
                    '<div class="kb-search-message">Searching the llama library…</div>';

                setOpen(true);

                try {
                    const response =
                        await fetch(
                            `/api/knowledge-base-search.php?q=${encodeURIComponent(query)}`,
                            {
                                headers: {
                                    Accept:
                                        'application/json'
                                },
                                signal:
                                    controller.signal,
                                credentials:
                                    'same-origin'
                            }
                        );

                    if (!response.ok) {
                        throw new Error(
                            'Search request failed'
                        );
                    }

                    const payload =
                        await response.json();

                    if (query !== lastQuery) {
                        return;
                    }

                    lastCompletedQuery = query;

                    renderResults(
                        query,
                        Array.isArray(
                            payload.results
                        )
                            ? payload.results
                            : [],
                        Number.parseInt(
                            payload.search_id || 0,
                            10
                        ) || 0
                    );
                } catch (error) {
                    if (
                        error
                        && error.name === 'AbortError'
                    ) {
                        return;
                    }

                    if (query !== lastQuery) {
                        return;
                    }

                    showMessage(
                        'Knowledge Base search is temporarily unavailable.',
                        'is-error'
                    );
                }
            };

            input.addEventListener(
                'input',
                () => {
                    window.clearTimeout(timer);

                    timer =
                        window.setTimeout(
                            runSearch,
                            320
                        );
                }
            );

            input.addEventListener(
                'keydown',
                (event) => {
                    if (event.key === 'Escape') {
                        setOpen(false);
                        input.blur();
                    }
                }
            );

            clear.addEventListener(
                'click',
                () => {
                    if (controller) {
                        controller.abort();
                    }

                    window.clearTimeout(timer);
                    input.value = '';
                    lastQuery = '';
                    lastCompletedQuery = '';
                    clear.hidden = true;
                    results.replaceChildren();
                    setOpen(false);
                    input.focus();
                }
            );

            document.addEventListener(
                'click',
                (event) => {
                    if (!shell.contains(event.target)) {
                        setOpen(false);
                    }
                }
            );

            input.addEventListener(
                'focus',
                () => {
                    if (
                        input.value.trim().length >= 2
                        && results.childNodes.length
                    ) {
                        setOpen(true);
                    }
                }
            );
        });
    }

    function initializeFeedback() {
        const widgets =
            document.querySelectorAll(
                '[data-kb-feedback]'
            );

        widgets.forEach((widget) => {
            const articleId =
                Number.parseInt(
                    widget.dataset.articleId || '0',
                    10
                ) || 0;

            const csrfToken =
                widget.dataset.csrfToken || '';

            const yesButton =
                widget.querySelector(
                    '[data-kb-feedback-yes]'
                );

            const noButton =
                widget.querySelector(
                    '[data-kb-feedback-no]'
                );

            const buttons =
                widget.querySelector(
                    '[data-kb-feedback-buttons]'
                );

            const reasonPanel =
                widget.querySelector(
                    '[data-kb-feedback-reason]'
                );

            const reasonText =
                widget.querySelector(
                    '[data-kb-feedback-reason-text]'
                );

            const sendNo =
                widget.querySelector(
                    '[data-kb-feedback-send-no]'
                );

            const skipNo =
                widget.querySelector(
                    '[data-kb-feedback-skip-no]'
                );

            const status =
                widget.querySelector(
                    '[data-kb-feedback-status]'
                );

            if (
                articleId <= 0
                || !csrfToken
                || !yesButton
                || !noButton
                || !buttons
                || !reasonPanel
                || !reasonText
                || !sendNo
                || !skipNo
                || !status
            ) {
                return;
            }

            let busy = false;

            const setBusy = (value) => {
                busy = value;

                [
                    yesButton,
                    noButton,
                    sendNo,
                    skipNo
                ].forEach((button) => {
                    button.disabled = value;
                });
            };

            const showStatus = (
                message,
                success
            ) => {
                status.textContent = message;
                status.hidden = false;
                status.classList.toggle(
                    'is-success',
                    success
                );
                status.classList.toggle(
                    'is-error',
                    !success
                );
            };

            const submitFeedback = async (
                helpful,
                reason = ''
            ) => {
                if (busy) {
                    return;
                }

                setBusy(true);
                showStatus(
                    'Saving your feedback…',
                    true
                );

                try {
                    const response =
                        await fetch(
                            '/api/knowledge-base-feedback.php',
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type':
                                        'application/json',
                                    Accept:
                                        'application/json'
                                },
                                body: JSON.stringify({
                                    article_id:
                                        articleId,
                                    helpful,
                                    reason,
                                    csrf_token:
                                        csrfToken
                                })
                            }
                        );

                    const payload =
                        await response.json();

                    if (
                        !response.ok
                        || !payload.ok
                    ) {
                        throw new Error(
                            payload.message
                            || 'Feedback could not be saved.'
                        );
                    }

                    buttons.hidden = true;
                    reasonPanel.hidden = true;

                    showStatus(
                        payload.message
                        || 'Thanks for the feedback.',
                        true
                    );
                } catch (error) {
                    showStatus(
                        error instanceof Error
                            ? error.message
                            : 'Feedback could not be saved right now.',
                        false
                    );

                    setBusy(false);
                }
            };

            yesButton.addEventListener(
                'click',
                () => {
                    submitFeedback(
                        true,
                        ''
                    );
                }
            );

            noButton.addEventListener(
                'click',
                () => {
                    reasonPanel.hidden = false;
                    status.hidden = true;

                    window.requestAnimationFrame(
                        () => reasonText.focus()
                    );
                }
            );

            sendNo.addEventListener(
                'click',
                () => {
                    submitFeedback(
                        false,
                        reasonText.value.trim()
                    );
                }
            );

            skipNo.addEventListener(
                'click',
                () => {
                    submitFeedback(
                        false,
                        ''
                    );
                }
            );
        });
    }

    initializeSearch();
    initializeFeedback();
})();
