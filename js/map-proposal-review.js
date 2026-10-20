(() => {
    'use strict';
    if (!window.L) return;
    let items = [];
    try { items = JSON.parse(document.querySelector('#map-proposals-payload')?.textContent || '[]'); }
    catch (_) { return; }
    for (const item of items) {
        const element = document.querySelector(`[data-map-proposal-preview="${Number(item.id)}"]`);
        if (!element || !item.geometry) continue;
        const map = L.map(element, { scrollWheelZoom: false, zoomControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        try {
            const shape = L.geoJSON(item.geometry, {
                style: { color: '#249b56', weight: 3, fillOpacity: .15 }, interactive: false
            }).addTo(map);
            const bounds = shape.getBounds();
            if (bounds.isValid()) map.fitBounds(bounds, { padding: [25,25], maxZoom: 17 });
            else map.setView([0,0], 2);
        } catch (_) { map.setView([0,0], 2); }
    }
})();
