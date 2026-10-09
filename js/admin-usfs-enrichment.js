(() => {
    'use strict';

    const search = document.getElementById('usfs-place-search');
    const placeResults = document.getElementById('usfs-place-results');
    const matchesPanel = document.getElementById('usfs-matches-panel');
    const matchResults = document.getElementById('usfs-match-results');
    const confirmForm = document.getElementById('usfs-confirm-form');
    const confirmPlace = document.getElementById('usfs-confirm-place-id');
    const confirmSite = document.getElementById('usfs-confirm-site-id');
    const confirmSummary = document.getElementById('usfs-confirm-summary');
    if (!search || !placeResults || !matchesPanel || !matchResults || !confirmForm) return;

    let timer;
    let searchVersion = 0;
    let place = null;

    const text = (element, value) => { element.textContent = String(value ?? ''); };
    const make = (tag, label) => {
        const el = document.createElement(tag);
        text(el, label);
        return el;
    };
    const getJson = async (parameters) => {
        const url = new URL(window.location.pathname, window.location.origin);
        Object.entries(parameters).forEach(([key, value]) => url.searchParams.set(key, value));
        const response = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error('Could not load results. Please retry.');
        return response.json();
    };

    async function findPlaces() {
        const version = ++searchVersion;
        const query = search.value.trim();
        place = null;
        placeResults.replaceChildren();
        matchesPanel.hidden = true;
        confirmForm.hidden = true;
        if (query.length < 2) return;
        text(placeResults, 'Searching Places...');
        try {
            const data = await getJson({ action: 'search-places', q: query });
            if (version !== searchVersion) return;
            placeResults.replaceChildren();
            if (!data.places?.length) {
                text(placeResults, 'No matching Llama Scout Places.');
                return;
            }
            data.places.forEach((candidate) => {
                const button = make('button', `${candidate.name} (${candidate.city || 'Unknown town'}, ${candidate.state || 'Unknown state'})`);
                button.type = 'button';
                button.className = 'admin-button';
                button.addEventListener('click', () => choosePlace(candidate));
                placeResults.appendChild(button);
            });
        } catch (e) {
            if (version === searchVersion) text(placeResults, e.message);
        }
    }

    async function choosePlace(selected) {
        const version = ++searchVersion;
        place = selected;
        text(placeResults, `Selected: ${selected.name} (${selected.city || 'Unknown town'}, ${selected.state || 'Unknown state'})`);
        matchesPanel.hidden = false;
        confirmForm.hidden = true;
        text(matchResults, 'Looking up official Forest Service matches...');
        try {
            const data = await getJson({ action: 'search-usfs', name: selected.name });
            if (version !== searchVersion) return;
            matchResults.replaceChildren();
            if (!data.results?.length) {
                text(matchResults, 'No USFS matches found. Nothing was saved.');
                return;
            }
            data.results.forEach((site) => {
                const card = document.createElement('article');
                const heading = make('h4', `${site.site_name} (USFS site ${site.site_id})`);
                const detail = make('p', [site.fee_description, site.operational_hours].filter(Boolean).join(' | ') || 'No fee or season details supplied');
                const button = make('button', 'Review this match');
                button.type = 'button';
                button.className = 'admin-button';
                button.addEventListener('click', () => {
                    confirmPlace.value = selected.id;
                    confirmSite.value = site.site_id;
                    text(confirmSummary, `Llama Scout: ${selected.name} (${selected.city || 'Unknown town'}, ${selected.state || 'Unknown state'}) | USFS: ${site.site_name} (ID ${site.site_id}).`);
                    confirmForm.querySelector('input[name="confirm_match"]').checked = false;
                    confirmForm.hidden = false;
                });
                card.append(heading, detail, button);
                matchResults.appendChild(card);
            });
        } catch (e) {
            if (version === searchVersion) text(matchResults, e.message);
        }
    }

    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(findPlaces, 230);
    });
    findPlaces();
})();
