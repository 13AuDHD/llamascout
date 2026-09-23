(() => {
    'use strict';

    const textarea = document.getElementById('kb-article-content');

    if (!textarea) {
        return;
    }

    const imageDataNode = document.getElementById('kb-image-data');
    let imageRows = [];

    if (imageDataNode) {
        try {
            const parsed = JSON.parse(imageDataNode.textContent || '[]');
            if (Array.isArray(parsed)) {
                imageRows = parsed;
            }
        } catch (error) {
            imageRows = [];
        }
    }

    const imagesById = new Map(
        imageRows.map((row) => [String(row.id), row])
    );

    const standardTemplate = [
        '<h2>What it does</h2>',
        '<p></p>',
        '',
        '<h2>Why it exists</h2>',
        '<p></p>',
        '',
        '<h2>Where to find it</h2>',
        '<p></p>',
        '',
        '<h2>How to use it</h2>',
        '<ol>',
        '    <li></li>',
        '</ol>',
        '',
        '<h2>What happens next</h2>',
        '<p></p>',
        '',
        '<h2>Troubleshooting</h2>',
        '<p></p>'
    ].join('\n');

    function replaceSelection(before, after = '', fallback = '') {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? start;
        const selected = textarea.value.slice(start, end) || fallback;
        const replacement = before + selected + after;

        textarea.setRangeText(replacement, start, end, 'end');
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.querySelectorAll('[data-kb-wrap]').forEach((button) => {
        button.addEventListener('click', () => {
            const template = button.getAttribute('data-kb-wrap') || '|';
            const marker = template.indexOf('|');

            if (marker === -1) {
                replaceSelection(template);
                return;
            }

            replaceSelection(
                template.slice(0, marker),
                template.slice(marker + 1),
                'Text'
            );
        });
    });

    const linkButton = document.querySelector('[data-kb-link]');
    if (linkButton) {
        linkButton.addEventListener('click', () => {
            const href = window.prompt(
                'Link URL',
                'https://'
            );

            if (!href) {
                return;
            }

            replaceSelection(
                `<a href="${escapeAttribute(href)}">`,
                '</a>',
                'Link text'
            );
        });
    }

    const noteButton = document.querySelector('[data-kb-note]');
    if (noteButton) {
        noteButton.addEventListener('click', () => {
            replaceSelection(
                '<aside class="kb-note">\n    <strong>Good to know</strong>\n    <p>',
                '</p>\n</aside>',
                'Helpful detail'
            );
        });
    }

    const templateButton = document.querySelector('[data-kb-template]');
    if (templateButton) {
        templateButton.addEventListener('click', () => {
            if (
                textarea.value.trim() !== ''
                && !window.confirm(
                    'Replace the current article body with the standard Knowledge Base template?'
                )
            ) {
                return;
            }

            textarea.value = standardTemplate;
            textarea.focus();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    document.querySelectorAll('[data-kb-insert-image]').forEach((button) => {
        button.addEventListener('click', () => {
            const imageId = button.getAttribute('data-kb-insert-image');

            if (!imageId) {
                return;
            }

            const marker = `[kb-image id="${imageId}"]`;
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? start;
            const prefix =
                start > 0 && !textarea.value.slice(0, start).endsWith('\n\n')
                    ? '\n\n'
                    : '';
            const suffix =
                end < textarea.value.length
                    && !textarea.value.slice(end).startsWith('\n\n')
                    ? '\n\n'
                    : '';

            textarea.setRangeText(
                prefix + marker + suffix,
                start,
                end,
                'end'
            );
            textarea.focus();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));

            textarea.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    ? 'auto'
                    : 'smooth',
                block: 'center'
            });
        });
    });

    const tabs = document.querySelectorAll('[data-kb-editor-tab]');
    const panels = document.querySelectorAll('[data-kb-editor-panel]');
    const preview = document.querySelector('[data-kb-preview]');
    const emptyPreview = document.querySelector('[data-kb-preview-empty]');

    function setTab(name) {
        tabs.forEach((tab) => {
            const active = tab.getAttribute('data-kb-editor-tab') === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            panel.hidden =
                panel.getAttribute('data-kb-editor-panel') !== name;
        });

        if (name === 'preview') {
            renderPreview();
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            setTab(tab.getAttribute('data-kb-editor-tab') || 'write');
        });
    });

    function renderPreview() {
        if (!preview || !emptyPreview) {
            return;
        }

        let html = textarea.value.trim();

        if (html === '') {
            preview.replaceChildren();
            emptyPreview.hidden = false;
            return;
        }

        html = html.replace(
            /\[kb-image\s+id=["']?(\d+)["']?\s*\]/gi,
            (_, id) => {
                const image = imagesById.get(String(id));

                if (!image) {
                    return `<div class="kb-preview-missing-image">Screenshot #${id} is not available.</div>`;
                }

                const src = escapeAttribute(
                    new URL(
                        String(image.path || ''),
                        window.location.origin
                    ).toString()
                );
                const alt = escapeAttribute(String(image.alt || ''));
                const caption = escapeHtml(String(image.caption || ''));
                const archivedClass =
                    image.status === 'archived'
                        ? ' is-archived'
                        : '';

                return [
                    `<figure class="kb-preview-image${archivedClass}">`,
                    `<img src="${src}" alt="${alt}">`,
                    caption ? `<figcaption>${caption}</figcaption>` : '',
                    '</figure>'
                ].join('');
            }
        );

        const parser = new DOMParser();
        const documentFragment = parser.parseFromString(
            `<div id="kb-preview-root">${html}</div>`,
            'text/html'
        );

        const root = documentFragment.getElementById('kb-preview-root');

        if (!root) {
            preview.replaceChildren();
            emptyPreview.hidden = false;
            return;
        }

        root.querySelectorAll(
            'script,style,iframe,object,embed,form,input,button,textarea,select,meta,link'
        ).forEach((node) => node.remove());

        root.querySelectorAll('*').forEach((node) => {
            [...node.attributes].forEach((attribute) => {
                const name = attribute.name.toLowerCase();
                const value = attribute.value.trim().toLowerCase();

                if (
                    name.startsWith('on')
                    || name === 'srcdoc'
                    || (
                        (name === 'href' || name === 'src')
                        && value.startsWith('javascript:')
                    )
                ) {
                    node.removeAttribute(attribute.name);
                }
            });

            if (node.tagName === 'A') {
                node.setAttribute('target', '_blank');
                node.setAttribute('rel', 'noopener noreferrer');
            }
        });

        preview.replaceChildren(...root.childNodes);
        emptyPreview.hidden = preview.childNodes.length > 0;
    }

    textarea.addEventListener('input', () => {
        const previewPanel = document.querySelector(
            '[data-kb-editor-panel="preview"]'
        );

        if (previewPanel && !previewPanel.hidden) {
            renderPreview();
        }
    });

    function escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value);
    }
})();
