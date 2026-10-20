(() => {
    'use strict';

    const mapCard = document.querySelector('.place-map-card[data-explorer-name]');
    const section = document.querySelector('[data-stay-options]');
    const map = window.LlamaScoutMap?.map;
    if (!mapCard || !section || !map) return;

    const name = mapCard.dataset.explorerName || 'Location';
    const category = mapCard.dataset.explorerCategory || 'camping';
    const photo = mapCard.dataset.explorerPhoto || '';
    const count = Number(mapCard.dataset.explorerSiteCount || 0);
    const hue = category === 'camping' ? 'green'
        : category === 'parking' ? 'blue' : 'purple';
    const slot = section.querySelector('[data-stay-browser-slot]');
    if (!slot) return;

    // There is one selected real-world record. The map and browser are views of it.
    let selection = { kind: 'place', id: '' };

    // The HTML initially rendered by PHP is the authoritative Place report.
    // Keep its exact elements so a Site's temporary values never become a
    // replacement source of truth when the overview is reselected.
    const placeCards = Array.from(document.querySelectorAll(
        '[data-campsite-report-field]'
    ));
    const placeBaseline = new Map(placeCards.map((card) => [card, {
        html: card.innerHTML,
        className: card.className,
        hidden: card.hidden,
    }]));
    let restoringReport = false;
    const restorePlaceReport = () => {
        if (selection.kind !== 'place' || restoringReport) return;
        restoringReport = true;
        try {
            for (const [card, original] of placeBaseline) {
                if (card.innerHTML !== original.html) card.innerHTML = original.html;
                if (card.className !== original.className) card.className = original.className;
                if (card.hidden !== original.hidden) card.hidden = original.hidden;
            }
        } finally {
            restoringReport = false;
        }
    };

    const overview = document.createElement('button');
    overview.type = 'button';
    overview.className = `place-explorer-overview is-${hue}`;
    overview.dataset.explorerOverview = '1';
    overview.setAttribute('aria-current', 'true');
    const title = document.createElement('strong');
    title.textContent = name;
    const description = document.createElement('span');
    const countLabel = count > 0 ? `${count} Site${count === 1 ? '' : 's'}` : '';
    description.textContent = [
        category === 'camping' ? 'Campground or camping area'
            : category === 'parking' ? 'Parking location' : 'Location overview',
        countLabel,
    ].filter(Boolean).join(' · ');
    overview.append(title, description);
    slot.before(overview);

    // The source Place gallery remains authoritative; reuse the existing hero URL.
    const preview = document.createElement('section');
    preview.className = `place-explorer-preview is-${hue}`;
    preview.dataset.explorerPreview = '1';
    const previewHeading = document.createElement('h3');
    previewHeading.textContent = name;
    const previewText = document.createElement('p');
    previewText.textContent = description.textContent;
    preview.append(previewHeading, previewText);
    if (photo) {
        const image = document.createElement('img');
        image.src = photo;
        image.alt = `Overview of ${name}`;
        image.loading = 'lazy';
        image.decoding = 'async';
        image.addEventListener('error', () => image.remove());
        preview.prepend(image);
    }
    const detailSlot = section.querySelector('[data-stay-options-list]');
    if (detailSlot) detailSlot.after(preview);
    else slot.after(preview);

    const scrollSiteRowInCarousel = (row) => {
        // Only the horizontal track moves when selecting a Site from the map.
        const track = row.closest('[data-campsite-list]');
        if (!track) return;
        const left = row.offsetLeft - track.offsetLeft;
        const targetLeft = left - Math.max(0, (track.clientWidth - row.offsetWidth) / 2);
        if (Number.isFinite(targetLeft)) track.scrollTo({ left: targetLeft, behavior: 'smooth' });
    };

    const syncSelection = (next, mode = 'card') => {
        selection = next;
        const place = next.kind === 'place';
        section.dataset.explorerScope = next.kind;
        section.dataset.explorerColor = place ? hue
            : next.kind === 'site' ? 'orange'
                : next.kind === 'parking' ? 'blue' : 'green';

        overview.classList.toggle('is-selected', place);
        overview.setAttribute('aria-current', place ? 'true' : 'false');
        preview.hidden = !place;

        if (place) {
            window.LlamaScoutSiteMap?.clearSelection();
            restorePlaceReport();
            section.querySelectorAll('[data-campsite-id].is-selected, [data-stay-area-id].is-selected')
                .forEach((row) => {
                    row.classList.remove('is-selected');
                    row.setAttribute('aria-current', 'false');
                });
        }

        const report = document.querySelector('.scout-report');
        if (report) {
            report.dataset.explorerColor = section.dataset.explorerColor;
            report.dataset.explorerScope = next.kind;
        }

        const selectedSiteDetail = section.querySelector('[data-campsite-selected-detail]');
        if (selectedSiteDetail) selectedSiteDetail.hidden = place;

        window.dispatchEvent(new CustomEvent('llama:explorer-selection', {
            detail: { ...next, origin: mode },
        }));
    };

    const selectSite = (id, origin = 'map') => {
        // The user can choose a marker regardless of the current text/filter.
        const search = section.querySelector('[data-campsite-search]');
        if (search && search.value.trim() !== '') {
            search.value = '';
            search.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const all = section.querySelector('[data-campsite-filter="all"]');
        if (all && !all.classList.contains('is-active')) all.click();
        const row = section.querySelector(
            `[data-campsite-id="${CSS.escape(String(id))}"]`
        );
        if (!row) return false;
        if (origin === 'map') {
            const previousY = window.scrollY;
            row.click();
            scrollSiteRowInCarousel(row);
            // The existing Site browser still calls scrollIntoView. Restore the
            // document position; the carousel itself may move horizontally.
            window.requestAnimationFrame(() => window.scrollTo(window.scrollX, previousY));
        }
        syncSelection({ kind: 'site', id: String(id) }, origin);
        return true;
    };

    const selectArea = (row, origin = 'map') => {
        if (!row) return false;
        if (origin === 'map') row.click();
        const kind = row.dataset.stayAreaKind === 'parking' ? 'parking' : 'area';
        syncSelection({ kind, id: row.dataset.stayAreaId || '' }, origin);
        return true;
    };

    const selectPlace = (origin = 'card') => {
        syncSelection({ kind: 'place', id: '' }, origin);
        // The Site query parameter is not meaningful when viewing the Place.
        const url = new URL(window.location.href);
        if (url.searchParams.has('site')) {
            url.searchParams.delete('site');
            window.history.replaceState({}, '', url);
        }
        const context = document.querySelector('[data-selected-campsite-context]');
        if (context) {
            context.replaceChildren();
            const label = document.createElement('strong');
            label.textContent = name;
            context.append(label);
        }
    };

    overview.addEventListener('click', () => selectPlace('card'));
    section.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const site = event.target.closest('[data-campsite-id]');
        if (site && section.contains(site)) {
            const id = site.dataset.campsiteId || '';
            syncSelection({ kind: 'site', id }, 'card');
            // Site browser has already selected the row. Do not run the
            // older map-feature fallback, which points at the Place center.
            event.stopImmediatePropagation();
            window.LlamaScoutSiteMap?.focusSite(id, false);
            return;
        }
        const area = event.target.closest('[data-stay-area-id]');
        if (area && section.contains(area)) selectArea(area, 'card');
    });

    window.addEventListener('llama:explorer-map-site', (event) => {
        const id = Number(event.detail?.id);
        if (Number.isSafeInteger(id) && id > 0) selectSite(id, 'map');
    });

    // A boundary already belongs to a Place or Area. Never create another record.
    const normalize = (v) => String(v || '').trim().toLocaleLowerCase();
    const areaRow = (label, parking) => Array.from(
        section.querySelectorAll('[data-stay-area-id]')
    ).find((row) => normalize(row.dataset.stayAreaName) === normalize(label)
        && (!parking || row.dataset.stayAreaKind === 'parking'));

    const makeMapCard = (label, kind, select, usePlacePhoto = false) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = `place-explorer-map-card is-${kind}`;
        if (photo && usePlacePhoto) {
            const image = document.createElement('img');
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.src = photo;
            image.addEventListener('error', () => image.remove());
            card.append(image);
        }
        const body = document.createElement('span');
        body.className = 'place-explorer-map-card-body';
        const heading = document.createElement('strong');
        heading.textContent = label;
        const meta = document.createElement('span');
        meta.textContent = [
            kind === 'green' ? 'Camping area' : kind === 'blue'
                ? 'Parking area' : 'Location',
            count > 0 && normalize(label) === normalize(name) ? countLabel : '',
        ].filter(Boolean).join(' · ');
        body.append(heading, meta);
        card.append(body);
        card.addEventListener('click', (e) => {
            e.stopPropagation();
            select();
        });
        return card;
    };

    map.on('popupopen', (event) => {
        const popup = event.popup;
        const layer = popup?._source;
        const css = String(layer?.options?.className || '');
        const isCamping = css.includes('place-map-feature--camping-area');
        const isParking = css.includes('place-map-feature--parking-area');
        const isBoundary = css.includes('place-map-feature--place-boundary');
        if (!isCamping && !isParking && !isBoundary) return;
        const original = popup.getContent();
        if (!(original instanceof HTMLElement)) return;
        if (original.classList.contains('place-explorer-map-card')) return;
        const label = original.querySelector('strong')?.textContent?.trim() || name;
        const row = areaRow(label, isParking);
        const root = normalize(label) === normalize(name);
        // An unrecognized subdivision cannot silently become the whole Place.
        if (!root && !row) return;
        const kind = isParking ? 'blue' : isCamping ? 'green' : hue;
        const card = makeMapCard(label, kind,
            root ? () => selectPlace('map') : () => selectArea(row, 'map'),
            root);
        popup.setContent(card);
        popup.update();
    });

    // Site cards are orange; the campground overview is its own first card.
    // The first Site may still be internally preselected by the legacy browser.
    const deepLinkedSite = new URLSearchParams(window.location.search).get('site');
    if (deepLinkedSite && /^\d+$/.test(deepLinkedSite)) {
        // Preserve direct links to a particular Site.
        syncSelection({ kind: 'site', id: deepLinkedSite }, 'initial');
    } else {
        selectPlace('initial');
    }

    // The browser renders asynchronously; don't let its initial Site highlight
    // visually replace the default overview before somebody chooses a Site.
    const observer = new MutationObserver(() => {
        if (selection.kind !== 'place') return;
        section.querySelectorAll('[data-campsite-id].is-selected')
            .forEach((row) => {
                row.classList.remove('is-selected');
                row.setAttribute('aria-current', 'false');
            });
        const detail = section.querySelector('[data-campsite-selected-detail]');
        if (detail) detail.hidden = true;
    });
    observer.observe(slot, { childList: true, subtree: true });

    // The Site detail response can arrive after a Place selection. Reapply
    // only the original PHP Place facts when that happens.
    if (placeCards.length) {
        const reportObserver = new MutationObserver(() => {
            if (selection.kind === 'place') restorePlaceReport();
        });
        const report = document.querySelector('.scout-report');
        if (report) reportObserver.observe(report, {
            childList: true, subtree: true, attributes: true,
            attributeFilter: ['class', 'hidden'],
        });
    }
})();
