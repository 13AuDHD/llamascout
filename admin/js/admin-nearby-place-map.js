(() => {
    'use strict';

    const mapElement = document.getElementById('nearby-map');
    const dataElement = document.getElementById('map-data');

    if (!mapElement || !dataElement || typeof L === 'undefined') {
        return;
    }

    let data;

    try {
        data = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        return;
    }

    const latitude = Number(data.latitude);
    const longitude = Number(data.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return;
    }

    const places = Array.isArray(data.nearby_places)
        ? data.nearby_places
        : [];

    const escapeHtml = (value) => {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };

    const storedTheme = () => {
        try {
            return localStorage.getItem('llama-theme') || 'system';
        } catch (error) {
            return 'system';
        }
    };

    const resolvedTheme = () => {
        const stored = storedTheme();

        if (stored === 'dark' || stored === 'light') {
            return stored;
        }

        try {
            const parentTheme =
                window.parent?.document?.documentElement?.dataset?.theme;

            if (parentTheme === 'dark' || parentTheme === 'light') {
                return parentTheme;
            }
        } catch (error) {
        }

        return window.matchMedia?.('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    };

    const syncTheme = () => {
        document.documentElement.dataset.theme = resolvedTheme();
    };

    syncTheme();

    const map = L.map(mapElement, {
        zoomControl: false,
        minZoom: 3,
        maxZoom: 20,
        zoomSnap: .5
    }).setView([latitude, longitude], 18);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    const sources = {
        light: {
            url: String(data.light_tiles || ''),
            options: {
                maxNativeZoom: 20,
                maxZoom: 20,
                attribution: 'Map data and tiles by their respective providers'
            }
        },
        dark: {
            url: String(data.dark_tiles || ''),
            options: {
                maxNativeZoom: 20,
                maxZoom: 20,
                attribution: 'Map data and tiles by their respective providers'
            }
        },
        satellite: {
            url: String(data.satellite_tiles || ''),
            options: {
                maxNativeZoom: 19,
                maxZoom: 20,
                attribution: 'Tiles &copy; Esri and imagery contributors'
            }
        }
    };

    let selectedStyle = 'auto';
    let tileLayer = null;

    const buttons = Array.from(
        document.querySelectorAll('[data-map-style]')
    );

    const applyTiles = () => {
        syncTheme();

        const key = selectedStyle === 'satellite'
            ? 'satellite'
            : (resolvedTheme() === 'dark' ? 'dark' : 'light');

        const source = sources[key];

        if (!source?.url) {
            return;
        }

        if (tileLayer) {
            map.removeLayer(tileLayer);
        }

        tileLayer = L.tileLayer(source.url, source.options).addTo(map);

        buttons.forEach((button) => {
            const active = button.dataset.mapStyle === selectedStyle;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    applyTiles();

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedStyle = button.dataset.mapStyle || 'auto';
            applyTiles();
        });
    });

    const submittedIcon = L.divIcon({
        className: '',
        html: '<span class="submitted-pin"></span>',
        iconSize: [28, 28],
        iconAnchor: [14, 14]
    });

    const statusClass = (status) => {
        switch (String(status || '').toLowerCase()) {
            case 'draft': return 'is-draft';
            case 'unlisted': return 'is-unlisted';
            case 'archived': return 'is-archived';
            case 'removed': return 'is-removed';
            default: return 'is-current';
        }
    };

    const statusLabel = (status) => {
        const value = String(status || 'unknown');
        return value.charAt(0).toUpperCase() + value.slice(1);
    };

    const placeIcon = (status) => L.divIcon({
        className: '',
        html: `<span class="canonical-pin ${statusClass(status)}"></span>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11]
    });

    const submittedMarker = L.marker(
        [latitude, longitude],
        { icon: submittedIcon, zIndexOffset: 1000 }
    ).addTo(map).bindPopup(
        '<strong>Submitted Place</strong>'
        + '<span>Coordinates being reviewed</span>'
    );

    const bounds = L.latLngBounds(
        submittedMarker.getLatLng(),
        submittedMarker.getLatLng()
    );

    const formatDistance = (miles) => {
        const value = Number(miles);

        if (!Number.isFinite(value)) {
            return '';
        }

        if (value < .2) {
            return `${Math.max(1, Math.round(value * 5280)).toLocaleString()} ft away`;
        }

        return `${value.toFixed(2)} mi away`;
    };

    let historicalCount = 0;

    places.forEach((place) => {
        const placeLatitude = Number(place.latitude);
        const placeLongitude = Number(place.longitude);

        if (!Number.isFinite(placeLatitude) || !Number.isFinite(placeLongitude)) {
            return;
        }

        const status = String(place.status || 'draft').toLowerCase();
        const isPublic = status === 'active' || status === 'featured';
        const isHistorical = status === 'archived' || status === 'removed';

        if (isHistorical) {
            historicalCount += 1;
        }

        const marker = L.marker(
            [placeLatitude, placeLongitude],
            { icon: placeIcon(status) }
        ).addTo(map);

        bounds.extend(marker.getLatLng());

        const name = escapeHtml(place.name || 'Canonical Place');
        const type = escapeHtml(place.type || 'Place');
        const distance = escapeHtml(formatDistance(place.distance_miles));
        const statusText = escapeHtml(statusLabel(status));
        const reason = escapeHtml(place.status_reason || '');
        const changed = escapeHtml(place.status_changed || '');
        const id = Number(place.id || 0);
        const slug = String(place.slug || '').trim();

        let statusClassName = '';
        if (status === 'archived') statusClassName = 'status-warning';
        if (status === 'removed') statusClassName = 'status-removed';

        let popup =
            `<strong>${name}</strong>`
            + `<span class="${statusClassName}">Status: ${statusText}</span>`
            + `<span>${[type, distance].filter(Boolean).join(' · ')}</span>`;

        if (reason) {
            popup += `<span>Latest status note: ${reason}</span>`;
        }

        if (changed) {
            popup += `<span>Status changed: ${changed}</span>`;
        }

        if (id > 0) {
            popup +=
                `<a href="/place.php?id=${encodeURIComponent(id)}" target="_blank" rel="noopener noreferrer">`
                + 'Open canonical Place record'
                + '</a>';
        }

        if (isPublic && slug) {
            popup +=
                `<a href="https://llamascout.com/place.php?slug=${encodeURIComponent(slug)}" target="_blank" rel="noopener noreferrer">`
                + 'Open public Place'
                + '</a>';
        }

        marker.bindPopup(popup);
    });

    if (places.length > 0) {
        map.fitBounds(bounds, {
            padding: [44, 44],
            maxZoom: 18,
            animate: false
        });
    }

    const statusElement = document.getElementById('map-status');

    if (statusElement) {
        const count = places.length;

        if (count === 0) {
            statusElement.textContent = 'No canonical Places within 1 mile';
        } else {
            statusElement.textContent =
                `${count.toLocaleString()} canonical ${count === 1 ? 'Place' : 'Places'} within 1 mile`
                + (historicalCount > 0
                    ? ` · ${historicalCount.toLocaleString()} archived / removed`
                    : '');
        }
    }

    const onThemeChange = () => {
        if (selectedStyle === 'auto') {
            applyTiles();
        } else {
            syncTheme();
        }
    };

    const colorScheme = window.matchMedia?.('(prefers-color-scheme: dark)');

    if (colorScheme?.addEventListener) {
        colorScheme.addEventListener('change', onThemeChange);
    } else if (colorScheme?.addListener) {
        colorScheme.addListener(onThemeChange);
    }

    try {
        const parentRoot = window.parent.document.documentElement;
        const observer = new MutationObserver(onThemeChange);

        observer.observe(parentRoot, {
            attributes: true,
            attributeFilter: ['data-theme']
        });
    } catch (error) {
    }
})();
