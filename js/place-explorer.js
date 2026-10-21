(() => {
    'use strict';

    const mapCard = document.querySelector('.place-map-card[data-explorer-name]');
    const section = document.querySelector('[data-stay-options]');
    const map = window.LlamaScoutMap?.map;
    if (!mapCard || !section || !map) return;

    const name = String(mapCard.dataset.explorerName || 'Location').trim();
    const category = String(mapCard.dataset.explorerCategory || 'camping');
    const photo = String(mapCard.dataset.explorerPhoto || '').trim();
    const siteCount = Number(mapCard.dataset.explorerSiteCount || 0);
    const color = category === 'parking' ? 'blue'
        : category === 'camping' ? 'green' : 'purple';
    const slot = section.querySelector('[data-stay-browser-slot]');
    if (!slot) return;

    const report = document.querySelector('.scout-report');
    const reportCards = Array.from(document.querySelectorAll(
        '[data-campsite-report-field]'
    ));
    // The PHP Place report is the authoritative baseline, not a copy of a Site.
    const baseline = new Map(reportCards.map((card) => [card, {
        html: card.innerHTML,
        className: card.className,
        hidden: card.hidden,
    }]));

    const overview = document.createElement('button');
    overview.type = 'button';
    overview.className = `place-explorer-overview is-${color}`;
    overview.dataset.explorerOverview = '1';
    const title = document.createElement('strong');
    title.textContent = name;
    const subtitle = document.createElement('span');
    const countLabel = siteCount > 0
        ? `${siteCount.toLocaleString()} ${siteCount === 1 ? 'Site' : 'Sites'}` : '';
    subtitle.textContent = [
        category === 'camping' ? 'Campground or camping area'
            : category === 'parking' ? 'Parking location' : 'Location overview',
        countLabel,
    ].filter(Boolean).join(' · ');
    overview.append(title, subtitle);
    slot.before(overview);

    // The Place gallery is permanently rendered here by the PHP template.
    const placeGallery = section.querySelector('[data-explorer-place-photos]');

    // The map is directly above the explorer. Selection does not need a jump link.
    section.querySelector('.place-overnight-options-map-link')?.remove();

    const requestedId = new URLSearchParams(window.location.search).get('site');
    let selection = requestedId && /^\d+$/.test(requestedId)
        ? { kind: 'site', id: requestedId }
        : { kind: 'place', id: '' };
    let pendingMapSite = null;
    let mapSelectionInProgress = false;
    let activePlaceLayer = null;
    let activatingAreaFromMap = false;

    const sameName = (a, b) => String(a || '').trim().toLocaleLowerCase()
        === String(b || '').trim().toLocaleLowerCase();

    const reportColor = (kind) => {
        if (kind === 'place') return color;
        if (kind === 'site' || kind === 'mapped') return 'orange';
        if (kind === 'parking') return 'blue';
        return 'green';
    };

    const restorePlaceReport = () => {
        for (const [card, initial] of baseline) {
            if (card.innerHTML !== initial.html) card.innerHTML = initial.html;
            if (card.className !== initial.className) card.className = initial.className;
            if (card.hidden !== initial.hidden) card.hidden = initial.hidden;
        }
        window.LlamaScoutReportWarningsState = { restore: true };
        window.dispatchEvent(new CustomEvent('llama:report-warnings-change', {
            detail: { restore: true },
        }));
    };

    const setSelected = (next, origin = 'card') => {
        selection = { kind: next.kind, id: String(next.id || '') };
        const placeSelected = selection.kind === 'place';
        section.dataset.explorerScope = selection.kind;
        section.dataset.explorerColor = reportColor(selection.kind);
        overview.classList.toggle('is-selected', placeSelected);
        overview.setAttribute('aria-current', String(placeSelected));
        if (placeGallery) placeGallery.hidden = !placeSelected;

        if (report) {
            report.dataset.explorerScope = selection.kind;
            report.dataset.explorerColor = reportColor(selection.kind);
        }

        // Preserve the same Place identity even when its mapped boundary moves.
        if (placeSelected) {
            window.LlamaScoutCampsiteBrowser?.clearSelection();
            window.LlamaScoutSiteMap?.clearSelection();
            restorePlaceReport();
            section.querySelectorAll('[data-stay-area-id].is-selected, [data-stay-map-site-id].is-selected')
                .forEach((row) => {
                    row.classList.remove('is-selected');
                    row.setAttribute('aria-current', 'false');
                });
            const context = document.querySelector('[data-selected-campsite-context]');
            if (context) {
                const current = document.createElement('strong');
                current.textContent = name;
                context.replaceChildren(current);
            }
        } else if (selection.kind === 'site') {
            section.querySelectorAll('[data-stay-area-id].is-selected, [data-stay-map-site-id].is-selected')
                .forEach((row) => {
                    row.classList.remove('is-selected');
                    row.setAttribute('aria-current', 'false');
                });
        }

        window.dispatchEvent(new CustomEvent('llama:explorer-selection', {
            detail: { ...selection, origin },
        }));
    };

    const placeView = (origin) => {
        setSelected({ kind: 'place', id: '' }, origin);
        const url = new URL(window.location.href);
        if (url.searchParams.has('site')) {
            url.searchParams.delete('site');
            window.history.replaceState({}, '', url);
        }
        if (origin !== 'initial') {
            // Selecting an overview can move the map, but never the page.
            map.closePopup();
            if (activePlaceLayer?.getBounds) {
                const bounds = activePlaceLayer.getBounds();
                if (bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [22, 22], maxZoom: 16, animate: false });
                    return;
                }
            }
            const element = window.LlamaScoutMap?.mapElement;
            const latitude = Number(element?.dataset.placeLatitude);
            const longitude = Number(element?.dataset.placeLongitude);
            if (Number.isFinite(latitude) && Number.isFinite(longitude)
                && (latitude !== 0 || longitude !== 0)) {
                map.setView([latitude, longitude], Math.min(15, map.getMaxZoom()), {
                    animate: false,
                });
            }
        }
    };

    const allSites = () => {
        const search = section.querySelector('[data-campsite-search]');
        if (search && search.value !== '') {
            search.value = '';
            search.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const all = section.querySelector('[data-campsite-filter="all"]');
        if (all && !all.classList.contains('is-active')) all.click();
    };

    const selectSite = (id, origin = 'map') => {
        const value = String(id || '');
        if (!/^\d+$/.test(value) || Number(value) < 1) return false;
        const browser = window.LlamaScoutCampsiteBrowser;
        if (!browser) {
            pendingMapSite = { id: value, origin };
            return false;
        }
        allSites();
        // The browser owns Site detail/report updates and horizontal card scroll.
        mapSelectionInProgress = origin === 'map';
        browser.selectSite(value);
        mapSelectionInProgress = false;
        return true;
    };

    window.addEventListener('llama:explorer-browser-site-selected', (event) => {
        const id = String(event.detail?.id || '');
        if (!id) return;
        const origin = mapSelectionInProgress ? 'map' : 'card';
        setSelected({ kind: 'site', id }, origin);
        // Map-to-card and card-to-map share this one focus path.
        window.LlamaScoutSiteMap?.focusSite(id, false);
    });

    window.addEventListener('llama:explorer-browser-ready', (event) => {
        if (pendingMapSite) {
            const pending = pendingMapSite;
            pendingMapSite = null;
            selectSite(pending.id, pending.origin);
            return;
        }
        if (event.detail?.selectedSite) {
            setSelected({ kind: 'site', id: String(event.detail.selectedSite) }, 'initial');
            window.LlamaScoutSiteMap?.focusSite(event.detail.selectedSite, false);
        } else if (selection.kind === 'place') {
            placeView('initial');
        }
    });

    window.addEventListener('llama:explorer-markers-ready', () => {
        if (selection.kind === 'site' && selection.id) {
            window.LlamaScoutSiteMap?.focusSite(selection.id, false);
        }
    });

    window.addEventListener('llama:explorer-map-site', (event) => {
        const id = Number(event.detail?.id);
        if (Number.isSafeInteger(id) && id > 0) selectSite(id, 'map');
    });

    const selectArea = (row, origin = 'map') => {
        if (!row) return;
        const kind = row.dataset.stayAreaKind === 'parking' ? 'parking' : 'area';
        // An Area has a separate identity; do not overwrite Place-level facts.
        window.LlamaScoutCampsiteBrowser?.clearSelection();
        if (origin === 'map') {
            activatingAreaFromMap = true;
            try {
                row.click();
            } finally {
                activatingAreaFromMap = false;
            }
        }
        setSelected({ kind, id: row.dataset.stayAreaId || '' }, origin);
        const url = new URL(window.location.href);
        if (url.searchParams.has('site')) {
            url.searchParams.delete('site');
            window.history.replaceState({}, '', url);
        }
    };

    const oldIntro = section.querySelector('.place-stay-intro');
    if (oldIntro) {
        oldIntro.textContent = 'Select this location or explore individual Sites. The map, cards, and Scout Report stay in sync.';
    }
    overview.addEventListener('click', () => placeView('card'));
    section.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const area = target.closest('[data-stay-area-id]');
        if (area && section.contains(area) && !activatingAreaFromMap) {
            // The existing Area-report handler is allowed to finish first.
            queueMicrotask(() => selectArea(area, 'card'));
        }
    });

    const findAreaRow = (label, parking) => {
        const rows = Array.from(section.querySelectorAll('[data-stay-area-id]'))
            .filter((row) => sameName(row.dataset.stayAreaName, label)
                && (row.dataset.stayAreaKind === 'parking') === parking);
        return rows.length === 1 ? rows[0] : null;
    };

    // Green and purple shapes describe geography; they are not popup targets.
    // Orange Site markers and blue parking areas retain their own interactions.
    const silentPolygon = (layer) => {
        const type = String(layer?.options?.className || '');
        const boundary = type.includes('place-map-feature--place-boundary');
        const camping = type.includes('place-map-feature--camping-area');
        if (!boundary && !camping) return false;
        if (boundary && !activePlaceLayer) activePlaceLayer = layer;
        if (camping && !activePlaceLayer) activePlaceLayer = layer;
        layer.unbindPopup?.();
        return true;
    };
    map.eachLayer(silentPolygon);
    map.on('layeradd', (event) => silentPolygon(event.layer));

    const mapCardFor = (label, select) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'place-explorer-map-card is-blue';
        const body = document.createElement('span');
        body.className = 'place-explorer-map-card-body';
        const heading = document.createElement('strong');
        heading.textContent = label;
        const detail = document.createElement('span');
        detail.textContent = 'Parking area';
        body.append(heading, detail);
        card.append(body);
        card.addEventListener('click', (event) => {
            event.stopPropagation();
            select();
        });
        return card;
    };

    // The original Place pin is the only overview map popup.
    // Leaflet owns the close X, keyboard handling, and popup positioning.
    const mapElement = window.LlamaScoutMap?.mapElement;
    const latitude = Number(mapElement?.dataset.placeLatitude);
    const longitude = Number(mapElement?.dataset.placeLongitude);
    const validCoordinates = Number.isFinite(latitude) && Number.isFinite(longitude)
        && latitude >= -90 && latitude <= 90
        && longitude >= -180 && longitude <= 180
        && (latitude !== 0 || longitude !== 0);

    const buildPlacePinPopup = () => {
        const card = document.createElement('div');
        card.className = `place-explorer-pin-popup is-${color}`;
        if (photo) {
            const image = document.createElement('img');
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.src = photo;
            image.addEventListener('error', () => image.remove());
            card.appendChild(image);
        }
        const copy = document.createElement('div');
        copy.className = 'place-explorer-pin-popup-content';
        const heading = document.createElement('strong');
        heading.textContent = name;
        const detail = document.createElement('span');
        detail.textContent = [
            category === 'camping' ? 'Campground or camping area'
                : category === 'parking' ? 'Parking location' : 'Location',
            countLabel,
        ].filter(Boolean).join(' · ');
        copy.append(heading, detail);
        if (validCoordinates) {
            const gps = document.createElement('small');
            gps.className = 'place-explorer-pin-coordinates';
            gps.textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
            copy.appendChild(gps);
        }
        card.appendChild(copy);
        return card;
    };

    const isPlacePin = (layer) => layer instanceof L.Marker
        && layer.options.icon?.options?.className === 'place-map-marker-shell';
    const registerPlacePin = (layer) => {
        if (!isPlacePin(layer) || layer._llamaExplorerPinReady) return;
        layer._llamaExplorerPinReady = true;
        layer.bindPopup(buildPlacePinPopup(), {
            className: 'place-explorer-pin-popup-shell',
            minWidth: 240,
            maxWidth: 310,
            closeButton: true,
        });
        layer.on('click', () => placeView('initial'));
    };
    map.eachLayer(registerPlacePin);
    map.on('layeradd', (event) => registerPlacePin(event.layer));

    // Preserve the blue parking-area card. Green/purple polygon popups are off.
    map.on('popupopen', (event) => {
        const popup = event.popup;
        const layer = popup?._source;
        if (silentPolygon(layer)) {
            map.closePopup(popup);
            return;
        }
        const type = String(layer?.options?.className || '');
        if (!type.includes('place-map-feature--parking-area')) return;
        const original = popup.getContent();
        if (!(original instanceof HTMLElement)
            || original.classList.contains('place-explorer-map-card')) return;
        const label = original.querySelector('strong')?.textContent?.trim() || '';
        const area = findAreaRow(label, true);
        if (!area) return;
        popup.setContent(mapCardFor(label, () => selectArea(area, 'map')));
        popup.update();
    });

    // Do not interpret the first Site row as the initial selection.
    if (selection.kind === 'site') {
        setSelected(selection, 'initial');
    } else {
        placeView('initial');
    }

    window.LlamaScoutExplorer = Object.freeze({
        selected: () => ({ ...selection }),
        selectPlace: () => placeView('card'),
        selectSite: (id) => selectSite(id, 'map'),
    });
})();
