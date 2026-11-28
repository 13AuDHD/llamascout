(() => {
    'use strict';
    const element = document.getElementById('resource-location-map');
    if (!element) return;
    const section = element.closest('.resource-map-section');
    const status = section.querySelector('[data-resource-map-status]');
    const local = section.querySelector('[data-resource-map-local]');
    const reset = section.querySelector('[data-resource-map-reset]');
    const popup = section.querySelector('[data-resource-map-popup]');
    const latitude = Number(element.dataset.latitude);
    const longitude = Number(element.dataset.longitude);
    const initialZoom = Number(element.dataset.initialZoom) || 3;
    if (typeof L === 'undefined' || !popup ||
        !element.dataset.latitude || !element.dataset.longitude ||
        !Number.isFinite(latitude) || !Number.isFinite(longitude) ||
        Math.abs(latitude) > 90 || Math.abs(longitude) > 180 ||
        (latitude === 0 && longitude === 0)) {
        if (status) status.textContent = 'The map is temporarily unavailable. Use the location and directions above.';
        if (local) local.disabled = true;
        if (reset) reset.disabled = true;
        return;
    }
    const coordinates = [latitude, longitude];
    const map = L.map(element, {
        maxZoom: 20,
        zoomSnap: 1,
        scrollWheelZoom: false,
        zoomAnimation: false,
        fadeAnimation: false,
        markerZoomAnimation: false,
        zoomControl: false,
    }).setView(coordinates, initialZoom);
    L.control.zoom({position: 'bottomright'}).addTo(map);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxNativeZoom: 19,
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
        updateWhenZooming: false,
        updateWhenIdle: true,
    }).addTo(map);
    const marker = L.marker(coordinates, {
        title: element.dataset.name || 'Resource',
        alt: element.dataset.name || 'Resource',
        icon: L.divIcon({
            className: 'place-map-marker-shell',
            html: '<span class="place-map-marker resource-location-marker" aria-hidden="true"></span>',
            iconSize: [36, 46],
            iconAnchor: [18, 44],
            popupAnchor: [0, -42],
        }),
    }).addTo(map);
    marker.bindPopup(popup.content.firstElementChild.cloneNode(true), {
        className: 'resource-location-popup',
        maxWidth: 300,
        minWidth: 180,
        autoPanPadding: [12, 12],
    });
    local?.addEventListener('click', () => {
        marker.closePopup();
        map.setView(coordinates, 15);
    });
    reset?.addEventListener('click', () => {
        marker.closePopup();
        map.setView(coordinates, initialZoom);
    });
})();
