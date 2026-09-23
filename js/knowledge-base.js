(() => {
    'use strict';

    const shells = document.querySelectorAll('[data-kb-search-shell]');

    if (!shells.length) {
        return;
    }

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const articleUrl = (slug) =>
        `/help-article.php?slug=${encodeURIComponent(slug)}`;

    shells.forEach((shell) => {
        const input = shell.querySelector('[data-kb-search]');
        const results = shell.querySelector('[data-kb-search-results]');
        const clear = shell.querySelector('[data-kb-search-clear]');

        if (!input || !results || !clear) {
            return;
        }

        let timer = null;
        let controller = null;
        let lastQuery = '';

        const setOpen = (open) => {
            results.hidden = !open;
            input.setAttribute(
                'aria-expanded',
                open ? 'true' : 'false'
            );
        };

        const showMessage = (message, className = '') => {
            results.innerHTML =
                `<div class="kb-search-message ${className}">${escapeHtml(message)}</div>`;
            setOpen(true);
        };

        const renderResults = (query, rows) => {
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

            const html = rows.map((row) => {
                const title = escapeHtml(row.title || '');
                const summary = escapeHtml(row.summary || '');
                const category = escapeHtml(row.category_name || '');
                const href = articleUrl(row.slug || '');

                return [
                    `<a class="kb-search-result" href="${href}">`,
                    '<span class="kb-search-result-topic">',
                    category,
                    '</span>',
                    `<strong>${title}</strong>`,
                    summary ? `<p>${summary}</p>` : '',
                    '<span class="kb-search-result-link">Read article →</span>',
                    '</a>'
                ].join('');
            }).join('');

            results.innerHTML =
                `<div class="kb-search-result-list">${html}</div>`;
            setOpen(true);
        };

        const runSearch = async () => {
            const query = input.value.trim();
            lastQuery = query;
            clear.hidden = query === '';

            if (query.length < 2) {
                results.replaceChildren();
                setOpen(false);
                return;
            }

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            results.innerHTML =
                '<div class="kb-search-message">Searching the llama library…</div>';
            setOpen(true);

            try {
                const response = await fetch(
                    `/api/knowledge-base-search.php?q=${encodeURIComponent(query)}`,
                    {
                        headers: {
                            Accept: 'application/json'
                        },
                        signal: controller.signal,
                        credentials: 'same-origin'
                    }
                );

                if (!response.ok) {
                    throw new Error('Search request failed');
                }

                const payload = await response.json();

                if (query !== lastQuery) {
                    return;
                }

                renderResults(
                    query,
                    Array.isArray(payload.results)
                        ? payload.results
                        : []
                );
            } catch (error) {
                if (error && error.name === 'AbortError') {
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

        input.addEventListener('input', () => {
            window.clearTimeout(timer);

            timer = window.setTimeout(
                runSearch,
                180
            );
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
                input.blur();
            }
        });

        clear.addEventListener('click', () => {
            if (controller) {
                controller.abort();
            }

            window.clearTimeout(timer);
            input.value = '';
            lastQuery = '';
            clear.hidden = true;
            results.replaceChildren();
            setOpen(false);
            input.focus();
        });

        document.addEventListener('click', (event) => {
            if (!shell.contains(event.target)) {
                setOpen(false);
            }
        });

        input.addEventListener('focus', () => {
            if (
                input.value.trim().length >= 2
                && results.childNodes.length
            ) {
                setOpen(true);
            }
        });
    });
})();
