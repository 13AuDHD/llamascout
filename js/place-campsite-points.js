(() => {
    'use strict';

    const mapCard = document.querySelector('.place-map-card');
    const handle = window.LlamaScoutMap;
    const mappedApi = mapCard?.dataset.placeMapFeaturesApi || '';

    if (!handle?.map || mapCard?.dataset.mapMember !== '1' || !mappedApi
        || typeof L === 'undefined') {
        return;
    }

    const slug = new URL(mappedApi, window.location.origin).searchParams.get('slug');
    if (!slug) {
        return;
    }

    const map = handle.map;
    const siteMarkers = new Map();
    let selectedMarker = 0;

    const highlightMarker = (id) => {
        selectedMarker = Number(id) || 0;
        for (const [siteId, layer] of siteMarkers) {
            layer.getElement()?.classList.toggle(
                'is-explorer-selected', siteId === selectedMarker
            );
        }
    };

    const formatFeet = (value) => {
        const numeric = Number(value);
        if (!Number.isFinite(numeric) || numeric <= 0) {
            return '';
        }

        return `${Math.round(numeric)} ft max vehicle`;
    };

    const formatCapacity = (site) => {
        const parts = [];
        const maxVehicles = Number(site.max_vehicles);
        const maxPeople = Number(site.max_people);

        if (Number.isFinite(maxVehicles) && maxVehicles > 0) {
            parts.push(`${maxVehicles} vehicle${maxVehicles === 1 ? '' : 's'}`);
        }

        if (Number.isFinite(maxPeople) && maxPeople > 0) {
            parts.push(`${maxPeople} people`);
        }

        return parts.join(' • ');
    };

    const detailPartsFor = (site) => {
        const parts = [];
        const siteType = String(site.site_type_label || '').trim();
        const parking = String(site.parking_label || '').trim();
        const vehicleLength = formatFeet(site.max_vehicle_length_ft);
        const capacity = formatCapacity(site);

        if (siteType) {
            parts.push(siteType);
        }

        if (parking) {
            parts.push(parking);
        }

        if (vehicleLength) {
            parts.push(vehicleLength);
        }

        if (capacity) {
            parts.push(capacity);
        }

        return parts;
    };

    const svgIcon = (type) => {
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');

        const add = (tag, attributes) => {
            const element = document.createElementNS(ns, tag);
            Object.entries(attributes).forEach(([key, value]) => {
                element.setAttribute(key, String(value));
            });
            svg.appendChild(element);
        };

        if (type === 'accessible') {
            add('circle', { cx: 12, cy: 4, r: 2 });
            add('path', { d: 'M12 7v6h6l3 6' });
            add('path', { d: 'M12 10H8' });
            add('path', { d: 'M16 17a7 7 0 1 1-7-7' });
        } else if (type === 'electric') {
            add('path', { d: 'M12 2 6 13h6l-1 9 7-12h-6l1-8Z' });
        } else if (type === 'water') {
            add('path', { d: 'M12 22c4.4 0 8-3.6 8-8 0-4.5-8-12-8-12S4 9.5 4 14c0 4.4 3.6 8 8 8Z' });
        } else if (type === 'sewer') {
            add('path', { d: 'M2 8c2.5 0 2.5 2 5 2s2.5-2 5-2 2.5 2 5 2 2.5-2 5-2' });
            add('path', { d: 'M2 15c2.5 0 2.5 2 5 2s2.5-2 5-2 2.5 2 5 2 2.5-2 5-2' });
        }
        return svg;
    };

    const addIndicator = (parent, iconName, label) => {
        const pill = document.createElement('span');
        pill.className = 'place-map-site-popup-pill';
        pill.append(svgIcon(iconName), document.createTextNode(label));
        parent.appendChild(pill);
    };

    const photoSrc = (value) => {
        const raw = String(value || '').trim();
        if (!raw || raw.startsWith('//')) {
            return '';
        }

        try {
            const url = new URL(raw, window.location.origin);
            if (url.protocol !== 'https:'
                && !(url.protocol === 'http:' && url.origin === window.location.origin)) {
                return '';
            }
            return url.href;
        } catch (_) {
            return '';
        }
    };

    const buildPopup = (site, id, name) => {
        const popup = document.createElement('div');
        popup.className = 'place-map-site-popup';
        popup.dataset.explorerSiteId = String(id);

        const photo = photoSrc(site.hero_image);
        if (photo) {
            const image = document.createElement('img');
            image.className = 'place-map-site-popup-photo';
            image.alt = '';
            image.decoding = 'async';
            image.dataset.photoSrc = photo;
            image.addEventListener('load', () => {
                popup.classList.add('has-photo');
            });
            image.addEventListener('error', () => {
                image.removeAttribute('src');
                image.removeAttribute('data-photo-src');
                popup.classList.remove('has-photo');
            });
            popup.appendChild(image);
        }

        const content = document.createElement('div');
        content.className = 'place-map-site-popup-content';

        const top = document.createElement('div');
        top.className = 'place-map-site-popup-top';
        const heading = document.createElement('strong');
        heading.className = 'place-map-site-popup-title';
        heading.textContent = name;
        top.appendChild(heading);

        if (site.accessible === true) {
            const accessible = document.createElement('span');
            accessible.className = 'place-map-site-popup-accessible';
            accessible.append(svgIcon('accessible'), document.createTextNode('Accessible'));
            top.appendChild(accessible);
        }
        content.appendChild(top);

        const bottom = document.createElement('div');
        bottom.className = 'place-map-site-popup-bottom';
        const details = detailPartsFor(site);
        if (details.length) {
            const meta = document.createElement('div');
            meta.className = 'place-map-site-popup-meta';
            meta.textContent = details.join(' · ');
            bottom.appendChild(meta);
        }

        const amenities = document.createElement('div');
        amenities.className = 'place-map-site-popup-amenities';
        if (site.electric === true) addIndicator(amenities, 'electric', 'Power');
        if (site.water === true) addIndicator(amenities, 'water', 'Water');
        if (site.sewer === true) addIndicator(amenities, 'sewer', 'Sewer');
        if (amenities.childElementCount) {
            bottom.appendChild(amenities);
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'place-map-site-popup-action';
        button.textContent = 'View Site details';
        button.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('llama:explorer-map-site', {
                detail: { id },
            }));
        });

        bottom.appendChild(button);
        content.appendChild(bottom);
        popup.appendChild(content);
        // Treat the image/card as the action too, not only its small button.
        popup.setAttribute('role', 'button');
        popup.tabIndex = 0;
        popup.addEventListener('click', (event) => {
            if (event.target.closest('button')) return;
            window.dispatchEvent(new CustomEvent('llama:explorer-map-site', {
                detail: { id },
            }));
        });
        popup.addEventListener('keydown', (event) => {
            if (event.target !== popup || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            window.dispatchEvent(new CustomEvent('llama:explorer-map-site', {
                detail: { id },
            }));
        });
        return popup;
    };

    const focusSite = (id, scroll = false) => {
        const marker = siteMarkers.get(Number(id));
        if (!marker) {
            return false;
        }

        if (scroll) {
            document.getElementById('place-map-heading')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }

        highlightMarker(id);
        map.setView(marker.getLatLng(), Math.min(18, map.getMaxZoom()), {
            animate: false,
        });
        marker.openPopup();
        return true;
    };

    window.LlamaScoutSiteMap = Object.freeze({
        focusSite,
        clearSelection: () => highlightMarker(0),
    });

    const stayOptions = document.querySelector('[data-stay-options]');
    stayOptions?.addEventListener('click', (event) => {
        const row = event.target instanceof Element
            ? event.target.closest('[data-campsite-id]') : null;
        if (!row || !stayOptions.contains(row)) {
            return;
        }

        const id = Number(row.dataset.campsiteId);
        if (!siteMarkers.has(id)) {
            return;
        }

        event.stopImmediatePropagation();

        const context = document.querySelector('[data-selected-campsite-context]');
        if (context) {
            const label = context.querySelector('span');
            const selectedName = context.querySelector('strong');
            if (label) {
                label.textContent = 'Viewing Site: ';
            }
            if (selectedName) {
                selectedName.textContent = row.querySelector(
                    '.place-campsite-row-name'
                )?.textContent.trim() || `Site ${id}`;
            }
        }

        focusSite(id);
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const link = target.closest('a[href="#place-map-heading"]');
        if (!link) {
            return;
        }

        const headerLink = link.matches('.place-overnight-options-map-link');
        const context = link.closest('[data-selected-campsite-context]');
        if (!headerLink && !context) {
            return;
        }

        if (context && !context.textContent.includes('Viewing Site:')) {
            return;
        }

        const selected = document.querySelector(
            '[data-stay-options] [data-campsite-id].is-selected'
        );
        if (!siteMarkers.has(Number(selected?.dataset.campsiteId))) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        focusSite(selected.dataset.campsiteId, true);
    }, true);

    const loadPoints = async () => {
        try {
            const response = await fetch(
                `/api/place-campsite-points.php?slug=${encodeURIComponent(slug)}`,
                { credentials: 'same-origin', headers: { Accept: 'application/json' } }
            );

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (payload?.ok !== true || !Array.isArray(payload.sites)
                || payload.sites.length === 0) {
                return;
            }

            const layer = L.featureGroup();

            for (const site of payload.sites) {
                const id = Number(site.id);
                const latitude = Number(site.latitude);
                const longitude = Number(site.longitude);
                if (!Number.isSafeInteger(id) || id < 1
                    || !Number.isFinite(latitude) || !Number.isFinite(longitude)
                    || latitude < -90 || latitude > 90
                    || longitude < -180 || longitude > 180
                    || (latitude === 0 && longitude === 0)) {
                    continue;
                }

                const rawName = String(site.name || id).trim();
                const name = /^site\b/i.test(rawName) ? rawName : `Site ${rawName}`;
                const point = L.circleMarker([latitude, longitude], {
                    radius: 7,
                    className: 'place-map-feature place-map-feature--camping-site',
                    pane: 'markerPane',
                    keyboard: true,
                });

                const popupContent = buildPopup(site, id, name);
                point.bindPopup(popupContent, {
                    className: 'place-map-site-popup-shell',
                    minWidth: 260,
                    maxWidth: 300,
                });
                point.on('click', () => {
                    window.dispatchEvent(new CustomEvent('llama:explorer-map-site', {
                        detail: { id },
                    }));
                });
                point.on('popupopen', () => {
                    const photo = popupContent.querySelector('[data-photo-src]');
                    if (photo && !photo.getAttribute('src')) {
                        // Never fetch every Site photo when all markers load.
                        photo.src = photo.dataset.photoSrc;
                    }
                });
                layer.addLayer(point);
                siteMarkers.set(id, point);
            }

            if (layer.getLayers().length === 0) {
                return;
            }

            layer.addTo(map);
            const bounds = layer.getBounds();
            if (bounds.isValid() && layer.getLayers().length > 1) {
                map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
            }
        } catch (error) {
            console.error('Llama Scout campsite coordinates could not be displayed:', error);
        }
    };

    void loadPoints();
})();
