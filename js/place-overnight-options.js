(() => {
    'use strict';

    const section = document.querySelector('[data-stay-options]');
    if (!section) return;
    const mount = document.querySelector('[data-place-campsites-mount]');
    const slot = section.querySelector('[data-stay-browser-slot]');
    const fallbackList = section.querySelector('[data-stay-parking-list]');
    const detail = section.querySelector('[data-stay-selection]');
    if (!slot || !fallbackList || !detail) return;

    // Feature polygons and canonical campsite records are separate datasets.
    // Keep mapped sites visible even when the canonical campsite browser is
    // disabled for a travel center or a mixed-use Place.
    const mappedRows = [...fallbackList.querySelectorAll('[data-stay-map-site-id]')];
    const parkingRows = [...fallbackList.querySelectorAll('[data-stay-parking-id]')];
    const extraRows = [...mappedRows, ...parkingRows];
    if (mount) slot.appendChild(mount);

    let listObserver = null;
    let browser = null;

    function refresh() {
        browser = mount?.querySelector('[data-place-campsites]') || null;
        if (!browser) {
            fallbackList.hidden = extraRows.length === 0;
            return;
        }
        browser.classList.add('is-stay-browser');
        const list = browser.querySelector('[data-campsite-list]');
        if (!list) return;
        const filter = browser.querySelector('[data-campsite-filter].is-active');
        const query = String(browser.querySelector('[data-campsite-search]')?.value || '').trim().toLowerCase();
        const all = !filter || filter.dataset.campsiteFilter === 'all';

        // When the canonical campsite browser exists, don't hide mapped
        // polygons that have no corresponding canonical campsite record.
        const canonicalIds = new Set([...list.querySelectorAll('[data-campsite-id]')]
            .map(el => el.dataset.campsiteId));
        for (const row of extraRows) {
            if (row.parentElement !== list) list.appendChild(row);
            const text = row.textContent.toLowerCase();
            row.hidden = !all || (query !== '' && !text.includes(query));
        }
        fallbackList.hidden = true;

        if (!listObserver) {
            listObserver = new MutationObserver(refresh);
            listObserver.observe(list, {childList: true});
            browser.addEventListener('input', () => requestAnimationFrame(refresh));
            browser.addEventListener('click', () => requestAnimationFrame(refresh));
        }
    }

    if (mount) new MutationObserver(refresh).observe(mount, {childList: true});
    refresh();

    const clearSelection = () => {
        for (const row of extraRows) {
            row.classList.remove('is-selected');
            row.setAttribute('aria-current', 'false');
        }
    };

    for (const row of extraRows) {
        row.addEventListener('click', () => {
            clearSelection();
            row.classList.add('is-selected');
            row.setAttribute('aria-current', 'true');
            detail.replaceChildren();
            const title = document.createElement('strong');
            const summary = document.createElement('p');
            const link = document.createElement('a');
            const mappedSite = row.hasAttribute('data-stay-map-site-id');
            title.textContent = mappedSite
                ? row.dataset.stayMapSiteName || 'Campsite'
                : row.dataset.stayParkingName || 'Parking area';
            summary.textContent = mappedSite
                ? [row.dataset.stayMapSiteSummary, row.dataset.stayMapSiteArea].filter(Boolean).join(' · ')
                : [row.dataset.stayParkingCost, row.dataset.stayParkingStatus].filter(Boolean).join(' · ');
            link.href = '#place-map-heading';
            link.textContent = 'View on map';
            detail.append(title, summary, link);
            detail.hidden = false;
        });
    }
    mount?.addEventListener('click', event => {
        if (event.target.closest('[data-campsite-id]')) {
            clearSelection();
            detail.hidden = true;
        }
    });
})();
