document.addEventListener('DOMContentLoaded', () => {
    for (const picker of document.querySelectorAll('[data-facility-parent-picker]')) {
        const query = picker.querySelector('[data-facility-parent-query]');
        const id = picker.querySelector('[data-facility-parent-id]');
        const results = picker.querySelector('[data-facility-parent-results]');
        const selected = picker.querySelector('[data-facility-parent-selected]');
        const form = picker.closest('form');
        if (!query || !id || !results || !form) continue;

        let timer = null;
        let sequence = 0;
        let abort = null;
        let confirmedName = id.value ? query.value : '';

        const close = () => {
            results.replaceChildren();
            results.hidden = true;
            query.setAttribute('aria-expanded', 'false');
        };
        const selection = (place) => {
            id.value = String(place.id);
            confirmedName = place.name;
            query.value = place.name;
            if (selected) {
                selected.hidden = false;
                const strong = selected.querySelector('strong');
                if (strong) strong.textContent = place.name;
            }
            query.setCustomValidity('');
            close();
        };
        const message = (label) => {
            const element = document.createElement('p');
            element.className = 'facility-parent-search-message';
            element.textContent = label;
            results.replaceChildren(element);
            results.hidden = false;
            query.setAttribute('aria-expanded', 'true');
        };
        const search = async (text) => {
            abort?.abort();
            abort = new AbortController();
            const current = ++sequence;
            message('Searching Places…');
            try {
                const url = new URL(picker.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', text);
                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    signal: abort.signal,
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Place search unavailable');
                const data = await response.json();
                if (!data.ok) throw new Error(data.error || 'Place search unavailable');
                if (current !== sequence) return;
                results.replaceChildren();
                for (const place of data.results || []) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'facility-parent-result';
                    button.setAttribute('role', 'option');
                    const name = document.createElement('strong');
                    name.textContent = place.name;
                    const detail = document.createElement('span');
                    detail.textContent = [place.location, '#' + place.id].filter(Boolean).join(' · ');
                    button.append(name, detail);
                    button.addEventListener('click', () => selection(place));
                    results.appendChild(button);
                }
                if (!results.children.length) message('No matching Places found.');
                else {
                    results.hidden = false;
                    query.setAttribute('aria-expanded', 'true');
                }
            } catch (error) {
                if (error.name !== 'AbortError' && current === sequence) {
                    message('Search unavailable. Try again.');
                }
            }
        };
        query.addEventListener('input', () => {
            if (query.value === confirmedName && id.value) return;
            id.value = '';
            confirmedName = '';
            if (selected) selected.hidden = true;
            query.setCustomValidity('');
            clearTimeout(timer);
            abort?.abort();
            if (query.value.trim().length < 2) { close(); return; }
            timer = setTimeout(() => search(query.value.trim()), 220);
        });
        query.addEventListener('keydown', event => {
            if (event.key === 'Escape') close();
            if (event.key === 'ArrowDown' && !results.hidden) {
                const first = results.querySelector('button');
                if (first) { event.preventDefault(); first.focus(); }
            }
        });
        picker.addEventListener('focusout', event => {
            if (!picker.contains(event.relatedTarget)) {
                window.setTimeout(close, 150);
            }
        });
        form.addEventListener('submit', event => {
            if (id.value) return;
            event.preventDefault();
            query.setCustomValidity('Choose a Parent Place from the search results.');
            query.reportValidity();
            query.focus();
        });
    }
});
