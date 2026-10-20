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

    const popupBadges = (site) => {
        const badges = [];

        if (site.accessible === true) {
            badges.push('Accessible');
        }

        if (site.electric === true) {
            badges.push('Electric');
        }

        if (site.water === true) {
            badges.push('Water');
        }

        if (site.sewer === true) {
            badges.push('Sewer');
        }

        return badges;
    };

    const buildPopup = (site, id, name) => {
        const popup = document.createElement('div');
        popup.className = 'place-map-feature-popup';

        const heading = document.createElement('strong');
        heading.textContent = name;
        popup.appendChild(heading);

        const details = detailPartsFor(site);
        if (details.length) {
            const meta = document.createElement('span');
            meta.textContent = details.join(' • ');
            popup.appendChild(meta);
        }

        const badges = popupBadges(site);
        if (badges.length) {
            const badgeRow = document.createElement('span');
            badgeRow.textContent = badges.join(' • ');
            popup.appendChild(badgeRow);
        }

        const note = document.createElement('span');
        note.textContent = 'Imported GPS point for this campsite.';
        popup.appendChild(note);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'place-map-edit-areas';
        button.textContent = 'View Site details';
        button.addEventListener('click', () => {
            const target = document.querySelector(
                `[data-campsite-id="${CSS.escape(String(id))}"]`
            );
            if (target) {
                target.dispatchEvent(new MouseEvent('click', { bubbles: false }));
                return;
            }

            const destination = new URL(window.location.href);
            destination.searchParams.set('site', String(id));
            window.location.assign(destination.toString());
        });

        popup.appendChild(button);

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

        map.setView(marker.getLatLng(), Math.min(18, map.getMaxZoom()), {
            animate: false,
        });
        marker.openPopup();
        return true;
    };

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

                point.bindPopup(buildPopup(site, id, name));
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
